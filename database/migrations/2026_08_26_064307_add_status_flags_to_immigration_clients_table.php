<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the agent mark a client sheet as expired or carrying a penalty, and
 * records the visa expiry date those marks are usually judged from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('immigration_clients', function (Blueprint $table) {
            $table->date('visa_expiry_date')->nullable()->after('passport_number');
            $table->boolean('is_expired')->default(false)->after('visa_expiry_date');
            $table->boolean('has_penalty')->default(false)->after('is_expired');
            $table->string('status_note')->nullable()->after('has_penalty');
        });
    }

    public function down(): void
    {
        Schema::table('immigration_clients', function (Blueprint $table) {
            $table->dropColumn(['visa_expiry_date', 'is_expired', 'has_penalty', 'status_note']);
        });
    }
};
