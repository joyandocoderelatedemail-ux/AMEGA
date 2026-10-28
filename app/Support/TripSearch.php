<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The trip the visitor described in the home page's "Search Your Trip" box: departure and return
 * dates and number of travellers. It rides along in the package links (?depart=&return=&pax=) so
 * the package booking form starts filled in. Anything invalid is dropped, never trusted.
 */
class TripSearch
{
    /** @return array{depart: ?string, return: ?string, pax: ?int} */
    public static function from(Request $request): array
    {
        $depart = self::date($request->query('depart'));
        $return = self::date($request->query('return'));
        if ($depart && $return && $return < $depart) {
            $return = null;
        }

        $pax = filter_var($request->query('pax'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);

        return ['depart' => $depart, 'return' => $return, 'pax' => $pax === false ? null : $pax];
    }

    /** The query to carry into package links: only what was given. */
    public static function query(Request $request): array
    {
        return array_filter(self::from($request), fn ($value) => $value !== null);
    }

    /** "2 travellers · Nov 12 – Nov 18, 2026", or null when nothing was searched. */
    public static function summary(Request $request): ?string
    {
        $trip = self::from($request);
        $dates = match (true) {
            $trip['depart'] && $trip['return'] => Carbon::parse($trip['depart'])->format('M j').' – '.Carbon::parse($trip['return'])->format('M j, Y'),
            (bool) $trip['depart'] => 'from '.Carbon::parse($trip['depart'])->format('M j, Y'),
            default => null,
        };
        $people = $trip['pax'] ? $trip['pax'].' '.($trip['pax'] === 1 ? 'traveller' : 'travellers') : null;

        return collect([$people, $dates])->filter()->implode(' · ') ?: null;
    }

    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        // A real calendar date, today or later.
        if (! $date || $date->format('Y-m-d') !== $value || $date->lt(today())) {
            return null;
        }

        return $value;
    }
}
