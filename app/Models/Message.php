<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_type',
        'user_id',
        'message',
        'attachment_url',
        'attachment_type',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeBySender(Builder $query, string $type): Builder
    {
        return $query->where('sender_type', $type);
    }

    public function getIsFromAdminAttribute(): bool
    {
        return $this->sender_type === 'admin';
    }

    public function getIsFromGuestAttribute(): bool
    {
        return $this->sender_type === 'guest';
    }

    public function getFormattedTimeAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('h:i A') : '';
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('M d, Y') : '';
    }
}
