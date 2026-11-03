<?php

namespace App\Support;

use App\Models\ImmigrationClient;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\ImmigrationRestamping;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Every immigration case, whichever service it belongs to, as one list: the cases in a
 * client's folder, or the counter's list of cases across clients. Visa Extension is
 * the folder's own ledger rather than a case, so it is not listed here.
 */
class ImmigrationCases
{
    /**
     * The kinds of case, each tied to the service it is opened from.
     *
     * @return array<string, array{service: string, label: string}>
     */
    public static function kinds(): array
    {
        $label = fn (string $service) => ImmigrationServices::find($service)['label'];

        return [
            'overstay' => ['service' => 'visa_overstaying', 'label' => $label('visa_overstaying')],
            'restamping' => ['service' => 're_stamping_amendment', 'label' => $label('re_stamping_amendment')],
            'spouse' => ['service' => '13a_spouse', 'label' => $label('13a_spouse')],
            'permanent' => ['service' => '13a_permanent', 'label' => $label('13a_permanent')],
            'renewal' => ['service' => 'permanent_13a_series', 'label' => $label('permanent_13a_series')],
            'dual' => ['service' => 'dual_citizenship', 'label' => $label('dual_citizenship')],
            'downgrade' => ['service' => 'visa_downgrading', 'label' => $label('visa_downgrading')],
            'ecc' => ['service' => 'ecc', 'label' => $label('ecc')],
        ];
    }

    /** SQL order putting released files with a pending edit request first, then the declined ones, so a limit never drops them. */
    private const EDIT_REQUEST_ORDER_SQL = 'CASE WHEN edit_requested_at IS NOT NULL AND edit_request_replied_at IS NULL THEN 0 WHEN edit_requested_at IS NOT NULL THEN 1 ELSE 2 END';

    /** SQL: a released file with an edit request the admin has not answered. */
    private const EDIT_PENDING_SQL = '(edit_requested_at IS NOT NULL AND edit_request_replied_at IS NULL)';

    /** The groups the Status filter offers on top of the statuses themselves (CaseProgress::OPTIONS). */
    public const STATUSES = [
        'all' => 'All statuses',
        'open' => 'Open transactions',
        'closed' => 'Closed transactions',
    ];

