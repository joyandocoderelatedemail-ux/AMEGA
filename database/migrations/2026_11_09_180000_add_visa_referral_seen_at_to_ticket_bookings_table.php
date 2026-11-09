<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            // When the Visa Assistance desk opened the Ticketing referral (visa or passport help) for this booking.
            $table->timestamp('visa_referral_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropColumn('visa_referral_seen_at');
        });
    }
};
