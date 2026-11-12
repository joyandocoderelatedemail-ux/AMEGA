<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The airline's ticketing time limit: a held reservation (PNR) must be paid and ticketed by then,
 * or the airline cancels it. deadline_reminded_at keeps the reminder email to one per deadline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dateTime('ticketing_deadline')->nullable()->after('airline_pnr')->index();
            $table->dateTime('deadline_reminded_at')->nullable()->after('ticketing_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropIndex(['ticketing_deadline']);
            $table->dropColumn(['ticketing_deadline', 'deadline_reminded_at']);
        });
    }
};
