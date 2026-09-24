<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * bookings.total_amount was a string holding a copy of the package's display
 * price, so a booking's total was the literal text "₱14,999" and could not be
 * summed or reported on.
 *
 * This replaces it with a real numeric amount plus a currency. The name is
 * amount_due rather than total_amount because it is what the customer owes —
 * distinct from a ticket booking's total_amount, which already exists as a
 * decimal on that table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('amount_due', 12, 2)->nullable()->after('special_requests');
            $table->char('currency', 3)->default('PHP')->after('amount_due');
        });

        $this->backfillFromLegacyStrings();

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('total_amount');
        });
    }

    /**
     * Reversible: the legacy display string is derived from the numeric amount,
     * so nothing is lost by dropping it.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('total_amount')->nullable()->after('special_requests');
        });

        DB::table('bookings')
            ->select('id', 'amount_due', 'currency')
            ->orderBy('id')
            ->chunk(200, function ($bookings) {
                foreach ($bookings as $booking) {
                    if ($booking->amount_due === null) {
                        continue;
                    }

                    DB::table('bookings')
                        ->where('id', $booking->id)
                        ->update([
                            'total_amount' => $this->render(
                                (float) $booking->amount_due,
                                (string) ($booking->currency ?: 'PHP')
                            ),
                        ]);
                }
            });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['amount_due', 'currency']);
        });
    }

    /**
     * Existing totals were a copy of the package's display price, so they are
     * carried across at face value. They are NOT retroactively multiplied by
     * the passenger count — that would invent history that was never billed.
     */
    private function backfillFromLegacyStrings(): void
    {
        DB::table('bookings')
            ->select('id', 'total_amount')
            ->orderBy('id')
            ->chunk(200, function ($bookings) {
                foreach ($bookings as $booking) {
                    [$amount, $currency] = $this->parseDisplayPrice((string) $booking->total_amount);

                    if ($amount === null) {
                        continue;
                    }

                    DB::table('bookings')
                        ->where('id', $booking->id)
                        ->update([
                            'amount_due' => $amount,
                            'currency' => $currency,
                        ]);
                }
            });
    }

    /**
     * @return array{0: float|null, 1: string}
     */
    private function parseDisplayPrice(string $value): array
    {
        $upper = strtoupper($value);

        $currency = (str_contains($value, '$') || str_contains($upper, 'USD')) ? 'USD' : 'PHP';

        $digits = preg_replace('/[^0-9.]/', '', $value);

        if ($digits === null || $digits === '' || ! is_numeric($digits)) {
            return [null, $currency];
        }

        return [(float) $digits, $currency];
    }

    /**
     * Render a numeric amount back into the legacy display format.
     */
    private function render(float $amount, string $currency): string
    {
        $symbol = $currency === 'USD' ? '$' : '₱';

        return $symbol.number_format($amount, 0);
    }
};
