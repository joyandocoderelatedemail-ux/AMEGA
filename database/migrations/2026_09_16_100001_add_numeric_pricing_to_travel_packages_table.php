<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * travel_packages.price was a free-text display string ("₱14,999", "$2,399").
 * Package prices therefore could not be summed, compared or reported on, and
 * two currencies shared one column with no currency recorded anywhere.
 *
 * This adds real numeric columns and backfills them from the existing strings.
 * The display string is deliberately kept: it is the label rendered across the
 * public site, and the model keeps the two in step from here on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->decimal('price_amount', 12, 2)->nullable()->after('price');
            $table->char('price_currency', 3)->default('PHP')->after('price_amount');
        });

        $this->backfillFromDisplayStrings();
    }

    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropColumn(['price_amount', 'price_currency']);
        });
    }

    /**
     * A price that cannot be parsed keeps a null amount rather than a wrong one,
     * so nothing is silently invented. Its display string is left untouched.
     */
    private function backfillFromDisplayStrings(): void
    {
        DB::table('travel_packages')
            ->select('id', 'price')
            ->orderBy('id')
            ->chunk(200, function ($packages) {
                foreach ($packages as $package) {
                    [$amount, $currency] = $this->parseDisplayPrice((string) $package->price);

                    if ($amount === null) {
                        continue;
                    }

                    DB::table('travel_packages')
                        ->where('id', $package->id)
                        ->update([
                            'price_amount' => $amount,
                            'price_currency' => $currency,
                        ]);
                }
            });
    }

    /**
     * Pull a numeric amount and a currency code out of a display string.
     *
     * @return array{0: float|null, 1: string}
     */
    private function parseDisplayPrice(string $value): array
    {
        $upper = strtoupper($value);

        if (str_contains($value, '$') || str_contains($upper, 'USD')) {
            $currency = 'USD';
        } elseif (str_contains($value, '₱') || str_contains($upper, 'PHP')) {
            $currency = 'PHP';
        } else {
            $currency = 'PHP';
        }

        $digits = preg_replace('/[^0-9.]/', '', $value);

        if ($digits === null || $digits === '' || ! is_numeric($digits)) {
            return [null, $currency];
        }

        return [(float) $digits, $currency];
    }
};
