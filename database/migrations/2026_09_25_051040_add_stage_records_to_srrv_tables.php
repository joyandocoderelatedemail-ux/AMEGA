<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the SRRV stages record before a file may advance: the PRA reference
     * the file was lodged under, when the supporting documents were complete,
     * and what a renewal has paid against its fee.
     *
     * New applications now follow the PRA-SRRV flowchart (investment, supporting
     * documents, documentation, oath, release), so files on the old lodged /
     * paid / processing stages move to Documentation, where those steps live now.
     */
    public function up(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->string('pra_reference')->nullable()->after('lodged_at');
            $table->timestamp('supporting_completed_at')->nullable()->after('pra_reference');
        });

        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->decimal('amount_paid', 12, 2)->default(0)->after('fee_amount');
        });

        DB::table('srrv_applications')
            ->where('service_type', 'renewal_application')
            ->whereIn('status', ['lodged', 'paid', 'processing'])
            ->update(['status' => 'documentation']);
    }

    public function down(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->dropColumn(['pra_reference', 'supporting_completed_at']);
        });

        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->dropColumn('amount_paid');
        });
    }
};
