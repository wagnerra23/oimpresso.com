<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoTokenIssuer;

/**
 * InboxController — UI omnichannel `/atendimento/inbox` (ADR 0135 Fase 0).
 *
 * Substitui long-term `/whatsapp/conversations` legacy. Lê do schema novo
 * (Channel + Conversation + Message polimórficos) em vez de
 * WhatsappConversation/WhatsappMessage.
 *
 * Coexiste com `/whatsapp/conversations` durante PR B+C. Drivers/webhooks
 * legacy ainda processam Z-API/Meta Cloud no schema antigo até migration
 * completa.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
class InboxController extends Controller
{
    public function index(Request $request, CentrifugoTokenIssuer $tokenIssuer): Response
    {
        $businessId = (int) session('user.business_id');
        $tab = $request->input('tab', 'all');
        $q = $request->input('q', '');
        $threadId = $request->input('thread');
        $channelFilter = $request->input('channel'); // tipo: whatsapp_baileys, etc

        $convQuery = Conversation::query()
            ->where('business_id', $businessId)
            ->with('channel:id,label,type,status,channel_uuid,channel_health');

        // Filtros
        switch ($tab) {
            case 'unread':
                $convQuery->where('unread_count', '>', 0);
                break;
            case 'assigned':
                $userId = (int) (session('user.id') ?? auth()->id() ?? 0);
                $convQuery->where('assigned_user_id', $userId);
                break;
            case 'bot':
                $convQuery->where('bot_handling', true);
                break;
            case 'resolved':
                $convQuery->where('status', 'resolved');
                break;
        }

        if ($q !== '') {
            $convQuery->where(function ($x) use ($q) {
                $x->where('contact_name', 'LIKE', "%{$q}%")
                  ->orWhere('customer_external_id', 'LIKE', "%{$q}%");
            });
        }

        if ($channelFilter) {
            $convQuery->whereHas('channel', fn ($c) => $c->where('type', $channelFilter));
        }

        $paginated = $convQuery
            ->orderByDesc('last_message_at')
            ->paginate(50);

        $conversationsForUi = $paginated->getCollection()->map(fn (Conversation $c) => $this->convToListArray($c));

        // Stats counters
        $stats = [
            'unread' => Conversation::query()->where('business_id', $businessId)->where('unread_count', '>', 0)->count(),
            'assigned' => Conversation::query()->where('business_id', $businessId)
                ->where('assigned_user_id', (int) (session('user.id') ?? auth()->id() ?? 0))->count(),
            'bot' => Conversation::query()->where('business_id', $businessId)->where('bot_handling', true)->count(),
        ];

        // Thread aberta?
        $thread = null;
        $messages = null;
        if ($threadId) {
            $threadModel = Conversation::query()
                ->where('business_id', $businessId)
                ->with('channel')
                ->find($threadId);
            if ($threadModel) {
                $thread = $this->convToThreadArray($threadModel);
                // US-WA-077: eager-load `senderUser` pra evitar N+1 ao
                // renderizar nome do atendente acima de cada bubble outbound.
                $messages = Message::query()
                    ->where('business_id', $businessId)
                    ->where('conversation_id', $threadId)
                    ->with('senderUser:id,first_name,surname,last_name')
                    ->orderBy('created_at')
                    ->limit(200)
                    ->get()
                    ->map(fn (Message $m) => $this->msgToUiArray($m));

                // Zera unread quando abre
                if ($threadModel->unread_count > 0) {
                    $threadModel->forceFill(['unread_count' => 0])->save();
                }
            }
        }

        // Channels disponíveis pra filtro
        $availableChannels = Channel::query()
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->orderBy('label')
            ->get(['id', 'label', 'type'])
            ->map(fn ($ch) => ['id' => $ch->id, 'label' => $ch->label, 'type' => $ch->type]);

        // Centrifugo real-time (ADR 0058 + US-WA-059) — channel
        // `omnichannel:business:{id}` segregado por business_id (Tier 0).
        // Token JWT HS256 ttl curto, re-emitido a cada page load. Se
        // emissor falhar (secret ausente, etc), payload vira null e o
        // frontend cai pra polling fallback.
        $channel = "omnichannel:business:{$businessId}";
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);
        $token = $tokenIssuer->issue(
            $userId,
            [$channel],
            (int) config('whatsapp.centrifugo.token_ttl_seconds', 3600)
        );
        $centrifugoConfig = $token !== null ? [
            'wsUrl' => config('whatsapp.centrifugo.ws_url'),
            'token' => $token,
            'channel' => $channel,
        ] : null;

        return Inertia::render('Atendimento/Inbox/Index', [
            'conversations' => [
                'data' => $conversationsForUi,
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
            'tab' => $tab,
            'q' => $q,
            'channelFilter' => $channelFilter,
            'stats' => $stats,
            'businessId' => $businessId,
            'thread' => $thread,
            'messages' => $messages,
            'availableChannels' => $availableChannels,
            'centrifugoConfig' => $centrifugoConfig,
        ]);
    }

    /**
     * Conversation pro componente lista (sidebar esquerda).
     * Shape compatível com `ListConversation` (helpers.ts) pra reusar
     * `ConversationList` legacy do Cockpit pattern V2 (ADR 0110).
     */
    protected function convToListArray(Conversation $c): array
    {
        $channel = $c->channel;
        // reorder() limpa o `orderBy('created_at')` ASC default da relação
        // Conversation->messages() (Entities/Conversation.php). Sem isso,
        // o ASC ganhava e first() retornava a mensagem mais ANTIGA — daí
        // o preview mostrava lixo/vazio em vez do último texto. (US-WA-070)
        $lastMsg = $c->messages()->reorder('created_at', 'desc')->first();

        return [
            'id' => $c->id,
            'channel_id' => $c->channel_id,
            'channel_label' => $channel?->label,
            'channel_type' => $channel?->type,
            // Alias compat com Cockpit legacy (customer_phone) + customer_external_id polimórfico
            'customer_phone' => $c->customer_external_id,
            'customer_external_id' => $c->customer_external_id,
            'contact_name' => $c->contact_name ?? $c->customer_external_id,
            'status' => $c->status,
            'unread_count' => (int) $c->unread_count,
            'bot_handling' => (bool) $c->bot_handling,
            'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            'last_inbound_at' => optional($c->last_inbound_at)->toIso8601String(),
            // Preview (compat ConversationList legacy) — corte 80 chars
            'last_message_preview' => $lastMsg?->body ? mb_substr((string) $lastMsg->body, 0, 80) : null,
            'last_message_direction' => $lastMsg?->direction,
            // Window 24h Meta — só pra type=whatsapp_meta
            'within_24h_window' => $channel?->type === 'whatsapp_meta'
                ? ($c->last_inbound_at && $c->last_inbound_at->diffInHours(now()) < 24)
                : true, // Z-API/Baileys/Insta/etc não têm essa restrição
        ];
    }

    /**
     * Conversation pro componente thread (header central).
     * Shape compatível com `ThreadConversation` (helpers.ts).
     */
    protected function convToThreadArray(Conversation $c): array
    {
        $channel = $c->channel;
        return [
            'id' => $c->id,
            'channel_id' => $c->channel_id,
            'channel_label' => $channel?->label,
            'channel_type' => $channel?->type,
            // Alias compat com Cockpit legacy
            'customer_phone' => $c->customer_external_id,
            'customer_external_id' => $c->customer_external_id,
            'contact_name' => $c->contact_name ?? $c->customer_external_id,
            'status' => $c->status,
            'bot_handling' => (bool) $c->bot_handling,
            'within_24h_window' => $channel?->type === 'whatsapp_meta'
                ? ($c->last_inbound_at && $c->last_inbound_at->diffInHours(now()) < 24)
                : true,
            'last_inbound_at' => optional($c->last_inbound_at)->toIso8601String(),
            'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            'created_at' => optional($c->created_at)->toIso8601String(),
            'assigned_user' => null, // futuro: resolve via assigned_user_id
            'messages_total' => $c->messages()->count(),
        ];
    }

    /**
     * Message pro componente thread (bubble individual).
     */
    protected function msgToUiArray(Message $m): array
    {
        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'provider' => $m->provider,
            'type' => $m->type,
            'body' => $m->body,
            'status' => $m->status,
            'failed_reason' => $m->failed_reason,
            'sender_kind' => $m->sender_kind,
            // US-WA-077: nome curto do atendente que enviou (web UI). Null se
            // inbound, outbound do chip externo (Wagner pelo celular), ou bot.
            // Frontend MessageBubble renderiza acima da bubble outbound quando
            // sender_kind='human' E sender_user_name set (evita ambiguidade
            // quando time compartilha chip).
            'sender_user_name' => $this->resolveSenderUserName($m),
            'created_at' => $m->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    /**
     * Resolve nome curto do atendente pra exibir acima da bubble outbound.
     *
     * Prioridade: first_name → surname → last_name → "Atendente #id".
     * Fallback final cobre user com nome vazio (raro mas possível em legacy
     * UltimatePOS users criados via import).
     */
    protected function resolveSenderUserName(Message $m): ?string
    {
        $user = $m->senderUser;
        if (! $user) {
            return null;
        }

        return $user->first_name
            ?: $user->surname
            ?: $user->last_name
            ?: "Atendente #{$user->id}";
    }

    /**
     * Envia mensagem outbound pelo Channel apropriado (US-WA-069).
     *
     * Quick-path: chama daemon Baileys CT 100 direto (sem usar BaileysDriver
     * legacy que ainda consome WhatsappBusinessPhone). PR seguinte refatora
     * drivers pra aceitar Channel — então este método vira `Driver::send(...)`.
     *
     * Suporta só `whatsapp_baileys` por ora. Outros types retornam 422.
     */
    public function send(\Illuminate\Http\Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['freeform', 'template'])],
            'body' => ['required_if:kind,freeform', 'nullable', 'string', 'max:4096'],
            'template_name' => ['required_if:kind,template', 'nullable', 'string'],
            'template_locale' => ['nullable', 'string'],
            'template_params' => ['nullable', 'array'],
        ]);

        $conversation = Conversation::query()
            ->where('business_id', $businessId)
            ->with('channel')
            ->findOrFail($id);

        $channel = $conversation->channel;
        if (! $channel) {
            return back()->withErrors(['send' => 'Canal não associado à conversa.']);
        }

        // Persiste Message outbound em status=queued ANTES do dispatch — defesa
        // em profundidade. Se daemon falhar, a row já existe no DB pra retry.
        $message = Message::query()->create([
            'business_id' => $businessId,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'provider' => $channel->type,
            'type' => $data['kind'] === 'template' ? 'template' : 'text',
            'template_name' => $data['template_name'] ?? null,
            'body' => $data['body'] ?? null,
            'status' => 'queued',
            'sender_user_id' => $userId ?: null,
            'sender_kind' => 'human',
        ]);

        $conversation->forceFill([
            'last_outbound_at' => now(),
            'last_message_at' => now(),
        ])->save();

        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS) {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => "Envio só implementado pra Baileys nesta fase. Tipo atual: {$channel->type}",
            ])->save();
            return back()->withErrors(['send' => 'Envio só disponível pra canais Baileys nesta fase.']);
        }

        // Daemon Baileys: POST /instances/{instance_id}/text
        $daemonUrl = config('whatsapp.baileys.daemon_url');
        $apiKey = config('whatsapp.baileys.api_key');
        $instanceId = 'ch-' . str_replace('-', '', $channel->channel_uuid);
        $toPhone = preg_replace('/^\+/', '', $conversation->customer_external_id);

        try {
            $response = Http::withToken($apiKey)
                ->withoutVerifying() // FIXME(US-WA-058): cert LE pendente
                ->timeout(15)
                ->post("{$daemonUrl}/instances/{$instanceId}/text", [
                    'to' => $toPhone,
                    'text' => $data['body'],
                ]);

            if (! $response->successful()) {
                $message->forceFill([
                    'status' => 'failed',
                    'failed_reason' => 'Daemon ' . $response->status() . ': ' . mb_substr($response->body(), 0, 200),
                ])->save();
                Log::warning('[atendimento.inbox.send] daemon error', [
                    'channel_id' => $channel->id,
                    'status' => $response->status(),
                ]);
                return back()->withErrors(['send' => 'Falha ao enviar via daemon. Veja status da mensagem.']);
            }

            $payload = $response->json();
            $message->forceFill([
                'status' => $payload['status'] ?? 'sent',
                'provider_message_id' => $payload['message_id'] ?? null,
            ])->save();

            return back()->with('success', 'Mensagem enviada.');
        } catch (\Throwable $e) {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => mb_substr($e->getMessage(), 0, 240),
            ])->save();
            Log::error('[atendimento.inbox.send] exception', [
                'channel_id' => $channel->id,
                'exception' => $e->getMessage(),
            ]);
            return back()->withErrors(['send' => 'Erro de rede com daemon: ' . $e->getMessage()]);
        }
    }

    /**
     * PATCH status (atribuir a mim, marcar resolvido, etc).
     */
    public function updateStatus(\Illuminate\Http\Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);
        $conversation = Conversation::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $payload = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'awaiting_human', 'resolved', 'archived'])],
            'assigned_to_me' => ['nullable', 'boolean'],
            'bot_handling' => ['nullable', 'boolean'],
        ]);

        if (isset($payload['status'])) {
            $conversation->status = $payload['status'];
        }
        if (array_key_exists('assigned_to_me', $payload)) {
            $conversation->assigned_user_id = $payload['assigned_to_me'] ? $userId : null;
        }
        if (array_key_exists('bot_handling', $payload)) {
            $conversation->bot_handling = (bool) $payload['bot_handling'];
        }
        $conversation->save();

        return back()->with('success', 'Conversa atualizada.');
    }
}
