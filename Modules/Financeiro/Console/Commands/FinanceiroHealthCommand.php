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
            // Wave 23 D6 saturation:
            $this->checkOrphanBaixas($businessId),
            $this->checkValorAbertoConsistente($businessId),
            // Wave 25 D9 polish:
            $this->checkContasBancariasAtivas($businessId),
            $this->checkCaixaMovimentoFreshness($businessId),
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

    /**
     * Wave 23 D6 Check 7: baixas órfãs sem titulo (FK violation drift).
     */
    private function checkOrphanBaixas(?int $businessId): array
    {
        if (! Schema::hasTable('fin_titulo_baixas') || ! Schema::hasTable('fin_titulos')) {
            return $this->makeCheck('orphan_baixas', 'WARN', null, '0', 'Tabelas ausentes', 'Rode migrate.');
        }

        $q = DB::table('fin_titulo_baixas')
            ->leftJoin('fin_titulos', 'fin_titulo_baixas.titulo_id', '=', 'fin_titulos.id')
            ->whereNull('fin_titulos.id');

        if ($businessId !== null) {
            $q->where('fin_titulo_baixas.business_id', $businessId);
        }

        $orphans = $q->count();

        if ($orphans > 0) {
            return $this->makeCheck(
                'orphan_baixas',
                'WARN',
                $orphans,
                '0',
                "{$orphans} baixa(s) sem titulo correspondente",
                'Investigue: forceDelete de titulo deixou baixa órfã? Restaurar via soft delete ou purgar via comando dedicado.'
            );
        }

        return $this->makeCheck('orphan_baixas', 'OK', 0, '0', 'Sem baixas órfãs', 'FK integrity OK.');
    }

    /**
     * Wave 23 D6 Check 8: valor_aberto consistente com soma de baixas
     * (sanity check contra drift de cálculo).
     */
    private function checkValorAbertoConsistente(?int $businessId): array
    {
        if (! Schema::hasTable('fin_titulo_baixas') || ! Schema::hasTable('fin_titulos')) {
            return $this->makeCheck('valor_aberto_consistente', 'WARN', null, '0', 'Tabelas ausentes', 'Rode migrate.');
        }

        // Subquery: soma de baixas por titulo
        $q = DB::table('fin_titulos as t')
            ->leftJoinSub(
                DB::table('fin_titulo_baixas')
                    ->selectRaw('titulo_id, SUM(valor) as total_baixado')
                    ->groupBy('titulo_id'),
                'b',
                't.id', '=', 'b.titulo_id'
            )
            ->whereNull('t.deleted_at')
            ->whereRaw('ABS(t.valor_total - COALESCE(b.total_baixado, 0) - t.valor_aberto) > 0.01');

        if ($businessId !== null) {
            $q->where('t.business_id', $businessId);
        }

        $inconsistentes = $q->count();

        if ($inconsistentes > 0) {
            return $this->makeCheck(
                'valor_aberto_consistente',
                'WARN',
                $inconsistentes,
                '0',
                "{$inconsistentes} titulo(s) com valor_aberto ≠ (valor_total - SUM(baixas))",
                'Rode `financeiro:recalc-valor-aberto` (se existir) ou investigue caso a caso.'
            );
        }

        return $this->makeCheck('valor_aberto_consistente', 'OK', 0, '0', 'Saldos consistentes', 'Aritmética OK.');
    }

    /**
     * Wave 25 D9 Check 9: contas bancárias ativas cadastradas (pré-condição UnificadoService).
     */
    private function checkContasBancariasAtivas(?int $businessId): array
    {
        if (! Schema::hasTable('fin_contas_bancarias')) {
            return $this->makeCheck('contas_bancarias_ativas', 'WARN', null, '>=1', 'fin_contas_bancarias ausente', 'Rode migrate.');
        }

        $q = DB::table('fin_contas_bancarias')->whereNull('deleted_at');
        if ($businessId !== null) {
            $q->where('business_id', $businessId);
        }

        $count = $q->count();

        if ($count === 0) {
            return $this->makeCheck(
                'contas_bancarias_ativas',
                'WARN',
                0,
                '>=1',
                'Nenhuma conta bancária cadastrada',
                'Cadastre conta em /financeiro/contas-bancarias — KPI saldo_bancario depende disso.'
            );
        }

        return $this->makeCheck('contas_bancarias_ativas', 'OK', $count, '>=1', "{$count} conta(s) cadastrada(s)", 'KPI saldo_bancario operacional.');
    }

    /**
     * Wave 25 D9 Check 10: caixa_movimento freshness (último lançamento ≤ 7 dias).
     */
    private function checkCaixaMovimentoFreshness(?int $businessId): array
    {
        if (! Schema::hasTable('fin_caixa_movimentos')) {
            return $this->makeCheck('caixa_movimento_freshness', 'WARN', null, '<=7d', 'fin_caixa_movimentos ausente', 'Rode migrate.');
        }

        $q = DB::table('fin_caixa_movimentos')->whereNull('deleted_at');
        if ($businessId !== null) {
            $q->where('business_id', $businessId);
        }

        $count = (clone $q)->count();

        if ($count === 0) {
            return $this->makeCheck(
                'caixa_movimento_freshness',
                'WARN',
                0,
                '>=1',
                'Nenhum lançamento de caixa registrado',
                'Pré-uso: lançamentos surgem ao baixar título ou registrar manual.'
            );
        }

        $lastDate = (clone $q)->max('created_at');
        $daysSince = $lastDate ? now()->diffInDays($lastDate) : 999;

        if ($daysSince > 7) {
            return $this->makeCheck(
                'caixa_movimento_freshness',
                'WARN',
                $daysSince,
                '<=7d',
                "Último lançamento há {$daysSince} dia(s) — fluxo parado?",
                'Revise se operação financeira está ativa ou se há job/processo travado.'
            );
        }

        return $this->makeCheck('caixa_movimento_freshness', 'OK', $daysSince, '<=7d', "Último lançamento há {$daysSince} dia(s)", 'Operação ativa.');
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
