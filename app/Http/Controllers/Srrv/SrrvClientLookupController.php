<?php

namespace App\Http\Controllers\Srrv;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\SrrvServiceRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ClientMemory;
use App\Services\ClientProfileService;
use App\Support\SrrvForms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * Name the folder, like the Immigration counter: before a new SRRV application or ID renewal, the
 * officer finds the retiree. The search lists the SRRV files already open for them (to open
 * instead) and the client list's matches (to start a file filled from their record); someone new
 * is added to the client list first. The form then opens with what is on file.
 */
class SrrvClientLookupController extends Controller
{
    private const LIMIT = 8;

    /**
     * The services that start here, the form each one opens, and what that form starts with. Classic
     * and Courtesy are the desk's menu items; 'application' (either one) stays for older links.
     */
    public const SERVICES = [
        'classic' => ['label' => 'SRRV Classic', 'icon' => 'file-badge', 'route' => 'srrv.applications.create', 'params' => ['visa_class' => 'classic'],
            'blurb' => 'A new Classic retiree file, through to oath taking at the PRA.'],
        'courtesy' => ['label' => 'SRRV Courtesy', 'icon' => 'award', 'route' => 'srrv.applications.create', 'params' => ['visa_class' => 'courtesy'],
            'blurb' => 'A new Courtesy retiree file (former Filipinos, retired diplomats and international staff), through to oath taking at the PRA.'],
        'renewal_classic' => ['label' => 'SRRV Classic Renewal', 'icon' => 'calendar-check', 'route' => 'srrv.renewals.create', 'params' => ['visa_class' => 'classic'],
            'blurb' => 'The renewal of a Classic retiree\'s SRRV ID card.'],
        'renewal_courtesy' => ['label' => 'SRRV Courtesy Renewal', 'icon' => 'calendar-heart', 'route' => 'srrv.renewals.create', 'params' => ['visa_class' => 'courtesy'],
            'blurb' => 'The renewal of a Courtesy retiree\'s SRRV ID card.'],
        'termination' => ['label' => 'SRRV Termination of Membership', 'icon' => 'log-out', 'route' => 'srrv.requests.create', 'params' => ['type' => 'termination'],
            'blurb' => 'Cancelling the SRRV (with or without downgrading to a Temporary Visitor\'s Visa) and withdrawing the visa deposit (PRA-SD-FORM-0008).'],
        'conversion' => ['label' => 'SRRV Conversion into Active Investment', 'icon' => 'trending-up', 'route' => 'srrv.requests.create', 'params' => ['type' => 'conversion'],
            'blurb' => 'Converting an SRRV Classic visa deposit into the sublease of a residential property (PRA-SD-FORM-0010).'],
        'restamping' => ['label' => 'SRRV Re-stamping', 'icon' => 'stamp', 'route' => 'srrv.requests.create', 'params' => ['type' => 'restamping'],
            'blurb' => 'Moving the SRRV sticker to the retiree\'s new passport: endorsed by the PRA to the Bureau of Immigration.'],
        'renewal' => ['label' => 'SRRV Renewal', 'icon' => 'calendar-check', 'route' => 'srrv.renewals.create', 'params' => [],
            'blurb' => 'The renewal of the SRRV ID card.'],
        'application' => ['label' => 'SRRV Application', 'icon' => 'file-badge', 'route' => 'srrv.applications.create', 'params' => [],
            'blurb' => 'A new retiree file, Classic or Courtesy, through to oath taking at the PRA.'],
    ];

    /** The services that open an application (not a renewal). */
    public const APPLICATION_SERVICES = ['classic', 'courtesy', 'application'];

    /** The route keys the desk accepts for {service}. */
    public static function keys(): array
    {
        return array_keys(self::SERVICES);
    }

    /** The service a saved file belongs to: its kind ('application' or 'renewal') and its visa type. */
    public static function serviceFor(string $kind, ?string $visaClass): string
    {
        // A service request's own type names its service.
        if ($kind === 'request') {
            return array_key_exists((string) $visaClass, SrrvServiceRequest::TYPES) ? $visaClass : 'termination';
        }

        if (! in_array($visaClass, SrrvApplication::VISA_CLASSES, true)) {
            return $kind;
        }

        return $kind === 'application' ? $visaClass : 'renewal_'.$visaClass;
    }

