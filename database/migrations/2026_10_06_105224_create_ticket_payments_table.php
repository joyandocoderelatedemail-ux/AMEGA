<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per payment received (or refund given) against a ticket booking.
 *
 * `ticket_bookings.amount_paid` stays as the running total the rest of the
 * application reads; it is now moved only by these entries. Bookings that
 * already show money received get one opening entry for it, so their history
 * adds up to the same total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_booking_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('payment');
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('received_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ticket_booking_id', 'received_at']);
        });

        DB::table('ticket_bookings')->where('amount_paid', '>', 0)->orderBy('id')->each(function ($booking) {
            DB::table('ticket_payments')->insert([
                'ticket_booking_id' => $booking->id,
                'type' => 'payment',
                'amount' => $booking->amount_paid,
                'method' => 'other',
                'reference' => null,
                'note' => 'Recorded before payment history was kept',
                'received_at' => $booking->paid_at ?? $booking->updated_at ?? $booking->created_at ?? now(),
                'received_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_payments');
    }
};
