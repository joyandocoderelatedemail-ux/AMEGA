<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every status change on an Immigration case, with the remark the officer gave for it: from what,
 * to what, why, by whom and when. Kept for good, so the file's history can always be read back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('immigration_status_changes', function (Blueprint $table) {
            $table->id();
            // The case, as ImmigrationCases names it: overstay, restamping, spouse, permanent, renewal, dual, downgrade or ecc.
            $table->string('case_kind', 20);
            $table->unsignedBigInteger('case_id');
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('remark');
            $table->date('released_on')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['case_kind', 'case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('immigration_status_changes');
    }
};
