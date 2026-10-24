<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When staff first saw each website inquiry. An unread one counts on the admin's red badges and
 * notification bell. Inquiries already in the inbox count as read, so the badge starts from zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('status');
        });

        DB::table('inquiries')->update(['read_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};
