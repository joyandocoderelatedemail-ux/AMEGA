<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scans filed on an SRRV Termination or Conversion request: one per requirement of the PRA
 * checklist (or "other"), kept on the private disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('srrv_service_request_documents', function (Blueprint $table) {
            $table->id();
            // Short key names: with the table prefix the automatic ones pass MySQL's 64-character limit.
            $table->foreignId('srrv_service_request_id')->constrained('srrv_service_requests', indexName: 'srrv_req_docs_request_fk')->cascadeOnDelete();
            $table->string('requirement'); // a requirement key, or "other"
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users', indexName: 'srrv_req_docs_uploader_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('srrv_service_request_documents');
    }
};
