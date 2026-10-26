<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin's wording for one client message, overriding its default in App\Support\MessageTemplates.
 */
class MessageTemplate extends Model
{
    protected $fillable = ['key', 'subject', 'body', 'updated_by'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
