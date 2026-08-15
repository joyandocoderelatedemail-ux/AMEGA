<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load(['conversation', 'user']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.conversation.'.$this->message->conversation_id),
            new PrivateChannel('admin.chats'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_type' => $this->message->sender_type,
            'user_id' => $this->message->user_id,
            'sender_name' => $this->message->user ? $this->message->user->name : ($this->message->conversation->guest_name ?: 'Guest'),
            'message' => $this->message->message,
            'attachment_url' => $this->message->attachment_url,
            'attachment_type' => $this->message->attachment_type,
            'is_read' => $this->message->is_read,
            'formatted_time' => $this->message->formatted_time,
            'formatted_date' => $this->message->formatted_date,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
