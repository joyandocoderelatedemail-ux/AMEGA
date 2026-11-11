<?php

namespace App\Services;

use App\Models\LocalTourBooking;
use App\Models\LocalTourPayment;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use DomainException;
use Illuminate\Validation\Rule;

/**
 * Records money received on a local tour booking: the cashier's job once an approver has approved it
 * (admins can record it too). One place for the rules, the payment row, the booking's totals, the
 * client's email and the audit trail.
 */
class LocalTourPaymentRecorder
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'method' => ['required', Rule::in(array_keys(LocalTourPayment::METHODS))],
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

    /** Why a payment cannot be taken on this booking now, or null. */
    public static function blockedReason(LocalTourBooking $booking): ?string
    {
        return match (true) {
            $booking->status === 'cancelled' => 'Payment cannot be recorded against a cancelled booking.',
            $booking->isAwaitingApproval() => 'This booking is waiting for approval. Payment is taken once it is approved.',
            $booking->approval_status === null => 'This booking has not been sent for approval yet. Payment is taken once it is approved.',
            $booking->isReturned() => 'This booking was returned to the agent. Payment is taken once it is corrected and approved.',
            (float) $booking->total_amount <= 0 => 'This booking has no amount yet, so payment cannot be recorded against it.',
            $booking->outstandingBalance() <= 0 => 'This booking is paid in full.',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws DomainException when the amount is more than the balance
     */
    public function record(LocalTourBooking $booking, array $validated, User $receivedBy): LocalTourPayment
    {
        $amount = round((float) $validated['amount'], 2);
        $balance = $booking->outstandingBalance();

        if ($amount > $balance) {
            throw new DomainException('The amount is more than the balance of ₱'.number_format($balance, 2).'.');
        }

        $payment = LocalTourPayment::create([
            'local_tour_booking_id' => $booking->id,
            'amount' => $amount,
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'note' => $validated['note'] ?? null,
            'received_by' => $receivedBy->id,
            'received_at' => now(),
        ]);

        $booking->amount_paid = round((float) $booking->amount_paid + $amount, 2);
        if ($booking->isFullyPaid()) {
            $booking->payment_in_full_at = now();
        }
        $booking->save();

        ActivityLogger::log('Cashier', 'LOCAL_TOUR_PAYMENT', 'Received ₱'.number_format($amount, 2)." by {$payment->methodLabel()}".($payment->reference ? " ({$payment->reference})" : '')." on {$booking->reference}; total paid ₱".number_format((float) $booking->amount_paid, 2));

        ClientNotifier::send($booking->contact_email, $booking->lead_guest_name, new PaymentReceivedNotification(
            $booking->tourLabel(), $booking->reference, (string) $booking->lead_guest_name, 'PHP', $amount, (float) $booking->amount_paid, $booking->outstandingBalance(),
        ));

        return $payment;
    }
}
