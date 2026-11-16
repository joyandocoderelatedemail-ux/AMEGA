<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only the client's name and the review date are required; location, review text and rating are optional.
     */
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('location')->nullable()->change();
            $table->text('comment')->nullable()->change();
            $table->unsignedTinyInteger('rating')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('location')->nullable(false)->default('')->change();
            $table->text('comment')->nullable(false)->change();
            $table->unsignedTinyInteger('rating')->nullable(false)->default(5)->change();
        });
    }
};
