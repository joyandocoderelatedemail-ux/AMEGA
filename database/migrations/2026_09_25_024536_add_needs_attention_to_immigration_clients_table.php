<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fourth counter flag, beside expiring, expired and penalty: anything
     * else that needs following up. The status note says what.
     */
    public function up(): void
    {
        Schema::table('immigration_clients', function (Blueprint $table) {
            $table->boolean('needs_attention')->default(false)->after('has_penalty');
        });
    }

    public function down(): void
    {
        Schema::table('immigration_clients', function (Blueprint $table) {
            $table->dropColumn('needs_attention');
        });
    }
};
