<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Follow-up, as on inquiries: has staff contacted the customer, when, by whom, and what was said.
            $table->timestamp('contacted_at')->nullable()->after('payment_status');
            $table->foreignId('contacted_by')->nullable()->after('contacted_at')->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable()->after('contacted_by');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contacted_by');
            $table->dropColumn(['contacted_at', 'remarks']);
        });
    }
};
