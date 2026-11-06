<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A married client's spouse and dependents, and the passport scans beyond the first
 * (passport_photo stays the first scan, so everything that reads it keeps working).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('spouse_name')->nullable()->after('civil_status');
            $table->json('dependents')->nullable()->after('spouse_name');
            $table->json('passport_scans')->nullable()->after('passport_photo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['spouse_name', 'dependents', 'passport_scans']);
        });
    }
};
