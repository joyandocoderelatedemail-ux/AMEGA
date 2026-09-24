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
