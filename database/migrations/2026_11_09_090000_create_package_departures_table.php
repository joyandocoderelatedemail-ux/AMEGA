<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_package_id')->constrained()->cascadeOnDelete();
            $table->date('departure_date');
            $table->date('return_date')->nullable();
            $table->unsignedInteger('slots')->default(10);
            $table->timestamps();

            $table->unique(['travel_package_id', 'departure_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_departures');
    }
};
