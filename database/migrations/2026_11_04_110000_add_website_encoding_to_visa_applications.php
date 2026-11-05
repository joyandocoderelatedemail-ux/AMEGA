<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 3 of a Non-Immigrant file, Official Website Encoding: when the client's details were entered on the embassy's
 * website, by whom, and the reference it gave (optional). Nullable, no foreign key: the live tables are MyISAM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('visa_applications', 'encoded_at')) {
                $table->timestamp('encoded_at')->nullable();
            }
            if (! Schema::hasColumn('visa_applications', 'encoded_by')) {
                $table->unsignedBigInteger('encoded_by')->nullable();
            }
            if (! Schema::hasColumn('visa_applications', 'portal_reference')) {
                $table->string('portal_reference', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            foreach (['encoded_at', 'encoded_by', 'portal_reference'] as $column) {
                if (Schema::hasColumn('visa_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
