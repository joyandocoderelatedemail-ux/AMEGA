<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ACR I-Card, CRTV and Annual Report steps now take a scan of the document
     * (kept on the private documents disk) alongside the date it was paid.
     */
    public function up(): void
    {
        Schema::table('immigration_client_documents', function (Blueprint $table) {
            $table->string('file_path')->nullable()->after('validity');
            $table->string('original_name')->nullable()->after('file_path');
            $table->unsignedBigInteger('file_size')->nullable()->after('original_name');
            $table->string('mime_type')->nullable()->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('immigration_client_documents', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'original_name', 'file_size', 'mime_type']);
        });
    }
};
