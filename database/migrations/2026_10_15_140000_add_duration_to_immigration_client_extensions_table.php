<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How long each extension on the ledger runs ("29 days", "1 month", "2 months"),
     * so saving a new one can move the client's visa expiry forward by that much.
     */
    public function up(): void
    {
        Schema::table('immigration_client_extensions', function (Blueprint $table) {
            $table->string('duration', 20)->nullable()->after('details');
        });
    }

    public function down(): void
    {
        Schema::table('immigration_client_extensions', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
};
