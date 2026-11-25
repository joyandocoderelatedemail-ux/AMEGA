<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('srrv_renewal_documents')) {
            return;
        }

        Schema::create('srrv_renewal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('srrv_renewal_id')->constrained('srrv_renewals')->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('status', 20)->default('uploaded');
            $table->string('remarks', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('srrv_renewal_documents');
    }
};
