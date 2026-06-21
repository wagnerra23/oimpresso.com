<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Services\Drivers\WhatsmeowState;
use Modules\Whatsapp\Services\WhatsmeowReconciler;

/**
 * Camada 2 — health probe self-healing de canais WhatsApp não-oficiais.
 *
 * Dois drivers, dois comportamentos:
 *   - **Baileys** (auto-recovery): pinga daemon /instances/{id}/status e, se
 *     não `connected`, tenta `POST /connect` em 3 retries com backoff [1,5,30]s.
 *   - **Whatsmeow** (detect-only): consulta o estado canon via
 *     `WhatsmeowReconciler::reconcile()` (/admin/users + /session/status, ADR 0206)
 *     e reflete em `channel_health`. NÃO auto-reconecta — re-pareamento whatsmeow
 *     exige QR humano (invariante "reconnect tímido"; estudo channel-reliability
 *     2026-06-18, gap b: antes o whatsmeow nem era probado → queda invisível).
 *
 * Roda diariamente 03:30 (schedule em app/Console/Kernel.php). Itera Channels
 * status='active' de cada driver.
 *
 * Multi-tenant Tier 0 (ADR 0093):
 * - Job cross-business — `withoutGlobalScope(ScopeByBusiness)` com SUPERADMIN
 * - Logs estruturados com `business_id` + `channel_id` apenas (sem PII)
 *
 * Uso:
 *   php artisan whatsapp:health-probe-channels
 *   php artisan whatsapp:health-probe-channels --business=4
 *   php artisan whatsapp:health-probe-channels --only-disconnected --dry-run
 *
 * Estados retornados pelo daemon:
 *   - connected            → healthy (OK)
 *   - qr_required          → tenta connect (mas vai retornar qr_required —
 *                            scan manual obrigatório; ainda assim relogamos
 *                            failure pra incrementar consecutive_failures)
 *   - disconnected         → tenta connect (idempotente Baileys)
 *   - banned               → NÃO tenta connect (chip pegou ban Meta — escalation
 *                            manual); apenas marca channel_health='banned'
 *   - instance_not_found   → tenta connect (daemon CT 100 perdeu state após
 *                            restart container; recriar instance idempotente)
 *
 * Edge cases:
 *   - WHATSAPP_BAILEYS_DAEMON_URL não configurado → skip global + warn
 *   - Channel sem channel_uuid → skip + log error
 *   - HTTP error (timeout/5xx/cert inválido) → trata como state=unknown,
 *     tenta connect (fail-safe)
 *
 * @see app/Console/Kernel.php  (schedule daily 03:30)
 * @see Modules/Whatsapp/Http/Controllers/Admin/ChannelsController.php (connect/status canonical)
 * @see Modules/Whatsapp/Console/Commands/ReconnectAndImportCommand.php (Camada 4 — combo manual)
 * @see memory/requisitos/Whatsapp/SPEC.md (self-healing channels — Camada 2)
 */
class HealthProbeChannelsCommand extends Command
{
    protected $signature = 'whatsapp:health-probe-channels
                            {--business=all : Business ID ou "all" (default)}
                            {--only-disconnected : Só processa canais já marcados disconnected/banned}
                            {--dry-run : Preview sem chamar daemon nem atualizar DB}';

    protected $description = 'Probe saúde de canais WhatsApp: Baileys (auto-recovery) + Whatsmeow (detect-only) — Camada 2 self-healing.';

    /** Backoff exponencial entre tentativas de connect (segundos). */
    protected const RETRY_BACKOFF_SECONDS = [1, 5, 30];

    /** Estados que disparam tentativa de recovery via /connect. */
    protected const STATES_NEED_RECOVERY = [
        'qr_required',
        'disconnected',
        'instance_not_found',
        'unknown',
        'error',
    ];

