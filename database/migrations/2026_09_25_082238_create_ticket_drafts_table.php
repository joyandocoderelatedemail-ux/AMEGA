<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tickets saved as pending in the booking wizard: the whole form, kept on
     * the server so staff can continue later from any computer.
     */
    public function up(): void
    {
        Schema::create('ticket_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->string('summary')->nullable();
            $table->unsignedTinyInteger('step')->default(1);
            $table->longText('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_drafts');
    }
};
