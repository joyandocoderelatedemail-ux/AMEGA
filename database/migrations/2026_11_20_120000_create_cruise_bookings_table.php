<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Cruise Services desk: a booking per client, worked step by step. Step 1 (Check Availability)
 * records the destination, the dates, the guests (adults, children, infants) and the preferred cruise
 * line and ship, and whether the slot is confirmed. Step 2 (Prepare the Quotation) records the cost
 * and the day-by-day itinerary. Also the "Cruise Agent" staff role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cruise_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique();
            // No foreign keys to users: the production users table's engine refuses them (errno 150).
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('status', 30)->default('availability');

            $table->string('client_name');
            $table->string('contact_number', 50)->nullable();

            // Step 1: Check Availability.
            $table->string('destination')->nullable();
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->unsignedSmallInteger('adults')->nullable();
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedSmallInteger('infants')->default(0);
            $table->string('cruise_line', 100)->nullable();
            $table->string('ship', 100)->nullable();
            $table->timestamp('slot_confirmed_at')->nullable();
            $table->unsignedBigInteger('slot_confirmed_by')->nullable();

            // Step 2: Prepare the Quotation, in the booking's currency. total_amount is the sum of the
            // cost lines and the add-ons ({description, amount} each); the itinerary is {day, date, description} per day.
            $table->string('currency', 3)->default('PHP');
            $table->decimal('base_fare', 12, 2)->default(0);
            $table->decimal('tipping', 12, 2)->default(0);
            $table->decimal('insurance', 12, 2)->default(0);
            $table->decimal('travel_taxes', 12, 2)->default(0);
            $table->json('add_ons')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->json('itinerary')->nullable();
            $table->timestamp('quotation_at')->nullable();
            $table->unsignedBigInteger('quotation_by')->nullable();

            $table->timestamps();
            $table->index('status');
        });

        // The desk's own role, for installs that already have the staff roles.
        if (Schema::hasTable('staff_roles') && ! DB::table('staff_roles')->where('name', 'Cruise Agent')->exists()) {
            $role = Permissions::systemRoles()['Cruise Agent'];
            DB::table('staff_roles')->insert([
                'name' => 'Cruise Agent',
                'description' => $role['description'],
                'permissions' => json_encode(Permissions::clean($role['permissions'])),
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cruise_bookings');

        if (Schema::hasTable('staff_roles')) {
            DB::table('staff_roles')->where('name', 'Cruise Agent')->delete();
        }
    }
};
