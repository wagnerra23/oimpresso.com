<?php

declare(strict_types=1);

namespace Modules\AssetManagement\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * assetmanagement:health — Health check Modules/AssetManagement (Wave 23 D9.c).
 *
 * Sinais mínimos (alinhado ao padrão `spreadsheet:health` e `arquivos:health-check`):
 *   1. assets_table_present
 *   2. allocations_table_present
 *   3. maintenances_table_present
 *   4. assets_active_24h           — assets criados/atualizados nas últimas 24h
 *   5. orphan_allocations          — asset_transactions sem asset pai
 *   6. orphan_maintenances         — asset_maintenances sem asset pai
 *   7. warranties_expired_overdue  — garantias vencidas há mais de 30 dias sem flag (ALERT comercial/SLA)
 *   8. retention_config_present    — Wave 25 D9.c — Config/retention.php existe + entities mapeadas
 *
 * Multi-tenant Tier 0 (ADR 0093): agregação cross-tenant superadmin. Read-only.
 * SEMPRE sem `--verbose` (Symfony reserved — `--detail` se precisar).
 *
 * Uso:
 *   php artisan assetmanagement:health
 *   php artisan assetmanagement:health --json
 *   php artisan assetmanagement:health --alert  # exit 2 FAIL, 1 WARN
 *
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 * @see Modules/Spreadsheet/Console/Commands/SpreadsheetHealthCommand.php (sibling pattern)
 */
class AssetManagementHealthCommand extends Command
{
    protected $signature = 'assetmanagement:health
        {--alert : Exit code 2 se FAIL, 1 se WARN}
        {--json : Output JSON estruturado}';

    protected $description = 'Health check AssetManagement — 8 sinais (ADR 0155 D9.c, Wave 23 + Wave 25).';

