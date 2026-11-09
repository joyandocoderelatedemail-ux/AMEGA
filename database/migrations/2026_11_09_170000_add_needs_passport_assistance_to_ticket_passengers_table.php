<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_passengers', function (Blueprint $table) {
            // International passengers with no valid passport (none, expired or under six months left): the Visa Assistance desk helps them get or renew one.
            $table->boolean('needs_passport_assistance')->default(false)->after('passport_country');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_passengers', function (Blueprint $table) {
            $table->dropColumn('needs_passport_assistance');
        });
    }
};
