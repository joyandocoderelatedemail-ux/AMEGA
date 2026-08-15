<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GuestChatController extends Controller
{
    public function __construct(protected ChatService $chatService) {}

    /**
     * Initialize or fetch guest chat session.
     */
    public function init(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_token' => ['nullable', 'string', 'max:100'],
            'guest_name' => ['nullable', 'string', 'max:100'],
            'guest_email' => ['nullable', 'email', 'max:150'],
            'guest_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $guestToken = $validated['guest_token'] ?? null;
        if (! $guestToken) {
            $guestToken = 'gst_'.Str::random(24);
        }

        $currentUser = Auth::user();
        $conversation = $this->chatService->getOrCreateConversation($guestToken, $validated, $currentUser);

        // Mark any admin replies as read by guest when drawer is opened
        $this->chatService->markAsRead($conversation, 'guest');

        $messages = $this->chatService->getConversationMessages($conversation)->map(function ($msg) {
            return [
                'id' => $msg->id,
                'sender_type' => $msg->sender_type,
                'message' => $msg->message,
                'attachment_url' => $msg->attachment_url,
                'attachment_type' => $msg->attachment_type,
                'is_read' => $msg->is_read,
                'formatted_time' => $msg->formatted_time,
                'formatted_date' => $msg->formatted_date,
                'created_at' => $msg->created_at ? $msg->created_at->toIso8601String() : now()->toIso8601String(),
            ];
        });

        $conversation->load('assignedAgent');

        return response()->json([
            'success' => true,
            'guest_token' => $conversation->guest_token,
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'guest_name' => $conversation->guest_name,
                'guest_email' => $conversation->guest_email,
                'display_name' => $conversation->display_name,
                'assigned_agent_name' => $conversation->assignedAgent ? $conversation->assignedAgent->name : null,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Send a guest message to live chat.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_token' => ['required', 'string'],
            'message' => ['required', 'string', 'max:3000'],
            'guest_name' => ['nullable', 'string', 'max:100'],
            'guest_email' => ['nullable', 'email', 'max:150'],
            'request_agent' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:5120'],
        ]);

        $currentUser = Auth::user();
        $conversation = $this->chatService->getOrCreateConversation(
            $validated['guest_token'],
            [
                'guest_name' => $validated['guest_name'] ?? null,
                'guest_email' => $validated['guest_email'] ?? null,
            ],
            $currentUser
        );

        $attachmentUrl = null;
        $attachmentType = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('chat_attachments', 'public');
            $attachmentUrl = asset('storage/'.$path);
            $attachmentType = Str::contains($file->getMimeType(), 'image') ? 'image' : 'document';
        }

        $message = $this->chatService->sendMessage(
            $conversation,
            $validated['message'],
            'guest',
            $currentUser,
            $attachmentUrl,
            $attachmentType
        );

        // Auto-request a live agent when a guest types a custom question
        $status = $conversation->status;
        if (! empty($validated['request_agent']) && ! $conversation->assigned_agent_id && $conversation->status !== 'pending_agent') {
            $conversation = $this->chatService->requestAgent($conversation);
            $status = $conversation->status;
        }

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'message' => $message->message,
                'attachment_url' => $message->attachment_url,
                'attachment_type' => $message->attachment_type,
                'is_read' => $message->is_read,
                'formatted_time' => $message->formatted_time,
                'created_at' => $message->created_at ? $message->created_at->toIso8601String() : now()->toIso8601String(),
            ],
            'conversation_status' => $status,
        ]);
    }

    /**
     * Poll for new messages for the guest.
     */
    public function poll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_token' => ['required', 'string'],
            'last_id' => ['nullable', 'integer'],
        ]);

        $conversation = $this->chatService->getOrCreateConversation($validated['guest_token']);
        $sinceId = $validated['last_id'] ?? 0;

        $newMessages = $this->chatService->getConversationMessages($conversation, $sinceId)->map(function ($msg) {
            return [
                'id' => $msg->id,
                'sender_type' => $msg->sender_type,
                'message' => $msg->message,
                'attachment_url' => $msg->attachment_url,
                'attachment_type' => $msg->attachment_type,
                'is_read' => $msg->is_read,
                'formatted_time' => $msg->formatted_time,
                'created_at' => $msg->created_at ? $msg->created_at->toIso8601String() : now()->toIso8601String(),
            ];
        });

        if ($newMessages->isNotEmpty()) {
            $this->chatService->markAsRead($conversation, 'guest');
        }

        $conversation->load('assignedAgent');

        return response()->json([
            'success' => true,
            'messages' => $newMessages,
            'unread_count' => $conversation->unreadCountForGuest(),
            'status' => $conversation->status,
            'assigned_agent_name' => $conversation->assignedAgent ? $conversation->assignedAgent->name : null,
        ]);
    }

    /**
     * Request a live agent.
     */
    public function requestAgent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_token' => ['nullable', 'string', 'max:100'],
            'guest_name' => ['nullable', 'string', 'max:100'],
            'guest_email' => ['nullable', 'email', 'max:150'],
        ]);

        $guestToken = $validated['guest_token'] ?? null;
        if (! $guestToken) {
            $guestToken = 'gst_'.Str::random(24);
        }

        $currentUser = Auth::user();
        $conversation = $this->chatService->getOrCreateConversation($guestToken, $validated, $currentUser);
        $conversation = $this->chatService->requestAgent($conversation);
        $conversation->load('assignedAgent');

        return response()->json([
            'success' => true,
            'guest_token' => $conversation->guest_token,
            'status' => $conversation->status,
            'assigned_agent_name' => $conversation->assignedAgent ? $conversation->assignedAgent->name : null,
            'message' => 'Request sent to live agent.',
        ]);
    }

    /**
     * Update guest contact details.
     */
    public function updateInfo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_token' => ['required', 'string'],
            'guest_name' => ['nullable', 'string', 'max:100'],
            'guest_email' => ['nullable', 'email', 'max:150'],
            'guest_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $conversation = $this->chatService->getOrCreateConversation($validated['guest_token'], $validated);

        return response()->json([
            'success' => true,
            'guest_name' => $conversation->guest_name,
            'guest_email' => $conversation->guest_email,
            'guest_phone' => $conversation->guest_phone,
        ]);
    }
}
