<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each payment the cashier takes on a visa counter file (as ticket payments are kept): how much, how, the OR or
 * reference, who received it and when. No foreign keys: the live tables are MyISAM, so the code keeps them tidy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visa_payments')) {
            return;
        }

        Schema::create('visa_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visa_application_id')->index();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_payments');
    }
};
