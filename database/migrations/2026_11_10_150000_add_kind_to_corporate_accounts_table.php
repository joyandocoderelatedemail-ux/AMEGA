<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client group (a family, a barkada, a tour party) is kept like a company: a name, a leader and
 * its members, picked together when booking. `kind` tells the two apart. A group has no credit
 * account and its bookings carry no corporate service tax.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('corporate_accounts', function (Blueprint $table) {
            $table->string('kind', 20)->default('corporate')->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('corporate_accounts', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
