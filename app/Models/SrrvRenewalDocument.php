<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file on an SRRV ID renewal's Step 3 Documentation: the SRRV ID, its
 * photocopy, the filled online form, and anything else the desk files.
 */
class SrrvRenewalDocument extends Model
{
    /** What the desk may file on a renewal. */
    public const DOCUMENT_TYPES = [
        'id_card',
        'id_photocopy',
        'online_form',
        'signed_form',
        'pra_reply',
        'new_id_card',
        'signed_acknowledgement',
        'other',
    ];

    /** What each document type is called at the desk. */
    public const TYPE_LABELS = [
        'id_card' => 'SRRV ID card',
        'id_photocopy' => 'SRRV ID photocopy',
        'online_form' => 'Online form (filled)',
        'signed_form' => 'Signed form',
        'pra_reply' => 'PRA reply',
        'new_id_card' => 'New ID card (scan)',
        'signed_acknowledgement' => 'Signed acknowledgment',
        'other' => 'Other',
    ];

    protected $fillable = [
        'srrv_renewal_id',
        'document_type',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'srrv_renewal_id' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function renewal(): BelongsTo
    {
        return $this->belongsTo(SrrvRenewal::class, 'srrv_renewal_id');
    }
}
