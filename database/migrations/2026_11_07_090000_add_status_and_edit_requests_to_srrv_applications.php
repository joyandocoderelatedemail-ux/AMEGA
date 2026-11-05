<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An SRRV retiree file gets the hand-set status the Immigration cases have (On-going Preparation ... Released, Denied,
 * Withdrawn, Deferred), a release date that locks it, and Request edit (HasEditRequests): an agent asks the admin to
 * unlock a released or approved file. Nullable, no foreign key: the live tables are MyISAM.
 */
return new class extends Migration
{
    private const COLUMNS = ['workflow_status', 'workflow_status_at', 'released_on', 'edit_request_type', 'edit_requested_at', 'edit_requested_by', 'edit_request_note', 'edit_request_reply', 'edit_request_replied_at', 'status_unlocked_at'];

    public function up(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('srrv_applications', 'workflow_status')) {
                $table->string('workflow_status', 30)->nullable();
            }
            if (! Schema::hasColumn('srrv_applications', 'workflow_status_at')) {
                $table->timestamp('workflow_status_at')->nullable();
            }
            if (! Schema::hasColumn('srrv_applications', 'released_on')) {
                $table->date('released_on')->nullable();
            }
            if (! Schema::hasColumn('srrv_applications', 'edit_request_type')) {
                $table->string('edit_request_type', 20)->nullable();
            }
            if (! Schema::hasColumn('srrv_applications', 'edit_requested_at')) {
                $table->timestamp('edit_requested_at')->nullable();
            }
            if (! Schema::hasColumn('srrv_applications', 'edit_requested_by')) {
                $table->unsignedBigInteger('edit_requested_by')->nullable();
            }
            if (! Schema::hasColumn('srrv_applications', 'edit_request_note')) {
                $table->text('edit_request_note')->nullable();
            }
            if (! Schema::hasColumn('srrv_applications', 'edit_request_reply')) {
                $table->text('edit_request_reply')->nullable();
            }
            if (! Schema::hasColumn('srrv_applications', 'edit_request_replied_at')) {
                $table->timestamp('edit_request_replied_at')->nullable();
            }
            if (! Schema::hasColumn('srrv_applications', 'status_unlocked_at')) {
                $table->timestamp('status_unlocked_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('srrv_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
