<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\DeleteBaileysInstanceJob;

/**
 * Reconciler — sincroniza `channels` table com estado real do daemon Baileys.
 *
 * **Por que existe (Wagner 2026-05-13):** "como resolve isso vai sempre você?
 * automatize". Wagner não quer ter que pedir Claude toda vez que canal Baileys
 * trava. Este comando detecta drift e auto-corrige sem intervenção humana.
 *
 * **Fluxo (5 min em cron):**
 *
 * 1. Lista todos channels Baileys com `status` != 'setup' E type=whatsapp_baileys
 * 2. Pra cada channel, query GET /instances/{id}/status no daemon CT 100
 * 3. Detecta drift entre DB.status e daemon.state:
 *
 *    | DB.status     | daemon.state            | Ação                                          |
 *    |---------------|-------------------------|-----------------------------------------------|
 *    | active        | connected               | OK — atualiza last_health_check_at            |
 *    | active        | banned / disconnected   | marca DB.status=disconnected + dispatch purge |
 *    | active        | 404 (não existe)        | marca DB.status=setup (precisa re-parear)     |
 *    | active        | connected mas last_seen | log warning (zombie — PR #817 cuida via 503)  |
 *    |               | > 30min estagnado       |                                               |
 *    | disconnected  | connected               | bug rev: marca DB.status=active               |
 *    | banned        | qualquer                | sem ação (já marcado, aguarda re-parear)      |
 *
 * 4. Sumário: imprime tabela de N rows reconciled / fixes applied / warnings
 *
 * **Uso:**
 *   php artisan whatsapp:channels-reconcile           # default --batch=20 --sleep=500
 *   php artisan whatsapp:channels-reconcile --dry-run # preview
 *   php artisan whatsapp:channels-reconcile --channel=4 --detail # 1 só
 *
 * **Cron (Kernel.php):**
 *   $schedule->command('whatsapp:channels-reconcile')
 *            ->everyFiveMinutes()
 *            ->withoutOverlapping(5)
 *            ->runInBackground();
 *
 * **Multi-tenant Tier 0 (ADR 0093):**
 * - `withoutGlobalScopes` justificado: cron sem session() user
 * - Cada channel carrega seu `business_id`; passado no dispatch do
 *   DeleteBaileysInstanceJob
 *
 * **Anti-spam daemon:** sleep `--sleep` ms entre requests (default 500ms).
 * 20 canais batch * 500ms = ~10s/round. Aceitável pra cron 5min.
 *
 * @see Modules/Whatsapp/Jobs/DeleteBaileysInstanceJob.php
 * @see memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md
 */
class ChannelsReconcilerCommand extends Command
{
    protected $signature = 'whatsapp:channels-reconcile
                            {--channel= : Channel ID específico (debug; default: todos)}
                            {--batch=20 : Máximo de channels processados por execução}
                            {--sleep=500 : Sleep ms entre requests ao daemon (anti-spam)}
                            {--dry-run : Preview sem persistir mudanças no DB}
                            {--detail : Imprime detalhes por channel (--verbose conflita com flag Symfony default)}';

    protected $description = 'Reconcilia channels Baileys DB ↔ daemon CT 100 (auto-fix drift + zumbis).';

    /**
     * @var array<string, int>  Counters do round
     */
    private array $stats = [
        'checked' => 0,
        'in_sync' => 0,
        'auto_fixed' => 0,
        'requires_reset' => 0,
        'zombie_detected' => 0,
        'daemon_errors' => 0,
    ];

