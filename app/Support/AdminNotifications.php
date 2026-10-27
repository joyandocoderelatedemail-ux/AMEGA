<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What is new for a staff member in the admin panel: the red count badges on the menu and the
 * notification bell. Website inquiries and online tour-package bookings that nobody has opened yet, and
 * website chats where the visitor asked for an agent and nobody has taken the chat, and (for admins)
 * ticket bookings waiting for approval.
 * Only what they may open counts (canAccessPage), so the numbers never point at a page they cannot see.
 */
class AdminNotifications
{
    /** How many recent items the bell lists. */
    private const LATEST = 5;

    /**
     * @return array{total: int, inquiries: int, bookings: int, chats: int, approvals: int, latest: list<array{type: string, title: string, detail: string, when: string, url: string}>}
     */
    public static function for(User $user): array
    {
        $latest = new Collection;
        $counts = ['inquiries' => 0, 'bookings' => 0, 'chats' => 0, 'approvals' => 0];

        // Ticket bookings an agent submitted, until an admin opens them.
        if ($user->isAdmin()) {
            $counts['approvals'] = TicketNotifications::forApprovers()->count();
            $latest = $latest->concat(TicketNotifications::approvalItems());
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
            'latest' => $latest->sortByDesc('at')->take(self::LATEST)
                ->map(fn (array $item): array => ['when' => $item['at']?->diffForHumans() ?? ''] + collect($item)->except('at')->all())
                ->values()->all(),
        ];
    }

    /** Whether this person gets the bell at all: they can open something it reports on. */
    public static function shown(User $user): bool
    {
        return $user->isAdmin() || $user->canAccessPage('inquiries') || $user->canAccessPage('bookings') || $user->canAccessPage('chats');
    }
}
