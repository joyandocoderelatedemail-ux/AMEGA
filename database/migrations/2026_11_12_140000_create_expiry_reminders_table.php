<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every reminder a client was sent about a document running out (visa, passport, ACR I-Card): by a
 * staff member from Expiring Documents (sent_by), or by the daily one-month job (sent_by null).
 * doc_key + expires_on keeps the automatic one to once per document date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expiry_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('doc_key', 60);
            $table->string('document', 30);
            $table->date('expires_on');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('immigration_client_id')->nullable()->constrained('immigration_clients')->nullOnDelete();
            $table->string('channel', 20);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['doc_key', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expiry_reminders');
    }
};
