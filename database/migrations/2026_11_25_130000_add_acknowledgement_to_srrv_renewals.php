<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** When the retiree signed the printed Acknowledgment of Documents (its scan is a renewal document). */
    public function up(): void
    {
        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->timestamp('acknowledgement_signed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->dropColumn('acknowledgement_signed_at');
        });
    }
};
