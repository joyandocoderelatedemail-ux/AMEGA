<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('immigration_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immigration_category_id')->constrained()->cascadeOnDelete();
            $table->text('label');
            $table->string('type')->default('requirement');
            $table->boolean('needs_review')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immigration_requirements');
    }
};
