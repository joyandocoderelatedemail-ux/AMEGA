<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cruises have their own card (Cruise Services), so the Travel Services card drops its
     * "International Cruises" line and the cruise-line logos under it.
     */
    public function up(): void
    {
        DB::table('services')->where('title', 'Travel Services')->get(['id', 'full_description'])
            ->each(function ($service) {
                $lines = preg_split('/\R/', (string) $service->full_description) ?: [];
                $kept = array_filter($lines, fn ($line) => trim($line) !== 'International Cruises');

                DB::table('services')->where('id', $service->id)->update([
                    'full_description' => implode("\n", $kept),
                    'logo_strip' => null,
                ]);
            });
    }

    public function down(): void
    {
        // Content change; the admin can add the line or logos back from the Services page.
    }
};
