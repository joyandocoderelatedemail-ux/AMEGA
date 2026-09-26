<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The remaining passenger details the ticket form asks for, plus a passport
 * scan to sit beside the existing government ID scan, so a registered client
 * can be pulled into a booking instead of re-typed at the counter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->after('suffix');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('emergency_contact_email')->nullable()->after('emergency_contact_relationship');
            $table->string('passport_photo')->nullable()->after('passport_country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gender', 'date_of_birth', 'emergency_contact_email', 'passport_photo']);
        });
    }
};
