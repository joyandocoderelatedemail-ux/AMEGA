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
        Schema::create('booking_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_booking_id')->constrained('ticket_bookings')->onDelete('cascade');
            $table->string('agreement_number')->unique();
            $table->text('client_names');
            $table->date('agreement_date');
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('home_hotel_address')->nullable();

            // Flight / Carrier Schedule Table (JSON array of segments)
            // [{carrier, flight_number, flight_class, day, month, from_location, to_location, departure_time, arrival_time, flight_status}]
            $table->json('flight_segments')->nullable();

            // Inclusions & Conditions Checkboxes
            $table->boolean('has_baggage')->default(true);
            $table->boolean('is_non_refundable')->default(true);
            $table->boolean('is_non_rebookable')->default(false);
            $table->boolean('has_meals')->default(false);
            $table->boolean('with_rebooking_charge')->default(true);
            $table->boolean('with_airport_transfer')->default(false);

            // Pricing & Pax Table (JSON array of custom agent pricing lines)
            // [{airfare_description, price_details, pax_count, amount}]
            $table->json('pricing_items')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('payment_terms')->nullable();

            // Signatures & Issuing Metadata
            $table->string('agent_name')->nullable();
            $table->string('passenger_client_name')->nullable();
            $table->string('status')->default('generated'); // draft, generated, confirmed, signed

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_agreements');
    }
};
