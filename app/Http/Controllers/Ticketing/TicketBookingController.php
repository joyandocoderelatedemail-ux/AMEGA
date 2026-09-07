<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\TicketBooking;
use App\Models\TicketPassengerDocument;
use App\Models\TravelPackage;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

        return view('ticketing.tickets.index', compact('tickets', 'stats'));
    }

    /**
     * Show the multi-step booking wizard form.
     */
    public function create()
    {
        $destinations = Destination::orderBy('name')->get();
        $domesticDestinations = Destination::where('type', 'domestic')->orderBy('name')->get();
        $internationalDestinations = Destination::where('type', 'international')->orderBy('name')->get();

        $packages = TravelPackage::with('destination')
            ->where('status', 'active')
            ->orderBy('title')
            ->get();

        return view('ticketing.tickets.create', compact('destinations', 'domesticDestinations', 'internationalDestinations', 'packages'));
    }

    /**
     * Store a newly created ticket booking in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $travelType = $request->input('travel_type', 'domestic');

        // 1. Validate General Trip and Contact Fields
        $rules = [
            'travel_type' => ['required', 'string', 'in:domestic,international'],
            'package_type' => ['required', 'string', 'in:with_package,without_package'],
            'travel_package_id' => ['nullable', 'exists:travel_packages,id'],
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
            'passengers' => ['required', 'array', 'min:1'],
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
        $docErrors = [];
        $passengersData = $request->input('passengers', []);
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

            // 2.1 Passport Photo/Scan Upload is mandatory for ALL passengers
            if (! $request->hasFile("passengers.{$index}.passport_file")) {
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
                    if (! $request->hasFile("passengers.{$index}.government_id_file")) {
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

        if (! empty($docErrors)) {
            throw ValidationException::withMessages($docErrors);
        }

        // 3. Save to Database within Transaction
        $booking = DB::transaction(function () use ($request, $validated, $passengersData, $travelType) {
            $reference = TicketBooking::generateReference($travelType === 'domestic' ? 'DOM' : 'INT');

            $packageTitle = null;
            if (! empty($validated['travel_package_id'])) {
                $pkg = TravelPackage::find($validated['travel_package_id']);
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

            $booking = TicketBooking::create([
                'booking_reference' => $reference,
                'created_by' => Auth::id(),
                'travel_type' => $validated['travel_type'],
                'package_type' => $validated['package_type'],
                'travel_package_id' => $validated['travel_package_id'] ?? null,
                'package_name' => $packageTitle ?? ($validated['package_name'] ?? null),
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
                'contact_email' => $validated['contact_email'],
                'contact_phone' => $validated['contact_phone'],
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
                'status' => 'pending',
            ]);

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

                foreach ($docFiles as $inputKey => $docType) {
                    if ($request->hasFile("passengers.{$index}.{$inputKey}")) {
                        $file = $request->file("passengers.{$index}.{$inputKey}");
                        $storedPath = $file->store("tickets/{$booking->booking_reference}/p{$passenger->passenger_number}", 'public');

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

        ActivityLogger::log('Ticketing', 'CREATE', "Created ticket booking {$booking->booking_reference} for {$booking->contact_name} ({$booking->origin} to {$booking->destination})");

        return redirect()->route('ticketing.tickets.show', $booking)
            ->with('success', "Ticket booking {$booking->booking_reference} created successfully with {$booking->total_passengers} passenger(s)!");
    }

    /**
     * Display the specified ticket booking details.
     */
    public function show(TicketBooking $ticket)
    {
        $ticket->load(['passengers.documents', 'travelPackage', 'createdBy']);

        return view('ticketing.tickets.show', compact('ticket'));
    }

    /**
     * Download or view an uploaded passenger document.
     */
    public function downloadDocument(TicketPassengerDocument $document): StreamedResponse|RedirectResponse
    {
        if (! Storage::disk('public')->exists($document->file_path)) {
            return back()->with('error', 'The requested document file could not be found.');
        }

        return Storage::disk('public')->download($document->file_path, $document->original_name);
    }
}
