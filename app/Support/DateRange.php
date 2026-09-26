<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The reporting period picked on the dashboard and staff pages: a preset
 * (today, last 7 days, this month...) or custom from/to dates, read from the
 * query string. "All time" has no bounds and filters nothing.
 */
class DateRange
{
    /**
     * @var array<string, string>
     */
    public const PRESETS = [
        'all' => 'All time',
        'today' => 'Today',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
    ];

    public function __construct(
        public readonly string $preset,
        public readonly ?Carbon $from,
        public readonly ?Carbon $to,
    ) {}

    public static function fromRequest(Request $request, string $default = 'all'): self
    {
        $from = self::parseDate($request->query('from'));
        $to = self::parseDate($request->query('to'));

        if ($from || $to) {
            // A lone date is a one-day range; reversed dates are swapped.
            $from ??= $to->copy();
            $to ??= $from->copy();

            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            return new self('custom', $from->startOfDay(), $to->endOfDay());
        }

        $preset = (string) $request->query('range', $default);

        return self::preset(array_key_exists($preset, self::PRESETS) ? $preset : $default);
    }

    public static function preset(string $preset): self
    {
        $now = Carbon::now();

        return match ($preset) {
            'today' => new self($preset, $now->copy()->startOfDay(), $now->copy()->endOfDay()),
            '7d' => new self($preset, $now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()),
            '30d' => new self($preset, $now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()),
            'this_month' => new self($preset, $now->copy()->startOfMonth(), $now->copy()->endOfMonth()),
            'last_month' => new self($preset, $now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()),
            'this_year' => new self($preset, $now->copy()->startOfYear(), $now->copy()->endOfYear()),
            default => new self('all', null, null),
        };
    }

    public function isAllTime(): bool
    {
        return $this->from === null;
    }

    /**
     * Limit a query to rows whose date column falls in the range.
     */
    public function apply(Builder $query, string $column = 'created_at'): Builder
    {
        if ($this->isAllTime()) {
            return $query;
        }

        return $query->whereBetween($query->qualifyColumn($column), [$this->from, $this->to]);
    }

    /**
     * How the range reads in a heading: "All time", "Sep 1 – Sep 30, 2026".
     */
    public function label(): string
    {
        if ($this->isAllTime()) {
            return self::PRESETS['all'];
        }

        if ($this->from->isSameDay($this->to)) {
            return $this->from->format('M j, Y');
        }

        return $this->from->format($this->from->isSameYear($this->to) ? 'M j' : 'M j, Y').' – '.$this->to->format('M j, Y');
    }

    /**
     * The query-string values that reproduce this range on another page.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return match ($this->preset) {
            'custom' => ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()],
            'all' => [],
            default => ['range' => $this->preset],
        };
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
