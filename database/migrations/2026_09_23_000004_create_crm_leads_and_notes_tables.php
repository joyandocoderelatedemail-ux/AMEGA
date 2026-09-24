<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code')->unique();
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('service_type')->default('custom_tour'); // custom_tour, ready_package, flight_ticket, visa_assistance, srrv, general
            $table->string('source')->default('website'); // website, walk_in, phone, facebook, whatsapp, referral, portal
            $table->string('title');
            $table->string('destination')->nullable();
            $table->date('travel_date')->nullable();
            $table->integer('number_of_pax')->default(1);
            $table->decimal('estimated_value', 12, 2)->default(0);
            $table->string('currency', 3)->default('PHP');
            $table->string('stage')->default('new'); // new, contacted, quoted, won, lost
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type')->nullable(); // Polymorphic model
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index('stage');
            $table->index('service_type');
        });

        Schema::create('crm_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type')->default('note'); // note, call, email, meeting, status_change
            $table->text('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_notes');
        Schema::dropIfExists('crm_leads');
    }
};
