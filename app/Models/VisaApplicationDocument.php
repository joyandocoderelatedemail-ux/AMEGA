<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A document on a visa file, tracked so the counter can see what has been
 * handed over and what is still outstanding.
 */
class VisaApplicationDocument extends Model
{
    use HasFactory;

    /** Documents the counter commonly handles. */
    public const DOCUMENT_TYPES = [
        'passport_scan',
        'passport_photo',
        'valid_id',
        'birth_certificate',
        'application_form',
        'supporting_documents',
        'photo',
        'visa_scan',
        'other',
    ];

    public const STATUSES = ['uploaded', 'pending', 'missing'];

    protected $fillable = [
        'visa_application_id',
        'visa_applicant_id',
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
            'visa_application_id' => 'integer',
            'visa_applicant_id' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(VisaApplicant::class, 'visa_applicant_id');
    }
}
