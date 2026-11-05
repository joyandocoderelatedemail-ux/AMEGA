<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** What an "other" document is, in the counter's own words. */
    public function up(): void
    {
        Schema::table('visa_application_documents', function (Blueprint $table) {
            $table->string('remarks')->nullable()->after('document_type');
        });
    }

    public function down(): void
    {
        Schema::table('visa_application_documents', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
