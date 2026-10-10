<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who confirmed, before issuing, that the booking's information is correct
 * and accepted accountability for it, and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->foreignId('agent_acknowledged_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('agent_acknowledged_at')->nullable()->after('agent_acknowledged_by');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_acknowledged_by');
            $table->dropColumn('agent_acknowledged_at');
        });
    }
};
