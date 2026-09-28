<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function guestToken(): string
{
    return 'gst_'.Str::random(24);
}

test('a guest cannot chat without giving a name and email', function () {
    $token = guestToken();

    $this->postJson('/chat/send', ['guest_token' => $token, 'message' => 'Hello'])
        ->assertStatus(422)
        ->assertJson(['success' => false, 'needs_details' => true]);

    $this->postJson('/chat/send', ['guest_token' => $token, 'message' => 'Hello', 'guest_name' => 'Juan'])
        ->assertStatus(422);

    $this->postJson('/chat/request-agent', ['guest_token' => $token])
        ->assertStatus(422);

    expect(Conversation::where('guest_token', $token)->first()->messages()->count())->toBe(0);
});

test('a guest who gives a name and email can chat', function () {
    $token = guestToken();

    $this->postJson('/chat/send', [
        'guest_token' => $token,
        'message' => 'Hello',
        'guest_name' => 'Juan Dela Cruz',
        'guest_email' => 'juan@gmail.com',
    ])->assertOk()->assertJson(['success' => true]);

    $conversation = Conversation::where('guest_token', $token)->firstOrFail();

    expect($conversation->guest_name)->toBe('Juan Dela Cruz')
        ->and($conversation->guest_email)->toBe('juan@gmail.com');

    // Given once at the start, the details stay on the conversation.
    $this->postJson('/chat/send', ['guest_token' => $token, 'message' => 'Are you there?'])->assertOk();
    $this->postJson('/chat/request-agent', ['guest_token' => $token])->assertOk();
});

test('a signed-in visitor is already known and can chat straight away', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)
        ->postJson('/chat/send', ['guest_token' => guestToken(), 'message' => 'Hello'])
        ->assertOk();
});

test('the chat asks for a name and email before the first message', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('@submit.prevent="startChat()"')
        ->and($html)->toContain('autocomplete="name"')
        ->and($html)->toContain('autocomplete="email"')
        ->and($html)->not->toContain('Your Contact Details (Optional)');
});
