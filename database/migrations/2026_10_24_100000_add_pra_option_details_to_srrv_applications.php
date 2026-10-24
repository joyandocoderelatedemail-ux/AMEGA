<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The PRA's Expanded SRRV Program (PRA-PD-LORQ-0010, August 2025): Courtesy is either for foreign
 * nationals or for former Filipinos, Classic and Courtesy deposits depend on whether the retiree
 * has a pension, and each requirement on the PRA list can be ticked as seen at the desk. The wizard
 * is now the application itself, so files part-way through its old stages go back to that stage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->string('courtesy_type', 30)->nullable()->after('visa_class');
            $table->boolean('is_pensioner')->default(false)->after('courtesy_type');
            $table->json('requirements_received')->nullable()->after('military_service_proof_received');
        });

        // The wizard now covers the category, requirements, deposit and supporting documents.
        DB::table('srrv_applications')
            ->where('service_type', 'renewal_application')
            ->whereIn('status', ['requirements', 'investment', 'supporting'])
            ->update(['status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->dropColumn(['courtesy_type', 'is_pensioner', 'requirements_received']);
        });
    }
};
