<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One overstay case per lapse: what the BI's Request for Reconsideration on the
     * Updating and Extension of Authorized Stay asks, the documentary checklist, and
     * when it was filed and released. The client sheet keeps the client and the ledger.
     */
    public function up(): void
    {
        Schema::create('immigration_overstays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immigration_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expired_on')->nullable();
            $table->text('reason_failed')->nullable();
            $table->text('reason_stay')->nullable();
            $table->date('departure_date')->nullable();
            $table->string('destination')->nullable();
            $table->boolean('by_representative')->default(false);
            $table->string('representative_name')->nullable();
            $table->string('representative_accreditation_no')->nullable();
            $table->string('representative_relationship')->nullable();
            $table->json('checklist')->nullable();
            $table->json('documents')->nullable();
            $table->date('filed_on')->nullable();
            $table->date('released_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immigration_overstays');
    }
};
