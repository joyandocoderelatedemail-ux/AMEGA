<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The office is Unit 1 of the Astrofield Building only (no longer "Unit 1 & 2"). Fixes the
     * address in the FAQ answers staff can edit; the views and documents were changed in code.
     */
    public function up(): void
    {
        foreach (['Unit 1 & 2', 'Unit 1 &amp; 2', 'Unit 1&2', 'Unit 1&amp;2'] as $old) {
            DB::table('faqs')
                ->where('answer', 'like', '%'.$old.'%')
                ->update(['answer' => DB::raw('REPLACE(answer, '.DB::getPdo()->quote($old).", 'Unit 1')")]);
        }
    }

    public function down(): void
    {
        // The office address changed; nothing to restore.
    }
};
