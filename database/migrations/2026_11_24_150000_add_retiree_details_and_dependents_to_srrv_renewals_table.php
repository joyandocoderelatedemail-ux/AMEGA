<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('srrv_renewals', function (Blueprint $table) {
            // The Retiree step asks what an SRRV application asks.
            $table->string('retiree_phone', 50)->nullable()->after('retiree_email');
            $table->string('nationality', 100)->nullable()->after('retiree_phone');
            $table->date('date_of_birth')->nullable()->after('nationality');
            // Dependents on the SRRV: a Classic renewal charges each one beyond two (SrrvRenewal::annualFee).
            $table->unsignedTinyInteger('dependents')->default(0)->after('years_paid');
        });
    }

    public function down(): void
    {
        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->dropColumn(['retiree_phone', 'nationality', 'date_of_birth', 'dependents']);
        });
    }
};
