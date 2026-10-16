<?php

namespace App\Support;

/**
 * A peso amount spelled out, as the service agreement prints it:
 * 90000 → "NINETY THOUSAND PESOS", 1500.50 → "ONE THOUSAND FIVE HUNDRED PESOS AND FIFTY CENTAVOS".
 * Written out by hand because the intl extension is not on every host.
 */
class AmountInWords
{
    private const ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    private const SCALES = ['', 'thousand', 'million', 'billion'];

    public static function pesos(float $amount): string
    {
        $amount = round(max(0, $amount), 2);
        $whole = (int) floor($amount);
        $centavos = (int) round(($amount - $whole) * 100);

        $words = self::spell($whole).' '.($whole === 1 ? 'peso' : 'pesos');

        if ($centavos > 0) {
            $words .= ' and '.self::spell($centavos).' '.($centavos === 1 ? 'centavo' : 'centavos');
        }

        return mb_strtoupper($words);
    }

    public static function spell(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $parts = [];

        foreach (self::SCALES as $scale) {
            $chunk = $number % 1000;
            $number = intdiv($number, 1000);

            if ($chunk > 0) {
                array_unshift($parts, trim(self::hundreds($chunk).' '.$scale));
            }

            if ($number === 0) {
                break;
            }
        }

        return implode(' ', $parts);
    }

    private static function hundreds(int $number): string
    {
        $words = [];

        if ($number >= 100) {
            $words[] = self::ONES[intdiv($number, 100)].' hundred';
            $number %= 100;
        }

        if ($number >= 20) {
            $words[] = self::TENS[intdiv($number, 10)].($number % 10 ? '-'.self::ONES[$number % 10] : '');
        } elseif ($number > 0) {
            $words[] = self::ONES[$number];
        }

        return implode(' ', $words);
    }
}
