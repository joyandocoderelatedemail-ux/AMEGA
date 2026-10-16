<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The checklist row an upload was attached to at Receiving Documents, so a scan
     * sits under its requirement and counts that row as received. Sponsor uploads
     * have no applicant.
     */
    public function up(): void
    {
        Schema::table('visa_application_documents', function (Blueprint $table) {
            $table->string('checklist_item', 60)->nullable()->after('document_type');
        });
    }

    public function down(): void
    {
        Schema::table('visa_application_documents', function (Blueprint $table) {
            $table->dropColumn('checklist_item');
        });
    }
};
