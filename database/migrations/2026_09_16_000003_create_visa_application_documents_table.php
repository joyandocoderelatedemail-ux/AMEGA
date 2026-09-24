<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document checklist for a visa file. The counter needs to see, per applicant,
 * what has been handed over and what is still outstanding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visa_applicant_id')->nullable()->constrained()->cascadeOnDelete();

            // passport_scan | passport_photo | valid_id | birth_certificate
            // application_form | supporting_documents | photo | visa_scan | other
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
        Schema::dropIfExists('visa_application_documents');
    }
};
