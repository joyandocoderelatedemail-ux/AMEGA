<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A reminder a client was sent about a document running out (see App\Support\ClientExpiries). */
class ExpiryReminder extends Model
{
    protected $fillable = ['doc_key', 'document', 'expires_on', 'user_id', 'immigration_client_id', 'channel', 'sent_by'];

    protected function casts(): array
    {
        return ['expires_on' => 'date'];
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** True when the daily job sent it, not a staff member. */
    public function isAutomatic(): bool
    {
        return $this->sent_by === null;
    }
}
