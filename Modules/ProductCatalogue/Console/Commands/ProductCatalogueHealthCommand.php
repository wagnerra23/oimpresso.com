<?php

declare(strict_types=1);

namespace Modules\ProductCatalogue\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * product-catalogue:health — Health check do módulo ProductCatalogue
 * (D9.c — Wave 17 Governance saturação 97%).
 *
 * Equivalente leve do jana:health-check; foca em sinais críticos do catálogo
 * público (QR + e-commerce light):
 *
 *   1. core_tables_present  — products / business_locations / categories presentes
 *   2. catalog_products     — count de products is_inactive=0 (catálogo viável)
 *   3. active_locations     — business_locations sem deleted (rota /catalogue/{biz}/{loc})
 *   4. discounts_24h        — discounts table presente (FK de catálogo)
 *
 * Multi-tenant Tier 0 (ADR 0093): command CLI sem session.
 *   - Sem --business: admin global view (count agregado)
 *   - Com --business: filtra explicitamente um business
 *
 * Read-only — NUNCA INSERT/UPDATE/DELETE.
 *
 * Exit code:
 *   - Sem --alert: sempre 0
 *   - Com --alert: 2 se FAIL, 1 se WARN, 0 se OK
 *
 * Uso:
 *   php artisan product-catalogue:health
 *   php artisan product-catalogue:health --business=4
 *   php artisan product-catalogue:health --json --alert
 *
 * NOTA Tier 0: NUNCA `--verbose` custom (colide com Symfony Console).
 *
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 * @see Modules\Vestuario\Console\Commands\VestuarioHealthCommand (pattern referência)
 */
class ProductCatalogueHealthCommand extends Command
{
    protected $signature = 'product-catalogue:health
        {--business= : Filtra por business_id (default: todos)}
        {--alert : Exit code 2 se FAIL, 1 se WARN (cron + monitoring)}
        {--json : Output JSON estruturado em vez de tabela}';

    protected $description = 'Health check do ProductCatalogue — 4 sinais (ADR 0155 D9.c, Wave 17).';

    public function handle(): int
    {
        $businessId = $this->option('business') !== null ? (int) $this->option('business') : null;
        $asJson     = (bool) $this->option('json');
        $alert      = (bool) $this->option('alert');

        $checks = [
            $this->checkCoreTables(),
            $this->checkCatalogProducts($businessId),
            $this->checkActiveLocations($businessId),
            $this->checkDiscountsTable(),
        ];

        $summary = [
            'ok'    => collect($checks)->filter(fn ($c) => $c['status'] === 'OK')->count(),
            'warn'  => collect($checks)->filter(fn ($c) => $c['status'] === 'WARN')->count(),
            'fail'  => collect($checks)->filter(fn ($c) => $c['status'] === 'FAIL')->count(),
            'total' => count($checks),
        ];

        if ($asJson) {
            return $this->outputJson($checks, $summary, $businessId, $alert);
        }

        return $this->outputTable($checks, $summary, $businessId, $alert);
    }

    private function checkCoreTables(): array
    {
        $required = ['products', 'business_locations', 'categories'];
        $missing  = [];

        foreach ($required as $t) {
            if (! Schema::hasTable($t)) {
                $missing[] = $t;
            }
        }

        if (! empty($missing)) {
            return $this->makeCheck('core_tables_present', 'FAIL', count($missing), '0',
                'Tabelas UltimatePOS ausentes: ' . implode(', ', $missing),
                'Catálogo público depende de products/business_locations/categories. Rode `php artisan migrate`.'
            );
        }

        return $this->makeCheck('core_tables_present', 'OK', 0, '0',
            'Tabelas core UltimatePOS presentes',
            'products + business_locations + categories ok.'
        );
    }

