<?php

namespace App\Services;

use App\Events\ConversationStatusUpdated;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ChatService
{
    /**
     * Get an existing conversation by token or create a new one.
     */
    public function getOrCreateConversation(string $guestToken, array $guestData = [], ?User $currentUser = null): Conversation
    {
        $conversation = Conversation::where('guest_token', $guestToken)->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'guest_token' => $guestToken ?: 'gst_'.Str::random(24),
                'guest_name' => $guestData['guest_name'] ?? ($currentUser ? $currentUser->name : null),
                'guest_email' => $guestData['guest_email'] ?? ($currentUser ? $currentUser->email : null),
                'guest_phone' => $guestData['guest_phone'] ?? ($currentUser ? $currentUser->phone : null),
                'user_id' => $currentUser ? $currentUser->id : null,
                'status' => 'open',
                'last_message_at' => now(),
            ]);
        } else {
            $updateData = [];

            if ($currentUser && ! $conversation->user_id) {
                $updateData['user_id'] = $currentUser->id;
            }

            if (! empty($guestData['guest_name']) && $conversation->guest_name !== $guestData['guest_name']) {
                $updateData['guest_name'] = $guestData['guest_name'];
            }

            if (! empty($guestData['guest_email']) && $conversation->guest_email !== $guestData['guest_email']) {
                $updateData['guest_email'] = $guestData['guest_email'];
            }

            if (! empty($guestData['guest_phone']) && $conversation->guest_phone !== $guestData['guest_phone']) {
                $updateData['guest_phone'] = $guestData['guest_phone'];
            }

            if (! empty($updateData)) {
                $conversation->update($updateData);
            }
        }

        return $conversation;
    }

    /**
     * Send a new message in a conversation.
     */
    public function sendMessage(
        Conversation $conversation,
        string $messageText,
        string $senderType = 'guest',
        ?User $senderUser = null,
        ?string $attachmentUrl = null,
        ?string $attachmentType = null
    ): Message {
        // Automatically reopen conversation if it was closed
        if ($conversation->status === 'closed') {
            $conversation->update(['status' => 'open']);
            event(new ConversationStatusUpdated($conversation));
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => $senderType,
            'user_id' => $senderUser ? $senderUser->id : null,
            'message' => trim($messageText),
            'attachment_url' => $attachmentUrl,
            'attachment_type' => $attachmentType,
            'is_read' => false,
        ]);

        $conversation->update([
            'last_message_at' => now(),
        ]);

        // Dispatch real-time broadcasting event
        try {
            event(new MessageSent($message));
        } catch (\Throwable $e) {
            // Log fallback if event broadcasting service is unconfigured
            logger()->warning('MessageSent event broadcasting fallback: '.$e->getMessage());
        }

        return $message;
    }

    /**
     * Get messages for a conversation, optionally filtered since a specific message ID.
     */
    public function getConversationMessages(Conversation $conversation, ?int $sinceId = null): Collection
    {
        $query = $conversation->messages()->with('user');

        if ($sinceId && $sinceId > 0) {
            $query->where('id', '>', $sinceId);
        }

        return $query->get();
    }

    /**
     * Mark conversation messages as read.
     */
    public function markAsRead(Conversation $conversation, string $readerType = 'admin'): void
    {
        if ($readerType === 'admin') {
            $conversation->markAsReadForAdmin();
        } else {
            $conversation->markAsReadForGuest();
        }
    }

    /**
     * Update conversation status (open/closed/pending_agent).
     */
    public function updateStatus(Conversation $conversation, string $status): Conversation
    {
        $conversation->update([
            'status' => in_array($status, ['open', 'closed', 'pending_agent']) ? $status : 'open',
        ]);

        try {
            event(new ConversationStatusUpdated($conversation));
        } catch (\Throwable $e) {
            logger()->warning('ConversationStatusUpdated event broadcasting fallback: '.$e->getMessage());
        }

        return $conversation;
    }

    /**
     * Request connection to a live agent.
     */
    public function requestAgent(Conversation $conversation): Conversation
    {
        $conversation->update([
            'status' => 'pending_agent',
            'last_message_at' => now(),
        ]);

        $this->sendMessage(
            $conversation,
            'Requesting a live agent... Please wait while a travel agent connects to your chat.',
            'system'
        );

        try {
            event(new ConversationStatusUpdated($conversation));
        } catch (\Throwable $e) {
            logger()->warning('ConversationStatusUpdated event broadcasting fallback: '.$e->getMessage());
        }

        return $conversation;
    }

    /**
     * Accept a pending guest chat conversation by an agent.
     */
    public function acceptConversation(Conversation $conversation, User $agent): Message
    {
        $conversation->update([
            'assigned_agent_id' => $agent->id,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $message = $this->sendMessage(
            $conversation,
            "Agent \"{$agent->name}\" is connected",
            'system',
            $agent
        );

        try {
            event(new ConversationStatusUpdated($conversation));
        } catch (\Throwable $e) {
            logger()->warning('ConversationStatusUpdated event broadcasting fallback: '.$e->getMessage());
        }

        return $message;
    }

    /**
     * Link guest conversation to a registered user account after booking or registration.
     */
    public function linkGuestToUser(string $guestToken, User $user): ?Conversation
    {
        $conversation = Conversation::where('guest_token', $guestToken)->first();

        if ($conversation) {
            $conversation->linkToUser($user);
        }

        return $conversation;
    }
}
