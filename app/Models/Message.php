<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

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

    /** Stored as the file's path (older rows: its old public URL); handed out as a link that expires. */
    protected function attachmentUrl(): Attribute
    {
        return Attribute::get(fn (?string $value) => $value && preg_match('#chat_attachments/([\w.-]+)$#', $value, $file)
            ? URL::temporarySignedRoute('chat.attachment', now()->addHours(12), ['file' => $file[1]])
            : $value);
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

    /** Who wrote it, for the agents' view; an admin-side message with no user is the quick-question auto-reply. */
    public function getSenderNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        return $this->sender_type === 'guest' ? ($this->conversation->guest_name ?: 'Guest') : 'Auto-reply';
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