    public function handle(): int
    {
        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');

        $checks = [
            $this->checkAssetsTable(),
            $this->checkAllocationsTable(),
            $this->checkMaintenancesTable(),
            $this->checkAssetsActive24h(),
            $this->checkOrphanAllocations(),
            $this->checkOrphanMaintenances(),
            $this->checkWarrantiesExpiredOverdue(),
            $this->checkRetentionConfigPresent(),
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
                'module'    => 'AssetManagement',
                'checks'    => $checks,
                'summary'   => $summary,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $this->resolveExitCode($summary, $alert);
        }

        $this->line('');
        $this->info('AssetManagement Health Check — ' . now()->toDateTimeString());
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

    private function checkAssetsTable(): array
    {
        return Schema::hasTable('assets')
            ? $this->mk('assets_table_present', 'OK', 'Tabela assets presente', 'Schema canônico aplicado.')
            : $this->mk('assets_table_present', 'FAIL', 'Tabela assets ausente', 'Rode `php artisan migrate` em Modules/AssetManagement.');
    }

    private function checkAllocationsTable(): array
    {
        return Schema::hasTable('asset_transactions')
            ? $this->mk('allocations_table_present', 'OK', 'Tabela asset_transactions presente', 'Schema canônico aplicado.')
            : $this->mk('allocations_table_present', 'WARN', 'Tabela asset_transactions ausente', 'Allocations indisponíveis — feature parcial.');
    }

    private function checkMaintenancesTable(): array
    {
        return Schema::hasTable('asset_maintenances')
            ? $this->mk('maintenances_table_present', 'OK', 'Tabela asset_maintenances presente', 'Schema canônico aplicado.')
            : $this->mk('maintenances_table_present', 'WARN', 'Tabela asset_maintenances ausente', 'Manutenções indisponíveis — feature parcial.');
    }

    private function checkAssetsActive24h(): array
    {
        if (! Schema::hasTable('assets')) {
            return $this->mk('assets_active_24h', 'WARN', 'Tabela ausente', 'Rode migrate.');
        }
        $count = (int) DB::table('assets')
            ->where('updated_at', '>=', now()->subDay())
            ->count();
        if ($count === 0) {
            return $this->mk('assets_active_24h', 'WARN', '0 mudanças em 24h cross-tenant', 'Esperado se módulo opcional não usado.');
        }
        return $this->mk('assets_active_24h', 'OK', "{$count} mudanças em 24h", 'Módulo ativo.');
    }

    private function checkOrphanAllocations(): array
    {
        if (! Schema::hasTable('assets') || ! Schema::hasTable('asset_transactions')) {
            return $this->mk('orphan_allocations', 'WARN', 'Schema parcial', 'Rode migrate completo.');
        }
        $orphan = (int) DB::table('asset_transactions as t')
            ->leftJoin('assets as a', 't.asset_id', '=', 'a.id')
            ->whereNull('a.id')
            ->count();
        if ($orphan > 0) {
            return $this->mk('orphan_allocations', 'WARN', "{$orphan} allocations órfãs", 'Limpar via housekeeping.');
        }
        return $this->mk('orphan_allocations', 'OK', '0 allocations órfãs', 'Integridade referencial OK.');
    }

    private function checkOrphanMaintenances(): array
    {
        if (! Schema::hasTable('assets') || ! Schema::hasTable('asset_maintenances')) {
            return $this->mk('orphan_maintenances', 'WARN', 'Schema parcial', 'Rode migrate completo.');
        }
        $orphan = (int) DB::table('asset_maintenances as m')
            ->leftJoin('assets as a', 'm.asset_id', '=', 'a.id')
            ->whereNull('a.id')
            ->count();
        if ($orphan > 0) {
            return $this->mk('orphan_maintenances', 'WARN', "{$orphan} manutenções órfãs", 'Limpar via housekeeping.');
        }
        return $this->mk('orphan_maintenances', 'OK', '0 manutenções órfãs', 'Integridade referencial OK.');
    }

    private function checkWarrantiesExpiredOverdue(): array
    {
        if (! Schema::hasTable('asset_warranties')) {
            return $this->mk('warranties_expired_overdue', 'WARN', 'Tabela ausente', 'Rode migrate.');
        }
        // Garantias vencidas há mais de 30 dias — sinal de SLA esquecido.
        // Não bloqueia (SLA comercial, não compliance), só alerta WARN.
        $vencidas = (int) DB::table('asset_warranties')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now()->subDays(30)->toDateString())
            ->count();
        if ($vencidas > 100) {
            return $this->mk('warranties_expired_overdue', 'WARN', "{$vencidas} garantias vencidas >30d", 'Revisar processo de renovação / arquivamento.');
        }
        return $this->mk('warranties_expired_overdue', 'OK', "{$vencidas} garantias vencidas >30d (dentro do esperado)", 'Saudável.');
    }

    /**
     * Wave 25 D9.c — Verifica se Config/retention.php está presente e mapeia 4 entidades
     * canônicas (am_assets, am_asset_transactions, am_maintenance_logs, am_warranties).
     * Sem retention.php declarado, módulo viola D7.c rubrica governance v3.
     */
    private function checkRetentionConfigPresent(): array
    {
        $configPath = __DIR__ . '/../../Config/retention.php';

        if (! file_exists($configPath)) {
            return $this->mk(
                'retention_config_present',
                'FAIL',
                'Config/retention.php ausente',
                'Criar Config/retention.php declarando entities + strategy (D7.c LGPD).'
            );
        }

        $config = require $configPath;

        $entitiesEsperadas = ['am_assets', 'am_asset_transactions', 'am_maintenance_logs', 'am_warranties'];
        $entitiesDeclaradas = array_keys($config['entities'] ?? []);
        $faltantes = array_diff($entitiesEsperadas, $entitiesDeclaradas);

        if (! empty($faltantes)) {
            return $this->mk(
                'retention_config_present',
                'WARN',
                'retention.php existe mas faltam entities: ' . implode(', ', $faltantes),
                'Adicionar entities faltantes em Config/retention.php.'
            );
        }

        return $this->mk(
            'retention_config_present',
            'OK',
            count($entitiesDeclaradas) . ' entities mapeadas + strategy=' . ($config['strategy'] ?? 'n/a'),
            'D7.c compliance OK.'
        );
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
