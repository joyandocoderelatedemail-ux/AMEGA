<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            // Payment terms set by the cashier: how many payments, the days between them, and the day they start.
            $table->unsignedTinyInteger('terms_payments')->nullable();
            $table->unsignedSmallInteger('terms_days')->nullable();
            $table->date('terms_start')->nullable();
            $table->foreignId('terms_set_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('ticket_payments', function (Blueprint $table) {
            // 1st, 2nd, 3rd... payment on the booking; the one that clears the balance is the final payment.
            $table->unsignedSmallInteger('payment_number')->nullable();
            $table->boolean('is_final')->default(false);
            // The extra charge on a card payment, on top of the amount credited to the booking.
            $table->decimal('card_surcharge', 12, 2)->default(0);
        });

        $this->numberExistingPayments();
    }

    public function down(): void
    {
        Schema::table('ticket_payments', function (Blueprint $table) {
            $table->dropColumn(['payment_number', 'is_final', 'card_surcharge']);
        });

        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('terms_set_by');
            $table->dropColumn(['terms_payments', 'terms_days', 'terms_start']);
        });
    }

    /** Payments recorded before this existed get their number, and the one that cleared the balance is final. */
    private function numberExistingPayments(): void
    {
        $totals = DB::table('ticket_bookings')->pluck('total_amount', 'id');

        DB::table('ticket_payments')
            ->where('type', 'payment')
            ->orderBy('ticket_booking_id')
            ->orderBy('id')
            ->get(['id', 'ticket_booking_id', 'amount'])
            ->groupBy('ticket_booking_id')
            ->each(function ($payments, $bookingId) use ($totals): void {
                $total = (float) ($totals[$bookingId] ?? 0);
                $running = 0.0;
                $finalMarked = false;

                foreach ($payments->values() as $index => $payment) {
                    $running += (float) $payment->amount;
                    $final = ! $finalMarked && $total > 0 && $running + 0.004 >= $total;
                    $finalMarked = $finalMarked || $final;

                    DB::table('ticket_payments')->where('id', $payment->id)->update([
                        'payment_number' => $index + 1,
                        'is_final' => $final,
                    ]);
                }
            });
    }
};
