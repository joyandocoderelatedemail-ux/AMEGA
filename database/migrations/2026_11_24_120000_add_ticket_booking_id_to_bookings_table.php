<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A website package booking booked through the Ticketing desk points at its ticket. */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // No foreign key: ticket_bookings may be MyISAM, which refuses one from this InnoDB table
            // (tickets are never deleted in the app, so the link cannot dangle).
            $table->unsignedBigInteger('ticket_booking_id')->nullable()->index()->after('travel_package_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('ticket_booking_id');
        });
    }
};
