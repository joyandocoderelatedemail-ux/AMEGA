<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The trip shape a ready-made package is sold as. Multi-city packages keep
 * their route legs (from / to only: the dates belong to each booking).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->string('trip_type')->nullable()->after('airfare_inclusion'); // round_trip, one_way, multi_city
            $table->json('multi_city_segments')->nullable()->after('trip_type');
        });
    }

    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropColumn(['trip_type', 'multi_city_segments']);
        });
    }
};
