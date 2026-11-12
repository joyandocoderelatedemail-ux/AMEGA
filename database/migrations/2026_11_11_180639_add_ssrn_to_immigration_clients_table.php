<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client's Special Security Registration Number (SSRN), kept once on the folder and printed
 * on the ECC application form and the extension of stay CGAF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('immigration_clients', function (Blueprint $table) {
            $table->string('ssrn')->nullable()->after('passport_number');
        });
    }

    public function down(): void
    {
        Schema::table('immigration_clients', function (Blueprint $table) {
            $table->dropColumn('ssrn');
        });
    }
};
