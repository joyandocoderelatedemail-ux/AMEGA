<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** What the counter charged for a case, typed in by hand on its form. */
    private const TABLES = ['immigration_overstays', 'immigration_restampings', 'immigration_petitions'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->decimal('fee', 10, 2)->nullable()->after('filed_on');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('fee');
            });
        }
    }
};
