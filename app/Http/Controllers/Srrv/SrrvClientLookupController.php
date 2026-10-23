<?php

namespace App\Http\Controllers\Srrv;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\User;
use App\Services\ActivityLogger;
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

    /** The services that start here, and the form each one opens. */
    public const SERVICES = [
        'application' => ['label' => 'SRRV Application', 'icon' => 'file-badge', 'route' => 'srrv.applications.create',
            'blurb' => 'A new retiree file, Classic or Courtesy, through to oath taking at the PRA.'],
        'renewal' => ['label' => 'ID Renewal', 'icon' => 'calendar-check', 'route' => 'srrv.renewals.create',
            'blurb' => 'The renewal of the SRRV ID card.'],
    ];

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

        return redirect()->route(self::SERVICES[$service]['route'], ['client' => $client->id])
            ->with('success', "{$client->name} is added to the client list. Their details are filled in below.");
    }

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));
        $service = self::SERVICES[$request->input('service')] ?? self::SERVICES['application'];

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
                'url' => route($service['route'], ['client' => $client->id]),
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
            preg_match('/^form\[(.+)\]$/', $name, $match)
                ? $input['form'][$match[1]] = $value
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
        $gender = strtolower((string) $client->gender);
        $civil = strtolower((string) $client->civil_status);
        // Their last SRRV file at this desk gives the SRRV card number for a renewal.
        $previous = SrrvApplication::query()
            ->where(fn ($query) => $query->when($client->real_email, fn ($q) => $q->where('retiree_email', $client->real_email))
                ->when($client->phone, fn ($q) => $q->orWhere('retiree_phone', $client->phone)))
            ->latest()
            ->first();

        return array_filter([
            'retiree_name' => $client->name,
            'retiree_email' => $client->real_email,
            'retiree_phone' => $client->phone,
            'date_of_birth' => $client->date_of_birth?->format('Y-m-d'),
            'nationality' => $client->nationality,
            'srrv_card_number' => $previous?->srrv_card_number,
            'form[last_name]' => $client->last_name,
            'form[first_name]' => $client->first_name,
            'form[middle_name]' => $client->middle_name,
            'form[gender]' => str_starts_with($gender, 'm') ? 'M' : (str_starts_with($gender, 'f') ? 'F' : null),
            'form[civil_status]' => array_key_exists($civil, SrrvForms::CIVIL_STATUSES) ? $civil : null,
            'form[height]' => $client->height,
            'form[weight]' => $client->weight,
            'form[passport_no]' => $client->passport_number,
            'form[passport_expires_on]' => $client->passport_expiry?->format('Y-m-d'),
            'form[primary_address]' => $client->address,
            'form[ph_mobile]' => $client->phone,
            'form[ph_email]' => $client->real_email,
            // The Retiree Request Form on a renewal.
            'form[address_local]' => $client->address,
        ], fn ($value) => filled($value));
    }
}
