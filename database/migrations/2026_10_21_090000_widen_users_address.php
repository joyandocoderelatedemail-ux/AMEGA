<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The client forms accept an address of up to 500 characters, and an immigration sheet's address
     * (unlimited text) is copied onto the client, but the column held 255: a longer one failed the save
     * with "Data too long".
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('address')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Narrowing would cut longer addresses, so the column stays wide.
    }
};
