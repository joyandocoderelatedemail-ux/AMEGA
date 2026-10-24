<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'number_of_pax',
        'nationality',
        'message',
        'service_requested',
        'status',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /** Not yet seen by staff: these count on the admin's red badges and bell. */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
