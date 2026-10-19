<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The services were seeded with addresses on amegatravelandtours.com; the agency's
     * domain is amegatravelandtour.com (no "s").
     */
    public function up(): void
    {
        DB::table('services')
            ->where('email', 'like', '%@amegatravelandtours.com')
            ->update(['email' => DB::raw("REPLACE(email, '@amegatravelandtours.com', '@amegatravelandtour.com')")]);
    }

    public function down(): void
    {
        // The old addresses were never the agency's; nothing to restore.
    }
};
