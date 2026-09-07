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
        Schema::create('ticket_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_booking_id')->constrained('ticket_bookings')->cascadeOnDelete();
            $table->unsignedSmallInteger('passenger_number')->default(1);
            $table->string('passenger_type')->default('adult'); // adult, child, infant
            $table->string('nationality_type')->default('filipino'); // filipino, foreign_national
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('passport_number')->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->string('passport_country')->nullable();
            $table->string('government_id_type')->nullable();
            $table->string('government_id_number')->nullable();
            $table->string('visa_type')->nullable(); // e_visa, regular_visa, none
            $table->unsignedSmallInteger('stay_duration_months')->nullable();
            $table->boolean('requires_exit_clearance')->default(false);
            $table->boolean('travel_tax_included')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_passengers');
    }
};
