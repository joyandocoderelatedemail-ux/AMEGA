<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "Travel Information" grid on the Client Information Sheet: one row per
 * document type (ACR I-Card, CRTV, Annual Report) against reference number,
 * date paid, SSRN number, and validity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('immigration_client_documents', function (Blueprint $table) {
            $table->id();
            // Named explicitly to stay inside MySQL's 64-character identifier limit
            // once the table prefix is applied.
            $table->foreignId('immigration_client_id')
                ->constrained(indexName: 'icd_client_id_foreign')
                ->cascadeOnDelete();
            $table->string('document_type');
            $table->string('reference_number')->nullable();
            $table->date('date_paid')->nullable();
            $table->string('ssrn_number')->nullable();
            $table->string('validity')->nullable();
            $table->timestamps();

            $table->unique(['immigration_client_id', 'document_type'], 'client_document_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immigration_client_documents');
    }
};
