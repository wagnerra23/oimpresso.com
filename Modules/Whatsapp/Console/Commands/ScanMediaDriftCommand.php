<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Message;

/**
 * Guardião 6 camadas — Camada 5 (scan drift daily).
 *
 * Roda 1×/dia 03:30 BRT (após FSM scan-drift 03:00). Não corrige drift —
 * só MEDE e loga métricas pra que o time veja tendência:
 *
 *   - pending_count_1h          mídia pending há > 1h (sinal de retry job lento)
 *   - pending_count_24h         pending > 24h (sinal de problema sistêmico)
 *   - failed_permanent_7d       cap MAX_ATTEMPTS atingido última semana
 *   - total_size_pending_bytes  estimativa volume esperado (média MB/msg × count)
 *
 * Saída em tabela CLI human-readable + log estruturado pra Kibana/Datadog.
 * Em prod, integrar com `mcp_metrics` table se houver (atualmente apenas Log).
 *
 * Multi-tenant Tier 0 (ADR 0093): SUPERADMIN cross-business (consulta agregada).
 * Filtro `--business=N` permite cortar pra 1 tenant específico (debug).
 *
 * Uso:
 *   php artisan whatsapp:scan-media-drift
 *   php artisan whatsapp:scan-media-drift --business=4
 *   php artisan whatsapp:scan-media-drift --silent (não imprime tabela, só loga)
 *
 * @see app/Console/Kernel.php (schedule 03:30 BRT)
 * @see Modules/Whatsapp/Jobs/RetryFailedMediaDownloadsJob.php (Camada 4)
 */
class ScanMediaDriftCommand extends Command
{
    protected $signature = 'whatsapp:scan-media-drift
                            {--business=all : business_id específico ou "all"}
                            {--silent : Não imprime tabela CLI}';

    protected $description = 'Scan drift de mídia WhatsApp órfã (Camada 5 do Guardião 6).';

    public function handle(): int
    {
        $businessId = $this->option('business');
        $businessFilter = $businessId !== 'all' ? (int) $businessId : null;

        $metrics = [
            'pending_count_1h' => $this->countPending($businessFilter, now()->subHour()),
            'pending_count_24h' => $this->countPending($businessFilter, now()->subDay()),
            'failed_permanent_7d' => $this->countFailedPermanent($businessFilter, now()->subDays(7)),
            'total_size_pending_bytes' => $this->estimateTotalSize($businessFilter),
        ];

        $scope = $businessFilter ? "business={$businessFilter}" : 'all businesses';

        // Log estruturado SEMPRE (até em --silent) pra observability.
        Log::channel('single')->info('whatsapp:scan-media-drift', [
            'scope' => $scope,
            'metrics' => $metrics,
            'scanned_at' => now()->toIso8601String(),
        ]);

        if (! $this->option('silent')) {
            $this->renderTable($metrics, $scope);
        }

        // Exit code 0 sempre (scan é informativo, não enforce — health check Camada 6 alerta).
        return self::SUCCESS;
    }

    /**
     * Conta mídia com status pending OU downloading + media_url IS NULL,
     * criada antes do cutoff. Mídia órfã há muito tempo = sinal de problema.
     */
    protected function countPending(?int $businessFilter, \Carbon\Carbon $cutoff): int
    {
        $query = DB::table('messages')
            ->whereIn('media_download_status', [
                Message::DOWNLOAD_STATUS_PENDING,
                Message::DOWNLOAD_STATUS_DOWNLOADING,
            ])
            ->whereNull('media_url')
            ->whereNotNull('media_mime')
            ->where('created_at', '<', $cutoff);

        if ($businessFilter !== null) {
            $query->where('business_id', $businessFilter);
        }

        return (int) $query->count();
    }

    /**
     * Conta mídia que atingiu cap MAX_ATTEMPTS na janela passada.
     * Crescente = problema sistêmico (daemon offline, MIME novo bloqueado).
     */
    protected function countFailedPermanent(?int $businessFilter, \Carbon\Carbon $since): int
    {
        $query = DB::table('messages')
            ->where('media_download_status', Message::DOWNLOAD_STATUS_FAILED_PERMANENT)
            ->where('media_download_last_attempt_at', '>=', $since);

        if ($businessFilter !== null) {
            $query->where('business_id', $businessFilter);
        }

        return (int) $query->count();
    }

    /**
     * Estimativa volume pendente: count × média MB/msg dos sucessos.
     * Heurística simples — se média desconhecida (sem successes), assume 500KB.
     */
    protected function estimateTotalSize(?int $businessFilter): int
    {
        $countQuery = DB::table('messages')
            ->whereIn('media_download_status', [
                Message::DOWNLOAD_STATUS_PENDING,
                Message::DOWNLOAD_STATUS_DOWNLOADING,
            ])
            ->whereNull('media_url')
            ->whereNotNull('media_mime');

        $avgQuery = DB::table('messages')
            ->where('media_download_status', Message::DOWNLOAD_STATUS_SUCCESS)
            ->whereNotNull('media_size_bytes');

        if ($businessFilter !== null) {
            $countQuery->where('business_id', $businessFilter);
            $avgQuery->where('business_id', $businessFilter);
        }

        $count = (int) $countQuery->count();
        if ($count === 0) {
            return 0;
        }

        $avg = (float) ($avgQuery->avg('media_size_bytes') ?? 500_000);

        return (int) ($count * $avg);
    }

    /**
     * Tabela CLI human-readable. Output simples — equipa lê na hora.
     */
    protected function renderTable(array $metrics, string $scope): void
    {
        $this->newLine();
        $this->line('┌─────────────────────────────────────────────────────────────────────┐');
        $this->line('│  WHATSAPP MEDIA DRIFT — ' . str_pad(now()->toDateTimeString() . ' · ' . $scope, 44) . '│');
        $this->line('└─────────────────────────────────────────────────────────────────────┘');
        $this->newLine();

        $totalMb = number_format($metrics['total_size_pending_bytes'] / 1024 / 1024, 2);

        $rows = [
            ['pending_count_1h', (string) $metrics['pending_count_1h'], $metrics['pending_count_1h'] === 0 ? 'ok' : 'watch'],
            ['pending_count_24h', (string) $metrics['pending_count_24h'], $metrics['pending_count_24h'] === 0 ? 'ok' : 'alert'],
            ['failed_permanent_7d', (string) $metrics['failed_permanent_7d'], $metrics['failed_permanent_7d'] === 0 ? 'ok' : 'watch'],
            ['total_size_pending_bytes', "{$totalMb} MB", '—'],
        ];

        $this->table(['Métrica', 'Valor', 'Status'], $rows);

        $this->newLine();
        if ($metrics['pending_count_24h'] > 0) {
            $this->warn("ALERTA: {$metrics['pending_count_24h']} mídias pending > 24h — investigar daemon CT 100 + Retry job.");
        } else {
            $this->info('Sistema sadio — zero drift > 24h.');
        }
        $this->newLine();
    }
}