    /**
     * A client's cases, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forClient(ImmigrationClient $client): Collection
    {
        $client->loadMissing(['overstays', 'restampings', 'petitions']);

        return collect()
            ->concat($client->overstays)
            ->concat($client->restampings)
            ->concat($client->petitions)
            ->sortByDesc(fn (Model $case) => $case->created_at?->timestamp ?? 0)
            ->map(fn (Model $case) => self::row($case, $client))
            ->values();
    }

    /**
     * Cases across clients, filtered by kind, status and a search on the client's name,
     * passport or the case reference. Most recently changed first. $createdBy limits it to the
     * cases one staff member opened or last submitted.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function search(?string $kind = null, string $status = 'open', string $term = '', int $limit = 200, ?int $createdBy = null): Collection
    {
        $term = trim($term);
        $reference = self::parseReference($term);

        $queries = [
            'overstay' => ImmigrationOverstay::query(),
            'restamping' => ImmigrationRestamping::query(),
            'petition' => ImmigrationPetition::query(),
        ];

        $cases = collect();

        foreach ($queries as $table => $query) {
            if ($kind !== null && ! self::tableHolds($table, $kind)) {
                continue;
            }

            $query->with('client')
                ->when($createdBy !== null, fn (Builder $q) => self::ownedBy($q, $createdBy))
                ->when($table === 'petition' && $kind !== null, fn (Builder $q) => $q->where('type', $kind))
                ->when($status === 'open', fn (Builder $q) => $q->whereNull('released_on'))
                ->when($status === 'closed', fn (Builder $q) => $q->whereNotNull('released_on'))
                // A case never updated is in preparation.
                ->when(array_key_exists($status, CaseProgress::OPTIONS), fn (Builder $q) => $q->where(
                    fn (Builder $w) => $w->where('workflow_status', $status)->when($status === CaseProgress::DEFAULT, fn (Builder $n) => $n->orWhereNull('workflow_status'))
                ));

            if ($reference !== null) {
                if (! self::tableHolds($table, $reference['kind'])) {
                    continue;
                }
                $query->whereKey($reference['id'])
                    ->when($table === 'petition', fn (Builder $q) => $q->where('type', $reference['kind']));
            } elseif ($term !== '') {
                $query->whereHas('client', fn (Builder $client) => $client->search($term));
            }

            // A service's list is cut to the limit in this order, so an old file the admin is holding is never dropped.
            $cases = $cases->concat(
                $query->when($status === 'service', fn (Builder $q) => $q->orderByRaw(self::EDIT_REQUEST_ORDER_SQL)->orderHeldFirst('released_on IS NOT NULL'))
                    ->latest('updated_at')->limit($limit)->get()
            );
        }

        // Released cases stay in the list, after the ones still being worked on. A service's own list
        // ('service') puts the files held for the admin first: waiting, then returned, then in progress, then released.
        // A released file the agent asked to edit waits on the admin (pending first), then a declined request.
        $stage = fn (Model $case): int => match (true) {
            $status !== 'service' => $case->released_on !== null ? 1 : 0,
            $case->hasEditRequest() => 0,
            $case->editRequestDeclined() => 1,
            $case->approval_status === ImmigrationPetition::APPROVAL_PENDING => 0,
            $case->approval_status === ImmigrationPetition::APPROVAL_RETURNED => 1,
            $case->released_on === null => 2,
            default => 3,
        };

        return $cases
            ->sortBy([
                fn (Model $a, Model $b) => $stage($a) <=> $stage($b),
                fn (Model $a, Model $b) => ($b->updated_at?->timestamp ?? 0) <=> ($a->updated_at?->timestamp ?? 0),
            ])
            ->take($limit)
            ->map(fn (Model $case) => self::row($case, $case->client))
            ->values();
    }

    /**
     * Client folders, narrowed to those holding a service (by its service key: Visa Extension by
     * its ledger, the others by their cases), with what each row shows of its services.
     *
     * @return Builder<ImmigrationClient>
     */
    public static function folders(?string $service = null, ?Builder $query = null): Builder
    {
        $query ??= ImmigrationClient::query();
        $petitionType = $service ? ImmigrationPetition::typeFor($service) : null;

        $query = match (true) {
            $service === 'visa_extension' => $query->whereHas('extensions'),
            $service === 'visa_overstaying' => $query->whereHas('overstays'),
            $service === 're_stamping_amendment' => $query->whereHas('restampings'),
            $petitionType !== null => $query->whereHas('petitions', fn (Builder $case) => $case->where('type', $petitionType)),
            default => $query,
        };

        return $query->withCount(['extensions', 'overstays', 'restampings'])
            ->with('petitions:id,immigration_client_id,type,released_on');
    }

    /**
     * The client's most recent case of this service (by kind: overstay, restamping or a petition type),
     * which a new case of theirs can start from; null when they have never had one.
     */
    public static function previousFor(ImmigrationClient $client, string $kind): ?Model
    {
        return match (true) {
            $kind === 'overstay' => $client->overstays()->first(),
            $kind === 'restamping' => $client->restampings()->first(),
            array_key_exists($kind, self::kinds()) => $client->petitions()->where('type', $kind)->first(),
            default => null,
        };
    }

    /** One case by its kind (overstay, restamping or a petition type) and id, or null. */
    public static function find(string $kind, int $id): ?Model
    {
        return match (true) {
            $kind === 'overstay' => ImmigrationOverstay::find($id),
            $kind === 'restamping' => ImmigrationRestamping::find($id),
            array_key_exists($kind, self::kinds()) => ImmigrationPetition::where('type', $kind)->find($id),
            default => null,
        };
    }

