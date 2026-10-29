<?php

use App\Services\ClientMemory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the visa desks learn about a client, kept on their record so the next form is filled from it:
 * the SRRV number, class and issue date, the ACR I-Card number and validity, the SSRN, the place of
 * birth and the passport's place of issue. Filled from the existing files on the way in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('place_of_birth')->nullable()->after('date_of_birth');
            $table->string('passport_place')->nullable()->after('passport_country');
            $table->string('srrv_number')->nullable()->after('passport_place');
            $table->string('srrv_visa_class')->nullable()->after('srrv_number');
            $table->date('srrv_issued_on')->nullable()->after('srrv_visa_class');
            $table->string('acr_number')->nullable()->after('srrv_issued_on');
            $table->date('acr_valid_until')->nullable()->after('acr_number');
            $table->string('ssrn')->nullable()->after('acr_valid_until');
        });

        ClientMemory::backfill();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['place_of_birth', 'passport_place', 'srrv_number', 'srrv_visa_class', 'srrv_issued_on', 'acr_number', 'acr_valid_until', 'ssrn']);
        });
    }
};
