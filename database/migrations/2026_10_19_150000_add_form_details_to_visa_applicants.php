<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each applicant's answers to the country worksheet (Schengen, Korea, China, New
     * Zealand): what the embassy form asks beyond the name, birth date and passport.
     */
    public function up(): void
    {
        Schema::table('visa_applicants', function (Blueprint $table) {
            $table->json('form_details')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('visa_applicants', function (Blueprint $table) {
            $table->dropColumn('form_details');
        });
    }
};
