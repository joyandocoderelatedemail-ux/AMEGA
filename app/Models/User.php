<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

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
        if ($this->profile_photo && Storage::disk('public')->exists($this->profile_photo)) {
            return asset('storage/'.$this->profile_photo);
        }

        return null;
    }

    public function getGovernmentIdPhotoUrlAttribute(): ?string
    {
        if ($this->government_id_photo && Storage::disk('public')->exists($this->government_id_photo)) {
            return asset('storage/'.$this->government_id_photo);
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

    public function isTicketing(): bool
    {
        return $this->role === 'ticketing';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'agent', 'ticketing']);
    }

    /**
     * An agent who works the immigration counter and nothing else. They land on
     * the counter dashboard at login rather than the full admin dashboard.
     */
    public function isImmigrationAgent(): bool
    {
        if (! $this->isAgent()) {
            return false;
        }

        $modules = array_values(array_diff($this->allowed_pages ?? [], ['dashboard', 'chats']));

        return $modules === ['immigration'];
    }

    /**
     * An agent who works the ticketing desk and nothing else. They land on
     * the ticketing dashboard at login rather than the full admin dashboard.
     */
    public function isTicketingAgent(): bool
    {
        if (! $this->isAgent()) {
            return false;
        }

        $modules = array_values(array_diff($this->allowed_pages ?? [], ['dashboard', 'chats']));

        return $modules === ['ticketing'];
    }

    /**
     * Whether this user is a dedicated ticketing staff member (role 'ticketing' or ticketing-only agent).
     */
    public function isTicketingStaff(): bool
    {
        return $this->isTicketing() || $this->isTicketingAgent();
    }

    /**
     * Does this staff member have access to the main admin dashboard and sidebar?
     * Dedicated ticketing and immigration agents only have their respective portals.
     */
    public function hasAdminAccess(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isTicketingStaff() || $this->isImmigrationAgent()) {
            return false;
        }

        if (! $this->isAgent()) {
            return false;
        }

        $mainAdminModules = ['bookings', 'packages', 'destinations', 'inquiries', 'users', 'services', 'testimonials'];
        $allowed = $this->allowed_pages ?? $mainAdminModules;

        return ! empty(array_intersect($allowed, $mainAdminModules));
    }

    /**
     * Where this staff member lands after signing in.
     */
    public function staffHomeRoute(): string
    {
        if ($this->isTicketingStaff()) {
            return 'ticketing.dashboard';
        }

        if ($this->isImmigrationAgent()) {
            return 'admin.immigration.dashboard';
        }

        return 'admin.dashboard';
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

        if ($this->isTicketingStaff()) {
            return $page === 'ticketing';
        }

        if ($this->isImmigrationAgent()) {
            return $page === 'immigration';
        }

        if ($this->isAgent()) {
            if ($page === 'dashboard') {
                return $this->hasAdminAccess();
            }

            if ($page === 'chats') {
                return $this->hasAdminAccess();
            }

            $allowed = $this->allowed_pages ?? ['dashboard', 'bookings', 'inquiries', 'users', 'packages', 'destinations', 'chats'];

            return in_array($page, $allowed);
        }

        return false;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
