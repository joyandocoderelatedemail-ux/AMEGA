<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_plans', function (Blueprint $table) {
            $table->id();
            // What a booking stores in ticket_bookings.insurance_plan. Never changed once set.
            $table->string('key', 60)->unique();
            $table->string('name', 100);
            $table->decimal('price_per_pax', 10, 2)->default(0);
            $table->json('coverage')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // The three plans the wizard used to hard-code, so bookings already made keep their plan.
        $now = now();
        $plans = [
            ['basic', 'Basic Plan', 950, ['Up to $25,000 Medical', 'Emergency Evacuation', '24/7 Hotline'], false],
            ['standard', 'Standard Plan', 1850, ['Up to $50,000 Medical', 'Trip Cancellation Coverage', 'Baggage Loss Protection'], true],
            ['premium', 'Premium Plan', 3200, ['Up to $100,000 Global Medical', 'Zero Deductible / All Risks', 'Flight Delay & Concierge'], false],
        ];

        foreach ($plans as $order => [$key, $name, $price, $coverage, $popular]) {
            DB::table('insurance_plans')->insert([
                'key' => $key,
                'name' => $name,
                'price_per_pax' => $price,
                'coverage' => json_encode($coverage),
                'is_popular' => $popular,
                'is_active' => true,
                'sort_order' => $order + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_plans');
    }
};
