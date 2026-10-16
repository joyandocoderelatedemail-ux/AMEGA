<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A 13(A) case on a client sheet: conversion to a probationary immigrant visa by
     * marriage, amendment to permanent, or the yearly ACR I-Card renewal. What the BI
     * forms ask beyond the sheet is kept in `details`; the checklist, files and filing
     * dates work as on the other immigration cases.
     */
    public function up(): void
    {
        Schema::create('immigration_petitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('immigration_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->json('details')->nullable();
            $table->json('checklist')->nullable();
            $table->json('documents')->nullable();
            $table->date('filed_on')->nullable();
            $table->date('released_on')->nullable();
            $table->timestamps();

            $table->index(['immigration_client_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immigration_petitions');
    }
};
