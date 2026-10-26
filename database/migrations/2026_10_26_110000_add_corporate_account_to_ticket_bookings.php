<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The company a corporate ticket booking is for, when its travellers were picked from a corporate
 * account. Indexed only, like users.corporate_account_id: deleting a company leaves the booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('corporate_account_id')->nullable()->index()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropIndex(['corporate_account_id']);
            $table->dropColumn('corporate_account_id');
        });
    }
};
