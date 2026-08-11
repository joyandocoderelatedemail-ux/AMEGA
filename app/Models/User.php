<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'password',
        'role',
        'phone',
        'address',
        'address_line',
        'city',
        'province',
        'postal_code',
        'country',
        'avatar',
        'nationality',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'passport_number',
        'passport_expiry',
        'passport_country',
        'government_id_type',
        'government_id_number',
        'government_id_photo',
        'account_category',
        'allowed_pages',
        'profile_photo',
        'signature',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'passport_expiry' => 'date',
            'allowed_pages' => 'array',
        ];
    }

    public function getFullNameAttribute(): string
    {
        if ($this->first_name || $this->last_name) {
            return trim(implode(' ', array_filter([
                $this->first_name,
                $this->middle_name,
                $this->last_name,
                $this->suffix,
            ])));
        }

        return $this->name ?? 'Client User';
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        if ($this->profile_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->profile_photo)) {
            return asset('storage/' . $this->profile_photo);
        }

        return null;
    }

    public function getGovernmentIdPhotoUrlAttribute(): ?string
    {
        if ($this->government_id_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->government_id_photo)) {
            return asset('storage/' . $this->government_id_photo);
        }

        return null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'agent']);
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function canAccessPage(string $page): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($page === 'dashboard') {
            return true;
        }

        if ($this->isAgent()) {
            $allowed = $this->allowed_pages ?? ['dashboard', 'bookings', 'inquiries', 'users', 'packages', 'destinations'];

            return in_array($page, $allowed);
        }

        return false;
    }

    public function bookings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
