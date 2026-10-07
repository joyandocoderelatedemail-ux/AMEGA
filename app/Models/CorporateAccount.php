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

    public function members(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'client');
    }
}
