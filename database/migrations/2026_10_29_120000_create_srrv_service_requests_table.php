<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SRRV desk's PRA service requests beyond applications and renewals: Termination of Membership
 * (PRA-SD-FORM-0008) and Conversion of the Visa Deposit into an Active Investment (PRA-SD-FORM-0010).
 * The checklist and the details that decide it are kept as JSON; the fees as figures.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A first run on production stopped after creating the table (the key was too long for MyISAM,
        // 1000 bytes). The migration was never recorded, so the table holds nothing: start again.
        Schema::withoutForeignKeyConstraints(fn () => Schema::dropIfExists('srrv_service_requests'));

        Schema::create('srrv_service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique();
            $table->string('type', 20); // termination | conversion
            $table->foreignId('srrv_application_id')->nullable()->constrained('srrv_applications')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('documents');
            $table->string('retiree_name');
            $table->string('retiree_email')->nullable();
            $table->string('retiree_phone')->nullable();
            $table->string('srrv_card_number')->nullable();
            $table->string('visa_class')->nullable();
            $table->json('details')->nullable();
            $table->json('checklist')->nullable();
            // Termination fees: the PRA service fee in US dollars, the Bureau of Immigration fee in pesos.
            $table->decimal('fee_usd', 12, 2)->default(0);
            $table->decimal('fee_php', 12, 2)->default(0);
            $table->decimal('paid_usd', 12, 2)->default(0);
            $table->decimal('paid_php', 12, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('approval_status')->nullable();
            $table->timestamp('approval_requested_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('srrv_service_requests');
    }
};
