<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who opened each immigration client folder, so the counter's folders are private to their agent like
 * every other desk's files. Older folders take the agent of their earliest case where one is known;
 * the rest stay unowned and visible to the whole counter until an admin hands them to someone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('immigration_clients', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        foreach (['immigration_overstays', 'immigration_restampings', 'immigration_petitions'] as $cases) {
            $first = DB::table($cases)->whereNotNull('created_by')
                ->select('immigration_client_id', DB::raw('MIN(id) as first_id'))->groupBy('immigration_client_id');

            foreach (DB::table($cases)->joinSub($first, 'firsts', fn ($join) => $join->on("{$cases}.id", '=', 'firsts.first_id'))
                ->get(["{$cases}.immigration_client_id", "{$cases}.created_by"]) as $row) {
                DB::table('immigration_clients')->where('id', $row->immigration_client_id)->whereNull('created_by')
                    ->update(['created_by' => $row->created_by]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('immigration_clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
