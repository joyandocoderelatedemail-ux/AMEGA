<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who confirmed, before issuing, that the booking's information is correct
 * and accepted accountability for it, and when.
 *
 * Written to be safely re-run, and with an indexed column instead of a hard
 * foreign key onto users, which MySQL on the shared host refuses (errno 150).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ticket_bookings', 'agent_acknowledged_by')) {
            Schema::table('ticket_bookings', function (Blueprint $table) {
                $table->unsignedBigInteger('agent_acknowledged_by')->nullable()->after('created_by')->index();
            });
        }

        if (! Schema::hasColumn('ticket_bookings', 'agent_acknowledged_at')) {
            Schema::table('ticket_bookings', function (Blueprint $table) {
                $table->timestamp('agent_acknowledged_at')->nullable()->after('agent_acknowledged_by');
            });
        }
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropColumn(['agent_acknowledged_by', 'agent_acknowledged_at']);
        });
    }
};
