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
        Schema::create('custom_package_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name');
            $table->string('client_email');
            $table->string('client_phone')->nullable();
            $table->foreignId('destination_id')->nullable()->constrained('destinations')->nullOnDelete();
            $table->string('destination_name')->nullable();
            $table->string('travel_type')->default('domestic'); // domestic, international
            $table->date('check_in_date')->nullable();
            $table->date('check_out_date')->nullable();
            $table->string('duration')->nullable();
            $table->integer('number_of_pax')->default(1);
            $table->integer('adults_count')->default(1);
            $table->integer('children_count')->default(0);
            $table->integer('infants_count')->default(0);
            $table->string('hotel_name')->nullable();
            $table->string('preferred_hotel')->nullable();
            $table->boolean('has_breakfast')->default(false);
            $table->string('bed_config')->nullable(); // single, twin, double, queen, king, family
            $table->string('smoking_preference')->default('non_smoking'); // non_smoking, smoking
            $table->boolean('pet_friendly')->default(false);
            $table->boolean('has_transportation')->default(false);
            $table->string('transportation_type')->nullable(); // airport_transfer, private_van, tour_bus, car_rental
            $table->text('special_requests')->nullable();
            $table->decimal('estimated_budget', 12, 2)->nullable();
            $table->string('currency', 3)->default('PHP');
            $table->string('status')->default('pending'); // pending, quoted, booked, cancelled
            $table->text('agent_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_package_inquiries');
    }
};
