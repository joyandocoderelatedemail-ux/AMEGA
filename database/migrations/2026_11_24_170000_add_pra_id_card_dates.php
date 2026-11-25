<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** What the PRA issues at the end of an application (PRA Submission step 4), kept on the file and the client. */
    public function up(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->date('srrv_issued_on')->nullable()->after('srrv_card_number');
            $table->date('pra_id_valid_until')->nullable()->after('srrv_issued_on');
        });

        Schema::table('users', function (Blueprint $table) {
            // Listed on Expiring Documents (App\Support\ClientExpiries) like the passport and ACR I-Card.
            $table->date('pra_id_valid_until')->nullable()->after('srrv_issued_on');
        });
    }

    public function down(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->dropColumn(['srrv_issued_on', 'pra_id_valid_until']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pra_id_valid_until');
        });
    }
};
