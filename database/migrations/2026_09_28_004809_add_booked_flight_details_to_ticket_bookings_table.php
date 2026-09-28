<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The flight actually found for the client, as opposed to `preferred_airline`,
 * which only records what the client asked for. Staff copy these from the
 * airline's site so the voucher and reminders no longer need retyping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->foreignId('airline_id')->nullable()->after('preferred_airline')
                ->constrained('airlines')->nullOnDelete();
            $table->string('airline_pnr', 20)->nullable()->after('airline_id');
            $table->string('flight_number', 20)->nullable()->after('airline_pnr');
            $table->time('departure_time')->nullable()->after('flight_number');
            $table->time('arrival_time')->nullable()->after('departure_time');
            $table->string('return_flight_number', 20)->nullable()->after('arrival_time');
            $table->time('return_departure_time')->nullable()->after('return_flight_number');
            $table->time('return_arrival_time')->nullable()->after('return_departure_time');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('airline_id');
            $table->dropColumn([
                'airline_pnr',
                'flight_number',
                'departure_time',
                'arrival_time',
                'return_flight_number',
                'return_departure_time',
                'return_arrival_time',
            ]);
        });
    }
};
