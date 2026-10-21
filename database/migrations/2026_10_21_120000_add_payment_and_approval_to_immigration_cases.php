<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The cases that carry a payment option and go to an admin for approval. */
    private const CASE_TABLES = ['immigration_overstays', 'immigration_restampings', 'immigration_petitions'];

    public function up(): void
    {
        foreach (self::CASE_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                // What the client pays: full, a downpayment, terms or nothing yet, and how much has come in.
                $table->string('payment_type', 20)->nullable();
                $table->decimal('amount_paid', 10, 2)->nullable();
                $table->unsignedTinyInteger('terms_count')->nullable();
                $this->approval($table);
            });
        }

        // A Visa Extension has no case: each new ledger row is what goes to the admin.
        Schema::table('immigration_client_extensions', function (Blueprint $table) {
            $table->string('payment_type', 20)->nullable();
            $table->decimal('amount_received', 10, 2)->nullable();
            // When the row moved the visa on, so approving it later never moves the visa twice.
            $table->timestamp('visa_extended_at')->nullable();
            $table->unsignedTinyInteger('terms_count')->nullable();
            $this->approval($table);
        });
    }

    public function down(): void
    {
        foreach ([...self::CASE_TABLES, 'immigration_client_extensions'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropConstrainedForeignId('reviewed_by');
                $table->dropConstrainedForeignId('acknowledged_by');
                $table->dropColumn(['approval_status', 'approval_requested_at', 'reviewed_at', 'review_note', 'acknowledged_at', 'payment_type', 'terms_count']);
                $table->dropColumn($name === 'immigration_client_extensions' ? ['amount_received', 'visa_extended_at'] : ['amount_paid']);
            });
        }
    }

    /**
     * Cases saved before approvals have no status and are not held up. Every later save by
     * an agent waits for an admin: approved, or returned with a reason.
     */
    private function approval(Blueprint $table): void
    {
        $table->string('approval_status', 20)->nullable()->index();
        $table->timestamp('approval_requested_at')->nullable();
        $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('reviewed_at')->nullable();
        $table->text('review_note')->nullable();
        $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('acknowledged_at')->nullable();
    }
};
