<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The visa assistance fee breakdown is priced per destination country:
     * the selling price (amount) splits into our service fee and the visa
     * fee / expenses, with the inclusions listed beside it.
     */
    public function up(): void
    {
        Schema::table('visa_pricing_tiers', function (Blueprint $table) {
            $table->string('country')->nullable()->after('label');
            $table->decimal('service_fee', 10, 2)->nullable()->after('amount');
            $table->decimal('visa_fee', 10, 2)->nullable()->after('service_fee');
            $table->decimal('insurance_fee', 10, 2)->nullable()->after('visa_fee');
            $table->string('inclusions')->nullable()->after('insurance_fee');
        });
    }

    public function down(): void
    {
        Schema::table('visa_pricing_tiers', function (Blueprint $table) {
            $table->dropColumn(['country', 'service_fee', 'visa_fee', 'insurance_fee', 'inclusions']);
        });
    }
};
