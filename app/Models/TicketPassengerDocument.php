<?php

namespace App\Models;

use App\Support\DocumentStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketPassengerDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_passenger_id',
        'document_type',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(TicketPassenger::class, 'ticket_passenger_id');
    }

    /**
     * Passenger documents sit on the private disk, so this points at the
     * authorised download route rather than a public asset URL.
     */
    public function getFileUrlAttribute(): ?string
    {
        if ($this->file_path && DocumentStorage::disk()->exists($this->file_path)) {
            return route('ticketing.documents.download', $this);
        }

        return null;
    }

    public function getFormattedTypeAttribute(): string
    {
        return match ($this->document_type) {
            'passport_scan' => 'Passport Scan',
            'passport_photo' => 'Passport Photo (2x2)',
            'government_id' => 'Government ID',
            'birth_certificate' => 'Birth Certificate',
            'school_id' => 'School ID',
            'visa_scan' => 'Visa Document',
            'supporting_documents' => 'Supporting Document',
            'exit_clearance' => 'Exit Clearance Certificate',
            'travel_insurance' => 'Travel Insurance Policy',
            'flight_itinerary' => 'Flight Itinerary',
            'hotel_voucher' => 'Hotel Voucher',
            default => ucfirst(str_replace('_', ' ', $this->document_type)),
        };
    }
}
