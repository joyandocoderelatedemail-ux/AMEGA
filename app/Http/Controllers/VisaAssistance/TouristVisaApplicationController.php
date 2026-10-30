<?php

namespace App\Http\Controllers\VisaAssistance;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationClient;
use App\Models\TouristVisaApplication;
use App\Models\TouristVisaPayment;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Support\DateRange;
use App\Support\DocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Visit visa extensions filed with the Bureau of Immigration, at the visa
 * assistance counter: staff fill the CGAF and tick the Checklist of
 * Documentary Requirements, then print both on the BI's own layouts.
 */
class TouristVisaApplicationController extends Controller
{
    /** What carries over to a client's next form. */
    private const CLIENT_FIELDS = [
        'last_name', 'first_name', 'middle_name', 'alias', 'date_of_birth', 'sex', 'birthplace',
        'citizenship', 'height_cm', 'weight_kg', 'civil_status',
        'address_street', 'barangay', 'city', 'province', 'zip_code',
        'passport_number', 'passport_validity', 'mobile_number', 'email',
        'acr_number', 'acr_validity', 'ssrn',
    ];

    /** Clients per page on the folder list. */
    private const FOLDERS_PER_PAGE = 12;

    /**
     * The clients with saved forms, one folder each: every form filed under
     * the same passport sits in the same folder, newest folder first.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));

        $folders = TouristVisaApplication::query()
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($match) => $match
                    ->where('reference', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('passport_number', 'like', $like)
                    ->orWhere('citizenship', 'like', $like));
            })
            // Forms filed within the date range picked on the page.
            ->when(true, fn ($query) => DateRange::fromRequest($request)->apply($query))
            ->latest()
            ->get()
            ->groupBy(fn (TouristVisaApplication $application) => $application->passport_key)
            ->map(fn ($forms, $key) => (object) [
                'key' => $key,
                'latest' => $forms->first(),
                'count' => $forms->count(),
            ])
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $folders = new LengthAwarePaginator(
            $folders->forPage($page, self::FOLDERS_PER_PAGE)->values(),
            $folders->count(),
            self::FOLDERS_PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Visit visa files for other countries, so every client added shows here.
        $otherFiles = VisaApplication::query()
            ->where('service_type', 'visit_visa')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($match) => $match
                    ->where('reference', 'like', $like)
                    ->orWhere('client_name', 'like', $like)
                    ->orWhere('client_email', 'like', $like)
                    ->orWhere('client_phone', 'like', $like)
                    ->orWhere('destination_country', 'like', $like));
            })
            ->latest()
            ->limit(12)
            ->get();

        return view('visa-assistance.tourist-visa.index', compact('folders', 'search', 'otherFiles'));
    }

    /** One client's folder: their details and every form filed for them. */
    public function client(string $passport): View
    {
        $applications = TouristVisaApplication::forPassport($passport)->latest()->get();

        abort_if($applications->isEmpty(), 404);

        return view('visa-assistance.tourist-visa.client', [
            'applications' => $applications,
            'latest' => $applications->first(),
            'key' => TouristVisaApplication::passportKey($passport),
        ]);
    }

