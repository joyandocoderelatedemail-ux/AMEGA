<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of the ticket wizard's Trip & Flight block, so picking a ready-made
 * package fills it in completely: flight time preference, flight destination,
 * and the package's fixed departure / return dates (if it has them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->string('preferred_flight_time')->nullable()->after('multi_city_segments'); // anytime, morning, afternoon, evening
            $table->string('flight_destination')->nullable()->after('origin_airport');
            $table->date('departure_date')->nullable()->after('flight_destination');
            $table->date('return_date')->nullable()->after('departure_date');
        });
    }

    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropColumn(['preferred_flight_time', 'flight_destination', 'departure_date', 'return_date']);
        });
    }
};
