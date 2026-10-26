<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A service tax on corporate bookings: a rate of 1 to 10% set by the agent, charged on the
 * Amega Service Fee only, and added to the total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->decimal('service_tax_rate', 5, 2)->nullable()->after('service_fee');
            $table->decimal('service_tax_amount', 12, 2)->default(0)->after('service_tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropColumn(['service_tax_rate', 'service_tax_amount']);
        });
    }
};