    public function handle(): int
    {
        $baileysUrl = (string) config('whatsapp.baileys.daemon_url', '');
        $baileysKey = (string) config('whatsapp.baileys.api_key', '');
        $whatsmeowUrl = (string) config('whatsapp.whatsmeow.daemon_url', '');
        $whatsmeowKey = (string) config('whatsapp.whatsmeow.api_key', '');

        $baileysReady = $baileysUrl !== '' && $baileysKey !== '';
        $whatsmeowReady = $whatsmeowUrl !== '' && $whatsmeowKey !== '';

        if (! $baileysReady && ! $whatsmeowReady) {
            $this->warn('Nenhum daemon configurado (baileys/whatsmeow _DAEMON_URL/_API_KEY ausentes) — skip global.');
            Log::warning('[whatsapp.health_probe.skipped_no_daemon_config]');
            return self::SUCCESS;
        }

        $businessOption = (string) $this->option('business');
        $onlyDisconnected = (bool) $this->option('only-disconnected');
        $dryRun = (bool) $this->option('dry-run');

        if ($businessOption !== 'all' && (int) $businessOption <= 0) {
            $this->error("--business='{$businessOption}' inválido. Use 'all' ou ID numérico.");
            return self::FAILURE;
        }

        $rows = [];
        $stats = [
            'healthy' => 0,
            'recovered' => 0,
            'disconnected' => 0,
            'banned' => 0,
            'skipped' => 0,
        ];

        // ── Pass 1 — Baileys: probe /status + auto-recovery via /connect ──
        if ($baileysReady) {
            foreach ($this->channelsToProbe(Channel::TYPE_WHATSAPP_BAILEYS, $businessOption, $onlyDisconnected) as $channel) {
                $result = $this->probeChannel($channel, $baileysUrl, $baileysKey, $dryRun);
                $rows[] = $this->toRow($channel, $result);
                $stats[$result['outcome']] = ($stats[$result['outcome']] ?? 0) + 1;
            }
        }

        // ── Pass 2 — Whatsmeow: probe via Reconciler (/session/status), detect-only ──
        if ($whatsmeowReady) {
            $reconciler = app(WhatsmeowReconciler::class);
            foreach ($this->channelsToProbe(Channel::TYPE_WHATSAPP_WHATSMEOW, $businessOption, $onlyDisconnected) as $channel) {
                $result = $this->probeWhatsmeowChannel($channel, $reconciler, $dryRun);
                $rows[] = $this->toRow($channel, $result);
                $stats[$result['outcome']] = ($stats[$result['outcome']] ?? 0) + 1;
            }
        }

        if ($rows === []) {
            $this->info('Nenhum canal WhatsApp ativo pra probe.');
            return self::SUCCESS;
        }

        $this->info(count($rows) . ' canal(is) probado(s) (dry-run=' . ($dryRun ? 'sim' : 'não') . ')');
        $this->newLine();

        $this->table(
            ['ch_id', 'biz', 'label', 'before', 'after', 'attempts', 'duration_ms'],
            $rows,
        );

        $this->newLine();
        $this->info('Resumo:');
        foreach ($stats as $k => $v) {
            $this->line("  {$k} : {$v}");
        }

        Log::info('[whatsapp.health_probe.completed]', [
            'total' => count($rows),
            'dry_run' => $dryRun,
            'business_filter' => $businessOption,
            'only_disconnected' => $onlyDisconnected,
        ] + $stats);

        return self::SUCCESS;
    }

    /**
     * Query canônica de canais a probar de um driver. SUPERADMIN cross-business —
     * bypass de scope explícito (ADR 0093); o filtro --business mantém Tier 0.
     *
     * @return \Illuminate\Support\Collection<int, Channel>
     */
    protected function channelsToProbe(string $type, string $businessOption, bool $onlyDisconnected): \Illuminate\Support\Collection
    {
        $query = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('type', $type)
            ->where('status', 'active');

        if ($businessOption !== 'all') {
            $query->where('business_id', (int) $businessOption);
        }

        if ($onlyDisconnected) {
            $query->whereIn('channel_health', ['disconnected', 'banned', 'never_checked']);
        }

        return $query->orderBy('business_id')->orderBy('id')->get();
    }

    /**
     * Monta uma linha da tabela de saída a partir do resultado de um probe.
     *
     * @param  array{before_health:string, after_health:string, attempts:int, duration_ms:int, outcome:string}  $result
     * @return array<string, mixed>
     */
    protected function toRow(Channel $channel, array $result): array
    {
        return [
            'ch_id' => $channel->id,
            'biz' => $channel->business_id,
            'label' => mb_strimwidth((string) $channel->label, 0, 22, '...'),
            'before' => $result['before_health'],
            'after' => $result['after_health'],
            'attempts' => $result['attempts'],
            'duration_ms' => $result['duration_ms'],
        ];
    }