    /** Where a service's form opens for this client, starting on the service's visa type. */
    public static function formUrl(string $service, int $clientId): string
    {
        return route(self::SERVICES[$service]['route'], ['client' => $clientId] + self::SERVICES[$service]['params']);
    }

    /**
     * A service's active files, before anything is started: each opens as a read-only preview on one
     * page, and New goes on to name the retiree. Staff other than admins see their own files.
     */
    public function list(string $service): View
    {
        $application = in_array($service, self::APPLICATION_SERVICES, true);

        // Termination, Conversion and Re-stamping: their own files, opened on their own pages.
        if (array_key_exists($service, SrrvServiceRequest::TYPES)) {
            return view('srrv.services.list', [
                'service' => self::SERVICES[$service] + ['key' => $service],
                'files' => SrrvServiceRequest::where('type', $service)->whereNotIn('status', ['completed', 'cancelled'])->latest()->limit(200)->get(),
                'application' => false,
                'previewRoute' => 'srrv.requests.edit',
            ]);
        }

        $files = $application
            ? SrrvApplication::whereNotIn('status', ['released', 'cancelled'])
                ->when($service !== 'application', fn ($q) => $q->where('visa_class', $service))
                ->latest()->limit(200)->get()
            : SrrvRenewal::whereNotIn('status', ['collected', 'cancelled'])
                ->when(isset(self::SERVICES[$service]['params']['visa_class']), fn ($q) => $q->where('visa_class', self::SERVICES[$service]['params']['visa_class']))
                ->latest()->limit(200)->get();

        return view('srrv.services.list', [
            'service' => self::SERVICES[$service] + ['key' => $service],
            'files' => $files,
            'application' => $application,
        ]);
    }

    public function start(string $service): View
    {
        return view('srrv.start', ['service' => self::SERVICES[$service] + ['key' => $service]]);
    }

