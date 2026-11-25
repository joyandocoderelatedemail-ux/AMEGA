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
        Schema::table('srrv_renewals', function (Blueprint $table) {
            if (! Schema::hasColumn('srrv_renewals', 'submitted_at')) {
                $table->date('submitted_at')->nullable()->after('form_filled_online');
            }
            if (! Schema::hasColumn('srrv_renewals', 'submitted_remarks')) {
                $table->string('submitted_remarks', 500)->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('srrv_renewals', 'card_expiry_date')) {
                $table->date('card_expiry_date')->nullable()->after('submitted_remarks');
            }
            if (! Schema::hasColumn('srrv_renewals', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('collected_at');
            }
        });

        if (Schema::hasTable('srrv_renewal_notes')) {
            return;
        }

        Schema::create('srrv_renewal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('srrv_renewal_id')->constrained('srrv_renewals')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('srrv_renewal_notes');

        Schema::table('srrv_renewals', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'submitted_remarks', 'card_expiry_date', 'closed_at']);
        });
    }
};
