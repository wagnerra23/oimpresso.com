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

        // Lista canais do business — exclui tokens via toUiArray()
        $channels = Channel::query()
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->get()
            ->map(fn (Channel $c) => $this->toUiArray($c));

        return Inertia::render('Atendimento/Channels/Index', [
            'channels' => $channels,
            'businessId' => $businessId,
            'availableTypes' => $this->availableTypesForUi(),
            'forbiddenDrivers' => config('whatsapp.forbidden_drivers', []),
        ]);
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

        // LGPD obrigatório pra Baileys
        if ($data['type'] === Channel::TYPE_WHATSAPP_BAILEYS) {
            $channel->lgpd_acknowledged_at = now();
            $channel->lgpd_acknowledged_by_user_id = $userId;
        }

        // Display identifier inferido per-type pra UI mostrar
        $channel->display_identifier = match ($data['type']) {
            Channel::TYPE_WHATSAPP_BAILEYS => $data['config']['baileys_phone_e164'] ?? null,
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

        $channel = Channel::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        $accessRows = ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('channel_id', $channel->id)
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

        $accessUi = $accessRows->map(function (ChannelUserAccess $row) use ($usersById) {
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
        })->values();

        // Users disponíveis pra grant (do mesmo business, têm permission
        // whatsapp.access OU whatsapp.send, e ainda não têm grant ativo neste canal).
        $alreadyGrantedIds = $accessRows->pluck('user_id')->all();
        $candidatesQuery = User::where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->whereNotIn('id', $alreadyGrantedIds)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'username']);

        $available = $candidatesQuery->filter(function (User $u) {
            return $u->can('whatsapp.access') || $u->can('whatsapp.send');
        })->map(function (User $u) {
            return [
                'id' => $u->id,
                'name' => trim($u->first_name . ' ' . ($u->last_name ?? '')),
                'email' => $u->email,
                'username' => $u->username,
            ];
        })->values();

        // Audit log curto — últimas 20 entradas do canal (grant + revoke).
        // Inclui rows revoked pra mostrar histórico.
        $audit = ChannelUserAccess::query()
            ->where('business_id', $businessId)
            ->where('channel_id', $channel->id)
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

        $auditUi = $audit->map(function (ChannelUserAccess $row) use ($auditUsers) {
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
        })->values();

        return Inertia::render('Atendimento/Channels/Show', [
            'channel' => $this->toUiArray($channel),
            'users' => $accessUi,
            'availableUsers' => $available,
            'audit' => $auditUi,
        ]);
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

        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS) {
            return response()->json([
                'ok' => false,
                'error' => "Connect rápido só implementado pra Baileys nesta fase. Tipo atual: {$channel->type}",
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
     * Lê status atual da instance no daemon (poll do frontend pra detectar
     * connected após scan QR).
     */
    public function status(int $id): JsonResponse
    {
        $businessId = (int) session('user.business_id');
        $channel = Channel::query()
            ->where('business_id', $businessId)
            ->findOrFail($id);

        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS) {
            return response()->json(['state' => null, 'reason' => 'only_baileys']);
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
            'baileys_phone_e164' => $cfg['baileys_phone_e164'] ?? null, // não-secreto
            'zapi_instance_id' => $cfg['zapi_instance_id'] ?? null,     // não-secreto
            'meta_phone_number_id' => $cfg['meta_phone_number_id'] ?? null, // não-secreto
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
                'description' => 'Oficial Meta. Aprovação 1-3 dias. Free 1k conv/mês.',
                'enabled' => true,
            ],
            [
                'value' => Channel::TYPE_WHATSAPP_ZAPI,
                'label' => 'WhatsApp Z-API',
                'description' => 'SaaS BR. 5 min scan QR. Risco ban Meta.',
                'enabled' => true,
            ],
            [
                'value' => Channel::TYPE_WHATSAPP_BAILEYS,
                'label' => 'WhatsApp Baileys',
                'description' => 'Daemon Node próprio CT 100. Custo zero. Risco ban Meta.',
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
