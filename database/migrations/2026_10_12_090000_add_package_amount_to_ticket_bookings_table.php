<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ready-made package's price on a booking (its price per person times the
 * travellers, or what the agent set), part of the total like the fare.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ticket_bookings', 'package_amount')) {
            Schema::table('ticket_bookings', function (Blueprint $table) {
                $table->decimal('package_amount', 12, 2)->default(0)->after('estimated_fare');
            });
        }
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropColumn('package_amount');
        });
    }
};
