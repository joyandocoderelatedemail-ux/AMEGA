<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Conversation $conversation;

    public function __construct(Conversation $conversation)
    {
        $this->conversation = $conversation;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.conversation.'.$this->conversation->id),
            new PrivateChannel('admin.chats'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->conversation->id,
            'status' => $this->conversation->status,
            'updated_at' => $this->conversation->updated_at->toIso8601String(),
        ];
    }
}