    /**
     * Registered clients matching the search, with what the CGAF asks for taken
     * from their record, so the counter need not retype it. Height, weight and
     * civil status come from the client's immigration sheet when they have one.
     */
    public function clients(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['clients' => []]);
        }

        $clients = User::clientSearch($term)
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(fn (User $client) => [
                'id' => $client->id,
                'label' => $client->full_name,
                'detail' => collect([$client->passport_number, $client->nationality, $client->email, $client->phone])->filter()->implode(' · '),
                'fields' => $this->fromClient($client),
            ]);

        return response()->json(['clients' => $clients]);
    }

    /**
     * A new application. Opened from a client's folder (?passport=), it starts
     * from that client's latest form: their details, not that form's checklist,
     * reason or dates.
     */
    public function create(Request $request): View
    {
        $application = new TouristVisaApplication([
            'application_method' => 'personal',
            'application_mode' => 'regular',
            'months_requested' => 1,
            'filed_on' => now(),
        ]);

        $previous = $request->filled('passport')
            ? TouristVisaApplication::forPassport($request->string('passport')->toString())->latest()->first()
            : null;

        if ($previous) {
            $application->fill($previous->only(self::CLIENT_FIELDS));
        }

        return view('visa-assistance.tourist-visa.create', ['application' => $application, 'forClient' => $previous]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $this->assertFeeAndPayment($request, $validated, 0.0);

        $application = new TouristVisaApplication($validated);
        $application->created_by = $request->user()->id;
        $application->save();
        $this->syncPhoto($request, $application);
        $this->syncDocuments($request, $application);
        $this->recordPayment($request, $application);

        ActivityLogger::log('Visit Visa', 'CREATE', "Filed visit visa extension {$application->reference} for '{$application->full_name}'");
        $this->rememberClient($application);

        return $this->afterSave($request, $application, 'Application saved.');
    }

    public function edit(TouristVisaApplication $touristVisa): View
    {
        return view('visa-assistance.tourist-visa.edit', ['application' => $touristVisa]);
    }

    public function update(Request $request, TouristVisaApplication $touristVisa): RedirectResponse
    {
        $validated = $this->validated($request);
        $this->assertFeeAndPayment($request, $validated, $touristVisa->amountPaid());

        $touristVisa->fill($validated);
        $touristVisa->save();
        $this->syncPhoto($request, $touristVisa);
        $this->syncDocuments($request, $touristVisa);
        $this->recordPayment($request, $touristVisa);

        ActivityLogger::log('Visit Visa', 'UPDATE', "Updated visit visa extension {$touristVisa->reference} for '{$touristVisa->full_name}'");
        $this->rememberClient($touristVisa);

        return $this->afterSave($request, $touristVisa, 'Application updated.');
    }

    public function destroy(TouristVisaApplication $touristVisa): RedirectResponse
    {
        $this->purge($touristVisa);

        // Back to the client's folder, or to the list when that was its last form.
        $remaining = TouristVisaApplication::forPassport($touristVisa->passport_key)->exists();

        return ($remaining
            ? redirect()->route('visa.tourist-visa.client', $touristVisa->passport_key)
            : redirect()->route('visa.tourist-visa.index'))
            ->with('success', 'Application deleted.');
    }

    /**
     * Delete a client's whole folder: every form filed under their passport, with
     * its uploads and payments. Only the forms this staff member can see go.
     */
    public function destroyFolder(string $passport): RedirectResponse
    {
        $applications = TouristVisaApplication::forPassport($passport)->get();

        abort_if($applications->isEmpty(), 404);

        $name = $applications->first()->formal_name;
        $applications->each(fn (TouristVisaApplication $application) => $this->purge($application));

        ActivityLogger::log('Visit Visa', 'DELETE_FOLDER', "Deleted the folder of '{$name}' ({$applications->count()} forms)");

        return redirect()->route('visa.tourist-visa.index')
            ->with('success', "Folder of {$name} deleted ({$applications->count()} ".Str::plural('form', $applications->count()).').');
    }

    /** Remove one form with its photo, uploaded documents and payments. */
    private function purge(TouristVisaApplication $application): void
    {
        $this->forgetPhoto($application);
        DocumentStorage::disk()->deleteDirectory("tourist-visa/{$application->reference}");
        $application->payments()->delete();
        $application->delete();

        ActivityLogger::log('Visit Visa', 'DELETE', "Deleted visit visa extension {$application->reference} for '{$application->full_name}'");
    }

    /** The applicant's 2x2 photo, shown inline to staff who can open the application. */
    public function photo(TouristVisaApplication $touristVisa): Response
    {
        abort_unless($touristVisa->photo_path && DocumentStorage::disk()->exists($touristVisa->photo_path), 404);

        return DocumentStorage::disk()->response($touristVisa->photo_path, null, [
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** The file uploaded for one checklist requirement, shown inline to staff who can open the application. */
    public function document(TouristVisaApplication $touristVisa, string $item): Response
    {
        $document = $touristVisa->documentFor($item);

        abort_unless($document && DocumentStorage::disk()->exists($document['path']), 404);

        return DocumentStorage::disk()->response($document['path'], $document['name'], [
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Take a recorded payment off the ledger. Admins only: it changes what the client owes. */
    public function destroyPayment(Request $request, TouristVisaApplication $touristVisa, TouristVisaPayment $payment): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($payment->tourist_visa_application_id === $touristVisa->id, 404);

        $payment->delete();

        ActivityLogger::log('Visit Visa', 'PAYMENT_REMOVED', "Removed a payment of PHP {$payment->amount} from {$touristVisa->reference}");

        return redirect()->route('visa.tourist-visa.edit', $touristVisa)->with('success', 'Payment removed.');
    }

    /** The CGAF, filled in, on the BI's layout. */
    public function printForm(TouristVisaApplication $touristVisa): View
    {
        ActivityLogger::log('Visit Visa', 'PRINT', "Printed the CGAF for {$touristVisa->reference}");

        return view('visa-assistance.tourist-visa.print-form', ['application' => $touristVisa]);
    }

    /** The checklist with its certification and claim slip, on the BI's layout. */
    public function printChecklist(TouristVisaApplication $touristVisa): View
    {
        ActivityLogger::log('Visit Visa', 'PRINT', "Printed the documentary checklist for {$touristVisa->reference}");

        return view('visa-assistance.tourist-visa.print-checklist', ['application' => $touristVisa]);
    }

    /** A blank CGAF for a client to fill in by hand. */
    public function blankForm(): View
    {
        return view('visa-assistance.tourist-visa.print-form', ['application' => new TouristVisaApplication]);
    }

    /** A blank checklist for a client to take home. */
    public function blankChecklist(): View
    {
        return view('visa-assistance.tourist-visa.print-checklist', ['application' => new TouristVisaApplication]);
    }

    /**
     * "Save & print" opens the form (or the checklist) with the print dialog up;
     * "Save" stays on the application.
     */
    private function afterSave(Request $request, TouristVisaApplication $application, string $message): RedirectResponse
    {
        return match ($request->input('then')) {
            'print' => redirect()->route('visa.tourist-visa.print-form', ['touristVisa' => $application, 'autoprint' => 1]),
            'checklist' => redirect()->route('visa.tourist-visa.print-checklist', ['touristVisa' => $application, 'autoprint' => 1]),
            default => redirect()->route('visa.tourist-visa.edit', $application)->with('success', $message.' You can print it now.'),
        };
    }

    /**
     * The fee must agree with its plan and with what was already paid, and a
     * payment recorded now cannot be more than is still owed.
     *
     * @param  array<string, mixed>  $validated
     */
    private function assertFeeAndPayment(Request $request, array $validated, float $alreadyPaid): void
    {
        $total = (float) ($validated['total_fee'] ?? 0);
        $payment = round((float) $request->input('payment_amount'), 2);

        if ($validated['payment_type'] === 'terms' && $total <= 0) {
            throw ValidationException::withMessages(['total_fee' => 'Enter the total fee to split it into terms.']);
        }

        if ($total > 0 && $total < $alreadyPaid) {
            throw ValidationException::withMessages(['total_fee' => 'The total fee cannot be less than the PHP '.number_format($alreadyPaid, 2).' already paid.']);
        }

        if ($payment > 0 && $payment > round($total - $alreadyPaid, 2)) {
            throw ValidationException::withMessages(['payment_amount' => $total <= 0
                ? 'Enter the total fee before recording a payment.'
                : 'That is more than the PHP '.number_format(max(0, $total - $alreadyPaid), 2).' still owed.']);
        }
    }

    /** Record the payment entered with the form, if there is one. */
    private function recordPayment(Request $request, TouristVisaApplication $application): void
    {
        $amount = round((float) $request->input('payment_amount'), 2);

        if ($amount <= 0) {
            return;
        }

        $application->payments()->create([
            'amount' => $amount,
            'method' => $request->input('payment_method'),
            'paid_on' => $request->input('payment_paid_on') ?: now()->toDateString(),
            'reference' => $request->input('payment_reference'),
            'recorded_by' => $request->user()->id,
        ]);

        ActivityLogger::log('Visit Visa', 'PAYMENT', 'Recorded a payment of PHP '.number_format($amount, 2)." on {$application->reference}");
    }

    /**
     * Keep the file uploaded for each checklist requirement on the private
     * documents disk. A file in hand ticks its requirement; "remove" drops it.
     * The overstay letter counts only for an applicant who overstayed.
     */
    private function syncDocuments(Request $request, TouristVisaApplication $application): void
    {
        $documents = $application->documents ?? [];
        $checklist = $application->checklist ?? [];
        $items = array_keys(TouristVisaApplication::CHECKLIST + ($application->overstayed ? TouristVisaApplication::OVERSTAY_CHECKLIST : []));

        foreach ($items as $item) {
            $file = $request->file("documents.{$item}");

            if ($file) {
                if ($old = $application->documentFor($item)) {
                    DocumentStorage::disk()->delete($old['path']);
                }

                $documents[$item] = [
                    'path' => $file->store("tourist-visa/{$application->reference}/documents", DocumentStorage::diskName()),
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getClientMimeType(),
                ];
                $checklist[] = $item;
            } elseif ($request->boolean("remove_documents.{$item}") && ($old = $application->documentFor($item))) {
                DocumentStorage::disk()->delete($old['path']);
                unset($documents[$item]);
            }
        }

        $application->documents = $documents ?: null;
        $application->checklist = array_values(array_unique($checklist));
        $application->save();
    }

    /**
     * Keep the uploaded photo on the private documents disk, replacing the old
     * one; "remove" clears it.
     */
    private function syncPhoto(Request $request, TouristVisaApplication $application): void
    {
        if ($request->hasFile('photo')) {
            $this->forgetPhoto($application);
            $application->photo_path = $request->file('photo')->store("tourist-visa/{$application->reference}", DocumentStorage::diskName());
            $application->save();
        } elseif ($request->boolean('remove_photo') && $application->photo_path) {
            $this->forgetPhoto($application);
            $application->photo_path = null;
            $application->save();
        }
    }

    private function forgetPhoto(TouristVisaApplication $application): void
    {
        if ($application->photo_path) {
            DocumentStorage::disk()->delete($application->photo_path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $checklist = array_keys(TouristVisaApplication::CHECKLIST + TouristVisaApplication::OVERSTAY_CHECKLIST);

        $validated = $request->validate([
            'application_method' => ['required', Rule::in(array_keys(TouristVisaApplication::METHODS))],
            'representative_name' => ['nullable', 'required_if:application_method,representative', 'string', 'max:255'],
            'bi_accreditation_number' => ['nullable', 'string', 'max:255'],
            'application_mode' => ['required', Rule::in(array_keys(TouristVisaApplication::MODES))],
            'months_requested' => ['required', Rule::in(array_keys(TouristVisaApplication::MONTHS))],
            'reason' => ['nullable', 'string', 'max:2000'],
            'mobile_number' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'sex' => ['nullable', Rule::in(['M', 'F'])],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'citizenship' => ['required', 'string', 'max:255'],
            'height_cm' => ['nullable', 'string', 'max:20'],
            'weight_kg' => ['nullable', 'string', 'max:20'],
            'civil_status' => ['nullable', Rule::in(array_keys(TouristVisaApplication::CIVIL_STATUSES))],
            'address_street' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'passport_number' => ['required', 'string', 'max:255'],
            'passport_validity' => ['nullable', 'date'],
            'latest_arrival_date' => ['nullable', 'date'],
            'flight_number' => ['nullable', 'string', 'max:255'],
            'latest_extension_validity' => ['nullable', 'date'],
            'latest_extension_place' => ['nullable', 'string', 'max:255'],
            'acr_number' => ['nullable', 'string', 'max:255'],
            'acr_validity' => ['nullable', 'date'],
            'ssrn' => ['nullable', 'string', 'max:255'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => [Rule::in($checklist)],
            'overstayed' => ['nullable', 'boolean'],
            'filed_on' => ['nullable', 'date'],
            'evaluator_name' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'remove_documents' => ['nullable', 'array'],
            'filed_time' => ['nullable', 'date_format:H:i'],
            'processing_time' => ['nullable', 'string', 'max:100'],
            'total_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'payment_type' => ['nullable', Rule::in(array_keys(TouristVisaApplication::PAYMENT_TYPES))],
            'terms_count' => ['nullable', 'required_if:payment_type,terms', 'integer', 'between:'.TouristVisaApplication::MIN_TERMS.','.TouristVisaApplication::MAX_TERMS],
            'payment_amount' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'payment_method' => ['nullable', 'required_with:payment_amount', Rule::in(array_keys(TouristVisaPayment::METHODS))],
            'payment_paid_on' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ], [
            'documents.*.mimes' => 'Each document must be a PDF or a JPG, PNG or WebP image.',
            'documents.*.max' => 'Each document must be 10 MB or smaller.',
            'terms_count.required_if' => 'Choose how many times the fee will be paid.',
            'terms_count.between' => 'Terms can be '.TouristVisaApplication::MIN_TERMS.' to '.TouristVisaApplication::MAX_TERMS.' payments.',
            'payment_method.required_with' => 'Choose how the payment was made.',
            'photo.mimes' => 'The photo must be a JPG, PNG or WebP image.',
            'photo.max' => 'The photo must be 5 MB or smaller.',
            'representative_name.required_if' => 'Enter the authorized representative\'s name.',
        ]);

        unset(
            $validated['photo'], $validated['remove_photo'], $validated['documents'], $validated['remove_documents'],
            $validated['payment_amount'], $validated['payment_method'], $validated['payment_paid_on'], $validated['payment_reference'],
        );

        $validated['total_fee'] = round((float) ($validated['total_fee'] ?? 0), 2);
        $validated['payment_type'] ??= 'full';
        $validated['terms_count'] = $validated['payment_type'] === 'terms' ? (int) $validated['terms_count'] : null;
        $validated['processing_time'] = filled($validated['processing_time'] ?? null)
            ? $validated['processing_time']
            : TouristVisaApplication::PROCESSING_TIMES[$validated['application_mode']];
        $validated['overstayed'] = $request->boolean('overstayed');
        $validated['checklist'] = array_values($validated['checklist'] ?? []);

        // The overstay letter is asked for only when the applicant overstayed.
        if (! $validated['overstayed']) {
            $validated['checklist'] = array_values(array_diff($validated['checklist'], array_keys(TouristVisaApplication::OVERSTAY_CHECKLIST)));
        }

        if ($validated['application_method'] === 'personal') {
            $validated['representative_name'] = null;
            $validated['bi_accreditation_number'] = null;
        }

        return $validated;
    }

    /**
     * What a registered client's record holds, in the CGAF's terms. Blank
     * values are left out, so picking a client never wipes what staff typed.
     *
     * @return array<string, string>
     */
    private function fromClient(User $client): array
    {
        $sheet = ImmigrationClient::where('user_id', $client->id)->latest()->first();
        $civilStatus = Str::lower(trim((string) ($client->civil_status ?: $sheet?->civil_status)));
        $gender = Str::lower((string) $client->gender);

        // Accounts made at a desk may hold only the full name.
        $last = $client->last_name ?: (Str::contains(trim((string) $client->name), ' ') ? Str::afterLast(trim($client->name), ' ') : $client->name);
        $first = $client->first_name ?: (Str::contains(trim((string) $client->name), ' ') ? Str::beforeLast(trim($client->name), ' ') : '');

        return array_filter([
            'last_name' => $last,
            'first_name' => $first,
            'middle_name' => $client->middle_name,
            'date_of_birth' => $client->date_of_birth?->format('Y-m-d'),
            'sex' => str_starts_with($gender, 'm') ? 'M' : (str_starts_with($gender, 'f') ? 'F' : null),
            'citizenship' => $client->nationality,
            'height_cm' => $client->height ?: $sheet?->height,
            'weight_kg' => $client->weight ?: $sheet?->weight,
            'civil_status' => array_key_exists($civilStatus, TouristVisaApplication::CIVIL_STATUSES) ? $civilStatus : null,
            'address_street' => $client->address_line ?: $client->address,
            'city' => $client->city,
            'province' => $client->province,
            'zip_code' => $client->postal_code,
            'passport_number' => $client->passport_number,
            'passport_validity' => $client->passport_expiry?->format('Y-m-d'),
            'mobile_number' => $client->phone,
            'email' => $client->real_email,
            // What earlier forms at any desk recorded (ClientMemory).
            'birthplace' => $client->place_of_birth,
            'acr_number' => $client->acr_number,
            'acr_validity' => $client->acr_valid_until?->format('Y-m-d'),
            'ssrn' => $client->ssrn,
        ], fn ($value) => filled($value));
    }

    /**
     * Keep what the form knows on the client's record (the newest form wins): passport, ACR I-Card,
     * SSRN, birth details, address, height and weight. The next form for them starts with it.
     */
    private function rememberClient(TouristVisaApplication $form): void
    {
        ClientAccountService::findOrCreateClient([
            'name' => trim(collect([$form->first_name, $form->middle_name, $form->last_name])->filter()->implode(' ')),
            'first_name' => $form->first_name,
            'last_name' => $form->last_name,
            'email' => $form->email,
            'phone' => $form->mobile_number,
            'nationality' => $form->citizenship,
            'date_of_birth' => $form->date_of_birth?->toDateString(),
            'place_of_birth' => $form->birthplace,
            'gender' => $form->sex,
            'civil_status' => $form->civil_status,
            'height' => $form->height_cm,
            'weight' => $form->weight_kg,
            'passport_number' => $form->passport_number,
            'passport_expiry' => $form->passport_validity?->toDateString(),
            'address' => collect([$form->address_street, $form->barangay, $form->city, $form->province])->filter()->implode(', '),
            'acr_number' => $form->acr_number,
            'acr_valid_until' => $form->acr_validity?->toDateString(),
            'ssrn' => $form->ssrn,
        ]);
    }
}
