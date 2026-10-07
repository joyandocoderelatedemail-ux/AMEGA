<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Staff now choose between Individual, Corporate and Group. Clients saved as
 * "Family" are a group, so they move across rather than being left under a
 * category the filter no longer offers.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('account_category', 'Family')->update(['account_category' => 'Group']);
    }

    public function down(): void
    {
        // The two cannot be told apart again, so there is nothing to restore.
    }
};
