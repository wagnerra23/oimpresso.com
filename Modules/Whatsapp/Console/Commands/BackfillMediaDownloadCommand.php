<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Jobs\DownloadMediaJob;

/**
 * Guardião 6 camadas — Bonus (backfill manual).
 *
 * Comando one-shot pra reprocessar messages órfãs históricas (antes do
 * guardião). Caso de uso: pós-deploy daemon CT 100 com endpoint
 * `/media/decrypt-url`, rodar `whatsapp:backfill-media-download --since=2026-05-10`
 * pra recuperar 89 messages biz=1 que ficaram com `media_url=null`.
 *
 * Idempotente: DownloadMediaJob no início checa se já success → skip.
 *
 * Multi-tenant Tier 0 (ADR 0093): SUPERADMIN cross-business via flag
 * `--business=all`. Comentário explícito justifica withoutGlobalScopes().
 *
 * Uso:
 *   php artisan whatsapp:backfill-media-download --dry-run
 *   php artisan whatsapp:backfill-media-download --business=4 --since=2026-05-01
 *   php artisan whatsapp:backfill-media-download --limit=10 --force-failed
 *
 * Flags:
 *   --business=N|all      default 'all' (cross-business)
 *   --since=YYYY-MM-DD    default 30 dias atrás
 *   --limit=N             default 1000 (cap pra evitar acidente)
 *   --dry-run             só conta, não dispatcha
 *   --force-failed        inclui failed_permanent (reset attempts pra 0)
 *
 * @see Modules/Whatsapp/Jobs/DownloadMediaJob.php
 */
class BackfillMediaDownloadCommand extends Command
{
    protected $signature = 'whatsapp:backfill-media-download
                            {--business=all : business_id específico ou "all"}
                            {--since= : Cutoff inferior YYYY-MM-DD (default 30d atrás)}
                            {--limit=1000 : Máx mensagens pra processar (cap)}
                            {--dry-run : Só conta, não dispatcha}
                            {--force-failed : Inclui failed_permanent (reset attempts)}';

    protected $description = 'Reprocessa mídia órfã histórica (Guardião 6 Bonus).';

    public function handle(): int
    {
        $businessId = $this->option('business');
        $businessFilter = $businessId !== 'all' ? (int) $businessId : null;
        $since = $this->option('since')
            ? \Carbon\Carbon::parse($this->option('since'))->startOfDay()
            : now()->subDays(30);
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $forceFailed = (bool) $this->option('force-failed');

        $this->info('Backfill mídia órfã — Guardião 6 Bonus');
        $this->line("  business : {$businessId}");
        $this->line("  since    : {$since->toDateString()}");
        $this->line("  limit    : {$limit}");
        $this->line("  dry-run  : " . ($dryRun ? 'sim' : 'não'));
        $this->line("  force-failed : " . ($forceFailed ? 'sim' : 'não'));
        $this->newLine();

        // SUPERADMIN: backfill cross-business (ADR 0093)
        $query = Message::query()
            ->withoutGlobalScopes()
            ->whereNotNull('media_mime')
            ->whereNull('media_url')
            ->where('created_at', '>=', $since);

        if ($businessFilter !== null) {
            $query->where('business_id', $businessFilter);
        }

        if (! $forceFailed) {
            // Excluir failed_permanent (default — não retentar caps)
            $query->where('media_download_status', '!=', Message::DOWNLOAD_STATUS_FAILED_PERMANENT);
        }

        $total = (int) $query->count();
        $this->line("Total candidatas: {$total}");

        if ($total === 0) {
            $this->info('Nada pra fazer.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("DRY-RUN — não dispatchou nada. Use sem --dry-run pra processar.");
            return self::SUCCESS;
        }

        $processed = 0;
        $query->orderBy('id')->limit($limit)->lazy(50)->each(function (Message $m) use ($forceFailed, &$processed) {
            // Reset attempts se --force-failed (senão respeita cap MAX_ATTEMPTS).
            if ($forceFailed && $m->media_download_status === Message::DOWNLOAD_STATUS_FAILED_PERMANENT) {
                $m->forceFill([
                    'media_download_status' => Message::DOWNLOAD_STATUS_PENDING,
                    'media_download_attempts' => 0,
                    'media_download_failed_reason' => null,
                ])->save();
            }

            DownloadMediaJob::dispatch(
                $m->business_id,
                $m->id,
                '',
                (string) ($m->media_mime ?? ''),
            );
            $processed++;
        });

        $this->info("OK — {$processed} jobs dispatchados.");
        return self::SUCCESS;
    }
}
