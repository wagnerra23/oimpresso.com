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
use App\Contact;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\Tag;
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

        // US-WA-063: filtro por tags (multi-select query param `tags=1,3,5`).
        // Comportamento OR: conversa com QUALQUER das tags listadas aparece.
        $tagsFilter = $request->input('tags', '');
        if ($tagsFilter) {
            $tagIds = array_filter(array_map('intval', explode(',', $tagsFilter)));
            if (! empty($tagIds)) {
                $convQuery->whereHas('tags', fn ($q) => $q->whereIn('whatsapp_tags.id', $tagIds));
            }
        }

        $paginated = $convQuery
            ->with('tags:id,slug,label,color')
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
                // US-WA-063: eager-load tags · US-WA-064: eager-load Contact UltimatePOS
                ->with(['channel', 'tags:id,slug,label,color'])
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

        // US-WA-063: tags disponíveis no business (catálogo) + tags ativas
        // (filtro UI). Seed automático no 1º load do business sem tags.
        $this->ensureDefaultTags($businessId);
        $availableTags = Tag::query()
            ->where('business_id', $businessId)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'slug', 'label', 'color'])
            ->map(fn (Tag $t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'label' => $t->label,
                'color' => $t->color,
            ]);
        $activeTagIds = $tagsFilter
            ? array_filter(array_map('intval', explode(',', $tagsFilter)))
            : [];

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
            'availableTags' => $availableTags,
            'activeTagIds' => $activeTagIds,
            'centrifugoConfig' => $centrifugoConfig,
        ]);
    }

    /**
     * US-WA-063: garante que o business tem ao menos as 6 tags default
     * ("Vendas", "Suporte", "Reclamação", "Repair-OS", "Cobrança",
     * "Financeiro"). Idempotente — só insere se não existir o slug.
     *
     * Chamado uma vez por page load (lazy seed). Custo: 1 SELECT count;
     * INSERTs só na 1ª vez. Cheaper que migration global pq atende
     * multi-tenant Tier 0 sem precisar saber business_ids em advance.
     */
    protected function ensureDefaultTags(int $businessId): void
    {
        $existing = Tag::query()
            ->where('business_id', $businessId)
            ->count();
        if ($existing > 0) {
            return;
        }

        $defaults = [
            ['slug' => 'vendas',     'label' => 'Vendas',     'color' => 'emerald', 'sort_order' => 10],
            ['slug' => 'suporte',    'label' => 'Suporte',    'color' => 'blue',    'sort_order' => 20],
            ['slug' => 'reclamacao', 'label' => 'Reclamação', 'color' => 'red',     'sort_order' => 30],
            ['slug' => 'repair-os',  'label' => 'Repair-OS',  'color' => 'amber',   'sort_order' => 40],
            ['slug' => 'cobranca',   'label' => 'Cobrança',   'color' => 'purple',  'sort_order' => 50],
            ['slug' => 'financeiro', 'label' => 'Financeiro', 'color' => 'cyan',    'sort_order' => 60],
        ];
        foreach ($defaults as $d) {
            Tag::query()->create(array_merge($d, ['business_id' => $businessId]));
        }
    }

    /**
     * US-WA-063: PATCH `/atendimento/inbox/{id}/tags` — sync tags da conv.
     *
     * Body: `{tag_ids: number[]}`. Substitui (não merge) — atendente
     * desmarcar chips remove. Permission `whatsapp.send` (mesma do
     * updateStatus, é ação operacional).
     */
    public function updateTags(\Illuminate\Http\Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);
        $conversation = Conversation::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $payload = $request->validate([
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => ['integer'],
        ]);

        // Filtra tag_ids — só permite tags do MESMO business (Tier 0 ADR 0093).
        // Atacante mandando ids de outro business → silently dropped.
        $validIds = Tag::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $payload['tag_ids'])
            ->pluck('id')
            ->all();

        // sync com pivot `created_by_user_id` setado no atendente atual.
        $pivotData = [];
        foreach ($validIds as $tagId) {
            $pivotData[$tagId] = ['created_by_user_id' => $userId ?: null];
        }
        $conversation->tags()->sync($pivotData);

        return back()->with('success', 'Tags atualizadas.');
    }

    /**
     * Conversation pro componente lista (sidebar esquerda).
     * Shape compatível com `ListConversation` (helpers.ts) pra reusar
     * `ConversationList` legacy do Cockpit pattern V2 (ADR 0110).
     */
    protected function convToListArray(Conversation $c): array
    {
        $channel = $c->channel;
        // US-WA-072 — preview/direction lidos direto das colunas denormalizadas.
        // Antes (US-WA-070): `$c->messages()->reorder('created_at','desc')->first()`
        // disparava 50 queries N+1 no paginate(50). Agora 0 queries — colunas
        // são mantidas pelo MessageObserver::created() em sync com cada msg nova.

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
            // Preview (compat ConversationList legacy) — denormalizado (US-WA-072)
            'last_message_preview' => $c->last_message_preview,
            'last_message_direction' => $c->last_message_direction,
            // Window 24h Meta — só pra type=whatsapp_meta
            'within_24h_window' => $channel?->type === 'whatsapp_meta'
                ? ($c->last_inbound_at && $c->last_inbound_at->diffInHours(now()) < 24)
                : true, // Z-API/Baileys/Insta/etc não têm essa restrição
            // US-WA-063: tags aplicadas (eager-loaded no index() — sem N+1)
            'tags' => $c->relationLoaded('tags')
                ? $c->tags->map(fn ($t) => ['id' => $t->id, 'slug' => $t->slug, 'label' => $t->label, 'color' => $t->color])->all()
                : [],
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
            // US-WA-066: flag de bloqueio — UI esconde composer + badge no header
            'is_blocked' => (bool) $c->is_blocked,
            'within_24h_window' => $channel?->type === 'whatsapp_meta'
                ? ($c->last_inbound_at && $c->last_inbound_at->diffInHours(now()) < 24)
                : true,
            'last_inbound_at' => optional($c->last_inbound_at)->toIso8601String(),
            'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            'created_at' => optional($c->created_at)->toIso8601String(),
            'assigned_user' => null, // futuro: resolve via assigned_user_id
            'messages_total' => $c->messages()->count(),
            // US-WA-063: tags pra renderizar chips no ConversationSidebar
            'tags' => $c->relationLoaded('tags')
                ? $c->tags->map(fn ($t) => ['id' => $t->id, 'slug' => $t->slug, 'label' => $t->label, 'color' => $t->color])->all()
                : [],
            // US-WA-064: contato UltimatePOS vinculado (CRM). Null se ainda
            // não vinculado. Inicialmente vem null porque webhook cria
            // Conversation com contact_id=null. Atendente vincula via modal.
            'linked_contact' => $c->contact_id ? $this->resolveLinkedContact((int) $c->contact_id, $c->business_id) : null,
        ];
    }

    /**
     * US-WA-064: resolve Contact UltimatePOS vinculado pra exibir no sidebar.
     *
     * Retorna campos minimos pro card (avatar/nome/phone/email/tipo + link
     * pra `/contacts/{id}` UltimatePOS edit). Tier 0 enforced — só retorna
     * Contact do mesmo business_id (defense-in-depth — controller já filtra).
     */
    protected function resolveLinkedContact(int $contactId, int $businessId): ?array
    {
        $contact = Contact::query()
            ->where('id', $contactId)
            ->where('business_id', $businessId)
            ->first(['id', 'name', 'mobile', 'landline', 'email', 'type']);

        return $contact ? [
            'id' => $contact->id,
            'name' => $contact->name,
            'mobile' => $contact->mobile,
            'landline' => $contact->landline,
            'email' => $contact->email,
            'type' => $contact->type,
            'edit_url' => "/contacts/{$contact->id}",
        ] : null;
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
            // US-WA-071 (ADR 0142): flag pra UI renderizar bubble amarelo
            // de nota interna. Backend já garante via global scope que só
            // atendentes do business veem (Tier 0).
            'is_internal_note' => (bool) $m->is_internal_note,
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
            // US-WA-071 (ADR 0142): true = nota interna, NUNCA vai pro driver
            'is_internal_note' => ['nullable', 'boolean'],
        ]);

        $isInternalNote = (bool) ($data['is_internal_note'] ?? false);

        // Template + nota interna = combinação inválida (template é sempre cliente-facing)
        if ($isInternalNote && $data['kind'] === 'template') {
            return back()->withErrors(['send' => 'Template não pode ser nota interna — templates sempre vão pro cliente.']);
        }

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
        //
        // US-WA-071: notas internas nascem com status='sent' direto (sem
        // dispatch driver) — Centrifugo distribui pros atendentes do business.
        $message = Message::query()->create([
            'business_id' => $businessId,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'provider' => $channel->type,
            'type' => $data['kind'] === 'template' ? 'template' : 'text',
            'template_name' => $data['template_name'] ?? null,
            'body' => $data['body'] ?? null,
            'status' => $isInternalNote ? 'sent' : 'queued',
            'sender_user_id' => $userId ?: null,
            'sender_kind' => 'human',
            'is_internal_note' => $isInternalNote,
        ]);

        $conversation->forceFill([
            'last_outbound_at' => now(),
            'last_message_at' => now(),
        ])->save();

        // US-WA-071 Tier 0 IRREVOGÁVEL — nota interna NUNCA vai pro driver.
        // Gate aplicado AQUI antes de qualquer HTTP. ADR 0142 §1.
        // Métrica `internal_note_dispatch_to_driver_violation_24h` MUST be 0.
        if ($isInternalNote) {
            Log::info('[atendimento.inbox.send] internal note persisted (no driver dispatch)', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'business_id' => $businessId,
                'author_user_id' => $userId,
            ]);
            return back()->with('success', 'Nota interna salva.');
        }

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
     * US-WA-066: PATCH `/atendimento/inbox/{id}/block` — toggle bloqueio do contato.
     *
     * Body: `{block: bool}` (true = bloquear, false = desbloquear).
     *
     * Comportamento:
     *  1. Persiste `conversations.is_blocked` (defesa em profundidade — UI já
     *     respeita esse flag mesmo sem daemon).
     *  2. Chama daemon Baileys CT 100 `POST /instances/{id}/block` body
     *     `{jid, action}` pra `sock.updateBlockStatus(jid, 'block'|'unblock')`.
     *  3. Tolerância a 404 do daemon (graceful) — endpoint daemon pode não
     *     existir ainda; backend NÃO falha pro user, apenas loga warning.
     *     Ao Webhook inbound de conv blocked: handleMessage retorna
     *     'inbound_dropped_blocked' 200 ANTES do firstOrCreate.
     *
     * Permission `whatsapp.send` (mesma escala operacional do updateStatus).
     */
    public function blockContact(\Illuminate\Http\Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $conversation = Conversation::query()
            ->where('business_id', $businessId)
            ->with('channel')
            ->findOrFail($id);

        $payload = $request->validate([
            'block' => ['present', 'boolean'],
        ]);
        $shouldBlock = (bool) $payload['block'];

        // 1. Persiste flag — defesa em profundidade (UI respeita mesmo se daemon falhar)
        $conversation->forceFill(['is_blocked' => $shouldBlock])->save();

        // 2. Chama daemon CT 100 — tolera 404 (endpoint pode não existir ainda)
        $channel = $conversation->channel;
        if ($channel && $channel->type === Channel::TYPE_WHATSAPP_BAILEYS) {
            $daemonUrl = config('whatsapp.baileys.daemon_url');
            $apiKey = config('whatsapp.baileys.api_key');
            $instanceId = 'ch-' . str_replace('-', '', $channel->channel_uuid);
            // JID Baileys: phone sem '+' + sufixo @s.whatsapp.net
            $rawNumber = preg_replace('/^\+/', '', $conversation->customer_external_id);
            $jid = $rawNumber . '@s.whatsapp.net';
            $action = $shouldBlock ? 'block' : 'unblock';

            try {
                $response = Http::withToken($apiKey)
                    ->withoutVerifying() // FIXME(US-WA-058): cert LE pendente
                    ->timeout(10)
                    ->post("{$daemonUrl}/instances/{$instanceId}/block", [
                        'jid' => $jid,
                        'action' => $action,
                    ]);

                if ($response->status() === 404) {
                    // Daemon ainda não implementa o endpoint — graceful, não falha
                    Log::warning('[atendimento.inbox.block] daemon endpoint missing (404 graceful)', [
                        'channel_id' => $channel->id,
                        'action' => $action,
                    ]);
                } elseif (! $response->successful()) {
                    Log::warning('[atendimento.inbox.block] daemon error', [
                        'channel_id' => $channel->id,
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 200),
                    ]);
                }
            } catch (\Throwable $e) {
                // Erro de rede com daemon NÃO bloqueia o flag local — log + segue
                Log::warning('[atendimento.inbox.block] daemon exception (graceful)', [
                    'channel_id' => $channel->id,
                    'exception' => mb_substr($e->getMessage(), 0, 200),
                ]);
            }
        }

        $msg = $shouldBlock ? 'Contato bloqueado.' : 'Contato desbloqueado.';
        return back()->with('success', $msg);
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

    /**
     * US-WA-064: GET `/atendimento/inbox/contacts/search?q=...` — busca
     * Contacts UltimatePOS do business atual filtrando por nome/mobile/landline.
     *
     * Frontend usa pra debounced search no modal de vincular contato.
     * Multi-tenant Tier 0 (ADR 0093) — APENAS contacts do business atual,
     * NUNCA vaza CRM cross-tenant (especialmente PII LGPD CPF/CNPJ).
     *
     * Retorna até 15 results, ordem `name ASC`. Não inclui PII sensível
     * (tax_number/cpf_cnpj) — só dados de display.
     */
    public function searchContacts(\Illuminate\Http\Request $request): JsonResponse
    {
        $businessId = (int) session('user.business_id');
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['contacts' => []], 200);
        }

        // Sanitiza query — LIKE escape ` % _ \`
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $like = "%{$escaped}%";

        // Filtra type=customer/supplier/both (exclui lead — ainda não é cliente).
        // Permite buscar tanto por nome quanto por qualquer phone field (mobile,
        // landline, alternate_number).
        $contacts = Contact::query()
            ->where('business_id', $businessId)
            ->whereIn('type', ['customer', 'supplier', 'both'])
            ->where(function ($q2) use ($like) {
                $q2->where('name', 'LIKE', $like)
                    ->orWhere('mobile', 'LIKE', $like)
                    ->orWhere('landline', 'LIKE', $like)
                    ->orWhere('alternate_number', 'LIKE', $like)
                    ->orWhere('email', 'LIKE', $like)
                    ->orWhere('supplier_business_name', 'LIKE', $like);
            })
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'mobile', 'landline', 'email', 'type', 'supplier_business_name']);

        return response()->json([
            'contacts' => $contacts->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'mobile' => $c->mobile,
                'landline' => $c->landline,
                'email' => $c->email,
                'type' => $c->type,
                'supplier_business_name' => $c->supplier_business_name,
            ])->all(),
        ], 200);
    }

    /**
     * US-WA-064: PATCH `/atendimento/inbox/{id}/contact` — vincula ou
     * desvincula Contact UltimatePOS à Conversation.
     *
     * Body: `{contact_id: int|null}`. Null desvincula (reset contact_id=null).
     * Tier 0 enforced — contact_id que não pertence ao mesmo business_id
     * vira null (silently dropped, atacante neutralizado).
     */
    public function linkContact(\Illuminate\Http\Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $conversation = Conversation::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $payload = $request->validate([
            'contact_id' => ['nullable', 'integer'],
        ]);

        $contactId = $payload['contact_id'] ?? null;

        if ($contactId !== null) {
            // Valida que Contact pertence ao MESMO business (Tier 0 defense).
            // Sem withoutGlobalScope porque Contact não usa HasBusinessScope
            // (legacy UltimatePOS — controller já filtra explicito).
            $exists = Contact::query()
                ->where('id', $contactId)
                ->where('business_id', $businessId)
                ->exists();
            if (! $exists) {
                $contactId = null; // silently dropped cross-tenant
            }
        }

        $conversation->contact_id = $contactId;
        $conversation->save();

        return back()->with('success', $contactId ? 'Contato vinculado.' : 'Contato desvinculado.');
    }
}
