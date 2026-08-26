<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('immigration_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immigration_category_id')->constrained()->cascadeOnDelete();
            $table->string('extension_label')->nullable();
            $table->string('duration_label')->nullable();
            $table->string('process_type')->default('regular');
            $table->string('payment_method')->default('cash');
            $table->string('condition_notes')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('processing_time')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immigration_pricing_tiers');
    }
};
