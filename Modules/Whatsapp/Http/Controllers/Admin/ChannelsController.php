<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Http\Controllers\Admin;

use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\ChannelUserAccess;
use Modules\Whatsapp\Http\Requests\ChannelRequest;
use Modules\Whatsapp\Http\Requests\GrantChannelUserRequest;

/**
 * ChannelsController — CRUD omnichannel (ADR 0135 Fase 0).
 *
 * Tela `/atendimento/canais` substitui long-term `/whatsapp/settings`. Por
 * enquanto coexistem — refactor drivers/jobs pra consumir Channel direto
 * vai em PR seguinte.
 *
 * Permission: `whatsapp.settings.manage` (reusada ao invés de criar nova
 * `atendimento.channels.manage` — dev cost vs valor não compensa nesta fase).
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
class ChannelsController extends Controller
{
    public function index(): Response
    {
        $businessId = (int) session('user.business_id');

        // D-14 perf 2026-05-15 (skill `inertia-defer-default` Tier 0):
        // `channels` (query + map) vira Inertia::defer. Outras props são
        // arrays static / IDs (eager OK).
        return Inertia::render('Atendimento/Channels/Index', [
            // ─── Eager (custo zero) ───
            'businessId' => $businessId,
            'availableTypes' => $this->availableTypesForUi(),
            'forbiddenDrivers' => config('whatsapp.forbidden_drivers', []),

            // ─── Defer (query + map) ───
            'channels' => Inertia::defer(fn () => $this->buildChannelsPayload($businessId)),
        ]);
    }

    /**
     * D-14 perf — channels list (query + map toUiArray).
     */
    protected function buildChannelsPayload(int $businessId): array
    {
        return Channel::query()
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->get()
            ->map(fn (Channel $c) => $this->toUiArray($c))
            ->all();
    }

    public function store(ChannelRequest $request): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $userId = (int) (session('user.id') ?? auth()->id() ?? 0);

        $data = $request->validated();

        $channel = new Channel();
        $channel->business_id = $businessId;
        $channel->label = $data['label'];
        $channel->type = $data['type'];
        $channel->status = 'setup';
        $channel->config_json = $data['config'] ?? [];
        $channel->handles_repair_status = (bool) ($data['handles_repair_status'] ?? false);
        $channel->handles_billing = (bool) ($data['handles_billing'] ?? false);
        $channel->handles_jana_bot = (bool) ($data['handles_jana_bot'] ?? true);
        $channel->handles_outbound_default = (bool) ($data['handles_outbound_default'] ?? false);
        $channel->bot_enabled = (bool) ($data['bot_enabled'] ?? false);

        // LGPD obrigatório pra TODOS drivers não-oficiais (Baileys + whatsmeow ADR 0204).
        if (in_array($data['type'], [Channel::TYPE_WHATSAPP_BAILEYS, Channel::TYPE_WHATSAPP_WHATSMEOW], true)) {
            $channel->lgpd_acknowledged_at = now();
            $channel->lgpd_acknowledged_by_user_id = $userId;
        }

        // Display identifier inferido per-type pra UI mostrar
        $channel->display_identifier = match ($data['type']) {
            Channel::TYPE_WHATSAPP_BAILEYS => $data['config']['baileys_phone_e164'] ?? null,
            Channel::TYPE_WHATSAPP_WHATSMEOW => $data['config']['whatsmeow_phone_e164'] ?? null,
            Channel::TYPE_WHATSAPP_ZAPI => $data['config']['zapi_instance_id'] ?? null,
            Channel::TYPE_WHATSAPP_META => $data['config']['meta_phone_number_id'] ?? null,
            default => null,
        };

        $channel->save();

        return back()->with('success', "Canal '{$channel->label}' criado. Status: setup pendente.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $businessId = (int) session('user.business_id');

        $channel = Channel::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $label = $channel->label;
        $channel->delete();

        return back()->with('success', "Canal '{$label}' removido.");
    }

    /**
     * Detalhe do canal — Page Show com tabs (Config | Usuários | Histórico).
     *
     * US-WA-068. Carrega:
     *  - canal completo (toUiArray)
     *  - lista de users com acesso ATIVO (revoked_at NULL) com join users
     *  - lista de users DISPONÍVEIS pra grant (mesmo business, tem
     *    whatsapp.access ou whatsapp.send, não já tem grant ativo)
     *  - audit log curto (últimas 20 entradas grant+revoke do canal)
     */
    public function show(int $id): Response
    {
        $businessId = (int) session('user.business_id');

        // findOrFail eager (404 cross-tenant Tier 0 ADR 0093 antes de render).
        $channel = Channel::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        // D-14 perf 2026-05-15 (skill `inertia-defer-default` Tier 0):
        // 3 listas pesadas (users + availableUsers + audit) viram Inertia::defer.
        // Cada uma faz 2 queries (rows + users lookup) ou filter `can()` por row.
        return Inertia::render('Atendimento/Channels/Show', [
            // ─── Eager (channel resolvido pra 404 cross-tenant + ID nas tabs) ───
            'channel' => $this->toUiArray($channel),

            // ─── Defer (queries pesadas com joins users) ───
            'users' => Inertia::defer(fn () => $this->buildChannelUsersPayload($businessId, $channel->id)),
            'availableUsers' => Inertia::defer(fn () => $this->buildAvailableUsersPayload($businessId, $channel->id)),
            'audit' => Inertia::defer(fn () => $this->buildAuditPayload($businessId, $channel->id)),
        ]);
    }

    /**
     * D-14 perf — users ativos com acesso ao canal (rows + users lookup).
     */
    protected function buildChannelUsersPayload(int $businessId, int $channelId): array
    {
        $accessRows = ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('channel_id', $channelId)
            ->active()
            ->orderBy('granted_at', 'desc')
            ->get();

        $userIds = $accessRows->pluck('user_id')
            ->merge($accessRows->pluck('granted_by_user_id'))
            ->unique()
            ->values()
            ->all();
        $usersById = User::whereIn('id', $userIds)
            ->where('business_id', $businessId)
            ->get(['id', 'first_name', 'last_name', 'email', 'username'])
            ->keyBy('id');

        return $accessRows->map(function (ChannelUserAccess $row) use ($usersById) {
            $u = $usersById->get($row->user_id);
            $granter = $usersById->get($row->granted_by_user_id);
            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'name' => $u ? trim($u->first_name . ' ' . ($u->last_name ?? '')) : "user#{$row->user_id}",
                'email' => $u?->email,
                'granted_at' => optional($row->granted_at)->toIso8601String(),
                'granted_by_user_id' => $row->granted_by_user_id,
                'granted_by_name' => $granter
                    ? trim($granter->first_name . ' ' . ($granter->last_name ?? ''))
                    : "user#{$row->granted_by_user_id}",
            ];
        })->values()->all();
    }

    /**
     * D-14 perf — candidatos pra grant (filter `can()` per row é pesado).
     */
    protected function buildAvailableUsersPayload(int $businessId, int $channelId): array
    {
        // Quem já tem grant ativo no canal — excluir desses
        $alreadyGrantedIds = ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('channel_id', $channelId)
            ->active()
            ->pluck('user_id')
            ->all();

        $candidatesQuery = User::where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->whereNotIn('id', $alreadyGrantedIds)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'username']);

        return $candidatesQuery->filter(function (User $u) {
            return $u->can('whatsapp.access') || $u->can('whatsapp.send');
        })->map(function (User $u) {
            return [
                'id' => $u->id,
                'name' => trim($u->first_name . ' ' . ($u->last_name ?? '')),
                'email' => $u->email,
                'username' => $u->username,
            ];
        })->values()->all();
    }

    /**
     * D-14 perf — audit log (últimas 20 grant+revoke) com users lookup.
     */
    protected function buildAuditPayload(int $businessId, int $channelId): array
    {
        $audit = ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('channel_id', $channelId)
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get();

        $auditUserIds = $audit->pluck('user_id')
            ->merge($audit->pluck('granted_by_user_id'))
            ->merge($audit->pluck('revoked_by_user_id')->filter())
            ->unique()
            ->values()
            ->all();
        $auditUsers = User::whereIn('id', $auditUserIds)
            ->where('business_id', $businessId)
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        return $audit->map(function (ChannelUserAccess $row) use ($auditUsers) {
            $userName = fn ($uid) => $uid && $auditUsers->get($uid)
                ? trim($auditUsers->get($uid)->first_name . ' ' . ($auditUsers->get($uid)->last_name ?? ''))
                : ($uid ? "user#{$uid}" : null);

            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'user_name' => $userName($row->user_id),
                'granted_at' => optional($row->granted_at)->toIso8601String(),
                'granted_by_name' => $userName($row->granted_by_user_id),
                'revoked_at' => optional($row->revoked_at)->toIso8601String(),
                'revoked_by_name' => $userName($row->revoked_by_user_id),
                'is_active' => $row->revoked_at === null,
            ];
        })->values()->all();
    }

    /**
     * Grant de acesso ao canal pra um user (US-WA-068).
     *
     * - Valida cross-tenant via GrantChannelUserRequest (user_id mesmo business
     *   + tem whatsapp.access ou whatsapp.send).
     * - Idempotente: re-grant após revoke funciona (UNIQUE permite via
     *   revoked_at). Grant duplicado ativo retorna no-op com aviso.
     * - AuditLog write via Log::info (estrutura padrão Whatsapp).
     */
    public function grantUser(GrantChannelUserRequest $request, int $channelId): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $currentUserId = (int) (session('user.id') ?? auth()->id() ?? 0);

        $channel = Channel::query()
            ->where('business_id', $businessId)
            ->findOrFail($channelId);

        $userId = (int) $request->validated('user_id');

        // Já existe grant ativo? → no-op (não cria duplicata)
        $existingActive = ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->active()
            ->first();

        if ($existingActive) {
            return back()->with('info', 'Usuário já tem acesso ativo a este canal.');
        }

        DB::transaction(function () use ($channel, $userId, $businessId, $currentUserId) {
            ChannelUserAccess::create([
                'business_id' => $businessId,
                'channel_id' => $channel->id,
                'user_id' => $userId,
                'granted_by_user_id' => $currentUserId,
                'granted_at' => now(),
            ]);
        });

        Log::info('[whatsapp.channel_user_access.granted]', [
            'business_id' => $businessId,
            'channel_id' => $channel->id,
            'user_id' => $userId,
            'granted_by_user_id' => $currentUserId,
        ]);

        return back()->with('success', 'Acesso concedido.');
    }

    /**
     * Revoke (soft) de acesso ao canal — set revoked_at + revoked_by_user_id.
     *
     * Preserva audit history (NÃO deleta a row). Re-grant possível depois
     * via grantUser.
     */
    public function revokeUser(int $channelId, int $userId): RedirectResponse
    {
        $businessId = (int) session('user.business_id');
        $currentUserId = (int) (session('user.id') ?? auth()->id() ?? 0);

        $channel = Channel::query()
            ->where('business_id', $businessId)
            ->findOrFail($channelId);

        $row = ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->active()
            ->first();

        if (! $row) {
            return back()->with('info', 'Usuário não tem acesso ativo a este canal.');
        }

        DB::transaction(function () use ($row, $currentUserId) {
            $row->revoked_at = now();
            $row->revoked_by_user_id = $currentUserId;
            $row->save();
        });

        Log::info('[whatsapp.channel_user_access.revoked]', [
            'business_id' => $businessId,
            'channel_id' => $channel->id,
            'user_id' => $userId,
            'revoked_by_user_id' => $currentUserId,
        ]);

        return back()->with('success', 'Acesso revogado.');
    }

    /**
     * Dispara connect Baileys + poll status até QR ou timeout.
     *
     * Quick-path pra testar daemon CT 100 sem refatorar BaileysConnectJob ainda
     * (ADR 0135 Fase 0 PR C completo virá depois). Funciona só pra
     * `whatsapp_baileys` por ora.
     */
    public function connect(int $id): JsonResponse
    {
        $businessId = (int) session('user.business_id');
        $channel = Channel::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        // ADR 0204 (2026-05-27): branch separado pra whatsmeow.
        // Baileys foi descontinuado (ADR 0202) mas o código de connect persiste
        // pra arqueologia + migração ainda-em-curso. whatsmeow tem fluxo próprio.
        if ($channel->type === Channel::TYPE_WHATSAPP_WHATSMEOW) {
            return $this->connectWhatsmeow($channel);
        }

        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS) {
            return response()->json([
                'ok' => false,
                'error' => "Connect rápido só implementado pra Baileys/Whatsmeow nesta fase. Tipo atual: {$channel->type}",
            ], 422);
        }

        $cfg = $channel->config_json ?? [];
        $phone = $cfg['baileys_phone_e164'] ?? null;
        if (empty($phone)) {
            return response()->json([
                'ok' => false,
                'error' => 'baileys_phone_e164 ausente em config_json.',
            ], 422);
        }

        $daemonUrl = config('whatsapp.baileys.daemon_url');
        $apiKey = config('whatsapp.baileys.api_key');
        $timeout = (int) config('whatsapp.baileys.request_timeout', 15);

        if (empty($daemonUrl) || empty($apiKey)) {
            return response()->json([
                'ok' => false,
                'error' => 'WHATSAPP_BAILEYS_DAEMON_URL ou _API_KEY não configurados no .env.',
            ], 503);
        }

        // Instance ID estável pelo channel_uuid
        $instanceId = 'ch-' . str_replace('-', '', $channel->channel_uuid);

        try {
            // Pre-flight: detecta instância banned/disconnected/zombie no daemon
            // e PURGA (DELETE) antes do connect — sem isso, daemon reusa creds
            // revogadas e nunca emite QR novo (state fica banned).
            //
            // Bug catalogado 2026-05-13: Wagner reportou "QR não abre"; 2 channels
            // biz=1 estavam `banned: logged_out` (celular desconectou via
            // Aparelhos Conectados). Sem auto-purge, /connect era no-op silencioso.
            //
            // Status states que PRECISAM de purge:
            //  - banned (logged_out, multidevice_mismatch, forbidden, etc.)
            //  - disconnected (socket morto)
            //  - error (qualquer estado degradado)
            //
            // Estados que NÃO precisam (fresh start já vai conectar):
            //  - 404 not_found (instância nunca existiu)
            //  - connecting / qr_required / connected (já está OK ou em fluxo)
            $statusResponse = Http::withToken($apiKey)
                ->withoutVerifying()
                ->timeout($timeout)
                ->get("{$daemonUrl}/instances/{$instanceId}/status");

            $needsPurge = false;
            if ($statusResponse->successful()) {
                $currentState = $statusResponse->json('state');
                $needsPurge = in_array($currentState, ['banned', 'disconnected', 'error'], true);
            }
            // 404 = não existe = nada a purgar. 5xx = melhor não fazer DELETE (defensive).

            if ($needsPurge) {
                Log::info('baileys.connect_autopurge_banned', [
                    'channel_id' => $channel->id,
                    'instance_id' => $instanceId,
                    'state' => $statusResponse->json('state'),
                    'ban_reason' => $statusResponse->json('ban_reason'),
                ]);

                $purgeResponse = Http::withToken($apiKey)
                    ->withoutVerifying()
                    ->timeout($timeout)
                    ->delete("{$daemonUrl}/instances/{$instanceId}");

                if (! $purgeResponse->successful() && $purgeResponse->status() !== 404) {
                    Log::warning('baileys.connect_autopurge_failed', [
                        'channel_id' => $channel->id,
                        'status' => $purgeResponse->status(),
                        'body' => $purgeResponse->body(),
                    ]);
                    // Não aborta — segue tentando connect. Daemon talvez recupere.
                }
            }

            // Dispara connect (202 + snapshot inicial)
            // FIXME(US-WA-058): withoutVerifying temp — cert Let's Encrypt no CT 100
            // ainda não emitido (DNS propagou após Traefik tentar ACME). Remover
            // após restart container disparar nova emissão.
            $connectResponse = Http::withToken($apiKey)
                ->withoutVerifying()
                ->timeout($timeout)
                ->post("{$daemonUrl}/instances/{$instanceId}/connect", [
                    'business_uuid' => $channel->channel_uuid,
                    'business_id' => $businessId,
                ]);

            if (! $connectResponse->successful()) {
                Log::warning('baileys.connect_failed', [
                    'channel_id' => $channel->id,
                    'status' => $connectResponse->status(),
                    'body' => $connectResponse->body(),
                ]);
                return response()->json([
                    'ok' => false,
                    'error' => 'Daemon retornou ' . $connectResponse->status() . ': ' . $connectResponse->body(),
                ], 502);
            }

            // Marca channel como connecting
            $channel->status = 'setup';
            $channel->channel_health = 'never_checked';
            $channel->save();

            // Marca channel como connecting
            $channel->status = 'setup';
            $channel->channel_health = 'never_checked';
            $channel->save();

            // Baileys 6.7.18 entrega QR já como PNG data URL (~6KB) via
            // QRCode.toDataURL no Instance.ts — cabe em <img src> sem problema.
            // Poll /status até qr field popular (snapshot inclui qr quando
            // state=qr_required). Fallback pairing code se /qr falhar.
            $qrPngDataUrl = null;
            $pairingCode = null;
            $state = null;
            for ($i = 0; $i < 15; $i++) {
                usleep(800_000); // 800ms
                $statusResponse = Http::withToken($apiKey)
                    ->withoutVerifying() // FIXME(US-WA-058): cert LE pendente
                    ->timeout($timeout)
                    ->get("{$daemonUrl}/instances/{$instanceId}/status");

                if ($statusResponse->successful()) {
                    $snap = $statusResponse->json();
                    $state = $snap['state'] ?? null;
                    $qrField = $snap['qr'] ?? null;
                    // qr field é "data:image/png;base64,..." se Baileys 6.7.18+ rasterizou
                    if ($qrField && str_starts_with($qrField, 'data:image')) {
                        $qrPngDataUrl = $qrField;
                        break;
                    }
                    if ($state === 'connected') break;
                }
            }

            // Fallback pairing code se QR não veio (raro)
            if (! $qrPngDataUrl && $state !== 'connected') {
                $pcResponse = Http::withToken($apiKey)
                    ->withoutVerifying()
                    ->timeout($timeout)
                    ->post("{$daemonUrl}/instances/{$instanceId}/pairing-code", [
                        'phone' => $phone,
                    ]);
                if ($pcResponse->status() === 200) {
                    $pairingCode = ($pcResponse->json())['pairing_code'] ?? null;
                }
            }

            return response()->json([
                'ok' => true,
                'instance_id' => $instanceId,
                'qr_png_data_url' => $qrPngDataUrl,
                'pairing_code' => $pairingCode,
                'state' => $state,
                'message' => match (true) {
                    $state === 'connected' => 'Canal já conectado.',
                    $qrPngDataUrl !== null => 'Escaneie o QR no WhatsApp → Configurações → Dispositivos vinculados.',
                    $pairingCode !== null => 'QR não disponível. Use o código numérico abaixo via "Conectar com número de telefone".',
                    default => 'Daemon respondeu sem QR/código. State: ' . ($state ?? 'desconhecido'),
                },
            ]);
        } catch (\Throwable $e) {
            Log::error('baileys.connect_exception', [
                'channel_id' => $channel->id,
                'exception' => $e->getMessage(),
            ]);
            return response()->json([
                'ok' => false,
                'error' => 'Falha ao falar com daemon: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Conecta channel `whatsapp_whatsmeow` ao daemon Go WuzAPI CT 100 (ADR 0204).
     *
     * Fluxo:
     *  1. Se channel ainda não tem `whatsmeow_user_token` → provisiona via
     *     POST /admin/users no daemon (gera token criptograficamente forte +
     *     configura webhook com {business_uuid} pra multi-tenant)
     *  2. POST /session/connect (inicia sessão WhatsApp Web no daemon)
     *  3. GET /session/qr (retorna QR base64 pra UI render no Dialog)
     *  4. Frontend polls /status até state=connected (webhook Connected dispara)
     */
    protected function connectWhatsmeow(Channel $channel): JsonResponse
    {
        try {
            // Resolve business pra obter business_uuid (webhook URL)
            $business = \DB::table('business')
                ->where('id', $channel->business_id)
                ->first();

            if ($business === null || empty($business->uuid)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Business sem uuid — não consigo montar webhook URL multi-tenant.',
                ], 500);
            }

            $driver = app(\Modules\Whatsapp\Services\Drivers\WhatsmeowDriver::class);
            $cfg = $channel->config_json ?? [];

            // Provisiona sessão se ainda não foi
            if (empty($cfg['whatsmeow_user_token'])) {
                $provision = $driver->provisionSession($channel, (string) $business->uuid);

                $cfg['whatsmeow_user_token'] = $provision['token'];
                $cfg['whatsmeow_user_name'] = $provision['name'];
                $cfg['whatsmeow_webhook_url'] = $provision['webhook'];
                $channel->config_json = $cfg;
                $channel->save();
            }

            // Inicia sessão + retorna QR
            $result = $driver->connect($channel);

            $channel->status = 'setup';
            $channel->channel_health = 'never_checked';
            $channel->save();

            return response()->json([
                'ok' => true,
                'qr_png_data_url' => $result['qr_base64']
                    ? 'data:image/png;base64,' . $result['qr_base64']
                    : null,
                'state' => $result['state'],
                'message' => $result['qr_base64']
                    ? 'Escaneie o QR no WhatsApp → Dispositivos vinculados.'
                    : 'Daemon respondeu sem QR. State: ' . ($result['state'] ?? 'desconhecido'),
            ]);
        } catch (\Throwable $e) {
            Log::error('whatsmeow.connect_exception', [
                'channel_id' => $channel->id,
                'exception' => $e->getMessage(),
            ]);
            return response()->json([
                'ok' => false,
                'error' => 'Falha ao falar com daemon whatsmeow: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Lê status atual da instance no daemon (poll do frontend pra detectar
     * connected após scan QR).
     */
    public function status(int $id): JsonResponse
    {
        $businessId = (int) session('user.business_id');
        $channel = Channel::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        // ADR 0204 — branch whatsmeow (status do daemon Go WuzAPI)
        if ($channel->type === Channel::TYPE_WHATSAPP_WHATSMEOW) {
            return $this->statusWhatsmeow($channel);
        }

        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS) {
            return response()->json(['state' => null, 'reason' => 'only_baileys_whatsmeow']);
        }

        $daemonUrl = config('whatsapp.baileys.daemon_url');
        $apiKey = config('whatsapp.baileys.api_key');
        $timeout = (int) config('whatsapp.baileys.request_timeout', 10);
        $instanceId = 'ch-' . str_replace('-', '', $channel->channel_uuid);

        try {
            $r = Http::withToken($apiKey)
                ->withoutVerifying() // FIXME(US-WA-058): cert LE pendente
                ->timeout($timeout)
                ->get("{$daemonUrl}/instances/{$instanceId}/status");

            if (! $r->successful()) {
                return response()->json(['state' => 'unknown', 'http' => $r->status()]);
            }

            $snap = $r->json();
            $state = $snap['state'] ?? 'unknown';

            // Atualiza channel.status quando conectado
            if ($state === 'connected' && $channel->status !== 'active') {
                $channel->status = 'active';
                $channel->channel_health = 'healthy';
                $channel->last_health_check_at = now();
                $channel->save();
            }

            return response()->json([
                'state' => $state,
                'qr_available' => $state === 'qr_required',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'state' => 'error',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * ADR 0204 — status pro daemon Go WuzAPI (channel.type=whatsapp_whatsmeow).
     *
     * WuzAPI /session/status retorna `{ Connected: bool, LoggedIn: bool, Jid: string }`.
     * Mapeia pra states canon: connected / qr_required / disconnected / error.
     */
    protected function statusWhatsmeow(Channel $channel): JsonResponse
    {
        $cfg = $channel->config_json ?? [];
        $userToken = $cfg['whatsmeow_user_token'] ?? null;

        if (empty($userToken)) {
            return response()->json(['state' => 'never_connected', 'qr_available' => false]);
        }

        $daemonUrl = config('whatsapp.whatsmeow.daemon_url');
        $timeout = (int) config('whatsapp.whatsmeow.request_timeout', 10);

        try {
            $r = Http::withHeaders(['Token' => $userToken])
                ->withoutVerifying() // FIXME: cert LE pendente CT 100 dev
                ->timeout($timeout)
                ->get(rtrim((string) $daemonUrl, '/') . '/session/status');

            if (! $r->successful()) {
                return response()->json(['state' => 'unknown', 'http' => $r->status()]);
            }

            $snap = $r->json();
            $connected = (bool) ($snap['Connected'] ?? false);
            $loggedIn = (bool) ($snap['LoggedIn'] ?? false);

            $state = match (true) {
                $connected && $loggedIn => 'connected',
                $connected && ! $loggedIn => 'qr_required',
                default => 'disconnected',
            };

            if ($state === 'connected' && $channel->status !== 'active') {
                $channel->status = 'active';
                $channel->channel_health = 'healthy';
                $channel->last_health_check_at = now();
                $channel->save();
            }

            return response()->json([
                'state' => $state,
                'qr_available' => $state === 'qr_required',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'state' => 'error',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Importa histórico ~90d retroativo de um canal Baileys já conectado.
     *
     * **Wagner request 2026-05-14:** botão UI gated por feature flag — só
     * libera pra cliente enterprise pagante. Default DESABILITADO via
     * `config('whatsapp.history_import.enabled_business_ids')` (lista vazia).
     *
     * **Como ativar pra um cliente:** Wagner adiciona biz_id ao .env Hostinger:
     *   WHATSAPP_HISTORY_IMPORT_ENABLED_BIZ=1,7,42
     *
     * **Como funciona:**
     * 1. Valida channel.type=whatsapp_baileys + status=active
     * 2. Valida biz_id está na whitelist do .env (403 senão)
     * 3. Chama `whatsapp:import-history --channel=N --since=90d` artisan
     *    (US-WA-080 já existente — usa fetchMessageHistory PDO Baileys)
     * 4. Dispatch Job background pra não travar UI
     * 5. Retorna 202 + estimated_minutes pro frontend mostrar progress
     *
     * **NOTA:** este endpoint NÃO faz pareamento novo (re-pair gera novo QR).
     * Usa instance JÁ CONECTADA pra puxar histórico on-demand. Wagner pode
     * rodar quantas vezes quiser (idempotente via provider_message_id UNIQUE).
     *
     * @see Modules/Whatsapp/Console/Commands/ImportHistoryCommand.php
     */
    public function importHistory(int $id): JsonResponse
    {
        $businessId = (int) session('user.business_id');
        $channel = Channel::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        // Gate 1: só baileys (Z-API/Meta Cloud não suportam fetchMessageHistory)
        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS) {
            return response()->json([
                'ok' => false,
                'error' => 'Importação de histórico só disponível pra canais Baileys.',
            ], 422);
        }

        // Gate 2: feature flag por business_id (Wagner libera manual no .env)
        $enabledBizIds = (array) config('whatsapp.history_import.enabled_business_ids', []);
        if (! in_array($businessId, $enabledBizIds, true)) {
            return response()->json([
                'ok' => false,
                'error' => 'Importação de histórico não está habilitada pra este negócio. '
                    . 'Funcionalidade Enterprise — entre em contato com o suporte oimpresso.',
                'gated' => true,
            ], 403);
        }

        // Gate 3: channel precisa estar conectado (fetchMessageHistory exige socket vivo)
        if ($channel->status !== 'active' || $channel->channel_health !== 'healthy') {
            return response()->json([
                'ok' => false,
                'error' => 'Canal precisa estar conectado e saudável pra importar histórico. '
                    . "Status atual: {$channel->status} / health: {$channel->channel_health}.",
            ], 422);
        }

        // Dispatch comando artisan em background — Wagner já tem worker queue
        // rodando via cron (Kernel.php) que pega Jobs queue=whatsapp-history.
        // Aqui chamamos o command direto via Artisan facade (synchronous mas
        // ele próprio dispatcha Jobs internos pra cada batch — não trava o
        // request HTTP por minutos).
        \Illuminate\Support\Facades\Artisan::queue('whatsapp:import-history', [
            '--channel' => $channel->id,
            '--since' => '90d',
            '--max' => 2000,
            '--sleep' => 1500,
        ]);

        \Illuminate\Support\Facades\Log::info('[channel.import-history]', [
            'channel_id' => $channel->id,
            'business_id' => $businessId,
            'requested_by' => session('user.id'),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Importação iniciada em background. Mensagens vão aparecer no Inbox progressivamente nos próximos ~10min.',
            'estimated_minutes' => 10,
        ], 202);
    }

    /**
     * Converte Channel pra payload UI — esconde tokens dentro de config_json.
     * Só metadados + flags `has_*` por driver chegam ao frontend.
     */
    protected function toUiArray(Channel $channel): array
    {
        $cfg = $channel->config_json ?? [];

        return [
            'id' => $channel->id,
            'channel_uuid' => $channel->channel_uuid,
            'label' => $channel->label,
            'type' => $channel->type,
            'status' => $channel->status,
            'display_identifier' => $channel->display_identifier,
            'channel_health' => $channel->channel_health,
            'last_health_check_at' => optional($channel->last_health_check_at)->toIso8601String(),
            'last_health_message' => $channel->last_health_message,
            'handles_repair_status' => (bool) $channel->handles_repair_status,
            'handles_billing' => (bool) $channel->handles_billing,
            'handles_jana_bot' => (bool) $channel->handles_jana_bot,
            'handles_outbound_default' => (bool) $channel->handles_outbound_default,
            'bot_enabled' => (bool) $channel->bot_enabled,
            'lgpd_acknowledged_at' => optional($channel->lgpd_acknowledged_at)->toIso8601String(),
            // Boolean flags per-driver — UI sabe que tem creds sem ver tokens
            'has_zapi_credentials' => ! empty($cfg['zapi_instance_id']) && ! empty($cfg['zapi_instance_token']),
            'has_meta_credentials' => ! empty($cfg['meta_phone_number_id']) && ! empty($cfg['meta_access_token']),
            'has_baileys_credentials' => ! empty($cfg['baileys_phone_e164']),
            // ADR 0204 — whatsmeow flag: tem creds quando user_token foi gerado pelo provision
            'has_whatsmeow_credentials' => ! empty($cfg['whatsmeow_phone_e164']) && ! empty($cfg['whatsmeow_user_token']),
            'baileys_phone_e164' => $cfg['baileys_phone_e164'] ?? null, // não-secreto
            'whatsmeow_phone_e164' => $cfg['whatsmeow_phone_e164'] ?? null, // não-secreto
            'zapi_instance_id' => $cfg['zapi_instance_id'] ?? null,     // não-secreto
            'meta_phone_number_id' => $cfg['meta_phone_number_id'] ?? null, // não-secreto
            // Wagner request 2026-05-14: botão "Importar Histórico" gated por
            // feature flag por business_id. Frontend checa pra renderizar
            // botão habilitado/desabilitado. Backend valida de novo no endpoint.
            'history_import_enabled' => $channel->type === Channel::TYPE_WHATSAPP_BAILEYS
                && in_array($channel->business_id, (array) config('whatsapp.history_import.enabled_business_ids', []), true),
            'created_at' => optional($channel->created_at)->toIso8601String(),
        ];
    }

    /**
     * Tipos selecionáveis na UI — Fase 1-3 marcados como `disabled` pra
     * documentar visualmente o roadmap (ADR 0135).
     */
    protected function availableTypesForUi(): array
    {
        return [
            [
                'value' => Channel::TYPE_WHATSAPP_META,
                'label' => 'WhatsApp Meta Cloud',
                'description' => 'Oficial Meta. Embedded Signup v4 — 5-15 min via OAuth Facebook. Free 1k conv/mês. Zero risco ban.',
                'enabled' => true,
            ],
            [
                'value' => Channel::TYPE_WHATSAPP_ZAPI,
                'label' => 'WhatsApp Z-API',
                'description' => 'SaaS BR opcional (legacy). 5 min scan QR. Risco ban Meta. Use só se já tem conta Z-API ativa.',
                'enabled' => true,
            ],
            [
                'value' => Channel::TYPE_WHATSAPP_BAILEYS,
                'label' => 'WhatsApp Baileys',
                'description' => 'DESCONTINUADO 2026-05-27 (ADR 0202). Substituído por WhatsApp Whatsmeow (ADR 0204).',
                'enabled' => false,
            ],
            [
                // ADR 0204 (2026-05-27) — substituto não-oficial Baileys via
                // daemon Go WuzAPI CT 100. Scan QR 30s, custo zero. Risco ban
                // Meta igual Baileys (Wagner ciente, whatsmeow issue #810).
                'value' => Channel::TYPE_WHATSAPP_WHATSMEOW,
                'label' => 'WhatsApp Whatsmeow (Go)',
                'description' => 'Scan QR 30s, custo zero. Daemon Go próprio CT 100. Risco ban Meta (não-oficial, ADR 0204).',
                'enabled' => true,
            ],
            [
                'value' => Channel::TYPE_INSTAGRAM,
                'label' => 'Instagram DM',
                'description' => 'Fase 1 — aguarda implementação driver',
                'enabled' => false,
            ],
            [
                'value' => Channel::TYPE_MESSENGER,
                'label' => 'Facebook Messenger',
                'description' => 'Fase 1 — aguarda implementação driver',
                'enabled' => false,
            ],
            [
                'value' => Channel::TYPE_EMAIL_IMAP,
                'label' => 'Email (IMAP)',
                'description' => 'Fase 2 — aguarda implementação driver',
                'enabled' => false,
            ],
            [
                'value' => Channel::TYPE_MERCADOLIVRE,
                'label' => 'Mercado Livre',
                'description' => 'Fase 3 — gate cliente pagante',
                'enabled' => false,
            ],
        ];
    }
}
