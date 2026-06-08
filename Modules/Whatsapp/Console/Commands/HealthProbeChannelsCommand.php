<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;

/**
 * Camada 2 — health probe + auto-recovery de canais Baileys (self-healing).
 *
 * Roda diariamente 03:30 (schedule em app/Console/Kernel.php). Itera Channels
 * com type=whatsapp_baileys e status='active', pinga daemon CT 100 /status,
 * e se algum não estiver `connected` tenta `POST /connect` em 3 retries com
 * backoff exponencial (1s/5s/30s). Cobre falhas do bootstrap auto-reconnect
 * da Camada 1 daemon-side.
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

    protected $description = 'Probe Baileys daemon + auto-recover canais com falha (Camada 2 self-healing).';

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
        $daemonUrl = (string) config('whatsapp.baileys.daemon_url', '');
        $apiKey = (string) config('whatsapp.baileys.api_key', '');

        if ($daemonUrl === '' || $apiKey === '') {
            $this->warn('WHATSAPP_BAILEYS_DAEMON_URL/_API_KEY ausentes no .env — skip global.');
            Log::warning('[whatsapp.health_probe.skipped_no_daemon_config]');
            return self::SUCCESS;
        }

        $businessOption = (string) $this->option('business');
        $onlyDisconnected = (bool) $this->option('only-disconnected');
        $dryRun = (bool) $this->option('dry-run');

        // SUPERADMIN: probe cross-business — bypass scope explícito (ADR 0093)
        $query = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('type', Channel::TYPE_WHATSAPP_BAILEYS)
            ->where('status', 'active');

        if ($businessOption !== 'all') {
            $bizId = (int) $businessOption;
            if ($bizId <= 0) {
                $this->error("--business='{$businessOption}' inválido. Use 'all' ou ID numérico.");
                return self::FAILURE;
            }
            $query->where('business_id', $bizId);
        }

        if ($onlyDisconnected) {
            $query->whereIn('channel_health', ['disconnected', 'banned', 'never_checked']);
        }

        $channels = $query->orderBy('business_id')->orderBy('id')->get();
        $total = $channels->count();

        if ($total === 0) {
            $this->info('Nenhum canal Baileys ativo pra probe.');
            return self::SUCCESS;
        }

        $this->info("Probe {$total} canal(is) Baileys (dry-run=" . ($dryRun ? 'sim' : 'não') . ")");
        $this->newLine();

        $rows = [];
        $stats = [
            'healthy' => 0,
            'recovered' => 0,
            'disconnected' => 0,
            'banned' => 0,
            'skipped' => 0,
        ];

        foreach ($channels as $channel) {
            $result = $this->probeChannel($channel, $daemonUrl, $apiKey, $dryRun);

            $rows[] = [
                'ch_id' => $channel->id,
                'biz' => $channel->business_id,
                'label' => mb_strimwidth((string) $channel->label, 0, 22, '...'),
                'before' => $result['before_health'],
                'after' => $result['after_health'],
                'attempts' => $result['attempts'],
                'duration_ms' => $result['duration_ms'],
            ];

            $stats[$result['outcome']] = ($stats[$result['outcome']] ?? 0) + 1;
        }

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
            'total' => $total,
            'dry_run' => $dryRun,
            'business_filter' => $businessOption,
            'only_disconnected' => $onlyDisconnected,
        ] + $stats);

        return self::SUCCESS;
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
