<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ImmigrationClientExtension;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\ImmigrationRestamping;
use App\Models\Inquiry;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\SrrvServiceRequest;
use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What is new for a staff member in the admin panel: the red count badges on the menu and the
 * notification bell. Website inquiries and online tour-package bookings that nobody has opened yet, and
 * website chats where the visitor asked for an agent and nobody has taken the chat, and (for admins)
 * ticket bookings waiting for approval, and (for whoever may approve a desk) the Immigration, Visa
 * Assistance and SRRV files waiting for approval.
 * Only what they may open counts (canAccessPage), so the numbers never point at a page they cannot see.
 */
class AdminNotifications
{
    /** How many recent items the bell lists. */
    private const LATEST = 5;

    /** The desks whose files wait for an approver: count key => [permission desk, label, approvals page, models]. */
    private const DESK_APPROVALS = [
        'immigration_approvals' => ['immigration', 'Immigration', 'admin.immigration-approvals.index', [
            ImmigrationPetition::class, ImmigrationOverstay::class, ImmigrationRestamping::class, ImmigrationClientExtension::class,
        ]],
        'visa_approvals' => ['visa_assistance', 'Visa', 'admin.visa-approvals.index', [VisaApplication::class]],
        'srrv_approvals' => ['srrv', 'SRRV', 'admin.srrv-approvals.index', [SrrvApplication::class, SrrvRenewal::class, SrrvServiceRequest::class]],
    ];

    /**
     * @return array{total: int, inquiries: int, bookings: int, chats: int, approvals: int, latest: list<array{type: string, title: string, detail: string, when: string, url: string}>}
     */
    public static function for(User $user, int $limit = self::LATEST): array
    {
        $latest = new Collection;
        $counts = ['inquiries' => 0, 'bookings' => 0, 'chats' => 0, 'approvals' => 0, 'immigration_approvals' => 0, 'visa_approvals' => 0, 'srrv_approvals' => 0];

        // Ticket bookings an agent submitted, until an admin opens them.
        if ($user->isAdmin()) {
            $counts['approvals'] = TicketNotifications::forApprovers()->count();
            $latest = $latest->concat(TicketNotifications::approvalItems());
        }

        // Desk files waiting for an approver (maker-checker): what this person may approve, not what they sent.
        foreach (self::DESK_APPROVALS as $key => [$desk, $label, $route, $models]) {
            if (! $user->hasPermission("{$desk}.approve")) {
                continue;
            }
            $waiting = collect($models)->map(fn (string $model) => $model::withoutGlobalScopes()
                ->where('approval_status', 'pending')
                ->where(fn ($q) => $q->whereNull('acknowledged_by')->orWhere('acknowledged_by', '!=', $user->id)));
            $counts[$key] = $waiting->sum(fn ($query) => (clone $query)->count());
            if ($counts[$key] > 0) {
                $latest->push([
                    'type' => 'approval',
                    'at' => ($sent = $waiting->map(fn ($query) => (clone $query)->max('approval_requested_at'))->filter()->max()) ? Carbon::parse($sent) : null,
                    'title' => $counts[$key].' '.$label.' '.($counts[$key] === 1 ? 'file is' : 'files are').' waiting for your approval',
                    'detail' => 'Open '.$label.' Approvals',
                    'url' => route($route),
                ]);
            }
        }

        if ($user->canAccessPage('inquiries')) {
            $counts['inquiries'] = Inquiry::unread()->count();
            $latest = $latest->concat(Inquiry::unread()->latest()->limit(self::LATEST)->get()
                ->map(fn (Inquiry $inquiry): array => [
                    'type' => 'inquiry',
                    'at' => $inquiry->created_at,
                    'title' => 'New inquiry from '.$inquiry->name,
                    'detail' => collect([$inquiry->service_requested, $inquiry->number_of_pax ? $inquiry->number_of_pax.' pax' : null])->filter()->implode(' · '),
                    'url' => route('admin.notifications.open', ['inquiry', $inquiry->id]),
                ]));
        }

        if ($user->canAccessPage('bookings')) {
            $counts['bookings'] = Booking::unread()->count();
            $latest = $latest->concat(Booking::unread()->with('travelPackage')->latest()->limit(self::LATEST)->get()
                ->map(fn (Booking $booking): array => [
                    'type' => 'booking',
                    'at' => $booking->created_at,
                    'title' => 'New booking from '.$booking->customer_name,
                    'detail' => collect([$booking->travelPackage?->title, $booking->number_of_passengers ? $booking->number_of_passengers.' pax' : null])->filter()->implode(' · '),
                    'url' => route('admin.notifications.open', ['booking', $booking->id]),
                ]));
        }

        // A visitor on the website asked for a live agent; it stops counting once staff open the chat (or accept it).
        if ($user->canAccessPage('chats')) {
            $waiting = Conversation::where('status', 'pending_agent')->whereNull('admin_seen_at');
            $counts['chats'] = (clone $waiting)->count();
            $latest = $latest->concat($waiting->latest('last_message_at')->limit(self::LATEST)->get()
                ->map(fn (Conversation $chat): array => [
                    'type' => 'chat',
                    'at' => $chat->last_message_at ?? $chat->created_at,
                    'title' => ($chat->guest_name ?: 'A website visitor').' is asking for an agent',
                    'detail' => collect([$chat->guest_email, $chat->guest_phone])->filter()->implode(' · ') ?: 'Live chat',
                    'url' => route('admin.notifications.open', ['chat', $chat->id]),
                ]));
        }

        return [
            'total' => array_sum($counts),
            ...$counts,
            'latest' => $latest->sortByDesc('at')->take($limit)
                ->map(fn (array $item): array => ['when' => $item['at']?->diffForHumans() ?? ''] + collect($item)->except('at')->all())
                ->values()->all(),
        ];
    }

