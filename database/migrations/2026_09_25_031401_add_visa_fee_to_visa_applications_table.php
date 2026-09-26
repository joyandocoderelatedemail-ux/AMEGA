<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The embassy visa fee / expenses collected on a file, kept apart from
     * our service fee as on the fee breakdown sheet.
     */
    public function up(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->decimal('visa_fee', 10, 2)->default(0)->after('service_fee');
        });
    }

    public function down(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->dropColumn('visa_fee');
        });
    }
};
