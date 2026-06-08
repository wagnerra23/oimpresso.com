<?php

declare(strict_types=1);

namespace Modules\Vestuario\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Vestuario\Entities\VestuarioSetting;

/**
 * vestuario:health — Health check do vertical Vestuario (D9.c — ADR 0155 module-grade-v3).
 *
 * Dashboard de saúde do módulo vertical (CNAE 4781-4/00). Equivalente leve do
 * arquivos:health-check / jana:health-check; foca nos sinais críticos do vertical:
 *
 *   1. settings_table_present — vestuario_settings existe (Sprint 2 migration aplicada)
 *   2. settings_per_business  — quantos businesses têm settings cadastrado
 *   3. grade_capabilities     — products/variations table presentes (core UltimatePOS)
 *
 * Multi-tenant Tier 0 (ADR 0093): command CLI sem session.
 *   - Sem --business: admin global view
 *   - Com --business: filtra explicitamente um business
 *
 * Read-only — NUNCA INSERT/UPDATE/DELETE.
 *
 * Exit code:
 *   - Sem --alert: sempre 0 (info-only)
 *   - Com --alert: 2 se FAIL, 1 se WARN, 0 se OK
 *
 * Uso:
 *   php artisan vestuario:health
 *   php artisan vestuario:health --business=1
 *   php artisan vestuario:health --json
 *   php artisan vestuario:health --alert
 *
 * NOTA Tier 0: NUNCA usar biz=4 (ROTA LIVRE prod) em testes deste command.
 * Tests biz=1 (ADR 0101).
 *
 * @see memory/decisions/0121-oimpresso-modular-especializado-por-vertical.md §P7
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 */
class VestuarioHealthCommand extends Command
{
    protected $signature = 'vestuario:health
        {--business= : Filtra por business_id (default: todos)}
        {--alert : Exit code 2 se FAIL, 1 se WARN (cron + monitoring)}
        {--json : Output JSON estruturado em vez de tabela}';

    protected $description = 'Health check do vertical Vestuario — 3 sinais (ADR 0155 D9.c).';

    public function handle(): int
    {
        $businessId = $this->option('business') !== null
            ? (int) $this->option('business')
            : null;

        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');

        $checks = [
            $this->checkSettingsTable(),
            $this->checkSettingsPerBusiness($businessId),
            $this->checkGradeCapabilities(),
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

    /**
     * Check 1: vestuario_settings table presente (migration Sprint 2 aplicada).
     */
    private function checkSettingsTable(): array
    {
        if (! Schema::hasTable('vestuario_settings')) {
            return $this->makeCheck(
                'settings_table_present',
                'FAIL',
                0,
                '1',
                'Tabela vestuario_settings ausente',
                'Rode `php artisan module:migrate Vestuario` — Sprint 2 ADR 0121 §P7.'
            );
        }

        return $this->makeCheck(
            'settings_table_present',
            'OK',
            1,
            '1',
            'Tabela vestuario_settings presente',
            'Schema canônico Sprint 2 aplicado.'
        );
    }

    /**
     * Check 2: quantos businesses têm settings cadastrado.
     *
     * Tier 0 — não retorna dados de business reais (só count).
     */
    private function checkSettingsPerBusiness(?int $businessId): array
    {
        if (! Schema::hasTable('vestuario_settings')) {
            return $this->makeCheck('settings_per_business', 'WARN', null, '>=1', 'Tabela ausente', 'Rode migrate.');
        }

        $query = VestuarioSetting::query()
            ->withoutGlobalScopes(['business_id']); // SUPERADMIN: health-check admin view

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $count = $query->count();

        if ($count === 0 && $businessId !== null) {
            return $this->makeCheck(
                'settings_per_business',
                'WARN',
                0,
                '>=1',
                "Business_id={$businessId} sem settings cadastrado",
                'Setup inicial pendente; cliente novo deve receber default via VestuarioSettingsCommand.'
            );
        }

        return $this->makeCheck(
            'settings_per_business',
            'OK',
            $count,
            '>=0',
            "{$count} business(es) com vestuario_settings cadastrado",
            'OK — vertical setup ok.'
        );
    }

    /**
     * Check 3: products / variations / variation_location_details tables presentes
     * (core UltimatePOS necessárias pra grade avançada Vestuario tamanho × cor).
     */
    private function checkGradeCapabilities(): array
    {
        $required = ['products', 'variations', 'variation_location_details'];
        $missing  = [];

        foreach ($required as $table) {
            if (! Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }

        if (! empty($missing)) {
            return $this->makeCheck(
                'grade_capabilities',
                'FAIL',
                count($missing),
                '0',
                'Tabelas UltimatePOS ausentes: ' . implode(', ', $missing),
                'Grade avançada (matriz tamanho × cor) requer schema UltimatePOS core. Rode `php artisan migrate`.'
            );
        }

        // Sample count rápido sem business filter (admin-only — só sanity)
        $produtosVariaveis = DB::table('products')
            ->where('type', 'variable')
            ->limit(1)
            ->count();

        return $this->makeCheck(
            'grade_capabilities',
            'OK',
            $produtosVariaveis,
            '>=0',
            'Schema grade avançada OK (products/variations/variation_location_details presentes)',
            'Core UltimatePOS pronto pra matriz tamanho × cor.'
        );
    }

    /**
     * Saída em tabela (default).
     */
    private function outputTable(array $checks, array $summary, ?int $businessId, bool $alert): int
    {
        $bizLabel = $businessId !== null ? "business_id={$businessId}" : 'todos businesses (admin)';
        $this->line('');
        $this->info('Vestuario Health Check — ' . now()->toDateTimeString());
        $this->line("   Filtro: {$bizLabel}");
        $this->newLine();

        $headers = ['Check', 'Status', 'Details', 'Recommendation'];
        $tableRows = collect($checks)->map(function (array $check) {
            $statusIcon = match ($check['status']) {
                'OK'   => 'OK',
                'WARN' => 'WARN',
                'FAIL' => 'FAIL',
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
     * Saída em JSON.
     */
    private function outputJson(array $checks, array $summary, ?int $businessId, bool $alert): int
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
        return $this->resolveExitCode($summary, $alert);
    }

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
