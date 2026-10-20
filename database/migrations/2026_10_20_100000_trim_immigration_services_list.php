<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Immigration Services card no longer lists Exit Clearances, 9G Working Visa or
     * Application for Correction. Only those lines go; anything else an admin has edited
     * in the list stays as it is.
     */
    public function up(): void
    {
        $removed = ['exit clearances', '9g working visa', 'application for correction'];

        foreach (DB::table('services')->where('title', 'Immigration Services')->get(['id', 'full_description']) as $service) {
            $lines = preg_split('/\R/', (string) $service->full_description) ?: [];
            $kept = array_filter($lines, fn (string $line) => ! in_array(mb_strtolower(trim($line)), $removed, true));

            DB::table('services')->where('id', $service->id)->update(['full_description' => implode("\n", $kept)]);
        }
    }

    public function down(): void
    {
        // The removed lines were a list on a public card; nothing to restore.
    }
};
