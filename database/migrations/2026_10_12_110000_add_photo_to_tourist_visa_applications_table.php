<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 2x2 photo the BI asks for when the stay ran six months or more past what
 * is allowed: kept on the private documents disk and printed in the CGAF's
 * photo box.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tourist_visa_applications')) {
            return;
        }

        Schema::table('tourist_visa_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('tourist_visa_applications', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('evaluator_name');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('tourist_visa_applications', 'photo_path')) {
            Schema::table('tourist_visa_applications', function (Blueprint $table) {
                $table->dropColumn('photo_path');
            });
        }
    }
};
