<?php

namespace App\Services;

use App\Models\TicketBooking;
use App\Models\TicketPayment;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use App\Support\DocumentStorage;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Records money received against a ticket booking: the cashier's job once an
 * admin has approved the booking (admins can record it too). One place for the
 * rules, the ledger entry, the client's acknowledgement and the audit trail.
 */
class TicketPaymentRecorder
{
    /**
     * Validation for a payment or refund entry.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'method' => ['required', Rule::in(array_keys(TicketPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
            'received_at' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            // The actual receipt, scanned or photographed (optional; it can be attached later).
            'receipt_file' => 'nullable|'.ClientProfileService::SCAN_RULE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'amount.required' => 'Enter the amount received.',
            'amount.min' => 'Enter an amount greater than zero.',
            'method.required' => 'Choose how the payment was made.',
            'receipt_file.mimes' => 'Attach the receipt as a JPG, PNG, WEBP or PDF file.',
            'receipt_file.max' => 'The receipt file is larger than 5 MB.',
            'receipt_file.uploaded' => 'The receipt could not be uploaded. Check that it is under 5 MB and try again.',
        ];
    }

    /**
     * The day the money changed hands: the date typed (with the time now), or now.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function receivedAt(array $validated): ?Carbon
    {
        return filled($validated['received_at'] ?? null)
            ? Carbon::parse($validated['received_at'])->setTimeFrom(now())
            : null;
    }

    /**
     * What had been received once this entry was in, and the balance left.
     *
     * @return array{0: float, 1: float}
     */
    public static function receiptFigures(TicketBooking $ticket, TicketPayment $payment): array
    {
        $receivedToDate = 0.0;
        foreach ($ticket->payments as $entry) {
            $receivedToDate += $entry->isRefund() ? -(float) $entry->amount : (float) $entry->amount;
            if ($entry->is($payment)) {
                break;
            }
        }

        return [$receivedToDate, max(0, round((float) $ticket->total_amount - $receivedToDate, 2))];
    }

    /**
     * Why a payment cannot be taken on this booking right now, or null.
     */
    public static function blockedReason(TicketBooking $ticket): ?string
    {
        return match (true) {
            $ticket->isCancelled() => 'Payment cannot be recorded against a cancelled booking.',
            $ticket->isIssued() => 'This ticket has already been issued; its payment can no longer be changed.',
            $ticket->isAwaitingApproval() => 'This booking is waiting for admin approval. Payment is taken once it is approved.',
            $ticket->isRejected() => 'This booking was returned to the agent. Payment is taken once it is corrected and approved.',
            (float) $ticket->total_amount <= 0 => 'This booking has no total amount yet, so payment cannot be recorded against it.',
            default => null,
        };
    }

    /**
     * Record the payment, tell the client, and log it.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws DomainException when the amount is more than the balance
     */
    public function record(TicketBooking $ticket, array $validated, User $receivedBy, ?UploadedFile $receiptFile = null): TicketPayment
    {
        $payment = $ticket->receivePayment(
            (float) $validated['amount'],
            $validated['method'],
            $receivedBy,
            $validated['reference'] ?? null,
            self::receivedAt($validated),
            $validated['note'] ?? null,
        );

        if ($receiptFile) {
            self::attachReceiptFile($ticket, $payment, $receiptFile);
        }

        ClientNotifier::send($ticket->contact_email, $ticket->contact_name, new PaymentReceivedNotification(
            'ticket booking', $ticket->booking_reference, (string) $ticket->contact_name, 'PHP',
            (float) $payment->amount, (float) $ticket->amount_paid, $ticket->balanceDue(),
        ));

        ActivityLogger::log(
            'Ticketing',
            'PAYMENT',
            "Received {$payment->amount} by {$payment->methodLabel()} ({$payment->receiptNumber()}) on {$ticket->booking_reference}; total paid {$ticket->amount_paid} (status: {$ticket->payment_status})"
        );

        return $payment;
    }

    /**
     * Keep the actual receipt with the entry, on the private documents disk; a new file replaces
     * the one before it.
     */
    public static function attachReceiptFile(TicketBooking $ticket, TicketPayment $payment, UploadedFile $file): void
    {
        $previous = $payment->receipt_file_path;

        $payment->forceFill([
            'receipt_file_path' => $file->store("tickets/{$ticket->booking_reference}/payments", DocumentStorage::diskName()),
            'receipt_file_name' => $file->getClientOriginalName(),
            'receipt_file_mime' => $file->getClientMimeType(),
            'receipt_file_size' => $file->getSize(),
        ])->save();

        if ($previous) {
            DocumentStorage::disk()->delete($previous);
        }

        ActivityLogger::log('Ticketing', 'UPLOAD_RECEIPT', "Attached the receipt {$payment->receipt_file_name} to {$payment->receiptNumber()} on {$ticket->booking_reference}");
    }
}
