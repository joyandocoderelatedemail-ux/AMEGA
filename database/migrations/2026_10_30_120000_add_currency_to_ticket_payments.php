<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment may be made in US dollars: the dollars handed over and the exchange rate used are kept,
 * while `amount` stays the peso equivalent that counts against the booking's peso total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_payments', 'currency')) {
                $table->string('currency', 3)->default('PHP')->after('amount');
                $table->decimal('foreign_amount', 12, 2)->nullable()->after('currency');
                $table->decimal('exchange_rate', 12, 4)->nullable()->after('foreign_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_payments', function (Blueprint $table) {
            $table->dropColumn(['currency', 'foreign_amount', 'exchange_rate']);
        });
    }
};
