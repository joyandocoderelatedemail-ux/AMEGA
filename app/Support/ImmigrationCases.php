<?php

namespace App\Support;

use App\Models\ImmigrationClient;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\ImmigrationRestamping;
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

    /** The groups the Status filter offers on top of the statuses themselves (CaseProgress::OPTIONS). */
    public const STATUSES = [
        'all' => 'All statuses',
        'open' => 'Open cases',
        'closed' => 'Closed cases',
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
     * passport or the case reference. Most recently changed first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function search(?string $kind = null, string $status = 'open', string $term = '', int $limit = 200): Collection
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

            $cases = $cases->concat($query->latest('updated_at')->limit($limit)->get());
        }

        // Released cases stay in the list, after the ones still being worked on.
        return $cases
            ->sortBy([
                fn (Model $a, Model $b) => ($a->released_on !== null) <=> ($b->released_on !== null),
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
        $missing = count($case->missingItems());

        return [
            'id' => $case->getKey(),
            'kind' => $kind,
            'reference' => $case->reference(),
            'service' => $service,
            'label' => self::kinds()[$kind]['label'],
            'summary' => self::summary($case),
            'progress' => $case->progressKey(),
            'progress_label' => $case->progressLabel(),
            'closed' => $case->released_on !== null,
            'missing' => $missing,
            'opened' => $case->created_at,
            'filed_on' => $case->filed_on,
            'released_on' => $case->released_on,
            'updated' => $case->updated_at,
            'client' => $client,
            'url' => $client ? route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => $service, 'case' => $case->id]) : null,
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
