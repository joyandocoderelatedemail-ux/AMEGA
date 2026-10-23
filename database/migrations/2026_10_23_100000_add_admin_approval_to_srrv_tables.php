<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The SRRV files an agent saves and an admin then checks. */
    private const TABLES = ['srrv_applications', 'srrv_renewals'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                // Files saved before approvals have no status and are not held up.
                $table->string('approval_status', 20)->nullable()->index();
                $table->timestamp('approval_requested_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('acknowledged_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('reviewed_by');
                $table->dropConstrainedForeignId('acknowledged_by');
                $table->dropColumn(['approval_status', 'approval_requested_at', 'reviewed_at', 'review_note', 'acknowledged_at']);
            });
        }
    }
};
