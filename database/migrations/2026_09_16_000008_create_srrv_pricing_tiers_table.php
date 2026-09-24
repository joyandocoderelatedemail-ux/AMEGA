<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRRV fee schedule, editable from the admin panel. The renewal fee turns on
 * the visa class alone: classic USD 360 a year, courtesy USD 10 a year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('srrv_pricing_tiers', function (Blueprint $table) {
            $table->id();

            // renewal_application | renewal | restamping
            $table->string('service_type')->index();
            $table->string('visa_class')->default('any')->index(); // classic | courtesy | any
            $table->string('label');
            $table->string('condition_notes')->nullable();

            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('processing_time')->nullable();

            $table->boolean('needs_review')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('srrv_pricing_tiers');
    }
};
