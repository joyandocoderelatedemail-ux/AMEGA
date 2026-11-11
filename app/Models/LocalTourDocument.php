<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scan filed on a local tour booking: a guest's valid ID, the supplier's confirmation, or proof of
 * the payment sent to the supplier. The file is on the private disk (App\Support\DocumentStorage)
 * and is downloaded only through the desk, by staff who can open the booking.
 */
class LocalTourDocument extends Model
{
    public const KINDS = [
        'guest_id' => "Guest's valid ID",
        'supplier_confirmation' => 'Supplier confirmation or voucher',
        'proof_of_payment' => 'Proof of payment to the supplier',
        'other' => 'Other',
    ];

    protected $fillable = ['local_tour_booking_id', 'kind', 'file_path', 'original_name', 'file_size', 'mime_type', 'uploaded_by'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(LocalTourBooking::class, 'local_tour_booking_id');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? 'Other';
    }
}
