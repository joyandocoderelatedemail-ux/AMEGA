<?php

namespace App\Services;

use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaPayment;
use App\Notifications\PaymentReceivedNotification;
use DomainException;
use Illuminate\Validation\Rule;

/**
 * Records money received on a visa counter file: the cashier's job once an admin has approved the file (admins can
 * record it too), as for ticket bookings. One place for the rules, the payment row, the file's totals, the client's
 * email and the audit trail.
 */
class VisaPaymentRecorder
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'method' => ['required', Rule::in(array_keys(VisaPayment::METHODS))],
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
    public static function blockedReason(VisaApplication $file): ?string
    {
        return match (true) {
            $file->status === 'cancelled' => 'Payment cannot be recorded against a cancelled file.',
            $file->isAwaitingApproval() => 'This file is waiting for admin approval. Payment is taken once it is approved.',
            $file->isFeeFirst() && $file->approval_status === null => 'This file has not been sent to the admin yet. Payment is taken once it is approved.',
            $file->isReturned() => 'This file was returned to the agent. Payment is taken once it is corrected and approved.',
            (float) $file->total_amount <= 0 => 'This file has no total amount yet, so payment cannot be recorded against it.',
            $file->outstandingBalance() <= 0 => 'This file is paid in full.',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws DomainException when the amount is more than the balance
     */
    public function record(VisaApplication $file, array $validated, User $receivedBy): VisaPayment
    {
        $amount = round((float) $validated['amount'], 2);
        $balance = $file->outstandingBalance();

        if ($amount > $balance) {
            throw new DomainException('The amount is more than the balance of '.$file->currency.' '.number_format($balance, 2).'.');
        }

        $payment = VisaPayment::create([
            'visa_application_id' => $file->id,
            'amount' => $amount,
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'note' => $validated['note'] ?? null,
            'received_by' => $receivedBy->id,
            'received_at' => now(),
        ]);

        $paid = round((float) $file->amount_paid + $amount, 2);
        $file->forceFill([
            'amount_paid' => $paid,
            'payment_type' => $paid >= (float) $file->total_amount ? 'full' : 'deposit',
        ])->save();

        ActivityLogger::log('Cashier', 'VISA_PAYMENT', "Received {$file->currency} ".number_format($amount, 2)." by {$payment->methodLabel()}".($payment->reference ? " ({$payment->reference})" : '')." on {$file->reference}; total paid ".number_format($paid, 2));

        ClientNotifier::send($file->client_email, $file->client_name, new PaymentReceivedNotification(
            match ($file->service_type) {
                'e_visa' => 'e-Visa application',
                'passporting' => 'passport application',
                'citizenship' => 'citizenship claim',
                default => 'visa application',
            },
            $file->reference, (string) $file->client_name, (string) $file->currency, $amount, $paid, $file->outstandingBalance(),
        ));

        return $payment;
    }
}
