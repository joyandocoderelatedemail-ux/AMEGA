<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->text('inclusions')->nullable()->after('description');
            $table->text('exclusions')->nullable()->after('inclusions');
            $table->text('itinerary')->nullable()->after('exclusions');
            $table->string('available_dates')->nullable()->after('itinerary');
            $table->enum('status', ['active', 'draft', 'sold_out'])->default('active')->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropColumn(['inclusions', 'exclusions', 'itinerary', 'available_dates', 'status']);
        });
    }
};
