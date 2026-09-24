<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The visa assistance counter file. One row per client engagement, covering any
 * of the three services the counter runs: visit visa, e-Visa, and passporting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('service_type')->index();   // visit_visa | e_visa | passporting
            $table->string('status')->default('pending')->index();
            $table->string('applicant_type')->default('individual'); // individual | group (e-Visa)

            // Counter contact
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();

            // Visit visa: the destination sets everything after, and the purpose
            // decides which requirement list applies.
            $table->string('destination_country')->nullable();
            $table->string('purpose')->nullable();      // tourist | business | family
            $table->boolean('requires_appearance')->default(true); // false for Australia & New Zealand
            $table->string('processing_speed')->default('regular'); // regular | rush

            // Passporting: two different jobs from here on.
            $table->string('passport_type')->nullable();  // foreign | local
            $table->string('embassy_country')->nullable(); // USA | Canada | Australia | United Kingdom
            $table->timestamp('appointment_at')->nullable(); // booked DFA slot; the timeline hangs on this

            // Milestones that the flowchart treats as signed-off steps
            $table->timestamp('agreement_signed_at')->nullable();
            $table->timestamp('acknowledgement_signed_at')->nullable();

            // Visit visa extras: travel cover and the e-Travel amount keyed in
            $table->boolean('insurance_included')->default(false);
            $table->string('etravel_reference')->nullable();

            // Money
            $table->decimal('service_fee', 10, 2)->default(0);
            $table->decimal('rush_fee', 10, 2)->default(0);
            $table->decimal('insurance_fee', 10, 2)->default(0);
            $table->decimal('etravel_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->string('payment_type')->default('full'); // deposit | full

            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_applications');
    }
};
