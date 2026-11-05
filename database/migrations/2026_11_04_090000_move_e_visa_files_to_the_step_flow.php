<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * E-Visa has its own steps now: client information, agreement and fees (sent to the admin), documents, results.
     * Files opened under the old order (requirements, payment, agreement, lodged) are mapped onto it; the ones
     * already past the agreement count as approved, since they pre-date that step.
     */
    public function up(): void
    {
        $eVisas = fn () => DB::table('visa_applications')->where('service_type', 'e_visa');

        $eVisas()->where('status', 'lodged')->update(['status' => 'result']);
        $eVisas()->where('status', 'payment')->update(['status' => 'requirements']);
        $eVisas()->where('status', 'agreement')->whereNotNull('agreement_signed_at')->update(['status' => 'requirements']);
        $eVisas()->whereIn('status', ['requirements', 'result', 'released'])->whereNull('approval_status')->update(['approval_status' => 'approved']);
    }

    public function down(): void {}
};
