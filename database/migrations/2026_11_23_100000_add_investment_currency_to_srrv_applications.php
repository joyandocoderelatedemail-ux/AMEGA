<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The investment amount (the PRA visa deposit) gets its own currency, apart from the AMEGA service fee's.
 * Until now one currency covered both, so each file starts with the one it had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->string('investment_currency', 3)->default('USD')->after('investment_amount');
        });

        DB::table('srrv_applications')->update(['investment_currency' => DB::raw("COALESCE(currency, 'USD')")]);
    }

    public function down(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->dropColumn('investment_currency');
        });
    }
};
