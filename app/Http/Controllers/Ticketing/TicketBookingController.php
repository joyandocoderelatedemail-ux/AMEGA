<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Airline;
use App\Models\CustomPackageInquiry;
use App\Models\Destination;
use App\Models\InsurancePlan;
use App\Models\TicketBooking;
use App\Models\TicketDraft;
use App\Models\TicketPassenger;
use App\Models\TicketPassengerDocument;
use App\Models\TicketPayment;
use App\Models\TravelPackage;
use App\Models\User;
use App\Notifications\TicketApprovalRequestedNotification;
use App\Notifications\TicketBookedNotification;
use App\Notifications\TicketIssuedNotification;
use App\Services\ActivityLogger;
use App\Services\BookingAgreementDrafter;
use App\Services\ClientAccountService;
use App\Services\ClientNotifier;
use App\Services\ClientProfileService;
use App\Services\SmsSender;
use App\Services\TicketPaymentRecorder;
use App\Support\DocumentStorage;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketBookingController extends Controller
{
    /**
     * The files a passenger can attach, by form field => the document type kept
     * on the passenger. Every one is held to the same type and size limits as a
     * client's profile scan.
     */
    private const PASSENGER_DOCUMENTS = [
        'passport_file' => 'passport_scan',
        'passport_photo_file' => 'passport_photo',
        'government_id_file' => 'government_id',
        'birth_cert_file' => 'birth_certificate',
        'school_id_file' => 'school_id',
        'visa_file' => 'visa_scan',
        'supporting_doc_file' => 'supporting_documents',
        'exit_clearance_file' => 'exit_clearance',
        'travel_insurance_file' => 'travel_insurance',
        'flight_itinerary_file' => 'flight_itinerary',
        'hotel_voucher_file' => 'hotel_voucher',
    ];

    /**
     * Display listing of ticket bookings.
     */
    public function index(Request $request)
    {
        $query = TicketBooking::with(['passengers.documents', 'createdBy'])->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('booking_reference', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%");
            });
        }

        if ($request->filled('trip_type')) {
            $query->where('trip_type', $request->input('trip_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $tickets = $query->paginate(12)->withQueryString();

        $stats = [
            'total' => TicketBooking::count(),
            'domestic' => TicketBooking::where('travel_type', 'domestic')->count(),
            'passengers' => (int) TicketBooking::sum('total_passengers'),
            'pending' => TicketBooking::where('status', 'pending')->count(),
        ];

        // Tickets saved as pending in the wizard, newest first (only the viewer's own).
        $pendingTickets = TicketDraft::latest('updated_at')->get();

        return view('ticketing.tickets.index', compact('tickets', 'stats', 'pendingTickets'));
    }

    /**
     * Show the multi-step booking wizard form.
     */
    public function create(Request $request)
    {
        // Arriving from "Register client" (or a link) with the client chosen.
        $preselectedClient = null;
        if ($request->filled('client')) {
            $client = User::where('role', 'client')->find($request->integer('client'));
            $preselectedClient = $client ? ClientProfileService::ticketProfile($client) : null;
        }

        $destinations = Destination::orderBy('name')->get();
        $domesticDestinations = Destination::where('type', 'domestic')->orderBy('name')->get();
        $internationalDestinations = Destination::where('type', 'international')->orderBy('name')->get();

        $airlines = Airline::offered()->get(['id', 'name', 'code', 'booking_url', 'agent_portal_url']);
        $insurancePlans = InsurancePlan::offered()->get(['key', 'name', 'price_per_pax', 'coverage', 'is_popular']);

        $packages = TravelPackage::with(['destination', 'airline:id,name,code'])
            ->where('status', 'active')
            ->orderBy('title')
            ->get();

        // Continuing a ticket saved as pending: the wizard reopens where it was.
        $pendingTicket = null;
        if ($request->filled('pending') && ($draft = TicketDraft::find($request->integer('pending')))) {
            $pendingTicket = [
                'id' => $draft->id,
                'step' => $draft->step,
                'payload' => $draft->payload,
                'saved_at' => $draft->updated_at->format('M j, g:i A'),
            ];
        }

        // Paused tickets waiting on requirements, reachable from the wizard.
        $pendingCount = TicketDraft::count();

        return view('ticketing.tickets.create', compact('destinations', 'domesticDestinations', 'internationalDestinations', 'packages', 'airlines', 'insurancePlans', 'preselectedClient', 'pendingTicket', 'pendingCount'));
    }

    /**
     * Store a newly created ticket booking in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $travelType = $request->input('travel_type', 'domestic');

        // Staff often need to price a trip before the client has gathered any
        // documents. A quotation saves the booking on the trip details alone,
        // skipping the document and manifest checks; the flag on the record is
        // what stops it from later being issued as a ticket.
        $asQuotation = $request->boolean('save_as_quotation');

        // 1. Validate General Trip and Contact Fields
        $rules = [
            'travel_type' => ['required', 'string', 'in:domestic,international'],
            'package_type' => ['required', 'string', 'in:with_package,without_package,custom_package'],
            'travel_package_id' => ['nullable'],
            'package_name' => ['nullable', 'string', 'max:255'],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'trip_type' => ['required', 'string', 'in:one_way,round_trip,multi_city'],
            'departure_date' => ['required', 'date', 'after_or_equal:today'],
            'return_date' => ['nullable', 'date'],
            'total_passengers' => ['required', 'integer', 'min:1', 'max:50'],
            'adults_count' => ['required', 'integer', 'min:1', 'max:50'],
            'children_count' => ['required', 'integer', 'min:0', 'max:50'],
            'infants_count' => ['required', 'integer', 'min:0', 'max:50'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:50'],
            'travel_tax_included' => ['nullable', 'boolean'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'airline_restrictions' => ['nullable', 'array', 'max:30'],
            'airline_restrictions.*' => ['nullable', 'string', 'max:500'],
            'has_insurance' => ['nullable', 'boolean'],
            'insurance_plan' => ['nullable', 'string', Rule::exists('insurance_plans', 'key')->where('is_active', true)],
            'client_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'client')],
            'fare_prices' => ['nullable', 'array'],
            'fare_prices.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // PWD / senior citizen passengers are taken out of the adults, so there cannot be more of them.
            'pwd_sc_count' => ['nullable', 'integer', 'min:0', 'lte:adults_count'],
            'extras_pricing' => ['nullable', 'array', 'max:40'],
            'extras_pricing.*.mode' => ['nullable', 'in:free,paid'],
            'extras_pricing.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'service_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.client_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'client')],
            ...$this->flightRules(),
        ];

        if ($request->input('trip_type') === 'round_trip') {
            $rules['return_date'] = ['required', 'date', 'after:departure_date'];
        }

        // A booking records the flight that was booked; a quotation is priced before one is held.
        if (! $asQuotation) {
            // The agent confirms the information is correct and is accountable for it.
            $rules['agent_acknowledged'] = ['accepted'];
            $rules['airline_id'] = ['required', 'integer', Rule::exists('airlines', 'id')];
            $rules['flight_number'] = ['required', 'string', 'max:20'];
            $rules['departure_time'] = ['required', 'date_format:H:i'];
            $rules['arrival_time'] = ['required', 'date_format:H:i'];
            if ($request->input('trip_type') === 'round_trip') {
                $rules['return_flight_number'] = ['required', 'string', 'max:20'];
                $rules['return_departure_time'] = ['required', 'date_format:H:i'];
                $rules['return_arrival_time'] = ['required', 'date_format:H:i'];
            }
        }

        if ($travelType === 'international') {
            $rules['destination_country'] = ['required', 'string', 'max:255'];
            $rules['destination_city'] = ['required', 'string', 'max:255'];
            $rules['arrival_airport'] = ['required', 'string', 'max:255'];
            $rules['preferred_airline'] = ['nullable', 'string', 'max:255'];
            $rules['travel_class'] = ['nullable', 'string', 'in:economy,premium_economy,business,first_class'];
            $rules['preferred_flight_time'] = ['nullable', 'string', 'in:anytime,morning,afternoon,evening'];
            $rules['emergency_contact_name'] = ['required', 'string', 'max:255'];
            $rules['emergency_contact_relationship'] = ['required', 'string', 'max:100'];
            $rules['emergency_contact_phone'] = ['required', 'string', 'max:50'];
            $rules['emergency_contact_email'] = ['nullable', 'email', 'max:255'];
        }

        if ($asQuotation) {
            // A quote needs the trip and someone to address it to. Everything
            // else is gathered later, when the booking is completed.
            $rules['contact_email'] = ['nullable', 'email', 'max:255'];
            $rules['contact_phone'] = ['nullable', 'string', 'max:50'];
            $rules['passengers'] = ['nullable', 'array'];

            foreach (['emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone'] as $field) {
                if (isset($rules[$field])) {
                    $rules[$field] = ['nullable', 'string', 'max:255'];
                }
            }
        }

        $documentMessages = [];
        foreach (array_keys(self::PASSENGER_DOCUMENTS) as $field) {
            $rules["passengers.*.{$field}"] = 'nullable|'.ClientProfileService::SCAN_RULE;
            $documentMessages["passengers.*.{$field}.mimes"] = 'Attach a JPG, PNG, WEBP or PDF file.';
            $documentMessages["passengers.*.{$field}.max"] = 'The file is larger than 5 MB.';
            $documentMessages["passengers.*.{$field}.uploaded"] = 'The file could not be uploaded. Check that it is under 5 MB and try again.';
        }

        $validated = $request->validate($rules, $documentMessages);

        // Validate passenger counts match
        $totalInput = (int) $validated['adults_count'] + (int) $validated['children_count'] + (int) $validated['infants_count'];
        if ($totalInput !== (int) $validated['total_passengers']) {
            throw ValidationException::withMessages([
                'total_passengers' => 'The sum of Adults, Children, and Infants must equal Total Passengers.',
            ]);
        }

        $departureDate = Carbon::parse($validated['departure_date']);

        // 2. Validate Individual Passengers & Required Documents
        // Registered clients picked as travellers fill passenger slots; their
        // profile scans stand in for an upload when staff chose to reuse them.
        // The first client picked is the booker.
        $selectedClient = ! empty($validated['client_user_id']) ? User::find($validated['client_user_id']) : null;

        $docErrors = [];
        $passengersData = $request->input('passengers', []);

        // A quotation is often priced before anyone's name is known, but the
        // wizard still sends a row for every passenger slot. Unnamed rows are
        // left off; saved blank they break the passengers' not-null names.
        if ($asQuotation) {
            $passengersData = array_filter(
                $passengersData,
                fn ($passenger): bool => filled($passenger['first_name'] ?? null) && filled($passenger['last_name'] ?? null),
            );
        }
        $passengerClients = [];
        $profileScans = [];
        foreach ($passengersData as $index => $passenger) {
            $passengerClients[$index] = $this->passengerClient($passenger, $index, $selectedClient);
            $profileScans[$index] = $this->profileScansInUse($request, $index, $passengerClients[$index]);
        }

        foreach ($passengersData as $index => $passenger) {
            $num = $index + 1;
            $firstName = trim($passenger['first_name'] ?? '');
            $lastName = trim($passenger['last_name'] ?? '');

            if (empty($firstName) || empty($lastName)) {
                $docErrors["passengers.{$index}.name"] = "Passenger #{$num} must have a first name and last name.";
            }

            $fullName = "{$firstName} {$lastName}";
            $type = $passenger['passenger_type'] ?? 'adult';

            // The fare category must match the traveller's age on departure.
            if (! empty($passenger['date_of_birth']) && strtotime($passenger['date_of_birth']) !== false) {
                $ageType = TicketPassenger::typeForAge(Carbon::parse($passenger['date_of_birth']), $departureDate);

                if ($ageType !== $type) {
                    $docErrors["passengers.{$index}.passenger_type"] = "Passenger #{$num} ({$fullName}) is booked as ".ucfirst($type).', but their date of birth makes them '.ucfirst($ageType).' on the departure date.';
                }
            }

            // Most uploads are not checked here: a booking can go on to payment
            // while documents are still being gathered; what is missing is worked
            // out by TicketPassenger::missingDocuments() and stops the ticket being
            // issued (see issue()). The passport scan is the exception, because
            // nothing else about the booking can be trusted without it.
            $needsPassport = $travelType === 'international' || ($passenger['nationality_type'] ?? null) === 'foreign_national';
            if (
                $needsPassport
                && ! $request->hasFile("passengers.{$index}.passport_file")
                && ! isset($profileScans[$index]['passport_scan'])
            ) {
                $docErrors["passengers.{$index}.passport_file"] = "Passenger #{$num} ({$fullName}): upload the passport scan to continue.";
            }

            // 2.1 Passport 6-month validity check (Mandatory for International & Filipino Domestic)
            if (! empty($passenger['passport_expiry_date'])) {
                $expiryDate = Carbon::parse($passenger['passport_expiry_date']);
                if ($expiryDate->lt($departureDate->copy()->addMonths(6))) {
                    $docErrors["passengers.{$index}.passport_expiry_date"] = "Passenger #{$num} ({$fullName}) passport must have at least six (6) months validity before departure ({$departureDate->format('M d, Y')}). Passport must be renewed before travel.";
                }
            } else {
                // Asked of every passenger, not only those who need the passport scan.
                $docErrors["passengers.{$index}.passport_expiry_date"] = "Passenger #{$num} ({$fullName}) passport expiration date is required.";
            }

            if ($needsPassport && blank($passenger['passport_number'] ?? null)) {
                $docErrors["passengers.{$index}.passport_number"] = "Passenger #{$num} ({$fullName}): enter the passport number.";
            }
        }

        if (! $asQuotation && ! empty($docErrors)) {
            throw ValidationException::withMessages($docErrors);
        }

        // 3. Save to Database within Transaction
        $booking = DB::transaction(function () use ($request, $validated, $passengersData, $travelType, $asQuotation, $selectedClient, $passengerClients, $profileScans) {
            $reference = TicketBooking::generateReference($travelType === 'domestic' ? 'DOM' : 'INT');

            $isCustom = ($validated['package_type'] ?? '') === 'custom_package' || $request->input('travel_package_id') === 'custom';

            $packageTitle = null;
            $travelPackageId = null;
            $customSpecs = null;

            if ($isCustom) {
                $validated['package_type'] = 'custom_package';
                $hotelPart = $request->input('custom_hotel_name') ?: $request->input('custom_preferred_hotel');
                $packageTitle = $hotelPart ? "Custom Package ({$hotelPart})" : 'Customized Tour Package';
                $customSpecs = [
                    'hotel_name' => $request->input('custom_hotel_name'),
                    'preferred_hotel' => $request->input('custom_preferred_hotel'),
                    'has_breakfast' => $request->boolean('custom_has_breakfast'),
                    'bed_config' => $request->input('custom_bed_config'),
                    'check_in_date' => $request->input('custom_check_in_date'),
                    'check_out_date' => $request->input('custom_check_out_date'),
                    'smoking_preference' => $request->input('custom_smoking_preference', 'non_smoking'),
                    'pet_friendly' => $request->boolean('custom_pet_friendly'),
                    'has_transportation' => $request->boolean('custom_has_transportation'),
                    'transportation_type' => $request->input('custom_transportation_type'),
                    'special_requests' => $request->input('custom_special_requests'),
                    'estimated_budget' => $request->input('custom_estimated_budget'),
                ];
            } elseif (! empty($validated['travel_package_id']) && is_numeric($validated['travel_package_id'])) {
                $travelPackageId = (int) $validated['travel_package_id'];
                $pkg = TravelPackage::find($travelPackageId);
                $packageTitle = $pkg?->title;
            }

            // Pricing Calculations
            $estimatedFare = (float) ($request->input('estimated_fare', 0));

            // Priced per passenger type, the fare is the sum of the subtotals.
            $fareBreakdown = $this->fareBreakdown($request, $validated);
            if ($fareBreakdown !== []) {
                $estimatedFare = round(array_sum(array_column($fareBreakdown, 'subtotal')), 2);
            }
            $taxesAmount = (float) ($request->input('taxes_amount', 0));
            $visaFee = (float) ($request->input('visa_assistance_fee', 0));
            $insuranceFee = (float) ($request->input('insurance_fee', 0));
            $otherCharges = (float) ($request->input('other_charges', 0));
            $serviceFee = round(max(0, (float) $request->input('service_fee', 0)), 2);
            $totalAmount = (float) ($request->input('total_amount', 0));

            // Services and requests the agent charged for, worked out here from the
            // choices rather than taken from a figure the browser sent.
            $extrasPricing = $this->extrasPricing($request);
            $extrasAmount = round(array_sum(array_column($extrasPricing, 'price')), 2);

            if ($totalAmount <= 0) {
                $totalAmount = $estimatedFare + $taxesAmount + $visaFee + $insuranceFee + $otherCharges + $serviceFee + $extrasAmount;
            }

            $firstPassport = null;
            if (! empty($passengersData[0]['passport_number'])) {
                $firstPassport = $passengersData[0]['passport_number'];
            }

            $clientUser = $selectedClient ?? ClientAccountService::findOrCreateClient([
                'name' => $validated['contact_name'],
                'email' => $validated['contact_email'] ?? null,
                'phone' => $validated['contact_phone'] ?? null,
                'passport_number' => $firstPassport,
            ]);

            // A walk-in booker who is also the lead passenger gets that
            // passenger's details on their client record.
            $leadIndex = array_key_first($passengersData);
            if ($leadIndex !== null && ! isset($passengerClients[$leadIndex]) && ClientAccountService::isSamePerson(
                $passengersData[$leadIndex]['first_name'] ?? null,
                $passengersData[$leadIndex]['last_name'] ?? null,
                $clientUser->full_name,
            )) {
                $passengerClients[$leadIndex] = $clientUser;
            }

            $booking = TicketBooking::create([
                'booking_reference' => $reference,
                'created_by' => Auth::id(),
                'agent_acknowledged_by' => $asQuotation ? null : Auth::id(),
                'agent_acknowledged_at' => $asQuotation ? null : now(),
                // Checked by an admin before the cashier takes payment (not quotations).
                'approval_status' => $asQuotation ? null : TicketBooking::APPROVAL_PENDING,
                'approval_requested_at' => $asQuotation ? null : now(),
                'user_id' => $clientUser->id,
                'travel_type' => $validated['travel_type'],
                'package_type' => $validated['package_type'],
                'travel_package_id' => $travelPackageId,
                'package_name' => $packageTitle ?? ($validated['package_name'] ?? null),
                'custom_package_specs' => $customSpecs,
                'origin' => $validated['origin'],
                'destination' => $validated['destination'],
                'destination_country' => $validated['destination_country'] ?? null,
                'destination_city' => $validated['destination_city'] ?? null,
                'arrival_airport' => $validated['arrival_airport'] ?? null,
                'preferred_airline' => $validated['preferred_airline'] ?? null,
                ...$this->bookedFlight($validated),
                'trip_type' => $validated['trip_type'],
                'travel_class' => $validated['travel_class'] ?? 'economy',
                'preferred_flight_time' => $validated['preferred_flight_time'] ?? 'anytime',
                'multi_city_segments' => $request->input('multi_city_segments'),
                'departure_date' => $validated['departure_date'],
                'return_date' => $validated['return_date'] ?? null,
                'total_passengers' => $validated['total_passengers'],
                'adults_count' => $validated['adults_count'],
                'children_count' => $validated['children_count'],
                'infants_count' => $validated['infants_count'],
                'contact_name' => $validated['contact_name'],
                // Optional on a quotation, so these may be absent entirely.
                'contact_email' => $validated['contact_email'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'emergency_contact_email' => $validated['emergency_contact_email'] ?? null,
                'has_insurance' => $request->boolean('has_insurance'),
                'insurance_plan' => $request->input('insurance_plan'),
                'selected_services' => $request->input('selected_services', []),
                'travel_tax_included' => ! empty($validated['travel_tax_included']),
                'special_requests_list' => $request->input('special_requests_list', []),
                'special_requests' => $validated['special_requests'] ?? null,
                'airline_restrictions' => self::cleanRestrictions($validated['airline_restrictions'] ?? []),
                'estimated_fare' => $estimatedFare,
                'taxes_amount' => $taxesAmount,
                'visa_assistance_fee' => $visaFee,
                'insurance_fee' => $insuranceFee,
                'other_charges' => $otherCharges,
                'service_fee' => $serviceFee,
                'extras_pricing' => $extrasPricing ?: null,
                'fare_breakdown' => $fareBreakdown ?: null,
                'extras_amount' => $extrasAmount,
                'total_amount' => $totalAmount,
                'status' => TicketBooking::STATUS_PENDING,
                'is_quotation' => $asQuotation,
            ]);

            if ($isCustom && ! empty($customSpecs)) {
                CustomPackageInquiry::create([
                    'user_id' => Auth::id(),
                    'client_name' => $validated['contact_name'],
                    'client_email' => $validated['contact_email'] ?? 'ticketing@amegatravel.com',
                    'client_phone' => $validated['contact_phone'] ?? null,
                    'destination_name' => $validated['destination'],
                    'travel_type' => $validated['travel_type'],
                    'check_in_date' => ! empty($customSpecs['check_in_date']) ? $customSpecs['check_in_date'] : null,
                    'check_out_date' => ! empty($customSpecs['check_out_date']) ? $customSpecs['check_out_date'] : null,
                    'number_of_pax' => $validated['total_passengers'],
                    'adults_count' => $validated['adults_count'],
                    'children_count' => $validated['children_count'],
                    'infants_count' => $validated['infants_count'],
                    'hotel_name' => $customSpecs['hotel_name'] ?? null,
                    'preferred_hotel' => $customSpecs['preferred_hotel'] ?? null,
                    'has_breakfast' => $customSpecs['has_breakfast'] ?? false,
                    'bed_config' => $customSpecs['bed_config'] ?? null,
                    'smoking_preference' => $customSpecs['smoking_preference'] ?? 'non_smoking',
                    'pet_friendly' => $customSpecs['pet_friendly'] ?? false,
                    'has_transportation' => $customSpecs['has_transportation'] ?? false,
                    'transportation_type' => $customSpecs['transportation_type'] ?? null,
                    'special_requests' => $customSpecs['special_requests'] ?? null,
                    'estimated_budget' => ! empty($customSpecs['estimated_budget']) ? $customSpecs['estimated_budget'] : null,
                    'status' => 'booked',
                    'agent_notes' => "Auto-linked to ticket booking {$reference}",
                ]);
            }

            // Save Passengers and their respective uploaded documents. Rows keep
            // their form index for file lookups, but are numbered in order.
            $passengerNumber = 0;
            foreach ($passengersData as $index => $data) {
                $passenger = $booking->passengers()->create([
                    'passenger_number' => ++$passengerNumber,
                    'passenger_type' => $data['passenger_type'] ?? 'adult',
                    'nationality_type' => $data['nationality_type'] ?? 'filipino',
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'last_name' => $data['last_name'],
                    'suffix' => $data['suffix'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'date_of_birth' => ! empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                    'passport_number' => $data['passport_number'] ?? null,
                    'passport_expiry_date' => ! empty($data['passport_expiry_date']) ? $data['passport_expiry_date'] : null,
                    'passport_country' => $data['passport_country'] ?? null,
                    'government_id_type' => $data['government_id_type'] ?? null,
                    'government_id_number' => $data['government_id_number'] ?? null,
                    'visa_type' => $data['visa_type'] ?? null,
                    'visa_status' => $data['visa_status'] ?? null,
                    'visa_assistance_type' => $data['visa_assistance_type'] ?? null,
                    'intended_stay_days' => ! empty($data['intended_stay_days']) ? (int) $data['intended_stay_days'] : null,
                    'purpose_of_travel' => $data['purpose_of_travel'] ?? null,
                    'stay_duration_months' => ! empty($data['stay_duration_months']) ? (int) $data['stay_duration_months'] : null,
                    'requires_exit_clearance' => ! empty($data['requires_exit_clearance']),
                    'travel_tax_included' => ! empty($data['travel_tax_included']),
                ]);

                // Handle all file uploads for this passenger

                $this->copyProfileScans($request, $index, $passenger, $booking, $profileScans[$index] ?? []);

                // Gender, birth date or passport details staff entered for a client whose profile had none.
                $passengerClient = $passengerClients[$index] ?? null;
                if ($passengerClient) {
                    ClientAccountService::fillFromPassenger($passengerClient, $passenger);
                }

                foreach (self::PASSENGER_DOCUMENTS as $inputKey => $docType) {
                    if ($request->hasFile("passengers.{$index}.{$inputKey}")) {
                        $file = $request->file("passengers.{$index}.{$inputKey}");
                        $storedPath = $file->store("tickets/{$booking->booking_reference}/p{$passenger->passenger_number}", DocumentStorage::diskName());

                        $passenger->documents()->create([
                            'document_type' => $docType,
                            'file_path' => $storedPath,
                            'original_name' => $file->getClientOriginalName(),
                            'file_size' => $file->getSize(),
                            'mime_type' => $file->getClientMimeType(),
                            'status' => 'uploaded',
                        ]);
                    }
                }
            }

            return $booking;
        });

        // The pending copy this ticket was continued from is done with.
        if ($request->filled('pending_ticket_id')) {
            TicketDraft::whereKey($request->integer('pending_ticket_id'))->delete();
        }

        ActivityLogger::log(
            'Ticketing',
            $asQuotation ? 'QUOTE' : 'CREATE',
            ($asQuotation ? 'Created quotation ' : 'Created ticket booking ')
                ."{$booking->booking_reference} for {$booking->contact_name} ({$booking->origin} to {$booking->destination})"
        );

        // A quotation is not a booking yet, so the client hears nothing until it is one.
        if (! $asQuotation) {
            ClientNotifier::send($booking->contact_email, $booking->contact_name, new TicketBookedNotification($booking));
            $this->notifyApprovers($booking);
        }

        if ($asQuotation) {
            return redirect()->route('ticketing.agreements.create', $booking)
                ->with('clear_booking_draft', true)
                ->with('success', "Quotation {$booking->booking_reference} started. Travel documents are still required before this can be issued as a ticket.");
        }

        $message = "Ticket booking {$booking->booking_reference} created and sent to the admin for approval. Once approved, the cashier records the payment.";

        if (! $booking->hasAllRequiredDocuments()) {
            $message .= ' Some required documents are still missing: the ticket cannot be issued until they are uploaded.';
        }

        return redirect()->route('ticketing.tickets.show', $booking)
            ->with('clear_booking_draft', true)
            ->with('success', $message);
    }

    /**
     * Attach a document to a passenger after the booking was made, for the ones
     * that were not at hand in the wizard. A document of the same kind replaces
     * the one before it, except supporting documents, which accumulate.
     */
    public function storeDocument(Request $request, TicketBooking $ticket, TicketPassenger $passenger): RedirectResponse
    {
        if ($reason = $this->lockedReason($ticket)) {
            return back()->with('error', $reason);
        }

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(array_values(self::PASSENGER_DOCUMENTS))],
            'file' => ['required', ...explode('|', ClientProfileService::SCAN_RULE)],
        ], [
            'file.required' => 'Choose a file to upload.',
            'file.mimes' => 'Attach a JPG, PNG, WEBP or PDF file.',
            'file.max' => 'The file is larger than 5 MB.',
            'file.uploaded' => 'The file could not be uploaded. Check that it is under 5 MB and try again.',
        ]);

        $file = $request->file('file');
        $disk = DocumentStorage::disk();
        $path = $file->store("tickets/{$ticket->booking_reference}/p{$passenger->passenger_number}", DocumentStorage::diskName());

        $replaced = $validated['document_type'] === 'supporting_documents'
            ? collect()
            : $passenger->documents()->where('document_type', $validated['document_type'])->get();

        $document = $passenger->documents()->create([
            'document_type' => $validated['document_type'],
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'status' => 'uploaded',
        ]);

        // The old file goes only once the new one is on record.
        foreach ($replaced as $old) {
            if ($disk->exists($old->file_path)) {
                $disk->delete($old->file_path);
            }
            $old->delete();
        }

        ActivityLogger::log('Ticketing', 'UPLOAD_DOCUMENT', "Uploaded {$document->formatted_type} for {$passenger->full_name} on {$ticket->booking_reference}");

        return back()->with('success', "{$document->formatted_type} uploaded for {$passenger->full_name}.");
    }

    /**
     * The fare per passenger type: head count, price each and subtotal. PWD / senior
     * citizen passengers are charged their own fare instead of the adult one, so
     * they come out of the adult count. Empty until a price is entered.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, array{qty: int, price: float, subtotal: float}>
     */
    private function fareBreakdown(Request $request, array $validated): array
    {
        $prices = (array) $request->input('fare_prices', []);
        $pwd = min((int) ($validated['pwd_sc_count'] ?? 0), (int) $validated['adults_count']);
        $counts = [
            'adult' => (int) $validated['adults_count'] - $pwd,
            'child' => (int) $validated['children_count'],
            'infant' => (int) $validated['infants_count'],
            'pwd_sc' => $pwd,
        ];

        $breakdown = [];
        foreach ($counts as $type => $qty) {
            $price = round(max(0, (float) ($prices[$type] ?? 0)), 2);
            $breakdown[$type] = ['qty' => $qty, 'price' => $price, 'subtotal' => round($price * $qty, 2)];
        }

        return array_sum(array_column($breakdown, 'price')) > 0 ? $breakdown : [];
    }

    /**
     * Free or priced, for each concierge service and special request that was
     * actually selected. A price only counts when the item is marked Paid.
     *
     * @return array<string, array{free: bool, price: float}>
     */
    private function extrasPricing(Request $request): array
    {
        $selected = [...(array) $request->input('selected_services', []), ...(array) $request->input('special_requests_list', [])];
        $pricing = [];

        foreach ((array) $request->input('extras_pricing', []) as $key => $row) {
            if (! in_array($key, $selected, true) || ! is_array($row)) {
                continue;
            }

            $paid = ($row['mode'] ?? 'free') === 'paid';
            $pricing[$key] = ['free' => ! $paid, 'price' => $paid ? round(max(0, (float) ($row['price'] ?? 0)), 2) : 0.0];
        }

        return $pricing;
    }

    /**
     * Why a booking can no longer be edited, or null while it still can.
     */
    private function lockedReason(TicketBooking $ticket): ?string
    {
        return match (true) {
            $ticket->isCancelled() => 'A cancelled booking cannot be edited.',
            $ticket->isIssued() => 'An issued ticket can no longer be edited. Cancel it and book again if something was wrong.',
            default => null,
        };
    }

    /**
     * Rules for the flight staff actually booked, all optional: a quote is
     * often priced before anything is held.
     *
     * @return array<string, mixed>
     */
    private function flightRules(): array
    {
        return [
            'airline_id' => ['nullable', 'integer', Rule::exists('airlines', 'id')],
            'airline_pnr' => ['nullable', 'string', 'alpha_num', 'max:20'],
            'flight_number' => ['nullable', 'string', 'max:20'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'return_flight_number' => ['nullable', 'string', 'max:20'],
            'return_departure_time' => ['nullable', 'date_format:H:i'],
            'return_arrival_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Correct a booking that has not been issued: the contact, the dates, the
     * flight that was booked and each passenger's personal details.
     */
    public function edit(TicketBooking $ticket): View|RedirectResponse
    {
        if ($reason = $this->lockedReason($ticket)) {
            return redirect()->route('ticketing.tickets.show', $ticket)->with('error', $reason);
        }

        $ticket->load('passengers.documents', 'bookingAgreement');

        // The airline already on the booking stays selectable even if it was switched off since.
        $airlines = Airline::offered()->get();
        if ($ticket->airline && ! $airlines->contains('id', $ticket->airline_id)) {
            $airlines->push($ticket->airline);
        }

        return view('ticketing.tickets.edit', compact('ticket', 'airlines'));
    }

    /**
     * Save the corrections, step by step as in the wizard: travel type, trip and
     * flight, restrictions, passengers, contact and pricing. The number of
     * passengers stays as booked (documents and payments hang off each one);
     * documents are uploaded from the ticket page.
     */
    public function update(Request $request, TicketBooking $ticket): RedirectResponse
    {
        if ($reason = $this->lockedReason($ticket)) {
            return redirect()->route('ticketing.tickets.show', $ticket)->with('error', $reason);
        }

        $isQuotation = $ticket->isQuotation();
        $tripType = (string) $request->input('trip_type', $ticket->trip_type);
        $isInternational = $request->input('travel_type', $ticket->travel_type) === 'international';

        $rules = [
            // 1. Travellers
            'travel_type' => ['required', Rule::in(['domestic', 'international'])],

            // 3. Destination & flight
            'trip_type' => ['required', Rule::in(['round_trip', 'one_way', 'multi_city'])],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'preferred_flight_time' => ['nullable', Rule::in(['anytime', 'morning', 'afternoon', 'evening'])],
            'travel_class' => ['nullable', Rule::in(['economy', 'premium_economy', 'business', 'first_class'])],
            'departure_date' => ['required', 'date', 'after_or_equal:today'],
            'multi_city_segments' => ['nullable', 'array', 'max:10'],
            'multi_city_segments.*.from' => ['nullable', 'string', 'max:100'],
            'multi_city_segments.*.to' => ['nullable', 'string', 'max:100'],
            'multi_city_segments.*.date' => ['nullable', 'date'],
            'destination_country' => [$isInternational ? 'required' : 'nullable', 'string', 'max:255'],
            'destination_city' => [$isInternational ? 'required' : 'nullable', 'string', 'max:255'],
            'arrival_airport' => [$isInternational ? 'required' : 'nullable', 'string', 'max:255'],
            'preferred_airline' => ['nullable', 'string', 'max:255'],

            // 4. Airline restrictions
            'airline_restrictions' => ['nullable', 'array', 'max:30'],
            'airline_restrictions.*' => ['nullable', 'string', 'max:500'],

            // 5. Passengers
            'passengers' => [$isQuotation ? 'nullable' : 'required', 'array'],
            'passengers.*.id' => ['required', 'integer', Rule::exists('ticket_passengers', 'id')->where('ticket_booking_id', $ticket->id)],
            'passengers.*.first_name' => ['required', 'string', 'max:255'],
            'passengers.*.middle_name' => ['nullable', 'string', 'max:255'],
            'passengers.*.last_name' => ['required', 'string', 'max:255'],
            'passengers.*.suffix' => ['nullable', 'string', 'max:20'],
            'passengers.*.gender' => ['nullable', Rule::in(array_keys(User::GENDERS))],
            'passengers.*.nationality_type' => ['nullable', Rule::in(['filipino', 'foreign_national'])],
            'passengers.*.date_of_birth' => ['nullable', 'date', 'before:today'],
            'passengers.*.passport_number' => ['nullable', 'string', 'max:50'],
            'passengers.*.passport_expiry_date' => ['nullable', 'date'],

            // 6. Contact & extras
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => [$isQuotation ? 'nullable' : 'required', 'email', 'max:255'],
            'contact_phone' => [$isQuotation ? 'nullable' : 'required', 'string', 'max:50'],
            'emergency_contact_name' => [$isInternational && ! $isQuotation ? 'required' : 'nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => [$isInternational && ! $isQuotation ? 'required' : 'nullable', 'string', 'max:100'],
            'emergency_contact_phone' => [$isInternational && ! $isQuotation ? 'required' : 'nullable', 'string', 'max:50'],
            'emergency_contact_email' => ['nullable', 'email', 'max:255'],
            'special_requests' => ['nullable', 'string', 'max:2000'],

            // 7. Pricing
            'fare_prices' => ['nullable', 'array'],
            'fare_prices.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'estimated_fare' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'taxes_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'visa_assistance_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'insurance_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'other_charges' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'service_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ] + $this->flightRules();

        if ($tripType === 'round_trip') {
            $rules['return_date'] = ['required', 'date', 'after:departure_date'];
        }

        $validated = $request->validate($rules);
        $departureDate = Carbon::parse($validated['departure_date']);

        // The same checks the wizard makes, against the corrected trip.
        $errors = [];
        $submitted = collect($validated['passengers'] ?? [])->keyBy('id');

        if ($tripType === 'multi_city') {
            $legs = collect($validated['multi_city_segments'] ?? [])->filter(fn (array $leg): bool => filled($leg['from'] ?? null) || filled($leg['to'] ?? null));
            if ($legs->count() < 2) {
                $errors['multi_city_segments'] = 'A multi-city trip needs at least two legs.';
            }
        }

        foreach ($ticket->passengers as $position => $passenger) {
            $data = $submitted->get($passenger->id);
            if ($data === null) {
                continue;
            }

            $key = collect($validated['passengers'])->search(fn (array $row): bool => (int) $row['id'] === $passenger->id);
            $label = 'Passenger #'.($position + 1).' ('.trim($data['first_name'].' '.$data['last_name']).')';
            $foreign = ($data['nationality_type'] ?? $passenger->nationality_type) === 'foreign_national';

            if (! empty($data['date_of_birth'])) {
                $ageType = TicketPassenger::typeForAge(Carbon::parse($data['date_of_birth']), $departureDate);

                if ($ageType !== $passenger->passenger_type) {
                    $errors["passengers.{$key}.date_of_birth"] = "{$label} is booked as ".ucfirst($passenger->passenger_type).', but that date of birth makes them '.ucfirst($ageType).' on the departure date.';
                }
            }

            if ($isQuotation) {
                continue;
            }

            // A passport for international travel and for foreign nationals; its expiry for everyone.
            if (($isInternational || $foreign) && blank($data['passport_number'] ?? null)) {
                $errors["passengers.{$key}.passport_number"] = "{$label} needs a passport number.";
            }

            if (empty($data['passport_expiry_date'])) {
                $errors["passengers.{$key}.passport_expiry_date"] = "{$label} needs a passport expiration date.";
            } elseif (Carbon::parse($data['passport_expiry_date'])->lt($departureDate->copy()->addMonths(6))) {
                $errors["passengers.{$key}.passport_expiry_date"] = "{$label}: the passport must be valid for at least six (6) months after departure ({$departureDate->format('M d, Y')}).";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $changed = [];

        DB::transaction(function () use ($ticket, $validated, $submitted, $tripType, $isInternational, &$changed): void {
            // Pricing: per passenger type when the booking was priced that way, otherwise the one fare.
            $fareBreakdown = $ticket->fare_breakdown ?: [];
            $estimatedFare = round((float) ($validated['estimated_fare'] ?? $ticket->estimated_fare), 2);
            if ($fareBreakdown !== [] && isset($validated['fare_prices'])) {
                foreach ($fareBreakdown as $type => $row) {
                    $price = round(max(0, (float) ($validated['fare_prices'][$type] ?? $row['price'] ?? 0)), 2);
                    $fareBreakdown[$type] = ['qty' => (int) ($row['qty'] ?? 0), 'price' => $price, 'subtotal' => round($price * (int) ($row['qty'] ?? 0), 2)];
                }
                $estimatedFare = round(array_sum(array_column($fareBreakdown, 'subtotal')), 2);
            }
            $charges = [
                'taxes_amount' => round((float) ($validated['taxes_amount'] ?? $ticket->taxes_amount), 2),
                'visa_assistance_fee' => round((float) ($validated['visa_assistance_fee'] ?? $ticket->visa_assistance_fee), 2),
                'insurance_fee' => round((float) ($validated['insurance_fee'] ?? $ticket->insurance_fee), 2),
                'other_charges' => round((float) ($validated['other_charges'] ?? $ticket->other_charges), 2),
                'service_fee' => round((float) ($validated['service_fee'] ?? $ticket->service_fee), 2),
            ];
            // The total moves only by what was changed, so a booking priced as one lump
            // sum (or with an agreed adjustment on top of its parts) keeps that.
            $partsBefore = (float) $ticket->estimated_fare + (float) $ticket->taxes_amount + (float) $ticket->visa_assistance_fee
                + (float) $ticket->insurance_fee + (float) $ticket->other_charges + (float) $ticket->service_fee;
            $partsAfter = $estimatedFare + array_sum($charges);
            $total = round(max(0, (float) $ticket->total_amount + $partsAfter - $partsBefore), 2);

            // Blank lines dropped; none at all keeps what was stored (null and [] both mean none).
            $restrictions = array_values(array_filter(array_map(fn ($line): string => trim((string) $line), $validated['airline_restrictions'] ?? [])));
            if ($restrictions === [] && empty($ticket->airline_restrictions)) {
                $restrictions = $ticket->airline_restrictions;
            }

            $legs = $tripType === 'multi_city'
                ? collect($validated['multi_city_segments'] ?? [])
                    ->filter(fn (array $leg): bool => filled($leg['from'] ?? null) || filled($leg['to'] ?? null))
                    ->map(fn (array $leg): array => ['from' => trim((string) ($leg['from'] ?? '')), 'to' => trim((string) ($leg['to'] ?? '')), 'date' => $leg['date'] ?? null])
                    ->values()->all()
                : null;

            $ticket->fill([
                'travel_type' => $validated['travel_type'],
                'trip_type' => $tripType,
                'origin' => $validated['origin'],
                'destination' => $validated['destination'],
                'preferred_flight_time' => $validated['preferred_flight_time'] ?? $ticket->preferred_flight_time,
                'travel_class' => $validated['travel_class'] ?? $ticket->travel_class,
                'departure_date' => $validated['departure_date'],
                'return_date' => $tripType === 'round_trip' ? $validated['return_date'] : null,
                'multi_city_segments' => $legs,
                'destination_country' => $isInternational ? $validated['destination_country'] : $ticket->destination_country,
                'destination_city' => $isInternational ? $validated['destination_city'] : $ticket->destination_city,
                'arrival_airport' => $isInternational ? $validated['arrival_airport'] : $ticket->arrival_airport,
                'preferred_airline' => $validated['preferred_airline'] ?? null,
                'airline_restrictions' => $restrictions,
                'contact_name' => $validated['contact_name'],
                'contact_email' => $validated['contact_email'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'emergency_contact_email' => $validated['emergency_contact_email'] ?? null,
                'special_requests' => $validated['special_requests'] ?? null,
                'fare_breakdown' => $fareBreakdown ?: null,
                'estimated_fare' => $estimatedFare,
                ...$charges,
                'total_amount' => $total,
            ] + $this->bookedFlight($validated + ['trip_type' => $tripType]));

            // A new total re-derives the payment status from what has already been paid.
            if ($ticket->isDirty('total_amount')) {
                $ticket->recordPayment((float) $ticket->amount_paid);
            }

            $changed = array_keys($ticket->getDirty());
            $ticket->save();

            foreach ($ticket->passengers as $passenger) {
                $data = $submitted->get($passenger->id);
                if ($data === null) {
                    continue;
                }

                $passenger->fill([
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'last_name' => $data['last_name'],
                    'suffix' => $data['suffix'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'nationality_type' => $data['nationality_type'] ?? $passenger->nationality_type,
                    'date_of_birth' => ! empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                    'passport_number' => $data['passport_number'] ?? null,
                    'passport_expiry_date' => ! empty($data['passport_expiry_date']) ? $data['passport_expiry_date'] : null,
                ]);

                if ($passenger->isDirty()) {
                    $changed[] = 'passenger '.$passenger->passenger_number.' ('.implode(', ', array_keys($passenger->getDirty())).')';
                    $passenger->save();
                }
            }
        });

        if ($changed === []) {
            return redirect()->route('ticketing.tickets.show', $ticket)->with('success', 'Nothing was changed.');
        }

        ActivityLogger::log('Ticketing', 'UPDATE', "Edited {$ticket->booking_reference}: ".implode('; ', $changed));

        // A changed booking is checked again before (more) payment is taken or it is issued.
        $resubmitted = false;
        if (! $ticket->isQuotation() && ($ticket->isApproved() || $ticket->isRejected())) {
            $ticket->submitForApproval();
            $ticket->save();
            $this->notifyApprovers($ticket, resubmitted: true);
            $resubmitted = true;
        }

        return redirect()->route('ticketing.tickets.show', $ticket)->with('success', 'Booking updated.'
            .($resubmitted ? ' It was sent to the admin for approval again.' : '')
            .($ticket->bookingAgreement()->exists() ? ' The booking agreement was not changed: update it too if it carries the same details.' : ''));
    }

    /**
     * Display the specified ticket booking details.
     */
    public function show(TicketBooking $ticket)
    {
        $ticket->load(['passengers.documents', 'travelPackage', 'airline', 'createdBy', 'issuedBy', 'cancelledBy', 'bookingAgreement', 'payments.receivedBy', 'reviewedBy']);

        // What each passenger still owes, by passenger id. Quotations are not
        // held to the document rules until they become bookings, and a ticket
        // that is issued or cancelled no longer takes uploads.
        $documentGaps = $ticket->isQuotation() || $ticket->isIssued() || $ticket->isCancelled()
            ? collect()
            : $ticket->missingDocuments()->keyBy(fn (array $row): int => $row['passenger']->id);

        // The history, each entry knowing its ticket so the client message can be built from it.
        $flightChanges = $ticket->flightChanges()->with('recordedBy')->get()->each->setRelation('ticket', $ticket);
        $smsEnabled = SmsSender::enabled();

        return view('ticketing.tickets.show', compact('ticket', 'documentGaps', 'flightChanges', 'smsEnabled'));
    }

    /**
     * The printable A4 voucher: a standalone page, so the portal chrome and
     * the working controls never reach the paper.
     */
    public function voucher(TicketBooking $ticket): View
    {
        $ticket->load(['passengers', 'travelPackage', 'airline', 'createdBy', 'issuedBy']);

        return view('ticketing.tickets.voucher', compact('ticket'));
    }

    /**
     * Replace the airline restrictions noted on a booking.
     *
     * They are reference notes, so they stay editable after the ticket is issued.
     */
    public function updateRestrictions(Request $request, TicketBooking $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'airline_restrictions' => ['nullable', 'array', 'max:30'],
            'airline_restrictions.*' => ['nullable', 'string', 'max:500'],
        ]);

        $ticket->update([
            'airline_restrictions' => self::cleanRestrictions($validated['airline_restrictions'] ?? []),
        ]);

        ActivityLogger::log('Ticketing', 'UPDATE', "Updated the airline restrictions on {$ticket->booking_reference}");

        return back()->with('success', 'Airline restrictions updated.');
    }

    /**
     * Trim the restrictions and drop the rows left blank.
     *
     * @param  array<int, string|null>  $restrictions
     * @return list<string>|null
     */
    private static function cleanRestrictions(array $restrictions): ?array
    {
        $cleaned = array_values(array_filter(array_map(fn ($item) => trim((string) $item), $restrictions), 'strlen'));

        return $cleaned === [] ? null : $cleaned;
    }

    /**
     * Record a payment received against a booking.
     *
     * Each payment is its own entry (amount, method, reference, who took it), and
     * the booking's total moves only through those entries. The booking's own
     * total is the source of truth for what "fully paid" means, so the amount is
     * checked against what is still owed rather than trusted.
     */
    public function updatePayment(Request $request, TicketBooking $ticket, TicketPaymentRecorder $recorder): RedirectResponse
    {
        // The cashier records payments once an admin approves the booking; admins may too.
        if (! $request->user()->isAdmin()) {
            return back()->with('error', 'Payments are recorded by the cashier once an admin approves the booking.');
        }

        if ($reason = TicketPaymentRecorder::blockedReason($ticket)) {
            return back()->with('error', $reason);
        }

        $validated = $request->validate(TicketPaymentRecorder::rules(), TicketPaymentRecorder::messages());

        try {
            $recorder->record($ticket, $validated, $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', $ticket->isFullyPaid()
            ? "Payment recorded. {$ticket->booking_reference} is now fully paid and ready to issue."
            : "Payment recorded. Outstanding balance on {$ticket->booking_reference}: ".number_format($ticket->balanceDue(), 2).'.');
    }

    /**
     * Record money returned to the client for a cancelled booking.
     */
    public function refund(Request $request, TicketBooking $ticket): RedirectResponse
    {
        if (! $ticket->isCancelled()) {
            return back()->with('error', 'Only a cancelled booking can be refunded.');
        }

        $validated = $request->validate($this->ledgerRules(), [
            'amount.required' => 'Enter the amount refunded.',
            'amount.min' => 'Enter an amount greater than zero.',
            'method.required' => 'Choose how the refund was made.',
        ]);

        try {
            $refund = $ticket->refundPayment(
                (float) $validated['amount'],
                $validated['method'],
                $request->user(),
                $validated['reference'] ?? null,
                $this->receivedAt($validated),
                $validated['note'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        ActivityLogger::log(
            'Ticketing',
            'REFUND',
            "Refunded {$refund->amount} by {$refund->methodLabel()} ({$refund->receiptNumber()}) on cancelled {$ticket->booking_reference}; still held {$ticket->amount_paid}"
        );

        return back()->with('success', 'Refund of ₱'.number_format((float) $refund->amount, 2).' recorded.'.($ticket->amount_paid > 0 ? ' ₱'.number_format((float) $ticket->amount_paid, 2).' is still held.' : ''));
    }

    /**
     * Cancel a booking, with the reason, and say what is owed back to the client.
     */
    public function cancel(Request $request, TicketBooking $ticket): RedirectResponse
    {
        if ($ticket->isCancelled()) {
            return back()->with('error', 'This booking is already cancelled.');
        }

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'cancellation_reason.required' => 'Say why this booking is being cancelled.',
            'cancellation_reason.min' => 'Give a little more detail on why this booking is being cancelled.',
        ]);

        $wasIssued = $ticket->isIssued();
        $ticket->cancel($request->user(), $validated['cancellation_reason']);

        $paid = (float) $ticket->amount_paid;

        ActivityLogger::log(
            'Ticketing',
            'CANCEL',
            "Cancelled {$ticket->booking_reference} for {$ticket->contact_name}: {$ticket->cancellation_reason}"
                .($paid > 0 ? " (₱{$ticket->amount_paid} had been received)" : '')
                .($wasIssued ? ' (the ticket had been issued)' : '')
        );

        $message = "{$ticket->booking_reference} is cancelled.";
        if ($paid > 0) {
            $message .= ' ₱'.number_format($paid, 2).' was already received: settle it with the client and record the refund on this page.';
        }
        if ($wasIssued) {
            $message .= ' The ticket had been issued, so also cancel or void it with the airline.';
        }

        return back()->with('success', $message);
    }

    /**
     * A printable receipt for one payment or refund.
     */
    public function receipt(TicketBooking $ticket, TicketPayment $payment): View
    {
        abort_unless($payment->ticket_booking_id === $ticket->id, 404);

        $payment->load('receivedBy');
        [$receivedToDate, $balanceAfter] = TicketPaymentRecorder::receiptFigures($ticket, $payment);

        return view('ticketing.tickets.receipt', compact('ticket', 'payment', 'receivedToDate', 'balanceAfter'));
    }

    /**
     * What a payment or refund entry must carry.
     *
     * @return array<string, mixed>
     */
    /**
     * Email the admins that a booking is waiting for their approval. A failed
     * email never stops the booking: it is on the approvals page either way.
     */
    private function notifyApprovers(TicketBooking $ticket, bool $resubmitted = false): void
    {
        try {
            Notification::send(
                User::where('role', 'admin')->whereNotNull('email')->get(),
                new TicketApprovalRequestedNotification($ticket->loadMissing('createdBy'), $resubmitted),
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function ledgerRules(): array
    {
        return TicketPaymentRecorder::rules();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function receivedAt(array $validated): ?Carbon
    {
        return TicketPaymentRecorder::receivedAt($validated);
    }

    /**
     * Issue the ticket once it is fully paid and consent has been given.
     */
    public function issue(Request $request, TicketBooking $ticket, BookingAgreementDrafter $drafter): RedirectResponse
    {
        if ($ticket->isIssued()) {
            return back()->with('error', 'This ticket has already been issued.');
        }

        if ($ticket->isCancelled()) {
            return back()->with('error', 'A cancelled booking cannot be issued.');
        }

        if ($ticket->isQuotation()) {
            return back()->with('error', 'This is a quotation. Complete the passenger details and required documents before issuing it as a ticket.');
        }

        if ($ticket->isAwaitingApproval() || $ticket->isRejected()) {
            return back()->with('error', 'This booking must be approved by an admin and paid before the ticket can be issued.');
        }

        if (! $ticket->isFullyPaid()) {
            return back()->with('error', 'Full payment is required before a ticket can be issued.');
        }

        // Payment may come first, but the ticket waits for every required document.
        $gaps = $ticket->missingDocuments();

        if ($gaps->isNotEmpty()) {
            $list = $gaps->map(fn (array $row): string => $row['passenger']->full_name.' ('.implode(', ', $row['missing']).')')->implode('; ');

            return back()->with('error', "The ticket cannot be issued until the required documents are uploaded: {$list}.");
        }

        $request->validate([
            'data_privacy_consent' => ['accepted'],
        ], [
            'data_privacy_consent.accepted' => 'The data privacy and consent declaration must be acknowledged before issuing.',
        ]);

        $ticket->markAsIssued($request->user());

        // The client is always sent their agreement: draw one up if staff never did.
        $drafter->ensureFor($ticket, $request->user());

        ClientNotifier::send($ticket->contact_email, $ticket->contact_name, new TicketIssuedNotification($ticket));

        ActivityLogger::log(
            'Ticketing',
            'ISSUE',
            "Issued ticket {$ticket->booking_reference} for {$ticket->contact_name}"
        );

        return back()->with('success', "Ticket {$ticket->booking_reference} has been issued.");
    }

    /**
     * Download or view an uploaded passenger document.
     */
    public function downloadDocument(TicketPassengerDocument $document): StreamedResponse|RedirectResponse
    {
        // Only from a booking this staff member can see (their own, or any for admins).
        abort_unless($document->passenger?->booking, 404);

        if (! DocumentStorage::disk()->exists($document->file_path)) {
            return back()->with('error', 'The requested document file could not be found.');
        }

        return DocumentStorage::disk()->download($document->file_path, $document->original_name);
    }

    /**
     * The registered client travelling in this passenger slot, if staff
     * picked one. Passenger #1 falls back to the booker for forms that only
     * send the booking-level client.
     *
     * @param  array<string, mixed>  $passenger
     */
    /**
     * The booked-flight columns, normalised. Codes are stored upper-case the
     * way airlines print them, and the return leg only exists on a round trip.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, int|string|null>
     */
    private function bookedFlight(array $validated): array
    {
        $code = fn (string $field): ?string => filled($validated[$field] ?? null)
            ? strtoupper(str_replace(' ', '', trim($validated[$field])))
            : null;

        $isRoundTrip = $validated['trip_type'] === 'round_trip';

        return [
            'airline_id' => $validated['airline_id'] ?? null,
            'airline_pnr' => $code('airline_pnr'),
            'flight_number' => $code('flight_number'),
            'departure_time' => $validated['departure_time'] ?? null,
            'arrival_time' => $validated['arrival_time'] ?? null,
            'return_flight_number' => $isRoundTrip ? $code('return_flight_number') : null,
            'return_departure_time' => $isRoundTrip ? ($validated['return_departure_time'] ?? null) : null,
            'return_arrival_time' => $isRoundTrip ? ($validated['return_arrival_time'] ?? null) : null,
        ];
    }

    private function passengerClient(array $passenger, int|string $index, ?User $booker): ?User
    {
        if (! empty($passenger['client_user_id'])) {
            return User::where('role', 'client')->find($passenger['client_user_id']);
        }

        return (int) $index === 0 ? $booker : null;
    }

    /**
     * The client's profile scans that staff chose to reuse for this passenger,
     * keyed by the document type they become on the booking.
     *
     * @return array<string, string>
     */
    private function profileScansInUse(Request $request, int|string $index, ?User $client): array
    {
        if (! $client) {
            return [];
        }

        $disk = DocumentStorage::disk();
        $scans = [];

        $candidates = [
            'passport_scan' => ['use_profile_passport', $client->passport_photo],
            'government_id' => ['use_profile_government_id', $client->government_id_photo],
        ];

        foreach ($candidates as $docType => [$flag, $path]) {
            if ($request->boolean("passengers.{$index}.{$flag}") && filled($path) && $disk->exists($path)) {
                $scans[$docType] = $path;
            }
        }

        return $scans;
    }

    /**
     * Copy the reused profile scans onto the booking, unless a fresh file was
     * uploaded for the same document. Copying keeps the booking's record intact
     * if the client later replaces the scan on their profile.
     *
     * @param  array<string, string>  $profileScans
     */
    private function copyProfileScans(Request $request, int|string $index, TicketPassenger $passenger, TicketBooking $booking, array $profileScans): void
    {
        $uploadFields = ['passport_scan' => 'passport_file', 'government_id' => 'government_id_file'];
        $disk = DocumentStorage::disk();

        foreach ($profileScans as $docType => $sourcePath) {
            if ($request->hasFile("passengers.{$index}.{$uploadFields[$docType]}")) {
                continue;
            }

            $copyPath = "tickets/{$booking->booking_reference}/p{$passenger->passenger_number}/".basename($sourcePath);
            $disk->copy($sourcePath, $copyPath);

            $passenger->documents()->create([
                'document_type' => $docType,
                'file_path' => $copyPath,
                'original_name' => 'Client profile '.str_replace('_', ' ', $docType).'.'.pathinfo($sourcePath, PATHINFO_EXTENSION),
                'file_size' => $disk->size($copyPath),
                'mime_type' => $disk->mimeType($copyPath) ?: null,
                'status' => 'uploaded',
            ]);
        }
    }
}
