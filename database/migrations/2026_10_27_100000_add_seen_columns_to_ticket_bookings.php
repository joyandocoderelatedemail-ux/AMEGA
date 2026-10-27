<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When each person in the ticket's path first opened it after it reached them: an admin (waiting for
 * approval), the cashier (approved, waiting for payment) and the agent (returned, or paid in full and
 * ready to issue). Until then it counts on their notification bell; the next handoff clears it again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->timestamp('approval_seen_at')->nullable()->after('review_note');
            $table->timestamp('cashier_seen_at')->nullable()->after('approval_seen_at');
            $table->timestamp('agent_seen_at')->nullable()->after('cashier_seen_at');
        });

        // Bookings from before the bells start out seen, so nobody is greeted by a backlog.
        DB::table('ticket_bookings')->update([
            'approval_seen_at' => now(),
            'cashier_seen_at' => now(),
            'agent_seen_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropColumn(['approval_seen_at', 'cashier_seen_at', 'agent_seen_at']);
        });
    }
};
