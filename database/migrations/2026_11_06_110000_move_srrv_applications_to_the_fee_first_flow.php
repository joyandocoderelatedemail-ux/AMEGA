<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New SRRV applications now open on Step 2 (agreement and fee, then the admin, then the cashier). Files opened before:
 * one still on the first step moves to Step 2; an amount already paid becomes a payment row (so the cashier's list and
 * the Step 2 check see it), and a file that is already past the documents is marked as having signed them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('srrv_applications')->where('service_type', 'renewal_application')->orderBy('id')->each(function ($file) use ($now) {
            $updates = [];

            if ($file->status === 'pending') {
                $updates['status'] = 'agreement';
            }

            if ((float) $file->amount_paid > 0 || ! in_array($file->status, ['pending', 'agreement'], true)) {
                $updates['agreement_signed_at'] = $file->agreement_signed_at ?? $file->created_at ?? $now;
            }

            if (! in_array($file->status, ['pending', 'agreement', 'requirements'], true)) {
                $updates['acknowledgement_signed_at'] = $file->acknowledgement_signed_at ?? $file->created_at ?? $now;
            }

            if ((float) $file->amount_paid > 0 && ! DB::table('srrv_payments')->where('srrv_application_id', $file->id)->exists()) {
                DB::table('srrv_payments')->insert([
                    'srrv_application_id' => $file->id,
                    'amount' => $file->amount_paid,
                    'method' => 'cash',
                    'note' => 'Recorded before the cashier took SRRV payments',
                    'received_at' => $file->updated_at ?? $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($updates !== []) {
                DB::table('srrv_applications')->where('id', $file->id)->update($updates);
            }
        });
    }

    public function down(): void
    {
        // Nothing to undo: the payment rows and signed dates are records, not structure.
    }
};