    /**
     * Probe + recovery de 1 canal. Atualiza Channel DB no fim (a menos que --dry-run).
     *
     * @return array{
     *   before_health: string,
     *   after_health: string,
     *   attempts: int,
     *   duration_ms: int,
     *   outcome: string,
     * }
     */
    protected function probeChannel(
        Channel $channel,
        string $daemonUrl,
        string $apiKey,
        bool $dryRun,
    ): array {
        $start = microtime(true);
        $beforeHealth = (string) $channel->channel_health;

        // Defesa — channel corrupto sem UUID não deveria existir
        if (empty($channel->channel_uuid)) {
            Log::error('[whatsapp.health_probe.skipped_no_uuid]', [
                'channel_id' => $channel->id,
                'business_id' => $channel->business_id,
            ]);
            return [
                'before_health' => $beforeHealth,
                'after_health' => $beforeHealth,
                'attempts' => 0,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'outcome' => 'skipped',
            ];
        }

        $instanceId = $this->resolveInstanceId($channel);

        // 1. Status inicial
        $state = $this->callStatus($daemonUrl, $apiKey, $instanceId);

        if ($state === 'connected') {
            if (! $dryRun) {
                $channel->channel_health = 'healthy';
                $channel->channel_health_consecutive_failures = 0;
                $channel->last_health_check_at = now();
                $channel->last_health_message = 'state=connected';
                $channel->save();
            }
            return [
                'before_health' => $beforeHealth,
                'after_health' => 'healthy',
                'attempts' => 0,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'outcome' => 'healthy',
            ];
        }

        // 2. Estado banned → NÃO tenta connect (escalation manual)
        if ($state === 'banned') {
            if (! $dryRun) {
                $channel->channel_health = 'banned';
                $channel->channel_health_consecutive_failures++;
                $channel->last_health_check_at = now();
                $channel->last_health_message = 'state=banned (chip pegou ban Meta — recriar manualmente)';
                $channel->save();
            }
            Log::warning('[whatsapp.health_probe.banned]', [
                'channel_id' => $channel->id,
                'business_id' => $channel->business_id,
                'instance_id' => $instanceId,
            ]);
            return [
                'before_health' => $beforeHealth,
                'after_health' => 'banned',
                'attempts' => 0,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'outcome' => 'banned',
            ];
        }

        // 3. Estado precisa recovery? → 3 retries connect com backoff exponencial
        if (! in_array($state, self::STATES_NEED_RECOVERY, true)) {
            // Estado desconhecido (state=connecting?) — sem ação, próximo probe
            if (! $dryRun) {
                $channel->last_health_check_at = now();
                $channel->last_health_message = "state={$state} (sem ação — aguardando próximo probe)";
                $channel->save();
            }
            return [
                'before_health' => $beforeHealth,
                'after_health' => $beforeHealth,
                'attempts' => 0,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'outcome' => 'skipped',
            ];
        }

        if ($dryRun) {
            return [
                'before_health' => $beforeHealth,
                'after_health' => "[dry-run would recover from {$state}]",
                'attempts' => 0,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'outcome' => 'skipped',
            ];
        }

        // 4. Loop de recovery com backoff exponencial
        $attempts = 0;
        $recovered = false;
        $lastState = $state;

        foreach (self::RETRY_BACKOFF_SECONDS as $i => $backoff) {
            $attempts++;
            sleep($backoff);

            $this->callConnect($daemonUrl, $apiKey, $instanceId, $channel);
            $lastState = $this->callStatus($daemonUrl, $apiKey, $instanceId);

            if ($lastState === 'connected') {
                $recovered = true;
                break;
            }

            if ($lastState === 'banned') {
                // Daemon detectou ban no meio — abortar retries
                $channel->channel_health = 'banned';
                $channel->channel_health_consecutive_failures++;
                $channel->last_health_check_at = now();
                $channel->last_health_message = 'state=banned mid-recovery';
                $channel->save();
                Log::warning('[whatsapp.health_probe.banned_during_recovery]', [
                    'channel_id' => $channel->id,
                    'business_id' => $channel->business_id,
                    'instance_id' => $instanceId,
                    'attempts' => $attempts,
                ]);
                return [
                    'before_health' => $beforeHealth,
                    'after_health' => 'banned',
                    'attempts' => $attempts,
                    'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                    'outcome' => 'banned',
                ];
            }
        }

        // 5. Persistir resultado
        if ($recovered) {
            $channel->channel_health = 'healthy';
            $channel->channel_health_consecutive_failures = 0;
            $channel->last_health_check_at = now();
            $channel->last_health_message = "recovered after {$attempts} attempt(s)";
            $channel->save();

            Log::info('[whatsapp.health_probe.recovered]', [
                'channel_id' => $channel->id,
                'business_id' => $channel->business_id,
                'instance_id' => $instanceId,
                'attempts' => $attempts,
                'from_state' => $state,
            ]);

            return [
                'before_health' => $beforeHealth,
                'after_health' => 'healthy',
                'attempts' => $attempts,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'outcome' => 'recovered',
            ];
        }

        $channel->channel_health = 'disconnected';
        $channel->channel_health_consecutive_failures++;
        $channel->last_health_check_at = now();
        $channel->last_health_message = "recovery failed após {$attempts} attempts (last_state={$lastState})";
        $channel->save();

        Log::error('[whatsapp.health_probe.recovery_failed]', [
            'channel_id' => $channel->id,
            'business_id' => $channel->business_id,
            'instance_id' => $instanceId,
            'attempts' => $attempts,
            'initial_state' => $state,
            'last_state' => $lastState,
            'consecutive_failures' => $channel->channel_health_consecutive_failures,
        ]);

        return [
            'before_health' => $beforeHealth,
            'after_health' => 'disconnected',
            'attempts' => $attempts,
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            'outcome' => 'disconnected',
        ];
    }

