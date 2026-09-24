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
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->string('package_type')->default('ready_made')->after('category'); // ready_made, custom
            $table->string('hotel_name')->nullable()->after('duration');
            $table->string('preferred_hotel')->nullable()->after('hotel_name');
            $table->boolean('has_breakfast')->default(false)->after('preferred_hotel');
            $table->string('bed_config')->nullable()->after('has_breakfast'); // single, twin, double, queen, king, family
            $table->date('check_in_date')->nullable()->after('bed_config');
            $table->date('check_out_date')->nullable()->after('check_in_date');
            $table->string('smoking_preference')->default('non_smoking')->after('check_out_date'); // non_smoking, smoking
            $table->boolean('pet_friendly')->default(false)->after('smoking_preference');
            $table->boolean('has_transportation')->default(false)->after('pet_friendly');
            $table->string('transportation_type')->nullable()->after('has_transportation'); // airport_transfer, private_van, tour_bus, car_rental
            $table->integer('number_of_pax')->default(1)->after('transportation_type');
            $table->text('special_requests')->nullable()->after('number_of_pax');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('travel_packages', function (Blueprint $table) {
            $table->dropColumn([
                'package_type',
                'hotel_name',
                'preferred_hotel',
                'has_breakfast',
                'bed_config',
                'check_in_date',
                'check_out_date',
                'smoking_preference',
                'pet_friendly',
                'has_transportation',
                'transportation_type',
                'number_of_pax',
                'special_requests',
            ]);
        });
    }
};
