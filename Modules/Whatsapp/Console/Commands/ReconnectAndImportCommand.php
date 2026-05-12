<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\Message;

/**
 * Camada 4 — combo manual reconnect + import-history pra 1 canal.
 *
 * Use case principal: Wagner clica "Reconectar + Sincronizar" no UI (futuro)
 * → roda este comando. Sequencia:
 *   1. Status atual do daemon (skip step se já connected)
 *   2. POST /connect + poll até state=connected OR --wait timeout
 *   3. Resolve --since=auto = última msg do channel +1h (buffer overlap)
 *   4. Invoca whatsapp:import-history internamente
 *
 * Diferenças vs Camada 2 (health-probe-channels):
 *   - Per-channel (não loop), 1 ação manual
 *   - Aguarda state=connected antes de prosseguir (poll com --wait timeout)
 *   - Encadeia import-history (Camada 2 só restaura conexão)
 *   - SEM retries com backoff — falha rápido se reconnect não rolar
 *
 * Multi-tenant Tier 0 (ADR 0093):
 * - Channel resolvido por --channel (FK), business_id derivado
 * - withoutGlobalScope + filtro business_id explícito
 *
 * Uso:
 *   php artisan whatsapp:reconnect-and-import --channel=3
 *   php artisan whatsapp:reconnect-and-import --channel=3 --since=90d
 *   php artisan whatsapp:reconnect-and-import --channel=3 --since=2026-04-01 --max=500
 *   php artisan whatsapp:reconnect-and-import --channel=3 --wait=120 --dry-run
 *
 * Edge cases:
 *   - Timeout reconnect (state nunca chega connected) → erro + abort sem import
 *   - Daemon URL não configurado → erro fatal
 *   - --since=auto + canal sem msgs no DB → fallback 90d
 *   - Channel banned → abort (escalation manual)
 *
 * @see Modules/Whatsapp/Console/Commands/ImportHistoryCommand.php (PR #683)
 * @see Modules/Whatsapp/Console/Commands/HealthProbeChannelsCommand.php (Camada 2)
 * @see memory/requisitos/Whatsapp/SPEC.md (self-healing channels — Camada 4)
 */
class ReconnectAndImportCommand extends Command
{
    protected $signature = 'whatsapp:reconnect-and-import
                            {--channel= : Channel ID (required)}
                            {--since=auto : auto (última msg DB +1h) | 90d | YYYY-MM-DD}
                            {--max=2000 : Cap mensagens importadas}
                            {--wait=60 : Timeout segundos pra aguardar state=connected}
                            {--dry-run : Preview sem dispatch import nem persist}';

    protected $description = 'Reconecta canal Baileys + importa histórico em 1 comando (Camada 4).';

    /** Intervalo do poll de status durante reconnect (segundos). */
    protected const POLL_INTERVAL_SECONDS = 2;