    /**
     * Probe de 1 canal whatsmeow via WhatsmeowReconciler (detect-only).
     *
     * Diferente do Baileys: NÃO tenta auto-recovery — re-pareamento whatsmeow
     * exige QR humano (invariante "reconnect tímido"). Só lê o estado canon
     * (Reconciler ADR 0206 → /admin/users + /session/status) e reflete em
     * channel_health. Mapeamento:
     *   - PAIRED                                        → healthy (reset failures)
     *   - BANNED                                        → banned (failures++)
     *   - NOT_EXISTS/PROVISION_PENDING/QR_PENDING/LOGGED_OUT → disconnected (failures++)
     *   - DAEMON_UNREACHABLE/ERROR                      → inconclusivo: saúde inalterada
     *     (a falha é do daemon/config, não do canal — penalizar geraria falso-alarme)
     *
     * Corrige o gap b do estudo channel-reliability 2026-06-18: antes o probe
     * só cobria Baileys, então uma queda real do whatsmeow (connected=False)
     * nunca virava channel_health=disconnected — ficava invisível na UI.
     *
     * @return array{before_health:string, after_health:string, attempts:int, duration_ms:int, outcome:string}
     */
    protected function probeWhatsmeowChannel(
        Channel $channel,
        WhatsmeowReconciler $reconciler,
        bool $dryRun,
    ): array {
        $start = microtime(true);
        $beforeHealth = (string) $channel->channel_health;
        $elapsed = static fn (): int => (int) ((microtime(true) - $start) * 1000);

        $state = $reconciler->reconcile($channel); // read-only — não muta nada

        // PAIRED → saudável
        if ($state === WhatsmeowState::PAIRED) {
            if (! $dryRun) {
                $channel->channel_health = 'healthy';
                $channel->channel_health_consecutive_failures = 0;
                $channel->last_health_check_at = now();
                $channel->last_health_message = 'whatsmeow state=paired';
                $channel->save();
            }
            return [
                'before_health' => $beforeHealth,
                'after_health' => 'healthy',
                'attempts' => 0,
                'duration_ms' => $elapsed(),
                'outcome' => 'healthy',
            ];
        }

        // BANNED → banido (sem recovery — escalation manual, igual Baileys)
        if ($state === WhatsmeowState::BANNED) {
            if (! $dryRun) {
                $channel->channel_health = 'banned';
                $channel->channel_health_consecutive_failures++;
                $channel->last_health_check_at = now();
                $channel->last_health_message = 'whatsmeow state=banned (recriar manualmente / Meta Cloud)';
                $channel->save();
            }
            Log::warning('[whatsapp.health_probe.whatsmeow_banned]', [
                'channel_id' => $channel->id,
                'business_id' => $channel->business_id,
            ]);
            return [
                'before_health' => $beforeHealth,
                'after_health' => 'banned',
                'attempts' => 0,
                'duration_ms' => $elapsed(),
                'outcome' => 'banned',
            ];
        }

        // DAEMON_UNREACHABLE / ERROR → inconclusivo: NÃO mexe na saúde do canal
        // (a falha é do daemon/config, não do canal — penalizar = falso-alarme).
        if ($state === WhatsmeowState::DAEMON_UNREACHABLE || $state === WhatsmeowState::ERROR) {
            if (! $dryRun) {
                $channel->last_health_check_at = now();
                $channel->last_health_message = "whatsmeow probe inconclusivo (state={$state->value}) — saúde inalterada";
                $channel->save();
            }
            Log::warning('[whatsapp.health_probe.whatsmeow_inconclusive]', [
                'channel_id' => $channel->id,
                'business_id' => $channel->business_id,
                'state' => $state->value,
            ]);
            return [
                'before_health' => $beforeHealth,
                'after_health' => $beforeHealth,
                'attempts' => 0,
                'duration_ms' => $elapsed(),
                'outcome' => 'skipped',
            ];
        }

        // NOT_EXISTS / PROVISION_PENDING / QR_PENDING / LOGGED_OUT → desconectado.
        // Detect-only: re-pareamento exige novo QR (humano).
        if ($dryRun) {
            return [
                'before_health' => $beforeHealth,
                'after_health' => "[dry-run marcaria disconnected (state={$state->value})]",
                'attempts' => 0,
                'duration_ms' => $elapsed(),
                'outcome' => 'skipped',
            ];
        }

        $channel->channel_health = 'disconnected';
        $channel->channel_health_consecutive_failures++;
        $channel->last_health_check_at = now();
        $channel->last_health_message = "whatsmeow desconectado (state={$state->value}) — re-pareamento exige novo QR";
        $channel->save();

        Log::warning('[whatsapp.health_probe.whatsmeow_disconnected]', [
            'channel_id' => $channel->id,
            'business_id' => $channel->business_id,
            'state' => $state->value,
            'consecutive_failures' => $channel->channel_health_consecutive_failures,
        ]);

        return [
            'before_health' => $beforeHealth,
            'after_health' => 'disconnected',
            'attempts' => 0,
            'duration_ms' => $elapsed(),
            'outcome' => 'disconnected',
        ];
    }

