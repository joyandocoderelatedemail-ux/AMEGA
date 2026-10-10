<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The flight terms a ready-made package is sold with. They are the same for
 * every client, so the ticket wizard fills them in when the package is picked;
 * the flight numbers, times and fare stay on each booking.
 *
 * Written to be safely re-run: on the shared host MySQL refused a hard foreign
 * key (errno 150) after the columns were already added, so airline_id is an
 * indexed column and each column is skipped when it already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'airfare_inclusion' => fn (Blueprint $table) => $table->string('airfare_inclusion')->nullable()->after('transportation_type'), // included, not_included, optional
            'airline_id' => fn (Blueprint $table) => $table->unsignedBigInteger('airline_id')->nullable()->after('airfare_inclusion')->index(),
            'origin_airport' => fn (Blueprint $table) => $table->string('origin_airport')->nullable()->after('airline_id'),
            'cabin_class' => fn (Blueprint $table) => $table->string('cabin_class')->nullable()->after('origin_airport'), // economy, premium_economy, business, first_class
            'baggage_allowance' => fn (Blueprint $table) => $table->string('baggage_allowance')->nullable()->after('cabin_class'),
        ];

        foreach ($columns as $name => $add) {
            if (! Schema::hasColumn('travel_packages', $name)) {
                Schema::table('travel_packages', $add);
            }
        }
    }

    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropColumn(['airfare_inclusion', 'airline_id', 'origin_airport', 'cabin_class', 'baggage_allowance']);
        });
    }
};
