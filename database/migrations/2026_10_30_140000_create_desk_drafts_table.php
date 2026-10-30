<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forms saved part-way at the Immigration, Visa Assistance and SRRV desks ("Save as Pending"):
     * what was typed, and the form it goes back into. Not a file yet, and nothing is sent for approval.
     */
    public function up(): void
    {
        Schema::create('desk_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('desk', 20);
            $table->string('title');
            $table->string('client_name')->nullable();
            $table->text('form_url');
            $table->longText('payload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['desk', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desk_drafts');
    }
};
