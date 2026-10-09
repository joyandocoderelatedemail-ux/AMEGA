<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fare an agent quotes per passenger type (Adult, Child, Infant, PWD/SC),
 * kept as entered with the head count and subtotal for each, so the quotation
 * can show how the fare was built. The booking's estimated fare is their sum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->json('fare_breakdown')->nullable()->after('extras_pricing');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropColumn('fare_breakdown');
        });
    }
};
