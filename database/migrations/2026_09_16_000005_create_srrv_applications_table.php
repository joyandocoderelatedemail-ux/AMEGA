<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRRV retiree file, worked through the Philippine Retirement Authority.
 * The counter runs three jobs off this: the renewal application, the annual
 * renewal (see srrv_renewals), and re-stamping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('srrv_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('service_type')->index(); // renewal_application | restamping
            $table->string('status')->default('pending')->index();

            // Classic or courtesy is decided by who the retiree is, not by
            // which one they want: courtesy covers government and military, 50+.
            $table->string('visa_class')->index(); // classic | courtesy

            $table->string('retiree_name');
            $table->string('retiree_email')->nullable();
            $table->string('retiree_phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('srrv_card_number')->nullable()->index();

            // Classic: the deposit the visa rests on. Courtesy: the same figure,
            // with or without a pension.
            $table->decimal('investment_amount', 12, 2)->default(0);

            // Classic-only paperwork, on top of the standard PRA checklist.
            $table->boolean('police_clearance_received')->default(false);
            $table->boolean('pension_proof_received')->default(false);

            // Courtesy-only: the document that unlocks it.
            $table->boolean('military_service_proof_received')->default(false);

            // "Four copies of everything."
            $table->unsignedTinyInteger('copies_submitted')->default(4);

            // Pipeline milestones, in flowchart order.
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('lodged_at')->nullable();          // pending with the PRA
            $table->timestamp('payment_in_full_at')->nullable(); // no deposit at this stage
            $table->timestamp('oath_at')->nullable();            // retiree appears in person
            $table->timestamp('released_at')->nullable();        // the visa is issued

            $table->decimal('service_fee', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');

            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('srrv_applications');
    }
};
