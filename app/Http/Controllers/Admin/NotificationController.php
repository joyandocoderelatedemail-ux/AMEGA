<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Inquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A click on a notification in the bell: that one item is marked seen (so the red count goes down at
 * once), then it opens. Each kind only for staff who may open its page.
 */
class NotificationController extends Controller
{
    private const PAGES = ['inquiry' => 'inquiries', 'booking' => 'bookings', 'chat' => 'chats'];

    public function open(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless($request->user()->canAccessPage(self::PAGES[$type]), 403);

        return match ($type) {
            'inquiry' => $this->seen(Inquiry::findOrFail($id), 'read_at', route('admin.inquiries.index')),
            'booking' => $this->seen(Booking::findOrFail($id), 'read_at', route('admin.bookings.index')),
            'chat' => $this->seen(Conversation::findOrFail($id), 'admin_seen_at', route('admin.chats.index', ['chat' => $id])),
        };
    }

    private function seen(Inquiry|Booking|Conversation $item, string $column, string $url): RedirectResponse
    {
        if ($item->{$column} === null) {
            $item->forceFill([$column => now()])->save();
        }

        return redirect()->to($url);
    }
}
