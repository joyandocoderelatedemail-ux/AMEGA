<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "Visa Extension Information" ledger on the Client Information Sheet:
 * ten numbered rows carrying SOA/OR number, date, details, amount paid,
 * annual report, and refund.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('immigration_client_extensions', function (Blueprint $table) {
            $table->id();
            // Constraints are named explicitly: the generated names overflow MySQL's
            // 64-character identifier limit once the table prefix is applied.
            $table->foreignId('immigration_client_id')
                ->constrained(indexName: 'ice_client_id_foreign')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('soa_or_number')->nullable();
            $table->date('extension_date')->nullable();
            $table->text('details')->nullable();
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->string('annual_report')->nullable();
            $table->decimal('refund', 10, 2)->nullable();

            // Links a completed row back to the price row it was quoted from, when known
            $table->foreignId('immigration_pricing_tier_id')
                ->nullable()
                ->constrained(indexName: 'ice_pricing_tier_id_foreign')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['immigration_client_id', 'sequence'], 'client_extension_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immigration_client_extensions');
    }
};
