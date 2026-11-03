<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CASE_TABLES = ['immigration_petitions', 'immigration_overstays', 'immigration_restampings'];

    /**
     * What an agent asked for when they requested an edit: 'details' (correct the file, which then goes back for
     * approval) or 'status' (reopen a released file just so its status can change). Nullable, no foreign key
     * (the live host's tables are MyISAM).
     */
    public function up(): void
    {
        foreach (self::CASE_TABLES as $name) {
            if (! Schema::hasColumn($name, 'edit_request_type')) {
                Schema::table($name, fn (Blueprint $table) => $table->string('edit_request_type', 10)->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (self::CASE_TABLES as $name) {
            if (Schema::hasColumn($name, 'edit_request_type')) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn('edit_request_type'));
            }
        }
    }
};
