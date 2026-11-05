<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Passporting walks the E-Visa steps now: client information, agreement and fees, documents, appointment, results.
     * Files opened under the old order (requirements, appointment, payment, lodged) are mapped onto it, and the ones
     * already open count as approved, since they pre-date the Send to Admin step.
     */
    public function up(): void
    {
        $files = fn () => DB::table('visa_applications')->where('service_type', 'passporting');

        $files()->whereIn('status', ['payment', 'lodged'])->update(['status' => 'result']);
        $files()->whereNull('approval_status')->update(['approval_status' => 'approved']);
    }

    public function down(): void {}
};
