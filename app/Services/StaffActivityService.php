<?php

namespace App\Services;

use App\Http\Controllers\Admin\AdminAgentController;
use App\Models\ActivityLog;
use App\Models\Scopes\OwnFilesScope;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What each staff account did in a period: the desk files they opened, the
 * tickets they issued and sold, and the actions the activity log recorded.
 * Admin-only reporting, so the desks' own-files scope is lifted throughout.
 *
 * @phpstan-type StaffSummary array{
 *     user: User,
 *     tickets: int,
 *     visa: int,
 *     srrv: int,
 *     files: int,
 *     issued: int,
 *     sales: float,
 *     actions: int,
 *     lastActiveAt: ?Carbon,
 * }
 */
class StaffActivityService
{
    /**
     * Staff accounts matching the search, busiest in the period first.
     *
     * @return LengthAwarePaginator<int, StaffSummary>
     */
    public function summaries(DateRange $range, ?string $search = null, ?string $role = null, int $perPage = 10): LengthAwarePaginator
    {
        $rows = $this->summarize($this->staffQuery($search, $role)->get(), $range)
            ->sortBy([
                ['files', 'desc'],
                ['actions', 'desc'],
                [fn (array $row): string => strtolower($row['user']->name), 'asc'],
            ])->values();

        $page = LengthAwarePaginator::resolveCurrentPage('staff_page');

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'staff_page'],
        );
    }

    /**
     * The period's figures for each of these staff accounts.
     *
     * @param  Collection<int, User>  $staff
     * @return Collection<int, StaffSummary>
     */
    private function summarize(Collection $staff, DateRange $range): Collection
    {
        $ids = $staff->pluck('id')->all();

        $tickets = $this->countBy(TicketBooking::class, $range, $ids);
        $visa = $this->countBy(VisaApplication::class, $range, $ids);
        $srrvApplications = $this->countBy(SrrvApplication::class, $range, $ids);
        $srrvRenewals = $this->countBy(SrrvRenewal::class, $range, $ids);

        $issued = $range->apply($this->unscoped(TicketBooking::class), 'issued_at')
            ->whereIn('issued_by', $ids)
            ->selectRaw('issued_by, COUNT(*) as aggregate')
            ->groupBy('issued_by')
            ->pluck('aggregate', 'issued_by');

        $sales = $range->apply($this->billableTickets())
            ->whereIn('created_by', $ids)
            ->selectRaw('created_by, SUM(total_amount) as aggregate')
            ->groupBy('created_by')
            ->pluck('aggregate', 'created_by');

        $actions = $range->apply(ActivityLog::query())
            ->whereIn('user_id', $ids)
            ->selectRaw('user_id, COUNT(*) as aggregate')
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id');

        $lastActive = ActivityLog::query()
            ->whereIn('user_id', $ids)
            ->selectRaw('user_id, MAX(created_at) as aggregate')
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id');

        return $staff->map(function (User $user) use ($tickets, $visa, $srrvApplications, $srrvRenewals, $issued, $sales, $actions, $lastActive): array {
            $counts = [
                'tickets' => (int) ($tickets[$user->id] ?? 0),
                'visa' => (int) ($visa[$user->id] ?? 0),
                'srrv' => (int) ($srrvApplications[$user->id] ?? 0) + (int) ($srrvRenewals[$user->id] ?? 0),
            ];

            return ['user' => $user] + $counts + [
                'files' => array_sum($counts),
                'issued' => (int) ($issued[$user->id] ?? 0),
                'sales' => (float) ($sales[$user->id] ?? 0),
                'actions' => (int) ($actions[$user->id] ?? 0),
                'lastActiveAt' => isset($lastActive[$user->id]) ? Carbon::parse($lastActive[$user->id]) : null,
            ];
        })->values();
    }

    /**
     * One staff member's period in detail: totals plus the files and actions behind them.
     *
     * @return array{
     *     summary: StaffSummary,
     *     tickets: Collection<int, TicketBooking>,
     *     visaFiles: Collection<int, VisaApplication>,
     *     srrvApplications: Collection<int, SrrvApplication>,
     *     srrvRenewals: Collection<int, SrrvRenewal>,
     *     activity: Collection<int, ActivityLog>,
     *     collected: float,
     * }
     */
    public function forStaff(User $user, DateRange $range): array
    {
        $summary = $this->summarize(collect([$user]), $range)->first();

        $owned = fn (string $model): Builder => $range->apply($this->unscoped($model))
            ->where('created_by', $user->id)
            ->latest();

        return [
            'summary' => $summary,
            'tickets' => $owned(TicketBooking::class)->limit(50)->get(),
            'visaFiles' => $owned(VisaApplication::class)->limit(50)->get(),
            'srrvApplications' => $owned(SrrvApplication::class)->limit(50)->get(),
            'srrvRenewals' => $owned(SrrvRenewal::class)->limit(50)->get(),
            'activity' => $range->apply(ActivityLog::query())->where('user_id', $user->id)->latest()->limit(100)->get(),
            'collected' => (float) $range->apply($this->billableTickets())->where('created_by', $user->id)->sum('amount_paid'),
        ];
    }

    /**
     * @return Builder<User>
     */
    private function staffQuery(?string $search, ?string $role): Builder
    {
        $query = User::whereIn('role', array_keys(AdminAgentController::STAFF_ROLES));

        if ($role && array_key_exists($role, AdminAgentController::STAFF_ROLES)) {
            $query->where('role', $role);
        }

        if (filled($search)) {
            $like = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(fn (Builder $match) => $match
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like));
        }

        return $query;
    }

    /**
     * Files of one desk opened in the range, per staff member.
     *
     * @param  class-string  $model
     * @param  list<int>  $ids
     * @return Collection<int, int>
     */
    private function countBy(string $model, DateRange $range, array $ids): Collection
    {
        return $range->apply($this->unscoped($model))
            ->whereIn('created_by', $ids)
            ->selectRaw('created_by, COUNT(*) as aggregate')
            ->groupBy('created_by')
            ->pluck('aggregate', 'created_by');
    }

    /**
     * Tickets that were owed money: not quotations, not cancelled.
     */
    private function billableTickets(): Builder
    {
        return $this->unscoped(TicketBooking::class)
            ->where('is_quotation', false)
            ->whereNot('status', TicketBooking::STATUS_CANCELLED);
    }

    /**
     * @param  class-string  $model
     */
    private function unscoped(string $model): Builder
    {
        return $model::withoutGlobalScope(OwnFilesScope::class);
    }
}
