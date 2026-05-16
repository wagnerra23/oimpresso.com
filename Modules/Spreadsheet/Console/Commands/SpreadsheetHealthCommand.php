<?php

declare(strict_types=1);

namespace Modules\Spreadsheet\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * spreadsheet:health — Health check Spreadsheet (D9.c — Wave 17 saturação 97%).
 *
 * Sinais mínimos:
 *   1. spreadsheets_table_present
 *   2. shares_table_present
 *   3. spreadsheets_active_24h — edições nas últimas 24h
 *   4. orphan_shares — shares apontando pra spreadsheet inexistente
 *
 * Multi-tenant Tier 0 (ADR 0093): agregação cross-tenant superadmin. Read-only.
 *
 * NOTA Tier 0: NUNCA `--verbose` (Symfony reserved — usar `--detail` se precisar).
 *
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 */
class SpreadsheetHealthCommand extends Command
{
    protected $signature = 'spreadsheet:health
        {--alert : Exit code 2 se FAIL, 1 se WARN}
        {--json : Output JSON estruturado}';

    protected $description = 'Health check Spreadsheet — 4 sinais (ADR 0155 D9.c, Wave 17).';

    public function handle(): int
    {
        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');

        $checks = [
            $this->checkSpreadsheetsTable(),
            $this->checkSharesTable(),
            $this->checkActive24h(),
            $this->checkOrphanShares(),
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
                'module'    => 'Spreadsheet',
                'checks'    => $checks,
                'summary'   => $summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $this->resolveExitCode($summary, $alert);
        }

        $this->line('');
        $this->info('Spreadsheet Health Check — ' . now()->toDateTimeString());
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

    private function checkSpreadsheetsTable(): array
    {
        return Schema::hasTable('spreadsheets')
            ? $this->mk('spreadsheets_table_present', 'OK', 'Tabela spreadsheets presente', 'Schema canônico aplicado.')
            : $this->mk('spreadsheets_table_present', 'FAIL', 'Tabela spreadsheets ausente', 'Rode `php artisan migrate` em Modules/Spreadsheet.');
    }

    private function checkSharesTable(): array
    {
        return Schema::hasTable('spreadsheet_shares')
            ? $this->mk('shares_table_present', 'OK', 'Tabela spreadsheet_shares presente', 'Schema canônico aplicado.')
            : $this->mk('shares_table_present', 'WARN', 'Tabela spreadsheet_shares ausente', 'Sem compartilhamento — feature parcial.');
    }

    private function checkActive24h(): array
    {
        if (! Schema::hasTable('spreadsheets')) {
            return $this->mk('spreadsheets_active_24h', 'WARN', 'Tabela ausente', 'Rode migrate.');
        }
        $count = (int) DB::table('spreadsheets')
            ->where('updated_at', '>=', now()->subDay())
            ->count();
        if ($count === 0) {
            return $this->mk('spreadsheets_active_24h', 'WARN', '0 edições em 24h cross-tenant', 'Esperado se módulo opcional não usado.');
        }
        return $this->mk('spreadsheets_active_24h', 'OK', "{$count} edições em 24h", 'Módulo ativo.');
    }

    private function checkOrphanShares(): array
    {
        if (! Schema::hasTable('spreadsheets') || ! Schema::hasTable('spreadsheet_shares')) {
            return $this->mk('orphan_shares', 'WARN', 'Schema parcial', 'Rode migrate completo.');
        }
        $orphan = (int) DB::table('spreadsheet_shares as s')
            ->leftJoin('spreadsheets as sh', 's.spreadsheet_id', '=', 'sh.id')
            ->whereNull('sh.id')
            ->count();
        if ($orphan > 0) {
            return $this->mk('orphan_shares', 'WARN', "{$orphan} shares órfãos", 'Limpar via job de housekeeping.');
        }
        return $this->mk('orphan_shares', 'OK', '0 shares órfãos', 'Integridade referencial OK.');
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
