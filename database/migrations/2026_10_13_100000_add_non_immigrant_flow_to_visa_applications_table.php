<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the Non-Immigrant (visit visa) flow records beyond the old pipeline:
     * the drafting step with its second review, requests for more documents,
     * biometrics and the expected processing time, and the country playbook's
     * ticked-off tasks.
     */
    public function up(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->date('biometrics_at')->nullable()->after('appointment_at');
            $table->unsignedSmallInteger('processing_days')->nullable()->after('biometrics_at');
            $table->foreignId('drafted_by')->nullable()->after('acknowledgement_signed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('drafted_at')->nullable()->after('drafted_by');
            $table->foreignId('assessed_by')->nullable()->after('drafted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable()->after('assessed_by');
            $table->json('playbook_progress')->nullable()->after('stage_notified_at');
            $table->json('additional_requests')->nullable()->after('playbook_progress');
        });

        // Open visit visa files move onto the new order: insurance and e-Travel are
        // add-ons now, and a lodged file that already has its result is at Result.
        DB::table('visa_applications')
            ->where('service_type', 'visit_visa')
            ->whereIn('status', ['insurance', 'etravel'])
            ->update(['status' => 'payment']);

        DB::table('visa_applications')
            ->where('service_type', 'visit_visa')
            ->where('status', 'lodged')
            ->whereNotNull('result')
            ->update(['status' => 'result']);
    }

    public function down(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('drafted_by');
            $table->dropConstrainedForeignId('assessed_by');
            $table->dropColumn(['biometrics_at', 'processing_days', 'drafted_at', 'assessed_at', 'playbook_progress', 'additional_requests']);
        });
    }
};