    public function handle(): int
    {
        $daemonUrl = (string) config('whatsapp.baileys.daemon_url', '');
        $apiKey = (string) config('whatsapp.baileys.api_key', '');

        if ($daemonUrl === '' || $apiKey === '') {
            $this->error('WHATSAPP_BAILEYS_DAEMON_URL/_API_KEY ausente no .env — abortando.');
            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $detail = (bool) $this->option('detail');
        $batch = (int) $this->option('batch');
        $sleepMs = (int) $this->option('sleep');
        $singleChannel = $this->option('channel') !== null ? (int) $this->option('channel') : null;

        if ($isDryRun) {
            $this->warn('🔵 DRY-RUN — nenhuma mudança será persistida.');
        }

        $query = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('type', Channel::TYPE_WHATSAPP_BAILEYS)
            ->whereNotIn('status', ['setup', 'banned']) // setup nunca foi pareado; banned já marcado
            ->orderBy('id');

        if ($singleChannel !== null) {
            $query->where('id', $singleChannel);
        } else {
            $query->limit($batch);
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->info('Nenhum channel ativo Baileys pra reconciliar.');
            return self::SUCCESS;
        }

        $this->info("Reconciliando {$channels->count()} channel(s) Baileys...");

        foreach ($channels as $channel) {
            $this->reconcileChannel($channel, $daemonUrl, $apiKey, $isDryRun, $detail);
            if ($sleepMs > 0 && $channels->count() > 1) {
                usleep($sleepMs * 1000);
            }
        }

        // Sumário pro operador (e pra logs/Sentry parse fácil)
        $this->newLine();
        $this->table(
            ['Métrica', 'Count'],
            [
                ['Checked', $this->stats['checked']],
                ['In sync', $this->stats['in_sync']],
                ['Auto-fixed (drift corrigido)', $this->stats['auto_fixed']],
                ['Requires reset (precisa re-parear)', $this->stats['requires_reset']],
                ['Zombie detected (state=connected stale)', $this->stats['zombie_detected']],
                ['Daemon errors', $this->stats['daemon_errors']],
            ]
        );

        Log::info('[whatsapp.channels-reconcile] round complete', $this->stats);

        return self::SUCCESS;
    }

    private function reconcileChannel(
        Channel $channel,
        string $daemonUrl,
        string $apiKey,
        bool $isDryRun,
        bool $detail,
    ): void {
        $this->stats['checked']++;

        $instanceId = 'ch-' . str_replace('-', '', (string) $channel->channel_uuid);

        try {
            $response = Http::withToken($apiKey)
                ->withoutVerifying() // FIXME(US-WA-058) — cert LE pendente
                ->timeout(10)
                ->get("{$daemonUrl}/instances/{$instanceId}/status");
        } catch (\Throwable $e) {
            $this->stats['daemon_errors']++;
            if ($detail) {
                $this->error("  ✗ ch#{$channel->id} ({$channel->label}): daemon offline — {$e->getMessage()}");
            }
            return;
        }

        // 404 daemon = instância não existe (mas DB diz ativo → precisa reset)
        if ($response->status() === 404) {
            if ($detail) {
                $this->warn("  ⚠ ch#{$channel->id} ({$channel->label}): instância não existe no daemon → marcando setup");
            }
            $this->stats['requires_reset']++;
            if (! $isDryRun) {
                $channel->forceFill([
                    'status' => 'setup',
                    'channel_health' => 'disconnected',
                    'last_health_check_at' => now(),
                    'last_health_message' => 'reconciler: instance_not_found no daemon — re-parear',
                ])->save();
            }
            return;
        }

        if (! $response->successful()) {
            $this->stats['daemon_errors']++;
            if ($detail) {
                $this->error("  ✗ ch#{$channel->id}: daemon HTTP {$response->status()}");
            }
            return;
        }

        $snap = $response->json();
        $daemonState = $snap['state'] ?? null;
        $banReason = $snap['ban_reason'] ?? null;
        $lastSeen = $snap['last_seen'] ?? null;
        $dbStatus = $channel->status;

        // Auto-fix: DB diz active mas daemon diz banned/disconnected/error
        if ($dbStatus === 'active' && in_array($daemonState, ['banned', 'disconnected', 'error'], true)) {
            $newStatus = $daemonState === 'banned' ? 'banned' : 'disconnected';
            if ($detail) {
                $this->warn("  🔧 ch#{$channel->id}: DB=active mas daemon={$daemonState} — auto-fix → DB={$newStatus}");
            }
            $this->stats['auto_fixed']++;
            if (! $isDryRun) {
                $channel->forceFill([
                    'status' => $newStatus,
                    'channel_health' => $daemonState,
                    'channel_health_consecutive_failures' => ($channel->channel_health_consecutive_failures ?? 0) + 1,
                    'last_health_check_at' => now(),
                    'last_health_message' => "reconciler: drift {$dbStatus}→{$daemonState}" . ($banReason ? " ({$banReason})" : ''),
                ])->save();
                // Observer ChannelObserver vai dispatchar DeleteBaileysInstanceJob automaticamente
                // pela transição active → banned/disconnected (PR #815)
            }
            return;
        }

        // Auto-fix reverso: DB diz disconnected mas daemon diz connected (raro)
        if ($dbStatus === 'disconnected' && $daemonState === 'connected') {
            if ($detail) {
                $this->info("  ↻ ch#{$channel->id}: DB=disconnected mas daemon=connected — auto-fix → DB=active");
            }
            $this->stats['auto_fixed']++;
            if (! $isDryRun) {
                $channel->forceFill([
                    'status' => 'active',
                    'channel_health' => 'healthy',
                    'channel_health_consecutive_failures' => 0,
                    'last_health_check_at' => now(),
                    'last_health_message' => 'reconciler: drift corrigido — daemon estava connected',
                ])->save();
            }
            return;
        }

        // Zombie detection: state=connected mas last_seen > 30min
        if ($daemonState === 'connected' && $lastSeen !== null) {
            $lastSeenTs = strtotime($lastSeen);
            if ($lastSeenTs !== false && (time() - $lastSeenTs) > 1800) {
                $minStale = (int) ((time() - $lastSeenTs) / 60);
                if ($detail) {
                    $this->warn("  💀 ch#{$channel->id}: zombie — state=connected mas last_seen estagnado {$minStale}min");
                }
                $this->stats['zombie_detected']++;
                // Apenas log — PR #817 (healthcheck 503) cuida do restart Docker
            }
        }

        // Tudo OK — atualiza apenas timestamp
        if ($daemonState === 'connected' && $dbStatus === 'active') {
            $this->stats['in_sync']++;
            if ($detail) {
                $this->line("  ✓ ch#{$channel->id} ({$channel->label}): in sync (state=connected)");
            }
            if (! $isDryRun) {
                $channel->forceFill([
                    'last_health_check_at' => now(),
                    'channel_health' => 'healthy',
                    'channel_health_consecutive_failures' => 0,
                ])->save();
            }
            return;
        }

        // Estados sem ação (qr_required, connecting)
        if ($detail) {
            $this->line("  · ch#{$channel->id}: daemon={$daemonState}, DB={$dbStatus} — sem ação");
        }
        $this->stats['in_sync']++;
    }
}
