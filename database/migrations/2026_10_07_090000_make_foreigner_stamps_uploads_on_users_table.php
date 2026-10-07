<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A foreign national's passport stamps are kept as uploaded scans (the page
 * with the multiple stamps, and the exception / arrival stamp) instead of the
 * free-text notes the previous migration added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['stamps', 'arrival_stamp_exception']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('stamps_photo')->nullable()->after('frequent_flyer_membership');
            $table->string('arrival_stamp_photo')->nullable()->after('stamps_photo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['stamps_photo', 'arrival_stamp_photo']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('stamps')->nullable()->after('frequent_flyer_membership');
            $table->string('arrival_stamp_exception')->nullable()->after('stamps');
        });
    }
};
