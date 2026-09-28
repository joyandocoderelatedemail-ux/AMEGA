<?php

namespace App\Models;

use Database\Factories\AirlineFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An airline the ticketing desk searches for fares.
 *
 * There is no fare API behind this: the wizard opens the airline's own site
 * in a new tab, and staff record the flight they chose on the ticket.
 */
class Airline extends Model
{
    /** @use HasFactory<AirlineFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'booking_url',
        'agent_portal_url',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function ticketBookings(): HasMany
    {
        return $this->hasMany(TicketBooking::class);
    }

    /**
     * Airlines offered in the wizard, in the desk's chosen order.
     *
     * @param  Builder<Airline>  $query
     */
    public function scopeOffered(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * "Cebu Pacific (5J)", or just the name when no code is on file.
     */
    public function label(): string
    {
        return $this->code ? "{$this->name} ({$this->code})" : $this->name;
    }
}
