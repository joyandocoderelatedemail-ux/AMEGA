<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supporting documents kept with a ticket booking as a whole, so the office has a copy: the actual
 * e-ticket from the airline, boarding passes, hotel and tour vouchers, insurance policies, receipts.
 * Passenger documents (passport, ID, visa) stay on ticket_passenger_documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_booking_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_booking_id')->constrained('ticket_bookings')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('note')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_booking_documents');
    }
};
