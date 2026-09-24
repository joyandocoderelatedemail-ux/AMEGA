<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counter fee schedule. Editable from the admin panel so the counter can change
 * a price without a deploy, mirroring the immigration pricing tiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_pricing_tiers', function (Blueprint $table) {
            $table->id();

            // visit_visa | e_visa | passporting
            $table->string('service_type')->index();
            $table->string('label');
            $table->string('condition_notes')->nullable();

            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->string('processing_time')->nullable(); // "1-3 weeks", "1-5 days"

            $table->boolean('needs_review')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_pricing_tiers');
    }
};
