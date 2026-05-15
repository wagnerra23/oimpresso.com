<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\Tag;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoTokenIssuer;

/**
 * CaixaUnificadaController — Caixa Unificada V4 (omnichannel redesign).
 *
 * Tela NOVA `/atendimento/caixa-unificada` que **coexiste** com `/atendimento/inbox`
 * durante canary 7d. Versão Cowork redesign do mesmo conceito omnichannel:
 *   - 3-col limpa (lista esquerda · thread central · contexto direita)
 *   - chips canal acima da shell (filtro horizontal por type)
 *   - banner "em homologação" pra canais preview-only (Meta Cloud, Z-API, IG, FB, Email, ML)
 *   - sidebar contexto 8 sections (Fila/SLA/Atribuído/Canal/Saldo/Histórico/Último contato/Ações)
 *
 * Reusa 80% da lógica de queries do InboxController via call indireto pra
 * Conversation/Channel/Tag — mas com payload simplificado pra UI Cowork
 * (sem multi-tab, sem aging, sem within_24h dropdown, sem unlinked toggle).
 * Esses filtros podem ser adicionados em refinement incremental.
 *
 * Princípios canônicos aplicados (Tier 0 IRREVOGÁVEL):
 *   - `business_id` global scope ADR 0093 (multi-tenant)
 *   - `Inertia::defer()` em props caras — skill `inertia-defer-default` Tier B
 *   - ACL canal=fila via `channel_user_access` (US-WA-069)
 *   - Centrifugo + polling fallback 5s (US-WA-066)
 *   - PT-BR em TODA copy (UI, logs, comentários)
 *
 * ⚠️ NÃO mexe em `InboxController` — coexiste durante canary.
 * ⚠️ Cutover (substituir Inbox legacy) só em PR seguinte após Wagner aprovar.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md (omnichannel)
 * @see memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md (loop Cowork)
 * @see prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx (fonte visual canônica)
 * @see memory/requisitos/Whatsapp/CaixaUnificadaV4-visual-comparison.md (gate F3)
 */
