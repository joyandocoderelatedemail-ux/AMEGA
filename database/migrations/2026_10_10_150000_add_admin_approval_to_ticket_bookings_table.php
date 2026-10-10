<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A booking is checked by an admin before the cashier takes payment:
 * pending → approved (to the cashier) or rejected (back to the agent).
 * Quotations are not submitted; bookings made before this keep working as
 * approved.
 *
 * Written to be safely re-run, and with an indexed column instead of a hard
 * foreign key onto users, which MySQL on the shared host refuses (errno 150).
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'approval_status' => fn (Blueprint $table) => $table->string('approval_status')->nullable()->after('status')->index(), // pending, approved, rejected
            'approval_requested_at' => fn (Blueprint $table) => $table->timestamp('approval_requested_at')->nullable()->after('approval_status'),
            'reviewed_by' => fn (Blueprint $table) => $table->unsignedBigInteger('reviewed_by')->nullable()->after('approval_requested_at')->index(),
            'reviewed_at' => fn (Blueprint $table) => $table->timestamp('reviewed_at')->nullable()->after('reviewed_by'),
            'review_note' => fn (Blueprint $table) => $table->text('review_note')->nullable()->after('reviewed_at'),
        ];

        foreach ($columns as $name => $add) {
            if (! Schema::hasColumn('ticket_bookings', $name)) {
                Schema::table('ticket_bookings', $add);
            }
        }

        // Bookings made before approvals existed carry on as approved (safe to re-run:
        // only bookings with no status yet, which only predate this migration).
        DB::table('ticket_bookings')->whereNull('approval_status')->where('is_quotation', false)->update(['approval_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approval_requested_at', 'reviewed_by', 'reviewed_at', 'review_note']);
        });
    }
};
