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
 *   1. licencas_table_present — licenca_computador (FAIL se ausente)
 *   2. licenca_logs_table_present — licenca_log, audit Delphi (FAIL se ausente)
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

    /**
     * Nomes reais das tabelas — os mesmos de Entities/Licenca_Computador e
     * Entities/LicencaLog. Até 2026-11-18 o comando procurava `licenca_computadores` e
     * `licenca_logs` (plural, que nunca existiram), então todo check saía WARN com as
     * tabelas presentes e nunca chegava a FAIL com elas ausentes.
     */
    private const TABELA_LICENCAS = 'licenca_computador';

    private const TABELA_LOG = 'licenca_log';

    private function checkLicencasTable(): array
    {
        $t = self::TABELA_LICENCAS;

        return Schema::hasTable($t)
            ? $this->mk('licencas_table_present', 'OK', "Tabela {$t} presente", 'Schema canônico aplicado.')
            : $this->mk('licencas_table_present', 'FAIL', "Tabela {$t} ausente", 'Rode as migrations do Officeimpresso.');
    }

    private function checkLogsTable(): array
    {
        $t = self::TABELA_LOG;

        return Schema::hasTable($t)
            ? $this->mk('licenca_logs_table_present', 'OK', "Tabela {$t} presente", 'Audit Delphi ativo.')
            : $this->mk('licenca_logs_table_present', 'FAIL', "Tabela {$t} ausente", 'Rode as migrations do Officeimpresso.');
    }

    private function checkDesktopPings24h(): array
    {
        $t = self::TABELA_LOG;
        if (! Schema::hasTable($t)) {
            return $this->mk('desktop_pings_24h', 'FAIL', "Tabela {$t} ausente", 'Rode as migrations do Officeimpresso.');
        }
        $count = (int) DB::table($t)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($count === 0) {
            return $this->mk('desktop_pings_24h', 'WARN', '0 pings desktop em 24h cross-tenant', 'Esperado se nenhum cliente desktop ativo.');
        }

        return $this->mk('desktop_pings_24h', 'OK', "{$count} pings desktop em 24h", 'Desktop legacy ativo.');
    }

    private function checkBloqueadasCount(): array
    {
        $t = self::TABELA_LICENCAS;
        if (! Schema::hasTable($t)) {
            return $this->mk('bloqueadas_count', 'FAIL', "Tabela {$t} ausente", 'Rode as migrations do Officeimpresso.');
        }
        if (! Schema::hasColumn($t, 'bloqueado')) {
            return $this->mk('bloqueadas_count', 'FAIL', "Coluna {$t}.bloqueado ausente", 'Schema parcial — rode as migrations.');
        }
        $bloq = (int) DB::table($t)->where('bloqueado', 1)->count();
        $total = (int) DB::table($t)->count();

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
