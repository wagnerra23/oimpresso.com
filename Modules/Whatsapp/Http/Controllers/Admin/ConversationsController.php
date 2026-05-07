<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\WhatsappConversation;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Http\Requests\SendMessageRequest;
use Modules\Whatsapp\Jobs\SendWhatsappMessageJob;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoTokenIssuer;

/**
 * Inbox conversas — Cockpit pattern (lista esquerda + chat painel direita).
 *
 * Decisão visual canon: ADR 0039 (Chat Cockpit). Real-time via Centrifugo
 * channel `whatsapp:business:{id}`.
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-012
 */
class ConversationsController extends Controller
{
    public function index(Request $request, CentrifugoTokenIssuer $tokenIssuer): Response
    {
        $tab = (string) $request->query('tab', 'all'); // all|unread|assigned|bot|resolved
        $search = trim((string) $request->query('q', ''));
        $threadId = $request->integer('thread');
        $businessId = (int) session('user.business_id');

        // Subquery última mensagem (preview body + direction) — evita N+1.
        $lastMsgBody = WhatsappMessage::query()
            ->select('body')
            ->whereColumn('conversation_id', 'whatsapp_conversations.id')
            ->orderByDesc('created_at')
            ->limit(1);

        $lastMsgDir = WhatsappMessage::query()
            ->select('direction')
            ->whereColumn('conversation_id', 'whatsapp_conversations.id')
            ->orderByDesc('created_at')
            ->limit(1);

        $query = WhatsappConversation::query()
            ->with('contact:id,name')
            ->select('whatsapp_conversations.*')
            ->selectSub($lastMsgBody, 'last_message_body')
            ->selectSub($lastMsgDir, 'last_message_direction')
            ->orderByDesc('last_message_at');

        $query = match ($tab) {
            'unread' => $query->where('unread_count', '>', 0),
            'assigned' => $query->where('assigned_user_id', $request->user()?->id),
            'bot' => $query->where('bot_handling', true),
            'resolved' => $query->where('status', 'resolved'),
            default => $query->whereNotIn('status', ['archived']),
        };

        // Search server-side: telefone OR contact.name (LIKE simples; FTS futuramente)
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('customer_phone', 'like', $like)
                  ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', $like));
            });
        }

        $conversations = $query->paginate(50)->withQueryString()->through(fn ($c) => [
            'id' => $c->id,
            'customer_phone' => $c->customer_phone,
            'contact_name' => $c->contact?->name ?? $c->customer_phone,
            'status' => $c->status,
            'unread_count' => $c->unread_count,
            'bot_handling' => (bool) $c->bot_handling,
            'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            'last_inbound_at' => optional($c->last_inbound_at)->toIso8601String(),
            'within_24h_window' => $c->isWithinMeta24hWindow(),
            'last_message_preview' => $c->last_message_body !== null
                ? mb_substr((string) $c->last_message_body, 0, 80)
                : null,
            'last_message_direction' => $c->last_message_direction,
        ]);

        $stats = [
            'unread' => WhatsappConversation::where('unread_count', '>', 0)->count(),
            'assigned' => WhatsappConversation::where('assigned_user_id', $request->user()?->id)->count(),
            'bot' => WhatsappConversation::where('bot_handling', true)->count(),
        ];

        // Cockpit split-view: quando ?thread=X presente, hidrata thread + messages
        // na mesma resposta. Frontend faz partial reload (only:[thread,messages]) ao
        // clicar conversa — sem reload da lista.
        $threadPayload = null;
        if ($threadId) {
            $threadPayload = $this->loadThreadPayload($threadId, $tokenIssuer);
        }

        return Inertia::render('Whatsapp/Conversations/Index', [
            'conversations' => $conversations,
            'tab' => $tab,
            'q' => $search,
            'stats' => $stats,
            'businessId' => $businessId,
            'thread' => $threadPayload['conversation'] ?? null,
            'messages' => $threadPayload['messages'] ?? null,
            'centrifugoConfig' => $threadPayload['centrifugoConfig'] ?? null,
            'centrifugoChannel' => $threadPayload['channel'] ?? null,
        ]);
    }

    public function show(int $id, CentrifugoTokenIssuer $tokenIssuer): Response
    {
        $payload = $this->loadThreadPayload($id, $tokenIssuer);

        return Inertia::render('Whatsapp/Conversations/Show', [
            'conversation' => $payload['conversation'],
            'messages' => $payload['messages'],
            'centrifugoChannel' => $payload['channel'],
            'centrifugoConfig' => $payload['centrifugoConfig'],
        ]);
    }

    /**
     * Hidrata payload de uma conversa (thread + messages + Centrifugo).
     *
     * Reusado por show() (rota permalink) e index() (cockpit split-view com ?thread=X).
     * Marca conversa como lida (zera unread_count) — efeito colateral intencional:
     * abrir thread no cockpit equivale a "ler".
     *
     * @return array{conversation: array<string,mixed>, messages: \Illuminate\Support\Collection<int,array<string,mixed>>, channel: string, centrifugoConfig: ?array{wsUrl: ?string, token: string, channel: string}}
     */
    protected function loadThreadPayload(int $id, CentrifugoTokenIssuer $tokenIssuer): array
    {
        $conversation = WhatsappConversation::with(['contact:id,name', 'assignedUser:id,first_name,last_name,email'])
            ->findOrFail($id);

        $messages = WhatsappMessage::where('conversation_id', $id)
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'provider' => $m->provider,
                'type' => $m->type,
                'body' => $m->body,
                'status' => $m->status,
                'failed_reason' => $m->failed_reason,
                'sender_kind' => $m->sender_kind,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        $messagesTotal = WhatsappMessage::where('conversation_id', $id)->count();

        $assignedUser = $conversation->assignedUser
            ? [
                'id' => $conversation->assignedUser->id,
                'name' => trim(($conversation->assignedUser->first_name ?? '').' '.($conversation->assignedUser->last_name ?? '')) ?: ($conversation->assignedUser->email ?? '—'),
            ]
            : null;

        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }

        $channel = "whatsapp:business:{$conversation->business_id}";
        $userId = (int) (auth()->id() ?? 0);
        $token = $tokenIssuer->issue($userId, [$channel], (int) config('whatsapp.centrifugo.token_ttl_seconds', 3600));
        $centrifugoConfig = $token !== null ? [
            'wsUrl' => config('whatsapp.centrifugo.ws_url'),
            'token' => $token,
            'channel' => $channel,
        ] : null;

        return [
            'conversation' => [
                'id' => $conversation->id,
                'customer_phone' => $conversation->customer_phone,
                'contact_name' => $conversation->contact?->name ?? $conversation->customer_phone,
                'status' => $conversation->status,
                'bot_handling' => (bool) $conversation->bot_handling,
                'within_24h_window' => $conversation->isWithinMeta24hWindow(),
                'last_inbound_at' => optional($conversation->last_inbound_at)->toIso8601String(),
                'last_message_at' => optional($conversation->last_message_at)->toIso8601String(),
                'created_at' => optional($conversation->created_at)->toIso8601String(),
                'assigned_user' => $assignedUser,
                'messages_total' => $messagesTotal,
            ],
            'messages' => $messages,
            'channel' => $channel,
            'centrifugoConfig' => $centrifugoConfig,
        ];
    }

    /**
     * PATCH /whatsapp/conversations/{id}
     *
     * Mudanças de estado da conversa (Sidebar Cockpit ações):
     * - status: open|awaiting_human|resolved|archived
     * - bot_handling: liga/desliga bot
     * - assigned_to_me: atribui ao usuário corrente (ou null pra liberar)
     *
     * Multi-tenant Tier 0 (ADR 0093): WhatsappConversation tem HasBusinessScope
     * — findOrFail só encontra rows do business corrente.
     */
    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        if (! $request->user()?->can('whatsapp.send')) {
            abort(403);
        }

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'awaiting_human', 'resolved', 'archived'])],
            'bot_handling' => ['sometimes', 'boolean'],
            'assigned_to_me' => ['sometimes', 'boolean'],
        ]);

        $conversation = WhatsappConversation::findOrFail($id);

        $changes = [];
        if (array_key_exists('status', $data)) {
            $changes['status'] = $data['status'];
        }
        if (array_key_exists('bot_handling', $data)) {
            $changes['bot_handling'] = $data['bot_handling'];
        }
        if (array_key_exists('assigned_to_me', $data)) {
            $changes['assigned_user_id'] = $data['assigned_to_me'] ? $request->user()->id : null;
        }

        if ($changes !== []) {
            $conversation->update($changes);
        }

        return back()->with('status', 'Conversa atualizada.');
    }

    /**
     * POST /whatsapp/conversations/{id}/send
     *
     * Envio manual via UI Composer. FormRequest valida regras (janela 24h
     * pra meta_cloud freeform, kind=template/media específicos).
     *
     * Dispatch SendWhatsappMessageJob que aplica fallback runtime + retry exponencial.
     *
     * @see memory/requisitos/Whatsapp/SPEC.md US-WA-003
     */
    public function send(SendMessageRequest $request, int $id): RedirectResponse
    {
        $conversation = WhatsappConversation::findOrFail($id);
        $kind = $request->validated()['kind'];

        $payload = match ($kind) {
            'freeform' => ['body' => $request->validated()['body']],
            'template' => [
                'name' => $request->validated()['template_name'],
                'params' => $request->validated()['template_params'] ?? [],
                'locale' => $request->validated()['template_locale'] ?? 'pt_BR',
            ],
            'media' => [
                'url' => $request->validated()['media_url'],
                'type' => $request->validated()['media_type'],
                'caption' => $request->validated()['media_caption'] ?? null,
            ],
        };

        SendWhatsappMessageJob::dispatch(
            $conversation->business_id,
            $conversation->customer_phone,
            $kind,
            $payload,
        );

        return back()->with('status', 'Mensagem enfileirada — entregue em segundos.');
    }
}
