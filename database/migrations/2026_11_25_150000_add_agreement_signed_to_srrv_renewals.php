<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** When the client signed the SRRV Assistance Booking Agreement: a renewal is not recorded without it. */
    public function up(): void
    {
        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->timestamp('agreement_signed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->dropColumn('agreement_signed_at');
        });
    }
};
