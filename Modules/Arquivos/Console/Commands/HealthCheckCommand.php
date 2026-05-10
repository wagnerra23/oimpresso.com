<?php

namespace Modules\Arquivos\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * arquivos:health-check — Sprint 2 ADR 0123 (compliance LGPD + integridade DMS).
 *
 * Dashboard de saúde do backbone Modules/Arquivos. Equivalente ao jana:health-check
 * (5 checks SQL) mas focado nos sinais de compliance LGPD e integridade DMS.
 *
 * 5 checks obrigatórios:
 *   1. orphan_files         — arquivos no DB sem file físico no disk
 *   2. dedupe_inconsistent  — registros fantasmas em arquivos_dedupe sem row em arquivos
 *   3. audit_log_lag        — tempo desde o último registro no audit log
 *   4. retention_overdue    — soft-deleted além do grace period (retention + 30d)
 *   5. vault_encryption_ratio — % de bucket=sensitive com encrypted=true
 *
 * Multi-tenant Tier 0 (ADR 0093): command CLI sem session.
 *   - Sem --business: admin global view (itera todos businesses)
 *   - Com --business: filtra explicitamente um business
 *
 * Proteção DB: hard cap interno de 1000 rows por check de filesystem (sample).
 * Health check é SOMENTE LEITURA — nenhum INSERT/UPDATE/DELETE.
 *
 * Exit code:
 *   - Sem --alert: sempre 0 (info-only)
 *   - Com --alert: 2 se FAIL, 1 se WARN, 0 se todos OK
 *
 * Uso:
 *   php artisan arquivos:health-check
 *   php artisan arquivos:health-check --business=1
 *   php artisan arquivos:health-check --json
 *   php artisan arquivos:health-check --alert
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 2
 * @see LGPD Art. 46 (segurança dos dados) · Art. 15-16 (término do tratamento)
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'arquivos:health-check
        {--business= : Filtra por business_id (default: todos)}
        {--alert : Exit code 2 se algum check FAIL, 1 se WARN (pra cron + monitoring alert)}
        {--json : Output JSON estruturado em vez de tabela (pra integração dashboard)}';

    protected $description = 'Dashboard de saúde do backbone Arquivos — 5 sinais compliance LGPD + integridade DMS (ADR 0123).';

    /** Hard cap de rows verificadas no check orphan_files (filesystem call por row — caro). */
    private const ORPHAN_SAMPLE_CAP = 1000;

    /** Threshold WARN para ratio de orphans (1-10%). */
    private const ORPHAN_WARN_PCT = 0.01;

    /** Threshold FAIL para ratio de orphans (>10%). */
    private const ORPHAN_FAIL_PCT = 0.10;

    /** Horas de inatividade no audit log antes de WARN. */
    private const AUDIT_LAG_WARN_HOURS = 24;

    /** Dias de grace period após retention_days antes de FAIL. */
    private const RETENTION_GRACE_DAYS = 30;

    /** Retention default em dias (fallback se config ausente). */
    private const RETENTION_DEFAULT_DAYS = 90;

    /** % mínimo de sensitive encrypted para WARN (95%). */
    private const VAULT_WARN_PCT = 0.95;

    /** % mínimo de sensitive encrypted para não FAIL (95%). Abaixo = FAIL. */
    private const VAULT_FAIL_PCT = 0.95;

    public function handle(): int
    {
        // Verifica tabelas obrigatórias antes de rodar
        if (! Schema::hasTable('arquivos')) {
            $this->error('arquivos table missing — rode Modules/Arquivos migrate primeiro.');
            return 1;
        }

        $businessId = $this->option('business') !== null
            ? (int) $this->option('business')
            : null;

        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');

        // Roda os 5 checks — cada um retorna array com: name, status, value, threshold, details, recommendation
        $checks = [
            $this->checkOrphanFiles($businessId),
            $this->checkDedupeInconsistent($businessId),
            $this->checkAuditLogLag($businessId),
            $this->checkRetentionOverdue($businessId),
            $this->checkVaultEncryptionRatio($businessId),
        ];

        // Monta summary
        $summary = [
            'ok'    => collect($checks)->filter(fn ($c) => $c['status'] === 'OK')->count(),
            'warn'  => collect($checks)->filter(fn ($c) => $c['status'] === 'WARN')->count(),
            'fail'  => collect($checks)->filter(fn ($c) => $c['status'] === 'FAIL')->count(),
            'total' => count($checks),
        ];

        if ($asJson) {
            return $this->outputJson($checks, $summary, $businessId);
        }

        return $this->outputTable($checks, $summary, $businessId, $alert);
    }

    // =========================================================================
    // Checks
    // =========================================================================

    /**
     * Check 1: orphan_files
     *
     * Verifica rows em `arquivos` onde o arquivo físico não existe no disk.
     * ATENÇÃO: filesystem call por row — caro. Amostrado em max 1000 rows.
     */
    private function checkOrphanFiles(?int $businessId): array
    {
        if (! Schema::hasTable('arquivos')) {
            return $this->makeCheck('orphan_files', 'WARN', null, '10%', 'Tabela arquivos ausente', 'Rode migrate primeiro.');
        }

        $query = DB::table('arquivos')
            ->whereNull('deleted_at')
            ->select(['id', 'business_id', 'disk', 'storage_path'])
            ->orderBy('id')
            ->limit(self::ORPHAN_SAMPLE_CAP);

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $rows = $query->get();
        $sampled = $rows->count();

        if ($sampled === 0) {
            return $this->makeCheck('orphan_files', 'OK', 0, '10%', '0 orphans em 0 sampled', 'Sem arquivos no DB.');
        }

        $orphans = 0;
        $topOrphans = [];

        foreach ($rows as $row) {
            try {
                $diskName = $row->disk ?: 'local';
                $exists = Storage::disk($diskName)->exists($row->storage_path);
            } catch (\Throwable) {
                // Disk inválido / inacessível — conta como orphan conservador
                $exists = false;
            }

            if (! $exists) {
                $orphans++;

                if (count($topOrphans) < 5) {
                    $topOrphans[] = "id:{$row->id} biz:{$row->business_id} disk:{$row->disk}";
                }
            }
        }

        $ratio = $sampled > 0 ? $orphans / $sampled : 0;
        $pctStr = number_format($ratio * 100, 1) . '%';

        // Determina status
        if ($ratio >= self::ORPHAN_FAIL_PCT) {
            $status = 'FAIL';
            $recommendation = 'Investigar disk / storage_path. Possível disk mal configurado ou arquivos movidos externamente.';
        } elseif ($ratio >= self::ORPHAN_WARN_PCT) {
            $status = 'WARN';
            $recommendation = 'Monitorar. Poucos orphans são normais (deleções em progresso), mas acima de 1% merece atenção.';
        } else {
            $status = 'OK';
            $recommendation = 'Integridade OK.';
        }

        $topStr = empty($topOrphans) ? '' : ' | top: ' . implode(', ', $topOrphans);
        $isSample = $sampled >= self::ORPHAN_SAMPLE_CAP ? ' (amostragem — cap 1000)' : '';

        return $this->makeCheck(
            'orphan_files',
            $status,
            $orphans,
            '10%',
            "{$orphans} orphans em {$sampled} sampled ({$pctStr}){$topStr}{$isSample}",
            $recommendation
        );
    }

    /**
     * Check 2: dedupe_inconsistent
     *
     * Verifica registros fantasmas em arquivos_dedupe:
     * rows com occurrences > 0 mas sem row correspondente em arquivos com mesmo md5.
     */
    private function checkDedupeInconsistent(?int $businessId): array
    {
        if (! Schema::hasTable('arquivos_dedupe')) {
            return $this->makeCheck('dedupe_inconsistent', 'WARN', null, '0', 'Tabela arquivos_dedupe ausente', 'Rode migrate primeiro.');
        }

        // SQL: registros em arquivos_dedupe sem correspondente em arquivos
        // Quando --business aplicado, filtra o JOIN em arquivos pelo business_id
        $query = DB::table('arquivos_dedupe as d')
            ->where('d.occurrences', '>', 0)
            ->whereNotExists(function ($sub) use ($businessId) {
                $sub->select(DB::raw(1))
                    ->from('arquivos as a')
                    ->whereColumn('a.md5', 'd.md5')
                    ->whereNull('a.deleted_at');

                if ($businessId !== null) {
                    $sub->where('a.business_id', $businessId);
                }
            });

        $fantasmas = (clone $query)->count();

        if ($fantasmas === 0) {
            return $this->makeCheck('dedupe_inconsistent', 'OK', 0, '0', '0 registros fantasmas em arquivos_dedupe', 'Consistência de deduplicação OK.');
        }

        return $this->makeCheck(
            'dedupe_inconsistent',
            'WARN',
            $fantasmas,
            '0',
            "{$fantasmas} registro(s) fantasma(s) em arquivos_dedupe sem row em arquivos",
            'Rodar arquivos:dedupe-stats e investigar. Possível regressão em cleanup ou migration incompleta.'
        );
    }

    /**
     * Check 3: audit_log_lag
     *
     * Verifica o tempo desde o último registro em arquivos_audit_log.
     * > 24h sem atividade = WARN (sistema parado ou log não escrevendo).
     */
    private function checkAuditLogLag(?int $businessId): array
    {
        if (! Schema::hasTable('arquivos_audit_log')) {
            return $this->makeCheck('audit_log_lag', 'WARN', null, '24h', 'Tabela arquivos_audit_log ausente', 'Rode migrate primeiro.');
        }

        $query = DB::table('arquivos_audit_log')
            ->select(DB::raw('MAX(created_at) as ultimo_log'))
            ->limit(1);

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $row = $query->first();
        $ultimoLog = $row->ultimo_log ?? null;

        if ($ultimoLog === null) {
            return $this->makeCheck(
                'audit_log_lag',
                'WARN',
                null,
                '24h',
                'Nenhum registro em arquivos_audit_log',
                'Verificar se upload/download está sendo logado. Pode ser ambiente sem atividade.'
            );
        }

        $ultimoLogAt = \Carbon\Carbon::parse($ultimoLog);
        $diffHours   = $ultimoLogAt->diffInHours(now(), true);

        if ($diffHours > self::AUDIT_LAG_WARN_HOURS) {
            return $this->makeCheck(
                'audit_log_lag',
                'WARN',
                $diffHours,
                '24h',
                "Último log: {$ultimoLog} ({$diffHours}h atrás) — acima do limite de " . self::AUDIT_LAG_WARN_HOURS . 'h',
                'Verificar se ArquivosService está logando ações. Pode indicar sistema parado ou falha no AuditLog middleware.'
            );
        }

        return $this->makeCheck(
            'audit_log_lag',
            'OK',
            $diffHours,
            '24h',
            "Último log: {$ultimoLog} ({$diffHours}h atrás) — dentro do limite de " . self::AUDIT_LAG_WARN_HOURS . 'h',
            'Atividade de audit log OK.'
        );
    }

    /**
     * Check 4: retention_overdue
     *
     * Verifica soft-deleted rows além do grace period (retention_default + 30d).
     * Indica que arquivos:retention-cleanup não está rodando.
     *
     * Threshold: > 0 = WARN, > 100 = FAIL.
     */
    private function checkRetentionOverdue(?int $businessId): array
    {
        if (! Schema::hasTable('arquivos')) {
            return $this->makeCheck('retention_overdue', 'WARN', null, '0/>100=FAIL', 'Tabela arquivos ausente', 'Rode migrate primeiro.');
        }

        $retentionDays = (int) (config('arquivos.retention_days_default', self::RETENTION_DEFAULT_DAYS) ?: self::RETENTION_DEFAULT_DAYS);
        $graceDays     = $retentionDays + self::RETENTION_GRACE_DAYS;
        $threshold     = now()->subDays($graceDays);

        $query = DB::table('arquivos')
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $threshold->toDateTimeString());

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $count = $query->count();

        if ($count === 0) {
            return $this->makeCheck(
                'retention_overdue',
                'OK',
                0,
                '0/>100=FAIL',
                "0 arquivos soft-deleted além do grace period de {$graceDays}d",
                'Retention cleanup OK. Sem pendências.'
            );
        }

        if ($count > 100) {
            return $this->makeCheck(
                'retention_overdue',
                'FAIL',
                $count,
                '0/>100=FAIL',
                "{$count} arquivos soft-deleted além de {$graceDays}d (retention {$retentionDays}d + grace 30d)",
                'CRÍTICO: Rodar arquivos:retention-cleanup imediatamente. Compliance LGPD Art. 15-16 em risco.'
            );
        }

        return $this->makeCheck(
            'retention_overdue',
            'WARN',
            $count,
            '0/>100=FAIL',
            "{$count} arquivos soft-deleted além de {$graceDays}d (retention {$retentionDays}d + grace 30d)",
            'Agendar arquivos:retention-cleanup. LGPD Art. 15-16: dados não devem ser retidos além da finalidade.'
        );
    }

    /**
     * Check 5: vault_encryption_ratio
     *
     * Verifica % de rows com bucket='sensitive' que têm encrypted=true.
     * Esperado: 100% após Sprint 1 dia 4 (VaultEncryptionService).
     *
     * Threshold: < 95% = FAIL, 95-99% = WARN, 100% = OK.
     */
    private function checkVaultEncryptionRatio(?int $businessId): array
    {
        if (! Schema::hasTable('arquivos')) {
            return $this->makeCheck('vault_encryption_ratio', 'WARN', null, '>=95%', 'Tabela arquivos ausente', 'Rode migrate primeiro.');
        }

        $query = DB::table('arquivos')
            ->where('bucket', 'sensitive')
            ->whereNull('deleted_at');

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            return $this->makeCheck(
                'vault_encryption_ratio',
                'OK',
                100,
                '>=95%',
                '0 arquivos sensitive no sistema — ratio N/A',
                'Sem arquivos sensitive. OK por ora; monitorar ao classificar novos arquivos.'
            );
        }

        $encrypted   = (clone $query)->where('encrypted', true)->count();
        $unencrypted = $total - $encrypted;
        $ratio       = $encrypted / $total;
        $pctStr      = number_format($ratio * 100, 1) . '%';

        if ($ratio < self::VAULT_FAIL_PCT) {
            return $this->makeCheck(
                'vault_encryption_ratio',
                'FAIL',
                $pctStr,
                '>=95%',
                "{$pctStr} sensitive encrypted ({$encrypted}/{$total}) — {$unencrypted} SEM encryption",
                'CRÍTICO: Rodar arquivos:reencrypt-vault. Dados sensitive em disco sem criptografia viola ADR 0123 §3.'
            );
        }

        if ($ratio < 1.0) {
            return $this->makeCheck(
                'vault_encryption_ratio',
                'WARN',
                $pctStr,
                '>=95%',
                "{$pctStr} sensitive encrypted ({$encrypted}/{$total}) — {$unencrypted} SEM encryption",
                'Próximo de 100% mas há arquivos sem encrypt. Verificar upload recente e rodar reencrypt-vault se necessário.'
            );
        }

        return $this->makeCheck(
            'vault_encryption_ratio',
            'OK',
            $pctStr,
            '>=95%',
            "100% sensitive encrypted ({$encrypted}/{$total})",
            'Vault encryption OK. Todos os arquivos sensitive estão cifrados.'
        );
    }

    // =========================================================================
    // Output
    // =========================================================================

    /**
     * Saída em tabela (modo padrão).
     *
     * @param  array<int, array<string, mixed>>  $checks
     * @param  array<string, int>                $summary
     */
    private function outputTable(array $checks, array $summary, ?int $businessId, bool $alert): int
    {
        $bizLabel = $businessId !== null ? "business_id={$businessId}" : 'todos businesses (admin)';
        $this->line('');
        $this->info('🔍 Modules/Arquivos Health Check — ' . now()->toDateTimeString());
        $this->line("   Filtro: {$bizLabel}");
        $this->newLine();

        $headers = ['Check', 'Status', 'Details', 'Recommendation'];

        $tableRows = collect($checks)->map(function (array $check) {
            $statusIcon = match ($check['status']) {
                'OK'   => '✅ OK',
                'WARN' => '⚠️  WARN',
                'FAIL' => '❌ FAIL',
                default => $check['status'],
            };

            return [
                $check['name'],
                $statusIcon,
                mb_strimwidth((string) $check['details'], 0, 80, '…'),
                mb_strimwidth((string) $check['recommendation'], 0, 80, '…'),
            ];
        })->toArray();

        $this->table($headers, $tableRows);

        $this->newLine();

        // Footer summary
        $summaryLine = sprintf(
            '%d OK, %d WARN, %d FAIL de %d checks',
            $summary['ok'],
            $summary['warn'],
            $summary['fail'],
            $summary['total']
        );

        if ($summary['fail'] > 0) {
            $this->error("  Resumo: {$summaryLine}");
        } elseif ($summary['warn'] > 0) {
            $this->warn("  Resumo: {$summaryLine}");
        } else {
            $this->info("  Resumo: {$summaryLine}");
        }

        $this->newLine();

        return $this->resolveExitCode($summary, $alert);
    }

    /**
     * Saída em JSON estruturado (--json).
     *
     * @param  array<int, array<string, mixed>>  $checks
     * @param  array<string, int>                $summary
     */
    private function outputJson(array $checks, array $summary, ?int $businessId): int
    {
        $output = [
            'timestamp'       => now()->toIso8601String(),
            'business_filter' => $businessId,
            'checks'          => collect($checks)->map(function (array $check) {
                return [
                    'name'           => $check['name'],
                    'status'         => $check['status'],
                    'value'          => $check['value'],
                    'threshold'      => $check['threshold'],
                    'details'        => $check['details'],
                    'recommendation' => $check['recommendation'],
                ];
            })->values()->toArray(),
            'summary' => $summary,
        ];

        $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // --alert em modo JSON também afeta exit code
        $alert = (bool) $this->option('alert');
        return $this->resolveExitCode($summary, $alert);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Fábrica de array de check padronizado.
     *
     * @param  mixed  $value  Valor medido (int, float, string ou null)
     * @return array<string, mixed>
     */
    private function makeCheck(
        string $name,
        string $status,
        mixed $value,
        string $threshold,
        string $details,
        string $recommendation
    ): array {
        return compact('name', 'status', 'value', 'threshold', 'details', 'recommendation');
    }

    /**
     * Resolve exit code conforme --alert e summary.
     *
     * @param  array<string, int>  $summary
     */
    private function resolveExitCode(array $summary, bool $alert): int
    {
        if (! $alert) {
            return 0;
        }

        if ($summary['fail'] > 0) {
            return 2;
        }

        if ($summary['warn'] > 0) {
            return 1;
        }

        return 0;
    }
}
