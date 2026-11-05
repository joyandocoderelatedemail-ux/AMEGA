<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A released visa counter file is locked, as an Immigration case is: an agent asks the admin to unlock it (Request
 * edit). The same columns as the Immigration cases (HasEditRequests). Nullable, no foreign key: the live tables are MyISAM.
 */
return new class extends Migration
{
    private const COLUMNS = ['edit_request_type', 'edit_requested_at', 'edit_requested_by', 'edit_request_note', 'edit_request_reply', 'edit_request_replied_at', 'status_unlocked_at'];

    public function up(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('visa_applications', 'edit_request_type')) {
                $table->string('edit_request_type', 20)->nullable();
            }
            if (! Schema::hasColumn('visa_applications', 'edit_requested_at')) {
                $table->timestamp('edit_requested_at')->nullable();
            }
            if (! Schema::hasColumn('visa_applications', 'edit_requested_by')) {
                $table->unsignedBigInteger('edit_requested_by')->nullable();
            }
            if (! Schema::hasColumn('visa_applications', 'edit_request_note')) {
                $table->text('edit_request_note')->nullable();
            }
            if (! Schema::hasColumn('visa_applications', 'edit_request_reply')) {
                $table->text('edit_request_reply')->nullable();
            }
            if (! Schema::hasColumn('visa_applications', 'edit_request_replied_at')) {
                $table->timestamp('edit_request_replied_at')->nullable();
            }
            if (! Schema::hasColumn('visa_applications', 'status_unlocked_at')) {
                $table->timestamp('status_unlocked_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('visa_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
