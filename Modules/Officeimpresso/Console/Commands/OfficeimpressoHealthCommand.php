<?php

declare(strict_types=1);

namespace Modules\Officeimpresso\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * officeimpresso:health — Health check Officeimpresso desktop legacy (D9.c — Wave 17).
 *
 * Sinais mínimos:
 *   1. licencas_table_present — Licenca_Computador
 *   2. licenca_logs_table_present — LicencaLog (audit Delphi)
 *   3. desktop_pings_24h — pings desktop Delphi últimas 24h
 *   4. bloqueadas_count — licenças bloqueadas (visibilidade)
 *
 * Multi-tenant Tier 0 (ADR 0093): agregação cross-tenant superadmin. Read-only.
 *
 * NOTA Tier 0: NUNCA `--verbose` (Symfony reserved — usar `--detail` se precisar).
 *
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 */
class OfficeimpressoHealthCommand extends Command
{
    protected $signature = 'officeimpresso:health
        {--alert : Exit code 2 se FAIL, 1 se WARN}
        {--json : Output JSON estruturado}';

    protected $description = 'Health check Officeimpresso desktop legacy — 4 sinais (ADR 0155 D9.c).';

    public function handle(): int
    {
        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');

        $checks = [
            $this->checkLicencasTable(),
            $this->checkLogsTable(),
            $this->checkDesktopPings24h(),
            $this->checkBloqueadasCount(),
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
                'module'    => 'Officeimpresso',
                'checks'    => $checks,
                'summary'   => $summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $this->resolveExitCode($summary, $alert);
        }

        $this->line('');
        $this->info('Officeimpresso Health Check — ' . now()->toDateTimeString());
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

    private function checkLicencasTable(): array
    {
        return Schema::hasTable('licenca_computadores')
            ? $this->mk('licencas_table_present', 'OK', 'Tabela licenca_computadores presente', 'Schema canônico aplicado.')
            : $this->mk('licencas_table_present', 'WARN',
                'Tabela licenca_computadores ausente (talvez nome legacy diferente)',
                'Confira Entities/Licenca_Computador::getTable().');
    }

    private function checkLogsTable(): array
    {
        $candidates = ['licenca_logs', 'officeimpresso_licenca_logs'];
        foreach ($candidates as $t) {
            if (Schema::hasTable($t)) {
                return $this->mk('licenca_logs_table_present', 'OK', "Tabela {$t} presente", 'Audit Delphi ativo.');
            }
        }
        return $this->mk('licenca_logs_table_present', 'WARN',
            'Nenhuma tabela de log encontrada',
            'Confira Entities/LicencaLog::getTable().');
    }

    private function checkDesktopPings24h(): array
    {
        $candidates = ['licenca_logs', 'officeimpresso_licenca_logs'];
        $table = null;
        foreach ($candidates as $t) {
            if (Schema::hasTable($t)) { $table = $t; break; }
        }
        if ($table === null) {
            return $this->mk('desktop_pings_24h', 'WARN', 'Tabela de log ausente', 'Confira migrations.');
        }
        $count = (int) DB::table($table)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($count === 0) {
            return $this->mk('desktop_pings_24h', 'WARN', '0 pings desktop em 24h cross-tenant', 'Esperado se nenhum cliente desktop ativo.');
        }
        return $this->mk('desktop_pings_24h', 'OK', "{$count} pings desktop em 24h", 'Desktop legacy ativo.');
    }

    private function checkBloqueadasCount(): array
    {
        if (! Schema::hasTable('licenca_computadores')) {
            return $this->mk('bloqueadas_count', 'WARN', 'Tabela ausente', 'Rode migrate.');
        }
        if (! Schema::hasColumn('licenca_computadores', 'bloqueado')) {
            return $this->mk('bloqueadas_count', 'WARN', 'Coluna bloqueado ausente', 'Schema parcial.');
        }
        $bloq = (int) DB::table('licenca_computadores')->where('bloqueado', 1)->count();
        $total = (int) DB::table('licenca_computadores')->count();
        return $this->mk('bloqueadas_count', 'OK',
            "{$bloq}/{$total} licenças bloqueadas",
            'Visibilidade — bloqueios manuais via UI.');
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
