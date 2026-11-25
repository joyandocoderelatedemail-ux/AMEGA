<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Split renewal billing: the PRA ID renewal fee is always in USD, AMEGA's
     * service fee (and its discount) always in PHP. No conversion, no exchange
     * rate. Rows saved in pesos keep their converted fee restored to USD.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('srrv_renewals', 'fee_paid')) {
            Schema::table('srrv_renewals', function (Blueprint $table) {
                $table->decimal('fee_paid', 12, 2)->default(0)->after('fee_amount');
                $table->char('service_currency', 3)->default('PHP')->after('currency');
            });
        }

        if (! Schema::hasColumn('srrv_payments', 'currency')) {
            Schema::table('srrv_payments', function (Blueprint $table) {
                $table->char('currency', 3)->nullable()->after('amount');
            });
        }

        // Restore peso-converted PRA fees to USD, then split what was paid.
        DB::transaction(function (): void {
            // Split what was paid: the fee side first, the rest stays on the service side.
            foreach (DB::table('srrv_renewals')->get(['id', 'fee_amount', 'amount_paid', 'exchange_rate']) as $row) {
                $fee = (float) $row->fee_amount;
                $paid = (float) $row->amount_paid;

                if ($row->exchange_rate) {
                    // Pesos rows keep their converted fee restored to USD, then split in pesos.
                    $feePhp = $fee;
                    $feePaidPhp = min($paid, $feePhp);
                    $fee = round($feePhp / (float) $row->exchange_rate, 2);
                    $feePaid = round($feePaidPhp / (float) $row->exchange_rate, 2);
                    $paid = round($paid - $feePaidPhp, 2);
                } else {
                    $feePaid = min($paid, $fee);
                    $paid = round($paid - $feePaid, 2);
                }

                DB::table('srrv_renewals')->where('id', $row->id)->update([
                    'fee_amount' => $fee,
                    'fee_paid' => $feePaid,
                    'amount_paid' => $paid,
                ]);
            }

            DB::table('srrv_renewals')->update([
                'service_currency' => DB::raw('currency'),
                'currency' => 'USD',
            ]);

            // Old payments covered the single mixed total: keep them on the service side in the row's currency.
            foreach (DB::table('srrv_payments')->whereNull('currency')->get(['id', 'srrv_renewal_id']) as $payment) {
                $serviceCurrency = $payment->srrv_renewal_id
                    ? DB::table('srrv_renewals')->where('id', $payment->srrv_renewal_id)->value('service_currency')
                    : null;

                DB::table('srrv_payments')->where('id', $payment->id)->update([
                    'currency' => $serviceCurrency ?: 'PHP',
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('srrv_payments', 'currency')) {
            Schema::table('srrv_payments', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }

        if (Schema::hasColumn('srrv_renewals', 'fee_paid')) {
            Schema::table('srrv_renewals', function (Blueprint $table) {
                $table->dropColumn(['fee_paid', 'service_currency']);
            });
        }
    }
};