    /**
     * Chama GET /instances/{id}/status no daemon. Retorna state string —
     * se erro HTTP ou exception, retorna 'unknown' (fail-safe — trata como
     * potencial recovery target).
     */
    protected function callStatus(string $daemonUrl, string $apiKey, string $instanceId): string
    {
        try {
            $response = $this->buildHttp($apiKey)
                ->get(rtrim($daemonUrl, '/') . "/instances/{$instanceId}/status");

            if ($response->status() === 404) {
                return 'instance_not_found';
            }

            if (! $response->successful()) {
                return 'unknown';
            }

            $json = $response->json();
            if (! is_array($json)) {
                return 'unknown';
            }

            return (string) ($json['state'] ?? 'unknown');
        } catch (\Throwable $e) {
            Log::warning('[whatsapp.health_probe.status_exception]', [
                'instance_id' => $instanceId,
                'exception_class' => $e::class,
            ]);
            return 'error';
        }
    }

    /**
     * Chama POST /instances/{id}/connect. Idempotente (Baileys retorna 200
     * mesmo se já connected). Não retorna o resultado — caller faz follow-up
     * com /status pra confirmar.
     */
    protected function callConnect(
        string $daemonUrl,
        string $apiKey,
        string $instanceId,
        Channel $channel,
    ): void {
        try {
            $response = $this->buildHttp($apiKey)
                ->post(rtrim($daemonUrl, '/') . "/instances/{$instanceId}/connect", [
                    'business_uuid' => $channel->channel_uuid,
                    'business_id' => $channel->business_id,
                ]);

            if (! $response->successful()) {
                Log::warning('[whatsapp.health_probe.connect_non_2xx]', [
                    'channel_id' => $channel->id,
                    'instance_id' => $instanceId,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[whatsapp.health_probe.connect_exception]', [
                'channel_id' => $channel->id,
                'instance_id' => $instanceId,
                'exception_class' => $e::class,
            ]);
        }
    }

    /**
     * Builder HTTP padrão — bearer token + sem verify TLS (FIXME US-WA-058
     * cert LE pendente no CT 100) + timeout config.
     */
    protected function buildHttp(string $apiKey): PendingRequest
    {
        $timeout = (int) config('whatsapp.baileys.request_timeout', 15);

        return Http::withToken($apiKey)
            ->withoutVerifying() // FIXME(US-WA-058): cert LE pendente CT 100
            ->timeout($timeout)
            ->acceptJson();
    }

    /**
     * Instance ID derivado de Channel — espelha ChannelsController::connect()
     * (linha 358): `'ch-' . str_replace('-', '', $channel->channel_uuid)`.
     */
    protected function resolveInstanceId(Channel $channel): string
    {
        return 'ch-' . str_replace('-', '', (string) $channel->channel_uuid);
    }
}
