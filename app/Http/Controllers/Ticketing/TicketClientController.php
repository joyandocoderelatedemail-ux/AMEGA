<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\CorporateAccount;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ClientProfileService;
use App\Services\CorporateAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * Finding or registering the client at the ticketing desk, before a booking.
 *
 * Ticketing officers cannot reach the admin client directory, so the search
 * and the registration form live inside the ticketing portal.
 */
class TicketClientController extends Controller
{
    /** How many matches the picker shows at once. */
    private const RESULT_LIMIT = 8;

    /**
     * Search registered clients by name, email, phone, passport or ID number.
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['clients' => [], 'companies' => []]);
        }

        $clients = User::clientSearch($term)
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get();

        // A corporate account by its name: its travellers are added together from it.
        $companies = CorporateAccount::where('company_name', 'like', '%'.addcslashes($term, '%_\\').'%')
            ->withCount('members')
            ->orderBy('company_name')
            ->limit(5)
            ->get();

        return response()->json([
            'clients' => $clients->map(fn (User $client) => ClientProfileService::ticketProfile($client))->values(),
            'companies' => $companies->map(fn (CorporateAccount $company) => self::companyProfile($company))->values(),
        ]);
    }

    /**
     * A corporate account's travellers, to tick and add to the booking together, with the
     * company's contact who books for them.
     */
    public function members(CorporateAccount $corporate): JsonResponse
    {
        $members = $corporate->members()->orderBy('last_name')->orderBy('first_name')->get();

        return response()->json([
            'company' => self::companyProfile($corporate->loadCount('members')),
            'members' => $members->map(fn (User $member) => ClientProfileService::ticketProfile($member))->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function companyProfile(CorporateAccount $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->company_name,
            'members_count' => (int) $company->members_count,
            'contact_person' => $company->contact_person,
            'contact_email' => $company->contact_email,
            'contact_phone' => $company->contact_phone,
        ];
    }

    /**
     * The registration form, for a client who is not on file yet.
     */
    public function create(Request $request): View
    {
        // "Register an employee" from a company's member list starts the form on that company.
        $presetCorporate = CorporateAccount::find($request->query('corporate'));

        return view('ticketing.clients.create', [
            'prefill' => $this->namePrefill((string) $request->input('name')),
            'corporates' => CorporateAccount::orderBy('company_name')->get(['id', 'company_name']),
            'presetCorporate' => $presetCorporate,
        ]);
    }

    /**
     * Start the form from what was typed into the search, when it reads as a
     * name rather than an email, phone or passport number.
     *
     * @return array{first_name?: string, last_name?: string}
     */
    private function namePrefill(string $typed): array
    {
        $typed = trim(preg_replace('/\s+/', ' ', $typed));

        if ($typed === '' || preg_match('/[@\d]/', $typed)) {
            return [];
        }

        $parts = explode(' ', $typed);
        $lastName = count($parts) > 1 ? array_pop($parts) : '';

        return ['first_name' => implode(' ', $parts), 'last_name' => $lastName];
    }

    /**
     * Replace the passport scan on a client's profile from the booking wizard.
     */
    public function updatePassport(Request $request, User $client): JsonResponse
    {
        return $this->replaceScan($request, $client, 'passport_photo', 'passport');
    }

    /**
     * Replace the government ID scan on a client's profile from the booking wizard.
     */
    public function updateGovernmentId(Request $request, User $client): JsonResponse
    {
        return $this->replaceScan($request, $client, 'government_id_photo', 'government ID');
    }

    /**
     * The new file is validated, stored and referenced before the old one is
     * deleted, so a failed upload leaves the client's current scan untouched.
     */
    private function replaceScan(Request $request, User $client, string $field, string $label): JsonResponse
    {
        abort_unless($client->isClient(), 404);

        $request->validate([$field => 'required|'.ClientProfileService::SCAN_RULE]);

        $replaced = filled($client->{$field});
        $fileName = $request->file($field)->getClientOriginalName();

        ClientProfileService::storeUploads($request, $client, [$field]);

        ActivityLogger::log('Ticketing', 'UPDATE_CLIENT_SCAN', ($replaced ? 'Replaced' : 'Uploaded')." the {$label} scan of client '{$client->name}' ({$client->email}) from the ticket form");

        return response()->json(['has_scan' => true, 'file_name' => $fileName, 'replaced' => $replaced]);
    }

    /**
     * Register the client, then return to a new booking with them selected.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(ClientProfileService::registrationRules() + CorporateAccountService::memberRules($request));

        $company = CorporateAccountService::resolve($validated);
        $attributes = Arr::except($validated, ['corporate_mode', 'corporate']);
        $attributes['corporate_account_id'] = $company?->id;

        $client = ClientProfileService::register($attributes, $request);

        ActivityLogger::log('Ticketing', 'REGISTER_CLIENT', "Registered client '{$client->name}' ({$client->email}) at the ticketing desk".($company ? " under corporate account '{$company->company_name}'" : ''));

        return redirect()->route('ticketing.tickets.create', ['client' => $client->id])
            ->with('success', "{$client->name} is registered and selected for this booking.");
    }
}
