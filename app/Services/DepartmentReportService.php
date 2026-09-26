<?php

namespace App\Services;

use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientExtension;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One report card per department desk for the admin dashboard.
 *
 * Every figure is a query. Desk files carry the OwnFilesScope, so an agent
 * sees their own files here while an admin sees every desk's full book.
 * Money is reported per currency because the visa and SRRV desks bill in
 * more than one; ticketing and immigration record pesos only.
 *
 * @phpstan-type Money array<string, float>
 * @phpstan-type Department array{
 *     key: string,
 *     label: string,
 *     icon: string,
 *     page: string,
 *     route: string,
 *     unit: string,
 *     total: int,
 *     newThisMonth: int,
 *     pipeline: array{open: int, done: int, cancelled: int}|null,
 *     pipelineLabels: array{open: string, done: string, cancelled: string}|null,
 *     facts: list<array{label: string, value: int}>,
 *     money: list<array{label: string, amounts: Money}>,
 *     attention: array{count: int, label: string, clear: string},
 * }
 */
class DepartmentReportService
{
    private Carbon $monthStart;

    private Carbon $monthEnd;

    public function __construct()
    {
        $this->monthStart = Carbon::now()->startOfMonth();
        $this->monthEnd = Carbon::now()->endOfMonth();
    }

    /**
     * The departments this user may open, each with its report.
     *
     * @return list<Department>
     */
    public function forUser(User $user): array
    {
        $departments = [
            'ticketing' => fn (): array => $this->ticketing(),
            'immigration' => fn (): array => $this->immigration(),
            'visa_assistance' => fn (): array => $this->visa(),
            'srrv' => fn (): array => $this->srrv(),
        ];

        $reports = [];

        foreach ($departments as $page => $build) {
            if ($user->canAccessPage($page)) {
                $reports[] = $build();
            }
        }

        return $reports;
    }

    /**
     * Airline ticketing. Counts match the ticketing desk dashboard; money
     * leaves out quotations and cancelled tickets, which were never owed.
     *
     * @return Department
     */
    public function ticketing(): array
    {
        $statusCounts = TicketBooking::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $billable = fn (): Builder => TicketBooking::query()
            ->where('is_quotation', false)
            ->whereNot('status', TicketBooking::STATUS_CANCELLED);

        $today = Carbon::today();

        return [
            'key' => 'ticketing',
            'label' => 'Ticketing',
            'icon' => 'ticket',
            'page' => 'ticketing',
            'route' => 'ticketing.dashboard',
            'unit' => 'ticket',
            'total' => (int) $statusCounts->sum(),
            'newThisMonth' => $this->createdThisMonth(TicketBooking::query()),
            'pipeline' => [
                'open' => (int) ($statusCounts[TicketBooking::STATUS_PENDING] ?? 0),
                'done' => (int) $statusCounts->only([TicketBooking::STATUS_CONFIRMED, TicketBooking::STATUS_ISSUED])->sum(),
                'cancelled' => (int) ($statusCounts[TicketBooking::STATUS_CANCELLED] ?? 0),
            ],
            'pipelineLabels' => ['open' => 'Pending', 'done' => 'Confirmed or issued', 'cancelled' => 'Cancelled'],
            'facts' => [
                ['label' => 'Domestic', 'value' => TicketBooking::where('travel_type', 'domestic')->count()],
                ['label' => 'International', 'value' => TicketBooking::where('travel_type', 'international')->count()],
                ['label' => 'Passengers', 'value' => (int) TicketBooking::sum('total_passengers')],
            ],
            'money' => [
                ['label' => 'Collected', 'amounts' => $this->nonZero(['PHP' => (float) $billable()->sum('amount_paid')])],
                ['label' => 'Outstanding', 'amounts' => $this->nonZero(['PHP' => (float) $billable()->sum(DB::raw($this->balanceSql('total_amount')))])],
            ],
            'attention' => [
                'count' => TicketBooking::whereDate('departure_date', '>=', $today->toDateString())
                    ->whereDate('departure_date', '<=', $today->copy()->addDays(7)->toDateString())
                    ->whereNot('status', TicketBooking::STATUS_CANCELLED)
                    ->count(),
                'label' => 'departing within 7 days',
                'clear' => 'Nothing departing in the next 7 days',
            ],
        ];
    }

