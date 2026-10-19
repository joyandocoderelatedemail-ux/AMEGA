<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the PRA forms ask for beyond what the desk works the file with: the
     * application form's personal, family and history details, and the Retiree
     * Request Form's addresses and income. Kept as one JSON bag per file.
     */
    public function up(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->json('form_details')->nullable()->after('remarks');
        });

        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->json('form_details')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->dropColumn('form_details');
        });

        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->dropColumn('form_details');
        });
    }
};