    /**
     * The case a folder form is open on: the service's case with this id, or (with no id) its open case; null for a
     * service with no cases (Visa Extension) or when nothing matches.
     */
    public static function caseFor(ImmigrationClient $client, string $key, ?int $id): ?Model
    {
        return match (true) {
            $key === 'visa_overstaying' => $id === null ? $client->openOverstay() : $client->overstays()->find($id),
            $key === 're_stamping_amendment' => $id === null ? $client->openRestamping() : $client->restampings()->find($id),
            ($type = ImmigrationPetition::typeFor($key)) !== null => $id === null ? $client->openPetition($type) : $client->petitions()->where('type', $type)->find($id),
            default => null,
        };
    }

    /** Whether the client already has another case of this one's service that is not released. */
    public static function hasOpenSibling(Model $case): bool
    {
        return $case::query()
            ->where('immigration_client_id', $case->immigration_client_id)
            ->when($case instanceof ImmigrationPetition, fn (Builder $query) => $query->where('type', $case->type))
            ->whereKeyNot($case->getKey())
            ->whereNull('released_on')
            ->exists();
    }

    /**
     * Open a released case again (a supervisor's Reopen, or an approved edit request; the latter, when $grantedBy is given, also
     * returns the file to the agent with the admin's note, and works on an approved case that is not released). A case closed by
     * Denied, Withdrawn or Deferred starts over in preparation; a released one keeps its status. Any edit
     * request on it is cleared. Returns why it cannot be reopened, or null once it is open.
     */
    public static function reopen(Model $case, ?User $grantedBy = null, ?string $note = null): ?string
    {
        // An approved edit request also unlocks an approved case that is not released.
        if (! $case->released_on && ! ($grantedBy && $case->approval_status === ImmigrationPetition::APPROVAL_APPROVED)) {
            return "{$case->reference()} is not released.";
        }

        if ($case->released_on && self::hasOpenSibling($case)) {
            return "The client already has another open transaction of this service, so {$case->reference()} cannot be reopened.";
        }

        // A status-change request on an Approved status that is not released just unlocks the status: the file stays
        // approved and keeps its status, its details stay locked.
        if ($grantedBy && ! $case->released_on && $case->edit_request_type === 'status' && $case->approvedStatusLocked()) {
            $case->forceFill(['status_unlocked_at' => now()] + $case::clearedEditRequest())->save();

            return null;
        }

        $updates = $case->released_on
            ? ['released_on' => null] + (CaseProgress::closes($case->progressKey())
                ? ['workflow_status' => CaseProgress::DEFAULT, 'workflow_status_at' => now()]
                : [])
            : [];

        // Granting an edit also returns the file: the agent edits it, and the save goes to the admin again.
        // A request only to change the status of a released file just reopens it: the file stays approved, its
        // details stay locked, and the status dropdown works again.
        if ($grantedBy && $case->edit_request_type !== 'status') {
            $updates += [
                'approval_status' => ImmigrationPetition::APPROVAL_RETURNED,
                'review_note' => 'Edit approved: '.($note ?: 'the admin unlocked this file for editing').'. Make your changes and save to send it for approval again.',
                'reviewed_by' => $grantedBy->id,
                'reviewed_at' => now(),
            ];
        }

        // A released file whose status is Approved comes back with that status unlocked, so the agent can move it.
        if ($case->released_on && $case->progressKey() === 'approved') {
            $updates['status_unlocked_at'] = now();
        }

        $case->forceFill($updates + $case::clearedEditRequest())->save();

        return null;
    }

