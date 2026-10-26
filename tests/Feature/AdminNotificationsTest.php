<?php

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Support\Str;

function bookOnline(string $name = 'Juan Dela Cruz'): void
{
    $package = TravelPackage::firstOrCreate(['title' => 'Palawan Escape'], [
        'duration' => '5 Days', 'price' => '$1,000', 'rating' => 5, 'image' => 'images/test.jpg',
        'description' => 'Test package', 'category' => 'short_haul', 'status' => 'active',
    ]);

    test()->post('/bookings', [
        'travel_package_id' => $package->id, 'customer_name' => $name, 'customer_email' => 'juan@example.com',
        'customer_phone' => '09171234567', 'travel_date' => now()->addDays(10)->format('Y-m-d'), 'number_of_passengers' => 2,
    ])->assertRedirect();
}

test('an online booking shows a red badge on Bookings and in the bell until the list is opened', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    bookOnline();

    expect(Booking::first()->read_at)->toBeNull();

    $this->actingAs($admin)->getJson(route('admin.notifications'))->assertOk()
        ->assertJsonPath('bookings', 1)->assertJsonPath('total', 1)
        ->assertJsonPath('latest.0.type', 'booking')->assertJsonPath('latest.0.title', 'New booking from Juan Dela Cruz')
        ->assertJsonPath('latest.0.detail', 'Palawan Escape · 2 pax');

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
        ->assertSee('data-notify-count="bookings"', false)->assertSee('data-notify-count="bookings,inquiries,chats"', false);

    // Opening the bookings list reads it: marked New this once, and the count drops to zero.
    $this->actingAs($admin)->get(route('admin.bookings.index'))->assertOk()->assertSee('>New</span>', false);
    $this->actingAs($admin)->getJson(route('admin.notifications'))->assertJsonPath('bookings', 0)->assertJsonPath('total', 0);
});

test('the bell counts only the pages a staff member has', function () {
    bookOnline();
    $bookingsOnly = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['bookings']]);
    $neither = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['users']]);

    $this->actingAs($bookingsOnly)->getJson(route('admin.notifications'))->assertJsonPath('bookings', 1)->assertJsonPath('inquiries', 0);
    $this->actingAs($bookingsOnly)->get(route('admin.bookings.index'))->assertOk()->assertSee('adminBell(', false);

    $this->actingAs($neither)->getJson(route('admin.notifications'))->assertJsonPath('total', 0);
    $this->actingAs($neither)->get(route('admin.users.index'))->assertOk()->assertDontSee('adminBell(', false);
});

test('a website visitor asking for an agent shows on the bell and Live Guest Chats until an agent accepts', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $token = 'gst_'.Str::random(24);

    $this->postJson('/chat/send', ['guest_token' => $token, 'message' => 'Hello', 'guest_name' => 'Juan Dela Cruz', 'guest_email' => 'juan@gmail.com'])->assertOk();
    $this->postJson('/chat/request-agent', ['guest_token' => $token])->assertOk();
    $chat = Conversation::where('guest_token', $token)->firstOrFail();

    $this->actingAs($admin)->getJson(route('admin.notifications'))->assertOk()
        ->assertJsonPath('chats', 1)
        ->assertJsonPath('latest.0.type', 'chat')
        ->assertJsonPath('latest.0.title', 'Juan Dela Cruz is asking for an agent')
        ->assertJsonPath('latest.0.url', route('admin.notifications.open', ['chat', $chat->id]));
    $this->actingAs($admin)->get(route('admin.users.index'))->assertSee('data-notify-count="chats"', false);

    $this->actingAs($admin)->post(route('admin.chats.accept', $chat));
    $this->actingAs($admin)->getJson(route('admin.notifications'))->assertJsonPath('chats', 0);

    // Staff without the chats page never see it.
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['bookings']]);
    $this->actingAs($agent)->getJson(route('admin.notifications'))->assertJsonPath('chats', 0);
});

test('clicking a notification marks that item seen, so the count goes down', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    bookOnline();
    bookOnline('Maria Santos');
    $inquiry = Inquiry::create(['name' => 'Ana Lim', 'email' => 'ana@example.com', 'message' => 'Hi']);
    $token = 'gst_'.Str::random(24);
    $this->postJson('/chat/send', ['guest_token' => $token, 'message' => 'Hello', 'guest_name' => 'Juan', 'guest_email' => 'juan@gmail.com'])->assertOk();
    $this->postJson('/chat/request-agent', ['guest_token' => $token])->assertOk();
    $chat = Conversation::where('guest_token', $token)->firstOrFail();

    $this->actingAs($admin)->getJson(route('admin.notifications'))->assertJsonPath('total', 4);

    // One booking clicked: one fewer, the other still counts.
    $this->actingAs($admin)->get(route('admin.notifications.open', ['booking', Booking::first()->id]))->assertRedirect(route('admin.bookings.index'));
    $this->actingAs($admin)->get(route('admin.notifications.open', ['inquiry', $inquiry->id]))->assertRedirect(route('admin.inquiries.index'));
    $this->actingAs($admin)->get(route('admin.notifications.open', ['chat', $chat->id]))->assertRedirect(route('admin.chats.index', ['chat' => $chat->id]));
    $this->actingAs($admin)->getJson(route('admin.notifications'))
        ->assertJsonPath('bookings', 1)->assertJsonPath('inquiries', 0)->assertJsonPath('chats', 0)->assertJsonPath('total', 1);

    // The visitor asking again notifies again.
    $this->postJson('/chat/request-agent', ['guest_token' => $token])->assertOk();
    $this->actingAs($admin)->getJson(route('admin.notifications'))->assertJsonPath('chats', 1);

    // Staff without the page cannot open (or clear) it.
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['bookings']]);
    $this->actingAs($agent)->get(route('admin.notifications.open', ['chat', $chat->id]))->assertForbidden();
});
