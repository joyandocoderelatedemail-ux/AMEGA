<?php

namespace App\Support;

use App\Models\Inquiry;
use App\Models\User;

/**
 * What is new for a staff member in the admin panel: the red count badges on the menu and the
 * notification bell. Only what they may open counts (canAccessPage), so the numbers never point
 * at a page they cannot see.
 */
class AdminNotifications
{
    /** How many recent items the bell lists. */
    private const LATEST = 5;

    /**
     * @return array{total: int, inquiries: int, latest: list<array{title: string, detail: string, when: string, url: string}>}
     */
    public static function for(User $user): array
    {
        if (! $user->canAccessPage('inquiries')) {
            return ['total' => 0, 'inquiries' => 0, 'latest' => []];
        }

        $unread = Inquiry::unread();
        $count = (clone $unread)->count();

        $latest = $unread->latest()->limit(self::LATEST)->get()
            ->map(fn (Inquiry $inquiry): array => [
                'title' => 'New inquiry from '.$inquiry->name,
                'detail' => collect([$inquiry->service_requested, $inquiry->number_of_pax ? $inquiry->number_of_pax.' pax' : null])->filter()->implode(' · '),
                'when' => $inquiry->created_at?->diffForHumans() ?? '',
                'url' => route('admin.inquiries.index'),
            ])
            ->all();

        return ['total' => $count, 'inquiries' => $count, 'latest' => $latest];
    }
}
