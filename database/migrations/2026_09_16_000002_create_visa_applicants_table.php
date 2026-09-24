<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applicants on a visa file. A visit visa or passporting job is usually one
 * applicant; an e-Visa group package is several pax booked under one file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_application_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('applicant_number');
            $table->boolean('is_primary')->default(false);

            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();

            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('nationality')->nullable();

            // A copy for every applicant.
            $table->string('passport_number')->nullable()->index();
            $table->date('passport_expiry_date')->nullable();
            $table->string('passport_country')->nullable();

            $table->text('remarks')->nullable();
            $table->timestamps();

            // Named explicitly: the generated name exceeds MySQL's 64-char identifier limit.
            $table->unique(['visa_application_id', 'applicant_number'], 'visa_applicant_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_applicants');
    }
};
