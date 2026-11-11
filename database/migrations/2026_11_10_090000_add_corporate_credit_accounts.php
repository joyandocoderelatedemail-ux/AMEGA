<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A company may travel now and pay later (accounts receivable), on terms an admin sets.
        Schema::table('corporate_accounts', function (Blueprint $table) {
            $table->boolean('credit_enabled')->default(false);
            // The most the company may owe at once; null = no limit.
            $table->decimal('credit_limit', 12, 2)->nullable();
            // Net days: a charge falls due this many days after it is made.
            $table->unsignedSmallInteger('credit_terms_days')->default(30);
            // Late interest, percent per month on overdue amounts, after the grace days (applied in a later phase).
            $table->decimal('credit_interest_rate', 5, 2)->nullable();
            $table->unsignedSmallInteger('credit_grace_days')->default(0);
        });

        // A ticket booking charged to its company's account: it can be issued unpaid, and is collected later.
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->timestamp('charged_to_account_at')->nullable();
            $table->unsignedBigInteger('charged_to_account_by')->nullable();
            // The balance that went on the account, and when it falls due.
            $table->decimal('ar_amount', 12, 2)->nullable();
            $table->date('ar_due_date')->nullable();
        });

        // One payment from a company (a cheque, a transfer), applied to its unpaid tickets oldest first.
        Schema::create('corporate_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_account_id')->constrained('corporate_accounts')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->string('note')->nullable();
            $table->timestamp('received_at');
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamps();
        });

        Schema::table('ticket_payments', function (Blueprint $table) {
            // The company payment this entry is a part of, when it came through the company's account.
            $table->unsignedBigInteger('corporate_payment_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_payments', fn (Blueprint $table) => $table->dropColumn('corporate_payment_id'));
        Schema::dropIfExists('corporate_payments');
        Schema::table('ticket_bookings', fn (Blueprint $table) => $table->dropColumn(['charged_to_account_at', 'charged_to_account_by', 'ar_amount', 'ar_due_date']));
        Schema::table('corporate_accounts', fn (Blueprint $table) => $table->dropColumn(['credit_enabled', 'credit_limit', 'credit_terms_days', 'credit_interest_rate', 'credit_grace_days']));
    }
};
