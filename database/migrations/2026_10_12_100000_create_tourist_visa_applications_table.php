<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Bureau of Immigration's tourist visa extension, filed at the visa
 * assistance counter: the Consolidated General Application Form (CGAF) and the
 * ticks on its Checklist of Documentary Requirements. The staff link is an
 * indexed column, not a foreign key: the shared host's MySQL refuses them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tourist_visa_applications')) {
            return;
        }

        Schema::create('tourist_visa_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('created_by')->nullable()->index();

            // Method of application
            $table->string('application_method')->default('personal');
            $table->string('representative_name')->nullable();
            $table->string('bi_accreditation_number')->nullable();

            // I. Application information
            $table->string('application_mode')->default('regular');
            $table->unsignedTinyInteger('months_requested')->nullable();
            $table->text('reason')->nullable();

            // Contact information
            $table->string('mobile_number')->nullable();
            $table->string('email')->nullable();

            // II. Personal information
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('alias')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('sex', 1)->nullable();
            $table->string('birthplace')->nullable();
            $table->string('citizenship')->nullable();
            $table->string('height_cm', 20)->nullable();
            $table->string('weight_kg', 20)->nullable();
            $table->string('civil_status')->nullable();
            $table->string('address_street')->nullable();
            $table->string('barangay')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('zip_code', 20)->nullable();

            // III. Travel information
            $table->string('passport_number')->nullable();
            $table->date('passport_validity')->nullable();
            $table->date('latest_arrival_date')->nullable();
            $table->string('flight_number')->nullable();

            // IV. Latest tourist visa extension
            $table->date('latest_extension_validity')->nullable();
            $table->string('latest_extension_place')->nullable();

            // V. ACR I-Card
            $table->string('acr_number')->nullable();
            $table->date('acr_validity')->nullable();
            $table->string('ssrn')->nullable();

            // Checklist of Documentary Requirements and filing
            $table->json('checklist')->nullable();
            $table->boolean('overstayed')->default(false);
            $table->date('filed_on')->nullable();
            $table->string('evaluator_name')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tourist_visa_applications');
    }
};
