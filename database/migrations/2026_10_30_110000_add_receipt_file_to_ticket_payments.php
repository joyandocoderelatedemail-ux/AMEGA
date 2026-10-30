<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The actual receipt the cashier was handed or issued for a payment (a scan or photo), kept on
 * the private documents disk beside the ledger entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_payments', 'receipt_file_path')) {
                $table->string('receipt_file_path')->nullable()->after('note');
                $table->string('receipt_file_name')->nullable()->after('receipt_file_path');
                $table->string('receipt_file_mime', 100)->nullable()->after('receipt_file_name');
                $table->unsignedInteger('receipt_file_size')->nullable()->after('receipt_file_mime');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_payments', function (Blueprint $table) {
            $table->dropColumn(['receipt_file_path', 'receipt_file_name', 'receipt_file_mime', 'receipt_file_size']);
        });
    }
};
