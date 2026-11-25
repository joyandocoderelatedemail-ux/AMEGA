<?php

namespace App\Services;

use App\Models\SrrvApplication;
use App\Models\SrrvPayment;
use App\Models\SrrvRenewal;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use DomainException;
use Illuminate\Validation\Rule;

/**
 * Records money received on a new SRRV application or an ID renewal: the cashier's job once an admin has approved the
 * file (admins can record it too). One place for the rules, the payment row, the file's totals, the client's email and
 * the audit trail.
 */
class SrrvPaymentRecorder
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            // ID renewals collect the PRA fee (USD) and the service fee (PHP) separately.
            'apply_to' => ['nullable', Rule::in(['fee', 'service'])],
            'method' => ['required', Rule::in(array_keys(SrrvPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'amount.required' => 'Enter the amount received.',
            'amount.min' => 'Enter an amount greater than zero.',
            'method.required' => 'Choose how the payment was made.',
        ];
    }

    /** Why a payment cannot be taken on this file now, or null. */
    public static function blockedReason(SrrvApplication|SrrvRenewal $file): ?string
    {
        $noFee = $file instanceof SrrvRenewal
            ? ($file->fee_amount <= 0 && $file->serviceDue() <= 0)
            : (float) $file->service_fee <= 0;

        return match (true) {
            $file->status === 'cancelled' => 'Payment cannot be recorded against a cancelled file.',
            $file->isAwaitingApproval() => 'This file is waiting for admin approval. Payment is taken once it is approved.',
            // A renewal is sent to the admin when it is saved; one without a status predates approvals and is cleared.
            ! $file instanceof SrrvRenewal && $file->approval_status === null => 'This file has not been sent to the admin yet. Payment is taken once it is approved.',
            $file->isReturned() => 'This file was returned to the agent. Payment is taken once it is corrected and approved.',
            $noFee => 'This file has no service fee yet, so payment cannot be recorded against it.',
            $file->outstandingBalance() <= 0 && ! ($file instanceof SrrvRenewal) => 'This file is paid in full.',
            $file instanceof SrrvRenewal && $file->isFullyPaid() => 'This renewal is paid in full: the PRA fee (USD) and the service fee (PHP) are both settled.',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws DomainException when the amount is more than the balance
     */
    public function record(SrrvApplication|SrrvRenewal $file, array $validated, User $receivedBy): SrrvPayment
    {
        $amount = round((float) $validated['amount'], 2);

        if ($file instanceof SrrvRenewal) {
            $applyTo = $validated['apply_to'] ?? null;

            if (! in_array($applyTo, ['fee', 'service'], true)) {
                throw new DomainException('Choose whether this payment is for the PRA fee (USD) or the service fee (PHP).');
            }

            $isFee = $applyTo === 'fee';
            $balance = $isFee ? $file->feeBalance() : $file->serviceBalance();
            $currency = $isFee ? SrrvRenewal::FEE_CURRENCY : ($file->service_currency ?: SrrvRenewal::SERVICE_CURRENCY);

            if ($amount > $balance) {
                throw new DomainException('The amount is more than the '.($isFee ? 'PRA fee' : 'service fee').' balance of '.$currency.' '.number_format($balance, 2).'.');
            }

            $payment = SrrvPayment::create([
                'srrv_renewal_id' => $file->id,
                'amount' => $amount,
                'currency' => $currency,
                'method' => $validated['method'],
                'reference' => $validated['reference'] ?? null,
                'note' => $validated['note'] ?? null,
                'received_by' => $receivedBy->id,
                'received_at' => now(),
            ]);

            $file->forceFill($isFee
                ? ['fee_paid' => round((float) $file->fee_paid + $amount, 2)]
                : ['amount_paid' => round((float) $file->amount_paid + $amount, 2)]);
            $file->save();

            $paid = $isFee ? (float) $file->fee_paid : (float) $file->amount_paid;
            $left = $isFee ? $file->feeBalance() : $file->serviceBalance();

            ActivityLogger::log('Cashier', 'SRRV_PAYMENT', "Received {$currency} ".number_format($amount, 2)." by {$payment->methodLabel()}".($payment->reference ? " ({$payment->reference})" : '')." on {$file->reference} (".($isFee ? 'PRA fee' : 'service fee').'); total paid '.number_format($paid, 2));

            ClientNotifier::send($file->retiree_email, $file->retiree_name, new PaymentReceivedNotification(
                'SRRV ID renewal', $file->reference, (string) $file->retiree_name, $currency, $amount, $paid, $left,
            ));

            return $payment;
        }

        $balance = $file->outstandingBalance();

        if ($amount > $balance) {
            throw new DomainException('The amount is more than the balance of '.$file->currency.' '.number_format($balance, 2).'.');
        }

        $payment = SrrvPayment::create([
            'srrv_application_id' => $file->id,
            'amount' => $amount,
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'note' => $validated['note'] ?? null,
            'received_by' => $receivedBy->id,
            'received_at' => now(),
        ]);

        $file->forceFill(['amount_paid' => round((float) $file->amount_paid + $amount, 2)]);

        if ($file->isFullyPaid()) {
            $file->payment_in_full_at = now();
        }

        $file->save();

        ActivityLogger::log('Cashier', 'SRRV_PAYMENT', "Received {$file->currency} ".number_format($amount, 2)." by {$payment->methodLabel()}".($payment->reference ? " ({$payment->reference})" : '')." on {$file->reference}; total paid ".number_format((float) $file->amount_paid, 2));

        ClientNotifier::send($file->retiree_email, $file->retiree_name, new PaymentReceivedNotification(
            'SRRV application', $file->reference, (string) $file->retiree_name, (string) $file->currency, $amount, (float) $file->amount_paid, $file->outstandingBalance(),
        ));

        return $payment;
    }
}
