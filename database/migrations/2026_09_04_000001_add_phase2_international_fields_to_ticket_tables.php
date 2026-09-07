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
        Schema::table('ticket_bookings', function (Blueprint $table) {
            // Step 1: Destination Fields
            $table->string('destination_country')->nullable()->after('destination');
            $table->string('destination_city')->nullable()->after('destination_country');
            $table->string('arrival_airport')->nullable()->after('destination_city');
            $table->string('preferred_airline')->nullable()->after('arrival_airport');

            // Step 2: Trip Details
            $table->string('travel_class')->default('economy')->after('trip_type'); // economy, premium_economy, business, first_class
            $table->string('preferred_flight_time')->default('anytime')->after('travel_class'); // anytime, morning, afternoon, evening
            $table->json('multi_city_segments')->nullable()->after('preferred_flight_time');

            // Step 6: Travel Insurance
            $table->boolean('has_insurance')->default(false)->after('travel_tax_included');
            $table->string('insurance_plan')->nullable()->after('has_insurance'); // basic, standard, premium

            // Step 7: Optional Services
            $table->json('selected_services')->nullable()->after('insurance_plan');

            // Step 8: Emergency Contact
            $table->string('emergency_contact_name')->nullable()->after('contact_phone');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_relationship');
            $table->string('emergency_contact_email')->nullable()->after('emergency_contact_phone');

            // Step 9: Special Requests (Multi-select)
            $table->json('special_requests_list')->nullable()->after('special_requests');

            // Step 11: Pricing Breakdown
            $table->decimal('estimated_fare', 12, 2)->default(0)->after('total_amount');
            $table->decimal('taxes_amount', 12, 2)->default(0)->after('estimated_fare');
            $table->decimal('visa_assistance_fee', 12, 2)->default(0)->after('taxes_amount');
            $table->decimal('insurance_fee', 12, 2)->default(0)->after('visa_assistance_fee');
            $table->decimal('other_charges', 12, 2)->default(0)->after('insurance_fee');
        });

        Schema::table('ticket_passengers', function (Blueprint $table) {
            // Step 5: International Visa Requirements per passenger
            $table->string('visa_status')->nullable()->after('visa_type'); // already_has_visa, needs_assistance, visa_not_required
            $table->string('visa_assistance_type')->nullable()->after('visa_status');
            $table->unsignedSmallInteger('intended_stay_days')->nullable()->after('visa_assistance_type');
            $table->string('purpose_of_travel')->nullable()->after('intended_stay_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'destination_country',
                'destination_city',
                'arrival_airport',
                'preferred_airline',
                'travel_class',
                'preferred_flight_time',
                'multi_city_segments',
                'has_insurance',
                'insurance_plan',
                'selected_services',
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_phone',
                'emergency_contact_email',
                'special_requests_list',
                'estimated_fare',
                'taxes_amount',
                'visa_assistance_fee',
                'insurance_fee',
                'other_charges',
            ]);
        });

        Schema::table('ticket_passengers', function (Blueprint $table) {
            $table->dropColumn([
                'visa_status',
                'visa_assistance_type',
                'intended_stay_days',
                'purpose_of_travel',
            ]);
        });
    }
};
