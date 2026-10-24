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

    /** The PRA requirements (SrrvApplication::REQUIREMENTS), then anything else the desk files. */
    public const DOCUMENT_TYPES = [
        'passport',
        'application_form',
        'medical_certificate',
        'police_clearance',
        'bi_clearance',
        'nbi_clearance',
        'visa_deposit',
        'relationship_proof',
        'id_photos',
        'pension_proof',
        'employment_certificate',
        'military_service_proof',
        'former_filipino_proof',
        'pra_checklist',
        'srrv_card',
        'valid_id',
        'form',
        'other',
    ];

    /** What each document type is called at the desk. */
    public const TYPE_LABELS = [
        'passport' => 'Passport',
        'application_form' => 'PRA application form',
        'medical_certificate' => 'PRA medical certificate',
        'police_clearance' => 'Police clearance',
        'bi_clearance' => 'BI Clearance Certificate',
        'nbi_clearance' => 'NBI clearance',
        'visa_deposit' => 'Certificate of visa deposit',
        'relationship_proof' => 'Proof of relationship',
        'id_photos' => 'ID photos',
        'pension_proof' => 'Proof of pension',
        'employment_certificate' => 'Certificate of employment',
        'military_service_proof' => 'Proof of military service',
        'former_filipino_proof' => 'Proof of former Filipino citizenship',
        'pra_checklist' => 'PRA checklist (filled)',
        'srrv_card' => 'SRRV card',
        'valid_id' => 'Other valid ID',
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
