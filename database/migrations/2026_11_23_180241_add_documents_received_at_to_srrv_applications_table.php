<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->timestamp('documents_received_at')->nullable()->after('pra_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->dropColumn('documents_received_at');
        });
    }
};
