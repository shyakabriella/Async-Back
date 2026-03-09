<?php

namespace App\Http\Controllers\API;

use App\Events\ChatMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\AgentPresence;
use App\Models\ChatConversation;
use App\Services\AiSupportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SupportChatController extends Controller
{
    public function __construct(
        protected AiSupportService $aiSupportService
    ) {
    }

    public function status()
    {
        $online = AgentPresence::query()
            ->where('is_online', true)
            ->where('last_seen_at', '>=', now()->subMinutes(2))
            ->exists();

        return response()->json([
            'success' => true,
            'online' => $online,
        ]);
    }

    public function startSession(Request $request)
    {
        $validated = $request->validate([
            'guest_name' => ['nullable', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email', 'max:255'],
        ]);

        $conversation = ChatConversation::create([
            'public_token' => (string) Str::uuid(),
            'guest_name' => $validated['guest_name'] ?? null,
            'guest_email' => $validated['guest_email'] ?? null,
            'status' => 'open',
            'bot_enabled' => true,
            'last_message_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'conversation' => $this->conversationPayload($conversation->fresh()),
            'messages' => [],
        ], 201);
    }

    public function showSession(string $token)
    {
        $conversation = ChatConversation::query()
            ->where('public_token', $token)
            ->with(['messages' => fn ($q) => $q->orderBy('id')])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'conversation' => $this->conversationPayload($conversation),
            'messages' => $conversation->messages->map(fn ($m) => $this->messagePayload($m)),
        ]);
    }

    public function sendGuestMessage(Request $request, string $token)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $conversation = ChatConversation::query()
            ->where('public_token', $token)
            ->with('messages')
            ->firstOrFail();

        if ($conversation->status === 'closed') {
            $conversation->update(['status' => 'open']);
        }

        $customerMessage = $conversation->messages()->create([
            'sender_type' => 'customer',
            'sender_id' => null,
            'message' => $validated['message'],
        ]);

        $conversation->update([
            'last_message_at' => now(),
        ]);

        broadcast(new ChatMessageCreated($customerMessage));

        $onlinePresence = AgentPresence::query()
            ->where('is_online', true)
            ->where('last_seen_at', '>=', now()->subMinutes(2))
            ->orderByDesc('last_seen_at')
            ->first();

        if ($onlinePresence) {
            if (!$conversation->assigned_agent_id) {
                $conversation->update([
                    'assigned_agent_id' => $onlinePresence->user_id,
                ]);
            }

            $conversation->update([
                'bot_enabled' => false,
            ]);

            return response()->json([
                'success' => true,
                'mode' => 'human',
                'customer_message' => $this->messagePayload($customerMessage),
                'conversation' => $this->conversationPayload($conversation->fresh()),
            ]);
        }

        $botReply = null;

        if ($conversation->bot_enabled) {
            $botReply = $this->aiSupportService->reply($conversation);
            $conversation->update([
                'last_message_at' => now(),
            ]);

            broadcast(new ChatMessageCreated($botReply));
        }

        return response()->json([
            'success' => true,
            'mode' => 'bot',
            'customer_message' => $this->messagePayload($customerMessage),
            'bot_reply' => $botReply ? $this->messagePayload($botReply) : null,
            'conversation' => $this->conversationPayload($conversation->fresh()),
        ]);
    }

    public function agentConversations()
    {
        $conversations = ChatConversation::query()
            ->with(['latestMessage', 'assignedAgent'])
            ->where('status', 'open')
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $conversations->map(fn ($c) => $this->conversationPayload($c, true)),
        ]);
    }

    public function agentShowConversation(int $id)
    {
        $conversation = ChatConversation::query()
            ->with(['messages' => fn ($q) => $q->orderBy('id'), 'assignedAgent'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'conversation' => $this->conversationPayload($conversation, true),
            'messages' => $conversation->messages->map(fn ($m) => $this->messagePayload($m)),
        ]);
    }

    public function agentSendMessage(Request $request, int $id)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $conversation = ChatConversation::query()->findOrFail($id);

        $conversation->update([
            'assigned_agent_id' => Auth::id(),
            'bot_enabled' => false,
            'last_message_at' => now(),
        ]);

        $agentMessage = $conversation->messages()->create([
            'sender_type' => 'agent',
            'sender_id' => Auth::id(),
            'message' => $validated['message'],
        ]);

        broadcast(new ChatMessageCreated($agentMessage));

        return response()->json([
            'success' => true,
            'message' => $this->messagePayload($agentMessage),
            'conversation' => $this->conversationPayload($conversation->fresh(), true),
        ]);
    }

    public function takeOver(int $id)
    {
        $conversation = ChatConversation::query()->findOrFail($id);

        $conversation->update([
            'assigned_agent_id' => Auth::id(),
            'bot_enabled' => false,
        ]);

        return response()->json([
            'success' => true,
            'conversation' => $this->conversationPayload($conversation->fresh(), true),
        ]);
    }

    public function closeConversation(int $id)
    {
        $conversation = ChatConversation::query()->findOrFail($id);

        $conversation->update([
            'status' => 'closed',
        ]);

        return response()->json([
            'success' => true,
            'conversation' => $this->conversationPayload($conversation->fresh(), true),
        ]);
    }

    public function agentPresence(Request $request)
    {
        $validated = $request->validate([
            'is_online' => ['required', 'boolean'],
        ]);

        AgentPresence::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'is_online' => $validated['is_online'],
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
        ]);
    }

    protected function conversationPayload(ChatConversation $conversation, bool $includeLatest = false): array
    {
        $payload = [
            'id' => $conversation->id,
            'public_token' => $conversation->public_token,
            'guest_name' => $conversation->guest_name,
            'guest_email' => $conversation->guest_email,
            'assigned_agent_id' => $conversation->assigned_agent_id,
            'assigned_agent_name' => optional($conversation->assignedAgent)->name,
            'status' => $conversation->status,
            'bot_enabled' => (bool) $conversation->bot_enabled,
            'last_message_at' => optional($conversation->last_message_at)->toISOString(),
            'created_at' => optional($conversation->created_at)->toISOString(),
        ];

        if ($includeLatest && $conversation->relationLoaded('latestMessage') && $conversation->latestMessage) {
            $payload['latest_message'] = $this->messagePayload($conversation->latestMessage);
        }

        return $payload;
    }

    protected function messagePayload($message): array
    {
        return [
            'id' => $message->id,
            'chat_conversation_id' => $message->chat_conversation_id,
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'message' => $message->message,
            'created_at' => optional($message->created_at)->toISOString(),
        ];
    }
}