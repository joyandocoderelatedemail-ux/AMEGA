<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When staff last emailed the client that each stage was done, keyed by
     * stage, so the file shows who has already been told what.
     */
    public function up(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->json('stage_notified_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            $table->dropColumn('stage_notified_at');
        });
    }
};
