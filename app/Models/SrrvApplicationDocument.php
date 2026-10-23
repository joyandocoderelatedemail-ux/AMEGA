<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A document on an SRRV file: the standard PRA checklist plus whichever
 * class-specific proof the file depends on.
 */
class SrrvApplicationDocument extends Model
{
    use HasFactory;

    public const DOCUMENT_TYPES = [
        'pra_checklist',
        'police_clearance',
        'pension_proof',
        'military_service_proof',
        'srrv_card',
        'valid_id',
        'form',
        'other',
    ];

    /** What each document type is called at the desk. */
    public const TYPE_LABELS = [
        'pra_checklist' => 'Standard PRA checklist',
        'police_clearance' => 'Police clearance',
        'pension_proof' => 'Proof of pension',
        'military_service_proof' => 'Proof of military service',
        'srrv_card' => 'SRRV card',
        'valid_id' => 'Passport / valid ID',
        'form' => 'Signed PRA form',
        'other' => 'Other',
    ];

    public const STATUSES = ['uploaded', 'pending', 'missing'];

    protected $fillable = [
        'srrv_application_id',
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
            'srrv_application_id' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(SrrvApplication::class, 'srrv_application_id');
    }
}
