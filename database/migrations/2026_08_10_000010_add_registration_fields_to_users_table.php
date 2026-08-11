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
        Schema::table('users', function (Blueprint $table) {
            // Part 1: Personal, Nationality & Emergency Contact
            $table->string('nationality')->nullable()->after('phone');
            $table->string('emergency_contact_name')->nullable()->after('nationality');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_phone');

            // Part 2: Passport, ID & Account Category
            $table->string('passport_number')->nullable()->after('emergency_contact_relationship');
            $table->date('passport_expiry')->nullable()->after('passport_number');
            $table->string('passport_country')->nullable()->after('passport_expiry');
            $table->string('government_id_type')->nullable()->after('passport_country');
            $table->string('government_id_number')->nullable()->after('government_id_type');
            $table->string('account_category')->default('Individual')->after('government_id_number');

            // Part 3: Profile Photo & E-Signature
            $table->string('profile_photo')->nullable()->after('account_category');
            $table->longText('signature')->nullable()->after('profile_photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nationality',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relationship',
                'passport_number',
                'passport_expiry',
                'passport_country',
                'government_id_type',
                'government_id_number',
                'account_category',
                'profile_photo',
                'signature',
            ]);
        });
    }
};