    /**
     * What each kind has on the desk, for the dashboard cards: the cases still open plus those held for the
     * admin whatever their release date (the same files its service list shows first), and how many are held.
     *
     * @return array<string, array{open: int, waiting: int}>
     */
    public static function activeCounts(?int $by = null): array
    {
        $held = ImmigrationPetition::APPROVAL_HELD;
        $counts = array_map(fn () => ['open' => 0, 'waiting' => 0], self::kinds());
        $counts['overstay'] = self::activeCountsOf(ImmigrationOverstay::query(), $by);
        $counts['restamping'] = self::activeCountsOf(ImmigrationRestamping::query(), $by);

        foreach (ImmigrationPetition::query()->select('type')->selectRaw('count(*) as total')
            ->selectRaw('sum(case when released_on is null or approval_status in (?, ?) or '.self::EDIT_PENDING_SQL.' then 1 else 0 end) as open_total', $held)
            ->selectRaw('sum(case when approval_status in (?, ?) or '.self::EDIT_PENDING_SQL.' then 1 else 0 end) as waiting_total', $held)
            ->when($by !== null, fn (Builder $q) => self::ownedBy($q, $by))
            ->groupBy('type')->get() as $row) {
            $counts[$row->type] = ['open' => (int) $row->open_total, 'waiting' => (int) $row->waiting_total];
        }

        return $counts;
    }

    /** @return array{open: int, waiting: int} */
    private static function activeCountsOf(Builder $query, ?int $by = null): array
    {
        $query->when($by !== null, fn (Builder $q) => self::ownedBy($q, $by));

        return [
            'open' => (clone $query)->where(fn (Builder $q) => $q->whereNull('released_on')->orWhereIn('approval_status', ImmigrationPetition::APPROVAL_HELD)->orWhere(fn (Builder $e) => $e->editRequested()))->count(),
            'waiting' => (clone $query)->where(fn (Builder $q) => $q->whereIn('approval_status', ImmigrationPetition::APPROVAL_HELD)->orWhere(fn (Builder $e) => $e->editRequested()))->count(),
        ];
    }

    /**
     * The folders with Visa Extension records, narrowed to the ones a staff member opened or last
     * submitted an extension on when $by is given.
     *
     * @return Builder<ImmigrationClient>
     */
    public static function extensionFolders(?int $by = null): Builder
    {
        return ImmigrationClient::whereHas('extensions')
            ->when($by !== null, fn (Builder $q) => $q->where(
                fn (Builder $w) => $w->where('created_by', $by)->orWhereHas('extensions', fn (Builder $e) => $e->where('acknowledged_by', $by))
            ));
    }

    /** How many folders have a Visa Extension held for the admin (one per client, as the list shows). */
    public static function heldExtensionFolders(?int $by = null): int
    {
        return (clone self::extensionFolders($by))->whereHas('extensions', fn (Builder $e) => $e->held())->count();
    }

