<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional extras kept on a client's profile: a frequent flyer membership,
 * the entry stamps that foreign nationals carry, and remarks on the ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('frequent_flyer_membership')->nullable()->after('passport_photo');
            $table->text('stamps')->nullable()->after('frequent_flyer_membership');
            $table->string('arrival_stamp_exception')->nullable()->after('stamps');
            $table->text('government_id_remarks')->nullable()->after('government_id_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['frequent_flyer_membership', 'stamps', 'arrival_stamp_exception', 'government_id_remarks']);
        });
    }
};
