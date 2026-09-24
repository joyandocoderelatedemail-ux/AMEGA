<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a booking that was saved purely to produce a quotation.
 *
 * Staff often need to quote a price before the client has gathered any travel
 * documents. Such a booking skips the document checks at creation, so it must
 * not be issuable — the flag is what keeps an undocumented quotation from
 * turning into a real ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->boolean('is_quotation')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropColumn('is_quotation');
        });
    }
};
