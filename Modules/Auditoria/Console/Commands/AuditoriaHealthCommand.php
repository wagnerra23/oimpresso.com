<?php

declare(strict_types=1);

namespace Modules\Auditoria\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * auditoria:health — Health check Auditoria (D9.c — Wave 17 saturação 97%).
 *
 * Sinais mínimos:
 *   1. activity_log_table_present
 *   2. activity_24h — atividade gravada nas últimas 24h
 *   3. revert_columns_present — schema de revert (reverted_at/by/reason) aplicado
 *   4. revert_rate_7d — taxa de revert <5% (alta taxa sugere UX ruim)
 *
 * Multi-tenant Tier 0 (ADR 0093): agregação cross-tenant superadmin; NUNCA escreve.
 *
 * Exit code:
 *   - Sem --alert: sempre 0
 *   - Com --alert: 2 FAIL, 1 WARN, 0 OK
 *
 * NOTA Tier 0: NUNCA `--verbose` (Symfony reserved — usar `--detail` se precisar).
 *
 * @see memory/decisions/0127-modulo-auditoria-revert.md
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 */
class AuditoriaHealthCommand extends Command
{
    protected $signature = 'auditoria:health
        {--alert : Exit code 2 se FAIL, 1 se WARN (cron + monitoring)}
        {--json : Output JSON estruturado em vez de tabela}';

    protected $description = 'Health check Auditoria — 4 sinais (ADR 0155 D9.c, Wave 17).';

    public function handle(): int
    {
        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');

        $checks = [
            $this->checkActivityLogTable(),
            $this->checkActivity24h(),
            $this->checkRevertColumns(),
            $this->checkRevertRate7d(),
        ];

        $summary = [
            'ok'    => collect($checks)->filter(fn ($c) => $c['status'] === 'OK')->count(),
            'warn'  => collect($checks)->filter(fn ($c) => $c['status'] === 'WARN')->count(),
            'fail'  => collect($checks)->filter(fn ($c) => $c['status'] === 'FAIL')->count(),
            'total' => count($checks),
        ];

        if ($asJson) {
            $this->line(json_encode([
                'timestamp' => now()->toIso8601String(),
                'module'    => 'Auditoria',
                'checks'    => $checks,
                'summary'   => $summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $this->resolveExitCode($summary, $alert);
        }

        $this->line('');
        $this->info('Auditoria Health Check — ' . now()->toDateTimeString());
        $this->newLine();

        $rows = collect($checks)->map(fn ($c) => [
            $c['name'],
            $c['status'],
            mb_strimwidth((string) $c['details'], 0, 80, '…'),
            mb_strimwidth((string) $c['recommendation'], 0, 80, '…'),
        ])->toArray();

        $this->table(['Check', 'Status', 'Details', 'Recommendation'], $rows);
        $this->newLine();
        $line = "{$summary['ok']} OK, {$summary['warn']} WARN, {$summary['fail']} FAIL de {$summary['total']} checks";
        $summary['fail'] > 0 ? $this->error("  Resumo: {$line}")
            : ($summary['warn'] > 0 ? $this->warn("  Resumo: {$line}") : $this->info("  Resumo: {$line}"));

        return $this->resolveExitCode($summary, $alert);
    }

    private function checkActivityLogTable(): array
    {
        return Schema::hasTable('activity_log')
            ? $this->mk('activity_log_table_present', 'OK', 'Tabela activity_log presente', 'Spatie/Activitylog schema aplicado.')
            : $this->mk('activity_log_table_present', 'FAIL', 'Tabela activity_log ausente', 'Rode `php artisan migrate` Spatie/Activitylog.');
    }

    private function checkActivity24h(): array
    {
        if (! Schema::hasTable('activity_log')) {
            return $this->mk('activity_24h', 'WARN', 'Tabela ausente', 'Rode migrate.');
        }
        $count = (int) DB::table('activity_log')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($count === 0) {
            return $this->mk('activity_24h', 'WARN', '0 activity em 24h cross-tenant', 'Logs Spatie podem não estar enabled em Models;.');
        }
        return $this->mk('activity_24h', 'OK', "{$count} activity em 24h cross-tenant", 'Append-only audit ativo.');
    }

    private function checkRevertColumns(): array
    {
        if (! Schema::hasTable('activity_log')) {
            return $this->mk('revert_columns_present', 'FAIL', 'activity_log ausente', 'Rode migrate.');
        }
        $cols = ['reverted_at', 'reverted_by_user_id', 'revert_reason'];
        $missing = array_filter($cols, fn ($c) => ! Schema::hasColumn('activity_log', $c));
        if (! empty($missing)) {
            return $this->mk('revert_columns_present', 'FAIL',
                'Colunas revert ausentes: ' . implode(', ', $missing),
                'Rode migration `add_revert_metadata_to_activity_log` (ADR 0127).');
        }
        return $this->mk('revert_columns_present', 'OK', 'Schema revert completo', 'Append-only conceitual ativo.');
    }

    private function checkRevertRate7d(): array
    {
        if (! Schema::hasTable('activity_log')
            || ! Schema::hasColumn('activity_log', 'reverted_at')) {
            return $this->mk('revert_rate_7d', 'WARN', 'Schema parcial', 'Rode migrate completo.');
        }
        $total = (int) DB::table('activity_log')
            ->where('created_at', '>=', now()->subWeek())
            ->count();
        if ($total === 0) {
            return $this->mk('revert_rate_7d', 'WARN', '0 activity em 7d', 'Sem volume para calcular taxa.');
        }
        $reverted = (int) DB::table('activity_log')
            ->where('created_at', '>=', now()->subWeek())
            ->whereNotNull('reverted_at')
            ->count();
        $pct = (int) round(($reverted / max(1, $total)) * 100);
        if ($pct > 15) {
            return $this->mk('revert_rate_7d', 'FAIL', "{$reverted}/{$total} reverts (={$pct}%)", 'Taxa de revert >15% sugere bug recorrente ou UX ruim — investigar reasons.');
        }
        if ($pct > 5) {
            return $this->mk('revert_rate_7d', 'WARN', "{$reverted}/{$total} reverts (={$pct}%)", 'Taxa elevada — monitore evolução.');
        }
        return $this->mk('revert_rate_7d', 'OK', "{$reverted}/{$total} reverts (={$pct}%)", 'Taxa dentro do esperado.');
    }

    private function mk(string $name, string $status, string $details, string $recommendation): array
    {
        return compact('name', 'status', 'details', 'recommendation');
    }

    private function resolveExitCode(array $summary, bool $alert): int
    {
        if (! $alert) return 0;
        if ($summary['fail'] > 0) return 2;
        if ($summary['warn'] > 0) return 1;
        return 0;
    }
}
