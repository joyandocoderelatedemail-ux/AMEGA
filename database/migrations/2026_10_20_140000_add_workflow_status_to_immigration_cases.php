<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['immigration_overstays', 'immigration_restampings', 'immigration_petitions'];

    /**
     * Where a case stands in the desk's own workflow (On-going Preparation through Approved,
     * Denied and so on), set by hand from the Cases list. A case with none is in preparation.
     */
    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('workflow_status', 30)->nullable()->index();
                $table->timestamp('workflow_status_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['workflow_status']);
                $table->dropColumn(['workflow_status', 'workflow_status_at']);
            });
        }
    }
};