    /**
     * Every kind of notification this person gets, with how many are waiting and where they are
     * handled: the admin dashboard lists them all, including those at zero.
     *
     * @param  array<string, mixed>  $counts  from for()
     * @return list<array{key: string, label: string, icon: string, count: int, url: string}>
     */
    public static function categories(User $user, array $counts): array
    {
        $rows = [
            ['approvals', 'Ticket approvals', 'ticket', 'admin.ticket-approvals.index', $user->isAdmin()],
            ['immigration_approvals', 'Immigration approvals', 'stamp', 'admin.immigration-approvals.index', $user->hasPermission('immigration.approve')],
            ['visa_approvals', 'Visa approvals', 'globe', 'admin.visa-approvals.index', $user->hasPermission('visa_assistance.approve')],
            ['srrv_approvals', 'SRRV approvals', 'landmark', 'admin.srrv-approvals.index', $user->hasPermission('srrv.approve')],
            ['inquiries', 'New inquiries', 'inbox', 'admin.inquiries.index', $user->canAccessPage('inquiries')],
            ['bookings', 'New package bookings', 'calendar', 'admin.bookings.index', $user->canAccessPage('bookings')],
            ['chats', 'Guests asking for an agent', 'messages-square', 'admin.chats.index', $user->canAccessPage('chats')],
        ];

        return collect($rows)->filter(fn (array $row) => $row[4])
            ->map(fn (array $row) => ['key' => $row[0], 'label' => $row[1], 'icon' => $row[2], 'count' => (int) ($counts[$row[0]] ?? 0), 'url' => route($row[3])])
            ->values()->all();
    }

    /** Whether this person gets the bell at all: they can open something it reports on. */
    public static function shown(User $user): bool
    {
        return $user->isAdmin() || $user->canAccessPage('inquiries') || $user->canAccessPage('bookings') || $user->canAccessPage('chats')
            || $user->hasPermission('immigration.approve') || $user->hasPermission('visa_assistance.approve') || $user->hasPermission('srrv.approve');
    }
}
