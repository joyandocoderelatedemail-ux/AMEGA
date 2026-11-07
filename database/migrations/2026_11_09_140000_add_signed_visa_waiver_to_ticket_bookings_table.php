<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            // The client signed the Visa Waiver (a passenger booked before the visa was approved):
            // when it was confirmed, by whom, and the signed copy that was uploaded.
            $table->timestamp('visa_waiver_signed_at')->nullable();
            $table->foreignId('visa_waiver_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visa_waiver_path')->nullable();
            $table->string('visa_waiver_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visa_waiver_signed_by');
            $table->dropColumn(['visa_waiver_signed_at', 'visa_waiver_path', 'visa_waiver_name']);
        });
    }
};