    /** The cases one staff member opened or last submitted. */
    private static function ownedBy(Builder $query, int $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('created_by', $user)->orWhere('acknowledged_by', $user));
    }

    /**
     * The staff member whose own cases a service list is limited to, or null when they see every case in
     * the folders they can open: admins, and anyone holding view_all or approve on the immigration desk.
     */
    public static function ownerFilter(User $user): ?int
    {
        return $user->seesOnlyOwnFiles()
            && ! $user->hasPermission('immigration.view_all')
            && ! $user->hasPermission('immigration.approve')
            ? $user->id
            : null;
    }

    /** How many cases are not released yet, per kind. */
    public static function openCounts(): array
    {
        $counts = array_fill_keys(array_keys(self::kinds()), 0);
        $counts['overstay'] = ImmigrationOverstay::whereNull('released_on')->count();
        $counts['restamping'] = ImmigrationRestamping::whereNull('released_on')->count();

        foreach (ImmigrationPetition::whereNull('released_on')->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type') as $type => $total) {
            $counts[$type] = (int) $total;
        }

        return $counts;
    }

    /**
     * One case as the folder and the counter show it.
     *
     * @return array<string, mixed>
     */
    public static function row(Model $case, ?ImmigrationClient $client): array
    {
        $kind = self::kindOf($case);
        $service = self::kinds()[$kind]['service'];
        $missingItems = array_values($case->missingItems());
        $missing = count($missingItems);
        $released = $case->released_on !== null;
        // A closing status (denied, withdrawn, deferred) keeps saying how it ended; any other released case reads Released.
        $progress = $released && ! CaseProgress::closes($case->progressKey()) ? CaseProgress::RELEASED : $case->progressKey();

        return [
            'id' => $case->getKey(),
            'kind' => $kind,
            'reference' => $case->reference(),
            'service' => $service,
            'label' => self::kinds()[$kind]['label'],
            'summary' => self::summary($case),
            'progress' => $progress,
            'progress_label' => CaseProgress::label($progress),
            'closed' => $released,
            'released' => $released,
            // Released or approved, and so locked: whether an edit was asked for ('pending' or 'declined'), why, and the admin's reply.
            'edit_request' => match (true) {
                ! $case->needsEditRequest() && ! $case->approvedStatusLocked() => null,
                $case->hasEditRequest() => 'pending',
                $case->editRequestDeclined() => 'declined',
                default => null,
            },
            // The Bureau's Approved status is locked for agents until the admin approves a status-change request.
            'status_locked' => $case->approvedStatusLocked(),
            'edit_note' => $case->edit_request_note,
            'edit_reply' => $case->edit_request_reply,
            'edit_type' => $case->edit_request_type ?? 'details',
            'workflow' => $case->progressKey(),
            'approval' => $case->approval_status,
            'approval_note' => $case->review_note,
            'missing' => $missing,
            'missing_items' => $missingItems,
            'opened' => $case->created_at,
            'filed_on' => $case->filed_on,
            'released_on' => $case->released_on,
            'updated' => $case->updated_at,
            'client' => $client,
            'url' => $client ? route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => $service, 'case' => $case->id]) : null,
            // The same file, read-only on one page: what the application list opens.
            'preview_url' => $client ? route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => $service, 'case' => $case->id, 'preview' => 1]) : null,
        ];
    }

    /** The kind of a case model: overstay, restamping, or the petition's type. */
    public static function kindOf(Model $case): string
    {
        return match (true) {
            $case instanceof ImmigrationOverstay => 'overstay',
            $case instanceof ImmigrationRestamping => 'restamping',
            $case instanceof ImmigrationPetition => $case->type,
        };
    }

    /** One line on what the case is about. */
    private static function summary(Model $case): string
    {
        return match (true) {
            $case instanceof ImmigrationOverstay => $case->expired_on
                ? 'Stay lapsed '.$case->expired_on->format('M d, Y').($case->overstayLength() ? ' · '.$case->overstayLength() : '')
                : 'Overstay',
            $case instanceof ImmigrationRestamping => collect($case->applications)
                ->map(fn ($key) => ImmigrationRestamping::APPLICATIONS[$key] ?? $key)->implode(', ') ?: 'Re-stamping',
            $case instanceof ImmigrationPetition => ImmigrationPetition::TITLES[$case->type] ?? '13(A)',
        };
    }

    /**
     * "OS-2026-0014" → the kind and the id, or null when the term is not a reference.
     *
     * @return array{kind: string, id: int}|null
     */
    public static function parseReference(string $term): ?array
    {
        if (! preg_match('/^\s*(OS|RS|13AP|13AM|ACR|DC|DG|ECC)-\d{4}-0*(\d+)\s*$/i', $term, $match)) {
            return null;
        }

        $kind = ['OS' => 'overstay', 'RS' => 'restamping', '13AP' => 'spouse', '13AM' => 'permanent', 'ACR' => 'renewal', 'DC' => 'dual', 'DG' => 'downgrade', 'ECC' => 'ecc'][strtoupper($match[1])];

        return ['kind' => $kind, 'id' => (int) $match[2]];
    }

    private static function tableHolds(string $table, string $kind): bool
    {
        return match ($table) {
            'overstay' => $kind === 'overstay',
            'restamping' => $kind === 'restamping',
            default => in_array($kind, ImmigrationPetition::SERVICES, true),
        };
    }
}
