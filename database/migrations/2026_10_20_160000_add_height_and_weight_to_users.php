<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A client's height and weight, kept on the client so every new folder or form
     * starts with them. Clients whose latest immigration sheet has them start with those.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('height', 50)->nullable()->after('civil_status');
            $table->string('weight', 50)->nullable()->after('height');
        });

        DB::table('immigration_clients')
            ->whereNotNull('user_id')
            ->where(fn ($query) => $query->whereNotNull('height')->orWhereNotNull('weight'))
            ->orderBy('id')
            ->get(['user_id', 'height', 'weight'])
            ->each(function ($sheet): void {
                foreach (['height', 'weight'] as $field) {
                    if (filled($sheet->{$field})) {
                        DB::table('users')->where('id', $sheet->user_id)->update([$field => mb_substr(trim($sheet->{$field}), 0, 50)]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['height', 'weight']);
        });
    }
};
