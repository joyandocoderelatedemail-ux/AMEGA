<?php

namespace App\Models\Concerns;

/**
 * How the client pays for a counter case: in full, a downpayment, in terms, or nothing yet.
 * The fee is typed in; what has come in is recorded beside it, so the balance can be shown.
 */
trait HasPaymentOption
{
    public const PAYMENT_TYPES = [
        'full' => 'Full payment',
        'downpayment' => 'Downpayment',
        'terms' => 'Terms',
        'none' => 'No payment',
    ];

    public function paymentLabel(): ?string
    {
        return self::PAYMENT_TYPES[$this->payment_type] ?? null;
    }

    /** What is still owed, or null when no fee was entered. */
    public function paymentBalance(): ?float
    {
        return $this->fee === null ? null : max(0.0, (float) $this->fee - (float) $this->amount_paid);
    }

    /**
     * The payment columns for what the form sent: full takes the whole fee, no payment takes
     * nothing, a downpayment and terms keep the amount typed (and the number of terms).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function paymentFrom(array $data): array
    {
        $type = $data['payment_type'] ?? null;

        if (! array_key_exists((string) $type, self::PAYMENT_TYPES)) {
            return ['payment_type' => null, 'amount_paid' => null, 'terms_count' => null];
        }

        return match ($type) {
            'full' => ['payment_type' => $type, 'amount_paid' => $data['fee'] ?? 0, 'terms_count' => null],
            'none' => ['payment_type' => $type, 'amount_paid' => 0, 'terms_count' => null],
            'downpayment' => ['payment_type' => $type, 'amount_paid' => $data['amount_paid'] ?? 0, 'terms_count' => null],
            default => ['payment_type' => $type, 'amount_paid' => $data['amount_paid'] ?? 0, 'terms_count' => $data['terms_count'] ?? null],
        };
    }
}
