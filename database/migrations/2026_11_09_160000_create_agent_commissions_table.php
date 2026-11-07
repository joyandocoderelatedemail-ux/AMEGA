<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_commissions', function (Blueprint $table) {
            $table->id();
            // The file the admin approved: its desk, its kind within the desk (a ticket, a case type...) and its id.
            $table->string('desk', 30);
            $table->string('kind', 40);
            $table->unsignedBigInteger('file_id');
            // The agent who sent the file, and what the admin decided they earn on it (pesos).
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            // Kept so a commission still reads on its own if the file is later renamed or removed.
            $table->string('reference')->nullable();
            $table->string('client_name')->nullable();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['desk', 'kind', 'file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commissions');
    }
};
