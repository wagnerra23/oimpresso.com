<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\Tag;
use Modules\Whatsapp\Entities\WhatsappQueue;
use Modules\Whatsapp\Entities\WhatsappTemplate;
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
        $channelTypeFilter = $request->input('channel'); // type=whatsapp_whatsmeow, etc
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
        $customerContext = null;
        if ($threadId) {
            $threadQuery = Conversation::query()
                ->where('business_id', $businessId)
                ->with(['channel', 'tags:id,slug,label,color', 'assignedUser:id,first_name,surname,last_name']);
            $this->applyChannelAclFilter($threadQuery, $businessId, $userId);
            $threadModel = $threadQuery->find($threadId);
            if ($threadModel) {
                $thread = $this->convToThreadArray($threadModel);
                // Onda 3 — Saldo + Histórico do cliente (Tier 0; contact da conversa)
                $customerContext = $this->buildCustomerContextPayload(
                    $businessId,
                    $threadModel->contact_id !== null ? (int) $threadModel->contact_id : null,
                );
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
            'availableAssignees' => Inertia::defer(fn () => $this->buildAvailableAssigneesPayload($businessId)),
            'availableTemplates' => Inertia::defer(fn () => $this->buildAvailableTemplatesPayload($businessId)),
            // US-WA-301 (ADR 0267) — rows completas pro painel "Filas" (Sheet CRUD)
            'queuesAdmin' => Inertia::defer(fn () => $this->buildQueuesAdminPayload($businessId)),

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
            // Onda 3 — contexto comercial do cliente (Saldo + Histórico); eager,
            // refresca no thread switch via only:['customerContext'].
            'customerContext' => $customerContext,
            'centrifugoConfig' => $centrifugoConfig,

            // US-WA-301 (ADR 0267) — filas agora vêm do DB (seed lazy do config
            // na 1ª visita; fallback config se DB vazio). Shape QueueConfig compat.
            'queues' => $this->getQueuesConfig($businessId),
            'defaultQueue' => (string) config('whatsapp.default_queue', 'comercial'),
            'canManageQueues' => (bool) (auth()->user()?->can('whatsapp.settings.manage') ?? false),
        ]);
    }

    /**
     * Onda 3 — contexto comercial do cliente da conversa (Saldo + Histórico) pra
     * sidebar. Reusa o agregador canônico do painel Cliente (ContactController):
     * uma query group-by em `transactions`. Tier 0 ADR 0093 — escopado em
     * `business_id`; o `contact_id` vem da conversa (já do business atual).
     * `total_paid` não existe no schema → subquery em `transaction_payments`
     * (mesma expressão do painel Cliente). Contagem/LTV só de venda real
     * (`status != 'draft'`); saldo só de `due`/`partial`.
     *
     * @return array{linked: bool, sells_count: int, ltv: float, saldo_aberto: float}
     */
    protected function buildCustomerContextPayload(int $businessId, ?int $contactId): array
    {
        if ($contactId === null) {
            return ['linked' => false, 'sells_count' => 0, 'ltv' => 0.0, 'saldo_aberto' => 0.0];
        }

        // DB::table (não Eloquent) — agregado puro, sem hidratar Model nem global
        // scope; o filtro Tier 0 é explícito aqui.
        $row = \DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('contact_id', $contactId)
            ->where('type', 'sell')
            ->selectRaw("
                SUM(CASE WHEN status != 'draft' THEN 1 ELSE 0 END) AS sells_count,
                SUM(CASE WHEN status != 'draft' THEN final_total ELSE 0 END) AS ltv,
                SUM(CASE WHEN status != 'draft' AND payment_status IN ('due','partial')
                    THEN (final_total - (SELECT COALESCE(SUM(tp.amount), 0) FROM transaction_payments tp WHERE tp.transaction_id = transactions.id))
                    ELSE 0 END) AS saldo_aberto
            ")
            ->first();

        return [
            'linked' => true,
            'sells_count' => (int) ($row?->sells_count ?? 0),
            'ltv' => (float) ($row?->ltv ?? 0),
            'saldo_aberto' => (float) ($row?->saldo_aberto ?? 0),
        ];
    }

    /** Cache per-request do mapa slug => QueueConfig (evita reler DB nos payloads). */
    protected ?array $queuesConfigCache = null;

    /**
     * US-WA-301 (ADR 0267) — filas do business: DB com seed lazy + fallback config.
     *
     * Shape compat com `QueueConfig` do frontend (label/hue/sla/trigger_tags) —
     * `sla` humanizado de `sla_minutes`. Princípio duro 8 (ADR 0094): se a
     * leitura DB falhar/vier vazia, degrade gracioso pro config estático.
     *
     * @return array<string, array{label: string, hue: int, sla: ?string, trigger_tags: array<int, string>}>
     */
    protected function getQueuesConfig(int $businessId): array
    {
        if ($this->queuesConfigCache !== null) {
            return $this->queuesConfigCache;
        }

        try {
            $this->ensureDefaultQueues($businessId);
            $rows = WhatsappQueue::query()
                ->where('business_id', $businessId)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get();
        } catch (\Throwable) {
            $rows = collect();
        }

        if ($rows->isEmpty()) {
            // Normaliza o config pro shape completo (PHPStan + consumidores
            // acessam offsets direto sem ?? redundante).
            $fallback = [];
            foreach ((array) config('whatsapp.queues', []) as $slug => $cfg) {
                $fallback[(string) $slug] = [
                    'label' => (string) ($cfg['label'] ?? ucfirst((string) $slug)),
                    'hue' => (int) ($cfg['hue'] ?? 220),
                    'sla' => $cfg['sla'] ?? null,
                    'trigger_tags' => (array) ($cfg['trigger_tags'] ?? []),
                ];
            }

            return $this->queuesConfigCache = $fallback;
        }

        return $this->queuesConfigCache = $rows
            ->mapWithKeys(fn (WhatsappQueue $q) => [$q->slug => [
                'label' => $q->label,
                'hue' => $q->hue,
                'sla' => $q->slaHuman(),
                'trigger_tags' => (array) $q->trigger_tags,
            ]])
            ->all();
    }

    /**
     * Seed lazy idempotente das filas default a partir de `config('whatsapp.queues')`
     * (pattern ensureDefaultTags — migração sem quebra, ADR 0267).
     */
    protected function ensureDefaultQueues(int $businessId): void
    {
        $existing = WhatsappQueue::query()->where('business_id', $businessId)->count();
        if ($existing > 0) {
            return;
        }
        $sort = 10;
        foreach ((array) config('whatsapp.queues', []) as $slug => $cfg) {
            WhatsappQueue::query()->create([
                'business_id' => $businessId,
                'slug' => (string) $slug,
                'label' => (string) ($cfg['label'] ?? ucfirst((string) $slug)),
                'hue' => (int) ($cfg['hue'] ?? 220),
                'sla_minutes' => WhatsappQueue::slaToMinutes($cfg['sla'] ?? null),
                'dist' => 'manual',
                'trigger_tags' => (array) ($cfg['trigger_tags'] ?? []),
                'members' => [],
                'sort_order' => $sort,
            ]);
            $sort += 10;
        }
    }

    /**
     * US-WA-301 — rows completas pro painel "Filas" (Sheet CRUD da V4).
     *
     * @return array<int, array{id: int, slug: string, label: string, hue: int, sla_minutes: ?int, sla: ?string, dist: string, trigger_tags: array<int, string>, sort_order: int, is_default: bool}>
     */
    protected function buildQueuesAdminPayload(int $businessId): array
    {
        // INCIDENTE 2026-06-10 ("carregando canais erro 500"): payload deferred
        // SEM guard derruba o GRUPO Inertia::defer inteiro quando a tabela
        // whatsapp_queues ainda não existe (deploy com rsync feito e migrate
        // cancelado/pendente). Princípio duro 8 (ADR 0094): degrade gracioso —
        // painel Filas mostra vazio, resto da tela vive.
        try {
            $this->ensureDefaultQueues($businessId);
            $defaultSlug = (string) config('whatsapp.default_queue', 'comercial');

            return WhatsappQueue::query()
                ->where('business_id', $businessId)
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get()
                ->map(fn (WhatsappQueue $q) => [
                    'id' => $q->id,
                    'slug' => $q->slug,
                    'label' => $q->label,
                    'hue' => $q->hue,
                    'sla_minutes' => $q->sla_minutes,
                    'sla' => $q->slaHuman(),
                    'dist' => $q->dist,
                    'trigger_tags' => (array) $q->trigger_tags,
                    'sort_order' => $q->sort_order,
                    'is_default' => $q->slug === $defaultSlug,
                ])
                ->all();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[caixa-unificada] queuesAdmin degrade gracioso', [
                'business_id' => $businessId,
                'error_class' => get_class($e),
            ]);

            return [];
        }
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

        // mediaInbound24h: convs com mensagem inbound type=image/video/audio/document nas 24h.
        // Lê da relação Conversation::messages (tabela `messages` do schema novo polimórfico,
        // ADR 0135) — NÃO da tabela legacy `whatsapp_messages`, que não recebe os dados do
        // schema novo: o join cross-schema `whatsapp_messages.conversation_id = conversations.id`
        // não casava nenhuma row e o filtro voltava sempre vazio. Mesmo padrão correto do
        // InboxController::index.
        if ($mediaInbound24h) {
            $convQuery->whereHas('messages', fn ($q) => $q
                ->where('direction', 'inbound')
                ->whereIn('type', ['image', 'video', 'audio', 'document'])
                ->where('created_at', '>=', now()->subHours(24)));
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
        // Catálogo canônico dos 7 tipos suportados (Cowork visual).
        // WhatsApp scan-QR LIVE = `whatsapp_whatsmeow` (WuzAPI/whatsmeow, ADR 0204) — é o
        // type REAL de onde as conversas chegam. Baileys foi descontinuado e deletado
        // (ADR 0202); uma row `whatsapp_baileys` jamais casaria com um Channel ativo
        // (`$activeTypesCount[$row['id']]`), derrubando TODOS os chips pra 'em_breve' e
        // escondendo o canal vivo. O `id` da row também é o valor do filtro `?channel=`
        // (whereHas channel.type), então precisa bater com a constante canônica.
        $catalog = [
            ['id' => 'whatsapp_whatsmeow', 'label' => 'WhatsApp', 'short' => 'WhatsApp', 'hue' => 145, 'glyph' => 'W'],
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
     * US-WA-303 — templates prontos pra envio (picker ⌘T do composer).
     *
     * Shape espelha `ReadyTemplate` do frontend legacy (TemplatePicker reusado).
     * Só status ready (`LOCAL` Z-API/Baileys + `APPROVED` Meta) — picker filtra
     * por provider do canal da thread no client. Tier 0 ADR 0093: business atual.
     *
     * @return array<int, array{id: int, name: string, language: string, category: string, provider: string, status: string, body: string}>
     */
    protected function buildAvailableTemplatesPayload(int $businessId): array
    {
        return WhatsappTemplate::query()
            ->where('business_id', $businessId)
            ->whereIn('status', ['LOCAL', 'APPROVED'])
            ->orderBy('name')
            ->get()
            ->map(fn (WhatsappTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'language' => $t->language,
                'category' => $t->category,
                'provider' => $t->provider,
                'status' => $t->status,
                // Body cru com placeholders {{1}}/{{nome}} — picker expande no preview
                'body' => $t->expandBody([]),
            ])
            ->all();
    }

    /**
     * US-WA-302 — operadores atribuíveis (assignee picker da sidebar).
     *
     * Tier 0 ADR 0093: APENAS users do business atual. Inclui users com grant
     * ativo em algum canal (`channel_user_access`) OU com permission
     * `whatsapp.access`/`whatsapp.send` (mesmo critério do
     * ChannelsController::buildCandidates). O check de permission é guarded —
     * se a infra de permissions não resolver (ex: ambiente de teste sem as
     * tabelas), degrade gracioso pro critério de grant DB-only.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function buildAvailableAssigneesPayload(int $businessId): array
    {
        $grantedIds = ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->whereNull('revoked_at')
            ->pluck('user_id')
            ->unique();

        $users = User::where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'surname', 'last_name']);

        return $users
            ->filter(function (User $u) use ($grantedIds) {
                if ($grantedIds->contains($u->id)) {
                    return true;
                }
                try {
                    return $u->can('whatsapp.access') || $u->can('whatsapp.send');
                } catch (\Throwable) {
                    return false;
                }
            })
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => trim(implode(' ', array_filter([$u->first_name, $u->surname, $u->last_name]))) ?: "Operador #{$u->id}",
            ])
            ->values()
            ->all();
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
     * Heurística tag → fila (paridade com InboxController::deriveQueueFromTags).
     *
     * US-WA-301 (ADR 0267): filas agora vêm de getQueuesConfig (DB com seed
     * lazy + fallback config) — shape e semântica idênticos.
     *
     * @return array{slug: string, label: string, hue: int, sla: ?string}
     */
    protected function deriveQueueFromTags(array $tagSlugs): array
    {
        $businessId = (int) session('user.business_id');
        $queues = $this->getQueuesConfig($businessId);
        $default = (string) config('whatsapp.default_queue', 'comercial');
        $matched = $default;
        foreach ($queues as $slug => $cfg) {
            // Shape garantido por getQueuesConfig (offsets sempre presentes)
            $triggers = $cfg['trigger_tags'];
            if ($triggers === []) {
                continue;
            }
            if (array_intersect($tagSlugs, $triggers) !== []) {
                $matched = $slug;
                break;
            }
        }
        $cfg = $queues[$matched] ?? ['label' => ucfirst($matched), 'hue' => 0, 'sla' => null, 'trigger_tags' => []];

        return [
            'slug' => $matched,
            'label' => (string) $cfg['label'],
            'hue' => (int) $cfg['hue'],
            'sla' => $cfg['sla'],
        ];
    }

    /**
     * US-WA-305 — fila efetiva da conversa: `queue_override` (manual) vence a
     * heurística tag→fila quando preenchido E o slug ainda existe nas filas do
     * business (slug órfão de fila deletada cai no fallback automático).
     *
     * @return array{slug: string, label: string, hue: int, sla: ?string}
     */
    protected function resolveQueue(Conversation $c, array $tagSlugs): array
    {
        $override = $c->queue_override;
        if ($override !== null && $override !== '') {
            $businessId = (int) session('user.business_id');
            $queues = $this->getQueuesConfig($businessId);
            if (isset($queues[$override])) {
                $cfg = $queues[$override];

                // Shape garantido por getQueuesConfig — offsets sempre presentes
                return [
                    'slug' => $override,
                    'label' => (string) $cfg['label'],
                    'hue' => (int) $cfg['hue'],
                    'sla' => $cfg['sla'],
                ];
            }
        }

        return $this->deriveQueueFromTags($tagSlugs);
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
            'queue' => $this->resolveQueue($c, $tagSlugs),
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
            // US-WA-302 — assignee picker (sidebar section 2)
            'assigned_user_id' => $c->assigned_user_id !== null ? (int) $c->assigned_user_id : null,
            'assigned_user_name' => $c->relationLoaded('assignedUser') && $c->assignedUser
                ? trim(implode(' ', array_filter([$c->assignedUser->first_name, $c->assignedUser->surname, $c->assignedUser->last_name]))) ?: "Operador #{$c->assigned_user_id}"
                : null,
            'last_inbound_at' => optional($c->last_inbound_at)->toIso8601String(),
            'last_message_at' => optional($c->last_message_at)->toIso8601String(),
            'created_at' => optional($c->created_at)->toIso8601String(),
            'tags' => $c->relationLoaded('tags')
                ? $c->tags->map(fn ($t) => ['id' => $t->id, 'slug' => $t->slug, 'label' => $t->label, 'color' => $t->color])->all()
                : [],
            'queue' => $this->resolveQueue($c, $tagSlugs),
            // US-WA-305 — sidebar mostra se a fila é override manual ou heurística
            'queue_is_override' => $c->queue_override !== null && $c->queue_override !== ''
                && isset($this->getQueuesConfig((int) session('user.business_id'))[$c->queue_override]),
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
            // M6+M7 fix 2026-05-28: expor media fields via Controller-served URL.
            // M6 (PR #1846): media_url + media_thumbnail_url no UI shape.
            // M7 (este PR): rotear via route('atendimento.midia.show') porque
            // Hostinger LiteSpeed bloqueia /storage/* direct serve com 403.
            'media_url' => $m->media_url ? route('atendimento.midia.show', ['path' => $m->media_url]) : null,
            'media_thumbnail_url' => $m->media_thumbnail_url ? route('atendimento.midia.show', ['path' => $m->media_thumbnail_url]) : null,
            'media_mime' => $m->media_mime,
            'media_size_bytes' => $m->media_size_bytes ? (int) $m->media_size_bytes : null,
            'media_filename' => $m->media_filename,
            'media_transcription' => $m->media_transcription,
            'media_download_status' => $m->media_download_status,
        ];
    }

    /**
     * M7 fix 2026-05-28 — serve mídia via Controller (não /storage/* direct).
     *
     * Hostinger LiteSpeed retorna 403 pra /storage/whatsapp/... mesmo com symlink
     * existente + permissões corretas (segurança contra symlink traversal).
     * Solução: rotear via Controller que:
     *   1. Valida path traversal (..)
     *   2. Tier 0 — só serve arquivos do business_id do user atual
     *   3. Storage::disk('public')->response($path) — Laravel serve com correct
     *      Content-Type + Cache-Control + ETag
     *
     * Performance: pra mídias <16MB OK. Pra arquivos maiores usar download()
     * + range support (não implementado nesta fase).
     */
    public function serveMedia(Request $request, string $path)
    {
        // Anti path-traversal
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            abort(403, 'Invalid path');
        }

        // Tier 0 ADR 0093 — path canon: whatsapp/{businessId}/YYYY-MM/<uuid>.<ext>
        $businessId = (int) (session('user.business_id') ?? auth()->user()?->business_id ?? 0);
        if ($businessId <= 0) {
            abort(403, 'No business context');
        }
        $expectedPrefix = "whatsapp/{$businessId}/";
        if (! str_starts_with($path, $expectedPrefix)) {
            abort(403, "Cross-tenant access denied (expected prefix: {$expectedPrefix})");
        }

        if (! Storage::disk('public')->exists($path)) {
            abort(404, 'Media not found');
        }

        // Storage::response gera proper headers + suporta inline (não force download)
        return Storage::disk('public')->response($path);
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
