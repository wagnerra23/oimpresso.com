<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Jobs\DownloadMediaJob;

/**
 * Wave 3 Agent B — Cron horário pra mídia inbound recente órfã.
 *
 * Histórico: 2026-05-15 09:25 BRT prod biz=1 (ROTA LIVRE) foi re-pareada
 * com Baileys 7.x deploy CT 100. BRIEFING reportou "55 msgs sincronizadas
 * via history fetch" e Wagner relatou "mídias não estão mostrando" no
 * Inbox `/atendimento/inbox`. Mídias dessas msgs históricas + as inbound
 * recentes ficaram com `messages.media_url=NULL` porque:
 *
 *   1. `DownloadMediaJob` falhou silencioso (daemon offline durante deploy)
 *   2. `PersistHistorySyncBatchJob` pode ter pulado o `MessageObserver`
 *      pra batch históricas — `media_download_status` ficou NULL/anômalo
 *   3. Race condition webhook + observer em alguma janela do deploy
 *
 * **Diferença vs Camada 4 (`RetryFailedMediaDownloadsJob`):**
 *
 *   Camada 4 só pega mídia em `status IN (pending, downloading)` —
 *   se ficou em estado anômalo (NULL, ou 'success' sem URL — Eloquent
 *   default em SQLite/MySQL pode variar), Camada 4 NÃO pega.
 *
 *   Este comando é MAIS PERMISSIVO (qualquer mídia com `media_url IS
 *   NULL` + `media_mime NOT NULL` + janela 24h, status irrelevante) e
 *   MAIS CONSERVADOR (só últimas 24h, limit padrão 200).
 *
 *   Complementa Camada 4 sem substituir. Roda hourly como rede adicional
 *   pra história recente. Wagner pediu explicitamente cron horário pro
 *   gap de mídias da última repareação não aparecerem por horas.
 *
 * **Idempotente:** `DownloadMediaJob::handle()` no início checa se já
 * `success` + `media_url !== null` → skip imediato. Re-run é safe.
 *
 * **Multi-tenant Tier 0 (ADR 0093):** SUPERADMIN cross-business via
 * `withoutGlobalScopes()`. Comentário explícito PT-BR justifica. Cada
 * `DownloadMediaJob` dispatchado carrega `$message->business_id` no
 * constructor (job não tem session — ADR 0093 §Jobs).
 *
 * **Anti-cap:** Excluí `failed_permanent` por default — esses já foram
 * processados pelo `DownloadMediaJob::handleFailedAttempt` 5 vezes e o
 * cap atingido tem razão (URL expirou, MIME bloqueado, daemon mal
 * configurado). Reprocessar gasta queue + daemon sem ganho. Use
 * `whatsapp:backfill-media-download --force-failed` ad-hoc se quiser.
 *
 * Uso típico:
 *   php artisan whatsapp:retry-recent-media-downloads
 *   php artisan whatsapp:retry-recent-media-downloads --hours=48 --limit=500
 *   php artisan whatsapp:retry-recent-media-downloads --dry-run
 *
 * Agendado em `app/Console/Kernel.php` hourly cross-business.
 *
 * @see Modules/Whatsapp/Jobs/DownloadMediaJob.php (Camada 3)
 * @see Modules/Whatsapp/Jobs/RetryFailedMediaDownloadsJob.php (Camada 4 — pareada)
 * @see Modules/Whatsapp/Console/Commands/BackfillMediaDownloadCommand.php (Bonus — ad-hoc histórico)
 * @see memory/sessions/2026-05-15-agent-b-retry-recent-media.md
 */
class RetryRecentMediaDownloadsCommand extends Command
{
    protected $signature = 'whatsapp:retry-recent-media-downloads
                            {--hours=24 : Lookback horas (default 24)}
                            {--limit=200 : Máx mensagens pra dispatchar (cap anti-flood)}
                            {--dry-run : Só conta, não dispatcha}';

    protected $description = 'Cron horário — retenta download de mídia inbound recente órfã (media_url IS NULL).';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = now()->subHours($hours);
        $startedAt = now();

        $this->info('Retry mídia inbound recente — Wave 3 Agent B');
        $this->line("  lookback : {$hours}h (since {$cutoff->toDateTimeString()})");
        $this->line("  limit    : {$limit}");
        $this->line("  dry-run  : " . ($dryRun ? 'sim' : 'não'));
        $this->newLine();

        // SUPERADMIN: cross-business cron horário (ADR 0093).
        // Jobs dispatchados carregam business_id próprio no constructor.
        $query = Message::query()
            ->withoutGlobalScopes()
            ->whereNull('media_url')
            ->whereNotNull('media_mime')
            ->where('created_at', '>=', $cutoff)
            // Status irrelevante EXCETO failed_permanent (cap atingido por razão).
            // Permissivo de propósito — pega NULL/success-sem-URL/pending/downloading.
            ->where(function ($q) {
                $q->whereNull('media_download_status')
                    ->orWhere('media_download_status', '!=', Message::DOWNLOAD_STATUS_FAILED_PERMANENT);
            });

        $total = (int) $query->count();
        $this->line("Total candidatas: {$total}");

        if ($total === 0) {
            $this->info('Nada pra fazer.');
            Log::info('[retry_recent_media] tick — nada pendente', [
                'hours' => $hours,
                'duration_ms' => $this->durationMs($startedAt),
            ]);
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('DRY-RUN — nenhum job dispatchado. Use sem --dry-run pra processar.');
            return self::SUCCESS;
        }

        $dispatched = 0;
        $failed = 0;

        $query->orderBy('id')
            ->limit($limit)
            ->lazy(50)
            ->each(function (Message $m) use (&$dispatched, &$failed) {
                try {
                    DownloadMediaJob::dispatch(
                        $m->business_id,
                        $m->id,
                        '',
                        (string) ($m->media_mime ?? ''),
                    );
                    $dispatched++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('[retry_recent_media] dispatch falhou', [
                        'message_id' => $m->id,
                        'business_id' => $m->business_id,
                        'error' => mb_substr($e->getMessage(), 0, 200),
                    ]);
                }
            });

        $durationMs = $this->durationMs($startedAt);

        $this->info("OK — {$dispatched} jobs dispatchados (falhou: {$failed}) em {$durationMs}ms.");

        Log::info('[retry_recent_media] tick', [
            'hours' => $hours,
            'limit' => $limit,
            'total_candidatas' => $total,
            'dispatched' => $dispatched,
            'failed' => $failed,
            'duration_ms' => $durationMs,
        ]);

        return self::SUCCESS;
    }

    /**
     * Mede duração em ms (floatDiff já usado em RetryFailedMediaDownloadsJob).
     */
    protected function durationMs(\Illuminate\Support\Carbon $startedAt): int
    {
        return (int) round(now()->floatDiffInSeconds($startedAt) * 1000);
    }
}
