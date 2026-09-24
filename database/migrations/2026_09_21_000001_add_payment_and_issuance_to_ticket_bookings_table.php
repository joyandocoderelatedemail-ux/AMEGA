<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separates "money received" from "ticket issued".
 *
 * `status` already tracked the booking lifecycle but never moved off its
 * 'pending' default, because nothing in the codebase could advance it. Payment
 * lives in its own column so a fully paid booking is still explicitly not an
 * issued ticket — issuance stays a deliberate staff action, gated on full
 * payment and a recorded data-privacy consent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')->after('status');
            $table->decimal('amount_paid', 12, 2)->default(0)->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('amount_paid');

            $table->timestamp('issued_at')->nullable()->after('paid_at');
            $table->foreignId('issued_by')->nullable()->after('issued_at')
                ->constrained('users')->nullOnDelete();

            $table->timestamp('consent_accepted_at')->nullable()->after('issued_by');
            $table->foreignId('consent_accepted_by')->nullable()->after('consent_accepted_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issued_by');
            $table->dropConstrainedForeignId('consent_accepted_by');
            $table->dropColumn([
                'payment_status',
                'amount_paid',
                'paid_at',
                'issued_at',
                'consent_accepted_at',
            ]);
        });
    }
};
