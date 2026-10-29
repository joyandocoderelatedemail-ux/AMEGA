<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scan filed on an SRRV Termination or Conversion request, against one requirement of the PRA
 * checklist (or "other"). The file is on the private disk (App\Support\DocumentStorage) and is
 * downloaded only through the desk, by staff who can open the request.
 */
class SrrvServiceRequestDocument extends Model
{
    protected $fillable = ['srrv_service_request_id', 'requirement', 'file_path', 'original_name', 'file_size', 'mime_type', 'uploaded_by'];

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(SrrvServiceRequest::class, 'srrv_service_request_id');
    }
}
