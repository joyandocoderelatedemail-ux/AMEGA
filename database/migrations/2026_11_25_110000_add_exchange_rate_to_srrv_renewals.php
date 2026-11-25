<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A renewal charged in pesos: the rate its USD PRA fee was converted at (pesos to one US dollar). */
    public function up(): void
    {
        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 4)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
