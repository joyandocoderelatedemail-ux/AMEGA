<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The airlines staff search when pricing a ticket.
 *
 * None of the local carriers offer a public fare API, so the wizard links out
 * to each airline's own booking site (and the agency's agent portal, when the
 * desk has one). The common carriers are loaded here so production has them
 * without running a seeder; the desk maintains the list from then on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 3)->nullable();
            $table->string('booking_url', 500);
            $table->string('agent_portal_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $defaults = [
            ['name' => 'Cebu Pacific', 'code' => '5J', 'booking_url' => 'https://www.cebupacificair.com', 'sort_order' => 1],
            ['name' => 'Philippine Airlines', 'code' => 'PR', 'booking_url' => 'https://www.philippineairlines.com', 'sort_order' => 2],
            ['name' => 'AirAsia Philippines', 'code' => 'Z2', 'booking_url' => 'https://www.airasia.com', 'sort_order' => 3],
        ];

        $now = now();

        DB::table('airlines')->insert(array_map(
            fn (array $airline) => $airline + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            $defaults,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('airlines');
    }
};
