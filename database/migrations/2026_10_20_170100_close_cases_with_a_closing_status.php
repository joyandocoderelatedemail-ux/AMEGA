<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Denied, withdrawn and deferred cases are closed. Cases already set to one of those
     * are closed on the day their status was set.
     */
    public function up(): void
    {
        foreach (['immigration_overstays', 'immigration_restampings', 'immigration_petitions'] as $table) {
            DB::table($table)
                ->whereIn('workflow_status', ['denied', 'withdrawn', 'deferred'])
                ->whereNull('released_on')
                ->orderBy('id')
                ->get(['id', 'workflow_status_at'])
                ->each(fn ($case) => DB::table($table)->where('id', $case->id)->update([
                    'released_on' => substr((string) ($case->workflow_status_at ?? now()), 0, 10),
                ]));
        }
    }

    public function down(): void
    {
        // The closing date is real data once recorded; nothing to undo.
    }
};
