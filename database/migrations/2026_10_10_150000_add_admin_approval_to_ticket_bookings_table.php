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
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->string('approval_status')->nullable()->after('status'); // pending, approved, rejected
            $table->timestamp('approval_requested_at')->nullable()->after('approval_status');
            $table->foreignId('reviewed_by')->nullable()->after('approval_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
            $table->index('approval_status');
        });

        DB::table('ticket_bookings')->where('is_quotation', false)->update(['approval_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropIndex(['approval_status']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['approval_status', 'approval_requested_at', 'reviewed_at', 'review_note']);
        });
    }
};
