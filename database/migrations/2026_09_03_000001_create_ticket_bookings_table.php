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
        Schema::create('ticket_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_reference')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('travel_type')->default('domestic');
            $table->string('package_type')->default('without_package');
            $table->foreignId('travel_package_id')->nullable()->constrained('travel_packages')->nullOnDelete();
            $table->string('package_name')->nullable();
            $table->string('origin')->default('Manila (MNL)');
            $table->string('destination');
            $table->string('trip_type')->default('round_trip');
            $table->date('departure_date');
            $table->date('return_date')->nullable();
            $table->unsignedSmallInteger('total_passengers')->default(1);
            $table->unsignedSmallInteger('adults_count')->default(1);
            $table->unsignedSmallInteger('children_count')->default(0);
            $table->unsignedSmallInteger('infants_count')->default(0);
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->boolean('travel_tax_included')->default(false);
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->string('status')->default('pending');
            $table->text('special_requests')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_bookings');
    }
};
