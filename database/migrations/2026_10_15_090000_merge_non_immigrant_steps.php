<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Non-Immigrant flow went from nine steps to six: Agreement and Payment are one
     * step, Receiving Documents and the Acknowledgment are one, and Application Result
     * and Release are one. Open files move onto the merged step; nothing recorded is lost.
     */
    public function up(): void
    {
        $move = fn (string $from, string $to) => DB::table('visa_applications')
            ->where('service_type', 'visit_visa')
            ->where('status', $from)
            ->update(['status' => $to]);

        $move('payment', 'agreement');
        $move('acknowledged', 'requirements');
    }

    public function down(): void
    {
        // The merged steps keep everything the old ones recorded; there is nothing to undo.
    }
};
