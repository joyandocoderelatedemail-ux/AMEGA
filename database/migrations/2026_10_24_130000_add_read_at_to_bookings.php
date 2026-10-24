<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When staff first saw each online tour-package booking. An unread one counts on the admin's red
 * badges and notification bell. Bookings already in the list count as read, so the badge starts at zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('payment_status');
        });

        DB::table('bookings')->update(['read_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};
