<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The PRA Submission steps after the wizard (submission, visa deposit and payment, the PRA's evaluation, documents
 * received, client turnover) replace the old Lodged with PRA / Oath Taking / Waiting for Release stages. Each step's
 * remarks, the furthest step reached and the PRA's decision are kept in `pra_flow`. Files part-way through the old
 * stages move to the matching step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->json('pra_flow')->nullable()->after('pra_reference');
        });

        foreach (['documentation' => 1, 'oath' => 3, 'awaiting_release' => 4] as $status => $step) {
            DB::table('srrv_applications')
                ->where('service_type', 'renewal_application')
                ->where('status', $status)
                ->update([
                    'status' => 'pra',
                    'pra_flow' => json_encode(['step' => $step, 'decision' => $step >= 4 ? 'approved' : 'processing', 'remarks' => []]),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('srrv_applications')->where('status', 'pra')->update(['status' => 'documentation']);

        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->dropColumn('pra_flow');
        });
    }
};
