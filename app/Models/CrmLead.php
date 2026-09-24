<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmLead extends Model
{
    use HasFactory;

    public const STAGE_NEW = 'new';

    public const STAGE_CONTACTED = 'contacted';

    public const STAGE_QUOTED = 'quoted';

    public const STAGE_WON = 'won';

    public const STAGE_LOST = 'lost';

    public const STAGES = [
        self::STAGE_NEW => [
            'label' => 'New Lead',
            'color' => 'blue',
            'icon' => 'sparkles',
            'desc' => 'Newly arrived inquiries awaiting first contact',
        ],
        self::STAGE_CONTACTED => [
            'label' => 'Contacted',
            'color' => 'amber',
            'icon' => 'phone-call',
            'desc' => 'In active discussion regarding requirements & travel dates',
        ],
        self::STAGE_QUOTED => [
            'label' => 'Proposal / Quoted',
            'color' => 'indigo',
            'icon' => 'file-text',
            'desc' => 'Official proposal or quotation sent to customer',
        ],
        self::STAGE_WON => [
            'label' => 'Won / Booked',
            'color' => 'emerald',
            'icon' => 'check-circle-2',
            'desc' => 'Successfully closed deal and confirmed booking',
        ],
        self::STAGE_LOST => [
            'label' => 'Lost / Cancelled',
            'color' => 'slate',
            'icon' => 'x-circle',
            'desc' => 'Opportunity lost, budget mismatch, or cancelled',
        ],
    ];

    public const SERVICE_TYPES = [
        'custom_tour' => 'Custom Tour Package',
        'ready_package' => 'Ready-Made Package',
        'flight_ticket' => 'Flight & Airline Ticket',
        'visa_assistance' => 'Visa Assistance',
        'srrv' => 'SRRV Retirement Visa',
        'general' => 'General Inquiry',
    ];

    public const SOURCES = [
        'website' => 'Website Ingestion',
        'walk_in' => 'Walk-in Guest',
        'phone' => 'Phone / Hotline',
        'facebook' => 'Facebook / Messenger',
        'whatsapp' => 'WhatsApp / Viber',
        'referral' => 'Client Referral',
        'portal' => 'Desk Portal',
    ];

    public const PRIORITIES = [
        'low' => ['label' => 'Low', 'color' => 'slate'],
        'medium' => ['label' => 'Medium', 'color' => 'sky'],
        'high' => ['label' => 'High', 'color' => 'amber'],
        'urgent' => ['label' => 'Urgent', 'color' => 'rose'],
    ];

    protected $fillable = [
        'reference_code',
        'client_name',
        'client_email',
        'client_phone',
        'service_type',
        'source',
        'title',
        'destination',
        'travel_date',
        'number_of_pax',
        'estimated_value',
        'currency',
        'stage',
        'priority',
        'assigned_to',
        'source_type',
        'source_id',
        'notes',
        'lost_reason',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
            'estimated_value' => 'decimal:2',
            'number_of_pax' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    public static function generateReferenceCode(): string
    {
        $year = date('Y');
        $lastLead = static::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastLead ? ((int) substr($lastLead->reference_code, -4)) + 1 : 1;

        return sprintf('CRM-%s-%04d', $year, $sequence);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CrmNote::class)->latest();
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function getFormattedValueAttribute(): string
    {
        $symbol = $this->currency === 'USD' ? '$' : '₱';

        return $symbol.number_format((float) $this->estimated_value, 2);
    }

    public function getStageDetailsAttribute(): array
    {
        return self::STAGES[$this->stage] ?? self::STAGES[self::STAGE_NEW];
    }

    public function getServiceLabelAttribute(): string
    {
        return self::SERVICE_TYPES[$this->service_type] ?? ucfirst(str_replace('_', ' ', $this->service_type));
    }

    public function getSourceLabelAttribute(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst(str_replace('_', ' ', $this->source));
    }

    public function getPriorityDetailsAttribute(): array
    {
        return self::PRIORITIES[$this->priority] ?? self::PRIORITIES['medium'];
    }
}
