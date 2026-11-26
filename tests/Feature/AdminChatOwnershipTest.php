<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

function chatAgent(): User
{
    return staffWith('agent', ['chats.view', 'chats.edit'], ['chats']);
}

function guestConversation(): Conversation
{
    return Conversation::create(['guest_token' => 'gst_'.Str::random(24), 'guest_name' => 'Rona', 'guest_email' => 'rona@gmail.com', 'status' => 'pending_agent']);
}

test('once an agent accepts a chat, another agent can neither reply nor take it', function () {
    [$first, $second] = [chatAgent(), chatAgent()];
    $chat = guestConversation();

    $this->actingAs($first)->postJson(route('admin.chats.accept', $chat))->assertOk();

    $this->actingAs($second)->postJson(route('admin.chats.reply', $chat), ['message' => 'Hi'])
        ->assertForbidden()->assertJson(['message' => "{$first->name} is handling this chat."]);
    $this->actingAs($second)->postJson(route('admin.chats.accept', $chat))->assertForbidden();

    expect($chat->fresh()->assigned_agent_id)->toBe($first->id)
        ->and($chat->messages()->where('sender_type', 'admin')->count())->toBe(0);

    $this->actingAs($second)->getJson(route('admin.chats.show', $chat))
        ->assertJsonPath('conversation.can_reply', false)
        ->assertJsonPath('conversation.can_take_over', false);

    $this->actingAs($first)->postJson(route('admin.chats.reply', $chat), ['message' => 'Hello Rona'])->assertOk();
});

test('accepting your own chat again does not post a second connected notice', function () {
    $agent = chatAgent();
    $chat = guestConversation();

    $this->actingAs($agent)->postJson(route('admin.chats.accept', $chat))->assertOk();
    $this->actingAs($agent)->postJson(route('admin.chats.accept', $chat))->assertOk();

    expect($chat->messages()->where('sender_type', 'system')->count())->toBe(1);
});

test('replying to an unclaimed chat claims it', function () {
    $agent = chatAgent();
    $chat = guestConversation();

    $this->actingAs($agent)->postJson(route('admin.chats.reply', $chat), ['message' => 'Hello'])->assertOk();

    expect($chat->fresh())->assigned_agent_id->toBe($agent->id)->status->toBe('open');
});

test('an admin can take over another agent\'s chat, and it is logged', function () {
    $agent = chatAgent();
    $admin = User::factory()->create(['role' => 'admin']);
    $chat = guestConversation();

    $this->actingAs($agent)->postJson(route('admin.chats.accept', $chat))->assertOk();

    $this->actingAs($admin)->getJson(route('admin.chats.show', $chat))->assertJsonPath('conversation.can_take_over', true);
    $this->actingAs($admin)->postJson(route('admin.chats.reply', $chat), ['message' => 'Hi'])->assertForbidden();
    $this->actingAs($admin)->postJson(route('admin.chats.accept', $chat))->assertOk();

    expect($chat->fresh()->assigned_agent_id)->toBe($admin->id);
    $this->assertDatabaseHas('activity_logs', ['action' => 'TAKE_OVER']);
});

test('"Mine" lists only my chats, and an auto-reply preview says so', function () {
    $agent = chatAgent();
    $mine = guestConversation();
    $other = guestConversation();
    $mine->update(['assigned_agent_id' => $agent->id, 'last_message_at' => now()]);
    Message::create(['conversation_id' => $mine->id, 'sender_type' => 'admin', 'message' => 'Booking is simple!', 'is_read' => true]);

    $response = $this->actingAs($agent)->getJson(route('admin.chats.index', ['status' => 'mine']))->assertOk();

    expect($response->json('conversations'))->toHaveCount(1)
        ->and($response->json('conversations.0.id'))->toBe($mine->id)
        ->and($response->json('conversations.0.last_message'))->toBe('Auto-reply: Booking is simple!')
        ->and($response->json('stats.mine'))->toBe(1)
        ->and($other->id)->not->toBe($mine->id);
});

test('the chat desk and the website chat render', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.chats.index'))->assertOk()->assertSee('takeOver()', false);

    auth()->logout();
    $this->get(route('home'))->assertOk()->assertSee('unaskedQuestions', false);
});
