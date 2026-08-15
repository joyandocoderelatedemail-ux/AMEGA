<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'guest_token',
        'guest_name',
        'guest_email',
        'guest_phone',
        'user_id',
        'assigned_agent_id',
        'status',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'pending_agent']);
    }

    public function scopePendingAgent(Builder $query): Builder
    {
        return $query->where('status', 'pending_agent');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('guest_name', 'like', "%{$term}%")
                ->orWhere('guest_email', 'like', "%{$term}%")
                ->orWhere('guest_phone', 'like', "%{$term}%")
                ->orWhere('guest_token', 'like', "%{$term}%")
                ->orWhereHas('user', function (Builder $userQuery) use ($term) {
                    $userQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                })
                ->orWhereHas('messages', function (Builder $msgQuery) use ($term) {
                    $msgQuery->where('message', 'like', "%{$term}%");
                });
        });
    }

    public function unreadCountForAdmin(): int
    {
        return $this->messages()
            ->where('sender_type', 'guest')
            ->where('is_read', false)
            ->count();
    }

    public function unreadCountForGuest(): int
    {
        return $this->messages()
            ->where('sender_type', 'admin')
            ->where('is_read', false)
            ->count();
    }

    public function markAsReadForAdmin(): void
    {
        $this->messages()
            ->where('sender_type', 'guest')
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    public function markAsReadForGuest(): void
    {
        $this->messages()
            ->where('sender_type', 'admin')
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    public function linkToUser(User $user): void
    {
        $this->update([
            'user_id' => $user->id,
            'guest_name' => $this->guest_name ?: $user->name,
            'guest_email' => $this->guest_email ?: $user->email,
            'guest_phone' => $this->guest_phone ?: $user->phone,
        ]);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->name.' (Registered)';
        }

        if ($this->guest_name) {
            return $this->guest_name;
        }

        return 'Guest #'.strtoupper(substr(md5($this->guest_token), 0, 6));
    }

    public function getFormattedLastActivityAttribute(): string
    {
        $time = $this->last_message_at ?: $this->updated_at;

        if (! $time) {
            return 'Just now';
        }

        return $time->diffForHumans();
    }
}
