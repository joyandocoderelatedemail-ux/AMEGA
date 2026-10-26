<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When staff first opened a website chat after the visitor asked for an agent. Until then it counts on
 * the admin's bell and red badges; asking for an agent again makes it count again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('admin_seen_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('admin_seen_at');
        });
    }
};
