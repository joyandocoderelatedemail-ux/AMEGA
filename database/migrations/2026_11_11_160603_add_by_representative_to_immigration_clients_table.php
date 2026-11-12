<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Visa Extension folder records whether AMEGA's representative files it, so the CGAF marks
 * Authorized Representative and prints her name and BI accreditation number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('immigration_clients', function (Blueprint $table) {
            $table->boolean('by_representative')->default(false)->after('needs_attention');
        });
    }

    public function down(): void
    {
        Schema::table('immigration_clients', function (Blueprint $table) {
            $table->dropColumn('by_representative');
        });
    }
};
