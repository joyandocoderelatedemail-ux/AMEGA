<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A client's civil status, kept on the client so every form that asks for it can
     * pull it. Clients whose immigration sheet already has one start with that.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('civil_status', 20)->nullable()->after('gender');
        });

        $valid = ['single', 'married', 'annulled', 'separated', 'widowed', 'divorced'];

        DB::table('immigration_clients')
            ->whereNotNull('user_id')->whereNotNull('civil_status')
            ->orderBy('id')
            ->get(['user_id', 'civil_status'])
            ->each(function ($sheet) use ($valid): void {
                $status = strtolower(trim((string) $sheet->civil_status));

                if (in_array($status, $valid, true)) {
                    DB::table('users')->where('id', $sheet->user_id)->whereNull('civil_status')->update(['civil_status' => $status]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('civil_status');
        });
    }
};
