<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every change to a booked flight (a delay, a new date, a cancellation by the
 * airline, a change the client asked for), with the schedule before and after,
 * who recorded it, and whether the client has been told.
 *
 * The links are indexed columns rather than foreign keys: the shared host has
 * refused foreign keys before (see the corporate accounts migration), and a
 * missing constraint here costs nothing, since tickets are never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_flight_changes')) {
            return;
        }

        Schema::create('ticket_flight_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_booking_id')->index();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->string('type', 30);
            $table->json('schedule_before')->nullable();
            $table->json('schedule_after')->nullable();
            $table->string('reason', 500)->nullable();
            $table->decimal('change_fee', 12, 2)->default(0);
            // Whether the fee was added to the booking's total (not possible once the ticket is issued).
            $table->boolean('fee_added')->default(false);
            $table->timestamp('client_notified_at')->nullable();
            $table->string('notified_via', 60)->nullable();
            $table->timestamps();

            $table->index(['client_notified_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_flight_changes');
    }
};
