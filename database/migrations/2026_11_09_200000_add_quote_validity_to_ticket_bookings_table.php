<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            // How long a ticketing quotation's price holds; the agent who made it is told as it runs out.
            $table->date('quote_valid_until')->nullable();
            // The morning email to the agent went out (once per end date).
            $table->timestamp('quote_expiry_notified_at')->nullable();
            // The agent opened the quotation after it was flagged in their bell.
            $table->timestamp('quote_expiry_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropColumn(['quote_valid_until', 'quote_expiry_notified_at', 'quote_expiry_seen_at']);
        });
    }
};
