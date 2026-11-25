<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** When the filled fingerprint card (PRA-SD-FORM-0016) was first printed: Step 4 opens Step 5 only after it. */
    public function up(): void
    {
        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->timestamp('card_printed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->dropColumn('card_printed_at');
        });
    }
};
