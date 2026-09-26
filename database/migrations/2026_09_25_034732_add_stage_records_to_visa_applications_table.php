<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What each pipeline stage records before a file may advance: the travel
     * insurance taken (or declined), the embassy lodgement, and the
     * application result.
     */
    public function up(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->string('insurance_provider')->nullable()->after('insurance_included');
            $table->string('insurance_policy_number')->nullable()->after('insurance_provider');
            $table->boolean('insurance_declined')->default(false)->after('insurance_policy_number');
            $table->date('lodged_at')->nullable()->after('acknowledgement_signed_at');
            $table->string('embassy_reference')->nullable()->after('lodged_at');
            $table->string('result')->nullable()->after('embassy_reference');
            $table->date('result_at')->nullable()->after('result');
            $table->string('result_reference')->nullable()->after('result_at');
            $table->string('result_validity')->nullable()->after('result_reference');
        });
    }

    public function down(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->dropColumn([
                'insurance_provider', 'insurance_policy_number', 'insurance_declined',
                'lodged_at', 'embassy_reference',
                'result', 'result_at', 'result_reference', 'result_validity',
            ]);
        });
    }
};
