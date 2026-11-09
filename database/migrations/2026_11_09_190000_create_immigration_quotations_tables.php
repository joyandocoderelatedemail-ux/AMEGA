<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A price quoted at the Immigration counter before any file is opened.
        Schema::create('immigration_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number')->nullable()->unique();
            // Who it is for: the client's folder, a registered client without one, or just a name (walk-in).
            $table->foreignId('immigration_client_id')->nullable()->constrained('immigration_clients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone', 50)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('passport_number', 50)->nullable();
            $table->date('valid_until');
            $table->string('status', 20)->default('open');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'valid_until']);
        });

        Schema::create('immigration_quotation_items', function (Blueprint $table) {
            $table->id();
            // A short constraint name: with the table prefix the default one passes MySQL's 64-character limit.
            $table->foreignId('immigration_quotation_id')->constrained('immigration_quotations', 'id', 'iq_items_quotation_fk')->cascadeOnDelete();
            // The counter service the line is for (App\Support\ImmigrationServices), or null for anything else.
            $table->string('service_key', 50)->nullable();
            $table->string('description');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immigration_quotation_items');
        Schema::dropIfExists('immigration_quotations');
    }
};
