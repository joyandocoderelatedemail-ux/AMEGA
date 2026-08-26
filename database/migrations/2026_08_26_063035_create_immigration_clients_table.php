<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('immigration_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Personal Information, as laid out on the Client Information Sheet
            $table->string('last_name');
            $table->string('given_name');
            $table->text('address')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('height')->nullable();
            $table->string('weight')->nullable();
            $table->string('civil_status')->nullable();
            $table->string('nationality')->nullable();
            $table->date('date_of_birth')->nullable();

            // The counter looks a client up by passport, so this carries an index
            $table->string('passport_number')->nullable()->index();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immigration_clients');
    }
};
