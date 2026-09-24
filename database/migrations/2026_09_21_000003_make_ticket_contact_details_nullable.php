<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A quotation is often raised before the client has given an email or phone
 * number — only a name to address it to. Validation still requires both for a
 * real booking; the columns simply no longer force them at the schema level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->change();
            $table->string('contact_phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->string('contact_email')->nullable(false)->change();
            $table->string('contact_phone')->nullable(false)->change();
        });
    }
};