    public function handle(): int
    {
        $channelId = (int) $this->option('channel');
        if ($channelId <= 0) {
            $this->error('--channel=<ID> obrigatório.');
            return self::FAILURE;
        }

        $sinceOption = (string) $this->option('since');
        $max = (int) $this->option('max');
        $waitSeconds = max(5, (int) $this->option('wait'));
        $dryRun = (bool) $this->option('dry-run');

        $daemonUrl = (string) config('whatsapp.baileys.daemon_url', '');
        $apiKey = (string) config('whatsapp.baileys.api_key', '');

        if ($daemonUrl === '' || $apiKey === '') {
            $this->error('WHATSAPP_BAILEYS_DAEMON_URL/_API_KEY ausentes no .env.');
            return self::FAILURE;
        }

        // SUPERADMIN: comando manual cross-business — bypass scope (ADR 0093)
        $channel = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->find($channelId);

        if (! $channel) {
            $this->error("Channel #{$channelId} não encontrado.");
            return self::FAILURE;
        }

        if ($channel->type !== Channel::TYPE_WHATSAPP_BAILEYS) {
            $this->error("Channel #{$channelId} tipo '{$channel->type}' — só Baileys suporta reconnect-and-import.");
            return self::FAILURE;
        }

        if (empty($channel->channel_uuid)) {
            $this->error("Channel #{$channelId} sem channel_uuid (corrupção?). Skip.");
            return self::FAILURE;
        }

        $instanceId = $this->resolveInstanceId($channel);
        $businessId = (int) $channel->business_id;

        $this->info('Reconnect + Import histórico');
        $this->line("  channel  : #{$channel->id} '{$channel->label}' (biz={$businessId})");
        $this->line("  instance : {$instanceId}");
        $this->line("  since    : {$sinceOption}");
        $this->line("  max      : {$max} msgs");
        $this->line("  wait     : {$waitSeconds}s");
        $this->line('  dry-run  : ' . ($dryRun ? 'sim' : 'não'));
        $this->newLine();

        // ============================================================
        // STEP A — Reconnect
        // ============================================================
        $this->info('Step A — Reconnect');
        $reconnectStart = microtime(true);

        $currentState = $this->callStatus($daemonUrl, $apiKey, $instanceId);
        $this->line("  estado inicial: {$currentState}");

        if ($currentState === 'banned') {
            $this->error("  state=banned — escalation manual (recriar chip). Abort.");
            Log::warning('[whatsapp.reconnect_and_import.banned]', [
                'channel_id' => $channel->id,
                'business_id' => $businessId,
                'instance_id' => $instanceId,
            ]);
            return self::FAILURE;
        }

        if ($currentState !== 'connected') {
            if ($dryRun) {
                $this->line('  [dry-run] pularia reconnect real, simulando connected.');
            } else {
                $this->line("  disparando POST /connect...");
                $this->callConnect($daemonUrl, $apiKey, $instanceId, $channel);

                $deadline = microtime(true) + $waitSeconds;
                $polled = 0;

                while (microtime(true) < $deadline) {
                    sleep(self::POLL_INTERVAL_SECONDS);
                    $polled++;
                    $currentState = $this->callStatus($daemonUrl, $apiKey, $instanceId);

                    if ($currentState === 'connected') {
                        break;
                    }

                    if ($currentState === 'banned') {
                        $this->error("  state=banned mid-reconnect — abort.");
                        return self::FAILURE;
                    }

                    if ($polled % 5 === 0) {
                        $this->line("  poll #{$polled}: state={$currentState}");
                    }
                }

                if ($currentState !== 'connected') {
                    $reconnectMs = (int) ((microtime(true) - $reconnectStart) * 1000);
                    $this->error("  timeout {$waitSeconds}s — state final='{$currentState}'. Abort sem import.");
                    Log::error('[whatsapp.reconnect_and_import.timeout]', [
                        'channel_id' => $channel->id,
                        'business_id' => $businessId,
                        'instance_id' => $instanceId,
                        'last_state' => $currentState,
                        'wait_seconds' => $waitSeconds,
                        'duration_ms' => $reconnectMs,
                    ]);
                    return self::FAILURE;
                }
            }
        }

        $reconnectMs = (int) ((microtime(true) - $reconnectStart) * 1000);
        $this->info("  ✓ connected ({$reconnectMs}ms)");
        $this->newLine();

        // ============================================================
        // STEP B — Resolve --since
        // ============================================================
        $this->info('Step B — Resolve --since');
        $resolvedSince = $this->resolveSince($sinceOption, $channel);
        $this->line("  since resolvido: {$resolvedSince}");
        $this->newLine();

        // ============================================================
        // STEP C — Invoca whatsapp:import-history
        // ============================================================
        $this->info('Step C — Import histórico');
        $importStart = microtime(true);

        $importArgs = [
            '--channel' => $channel->id,
            '--since' => $resolvedSince,
            '--max' => $max,
        ];

        if ($dryRun) {
            $importArgs['--dry-run'] = true;
        }

        $exitCode = $this->call('whatsapp:import-history', $importArgs);
        $importMs = (int) ((microtime(true) - $importStart) * 1000);

        $this->newLine();
        $this->info('Resumo:');
        $this->line("  reconnect : {$reconnectMs}ms");
        $this->line("  import    : {$importMs}ms");
        $this->line("  import_exit_code: {$exitCode}");

        Log::info('[whatsapp.reconnect_and_import.completed]', [
            'channel_id' => $channel->id,
            'business_id' => $businessId,
            'instance_id' => $instanceId,
            'reconnect_ms' => $reconnectMs,
            'import_ms' => $importMs,
            'import_exit_code' => $exitCode,
            'since' => $resolvedSince,
            'dry_run' => $dryRun,
        ]);

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Resolve --since: 'auto' busca última msg DB +1h (buffer overlap).
     * Strings tipo '90d' / '2026-04-01' são passadas direto pro
     * import-history (que sabe parsear).
     */
    protected function resolveSince(string $sinceOption, Channel $channel): string
    {
        $option = trim($sinceOption);

        if ($option !== 'auto') {
            return $option;
        }

        // SUPERADMIN: lookup cross-business da última msg (ADR 0093)
        $convIds = Conversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $channel->business_id)
            ->where('channel_id', $channel->id)
            ->pluck('id');

        if ($convIds->isEmpty()) {
            $this->warn('  --since=auto: canal sem conversations — fallback 90d.');
            return '90d';
        }

        $lastMsgAt = Message::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $channel->business_id)
            ->whereIn('conversation_id', $convIds)
            ->max('created_at');

        if (! $lastMsgAt) {
            $this->warn('  --since=auto: canal sem mensagens — fallback 90d.');
            return '90d';
        }

        // +1h buffer overlap pra deduplicação (provider_message_id UNIQUE handle)
        $resolved = Carbon::parse($lastMsgAt)->addHour();

        // Se janela for muito curta (<6h), expande pra 24h (proteção contra
        // último-msg-recente que deixaria janela quase vazia)
        if ($resolved->greaterThan(now()->subHours(6))) {
            $this->line('  --since=auto: última msg recente — usando 24h pra cobrir lacuna.');
            return '24h';
        }

        return $resolved->toIso8601String();
    }

    /**
     * Chama GET /instances/{id}/status. Retorna state string ou 'unknown'/'error'.
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
            Log::warning('[whatsapp.reconnect_and_import.status_exception]', [
                'instance_id' => $instanceId,
                'exception_class' => $e::class,
            ]);
            return 'error';
        }
    }

    /**
     * POST /instances/{id}/connect (idempotente Baileys).
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
                Log::warning('[whatsapp.reconnect_and_import.connect_non_2xx]', [
                    'channel_id' => $channel->id,
                    'instance_id' => $instanceId,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[whatsapp.reconnect_and_import.connect_exception]', [
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
