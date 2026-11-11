<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An account several clients travel under, picked together when booking. Two kinds share the table:
 *
 *  - a company (kind 'corporate'): may hold a credit account, and its ticket bookings can carry the
 *    corporate service tax;
 *  - a group (kind 'group'): a family, a barkada, a tour party. Members are kept and picked the same
 *    way, with a leader as the contact, but there is no credit account and no service tax.
 */
class CorporateAccount extends Model
{
    public const KIND_CORPORATE = 'corporate';

    public const KIND_GROUP = 'group';

    /** Kind => the client Account Category its members carry. */
    public const CATEGORIES = [self::KIND_CORPORATE => 'Corporate', self::KIND_GROUP => 'Group'];

    /** What a group's form asks for: a name, its leader and how to reach them. */
    public const GROUP_FIELDS = ['company_name', 'contact_person', 'contact_email', 'contact_phone', 'notes'];

    /** Company fields, in the order the forms show them. */
    public const FIELDS = [
        'company_name',
        'registration_number',
        'tin',
        'industry',
        'address',
        'contact_person',
        'contact_position',
        'contact_email',
        'contact_phone',
        'notes',
    ];

    protected $fillable = self::FIELDS;

    /** Net days a new credit account starts with. */
    public const DEFAULT_TERMS_DAYS = 30;

    /** A company unless said otherwise (rows from before groups existed, and new ones made without a kind). */
    protected $attributes = ['kind' => self::KIND_CORPORATE];

    protected function casts(): array
    {
        return [
            'credit_enabled' => 'boolean',
            'credit_limit' => 'decimal:2',
            'credit_terms_days' => 'integer',
            'credit_interest_rate' => 'decimal:2',
            'credit_grace_days' => 'integer',
        ];
    }

    public function isGroup(): bool
    {
        return $this->kind === self::KIND_GROUP;
    }

    /** The Account Category of its members: Corporate or Group. */
    public function category(): string
    {
        return self::CATEGORIES[$this->kind] ?? self::CATEGORIES[self::KIND_CORPORATE];
    }

    /** What it is called in a sentence: "company" or "group". */
    public function noun(): string
    {
        return $this->isGroup() ? 'group' : 'company';
    }

    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind);
    }

    /** The company may charge tickets to its account and pay later. A group never does. */
    public function hasCredit(): bool
    {
        return ! $this->isGroup() && (bool) $this->credit_enabled;
    }

    /** Its ticket bookings may carry the corporate service tax. A group's never do. */
    public function takesServiceTax(): bool
    {
        return ! $this->isGroup();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CorporatePayment::class);
    }

    protected static function booted(): void
    {
        // Members stay on file as ordinary clients when their company is deleted.
        static::deleting(fn (self $company) => User::where('corporate_account_id', $company->id)->update(['corporate_account_id' => null]));
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'client');
    }
}
