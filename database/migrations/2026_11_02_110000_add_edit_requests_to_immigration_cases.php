<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The cases that can be released and locked. */
    private const CASE_TABLES = ['immigration_petitions', 'immigration_overstays', 'immigration_restampings'];

    private const COLUMNS = ['edit_requested_at', 'edit_requested_by', 'edit_request_note', 'edit_request_reply', 'edit_request_replied_at'];

    /**
     * An agent asks the admin to unlock a released file: when, who, why, and the admin's reply if declined.
     * Nullable columns only and no foreign key, as the live host's tables are MyISAM.
     */
    public function up(): void
    {
        foreach (self::CASE_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! Schema::hasColumn($name, 'edit_requested_at')) {
                    $table->timestamp('edit_requested_at')->nullable();
                }
                if (! Schema::hasColumn($name, 'edit_requested_by')) {
                    $table->unsignedBigInteger('edit_requested_by')->nullable();
                }
                if (! Schema::hasColumn($name, 'edit_request_note')) {
                    $table->text('edit_request_note')->nullable();
                }
                if (! Schema::hasColumn($name, 'edit_request_reply')) {
                    $table->text('edit_request_reply')->nullable();
                }
                if (! Schema::hasColumn($name, 'edit_request_replied_at')) {
                    $table->timestamp('edit_request_replied_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::CASE_TABLES as $name) {
            $present = array_values(array_filter(self::COLUMNS, fn (string $column) => Schema::hasColumn($name, $column)));

            if ($present !== []) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn($present));
            }
        }
    }
};
