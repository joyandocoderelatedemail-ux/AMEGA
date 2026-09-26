<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\CustomPackageInquiry;
use App\Models\Destination;
use App\Models\TicketBooking;
use App\Models\TicketDraft;
use App\Models\TicketPassenger;
use App\Models\TicketPassengerDocument;
use App\Models\TravelPackage;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\TicketBookedNotification;
use App\Notifications\TicketIssuedNotification;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Services\ClientNotifier;
use App\Services\ClientProfileService;
use App\Support\DocumentStorage;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketBookingController extends Controller
{
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

        $packages = TravelPackage::with('destination')
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

        return view('ticketing.tickets.create', compact('destinations', 'domesticDestinations', 'internationalDestinations', 'packages', 'preselectedClient', 'pendingTicket', 'pendingCount'));
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
            'client_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'client')],
            'passengers' => ['required', 'array', 'min:1'],
            'passengers.*.client_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'client')],
        ];

        if ($request->input('trip_type') === 'round_trip') {
            $rules['return_date'] = ['required', 'date', 'after:departure_date'];
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
            $rules['has_insurance'] = ['nullable', 'boolean'];
            $rules['insurance_plan'] = ['nullable', 'string', 'in:basic,standard,premium'];
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

        $validated = $request->validate($rules);

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
            $nationality = $passenger['nationality_type'] ?? 'filipino';
            $type = $passenger['passenger_type'] ?? 'adult';

            // The fare category must match the traveller's age on departure.
            if (! empty($passenger['date_of_birth']) && strtotime($passenger['date_of_birth']) !== false) {
                $ageType = TicketPassenger::typeForAge(Carbon::parse($passenger['date_of_birth']), $departureDate);

                if ($ageType !== $type) {
                    $docErrors["passengers.{$index}.passenger_type"] = "Passenger #{$num} ({$fullName}) is booked as ".ucfirst($type).', but their date of birth makes them '.ucfirst($ageType).' on the departure date.';
                }
            }

            // 2.1 Passport is mandatory for international travel, and for foreign
            // nationals on domestic flights since it is their identity document.
            // Filipino domestic passengers are covered by the government ID,
            // school ID, or birth certificate rules below instead.
            $passportRequired = $travelType === 'international' || $nationality === 'foreign_national';

            if ($passportRequired && ! $request->hasFile("passengers.{$index}.passport_file") && ! isset($profileScans[$index]['passport_scan'])) {
                $docErrors["passengers.{$index}.passport_file"] = "Passenger #{$num} ({$fullName}) requires a Passport photo/scan upload.";
            }

            // 2.2 Passport 6-month validity check (Mandatory for International & Filipino Domestic)
            if (! empty($passenger['passport_expiry_date'])) {
                $expiryDate = Carbon::parse($passenger['passport_expiry_date']);
                if ($expiryDate->lt($departureDate->copy()->addMonths(6))) {
                    $docErrors["passengers.{$index}.passport_expiry_date"] = "Passenger #{$num} ({$fullName}) passport must have at least six (6) months validity before departure ({$departureDate->format('M d, Y')}). Passport must be renewed before travel.";
                }
            } elseif ($travelType === 'international') {
                $docErrors["passengers.{$index}.passport_expiry_date"] = "Passenger #{$num} ({$fullName}) passport expiration date is required for international travel.";
            }

            // Additional checks for Domestic
            if ($travelType === 'domestic') {
                // Government ID Photo/Scan required for Filipino Adults (18+)
                if ($nationality === 'filipino' && $type === 'adult') {
                    if (! $request->hasFile("passengers.{$index}.government_id_file") && ! isset($profileScans[$index]['government_id'])) {
                        $docErrors["passengers.{$index}.government_id_file"] = "Passenger #{$num} ({$fullName} - Adult 18+) requires a Government ID photo/scan upload.";
                    }
                }

                // Birth Certificate Photo/Scan required for Infants (0-2)
                if ($type === 'infant') {
                    if (! $request->hasFile("passengers.{$index}.birth_cert_file")) {
                        $docErrors["passengers.{$index}.birth_cert_file"] = "Passenger #{$num} ({$fullName} - Infant) requires a Birth Certificate photo/scan upload.";
                    }
                }

                // School ID or Birth Certificate Photo/Scan required for Children (2-17)
                if ($type === 'child') {
                    if (! $request->hasFile("passengers.{$index}.school_id_file") && ! $request->hasFile("passengers.{$index}.birth_cert_file")) {
                        $docErrors["passengers.{$index}.school_id_file"] = "Passenger #{$num} ({$fullName} - Child) requires a School ID or Birth Certificate photo/scan upload.";
                    }
                }

                // Foreign National Visa Photo/Scan
                $visaType = $passenger['visa_type'] ?? 'none';
                if ($nationality === 'foreign_national' && in_array($visaType, ['e_visa', 'regular_visa'])) {
                    if (! $request->hasFile("passengers.{$index}.visa_file")) {
                        $visaLabel = $visaType === 'e_visa' ? 'e-Visa' : 'Regular Visa (R-Visa)';
                        $docErrors["passengers.{$index}.visa_file"] = "Passenger #{$num} ({$fullName}) requires a {$visaLabel} document photo/scan upload.";
                    }
                }

                // Foreign National Exit Clearance Photo/Scan for stays > 6 months
                $stayMonths = (int) ($passenger['stay_duration_months'] ?? 0);
                if ($nationality === 'foreign_national' && $stayMonths > 6) {
                    if (! $request->hasFile("passengers.{$index}.exit_clearance_file")) {
                        $docErrors["passengers.{$index}.exit_clearance_file"] = "Passenger #{$num} ({$fullName} - Stay exceeding 6 months) requires an Emigration Exit Clearance (ECC) certificate photo/scan upload.";
                    }
                }
            } else {
                // Phase 2 International Specific Document Checks
                $visaStatus = $passenger['visa_status'] ?? 'visa_not_required';

                // If "Already Has Visa" -> Visa copy upload is required
                if ($visaStatus === 'already_has_visa') {
                    if (! $request->hasFile("passengers.{$index}.visa_file")) {
                        $docErrors["passengers.{$index}.visa_file"] = "Passenger #{$num} ({$fullName}) has 'Already Has Visa' selected and requires a Visa Copy document upload.";
                    }
                }

                // If "Needs Visa Assistance" -> Passport Photo & Supporting Documents required
                if ($visaStatus === 'needs_assistance') {
                    if (! $request->hasFile("passengers.{$index}.passport_photo_file")) {
                        $docErrors["passengers.{$index}.passport_photo_file"] = "Passenger #{$num} ({$fullName}) requested Visa Assistance and requires a Passport Photo (2x2) upload.";
                    }

                    if (! $request->hasFile("passengers.{$index}.supporting_doc_file")) {
                        $docErrors["passengers.{$index}.supporting_doc_file"] = "Passenger #{$num} ({$fullName}) requested Visa Assistance and requires Supporting Documents (e.g. COE, Bank Certificate, Invitation Letter) upload.";
                    }
                }
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
            $taxesAmount = (float) ($request->input('taxes_amount', 0));
            $visaFee = (float) ($request->input('visa_assistance_fee', 0));
            $insuranceFee = (float) ($request->input('insurance_fee', 0));
            $otherCharges = (float) ($request->input('other_charges', 0));
            $totalAmount = (float) ($request->input('total_amount', 0));

            if ($totalAmount <= 0) {
                $totalAmount = $estimatedFare + $taxesAmount + $visaFee + $insuranceFee + $otherCharges;
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
                'estimated_fare' => $estimatedFare,
                'taxes_amount' => $taxesAmount,
                'visa_assistance_fee' => $visaFee,
                'insurance_fee' => $insuranceFee,
                'other_charges' => $otherCharges,
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

            // Save Passengers and their respective uploaded documents
            foreach ($passengersData as $index => $data) {
                $passenger = $booking->passengers()->create([
                    'passenger_number' => $index + 1,
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
                $docFiles = [
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

                $this->copyProfileScans($request, $index, $passenger, $booking, $profileScans[$index] ?? []);

                // A gender or birth date staff entered for a client whose profile had none.
                $passengerClient = $passengerClients[$index] ?? null;
                if ($passengerClient) {
                    ClientAccountService::fillFromPassenger($passengerClient, $passenger);
                }

                foreach ($docFiles as $inputKey => $docType) {
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
        }

        if ($asQuotation) {
            return redirect()->route('ticketing.agreements.create', $booking)
                ->with('clear_booking_draft', true)
                ->with('success', "Quotation {$booking->booking_reference} started. Travel documents are still required before this can be issued as a ticket.");
        }

        return redirect()->route('ticketing.tickets.show', $booking)
            ->with('clear_booking_draft', true)
            ->with('success', "Ticket booking {$booking->booking_reference} created successfully with {$booking->total_passengers} passenger(s)!");
    }

    /**
     * Display the specified ticket booking details.
     */
    public function show(TicketBooking $ticket)
    {
        $ticket->load(['passengers.documents', 'travelPackage', 'createdBy', 'issuedBy', 'bookingAgreement']);

        return view('ticketing.tickets.show', compact('ticket'));
    }

    /**
     * The printable A4 voucher: a standalone page, so the portal chrome and
     * the working controls never reach the paper.
     */
    public function voucher(TicketBooking $ticket): View
    {
        $ticket->load(['passengers', 'travelPackage', 'createdBy', 'issuedBy']);

        return view('ticketing.tickets.voucher', compact('ticket'));
    }

    /**
     * Record payment received against a booking.
     *
     * The booking's own total is the source of truth for what "fully paid"
     * means, so the amount is validated against it rather than trusted.
     */
    public function updatePayment(Request $request, TicketBooking $ticket): RedirectResponse
    {
        if ($ticket->isCancelled()) {
            return back()->with('error', 'Payment cannot be recorded against a cancelled booking.');
        }

        if ($ticket->isIssued()) {
            return back()->with('error', 'This ticket has already been issued; its payment can no longer be changed.');
        }

        $validated = $request->validate([
            'amount_paid' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ], [
            'amount_paid.required' => 'Enter the total amount received for this booking.',
        ]);

        if ((float) $ticket->total_amount <= 0) {
            return back()->with('error', 'This booking has no total amount yet, so payment cannot be recorded against it.');
        }

        $previouslyPaid = (float) $ticket->amount_paid;

        $ticket->recordPayment((float) $validated['amount_paid']);

        // The form takes the running total, so only an increase is a new payment.
        $received = (float) $ticket->amount_paid - $previouslyPaid;
        if ($received > 0) {
            ClientNotifier::send($ticket->contact_email, $ticket->contact_name, new PaymentReceivedNotification(
                'ticket booking', $ticket->booking_reference, (string) $ticket->contact_name, 'PHP',
                $received, (float) $ticket->amount_paid, $ticket->balanceDue(),
            ));
        }

        ActivityLogger::log(
            'Ticketing',
            'PAYMENT',
            "Recorded payment of {$ticket->amount_paid} on {$ticket->booking_reference} (status: {$ticket->payment_status})"
        );

        return back()->with('success', $ticket->isFullyPaid()
            ? "Payment recorded. {$ticket->booking_reference} is now fully paid and ready to issue."
            : "Payment recorded. Outstanding balance on {$ticket->booking_reference}: ".number_format($ticket->balanceDue(), 2).'.');
    }

    /**
     * Issue the ticket once it is fully paid and consent has been given.
     */
    public function issue(Request $request, TicketBooking $ticket): RedirectResponse
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

        if (! $ticket->isFullyPaid()) {
            return back()->with('error', 'Full payment is required before a ticket can be issued.');
        }

        $request->validate([
            'data_privacy_consent' => ['accepted'],
        ], [
            'data_privacy_consent.accepted' => 'The data privacy and consent declaration must be acknowledged before issuing.',
        ]);

        $ticket->markAsIssued($request->user());

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
