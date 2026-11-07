<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What an admin decided an agent earns on a service they sent, set when the admin approves it.
 * Admin eyes only: see App\Support\Commissions.
 */
class AgentCommission extends Model
{
    protected $fillable = ['desk', 'kind', 'file_id', 'agent_id', 'amount', 'reference', 'client_name', 'set_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
