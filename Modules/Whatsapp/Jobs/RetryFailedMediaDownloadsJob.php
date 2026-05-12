<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Message;

/**
 * Guardião 6 camadas — Camada 4 (retry hourly).
 *
 * Rede de proteção pra dispatches que falharam por:
 *   - Queue connection down quando Observer tentou disparar
 *   - Daemon CT 100 offline temporariamente (deploy, restart, network)
 *   - Erro transitório (timeout, 5xx, exception fora dos handlers)
 *
 * Escopo: messages com `media_download_status IN (pending, downloading)`,
 * `media_url IS NULL`, `attempts < MAX_ATTEMPTS`, criadas há ≤ 7 dias.
 *
 * Por que 7 dias? URL Baileys cripto expira em ~14 dias (chave de sessão),
 * Z-API/Meta direct download em 30d-90d. 7d é janela conservadora —
 * tentar mais antigo gasta queue/daemon time sem grande chance de sucesso.
 *
 * Multi-tenant Tier 0 (ADR 0093): SUPERADMIN cross-business — comentário
 * explícito justifica `withoutGlobalScopes()`. Filtro `business_id` é
 * implícito (Job dispatched per-message já carrega o ID correto).
 *
 * Performance: lazy(50) — não carrega 1000 messages em memória de uma vez.
 * Hostinger queue=sync → cada dispatch roda síncrono no mesmo request.
 * Em fila normal vira batch async.
 *
 * @see Modules/Whatsapp/Jobs/DownloadMediaJob.php (Camada 3)
 * @see app/Console/Kernel.php (schedule hourly)
 */
class RetryFailedMediaDownloadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Tries 1 — se cron falhar, próxima hora pega de novo. */
    public int $tries = 1;

    /** Lookback dias — só retentar mídia criada nos últimos N dias. */
    public const LOOKBACK_DAYS = 7;

    /** Batch size — lazy chunks. */
    public const BATCH_SIZE = 50;

    public function handle(): void
    {
        $startedAt = now();
        $dispatched = 0;

        // SUPERADMIN: cron cross-business (ADR 0093). Filtro business_id
        // implícito via Message::business_id propagado pro Job downstream.
        Message::query()
            ->withoutGlobalScopes()
            ->whereIn('media_download_status', [
                Message::DOWNLOAD_STATUS_PENDING,
                Message::DOWNLOAD_STATUS_DOWNLOADING,
            ])
            ->whereNull('media_url')
            ->whereNotNull('media_mime')
            ->where('media_download_attempts', '<', Message::MEDIA_DOWNLOAD_MAX_ATTEMPTS)
            ->where('created_at', '>', now()->subDays(self::LOOKBACK_DAYS))
            ->lazy(self::BATCH_SIZE)
            ->each(function (Message $m) use (&$dispatched) {
                try {
                    DownloadMediaJob::dispatch(
                        $m->business_id,
                        $m->id,
                        '',
                        (string) ($m->media_mime ?? ''),
                    );
                    $dispatched++;
                } catch (\Throwable $e) {
                    Log::warning('[retry_failed_media] dispatch falhou', [
                        'message_id' => $m->id,
                        'business_id' => $m->business_id,
                        'error' => mb_substr($e->getMessage(), 0, 200),
                    ]);
                }
            });

        $durationMs = (int) round((now()->floatDiffInSeconds($startedAt)) * 1000);
        Log::info('[retry_failed_media] tick', [
            'dispatched' => $dispatched,
            'lookback_days' => self::LOOKBACK_DAYS,
            'duration_ms' => $durationMs,
        ]);
    }
}
