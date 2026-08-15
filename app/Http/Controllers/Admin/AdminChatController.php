<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ActivityLogger;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdminChatController extends Controller
{
    public function __construct(protected ChatService $chatService) {}

    /**
     * Display the Admin Chat Dashboard.
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $search = $request->query('search', '');

        $query = Conversation::query()
            ->with(['latestMessage', 'user', 'assignedAgent'])
            ->withCount(['messages as unread_count' => function ($q) {
                $q->where('sender_type', 'guest')->where('is_read', false);
            }])
            ->orderBy('last_message_at', 'desc');

        if ($status === 'open') {
            $query->open();
        } elseif ($status === 'pending') {
            $query->pendingAgent();
        } elseif ($status === 'closed') {
            $query->closed();
        } elseif ($status === 'unread') {
            $query->whereHas('messages', function ($q) {
                $q->where('sender_type', 'guest')->where('is_read', false);
            });
        }

        if (! empty($search)) {
            $query->search($search);
        }

        $conversations = $query->paginate(25);

        // Overall Chat Metrics
        $stats = [
            'total' => Conversation::count(),
            'open' => Conversation::open()->count(),
            'pending' => Conversation::pendingAgent()->count(),
            'closed' => Conversation::closed()->count(),
            'unread' => Conversation::whereHas('messages', function ($q) {
                $q->where('sender_type', 'guest')->where('is_read', false);
            })->count(),
        ];

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'conversations' => $conversations->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'guest_token' => $c->guest_token,
                        'display_name' => $c->display_name,
                        'guest_name' => $c->guest_name,
                        'guest_email' => $c->guest_email,
                        'guest_phone' => $c->guest_phone,
                        'status' => $c->status,
                        'assigned_agent_id' => $c->assigned_agent_id,
                        'assigned_agent_name' => $c->assignedAgent ? $c->assignedAgent->name : null,
                        'is_accepted' => (bool) $c->assigned_agent_id,
                        'is_registered' => (bool) $c->user_id,
                        'user_name' => $c->user ? $c->user->name : null,
                        'unread_count' => $c->unread_count,
                        'last_message' => $c->latestMessage ? Str::limit($c->latestMessage->message, 60) : 'No messages yet',
                        'last_message_time' => $c->latestMessage ? $c->latestMessage->formatted_time : '',
                        'formatted_last_activity' => $c->formatted_last_activity,
                        'last_message_at' => $c->last_message_at ? $c->last_message_at->toIso8601String() : null,
                    ];
                }),
            ]);
        }

        return view('admin.chats.index', compact('conversations', 'stats', 'status', 'search'));
    }

    /**
     * Get specific conversation details & messages for admin view.
     */
    public function show(Request $request, Conversation $conversation)
    {
        // Mark guest messages as read by admin
        $this->chatService->markAsRead($conversation, 'admin');

        if (! $request->wantsJson() && ! $request->ajax()) {
            return redirect()->route('admin.chats.index', ['chat' => $conversation->id]);
        }

        $conversation->load(['user', 'latestMessage', 'assignedAgent']);

        $messages = $conversation->messages()->with('user')->get()->map(function ($msg) {
            return [
                'id' => $msg->id,
                'sender_type' => $msg->sender_type,
                'user_id' => $msg->user_id,
                'sender_name' => $msg->user ? $msg->user->name : ($msg->conversation->guest_name ?: 'Guest'),
                'message' => $msg->message,
                'attachment_url' => $msg->attachment_url,
                'attachment_type' => $msg->attachment_type,
                'is_read' => $msg->is_read,
                'formatted_time' => $msg->formatted_time,
                'formatted_date' => $msg->formatted_date,
                'created_at' => $msg->created_at ? $msg->created_at->toIso8601String() : now()->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'guest_token' => $conversation->guest_token,
                'display_name' => $conversation->display_name,
                'guest_name' => $conversation->guest_name,
                'guest_email' => $conversation->guest_email,
                'guest_phone' => $conversation->guest_phone,
                'status' => $conversation->status,
                'assigned_agent_id' => $conversation->assigned_agent_id,
                'assigned_agent_name' => $conversation->assignedAgent ? $conversation->assignedAgent->name : null,
                'is_accepted' => (bool) $conversation->assigned_agent_id,
                'is_registered' => (bool) $conversation->user_id,
                'user' => $conversation->user ? [
                    'id' => $conversation->user->id,
                    'name' => $conversation->user->name,
                    'email' => $conversation->user->email,
                    'phone' => $conversation->user->phone,
                ] : null,
                'formatted_last_activity' => $conversation->formatted_last_activity,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Accept a pending guest conversation.
     */
    public function accept(Request $request, Conversation $conversation): JsonResponse
    {
        $agent = Auth::user();
        $message = $this->chatService->acceptConversation($conversation, $agent);
        $conversation->load('assignedAgent');

        ActivityLogger::log('Chat', 'ACCEPT', "Agent {$agent->name} accepted conversation #{$conversation->id} ({$conversation->display_name})");

        return response()->json([
            'success' => true,
            'status' => $conversation->status,
            'assigned_agent_name' => $agent->name,
            'message' => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'user_id' => $message->user_id,
                'sender_name' => $agent->name,
                'message' => $message->message,
                'formatted_time' => $message->formatted_time,
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Send admin reply to guest conversation.
     */
    public function reply(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:5120'],
        ]);

        $attachmentUrl = null;
        $attachmentType = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('chat_attachments', 'public');
            $attachmentUrl = asset('storage/'.$path);
            $attachmentType = Str::contains($file->getMimeType(), 'image') ? 'image' : 'document';
        }

        $admin = Auth::user();

        $message = $this->chatService->sendMessage(
            $conversation,
            $validated['message'],
            'admin',
            $admin,
            $attachmentUrl,
            $attachmentType
        );

        ActivityLogger::log('Chat', 'REPLY', "Agent {$admin->name} replied to {$conversation->display_name}");

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'user_id' => $message->user_id,
                'sender_name' => $admin->name,
                'message' => $message->message,
                'attachment_url' => $message->attachment_url,
                'attachment_type' => $message->attachment_type,
                'is_read' => $message->is_read,
                'formatted_time' => $message->formatted_time,
                'formatted_date' => $message->formatted_date,
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Toggle conversation status (open/closed).
     */
    public function updateStatus(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,closed'],
        ]);

        $conversation = $this->chatService->updateStatus($conversation, $validated['status']);

        $agentName = Auth::user()->name;
        ActivityLogger::log('Chat', 'STATUS_CHANGE', "Agent {$agentName} set conversation #{$conversation->id} ({$conversation->display_name}) status to {$conversation->status}");

        return response()->json([
            'success' => true,
            'status' => $conversation->status,
            'message' => "Conversation status updated to {$conversation->status}.",
        ]);
    }

    /**
     * Mark all guest messages in a conversation as read.
     */
    public function markRead(Conversation $conversation): JsonResponse
    {
        $this->chatService->markAsRead($conversation, 'admin');

        return response()->json([
            'success' => true,
            'message' => 'Conversation marked as read.',
        ]);
    }

    /**
     * Delete a conversation.
     */
    public function destroy(Conversation $conversation): JsonResponse
    {
        $name = $conversation->display_name;
        $conversation->delete();

        ActivityLogger::log('Chat', 'DELETE', 'Agent '.Auth::user()->name." deleted conversation with {$name}");

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully.',
        ]);
    }
}
