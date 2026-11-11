<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The extension ledger keeps what an extension costs apart from what the client paid on it, so the
 * A.R. balance (cost less paid) and the total (paid less refund) can be worked out per row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('immigration_client_extensions', function (Blueprint $table) {
            $table->decimal('estimated_cost', 12, 2)->nullable()->after('details');
        });

        // Until now amount_paid held the extension's price. It becomes the cost, and what was paid is
        // what was received when a part payment was recorded (otherwise the price, paid in full).
        DB::table('immigration_client_extensions')->whereNotNull('amount_paid')->update(['estimated_cost' => DB::raw('amount_paid')]);
        DB::table('immigration_client_extensions')
            ->whereIn('payment_type', ['none', 'downpayment', 'terms'])
            ->whereNotNull('amount_received')
            ->update(['amount_paid' => DB::raw('amount_received')]);
    }

    public function down(): void
    {
        DB::table('immigration_client_extensions')->whereNotNull('estimated_cost')->update(['amount_paid' => DB::raw('estimated_cost')]);

        Schema::table('immigration_client_extensions', function (Blueprint $table) {
            $table->dropColumn('estimated_cost');
        });
    }
};
