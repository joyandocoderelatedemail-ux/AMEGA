<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document checklist for an SRRV file: the standard PRA checklist plus the
 * class-specific proof that unlocks the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('srrv_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('srrv_application_id')->constrained()->cascadeOnDelete();

            // pra_checklist | police_clearance | pension_proof
            // military_service_proof | srrv_card | valid_id | form | other
            $table->string('document_type')->index();

            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('status')->default('uploaded'); // uploaded | pending | missing

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('srrv_application_documents');
    }
};
