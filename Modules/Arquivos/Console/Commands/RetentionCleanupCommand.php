<?php

namespace Modules\Arquivos\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * arquivos:retention-cleanup — Sprint 7 ADR 0123 (LGPD hard-delete pós-retention).
 *
 * Purga definitiva (hard delete) de arquivos soft-deleted há mais de N dias.
 * Compliance LGPD Art. 15-16: dados pessoais não devem ser retidos além do
 * período necessário à sua finalidade.
 *
 * Fluxo:
 *   1. Resolve retention: --days > config('arquivos.retention_days_default') > 90
 *   2. Calcula threshold: Carbon::now()->subDays($retentionDays)
 *   3. Query arquivos WHERE deleted_at IS NOT NULL AND deleted_at < $threshold
 *   4. Chunk 100 rows — pra cada row:
 *        - Storage::disk($disk)->delete($path)  (try/catch — orphan ok)
 *        - DB::table('arquivos')->where('id', $id)->delete()  (hard delete)
 *        - Insert audit_log action=hard_delete
 *   5. Stats: hard_deleted, missing_file, errored
 *   6. Exit 0 se OK, exit 2 se errored > total/2
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   Command CLI sem session — --business é filtro EXPLÍCITO.
 *   Sem filtro = admin global view (operação administrativa justificada).
 *   Audit log SEMPRE preserva business_id original pra rastreio LGPD.
 *
 * Uso:
 *   php artisan arquivos:retention-cleanup
 *   php artisan arquivos:retention-cleanup --dry-run
 *   php artisan arquivos:retention-cleanup --days=30 --business=1 --limit=200
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 7
 * @see LGPD Art. 15-16 (término do tratamento de dados pessoais)
 */
class RetentionCleanupCommand extends Command
{
    protected $signature = 'arquivos:retention-cleanup
        {--days= : Override config retention_days_default (default 90)}
        {--business= : Filtra por business_id (default: todos — admin operação)}
        {--limit=500 : Cap rows processadas}
        {--dry-run : Não deleta, só simula + log}';

    protected $description = 'Hard-delete arquivos soft-deleted além do período de retention — LGPD compliance (ADR 0123 Sprint 7).';