    /**
     * Add someone new to the client list, then open the service's form filled with what was typed.
     */
    public function startNewClient(Request $request, string $service): RedirectResponse
    {
        $validated = $request->validate(Arr::only(ClientProfileService::registrationRules(), [
            'first_name', 'middle_name', 'last_name', 'suffix', 'no_email', 'no_phone', 'email', 'phone', 'nationality',
        ]));

        $client = ClientProfileService::register($validated + ['account_category' => 'Individual'], $request);

        ActivityLogger::log('SRRV', 'REGISTER_CLIENT', "Added client '{$client->name}' at the SRRV desk for ".self::SERVICES[$service]['label']);

        return redirect()->to(self::formUrl($service, $client->id))
            ->with('success', "{$client->name} is added to the client list. Their details are filled in below.");
    }

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));
        $serviceKey = array_key_exists((string) $request->input('service'), self::SERVICES) ? (string) $request->input('service') : 'application';

        if (mb_strlen($term) < 2) {
            return response()->json(['clients' => [], 'files' => []]);
        }

        $clients = User::clientSearch($term)
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (User $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'meta' => collect([$client->passport_number ? 'Passport '.$client->passport_number : null, $client->real_email, $client->phone, $client->nationality])->filter()->implode(' · '),
                'url' => self::formUrl($serviceKey, $client->id),
            ]);

        return response()->json(['clients' => $clients, 'files' => $this->files($term)]);
    }

    /**
     * On a new form opened with ?client=, fill the fields from that client's record. The values go in
     * as old input for this request only, so every field shows them and a failed save keeps what was typed.
     */
    public static function prefill(Request $request): void
    {
        $client = $request->filled('client') && ! $request->session()->hasOldInput()
            ? User::where('role', 'client')->find($request->integer('client'))
            : null;

        if (! $client) {
            return;
        }

        $input = [];
        foreach (self::fill($client) as $name => $value) {
            preg_match('/^(form|details)\[(.+)\]$/', $name, $match)
                ? $input[$match[1]][$match[2]] = $value
                : $input[$name] = $value;
        }

        $request->session()->now('_old_input', $input);
    }

    /**
     * The SRRV applications and ID renewals already open for this name, email, phone or reference.
     * Each officer sees only their own files (OwnFilesScope); admins see all.
     *
     * @return list<array<string, string>>
     */
    private function files(string $term): array
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        $applications = SrrvApplication::query()
            ->where(fn ($q) => $q->where('retiree_name', 'like', $like)->orWhere('retiree_email', 'like', $like)
                ->orWhere('retiree_phone', 'like', $like)->orWhere('reference', 'like', $like))
            ->latest()->limit(self::LIMIT)->get()
            ->map(fn (SrrvApplication $file): array => [
                'key' => 'application-'.$file->id,
                'name' => $file->retiree_name,
                'meta' => collect([$file->reference, $file->service_label, $file->status_label])->filter()->implode(' · '),
                'url' => route('srrv.applications.show', $file),
            ]);

        $renewals = SrrvRenewal::query()
            ->where(fn ($q) => $q->where('retiree_name', 'like', $like)->orWhere('retiree_email', 'like', $like)
                ->orWhere('reference', 'like', $like)->orWhere('srrv_card_number', 'like', $like))
            ->latest()->limit(self::LIMIT)->get()
            ->map(fn (SrrvRenewal $file): array => [
                'key' => 'renewal-'.$file->id,
                'name' => $file->retiree_name,
                'meta' => collect([$file->reference, 'ID renewal', $file->status_label])->filter()->implode(' · '),
                'url' => route('srrv.renewals.show', $file),
            ]);

        return $applications->concat($renewals)->take(self::LIMIT)->values()->all();
    }

    /**
     * What the client's record fills, by field name. Only values on file are sent.
     *
     * @return array<string, string>
     */
    private static function fill(User $client): array
    {
        // What the office already knows about them, from any desk (ClientMemory): their SRRV number and
        // class from an earlier application, renewal or request, passport, birth details and address.
        $known = ClientMemory::recall($client);
        $gender = strtolower((string) ($known['gender'] ?? ''));
        $civil = strtolower((string) ($known['civil_status'] ?? ''));

        return array_filter([
            'retiree_name' => $client->name,
            'retiree_email' => $known['email'] ?? null,
            'retiree_phone' => $known['phone'] ?? null,
            'date_of_birth' => $known['date_of_birth'] ?? null,
            'nationality' => $known['nationality'] ?? null,
            'srrv_card_number' => $known['srrv_number'] ?? null,
            'visa_class' => $known['srrv_visa_class'] ?? null,
            'form[last_name]' => $client->last_name,
            'form[first_name]' => $client->first_name,
            'form[middle_name]' => $client->middle_name,
            'form[gender]' => str_starts_with($gender, 'm') ? 'M' : (str_starts_with($gender, 'f') ? 'F' : null),
            'form[civil_status]' => array_key_exists($civil, SrrvForms::CIVIL_STATUSES) ? $civil : null,
            'form[place_of_birth]' => $known['place_of_birth'] ?? null,
            'form[height]' => $known['height'] ?? null,
            'form[weight]' => $known['weight'] ?? null,
            'form[passport_no]' => $known['passport_number'] ?? null,
            'form[passport_place]' => $known['passport_place'] ?? null,
            'form[passport_expires_on]' => $known['passport_expiry'] ?? null,
            'form[primary_address]' => $known['address'] ?? null,
            'form[ph_mobile]' => $known['phone'] ?? null,
            'form[ph_email]' => $known['email'] ?? null,
            // The Retiree Request Form on a renewal.
            'form[address_local]' => $known['address'] ?? null,
            // A conversion asks when the SRRV was issued.
            'details[srrv_issued_on]' => $known['srrv_issued_on'] ?? null,
            // A re-stamping starts from the passport on record (the old one) and the local address.
            'details[old_passport_number]' => $known['passport_number'] ?? null,
            'details[address_local]' => $known['address'] ?? null,
        ], fn ($value) => filled($value));
    }
}
