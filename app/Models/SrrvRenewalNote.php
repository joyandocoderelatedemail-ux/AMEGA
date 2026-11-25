<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in an SRRV ID renewal's running notes log: what happened, when,
 * and who wrote it.
 */
class SrrvRenewalNote extends Model
{
    protected $fillable = [
        'srrv_renewal_id',
        'created_by',
        'note',
    ];

    public function renewal(): BelongsTo
    {
        return $this->belongsTo(SrrvRenewal::class, 'srrv_renewal_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
