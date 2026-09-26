<?php

namespace App\Models;

use App\Models\Scopes\OwnFilesScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ticket saved as pending in the booking wizard, to continue later. It holds
 * the whole form as it was, so reopening it puts staff back on the same step
 * with everything filled in. Uploaded files are not kept; they are attached
 * again before the ticket is issued. Private to its creator, like other desk files.
 */
#[ScopedBy([OwnFilesScope::class])]
class TicketDraft extends Model
{
    protected $fillable = [
        'created_by',
        'client_name',
        'summary',
        'step',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'step' => 'integer',
            'payload' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
