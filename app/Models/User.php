<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\DocumentStorage;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Gender options, matching the passenger form on a ticket booking. */
    public const GENDERS = [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
    ];

    /** Philippine IDs the ticketing desk accepts for domestic travel. */
    public const GOVERNMENT_ID_TYPES = [
        'PhilSys National ID',
        'UMID',
        'SSS ID',
        "Driver's License",
        'PRC ID',
        'Postal ID',
        "Voter's ID",
        'Senior Citizen ID',
        'PWD ID',
        'Other',
    ];

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
        'gender',
        'date_of_birth',
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
        'emergency_contact_email',
        'passport_number',
        'passport_expiry',
        'passport_country',
        'passport_photo',
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
            'date_of_birth' => 'date',
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

    /**
     * Profile photos sit on the private document disk, so this points at the
     * authorised route rather than a public asset URL.
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        if ($this->profile_photo && DocumentStorage::disk()->exists($this->profile_photo)) {
            return route('users.profile-photo', $this);
        }

        return null;
    }

    /**
     * Government ID scans sit on the private document disk too.
     */
    public function getGovernmentIdPhotoUrlAttribute(): ?string
    {
        if ($this->government_id_photo && DocumentStorage::disk()->exists($this->government_id_photo)) {
            return route('users.government-id', $this);
        }

        return null;
    }

    /**
     * Passport scans sit on the private document disk as well.
     */
    public function getPassportPhotoUrlAttribute(): ?string
    {
        if ($this->passport_photo && DocumentStorage::disk()->exists($this->passport_photo)) {
            return route('users.passport', $this);
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

    public function isVisaAssistance(): bool
    {
        return $this->role === 'visa_assistance';
    }

    public function isSrrv(): bool
    {
        return $this->role === 'srrv';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'agent', 'ticketing', 'visa_assistance', 'srrv']);
    }

    /**
     * Staff other than admins see only the desk files they opened themselves.
     */
    public function seesOnlyOwnFiles(): bool
    {
        return $this->isStaff() && ! $this->isAdmin();
    }

    /**
     * Who can own a file at a desk ('ticketing', 'visa_assistance' or
     * 'srrv'): its officers, the agents granted it, and admins, who can take
     * a file on themselves.
     *
     * @return Collection<int, User>
     */
    public static function deskStaff(string $page): Collection
    {
        return static::query()
            ->whereIn('role', ['admin', 'agent', 'ticketing', 'visa_assistance', 'srrv'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->canAccessPage($page))
            ->values();
    }

    /**
     * The modules an agent has been granted, ignoring the two that every staff
     * member sees by default. A single-module result means a dedicated desk.
     *
     * @return list<string>
     */
    protected function dedicatedModules(): array
    {
        return array_values(array_diff($this->allowed_pages ?? [], ['dashboard', 'chats']));
    }

    /**
     * An agent who works one dedicated desk and nothing else. They land on that
     * desk's own dashboard at login rather than the full admin dashboard.
     */
    public function isDedicatedDeskAgent(string $page): bool
    {
        return $this->isAgent() && $this->dedicatedModules() === [$page];
    }

    /**
     * An agent who works the immigration counter and nothing else.
     */
    public function isImmigrationAgent(): bool
    {
        return $this->isDedicatedDeskAgent('immigration');
    }

    /**
     * An agent who works the ticketing desk and nothing else.
     */
    public function isTicketingAgent(): bool
    {
        return $this->isDedicatedDeskAgent('ticketing');
    }

    /**
     * An agent who works the visa assistance counter and nothing else.
     */
    public function isVisaAssistanceAgent(): bool
    {
        return $this->isDedicatedDeskAgent('visa_assistance');
    }

    /**
     * An agent who works the SRRV desk and nothing else.
     */
    public function isSrrvAgent(): bool
    {
        return $this->isDedicatedDeskAgent('srrv');
    }

    /**
     * Whether this user is a dedicated ticketing staff member (role 'ticketing' or ticketing-only agent).
     */
    public function isTicketingStaff(): bool
    {
        return $this->isTicketing() || $this->isTicketingAgent();
    }

    /**
     * Dedicated visa assistance staff (role 'visa_assistance' or visa-only agent).
     */
    public function isVisaAssistanceStaff(): bool
    {
        return $this->isVisaAssistance() || $this->isVisaAssistanceAgent();
    }

    /**
     * Dedicated SRRV staff (role 'srrv' or SRRV-only agent).
     */
    public function isSrrvStaff(): bool
    {
        return $this->isSrrv() || $this->isSrrvAgent();
    }

    /**
     * Any staff member confined to a single specialised desk.
     */
    public function isDedicatedDeskStaff(): bool
    {
        return $this->isTicketingStaff()
            || $this->isImmigrationAgent()
            || $this->isVisaAssistanceStaff()
            || $this->isSrrvStaff();
    }

    /**
     * Does this staff member have access to the main admin dashboard and sidebar?
     * Dedicated desk staff only have their respective portals.
     */
    public function hasAdminAccess(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isDedicatedDeskStaff()) {
            return false;
        }

        if (! $this->isAgent()) {
            return false;
        }

        $mainAdminModules = ['bookings', 'packages', 'destinations', 'inquiries', 'crm', 'users', 'services', 'testimonials'];
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

        if ($this->isVisaAssistanceStaff()) {
            return 'visa.dashboard';
        }

        if ($this->isSrrvStaff()) {
            return 'srrv.dashboard';
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

        if ($this->isVisaAssistanceStaff()) {
            return $page === 'visa_assistance';
        }

        if ($this->isSrrvStaff()) {
            return $page === 'srrv';
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

            $allowed = $this->allowed_pages ?? ['dashboard', 'bookings', 'inquiries', 'crm', 'users', 'packages', 'destinations', 'chats'];

            if ($page === 'crm') {
                return in_array('crm', $allowed) || in_array('inquiries', $allowed) || in_array('bookings', $allowed);
            }

            return in_array($page, $allowed);
        }

        return false;
    }

    /**
     * Registered clients matching a name, email, phone, passport or ID number.
     * Every word must match somewhere, so "juan cruz" finds "Juan Dela Cruz".
     */
    public function scopeClientSearch(Builder $query, string $term): Builder
    {
        $query->where('role', 'client');

        foreach (preg_split('/\s+/', trim($term), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';

            $query->where(fn (Builder $match) => $match
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('passport_number', 'like', $like)
                ->orWhere('government_id_number', 'like', $like)
            );
        }

        return $query;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function ticketBookings(): HasMany
    {
        return $this->hasMany(TicketBooking::class);
    }

    public function immigrationClients(): HasMany
    {
        return $this->hasMany(ImmigrationClient::class);
    }

    public function customPackageInquiries(): HasMany
    {
        return $this->hasMany(CustomPackageInquiry::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
