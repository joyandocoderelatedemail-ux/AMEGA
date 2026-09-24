<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The annual SRRV renewal. Due once a year for as long as the retiree stays.
 * The fee turns on the visa class and nothing else: classic USD 360, courtesy
 * USD 10, with two years at most payable ahead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('srrv_renewals', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('srrv_application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('pending')->index();
            $table->string('visa_class')->index(); // classic | courtesy

            $table->string('retiree_name');
            $table->string('retiree_email')->nullable();
            $table->string('srrv_card_number')->nullable()->index();

            $table->unsignedTinyInteger('years_paid')->default(1); // ceiling of two
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');

            // The existing card plus a photocopy is the starting document.
            $table->boolean('id_and_photocopy_received')->default(false);
            $table->boolean('form_filled_online')->default(false);

            $table->timestamp('signature_thumbmark_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('processed_at')->nullable();          // no appearance, no oath
            $table->timestamp('ready_at_pra_at')->nullable();       // held at the PRA office
            $table->timestamp('client_notified_at')->nullable();    // the counter makes the call
            $table->timestamp('collected_at')->nullable();          // in person, never posted
            $table->string('collected_by_name')->nullable();

            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('srrv_renewals');
    }
};
