<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Re Stamping / Amendment case on a client sheet: what the BI's IRD Consolidated
     * General Application Form (IRD01.QF.006) asks beyond the sheet, the documentary
     * checklists for transfer and for amendment of admission, and filing and release.
     */
    public function up(): void
    {
        Schema::create('immigration_restampings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immigration_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('applications')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('old_passport_number')->nullable();
            $table->string('landline')->nullable();
            $table->date('arrival_date')->nullable();
            $table->string('flight_number')->nullable();
            $table->string('bibs_number')->nullable();
            $table->string('admission_status')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('passport_lost')->default(false);
            $table->boolean('balikbayan')->default(false);
            $table->boolean('with_filipino_spouse')->default(false);
            $table->json('checklist')->nullable();
            $table->json('documents')->nullable();
            $table->date('filed_on')->nullable();
            $table->date('released_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immigration_restampings');
    }
};
