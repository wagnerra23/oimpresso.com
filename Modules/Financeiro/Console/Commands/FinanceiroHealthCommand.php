<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * financeiro:health — Health check do Modules/Financeiro (Wave 17 D9.c — governance v3).
 *
 * Dashboard de saúde do módulo:
 *
 *   1. titulos_table          — fin_titulos presente
 *   2. baixas_table           — fin_titulo_baixas presente
 *   3. caixa_table            — fin_caixa_movimentos presente
 *   4. titulos_per_business   — businesses com titulos abertos
 *   5. vencidos_alarme        — titulos receber vencidos > 30d sem baixa
 *   6. retention_policy       — config retention.php presente
 *
 * Multi-tenant Tier 0 (ADR 0093): command CLI sem session.
 *   - Sem --business: admin global (cross-tenant via withoutGlobalScopes)
 *   - Com --business: filtra explicitamente
 *
 * Read-only — NUNCA INSERT/UPDATE/DELETE.
 *
 * Pattern: irmão de RecurringHealthCommand (ADR 0155 D9.c). Convenção
 * `--detail` (NÃO `--verbose` — Symfony reserved word, ver
 * .claude/rules/commands.md handoff 2026-05-14 PR #851).
 *
 * Exit code:
 *   - Sem --alert: sempre 0
 *   - Com --alert: 2 FAIL, 1 WARN, 0 OK
 *
 * Uso:
 *   php artisan financeiro:health
 *   php artisan financeiro:health --business=1
 *   php artisan financeiro:health --json
 *   php artisan financeiro:health --alert
 *   php artisan financeiro:health --detail
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules/RecurringBilling/Console/Commands/RecurringHealthCommand.php (pattern irmão)
 */
class FinanceiroHealthCommand extends Command
{
    protected $signature = 'financeiro:health
        {--business= : Filtra por business_id (default: todos)}
        {--alert : Exit code 2 FAIL, 1 WARN (cron + monitoring)}
        {--json : Output JSON estruturado}
        {--detail : Log detalhado por check (NUNCA --verbose: Symfony reserved)}';

    protected $description = 'Health check Modules/Financeiro — 6 sinais críticos (Wave 17 D9.c).';

    /** Dias maximo de vencido pra titulo a receber antes de WARN. */
    private const VENCIDO_WARN_DAYS = 30;

    public function handle(): int
    {
        $businessId = $this->option('business') !== null
            ? (int) $this->option('business')
            : null;

        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');
        $detail = (bool) $this->option('detail');

        $checks = [
            $this->checkTitulosTable(),
            $this->checkBaixasTable(),
            $this->checkCaixaTable(),
            $this->checkTitulosPerBusiness($businessId),
            $this->checkVencidosAlarme($businessId),
            $this->checkRetentionPolicy(),
        ];

        if ($detail && ! $asJson) {
            foreach ($checks as $c) {
                $this->line("  [{$c['status']}] {$c['name']}: {$c['details']}");
            }
        }

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

    private function checkTitulosTable(): array
    {
        if (! Schema::hasTable('fin_titulos')) {
            return $this->makeCheck('titulos_table', 'FAIL', 0, '1', 'fin_titulos ausente', 'Rode `module:migrate Financeiro`.');
        }
        return $this->makeCheck('titulos_table', 'OK', 1, '1', 'fin_titulos presente', 'Schema OK.');
    }

    private function checkBaixasTable(): array
    {
        if (! Schema::hasTable('fin_titulo_baixas')) {
            return $this->makeCheck('baixas_table', 'FAIL', 0, '1', 'fin_titulo_baixas ausente', 'Rode `module:migrate Financeiro`.');
        }
        return $this->makeCheck('baixas_table', 'OK', 1, '1', 'fin_titulo_baixas presente', 'Schema OK.');
    }

    private function checkCaixaTable(): array
    {
        if (! Schema::hasTable('fin_caixa_movimentos')) {
            return $this->makeCheck('caixa_table', 'FAIL', 0, '1', 'fin_caixa_movimentos ausente', 'Rode `module:migrate Financeiro`.');
        }
        return $this->makeCheck('caixa_table', 'OK', 1, '1', 'fin_caixa_movimentos presente', 'Schema OK.');
    }

    /**
     * Check 4: businesses ativos com titulos cadastrados.
     */
    private function checkTitulosPerBusiness(?int $businessId): array
    {
        if (! Schema::hasTable('fin_titulos')) {
            return $this->makeCheck('titulos_per_business', 'WARN', null, '>=1', 'Tabela ausente', 'Rode migrate.');
        }

        $query = DB::table('fin_titulos')->whereNull('deleted_at');
        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $count = (clone $query)->count();
        $distintos = (clone $query)->distinct('business_id')->count('business_id');

        if ($count === 0 && $businessId !== null) {
            return $this->makeCheck(
                'titulos_per_business',
                'WARN',
                0,
                '>=1',
                "business_id={$businessId} sem titulos cadastrados",
                'Modulo Financeiro instalado mas sem uso. Sells/Repair criam titulos via TituloAutoService.'
            );
        }

        if ($count === 0) {
            return $this->makeCheck(
                'titulos_per_business',
                'WARN',
                0,
                '>=1',
                'Nenhum titulo no sistema',
                'Pre-uso: titulos surgem ao finalizar venda em Sells ou OS em Repair.'
            );
        }

        return $this->makeCheck(
            'titulos_per_business',
            'OK',
            $count,
            '>=1',
            "{$count} titulo(s) em {$distintos} business(es)",
            'Modulo em uso.'
        );
    }

    /**
     * Check 5: titulos a receber vencidos ha mais de N dias sem baixa.
     */
    private function checkVencidosAlarme(?int $businessId): array
    {
        if (! Schema::hasTable('fin_titulos')) {
            return $this->makeCheck('vencidos_alarme', 'WARN', null, '<' . self::VENCIDO_WARN_DAYS . 'd', 'Tabela ausente', 'Rode migrate.');
        }

        $cutoff = now()->subDays(self::VENCIDO_WARN_DAYS)->toDateString();

        $query = DB::table('fin_titulos')
            ->where('tipo', 'receber')
            ->whereIn('status', ['aberto', 'parcial'])
            ->where('vencimento', '<', $cutoff)
            ->whereNull('deleted_at');

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $vencidosAntigos = (clone $query)->count();
        $valorVencido = (float) (clone $query)->sum('valor_aberto');

        if ($vencidosAntigos > 0) {
            return $this->makeCheck(
                'vencidos_alarme',
                'WARN',
                $vencidosAntigos,
                '0',
                "{$vencidosAntigos} titulo(s) receber vencido(s) > " . self::VENCIDO_WARN_DAYS . "d (R$ " . number_format($valorVencido, 2, ',', '.') . " em aberto)",
                'Revise lista em /financeiro?status=aberto&tipo=receber e acione cobrança ou registre baixa/cancelamento.'
            );
        }

        return $this->makeCheck(
            'vencidos_alarme',
            'OK',
            0,
            '0',
            'Nenhum titulo a receber vencido ha mais de ' . self::VENCIDO_WARN_DAYS . ' dias',
            'Carteira em dia.'
        );
    }

    /**
     * Check 6: config retention.php presente + enabled flag conhecido.
     */
    private function checkRetentionPolicy(): array
    {
        $configPath = module_path('Financeiro', 'Config/retention.php');

        if (! file_exists($configPath)) {
            return $this->makeCheck(
                'retention_policy',
                'WARN',
                0,
                '1',
                'Modules/Financeiro/Config/retention.php ausente',
                'Crie config retention.php (Wave 14 D7.c) — declaração canônica LGPD/CTN.'
            );
        }

        $enabled = config('financeiro.retention.enabled', false);

        return $this->makeCheck(
            'retention_policy',
            'OK',
            1,
            '1',
            'Config presente — purge ' . ($enabled ? 'ENABLED' : 'declarado mas não-ativo (default backlog ADR 0105)'),
            $enabled
                ? 'Job financeiro:purge-expired pode rodar — verifique cron.'
                : 'Declaração canônica OK. Ativar via FINANCEIRO_RETENTION_ENABLED=true quando job estiver implementado.'
        );
    }

    private function outputTable(array $checks, array $summary, ?int $businessId, bool $alert): int
    {
        $bizLabel = $businessId !== null ? "business_id={$businessId}" : 'todos businesses (admin)';
        $this->line('');
        $this->info('Financeiro Health Check — ' . now()->toDateTimeString());
        $this->line("   Filtro: {$bizLabel}");
        $this->newLine();

        $headers = ['Check', 'Status', 'Details', 'Recommendation'];
        $tableRows = collect($checks)->map(function (array $check) {
            return [
                $check['name'],
                $check['status'],
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

    /**
     * @return array{name: string, status: string, value: mixed, threshold: string, details: string, recommendation: string}
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
