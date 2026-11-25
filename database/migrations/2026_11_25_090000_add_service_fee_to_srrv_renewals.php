<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An ID renewal's AMEGA service fee with the agent's discount (approved by the admin), paid with the PRA fee at
     * the cashier, whose payments now go on srrv_payments like an SRRV application's.
     */
    public function up(): void
    {
        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->decimal('service_fee', 12, 2)->default(0)->after('fee_amount');
            $table->string('discount_type', 10)->nullable()->after('service_fee');
            $table->decimal('discount_value', 12, 2)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_value');
        });

        Schema::table('srrv_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('srrv_application_id')->nullable()->change();
            $table->unsignedBigInteger('srrv_renewal_id')->nullable()->index()->after('srrv_application_id');
        });
    }

    public function down(): void
    {
        Schema::table('srrv_payments', function (Blueprint $table) {
            $table->dropColumn('srrv_renewal_id');
        });

        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->dropColumn(['service_fee', 'discount_type', 'discount_value', 'discount_amount']);
        });
    }
};