    public function handle(): int
    {
        if (! Schema::hasTable('arquivos')) {
            $this->error('arquivos table missing — rode Modules/Arquivos migrate primeiro.');
            return 1;
        }

        // ── Resolve opções ──────────────────────────────────────────────────
        $dryRun    = (bool) $this->option('dry-run');
        $limit     = max(1, (int) $this->option('limit'));
        $businessId = $this->option('business') !== null
            ? (int) $this->option('business')
            : null;

        // Resolve retention: --days override > config > 90
        $retentionDays = $this->resolveRetentionDays();
        if ($retentionDays === null) {
            // Erro de validação já emitido por resolveRetentionDays()
            return 1;
        }

        $threshold = Carbon::now()->subDays($retentionDays);

        // ── Header informativo ───────────────────────────────────────────────
        if ($businessId !== null) {
            $this->line("Retention cleanup — business_id={$businessId} | retention={$retentionDays}d | threshold={$threshold->toDateTimeString()} | limit={$limit}");
        } else {
            $this->warn('MODO ADMIN — todos businesses (sem filtro --business)');
            $this->line("Retention cleanup — retention={$retentionDays}d | threshold={$threshold->toDateTimeString()} | limit={$limit}");
        }

        if ($dryRun) {
            $this->warn('[DRY-RUN] Nenhuma modificação será feita.');
        }

        $this->newLine();

        // ── Conta rows candidatas ─────────────────────────────────────────
        $query = DB::table('arquivos')
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $threshold->toDateTimeString());

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nada pra processar.');
            return 0;
        }

        // ── Warning obrigatório antes de hard delete real ─────────────────
        if (! $dryRun) {
            $this->warn("⚠️  HARD DELETE — {$total} rows serão purgadas DEFINITIVAMENTE. Audit log preservado.");
            $this->newLine();
        } else {
            $this->info("Simulação: {$total} row(s) seriam processadas.");
            $this->newLine();
        }

        // ── Stats ─────────────────────────────────────────────────────────
        $stats = [
            'hard_deleted' => 0,
            'missing_file' => 0,
            'errored'      => 0,
        ];

        $query->orderBy('id')
            ->limit($limit)
            ->chunk(100, function ($rows) use ($dryRun, $retentionDays, &$stats) {
                foreach ($rows as $row) {
                    if ($dryRun) {
                        // Dry-run: apenas imprime o que faria
                        $this->line(
                            "  arquivo:{$row->id} biz:{$row->business_id} disk:{$row->disk} " .
                            "path:{$row->storage_path} deleted_at:{$row->deleted_at} → would hard-delete"
                        );
                        $stats['hard_deleted']++;
                        continue;
                    }

                    try {
                        // 1. Remove file físico do disk
                        $fileRemoved = false;
                        try {
                            if (Storage::disk($row->disk)->exists($row->storage_path)) {
                                Storage::disk($row->disk)->delete($row->storage_path);
                                $fileRemoved = true;
                            } else {
                                // Orphan — arquivo ausente no disk mas presente no DB
                                $stats['missing_file']++;
                                $fileRemoved = false;
                            }
                        } catch (\Throwable $diskErr) {
                            // Erro de disk não impede hard-delete do DB (cleanup de orphan)
                            $stats['missing_file']++;
                            Log::warning('arquivos.retention_cleanup.disk_error', [
                                'arquivo_id' => $row->id,
                                'disk'       => $row->disk,
                                'path'       => $row->storage_path,
                                'error'      => substr($diskErr->getMessage(), 0, 200),
                            ]);
                        }

                        // 2. Hard delete do DB (bypass SoftDeletes — delete direto via query builder)
                        DB::table('arquivos')->where('id', $row->id)->delete();

                        // 3. Audit log — action hard_delete (LGPD Art. 15-16 rastreabilidade)
                        DB::table('arquivos_audit_log')->insert([
                            'arquivo_id'  => $row->id,
                            'business_id' => $row->business_id,
                            'user_id'     => null, // CLI sem sessão — user_action no payload
                            'action'      => 'hard_delete',
                            'payload'     => json_encode([
                                'retention_days'      => $row->retention_days ?? null,
                                'retention_days_used' => $this->resolveRetentionDays(),
                                'original_deleted_at' => $row->deleted_at,
                                'business_id'         => $row->business_id,
                                'user_action'         => 'retention_cleanup_command',
                                'file_removed_from_disk' => $fileRemoved,
                            ]),
                            'created_at'  => now()->toDateTimeString(),
                        ]);

                        $stats['hard_deleted']++;
                    } catch (\Throwable $e) {
                        $stats['errored']++;
                        Log::error('arquivos.retention_cleanup.error', [
                            'arquivo_id' => $row->id ?? null,
                            'business_id' => $row->business_id ?? null,
                            'error'      => substr($e->getMessage(), 0, 200),
                        ]);
                    }
                }

                // Log por batch — rastreio de progresso em jobs longos
                Log::info('arquivos.retention_cleanup', array_merge($stats, [
                    'dry_run'        => $dryRun,
                    'retention_days' => $retentionDays,
                    'business_id'    => $businessId,
                ]));
            });

        // ── Output final ──────────────────────────────────────────────────
        $this->newLine();
        $label = $dryRun ? 'Simulados (dry-run)' : 'Hard-deleted';
        $this->info("{$label}: {$stats['hard_deleted']}");
        $this->warn("File ausente (orphan): {$stats['missing_file']}");
        $this->error("Errored:               {$stats['errored']}");

        // Exit 2 se errored supera metade do total processado
        $processed = $stats['hard_deleted'] + $stats['errored'];
        if ($processed > 0 && $stats['errored'] > $processed / 2) {
            return 2;
        }

        return 0;
    }

    /**
     * Resolve o período de retention em dias.
     *
     * Prioridade: --days override > config('arquivos.retention_days_default') > 90.
     * Valida range [1, 3650] se --days foi passado.
     *
     * @return int|null  Retorna null se validação falhar (com mensagem de erro já emitida).
     */
    private function resolveRetentionDays(): ?int
    {
        $daysOption = $this->option('days');

        if ($daysOption !== null) {
            $days = (int) $daysOption;

            if ($days < 1 || $days > 3650) {
                $this->error("--days deve estar entre 1 e 3650 (10 anos). Recebido: {$daysOption}");
                return null;
            }

            return $days;
        }

        // Config ou fallback 90
        return (int) (config('arquivos.retention_days_default', 90) ?: 90);
    }
}
