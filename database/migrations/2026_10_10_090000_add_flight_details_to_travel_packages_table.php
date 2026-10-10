<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The flight terms a ready-made package is sold with. They are the same for
 * every client, so the ticket wizard fills them in when the package is picked;
 * the flight numbers, times and fare stay on each booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->string('airfare_inclusion')->nullable()->after('transportation_type'); // included, not_included, optional
            $table->foreignId('airline_id')->nullable()->after('airfare_inclusion')->constrained('airlines')->nullOnDelete();
            $table->string('origin_airport')->nullable()->after('airline_id');
            $table->string('cabin_class')->nullable()->after('origin_airport'); // economy, premium_economy, business, first_class
            $table->string('baggage_allowance')->nullable()->after('cabin_class');
        });
    }

    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('airline_id');
            $table->dropColumn(['airfare_inclusion', 'origin_airport', 'cabin_class', 'baggage_allowance']);
        });
    }
};