    private function checkCatalogProducts(?int $businessId): array
    {
        if (! Schema::hasTable('products')) {
            return $this->makeCheck('catalog_products', 'WARN', null, '>=1', 'Tabela products ausente', 'Rode migrate.');
        }

        $query = DB::table('products')->where('is_inactive', 0);

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $count = (int) $query->count();

        if ($count === 0 && $businessId !== null) {
            return $this->makeCheck('catalog_products', 'WARN', 0, '>=1',
                "Business_id={$businessId} sem produtos ativos",
                'Cadastre produtos pra catálogo público QR ser viável.'
            );
        }

        return $this->makeCheck('catalog_products', 'OK', $count, '>=0',
            "{$count} produto(s) ativo(s)" . ($businessId !== null ? " no business_id={$businessId}" : ''),
            'Catálogo viável.'
        );
    }

    private function checkActiveLocations(?int $businessId): array
    {
        if (! Schema::hasTable('business_locations')) {
            return $this->makeCheck('active_locations', 'WARN', null, '>=1', 'Tabela ausente', 'Rode migrate.');
        }

        $query = DB::table('business_locations')->whereNull('deleted_at');

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $count = (int) $query->count();

        if ($count === 0 && $businessId !== null) {
            return $this->makeCheck('active_locations', 'FAIL', 0, '>=1',
                "Business_id={$businessId} sem locations ativas",
                'Rota /catalogue/{biz}/{loc} 404 — cadastre business_location.'
            );
        }

        return $this->makeCheck('active_locations', 'OK', $count, '>=1',
            "{$count} business_location(s) ativa(s)",
            'Rota QR pública viável.'
        );
    }

    private function checkDiscountsTable(): array
    {
        if (! Schema::hasTable('discounts')) {
            return $this->makeCheck('discounts_24h', 'WARN', null, '1',
                'Tabela discounts ausente',
                'Catálogo funciona sem discounts, mas formatDiscountAmounts() falha. Rode migrate.'
            );
        }

        return $this->makeCheck('discounts_24h', 'OK', 1, '1',
            'Tabela discounts presente',
            'Catalogue::activeDiscounts() viável.'
        );
    }

    private function outputTable(array $checks, array $summary, ?int $businessId, bool $alert): int
    {
        $bizLabel = $businessId !== null ? "business_id={$businessId}" : 'todos businesses (admin)';
        $this->line('');
        $this->info('ProductCatalogue Health Check — ' . now()->toDateTimeString());
        $this->line("   Filtro: {$bizLabel}");
        $this->newLine();

        $headers = ['Check', 'Status', 'Details', 'Recommendation'];
        $tableRows = collect($checks)->map(fn ($c) => [
            $c['name'],
            $c['status'],
            mb_strimwidth((string) $c['details'], 0, 80, '…'),
            mb_strimwidth((string) $c['recommendation'], 0, 80, '…'),
        ])->toArray();

        $this->table($headers, $tableRows);
        $this->newLine();

        $summaryLine = sprintf('%d OK, %d WARN, %d FAIL de %d checks',
            $summary['ok'], $summary['warn'], $summary['fail'], $summary['total']);

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

    private function outputJson(array $checks, array $summary, ?int $businessId, bool $alert): int
    {
        $output = [
            'timestamp'       => now()->toIso8601String(),
            'business_filter' => $businessId,
            'checks'          => collect($checks)->map(fn ($c) => [
                'name'           => $c['name'],
                'status'         => $c['status'],
                'value'          => $c['value'],
                'threshold'      => $c['threshold'],
                'details'        => $c['details'],
                'recommendation' => $c['recommendation'],
            ])->values()->toArray(),
            'summary' => $summary,
        ];

        $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $this->resolveExitCode($summary, $alert);
    }

    private function makeCheck(string $name, string $status, mixed $value, string $threshold,
        string $details, string $recommendation): array
    {
        return compact('name', 'status', 'value', 'threshold', 'details', 'recommendation');
    }

    private function resolveExitCode(array $summary, bool $alert): int
    {
        if (! $alert) return 0;
        if ($summary['fail'] > 0) return 2;
        if ($summary['warn'] > 0) return 1;
        return 0;
    }
}
