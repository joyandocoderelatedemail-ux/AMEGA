<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The character boxes BI forms are filled in on: one capital letter per box, and dates
 * as DD / MMM / YYYY groups. The page styles `.cells` and `.cell` (see the 13(A) prints).
 */
class BoxedForm
{
    /**
     * A row of boxes holding the value one character per box. With $blank, every box is
     * left empty. A value longer than the row is cut at the last box.
     */
    public static function boxes(mixed $value, int $count, bool $blank = false, string $class = ''): HtmlString
    {
        $chars = $blank ? [] : mb_str_split(self::clean($value));
        $html = '<span class="cells '.e($class).'">';

        for ($i = 0; $i < $count; $i++) {
            $html .= '<span class="cell">'.e($chars[$i] ?? '').'</span>';
        }

        return new HtmlString($html.'</span>');
    }

    /** A date in the BI's DD MMM YYYY boxes. */
    public static function date(?Carbon $date, bool $blank = false): HtmlString
    {
        $show = $blank ? null : $date;

        return new HtmlString('<span class="date-cells">'
            .self::boxes($show?->format('d'), 2, $blank)
            .self::boxes($show ? strtoupper($show->format('M')) : null, 3, $blank)
            .self::boxes($show?->format('Y'), 4, $blank)
            .'</span>');
    }

    /** A tick box, ticked when $on. */
    public static function tick(bool $on, bool $blank = false): HtmlString
    {
        return new HtmlString('<span class="tick">'.(! $blank && $on ? '✓' : '').'</span>');
    }

    /** Capitals, English letters only, as the BI asks ("Muñoz" is entered as "MUNOZ"). */
    private static function clean(mixed $value): string
    {
        return mb_strtoupper(Str::ascii(trim((string) $value)));
    }
}
