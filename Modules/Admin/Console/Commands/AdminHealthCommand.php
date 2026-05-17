<?php

declare(strict_types=1);

namespace Modules\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * admin:health — Health check Admin Center (D9.c — Wave 17 saturação 97%).
 *
 * Sinais mínimos:
 *   1. audit_log_table_present — mcp_admin_audit_log
 *   2. snapshot_jana_present — storage/app/jana-health-snapshot.json fresco (<6h)
 *   3. mcp_memory_documents — tabela presente + linhas
 *   4. admin_actions_24h — atividade no Admin Center últimas 24h
 *
 * Multi-tenant Tier 0 (ADR 0093): Admin Center é Wagner-only CT 100. Read-only.
 *
 * NOTA Tier 0: NUNCA `--verbose` (Symfony reserved — usar `--detail` se precisar).
 *
 * @see memory/decisions/0122-admin-center-ct100.md
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 */
class AdminHealthCommand extends Command
{
    protected $signature = 'admin:health
        {--alert : Exit code 2 se FAIL, 1 se WARN}
        {--json : Output JSON estruturado}';

    protected $description = 'Health check Admin Center — 4 sinais (ADR 0155 D9.c, Wave 17).';

    // NOTE Wave 23: pareado com `admin:export-audit` (Modules\Admin\Console\Commands\ExportAuditCommand)
    // e `Modules\Admin\Services\CentrifugoAdminChannel` (esqueleto canal admin.wagner).

    public function handle(): int
    {
        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');

        $checks = [
            $this->checkAuditLogTable(),
            $this->checkSnapshotFresh(),
            $this->checkMcpMemoryDocs(),
            $this->checkAdminActions24h(),
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
                'module'    => 'Admin',
                'checks'    => $checks,
                'summary'   => $summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $this->resolveExitCode($summary, $alert);
        }

        $this->line('');
        $this->info('Admin Health Check — ' . now()->toDateTimeString());
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

    private function checkAuditLogTable(): array
    {
        return Schema::hasTable('mcp_admin_audit_log')
            ? $this->mk('audit_log_table_present', 'OK', 'mcp_admin_audit_log presente', 'Schema canônico aplicado.')
            : $this->mk('audit_log_table_present', 'FAIL', 'mcp_admin_audit_log ausente', 'Rode `php artisan migrate` em Modules/Admin.');
    }

    private function checkSnapshotFresh(): array
    {
        $path = 'jana-health-snapshot.json';
        if (! Storage::disk('local')->exists($path)) {
            return $this->mk('snapshot_jana_present', 'FAIL',
                'jana-health-snapshot.json ausente',
                'Rode `php artisan jana:health-check --json > storage/app/jana-health-snapshot.json`.');
        }
        try {
            $lastMod = Storage::disk('local')->lastModified($path);
            $ageMin = (int) round((time() - $lastMod) / 60);
            if ($ageMin > 6 * 60) {
                return $this->mk('snapshot_jana_present', 'FAIL', "snapshot {$ageMin} min (>6h)", 'Cron jana:health-check parou.');
            }
            if ($ageMin > 2 * 60) {
                return $this->mk('snapshot_jana_present', 'WARN', "snapshot {$ageMin} min (>2h)", 'Cron pode estar atrasado.');
            }
            return $this->mk('snapshot_jana_present', 'OK', "snapshot {$ageMin} min", 'Snapshot fresco.');
        } catch (\Throwable $e) {
            return $this->mk('snapshot_jana_present', 'WARN', 'Erro ao ler timestamp', 'Verifique permissões em storage/app.');
        }
    }

    private function checkMcpMemoryDocs(): array
    {
        if (! Schema::hasTable('mcp_memory_documents')) {
            return $this->mk('mcp_memory_documents', 'FAIL', 'Tabela mcp_memory_documents ausente', 'Rode migration MCP server.');
        }
        $count = (int) DB::table('mcp_memory_documents')->count();
        if ($count === 0) {
            return $this->mk('mcp_memory_documents', 'WARN', '0 docs sincronizados', 'Rode webhook GitHub sync.');
        }
        return $this->mk('mcp_memory_documents', 'OK', "{$count} docs sincronizados", 'MCP memory ativo.');
    }

    private function checkAdminActions24h(): array
    {
        if (! Schema::hasTable('mcp_admin_audit_log')) {
            return $this->mk('admin_actions_24h', 'WARN', 'Tabela ausente', 'Rode migrate.');
        }
        $count = (int) DB::table('mcp_admin_audit_log')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        return $this->mk('admin_actions_24h', 'OK', "{$count} ações Admin em 24h",
            'Volume baixo é normal (Admin Center Wagner-only).');
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
