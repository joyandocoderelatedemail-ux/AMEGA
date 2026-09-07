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
        Schema::create('ticket_passenger_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_passenger_id')->constrained('ticket_passengers')->cascadeOnDelete();
            $table->string('document_type'); // passport_scan, government_id, birth_certificate, school_id, visa_scan, exit_clearance, other
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type')->nullable();
            $table->string('status')->default('uploaded'); // uploaded, verified, rejected
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_passenger_documents');
    }
};
