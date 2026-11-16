<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The date the client gave the review. Existing reviews take the day they were added.
     */
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->date('reviewed_on')->nullable()->after('rating');
        });

        DB::table('testimonials')->whereNull('reviewed_on')->update(['reviewed_on' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn('reviewed_on');
        });
    }
};
