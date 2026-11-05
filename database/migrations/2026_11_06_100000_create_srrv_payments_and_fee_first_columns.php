<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SRRV cashier: each payment taken on a new SRRV application (no foreign keys: the live tables are MyISAM), the
 * signed agreement and Acknowledgment of Documents on the file, and the remarks on a filed document.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('srrv_payments')) {
            Schema::create('srrv_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('srrv_application_id')->index();
                $table->decimal('amount', 12, 2);
                $table->string('method', 30);
                $table->string('reference', 100)->nullable();
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('received_by')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->timestamp('agreement_signed_at')->nullable();
            $table->timestamp('acknowledgement_signed_at')->nullable();
        });

        Schema::table('srrv_application_documents', function (Blueprint $table) {
            $table->string('remarks', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('srrv_payments');

        Schema::table('srrv_applications', function (Blueprint $table) {
            $table->dropColumn(['agreement_signed_at', 'acknowledgement_signed_at']);
        });

        Schema::table('srrv_application_documents', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
