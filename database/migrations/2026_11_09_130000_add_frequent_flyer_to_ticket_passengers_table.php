<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_passengers', function (Blueprint $table) {
            // The passenger's frequent flyer / airline membership, from the client's profile or typed in.
            $table->string('frequent_flyer_membership')->nullable()->after('passport_expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_passengers', function (Blueprint $table) {
            $table->dropColumn('frequent_flyer_membership');
        });
    }
};
