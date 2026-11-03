<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CASE_TABLES = ['immigration_petitions', 'immigration_overstays', 'immigration_restampings'];

    /**
     * When the admin unlocked the Approved status of a case (an approved status-change request). Null means an
     * Approved status is still locked for agents. Nullable, no foreign key (the live host's tables are MyISAM).
     */
    public function up(): void
    {
        foreach (self::CASE_TABLES as $name) {
            if (! Schema::hasColumn($name, 'status_unlocked_at')) {
                Schema::table($name, fn (Blueprint $table) => $table->timestamp('status_unlocked_at')->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (self::CASE_TABLES as $name) {
            if (Schema::hasColumn($name, 'status_unlocked_at')) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn('status_unlocked_at'));
            }
        }
    }
};
