<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff remarks on a ready-made package, and the airfare share of its price
 * per person (price_amount stays the total per person shown to clients).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('special_requests');
            $table->decimal('airfare_amount', 12, 2)->nullable()->after('price_amount');
        });
    }

    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropColumn(['remarks', 'airfare_amount']);
        });
    }
};
