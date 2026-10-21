<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\DocumentStorage;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /** Civil status options, as the BI and embassy forms tick them. */
    public const CIVIL_STATUSES = TouristVisaApplication::CIVIL_STATUSES;

    /**
     * Every page an agent can be given, one checkbox each on the staff forms: label, what it opens,
     * icon. Nothing outside this list can be granted, and nothing in it is granted unless ticked.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    public const PAGE_PERMISSIONS = [
        'dashboard' => ['Dashboard & Analytics', 'The admin dashboard with sales and desk figures', 'layout-dashboard'],
        'bookings' => ['Bookings Management', 'Process tour package reservations & statuses', 'calendar'],
        'crm' => ['CRM Sales Pipeline', 'Kanban deal pipeline, client interaction history & leads', 'kanban'],
        'ticketing' => ['Ticketing System', 'Domestic & international flight ticketing and vouchers', 'ticket'],
        'inquiries' => ['Inquiries Inbox', 'View and respond to client message inquiries', 'inbox'],
        'chats' => ['Live Guest Chats', 'Answer website visitors in the live chat', 'messages-square'],
        'users' => ['Client Directory & Entry', 'Access client profiles and add new traveler records', 'users'],
        'staff' => ['Staff Directory', 'See staff accounts and their activity (read-only: only an admin adds, edits or removes staff)', 'user-check'],
        'packages' => ['Travel Packages', 'View and manage available travel itineraries', 'package'],
        'destinations' => ['Destinations', 'View domestic and international destinations', 'map-pin'],
        'services' => ['Services Management', 'Edit and manage agency core service offers', 'briefcase'],
        'testimonials' => ['Testimonials', 'Manage client reviews and feedback', 'message-square'],
        'immigration' => ['Immigration Counter', 'Client sheets, BI pricing, and the counter dashboard', 'stamp'],
        'visa_assistance' => ['Visa Assistance Counter', 'Visit visa, e-Visa and passporting at one counter', 'globe'],
        'srrv' => ['SRRV Desk', 'PRA retiree visa: applications, ID renewals and re-stamping', 'landmark'],
    ];

    /** Where each page opens, in the order an agent without the dashboard lands on the first they have. */
    public const PAGE_HOME_ROUTES = [
        'dashboard' => 'admin.dashboard',
        'immigration' => 'admin.immigration.dashboard',
        'users' => 'admin.users.index',
        'staff' => 'admin.agents.index',
        'bookings' => 'admin.bookings.index',
        'inquiries' => 'admin.inquiries.index',
        'crm' => 'admin.crm.index',
        'chats' => 'admin.chats.index',
        'packages' => 'admin.packages.index',
        'destinations' => 'admin.destinations.index',
        'services' => 'admin.services.index',
        'testimonials' => 'admin.testimonials.index',
        'ticketing' => 'ticketing.tickets.create',
        'visa_assistance' => 'visa.dashboard',
        'srrv' => 'srrv.dashboard',
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
        'government_id_remarks',
        'government_id_photo',
        'frequent_flyer_membership',
        'stamps_photo',
        'arrival_stamp_photo',
        'account_category',
        'corporate_account_id',
        'allowed_pages',
        'profile_photo',
        'signature',
        'civil_status',
        'height',
        'weight',
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

    /**
     * A client with no email still needs a unique address in the users table, so
     * the desk gives them one on this domain. Nothing is ever sent to it.
     */
    public const PLACEHOLDER_EMAIL_DOMAIN = '@clients.amegatravel.local';

    /** The account categories staff can give a client. */
    public const ACCOUNT_CATEGORIES = ['Individual', 'Corporate', 'Group'];

    public function hasPlaceholderEmail(): bool
    {
        return str_ends_with(mb_strtolower((string) $this->email), self::PLACEHOLDER_EMAIL_DOMAIN);
    }

    /**
     * The client's own email address, or null when the desk made one up for them.
     */
    public function getRealEmailAttribute(): ?string
    {
        return $this->hasPlaceholderEmail() ? null : $this->email;
    }

    /**
     * The passport page with a foreigner's multiple stamps, on the private disk.
     */
    public function getStampsPhotoUrlAttribute(): ?string
    {
        if ($this->stamps_photo && DocumentStorage::disk()->exists($this->stamps_photo)) {
            return route('users.stamps', $this);
        }

        return null;
    }

    /**
     * The exception / arrival stamp scan, on the private disk.
     */
    public function getArrivalStampPhotoUrlAttribute(): ?string
    {
        if ($this->arrival_stamp_photo && DocumentStorage::disk()->exists($this->arrival_stamp_photo)) {
            return route('users.arrival-stamp', $this);
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

    /** Records ticket payments for every booking an admin approved. */
    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'agent', 'ticketing', 'visa_assistance', 'srrv', 'cashier']);
    }

    /**
     * Staff other than admins see only the desk files they opened themselves.
     * The cashier collects payment on every approved booking, so sees them all.
     */
    public function seesOnlyOwnFiles(): bool
    {
        return $this->isStaff() && ! $this->isAdmin() && ! $this->isCashier();
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
     * The modules an agent has been granted. A single desk module (and nothing else, not even
     * the dashboard) means a dedicated desk agent.
     *
     * @return list<string>
     */
    protected function dedicatedModules(): array
    {
        return array_values($this->allowed_pages ?? []);
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

        $mainAdminModules = ['dashboard', 'chats', 'bookings', 'packages', 'destinations', 'inquiries', 'crm', 'users', 'staff', 'services', 'testimonials'];
        $allowed = $this->allowed_pages ?? $mainAdminModules;

        return ! empty(array_intersect($allowed, $mainAdminModules));
    }

    /**
     * Where this staff member lands after signing in.
     */
    public function staffHomeRoute(): string
    {
        if ($this->isCashier()) {
            return 'cashier.dashboard';
        }

        if ($this->isTicketingStaff()) {
            return 'ticketing.tickets.create';
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

        // An agent without the dashboard starts on the first page they were given.
        if ($this->isAgent() && ! $this->canAccessPage('dashboard')) {
            foreach (self::PAGE_HOME_ROUTES as $page => $route) {
                if ($this->canAccessPage($page)) {
                    return $route;
                }
            }

            return 'home';
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

        if ($this->isCashier()) {
            return $page === 'cashier';
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
            // Only what is ticked on the account. The dashboard (analytics) and the live guest chats
            // are pages like any other: never granted just because the agent can open the admin panel.
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
    public function scopeClientSearch(Builder $query, string $term, bool $includeCompany = false): Builder
    {
        $query->where('role', 'client');

        foreach (preg_split('/\s+/', trim($term), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';

            $query->where(function (Builder $match) use ($like, $includeCompany) {
                $match->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('passport_number', 'like', $like)
                    ->orWhere('government_id_number', 'like', $like);

                // A company name finds every account registered under that company.
                if ($includeCompany) {
                    $match->orWhereHas('corporateAccount', fn (Builder $company) => $company->where('company_name', 'like', $like));
                }
            });
        }

        return $query;
    }

    public function corporateAccount(): BelongsTo
    {
        return $this->belongsTo(CorporateAccount::class);
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
