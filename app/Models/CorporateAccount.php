<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorporateAccount extends Model
{
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
