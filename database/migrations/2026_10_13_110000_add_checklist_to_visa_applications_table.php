<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which of the country's document checklists a Non-Immigrant file follows
     * (config/visa_checklists.php), and what has been received against it.
     */
    public function up(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->string('checklist_key')->nullable()->after('purpose');
            $table->json('checklist_progress')->nullable()->after('playbook_progress');
        });
    }

    public function down(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->dropColumn(['checklist_key', 'checklist_progress']);
        });
    }
};