class CaixaUnificadaController extends Controller
{
    public function index(Request $request, CentrifugoTokenIssuer $tokenIssuer): Response
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);

        // Estados de UI leves — eager (custo zero)
        // Wave 2 F1: parâmetro `tab` (7 valores) substitui `status` (4 valores).
        // Mantém compat — se request envia `status=abertas`, mapeia pra `tab=all`.
        $tabFilter = $request->input('tab');
        if ($tabFilter === null) {
            $legacyStatus = $request->input('status');
            $statusToTab = [
                'abertas' => 'all',
                'pendentes' => 'unread',
                'aguardando' => 'awaiting_human',
                'resolvidas' => 'resolved',
            ];
            $tabFilter = $statusToTab[$legacyStatus] ?? 'all';
        }
        $statusFilter = $tabFilter; // legacy alias mantido pra evitar quebra props
        $channelTypeFilter = $request->input('channel'); // type=whatsapp_baileys, etc
        $accountFilter = $request->has('account_id') && $request->input('account_id') !== ''
            ? (int) $request->input('account_id')
            : null;
        $queueFilter = $request->input('queue');
        $search = (string) $request->input('q', '');
        $threadId = $request->input('thread');

        // Wave 5 F1 — filtros power-user paridade Inbox legacy
        $within24h = $request->has('within24h')
            ? filter_var($request->input('within24h'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;
        $unlinked = (bool) $request->input('unlinked', false);
        $mediaInbound24h = (bool) $request->input('media_inbound_24h', false);
        $inboundAging = $request->input('inbound_aging'); // 6h/12h/24h/48h/7d
        $orderBy = (string) $request->input('order_by', 'last_message'); // last_message | inbound
        $activeTagIds = array_filter(array_map('intval', (array) $request->input('tags', [])));

        // ACL gate per-canal — se selecionou `account_id`, valida ANTES
        // (defense-in-depth — fail-loud em vez de lista vazia confusa).
        if ($accountFilter !== null) {
            $this->ensureChannelIdAccessOrAbort($accountFilter, $businessId, $userId);
        }

        // Thread aberta?
        $thread = null;
        $messages = null;
        if ($threadId) {
            $threadQuery = Conversation::query()
                ->where('business_id', $businessId)
                ->with(['channel', 'tags:id,slug,label,color']);
            $this->applyChannelAclFilter($threadQuery, $businessId, $userId);
            $threadModel = $threadQuery->find($threadId);
            if ($threadModel) {
                $thread = $this->convToThreadArray($threadModel);
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

        // Centrifugo real-time (Tier 0 segregado por business_id)
        $centrifugoChannel = "omnichannel:business:{$businessId}";
        $token = $tokenIssuer->issue(
            $userId,
            [$centrifugoChannel],
            (int) config('whatsapp.centrifugo.token_ttl_seconds', 3600)
        );
        $centrifugoConfig = $token !== null ? [
            'wsUrl' => config('whatsapp.centrifugo.ws_url'),
            'token' => $token,
            'channel' => $centrifugoChannel,
        ] : null;

        return Inertia::render('Atendimento/CaixaUnificada/Index', [
            // ─── DEFER: props caras (skip quando partial reload `only:[]` não pede) ───
            'conversations' => Inertia::defer(fn () => $this->buildConversationsPayload(
                $businessId, $userId, $statusFilter, $channelTypeFilter, $accountFilter, $queueFilter, $search,
                $within24h, $unlinked, $mediaInbound24h, $inboundAging, $orderBy, $activeTagIds
            )),
            'stats' => Inertia::defer(fn () => $this->buildStatsPayload($businessId, $userId)),
            'availableChannels' => Inertia::defer(fn () => $this->buildAvailableChannelsPayload($businessId, $userId)),
            'availableAccounts' => Inertia::defer(fn () => $this->buildAvailableAccountsPayload($businessId, $userId)),
            'availableTags' => Inertia::defer(fn () => $this->buildAvailableTagsPayload($businessId)),

            // ─── Eager: estados de UI leves ───
            'businessId' => $businessId,
            'statusFilter' => $statusFilter,
            'channelTypeFilter' => $channelTypeFilter,
            'accountFilter' => $accountFilter,
            'queueFilter' => $queueFilter,
            'q' => $search,
            // Wave 5 F1: filtros power-user pra UI sincronizar
            'within24h' => $within24h,
            'unlinked' => $unlinked,
            'mediaInbound24h' => $mediaInbound24h,
            'inboundAging' => $inboundAging,
            'orderBy' => $orderBy,
            'activeTagIds' => array_values($activeTagIds),
            'thread' => $thread,
            'messages' => $messages,
            'centrifugoConfig' => $centrifugoConfig,

            // Config static (custo zero — array em memória)
            'queues' => (array) config('whatsapp.queues', []),
            'defaultQueue' => (string) config('whatsapp.default_queue', 'comercial'),
        ]);
    }

    /**
     * Conversations paginadas + filtros + ACL + queue derivation.
     *
     * @return array{data: array, current_page: int, last_page: int, total: int}
     */
    protected function buildConversationsPayload(
        int $businessId,
        int $userId,
        string $statusFilter,
        ?string $channelTypeFilter,
        ?int $accountFilter,
        ?string $queueFilter,
        string $search,
        ?bool $within24h = null,
        bool $unlinked = false,
        bool $mediaInbound24h = false,
        ?string $inboundAging = null,
        string $orderBy = 'last_message',
        array $activeTagIds = []
    ): array {
        $convQuery = Conversation::query()
            ->where('business_id', $businessId)
            ->with(['channel:id,label,type,status,channel_uuid,channel_health,display_identifier', 'tags:id,slug,label,color']);

        $this->applyChannelAclFilter($convQuery, $businessId, $userId);

        // Wave 2 F1 — 7 tabs canônicas paridade InboxController::index
        switch ($statusFilter) {
            case 'unread':
                $convQuery->where('unread_count', '>', 0);
                break;
            case 'assigned':
                $convQuery->where('assigned_user_id', $userId);
                break;
            case 'bot':
                $convQuery->where('bot_handling', true);
                break;
            case 'awaiting_human':
                $convQuery->where('status', 'awaiting_human');
                break;
            case 'resolved':
                $convQuery->where('status', 'resolved');
                break;
            case 'archived':
                $convQuery->where('status', 'archived');
                break;
            case 'all':
            default:
                $convQuery->whereNotIn('status', ['archived']);
                break;
        }

        if ($channelTypeFilter) {
            $convQuery->whereHas('channel', fn ($c) => $c->where('type', $channelTypeFilter));
        }

        if ($accountFilter !== null) {
            $convQuery->where('channel_id', $accountFilter);
        }

        if ($search !== '') {
            $convQuery->where(function ($x) use ($search) {
                $x->where('contact_name', 'LIKE', "%{$search}%")
                  ->orWhere('customer_external_id', 'LIKE', "%{$search}%")
                  ->orWhere('last_message_preview', 'LIKE', "%{$search}%");
            });
        }

        // Wave 5 F1 — filtros power-user paridade Inbox legacy

        // within24h tri-estado: janela Meta 24h (last_inbound_at >/< now-24h)
        if ($within24h === true) {
            $convQuery->where('last_inbound_at', '>=', now()->subHours(24));
        } elseif ($within24h === false) {
            $convQuery->where(function ($q) {
                $q->whereNull('last_inbound_at')
                  ->orWhere('last_inbound_at', '<', now()->subHours(24));
            });
        }

        // unlinked: convs sem Contact CRM vinculado
        if ($unlinked) {
            $convQuery->whereNull('contact_id');
        }

        // mediaInbound24h: convs com mensagem inbound type=image/video/audio/document nas 24h
        if ($mediaInbound24h) {
            $convQuery->whereExists(function ($q) {
                $q->select(\DB::raw(1))
                    ->from('whatsapp_messages')
                    ->whereColumn('whatsapp_messages.conversation_id', 'conversations.id')
                    ->where('direction', 'inbound')
                    ->whereIn('type', ['image', 'video', 'audio', 'document'])
                    ->where('created_at', '>=', now()->subHours(24));
            });
        }

        // inboundAging: aguardando resposta há mais de N horas
        $agingMap = ['6h' => 6, '12h' => 12, '24h' => 24, '48h' => 48, '7d' => 168];
        if ($inboundAging && isset($agingMap[$inboundAging])) {
            $convQuery->where('last_inbound_at', '<=', now()->subHours($agingMap[$inboundAging]))
                ->where(function ($q) {
                    $q->whereNull('last_outbound_at')
                      ->orWhereColumn('last_inbound_at', '>', 'last_outbound_at');
                });
        }

        // Filtro por tags (intersecção — pelo menos 1 tag das selecionadas)
        if (! empty($activeTagIds)) {
            $convQuery->whereHas('tags', fn ($q) => $q->whereIn('whatsapp_conversation_tags.tag_id', $activeTagIds));
        }

        // Ordenação alternativa: por último inbound (vs default last_message_at)
        $orderColumn = $orderBy === 'inbound' ? 'last_inbound_at' : 'last_message_at';

        $paginated = $convQuery
            ->orderByDesc($orderColumn)
            ->paginate(50);

        $data = $paginated->getCollection()
            ->map(fn (Conversation $c) => $this->convToListArray($c))
            ->all();

        // Filtro por fila DERIVADA (não persiste em DB — heurística tag → fila).
        // Aplicado em-memory pós-paginate porque queue vem do derivador, não da query.
        if ($queueFilter) {
            $data = array_values(array_filter($data, fn ($row) => ($row['queue']['slug'] ?? null) === $queueFilter));
        }

        return [
            'data' => $data,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ];
    }

    /**
     * Stats agregadas pro header "3 contas ativas · 5 filas · 8 abertas · 1 não lidas".
     *
     * @return array{abertas: int, pendentes: int, aguardando: int, resolvidas: int, unread: int, active_accounts: int, queues_count: int}
     */
    protected function buildStatsPayload(int $businessId, int $userId): array
    {
        $base = fn () => tap(
            Conversation::query()->where('business_id', $businessId),
            fn ($q) => $this->applyChannelAclFilter($q, $businessId, $userId)
        );

        $activeAccountsQuery = Channel::query()
            ->where('business_id', $businessId)
            ->where('status', 'active');
        if (! $this->canSeeAllChannels()) {
            $activeAccountsQuery->whereIn('id', $this->allowedChannelIdsSubquery($businessId, $userId));
        }

        return [
            'abertas' => (clone $base())->where('status', '!=', 'resolved')->count(),
            'pendentes' => (clone $base())->where('unread_count', '>', 0)->count(),
            'aguardando' => (clone $base())
                ->whereNotNull('last_outbound_at')
                ->where(function ($q) {
                    $q->whereNull('last_inbound_at')
                      ->orWhereColumn('last_outbound_at', '>', 'last_inbound_at');
                })->count(),
            'resolvidas' => (clone $base())->where('status', 'resolved')->count(),
            'unread' => (clone $base())->sum('unread_count'),
            'active_accounts' => $activeAccountsQuery->count(),
            'queues_count' => count((array) config('whatsapp.queues', [])),
            // Wave 2 F1 — counts paridade Inbox legacy (7 tabs)
            'assigned' => (clone $base())->where('assigned_user_id', $userId)->count(),
            'bot' => (clone $base())->where('bot_handling', true)->count(),
            'awaiting_human' => (clone $base())->where('status', 'awaiting_human')->count(),
            'archived' => (clone $base())->where('status', 'archived')->count(),
        ];
    }

    /**
     * Catálogo de tipos de canal — para chips horizontais.
     *
     * Returna lista ENRIQUECIDA com count + status (`ativo`/`em_breve`).
     * Tipos sem nenhum Channel ATIVO viram `em_breve` (preview mode).
     *
     * @return array<int, array{id: string, label: string, short: string, hue: int, glyph: string, status: string, count: int}>
     */
    protected function buildAvailableChannelsPayload(int $businessId, int $userId): array
    {
        // Catálogo canônico dos 7 tipos suportados (Cowork visual)
        $catalog = [
            ['id' => 'whatsapp_baileys', 'label' => 'WhatsApp Baileys',    'short' => 'WA · Baileys',    'hue' => 145, 'glyph' => 'W'],
            ['id' => 'whatsapp_meta',    'label' => 'WhatsApp Meta Cloud', 'short' => 'WA · Meta Cloud', 'hue' => 145, 'glyph' => 'W'],
            ['id' => 'whatsapp_zapi',    'label' => 'WhatsApp Z-API',      'short' => 'WA · Z-API',      'hue' => 145, 'glyph' => 'W'],
            ['id' => 'instagram_dm',     'label' => 'Instagram DM',        'short' => 'Instagram',       'hue' => 0,   'glyph' => '◎'],
            ['id' => 'messenger',        'label' => 'Facebook Messenger',  'short' => 'Messenger',       'hue' => 250, 'glyph' => 'f'],
            ['id' => 'email_imap',       'label' => 'Email (IMAP)',        'short' => 'Email',           'hue' => 280, 'glyph' => '@'],
            ['id' => 'mercadolivre',     'label' => 'Mercado Livre',       'short' => 'Mercado Livre',   'hue' => 95,  'glyph' => 'M'],
        ];

        // Channels ATIVOS do business (com ACL aplicado)
        $activeQuery = Channel::query()
            ->where('business_id', $businessId)
            ->where('status', 'active');
        if (! $this->canSeeAllChannels()) {
            $activeQuery->whereIn('id', $this->allowedChannelIdsSubquery($businessId, $userId));
        }
        $activeTypesCount = $activeQuery
            ->selectRaw('type, COUNT(*) as cnt')
            ->groupBy('type')
            ->pluck('cnt', 'type')
            ->toArray();

        // Conversation count per channel TYPE (ACL aware)
        // Tier 0 ADR 0093: qualifica `conversations.business_id` pra evitar
        // SQLSTATE[23000] 1052 (ambiguous column) — channels TAMBEM tem
        // business_id, e o JOIN abaixo precisa de prefixo explicito.
        $convBase = Conversation::query()->where('conversations.business_id', $businessId);
        $this->applyChannelAclFilter($convBase, $businessId, $userId);
        $convCountsPerType = $convBase
            ->join('channels', 'channels.id', '=', 'conversations.channel_id')
            ->selectRaw('channels.type as channel_type, COUNT(*) as cnt')
            ->groupBy('channels.type')
            ->pluck('cnt', 'channel_type')
            ->toArray();

        return array_map(function ($row) use ($activeTypesCount, $convCountsPerType) {
            $isActive = isset($activeTypesCount[$row['id']]) && $activeTypesCount[$row['id']] > 0;
            return array_merge($row, [
                'status' => $isActive ? 'ativo' : 'em_breve',
                'count' => (int) ($convCountsPerType[$row['id']] ?? 0),
            ]);
        }, $catalog);
    }

    /**
     * Contas (instâncias) por canal — pra sub-filtro horizontal quando user
     * seleciona um TYPE específico.
     *
     * @return array<int, array{id: int, channel_type: string, label: string, handle: string, status: string, owner: ?string, channel_health: string, count: int}>
     */
    protected function buildAvailableAccountsPayload(int $businessId, int $userId): array
    {
        $query = Channel::query()
            ->where('business_id', $businessId);
        if (! $this->canSeeAllChannels()) {
            $query->whereIn('id', $this->allowedChannelIdsSubquery($businessId, $userId));
        }
        $channels = $query
            ->orderBy('type')
            ->orderBy('label')
            ->get(['id', 'label', 'type', 'status', 'display_identifier', 'channel_health']);

        // Conv count per channel_id
        $convBase = Conversation::query()->where('business_id', $businessId);
        $this->applyChannelAclFilter($convBase, $businessId, $userId);
        $convCounts = $convBase
            ->selectRaw('channel_id, COUNT(*) as cnt')
            ->groupBy('channel_id')
            ->pluck('cnt', 'channel_id')
            ->toArray();

        return $channels->map(fn ($ch) => [
            'id' => $ch->id,
            'channel_type' => $ch->type,
            'label' => $ch->label,
            'handle' => $ch->display_identifier ?? '—',
            'status' => $ch->status === 'active' ? 'ativo' : 'em_breve',
            'owner' => null, // TODO US-WA-XXX: derivar de granted_by_user_id majoritário
            'channel_health' => $ch->channel_health,
            'count' => (int) ($convCounts[$ch->id] ?? 0),
        ])->all();
    }

    /**
     * Tags catálogo + seed defaults idempotente (compat ensureDefaultTags do InboxController).
     *
     * @return array<int, array{id: int, slug: string, label: string, color: string}>
     */
    protected function buildAvailableTagsPayload(int $businessId): array
    {
        $this->ensureDefaultTags($businessId);
        return Tag::query()
            ->where('business_id', $businessId)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'slug', 'label', 'color'])
            ->map(fn (Tag $t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'label' => $t->label,
                'color' => $t->color,
            ])->all();
    }

    /**
     * Seed defaults idempotente (paridade com InboxController::ensureDefaultTags).
     */
    protected function ensureDefaultTags(int $businessId): void
    {
        $existing = Tag::query()->where('business_id', $businessId)->count();
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
     * Heurística tag → fila (paridade exata com InboxController::deriveQueueFromTags).
     *
     * @return array{slug: string, label: string, hue: int, sla: ?string}
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
     * Conversation pro componente lista (ConversationListV4).
     */
    protected function convToListArray(Conversation $c): array
    {
        $channel = $c->channel;
        $tagSlugs = $c->relationLoaded('tags') ? $c->tags->pluck('slug')->all() : [];

        return [
            'id' => $c->id,
            'channel_id' => $c->channel_id,
            'channel_label' => $channel?->label,
            'channel_type' => $channel?->type,
            'channel_status' => $channel?->status ?? 'em_breve',
            'channel_health' => $channel?->channel_health ?? 'never_checked',
            'customer_external_id' => $c->customer_external_id,
            'contact_name' => $c->contact_name ?? $c->customer_external_id,
            'status' => $c->status,
            'unread_count' => (int) $c->unread_count,
            'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            'last_message_preview' => $c->last_message_preview,
            'last_message_direction' => $c->last_message_direction,
            'last_inbound_at' => optional($c->last_inbound_at)->toIso8601String(),
            'tags' => $c->relationLoaded('tags')
                ? $c->tags->map(fn ($t) => ['id' => $t->id, 'slug' => $t->slug, 'label' => $t->label, 'color' => $t->color])->all()
                : [],
            'queue' => $this->deriveQueueFromTags($tagSlugs),
            // Preview-only — canal ainda em homologação (status != 'active')
            'preview_only' => ($channel?->status ?? 'active') !== 'active',
        ];
    }

    /**
     * Conversation pro componente thread (ConversationThreadV4).
     */
    protected function convToThreadArray(Conversation $c): array
    {
        $channel = $c->channel;
        $tagSlugs = $c->relationLoaded('tags') ? $c->tags->pluck('slug')->all() : [];

        return [
            'id' => $c->id,
            'channel_id' => $c->channel_id,
            'channel_label' => $channel?->label,
            'channel_type' => $channel?->type,
            'channel_status' => $channel?->status ?? 'em_breve',
            'channel_handle' => $channel?->display_identifier,
            'channel_health' => $channel?->channel_health ?? 'never_checked',
            'customer_external_id' => $c->customer_external_id,
            'contact_name' => $c->contact_name ?? $c->customer_external_id,
            'status' => $c->status,
            'is_blocked' => (bool) $c->is_blocked,
            'last_inbound_at' => optional($c->last_inbound_at)->toIso8601String(),
            'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            'created_at' => optional($c->created_at)->toIso8601String(),
            'tags' => $c->relationLoaded('tags')
                ? $c->tags->map(fn ($t) => ['id' => $t->id, 'slug' => $t->slug, 'label' => $t->label, 'color' => $t->color])->all()
                : [],
            'queue' => $this->deriveQueueFromTags($tagSlugs),
            'preview_only' => ($channel?->status ?? 'active') !== 'active',
        ];
    }

    /**
     * Message pro componente thread (bubble individual).
     */
    protected function msgToUiArray(Message $m): array
    {
        $user = $m->senderUser;
        $senderName = $user
            ? ($user->first_name ?: $user->surname ?: $user->last_name ?: "Atendente #{$user->id}")
            : null;

        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'provider' => $m->provider,
            'type' => $m->type,
            'body' => $m->body,
            'status' => $m->status,
            'failed_reason' => $m->failed_reason,
            'sender_kind' => $m->sender_kind,
            'sender_user_name' => $senderName,
            'is_internal_note' => (bool) $m->is_internal_note,
            'created_at' => $m->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    // ===========================================================================
    // ACL helpers — paridade com InboxController (single source of truth de regra
    // ACL canal=fila ADR 0135 + US-WA-069 fica no InboxController; aqui só replica
    // a interface por enquanto. Refactor futuro: extrair pra trait/Service).
    // ===========================================================================

    protected function applyChannelAclFilter($query, int $businessId, int $userId): void
    {
        if ($this->canSeeAllChannels()) {
            return;
        }
        $query->whereIn('channel_id', $this->allowedChannelIdsSubquery($businessId, $userId));
    }

    protected function allowedChannelIdsSubquery(int $businessId, int $userId)
    {
        return ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->select('channel_id');
    }

    protected function canSeeAllChannels(): bool
    {
        return (bool) (auth()->user()?->can('whatsapp.view-all-phones') ?? false);
    }

    protected function ensureChannelIdAccessOrAbort(int $channelId, int $businessId, int $userId): void
    {
        $channelExists = Channel::query()
            ->where('id', $channelId)
            ->where('business_id', $businessId)
            ->exists();
        if (! $channelExists) {
            abort(403, 'Canal não encontrado ou sem acesso.');
        }

        if ($this->canSeeAllChannels()) {
            return;
        }

        $hasAccess = ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('channel_id', $channelId)
            ->whereNull('revoked_at')
            ->exists();

        if (! $hasAccess) {
            abort(403, 'Sem acesso a este canal.');
        }
    }
}
