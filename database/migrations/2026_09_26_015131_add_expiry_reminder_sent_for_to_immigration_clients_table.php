<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records the visa expiry date the client was last reminded about, so the
     * daily reminder goes out once per expiry and again after an extension.
     */
    public function up(): void
    {
        Schema::table('immigration_clients', function (Blueprint $table) {
            $table->date('expiry_reminder_sent_for')->nullable()->after('visa_expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('immigration_clients', function (Blueprint $table) {
            $table->dropColumn('expiry_reminder_sent_for');
        });
    }
};
