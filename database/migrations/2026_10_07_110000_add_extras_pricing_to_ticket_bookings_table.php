<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each concierge service and special request on a booking is either free or
 * priced by the agent. The per-item choice is kept as it was entered, and the
 * charged total is held on its own so it shows in the fare breakdown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->json('extras_pricing')->nullable()->after('special_requests_list');
            $table->decimal('extras_amount', 12, 2)->default(0)->after('other_charges');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropColumn(['extras_pricing', 'extras_amount']);
        });
    }
};
