<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'crm_lead_id',
        'user_id',
        'action_type', // note, call, email, meeting, status_change
        'content',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