    /**
     * The immigration counter. Client sheets have no pipeline, so the report
     * leads with what the counter chases: flagged sheets and visas running out.
     *
     * @return Department
     */
    public function immigration(): array
    {
        $today = Carbon::today();

        $extensionsThisMonth = ImmigrationClientExtension::whereBetween('extension_date', [$this->monthStart, $this->monthEnd]);

        return [
            'key' => 'immigration',
            'label' => 'Immigration',
            'icon' => 'stamp',
            'page' => 'immigration',
            'route' => 'admin.immigration.dashboard',
            'unit' => 'client sheet',
            'total' => ImmigrationClient::count(),
            'newThisMonth' => $this->createdThisMonth(ImmigrationClient::query()),
            'pipeline' => null,
            'pipelineLabels' => null,
            'facts' => [
                ['label' => 'Extensions this month', 'value' => (clone $extensionsThisMonth)->count()],
                ['label' => 'Flagged', 'value' => ImmigrationClient::flagged()->count()],
                ['label' => 'Expiring in 7 days', 'value' => ImmigrationClient::whereNotNull('visa_expiry_date')
                    ->whereBetween('visa_expiry_date', [$today, $today->copy()->addDays(7)])
                    ->count()],
            ],
            'money' => [
                ['label' => 'Collected this month', 'amounts' => $this->nonZero(['PHP' => (float) (clone $extensionsThisMonth)->sum('amount_paid')])],
                ['label' => 'Collected to date', 'amounts' => $this->nonZero(['PHP' => (float) ImmigrationClientExtension::sum('amount_paid')])],
            ],
            'attention' => [
                'count' => ImmigrationClient::requiringAttention()->count(),
                'label' => 'flagged or expiring soon',
                'clear' => 'No flagged or expiring sheets',
            ],
        ];
    }

    /**
     * Visa assistance: visit visas, e-visas and passporting on one desk.
     *
     * @return Department
     */
    public function visa(): array
    {
        $statusCounts = VisaApplication::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $statusCounts->sum();
        $released = (int) ($statusCounts['released'] ?? 0);
        $cancelled = (int) ($statusCounts['cancelled'] ?? 0);

        $billable = fn (): Builder => VisaApplication::query()->whereNot('status', 'cancelled');

        return [
            'key' => 'visa',
            'label' => 'Visa Assistance',
            'icon' => 'globe',
            'page' => 'visa_assistance',
            'route' => 'visa.dashboard',
            'unit' => 'application',
            'total' => $total,
            'newThisMonth' => $this->createdThisMonth(VisaApplication::query()),
            'pipeline' => ['open' => $total - $released - $cancelled, 'done' => $released, 'cancelled' => $cancelled],
            'pipelineLabels' => ['open' => 'In progress', 'done' => 'Released', 'cancelled' => 'Cancelled'],
            'facts' => [
                ['label' => 'Visit visa', 'value' => VisaApplication::where('service_type', 'visit_visa')->count()],
                ['label' => 'e-Visa', 'value' => VisaApplication::where('service_type', 'e_visa')->count()],
                ['label' => 'Passporting', 'value' => VisaApplication::where('service_type', 'passporting')->count()],
            ],
            'money' => [
                ['label' => 'Collected', 'amounts' => $this->sumByCurrency($billable(), 'amount_paid')],
                ['label' => 'Outstanding', 'amounts' => $this->sumByCurrency($billable(), $this->balanceSql('total_amount'))],
            ],
            'attention' => [
                'count' => VisaApplication::where('processing_speed', 'rush')
                    ->whereNotIn('status', ['released', 'cancelled'])
                    ->count(),
                'label' => 'rush files still open',
                'clear' => 'No rush files waiting',
            ],
        ];
    }

