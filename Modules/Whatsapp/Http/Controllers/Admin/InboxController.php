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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Contact;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\Tag;
use Modules\Whatsapp\Jobs\SendInteractiveJob;
use Modules\Whatsapp\Jobs\SendMediaJob;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoTokenIssuer;
use Modules\Whatsapp\Services\Notes\SlashCommandParser;
use Modules\Whatsapp\Services\Notes\SlashCommandRegistry;
use Modules\Whatsapp\Services\Notes\SlashCommandResult;

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
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);
        $tab = $request->input('tab', 'all');
        $q = $request->input('q', '');
        $threadId = $request->input('thread');
        $channelFilter = $request->input('channel'); // tipo: whatsapp_baileys, etc

        // CYCLE-08 PR-A (US-WA-040): filtro POR CANAL específico via dropdown
        // topbar (`?channel_id=N`). Diferente de `channel` (que filtra por TYPE).
        // Quando user passa `channel_id`, validamos ACL ANTES da query — sem
        // acesso = 403 (fail-loud), evita confusão "filtro retorna vazio".
        $selectedChannelId = $request->has('channel_id') && $request->input('channel_id') !== ''
            ? (int) $request->input('channel_id')
            : null;
        if ($selectedChannelId !== null) {
            $this->ensureChannelIdAccessOrAbort($selectedChannelId, $businessId, $userId);
        }

        $convQuery = Conversation::query()
            ->where('business_id', $businessId)
            ->with('channel:id,label,type,status,channel_uuid,channel_health');

        // US-WA-069 (ADR 0135 canal=fila): filtra conversas pelos canais que o
        // user tem ACL ativa em `channel_user_access`. Gate
        // `whatsapp.view-all-phones` é o ÚNICO bypass (admin/superadmin).
        //
        // Defense-in-depth ON TOP do business_id global scope — Tier 0 ADR 0093
        // continua garantindo isolamento entre businesses; este filtro adiciona
        // segregação per-canal/fila DENTRO do mesmo business.
        $this->applyChannelAclFilter($convQuery, $businessId, $userId);

        // CYCLE-08 PR-A: aplica filtro per-canal específico DEPOIS do ACL filter.
        // Composição: user precisa ter acesso AO canal + canal precisa bater.
        if ($selectedChannelId !== null) {
            $convQuery->where('channel_id', $selectedChannelId);
        }

        // Filtros — tabs por status/condição. `awaiting_human` e `archived`
        // mapeiam pro enum `conversations.status` (criados em US-WA-* prévia,
        // tab visual faltando).
        switch ($tab) {
            case 'unread':
                $convQuery->where('unread_count', '>', 0);
                break;
            case 'assigned':
                $convQuery->where('assigned_user_id', $userId);
                break;
            case 'bot':
                $convQuery->where('bot_handling', true);
                break;
            case 'resolved':
                $convQuery->where('status', 'resolved');
                break;
            case 'awaiting_human':
                // Bot escalou pra humano — fila de atendimento manual.
                $convQuery->where('status', 'awaiting_human');
                break;
            case 'archived':
                // Conversa arquivada pelo atendente — fora do operacional dia-a-dia.
                $convQuery->where('status', 'archived');
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

        // Filtro `within_24h` — janela 24h da Meta WhatsApp Cloud.
        // true  → conversas com `last_inbound_at` >= 24h atrás (freeform OK)
        // false → conversas com `last_inbound_at` < 24h atrás OU null (precisa HSM)
        // Útil pro atendente decidir quem ainda dá pra mandar freeform.
        if ($request->has('within_24h')) {
            if ($request->boolean('within_24h')) {
                $convQuery->where('last_inbound_at', '>=', now()->subHours(24));
            } else {
                $convQuery->where(function ($q2) {
                    $q2->whereNull('last_inbound_at')
                       ->orWhere('last_inbound_at', '<', now()->subHours(24));
                });
            }
        }

        // Filtro `unlinked` — sem Contact CRM UltimatePOS vinculado.
        // Oportunidade pra atendente cadastrar/vincular contato existente.
        if ($request->boolean('unlinked')) {
            $convQuery->whereNull('contact_id');
        }

        // US-WA-043 (PR-8 CYCLE-07) — filtro `media_inbound_24h`: conversas
        // que receberam mídia (image/audio/video/document) inbound nas
        // últimas 24h. Útil pra atendente revisar fotos/áudios de clientes
        // sem ter que abrir conv por conv.
        //
        // Custo: 1 subquery por hit (`whereHas('messages')` vira EXISTS),
        // indexada via `messages.business_id + created_at` já presente.
        if ($request->boolean('media_inbound_24h')) {
            $convQuery->whereHas('messages', fn ($q) => $q
                ->where('direction', 'inbound')
                ->whereIn('type', ['image', 'audio', 'video', 'document'])
                ->where('created_at', '>=', now()->subHours(24)));
        }

        // Filtro `inbound_aging` — última msg do cliente há > X tempo E cliente
        // foi o último a falar. Fila SLA "esperando resposta". Whitelist do
        // valor (enum) bloqueia SQL injection via input não confiável.
        $inboundAging = $request->input('inbound_aging');
        if ($inboundAging) {
            $hours = match ($inboundAging) {
                '6h' => 6,
                '12h' => 12,
                '24h' => 24,
                '48h' => 48,
                '7d' => 168,
                default => null,
            };
            if ($hours !== null) {
                $convQuery
                    ->whereNotNull('last_inbound_at')
                    ->where('last_inbound_at', '<', now()->subHours($hours))
                    ->where(function ($q2) {
                        // Só conta como "esperando resposta" se cliente foi o
                        // último a falar (atendente ainda não respondeu OU
                        // resposta veio antes da última msg do cliente).
                        $q2->whereNull('last_outbound_at')
                           ->orWhereColumn('last_outbound_at', '<', 'last_inbound_at');
                    });
            }
        }

        // Ordenação: default `last_message_at` (mais recente). Opção `inbound`
        // ordena por `last_inbound_at` desc — útil pra ver primeiro quem está
        // esperando resposta (visão SLA-first).
        $orderBy = $request->input('orderBy');
        $orderColumn = $orderBy === 'inbound' ? 'last_inbound_at' : 'last_message_at';

        // US-WA-043 — `last_message_type` exposto via subquery scalar pra UI
        // mostrar ícone semântico (📷 image / 🎵 audio / 🎥 video / 📄 document)
        // ao lado do preview da última msg. Evita N+1 escolhendo subquery
        // correlated 1× por row no SELECT (1 EXISTS por linha, indexada em
        // `messages.conversation_id` + `created_at`).
        $paginated = $convQuery
            ->with('tags:id,slug,label,color')
            ->addSelect(['last_message_type' => Message::query()
                ->select('type')
                ->whereColumn('conversation_id', 'conversations.id')
                ->orderByDesc('created_at')
                ->limit(1),
            ])
            ->orderByDesc($orderColumn)
            ->paginate(50);

        $conversationsForUi = $paginated->getCollection()->map(fn (Conversation $c) => $this->convToListArray($c));

        // Stats counters — também filtrados por ACL canal (US-WA-069) pra
        // contadores baterem com a lista efetivamente visível.
        $statsBase = fn () => tap(
            Conversation::query()->where('business_id', $businessId),
            fn ($q) => $this->applyChannelAclFilter($q, $businessId, $userId)
        );

        $stats = [
            'unread' => $statsBase()->where('unread_count', '>', 0)->count(),
            'assigned' => $statsBase()->where('assigned_user_id', $userId)->count(),
            'bot' => $statsBase()->where('bot_handling', true)->count(),
            // Novos tabs (US-WA-* filtros novos)
            'awaiting_human' => $statsBase()->where('status', 'awaiting_human')->count(),
            'archived' => $statsBase()->where('status', 'archived')->count(),
        ];

        // Thread aberta?
        $thread = null;
        $messages = null;
        if ($threadId) {
            // US-WA-069: tenta achar com ACL ativo — se user não tem acesso ao
            // canal, thread vira null (UI mostra lista vazia, sem 500).
            $threadQuery = Conversation::query()
                ->where('business_id', $businessId)
                // US-WA-063: eager-load tags · US-WA-064: eager-load Contact UltimatePOS
                ->with(['channel', 'tags:id,slug,label,color']);
            $this->applyChannelAclFilter($threadQuery, $businessId, $userId);
            $threadModel = $threadQuery->find($threadId);
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

        // Channels disponíveis pra filtro — US-WA-069: filtrados por ACL.
        // User sem acesso a NENHUM canal vê lista vazia (não 500).
        //
        // CYCLE-08 PR-A (US-WA-040): incluir `display_identifier` (phone E.164)
        // + `channel_health` (semáforo healthy/degraded/disconnected/banned) +
        // `unread_count` (badge per-canal). Frontend `ChannelSelector` renderiza
        // dropdown topbar com esses dados quando user tem 2+ canais.
        $availableChannelsQuery = Channel::query()
            ->where('business_id', $businessId)
            ->where('status', 'active');
        if (! $this->canSeeAllChannels()) {
            $availableChannelsQuery->whereIn('id', $this->allowedChannelIdsSubquery($businessId, $userId));
        }
        $availableChannelsRaw = $availableChannelsQuery
            ->orderBy('label')
            ->get(['id', 'label', 'type', 'display_identifier', 'channel_health']);

        // Unread count per-canal (1 query agregada — escala em N canais sem N+1).
        // Filtrada pelos channel_ids visíveis ao user pra evitar leak cross-canal.
        $visibleChannelIds = $availableChannelsRaw->pluck('id')->all();
        $unreadByChannel = empty($visibleChannelIds)
            ? collect()
            : Conversation::query()
                ->where('business_id', $businessId)
                ->whereIn('channel_id', $visibleChannelIds)
                ->where('unread_count', '>', 0)
                ->selectRaw('channel_id, SUM(unread_count) as total_unread')
                ->groupBy('channel_id')
                ->pluck('total_unread', 'channel_id');

        $availableChannels = $availableChannelsRaw->map(fn ($ch) => [
            'id' => $ch->id,
            'label' => $ch->label,
            'type' => $ch->type,
            'display_identifier' => $ch->display_identifier,
            'channel_health' => $ch->channel_health,
            'unread_count' => (int) ($unreadByChannel[$ch->id] ?? 0),
        ]);

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
            // Filtros novos — passa estado pra UI re-renderizar chips/dropdown.
            // `within_24h` chega como bool|null (request->boolean retorna false
            // p/ ausente — usar has() pra distinguir "não filtrado" de "false").
            'within24h' => $request->has('within_24h') ? $request->boolean('within_24h') : null,
            'unlinked' => $request->boolean('unlinked'),
            'mediaInbound24h' => $request->boolean('media_inbound_24h'),
            'inboundAging' => $request->input('inbound_aging'),
            'orderBy' => $request->input('orderBy', 'last_message'),
            'businessId' => $businessId,
            'thread' => $thread,
            'messages' => $messages,
            'availableChannels' => $availableChannels,
            // CYCLE-08 PR-A (US-WA-040): channel_id ativo no dropdown topbar
            // (null = "Todos os canais"). Frontend usa pra marcar item selecionado
            // no ChannelSelector + manter estado entre partial reloads.
            'selectedChannelId' => $selectedChannelId,
            'availableTags' => $availableTags,
            'activeTagIds' => $activeTagIds,
            'centrifugoConfig' => $centrifugoConfig,
            // Caixa Unificada v4 — config static das filas (sem DB).
            // Frontend usa pra renderizar pílulas + cor (hue) + SLA.
            'queues' => (array) config('whatsapp.queues', []),
            'defaultQueue' => (string) config('whatsapp.default_queue', 'comercial'),
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

        // US-WA-069 defense-in-depth: user sem acesso ao canal não muta tags.
        $this->ensureChannelAccessOrAbort($conversation, $businessId, $userId);

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
            // US-WA-043 — type da última msg pra UI ícone semântico (📷/🎵/🎥/📄)
            'last_message_type' => $c->getAttribute('last_message_type'),
            // Window 24h Meta — só pra type=whatsapp_meta
            'within_24h_window' => $channel?->type === 'whatsapp_meta'
                ? ($c->last_inbound_at && $c->last_inbound_at->diffInHours(now()) < 24)
                : true, // Z-API/Baileys/Insta/etc não têm essa restrição
            // US-WA-063: tags aplicadas (eager-loaded no index() — sem N+1)
            'tags' => $c->relationLoaded('tags')
                ? $c->tags->map(fn ($t) => ['id' => $t->id, 'slug' => $t->slug, 'label' => $t->label, 'color' => $t->color])->all()
                : [],
            // Caixa Unificada v4 — fila derivada (heurística tag → fila).
            // Read-only nesta passada (RUNBOOK §4.4 Non-Goal: mover entre filas).
            'queue' => $this->deriveQueueFromTags(
                $c->relationLoaded('tags') ? $c->tags->pluck('slug')->all() : []
            ),
        ];
    }

    /**
     * Caixa Unificada v4 — heurística tag → fila.
     *
     * Lê `config('whatsapp.queues')` e retorna a 1ª fila cujo `trigger_tags`
     * intersecta com tags da conversa. Fallback = `config('whatsapp.default_queue')`.
     * Determinístico — mesma input gera mesmo output (ordem dos triggers
     * preserva ordem do config).
     */
    protected function deriveQueueFromTags(array $tagSlugs): array
    {
        $queues = (array) config('whatsapp.queues', []);
        $default = (string) config('whatsapp.default_queue', 'comercial');
        $matched = $default;
        foreach ($queues as $slug => $cfg) {
            $triggers = (array) ($cfg['trigger_tags'] ?? []);
            if ($triggers === []) {
                continue;
            }
            if (array_intersect($tagSlugs, $triggers) !== []) {
                $matched = $slug;
                break;
            }
        }
        $cfg = $queues[$matched] ?? ['label' => ucfirst($matched), 'hue' => 0, 'sla' => null];
        return [
            'slug' => $matched,
            'label' => (string) ($cfg['label'] ?? ucfirst($matched)),
            'hue' => (int) ($cfg['hue'] ?? 0),
            'sla' => $cfg['sla'] ?? null,
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
            // Caixa Unificada v4 — fila derivada (read-only no Contexto)
            'queue' => $this->deriveQueueFromTags(
                $c->relationLoaded('tags') ? $c->tags->pluck('slug')->all() : []
            ),
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
            // US-WA-072 — mídia (image/audio/document/sticker/video). URLs
            // resolvidas via Storage::temporaryUrl (S3) ou Storage::url (local).
            'media_url' => $this->resolveMediaUrl($m->media_url),
            'media_mime' => $m->media_mime,
            'media_size_bytes' => $m->media_size_bytes,
            'media_duration_s' => $m->media_duration_s,
            'media_thumbnail_url' => $this->resolveMediaUrl($m->media_thumbnail_url),
            'media_transcription' => $m->media_transcription,
            'media_filename' => $m->media_filename,
            'created_at' => $m->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    /**
     * US-WA-072 — Resolve URL pública/assinada do media path.
     *
     * - Disco `s3`/`gcs`: `Storage::temporaryUrl()` 24h (TTL config).
     * - Disco `public`: `Storage::url()` direto (sem TTL — caminho público).
     *   Defense-in-depth do path inclui {business_id} + UUID v4 (~122 bits
     *   entropia) → não enumerável por força bruta.
     *
     * `temporaryUrl()` lança RuntimeException em drivers que não suportam
     * (local) — try/catch faz fallback gracioso pra `url()` plain.
     */
    protected function resolveMediaUrl(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }
        $disk = config('whatsapp.media.disk', 'public');
        $ttl = (int) config('whatsapp.media.signed_url_ttl_seconds', 86400);
        $storage = Storage::disk($disk);

        try {
            return $storage->temporaryUrl($relativePath, now()->addSeconds($ttl));
        } catch (\Throwable) {
            return $storage->url($relativePath);
        }
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

        // US-WA-069 Tier 0 defense-in-depth: valida ACL do canal ANTES de
        // persistir Message ou tocar driver. Sem acesso → 403, não 200.
        $conversationForCheck = Conversation::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);
        $this->ensureChannelAccessOrAbort($conversationForCheck, $businessId, $userId);

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

            // US-WA-074 (ADR 0142) — slash commands em notas internas.
            // Gate DUPLO Tier 0: parser SÓ roda quando is_internal_note=true.
            // Comando não reconhecido / sem argumentos → nota fica como normal
            // (parser retorna null) sem warning na UI. Eager-load conversation
            // pra handler acessar contact_id sem N+1.
            $slashFlash = $this->dispatchSlashCommand($message, (string) ($data['body'] ?? ''));

            $back = back()->with('success', 'Nota interna salva.');
            if ($slashFlash !== null) {
                // Adiciona payload pra UI renderizar badge ao lado da bubble.
                // Chave dedicada `slash` evita poluir flash `success` genérico.
                $back = $back->with('slash', $slashFlash);
            }
            return $back;
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
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);
        $conversation = Conversation::query()
            ->where('business_id', $businessId)
            ->with('channel')
            ->findOrFail($id);

        // US-WA-069 defense-in-depth: user sem acesso ao canal não bloqueia.
        $this->ensureChannelAccessOrAbort($conversation, $businessId, $userId);

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

        // US-WA-069 defense-in-depth: user sem acesso ao canal não muta status.
        $this->ensureChannelAccessOrAbort($conversation, $businessId, $userId);

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
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);
        $conversation = Conversation::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        // US-WA-069 defense-in-depth: user sem acesso ao canal não vincula contato.
        $this->ensureChannelAccessOrAbort($conversation, $businessId, $userId);

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

    /**
     * US-WA-078: POST `/atendimento/inbox/{id}/contact/create-from-phone` —
     * cria Contact UltimatePOS a partir do phone da conversa + linka.
     *
     * Fluxo: atendente clica "Cadastrar como contato" no painel direito
     * quando `linked_contact === null` (US-WA-064 vincula contact existente
     * via modal; este endpoint cria do zero usando push_name+phone que o
     * webhook já capturou).
     *
     * Side-effects:
     *   1. Cria `App\Contact` com type='customer', business_id atual,
     *      name=$conversation->contact_name, mobile=$customer_external_id.
     *      `contact_id` (campo UltimatePOS numérico) gerado via
     *      `commonUtil->generateReferenceNumber('contacts', ...)`.
     *   2. Linka `conversation->contact_id = contact->id` + save.
     *   3. Returns back() com flash success — Inertia partial reload faz
     *      `linked_contact` aparecer no sidebar.
     *
     * Tier 0 enforced — Contact criado SEMPRE no business da conversa
     * (defense-in-depth ON TOP do global scope).
     *
     * Permission `whatsapp.send` + ACL canal (mesma do linkContact).
     */
    public function createContactFromPhone(\Illuminate\Http\Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);
        $conversation = Conversation::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $this->ensureChannelAccessOrAbort($conversation, $businessId, $userId);

        // Já vinculado? Não tem o que fazer — defense vs double-click.
        if ($conversation->contact_id !== null) {
            return back()->with('success', 'Conversa já vinculada a um contato.');
        }

        // Nome cadastrado vai ser: push_name (já curado pelo webhook),
        // ou fallback "Cliente {E.164 truncado}" se vazio. Atendente edita
        // depois via /contacts/{id}.
        $contactName = trim((string) ($conversation->contact_name ?? ''));
        if ($contactName === '' || $contactName === $conversation->customer_external_id) {
            $contactName = 'Cliente ' . mb_substr((string) $conversation->customer_external_id, -4);
        }

        // Mobile: usa o E.164 cru (com '+'). UltimatePOS Contact aceita
        // string livre — atendente normaliza no /contacts edit depois.
        $mobile = (string) $conversation->customer_external_id;

        try {
            // Gera `contact_id` UltimatePOS (numérico/string formatado tipo
            // CO0001) via util padrão. Mesmo pattern do ContactController
            // legacy quando cria contact via API ou import.
            $commonUtil = app(\App\Utils\Util::class);
            $refCount = $commonUtil->setAndGetReferenceCount('contacts', $businessId);
            $generatedContactId = $commonUtil->generateReferenceNumber('contacts', $refCount, $businessId);
        } catch (\Throwable $e) {
            // Fallback se Util falhar (ex: test env sem ref_count_details
            // configurado). Usa um sufixo timestamp pra evitar collision.
            $generatedContactId = 'WA-' . $businessId . '-' . now()->format('YmdHis');
            Log::warning('[atendimento.inbox.create_contact_from_phone] fallback contact_id', [
                'conversation_id' => $conversation->id,
                'business_id' => $businessId,
                'reason' => mb_substr($e->getMessage(), 0, 200),
            ]);
        }

        $contact = Contact::query()->create([
            'business_id' => $businessId,
            'type' => 'customer',
            'contact_type' => 'customer',
            'name' => $contactName,
            'mobile' => $mobile,
            'contact_status' => 'active',
            'contact_id' => $generatedContactId,
            'created_by' => $userId ?: null,
        ]);

        $conversation->contact_id = $contact->id;
        if (empty($conversation->contact_name) || $conversation->contact_name === $conversation->customer_external_id) {
            $conversation->contact_name = $contact->name;
        }
        $conversation->save();

        Log::info('[atendimento.inbox.create_contact_from_phone.created]', [
            'conversation_id' => $conversation->id,
            'business_id' => $businessId,
            'contact_id' => $contact->id,
            'created_by_user_id' => $userId,
        ]);

        return back()->with('success', 'Contato cadastrado e vinculado.');
    }

    /**
     * US-WA-072 — POST `/atendimento/inbox/{id}/send-media` — upload outbound.
     *
     * Aceita multipart com:
     *   - `file` (UploadedFile, obrigatório)
     *   - `caption` (string opcional, vira `body` da Message)
     *
     * Valida MIME contra `Message::MEDIA_MIME_WHITELIST` (bloqueia SVG/HTML),
     * size <= 16MB. Salva no disco config('whatsapp.media.disk', 'public')
     * em `whatsapp/{business_id}/{yyyy-mm}/{uuid}.{ext}`.
     *
     * Persiste Message em `status='queued'` ANTES de dispatchar `SendMediaJob`
     * (defense-in-depth: row já existe pra retry se daemon falhar).
     *
     * Tier 0 IRREVOGÁVEL — `is_internal_note` + `send-media` = 422 (notas
     * internas NÃO podem ter mídia outbound — atendente pode usar caption
     * mas não anexar arquivo).
     *
     * Permission `whatsapp.send` (mesma do send freeform).
     */
    public function sendMedia(\Illuminate\Http\Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:' . (Message::MEDIA_MAX_SIZE_BYTES / 1024)],
            'caption' => ['nullable', 'string', 'max:1024'],
            // Tier 0: notas internas NÃO podem ter mídia outbound (combinação inválida)
            'is_internal_note' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['is_internal_note'])) {
            return back()->withErrors([
                'send_media' => 'Notas internas não podem ter mídia outbound nesta fase.',
            ]);
        }

        $file = $request->file('file');
        $mime = $file->getMimeType() ?: $file->getClientMimeType();

        // MIME whitelist enforce — bloqueia SVG (XSS), HTML, executáveis
        if (! in_array($mime, Message::MEDIA_MIME_WHITELIST, true)) {
            return back()->withErrors([
                'send_media' => "Tipo de arquivo não permitido: {$mime}",
            ]);
        }

        $conversation = Conversation::query()
            ->where('business_id', $businessId)
            ->with('channel')
            ->findOrFail($id);

        if ($conversation->is_blocked) {
            return back()->withErrors(['send_media' => 'Contato bloqueado.']);
        }

        $channel = $conversation->channel;
        if (! $channel) {
            return back()->withErrors(['send_media' => 'Canal não associado à conversa.']);
        }

        // Salva o arquivo no disco public
        $disk = config('whatsapp.media.disk', 'public');
        $uuid = Str::uuid()->toString();
        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $relativePath = sprintf(
            'whatsapp/%d/%s/%s.%s',
            $businessId,
            now()->format('Y-m'),
            $uuid,
            $ext,
        );
        Storage::disk($disk)->put($relativePath, file_get_contents($file->getRealPath()));

        // Type derivado do MIME — mesmo mapping do webhook inbound
        $type = match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'audio/') => 'audio',
            str_starts_with($mime, 'video/') => 'video',
            default => 'document',
        };

        $message = Message::query()->create([
            'business_id' => $businessId,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'provider' => $channel->type,
            'type' => $type,
            'body' => $data['caption'] ?? null,
            'status' => 'queued',
            'sender_user_id' => $userId ?: null,
            'sender_kind' => 'human',
            'is_internal_note' => false,
            'media_url' => $relativePath,
            'media_mime' => $mime,
            'media_size_bytes' => $file->getSize(),
            'media_filename' => $file->getClientOriginalName(),
        ]);

        $conversation->forceFill([
            'last_outbound_at' => now(),
            'last_message_at' => now(),
        ])->save();

        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS) {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => "Envio de mídia só implementado pra Baileys nesta fase. Tipo: {$channel->type}",
            ])->save();
            return back()->withErrors(['send_media' => 'Envio de mídia só disponível pra canais Baileys.']);
        }

        SendMediaJob::dispatch($businessId, $message->id);

        return back()->with('success', 'Mídia enviada (aguardando confirmação do daemon).');
    }

    /**
     * US-WA-045b — POST `/atendimento/inbox/conversations/{id}/send-interactive`.
     *
     * Envia mensagem interativa (buttons / list / cta_url) HSM. Atendente
     * compõe via `InteractiveMessageDialog.tsx` no composer e dispara este
     * endpoint que valida payload, persiste `Message(type=interactive)` em
     * `status=queued`, e dispatcha pro daemon Baileys CT 100 (Tier 0 ADR 0093).
     *
     * Estrutura `$interactive` (discriminated union pelo `type`):
     *  - `['type' => 'buttons', 'buttons' => [{id, label}], 'header'?, 'footer'?]` (max 3 buttons)
     *  - `['type' => 'list',    'button_label' => '...', 'sections' => [{title, items: [{id, title, description?}]}]]` (max 10 items)
     *  - `['type' => 'cta_url', 'cta_label' => '...', 'cta_url' => 'https://...']` (Meta Cloud only)
     *
     * Permission `whatsapp.send`. ACL canal Tier 0 (US-WA-069). Não permite
     * notas internas (interactive sempre cliente-facing).
     *
     * @see Modules/Whatsapp/Jobs/SendInteractiveJob.php (PR #715)
     * @see Modules/Whatsapp/Services/Drivers/DriverInterface.php::sendInteractive
     */
    public function sendInteractive(Request $request, int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);

        $conversation = Conversation::query()
            ->where('business_id', $businessId)
            ->with('channel')
            ->findOrFail($id);

        // US-WA-069 defense-in-depth: ACL canal antes de QUALQUER persist/dispatch.
        $this->ensureChannelAccessOrAbort($conversation, $businessId, $userId);

        if ($conversation->is_blocked) {
            return back()->withErrors(['send_interactive' => 'Contato bloqueado.']);
        }

        $channel = $conversation->channel;
        if (! $channel) {
            return back()->withErrors(['send_interactive' => 'Canal não associado à conversa.']);
        }

        // Validação base — `type` discrimina o resto via rules condicionais.
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1024'],
            'type' => ['required', Rule::in(['buttons', 'list', 'cta_url'])],
            // buttons
            'header' => ['nullable', 'string', 'max:60'],
            'footer' => ['nullable', 'string', 'max:60'],
            'buttons' => ['required_if:type,buttons', 'array', 'min:1', 'max:3'],
            'buttons.*.id' => ['required_with:buttons', 'string', 'min:1', 'max:64'],
            'buttons.*.label' => ['required_with:buttons', 'string', 'min:1', 'max:20'],
            // list
            'button_label' => ['required_if:type,list', 'nullable', 'string', 'min:1', 'max:20'],
            'sections' => ['required_if:type,list', 'array', 'min:1'],
            'sections.*.title' => ['required_with:sections', 'string', 'min:1', 'max:24'],
            'sections.*.items' => ['required_with:sections', 'array', 'min:1'],
            'sections.*.items.*.id' => ['required_with:sections', 'string', 'min:1', 'max:64'],
            'sections.*.items.*.title' => ['required_with:sections', 'string', 'min:1', 'max:24'],
            'sections.*.items.*.description' => ['nullable', 'string', 'max:72'],
            // cta_url (Meta only)
            'cta_label' => ['required_if:type,cta_url', 'nullable', 'string', 'min:1', 'max:20'],
            'cta_url' => ['required_if:type,cta_url', 'nullable', 'url', 'max:2048'],
        ]);

        // Total items list ≤ 10 (limite WhatsApp Meta/Baileys). Validation rule
        // sections.*.items.* não consegue contar agregado, então enforce aqui.
        if ($data['type'] === 'list') {
            $totalItems = collect($data['sections'])->sum(fn ($s) => count($s['items'] ?? []));
            if ($totalItems > 10) {
                return back()->withErrors(['send_interactive' => "Lista pode ter no máximo 10 itens (recebido {$totalItems})."]);
            }
        }

        // cta_url só Meta Cloud — qualquer driver baileys/zapi rejeita.
        // Defense-in-depth ON TOP da `DriverDoesNotSupport` que o Job/Driver
        // lança no fluxo backend (fail-fast 422 em vez de Message=failed).
        if ($data['type'] === 'cta_url' && $channel->type !== Channel::TYPE_WHATSAPP_META) {
            return back()->withErrors([
                'send_interactive' => 'Botão CTA URL só está disponível em canais Meta Cloud.',
            ]);
        }

        // Monta payload `$interactive` no shape do DriverInterface::sendInteractive
        // (discriminated union) — backend único contrato pra Job + daemon direto.
        $interactive = match ($data['type']) {
            'buttons' => array_filter([
                'type' => 'buttons',
                'buttons' => collect($data['buttons'])
                    ->map(fn ($b) => ['id' => $b['id'], 'label' => $b['label']])
                    ->all(),
                'header' => $data['header'] ?? null,
                'footer' => $data['footer'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            'list' => [
                'type' => 'list',
                'button_label' => $data['button_label'],
                'sections' => collect($data['sections'])
                    ->map(fn ($s) => [
                        'title' => $s['title'],
                        'items' => collect($s['items'])
                            ->map(fn ($i) => array_filter([
                                'id' => $i['id'],
                                'title' => $i['title'],
                                'description' => $i['description'] ?? null,
                            ], fn ($v) => $v !== null && $v !== ''))
                            ->all(),
                    ])
                    ->all(),
            ],
            'cta_url' => [
                'type' => 'cta_url',
                'button_label' => $data['cta_label'],
                'url' => $data['cta_url'],
            ],
        };

        // Persiste Message em status=queued ANTES do dispatch — defesa em
        // profundidade (mesmo padrão do send()/sendMedia()). Payload JSON
        // serializado pra UI renderizar resumo + auditoria.
        $message = Message::query()->create([
            'business_id' => $businessId,
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'provider' => $channel->type,
            'type' => 'interactive',
            'body' => $data['body'],
            'payload' => $interactive,
            'status' => 'queued',
            'sender_user_id' => $userId ?: null,
            'sender_kind' => 'human',
            'is_internal_note' => false,
        ]);

        $conversation->forceFill([
            'last_outbound_at' => now(),
            'last_message_at' => now(),
        ])->save();

        // Path baileys: chama daemon direto (mesmo quick-path do send()).
        // Path meta: dispatch Job — driver MetaCloudDriver vai fazer o POST.
        // Path zapi: dispatch Job idem (driver decide se rejeita).
        if ($channel->type === Channel::TYPE_WHATSAPP_BAILEYS) {
            return $this->dispatchInteractiveViaBaileysDaemon($message, $conversation, $channel, $interactive);
        }

        // Meta/Z-API: usa Job (SendInteractiveJob — PR #715). Job espera
        // WhatsappBusinessPhone legacy; durante coexistência (ADR 0135), o
        // controller localiza phone pelo business — refactor pra Channel
        // será em PR separado (mesmo todo do send()).
        $phoneId = $this->resolveLegacyPhoneIdForChannel($businessId, $channel);
        if ($phoneId === null) {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => "Sem WhatsappBusinessPhone legacy mapeado pra canal {$channel->type} — envio interactive indisponível nesta fase.",
            ])->save();

            return back()->withErrors([
                'send_interactive' => 'Canal sem phone legacy associado pra dispatch interactive. Configure em /whatsapp/settings.',
            ]);
        }

        $rawNumber = preg_replace('/^\+/', '', (string) $conversation->customer_external_id);
        SendInteractiveJob::dispatch($businessId, $phoneId, $rawNumber, $data['body'], $interactive);

        return back()->with('success', 'Mensagem interativa enviada (aguardando confirmação do driver).');
    }

    /**
     * US-WA-045b — quick-path Baileys CT 100: chama daemon `/interactive` direto
     * sem passar pelo Job (mesmo padrão do `send()` legacy de texto).
     */
    protected function dispatchInteractiveViaBaileysDaemon(
        Message $message,
        Conversation $conversation,
        Channel $channel,
        array $interactive,
    ): RedirectResponse {
        // Daemon só aceita buttons/list (cta_url é Meta-only — caller já bloqueou).
        // Defense-in-depth: se chegou aqui com cta_url, falha graciosa antes do HTTP.
        if ($interactive['type'] === 'cta_url') {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => 'CTA URL não suportado em Baileys (Meta Cloud only).',
            ])->save();

            return back()->withErrors([
                'send_interactive' => 'Daemon Baileys não suporta CTA URL.',
            ]);
        }

        $daemonUrl = config('whatsapp.baileys.daemon_url');
        $apiKey = config('whatsapp.baileys.api_key');
        $instanceId = 'ch-' . str_replace('-', '', $channel->channel_uuid);
        $toPhone = preg_replace('/^\+/', '', (string) $conversation->customer_external_id);

        try {
            $response = Http::withToken($apiKey)
                ->withoutVerifying() // FIXME(US-WA-058): cert LE pendente
                ->timeout(15)
                ->post("{$daemonUrl}/instances/{$instanceId}/interactive", [
                    'to' => $toPhone,
                    'body' => $message->body,
                    'interactive' => $interactive,
                ]);

            if (! $response->successful()) {
                $message->forceFill([
                    'status' => 'failed',
                    'failed_reason' => 'Daemon ' . $response->status() . ': ' . mb_substr($response->body(), 0, 200),
                ])->save();
                Log::warning('[atendimento.inbox.send_interactive] daemon error', [
                    'channel_id' => $channel->id,
                    'status' => $response->status(),
                ]);

                return back()->withErrors(['send_interactive' => 'Falha ao enviar interativo via daemon.']);
            }

            $payload = $response->json();
            $message->forceFill([
                'status' => $payload['status'] ?? 'sent',
                'provider_message_id' => $payload['message_id'] ?? null,
            ])->save();

            return back()->with('success', 'Mensagem interativa enviada.');
        } catch (\Throwable $e) {
            $message->forceFill([
                'status' => 'failed',
                'failed_reason' => mb_substr($e->getMessage(), 0, 240),
            ])->save();
            Log::error('[atendimento.inbox.send_interactive] exception', [
                'channel_id' => $channel->id,
                'exception' => $e->getMessage(),
            ]);

            return back()->withErrors(['send_interactive' => 'Erro de rede com daemon: ' . $e->getMessage()]);
        }
    }

    /**
     * US-WA-045b — localiza WhatsappBusinessPhone legacy correspondente ao
     * Channel polimórfico (ADR 0135). Necessário enquanto SendInteractiveJob
     * + DriverInterface seguem acoplados a WhatsappBusinessPhone.
     *
     * Heurística (consistência com BaileysConnectJob/SettingsController):
     *  - Channel.config_json pode trazer `legacy_phone_id` quando criado via
     *    `/whatsapp/settings` migration. Se sim, usa direto.
     *  - Senão, tenta phone único do business com driver compatível
     *    (whatsapp_meta → driver=meta_cloud, whatsapp_zapi → driver=zapi).
     *  - Sem match → null (controller falha graciosamente).
     */
    protected function resolveLegacyPhoneIdForChannel(int $businessId, Channel $channel): ?int
    {
        $config = is_string($channel->config_json) ? json_decode($channel->config_json, true) : ($channel->config_json ?? []);
        if (is_array($config) && isset($config['legacy_phone_id'])) {
            return (int) $config['legacy_phone_id'];
        }

        $driverByType = match ($channel->type) {
            Channel::TYPE_WHATSAPP_META => 'meta_cloud',
            Channel::TYPE_WHATSAPP_ZAPI => 'zapi',
            Channel::TYPE_WHATSAPP_BAILEYS => 'baileys',
            default => null,
        };
        if ($driverByType === null) {
            return null;
        }

        $phone = \Modules\Whatsapp\Entities\WhatsappBusinessPhone::query()
            ->where('business_id', $businessId)
            ->where('driver', $driverByType)
            ->orderByDesc('id')
            ->first(['id']);

        return $phone?->id;
    }

    /**
     * US-WA-074 (ADR 0142) — dispatcha slash command da nota interna.
     *
     * Tier 0 IRREVOGÁVEL — parser SÓ é invocado quando `is_internal_note=true`
     * (caller já gateou). Comportamento conservador:
     *   - body sem `/cmd args` válido → retorna null (UI mostra nota normal)
     *   - comando registrado retorna success → flash payload {kind, badge, link_url}
     *   - comando retorna error → flash payload {kind=error, error_message}
     *   - comando unrecognized (no-op gracioso) → null (não polui UI)
     *
     * O retorno alimenta a flash session — frontend lê `props.flash.slash` e
     * renderiza badge clicável ao lado da bubble da nota recém-criada.
     */
    protected function dispatchSlashCommand(Message $note, string $body): ?array
    {
        // Defense-in-depth: parser só roda em nota interna. Caller já garante,
        // mas mantém auditável aqui (Tier 0 ADR 0142 §1).
        if (! $note->is_internal_note) {
            return null;
        }

        /** @var SlashCommandParser $parser */
        $parser = app(SlashCommandParser::class);
        $parsed = $parser->parse($body);
        if ($parsed === null) {
            return null;
        }

        /** @var SlashCommandRegistry $registry */
        $registry = app(SlashCommandRegistry::class);

        // Eager-load conversation pra handler acessar contact_id sem extra query.
        $note->loadMissing('conversation');

        $result = $registry->dispatch($parsed->command, $note, $parsed->arguments);

        // Unrecognized = no-op gracioso (comando não registrado OU args vazio
        // pós-validation no handler). UI ignora.
        if ($result->isUnrecognized()) {
            return null;
        }

        return array_merge(
            $result->toFlashPayload(),
            ['command' => $parsed->command, 'message_id' => $note->id],
        );
    }

    /**
     * US-WA-069: aplica filtro ACL canal=fila no query de Conversation.
     *
     * Filtragem por canais que o user tem acesso ATIVO em
     * `channel_user_access` (revoked_at IS NULL). Bypass único: Gate
     * `whatsapp.view-all-phones` (admin/superadmin). Sem acesso a nenhum
     * canal → user vê inbox vazia (não 500).
     *
     * Implementação por subquery em vez de `pluck()->whereIn(array)` pra
     * escalar em users com 50+ canais sem hidratar lista no PHP.
     */
    protected function applyChannelAclFilter($query, int $businessId, int $userId): void
    {
        if ($this->canSeeAllChannels()) {
            return; // admin/superadmin bypass
        }

        $query->whereIn('channel_id', $this->allowedChannelIdsSubquery($businessId, $userId));
    }

    /**
     * Subquery dos channel_ids que o user tem ACL ATIVA pra este business.
     *
     * Retorna um Builder pra ser passado em `whereIn(...)` — performático mesmo
     * com 50+ canais (não hidrata PHP). Importante: filtra ALSO por business_id
     * pra reforçar Tier 0 ADR 0093 (defense-in-depth — global scope já garante,
     * mas subquery explícita não custa).
     */
    protected function allowedChannelIdsSubquery(int $businessId, int $userId)
    {
        return ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->select('channel_id');
    }

    /**
     * US-WA-069: Gate `whatsapp.view-all-phones` é o ÚNICO bypass do filtro
     * per-canal. Mesmo gate usado pra `whatsapp_phone_user_access` legacy
     * (consistência). Definido em AuthServiceProvider — geralmente concedido
     * a roles `Admin#{biz}` (UltimatePOS) e superadmin global.
     */
    protected function canSeeAllChannels(): bool
    {
        return (bool) (auth()->user()?->can('whatsapp.view-all-phones') ?? false);
    }

    /**
     * US-WA-069 defense-in-depth: aborta com 403 se user não tem ACL no
     * canal da conversa. Chamado nos métodos de mutação (send, updateStatus,
     * updateTags, linkContact, blockContact) ANTES de qualquer write/dispatch.
     *
     * Admin (Gate `whatsapp.view-all-phones`) bypassa. Caso sem `channel_id`
     * (conversa órfã) NÃO bloqueia — global scope business_id já garante
     * Tier 0; mutação prossegue.
     */
    protected function ensureChannelAccessOrAbort(Conversation $conversation, int $businessId, int $userId): void
    {
        if ($this->canSeeAllChannels()) {
            return;
        }

        $channelId = (int) ($conversation->channel_id ?? 0);
        if ($channelId === 0) {
            return; // conversa sem canal (legacy órfã) — não bloqueia
        }

        $hasAccess = ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('channel_id', $channelId)
            ->whereNull('revoked_at')
            ->exists();

        if (! $hasAccess) {
            abort(403, 'Sem acesso ao canal desta conversa.');
        }
    }

    /**
     * CYCLE-08 PR-A (US-WA-040): valida que user tem ACL no `channel_id` passado
     * via query param `?channel_id=N` no dropdown topbar.
     *
     * Aplicação:
     *   1. Channel precisa existir no MESMO business (Tier 0 ADR 0093 — bloqueia
     *      atacante passando `channel_id` de biz=99 numa sessão biz=1).
     *   2. User precisa ter ACL ativo OU bypass Gate `whatsapp.view-all-phones`.
     *   3. Fail-loud (403) em vez de fail-silent (vazio) — atendente sabe que
     *      filtro inválido em vez de ficar com lista vazia confusa.
     *
     * Channel não-existente OU de outro business → 403 (mesma resposta de
     * "sem acesso") pra não vazar enumeração de channel_ids existentes.
     */
    protected function ensureChannelIdAccessOrAbort(int $channelId, int $businessId, int $userId): void
    {
        // Channel precisa existir no business correto — bloqueia ataque cross-tenant
        $channelExists = Channel::query()
            ->where('id', $channelId)
            ->where('business_id', $businessId)
            ->exists();
        if (! $channelExists) {
            abort(403, 'Canal não encontrado ou sem acesso.');
        }

        if ($this->canSeeAllChannels()) {
            return; // admin/superadmin bypass
        }

        $hasAccess = ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('channel_id', $channelId)
            ->whereNull('revoked_at')
            ->exists();

        if (! $hasAccess) {
            abort(403, 'Sem acesso ao canal selecionado.');
        }
    }
}