    /**
     * The SRRV desk: new retiree applications plus the yearly renewals.
     * Applications and renewals are billed separately, so their money is
     * added together per currency.
     *
     * @return Department
     */
    public function srrv(): array
    {
        $statusCounts = SrrvApplication::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $statusCounts->sum();
        $released = (int) ($statusCounts['released'] ?? 0);
        $cancelled = (int) ($statusCounts['cancelled'] ?? 0);

        $billableApplications = fn (): Builder => SrrvApplication::query()->whereNot('status', 'cancelled');
        $billableRenewals = fn (): Builder => SrrvRenewal::query()->whereNot('status', 'cancelled');

        return [
            'key' => 'srrv',
            'label' => 'SRRV',
            'icon' => 'landmark',
            'page' => 'srrv',
            'route' => 'srrv.dashboard',
            'unit' => 'application',
            'total' => $total,
            'newThisMonth' => $this->createdThisMonth(SrrvApplication::query()),
            'pipeline' => ['open' => $total - $released - $cancelled, 'done' => $released, 'cancelled' => $cancelled],
            'pipelineLabels' => ['open' => 'In progress', 'done' => 'Released', 'cancelled' => 'Cancelled'],
            'facts' => [
                ['label' => 'Classic', 'value' => SrrvApplication::where('visa_class', 'classic')->count()],
                ['label' => 'Courtesy', 'value' => SrrvApplication::where('visa_class', 'courtesy')->count()],
                ['label' => 'Renewals open', 'value' => SrrvRenewal::whereNotIn('status', ['collected', 'cancelled'])->count()],
            ],
            'money' => [
                ['label' => 'Collected', 'amounts' => $this->mergeMoney(
                    $this->sumByCurrency($billableApplications(), 'amount_paid'),
                    $this->sumByCurrency($billableRenewals(), 'amount_paid'),
                )],
                ['label' => 'Outstanding', 'amounts' => $this->mergeMoney(
                    $this->sumByCurrency($billableApplications(), $this->balanceSql('service_fee')),
                    $this->sumByCurrency($billableRenewals(), $this->balanceSql('fee_amount')),
                )],
            ],
            'attention' => [
                'count' => SrrvApplication::whereNull('oath_at')
                    ->whereNotNull('payment_in_full_at')
                    ->whereNot('status', 'cancelled')
                    ->count(),
                'label' => 'paid in full, awaiting oath',
                'clear' => 'Nobody waiting on an oath',
            ],
        ];
    }

    private function createdThisMonth(Builder $query): int
    {
        return $query->whereBetween('created_at', [$this->monthStart, $this->monthEnd])->count();
    }

    /**
     * SQL for what is still owed on each row, never negative when a file is overpaid.
     */
    private function balanceSql(string $billedColumn): string
    {
        return "CASE WHEN COALESCE({$billedColumn}, 0) > COALESCE(amount_paid, 0) THEN COALESCE({$billedColumn}, 0) - COALESCE(amount_paid, 0) ELSE 0 END";
    }

    /**
     * Sum a column or SQL expression per currency.
     *
     * @return Money
     */
    private function sumByCurrency(Builder $query, string $sumSql): array
    {
        return $this->nonZero(
            $query->selectRaw("currency, SUM({$sumSql}) as aggregate")
                ->groupBy('currency')
                ->pluck('aggregate', 'currency')
                ->map(fn (mixed $amount): float => (float) $amount)
                ->all()
        );
    }

    /**
     * @param  Money  ...$sets
     * @return Money
     */
    private function mergeMoney(array ...$sets): array
    {
        $merged = [];

        foreach ($sets as $set) {
            foreach ($set as $currency => $amount) {
                $merged[$currency] = ($merged[$currency] ?? 0) + $amount;
            }
        }

        return $this->nonZero($merged);
    }

    /**
     * @param  Money  $amounts
     * @return Money
     */
    private function nonZero(array $amounts): array
    {
        $amounts = array_filter($amounts, fn (float $amount): bool => round($amount, 2) > 0);
        ksort($amounts);

        return $amounts;
    }
}
