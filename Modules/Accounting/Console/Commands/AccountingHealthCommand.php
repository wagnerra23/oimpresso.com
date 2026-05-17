<?php

declare(strict_types=1);

namespace Modules\Accounting\Console\Commands;

use App\Util\OtelHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * accounting:health — Wave 23 D9.c (governance v3 — observabilidade módulo Accounting).
 *
 * Dashboard de saúde do módulo Accounting WR2. Espelha pattern PontoHealthCommand
 * / ManufacturingHealthCommand / CmsHealthCommand. READ-ONLY (nenhum INSERT/UPDATE/DELETE).
 *
 * Sinais mínimos:
 *   1. schema_canon          — tabelas `accounts`, `account_transactions`, `journal_entries` presentes
 *   2. catalog_global        — `account_types` (catálogo business_id=0) preservado (lição Wave 13/15)
 *   3. lancamentos_24h       — `journal_entries` recentes < 24h (sinal contábil vivo)
 *   4. transactions_orphan   — `account_transactions` sem journal_entry_id (drift legacy)
 *   5. accounts_by_business  — # accounts ativas por business_id (cobertura tenant)
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *   - Sem --business-id: leitura agregada cross-tenant (dashboard superadmin)
 *   - Com --business-id: filtra sinais 3-5 por tenant
 *   - Sinal 2 (catálogo) sempre global por contrato (business_id=0 IRREVOGÁVEL)
 *
 * Exit code:
 *   - Sem --alert: sempre 0 (info-only)
 *   - Com --alert: 2 se FAIL, 1 se WARN, 0 se OK
 *
 * NOTA Tier 0: `--detail` (não `--verbose` — colide Symfony Console).
 *
 * Uso:
 *   php artisan accounting:health
 *   php artisan accounting:health --business-id=1
 *   php artisan accounting:health --detail
 *   php artisan accounting:health --json
 *   php artisan accounting:health --alert
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 * @see Modules\Cms\Console\Commands\CmsHealthCommand (pattern referência)
 */
class AccountingHealthCommand extends Command
{
    protected $signature = 'accounting:health
        {--business-id= : Filtra checks 3-5 por business_id (default: agregado)}
        {--alert : Exit code 2 se FAIL, 1 se WARN, 0 se OK (cron + monitoring)}
        {--json : Output JSON estruturado (integração dashboard)}
        {--detail : Mostra detalhes adicionais por check (debug)}';

    protected $description = 'Dashboard de saúde do módulo Accounting — 5 sinais schema + catálogo global + integridade lançamentos.';

    /** Threshold WARN (horas) — última transação contábil por business. */
    private const LANCAMENTO_LAG_WARN_HORAS = 48;

    /** Threshold FAIL — empresa inativa contabilmente há mais de 7 dias. */
    private const LANCAMENTO_LAG_FAIL_HORAS = 168;

    public function handle(): int
    {
        return OtelHelper::spanBiz('accounting.health.run', function () {
            return $this->runChecks();
        }, ['detail' => (bool) $this->option('detail')]);
    }

    private function runChecks(): int
    {
        $businessId = $this->option('business-id') !== null
            ? (int) $this->option('business-id')
            : null;

        $checks = [
            'schema_canon'          => $this->checkSchemaCanon(),
            'catalog_global'        => $this->checkCatalogGlobal(),
            'lancamentos_24h'       => $this->checkLancamentosRecentes($businessId),
            'transactions_orphan'   => $this->checkTransactionsOrphan($businessId),
            'accounts_by_business'  => $this->checkAccountsByBusiness($businessId),
        ];

        if ($this->option('json')) {
            $this->line(json_encode([
                'module'      => 'Accounting',
                'business_id' => $businessId,
                'checked_at'  => now()->toIso8601String(),
                'checks'      => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable($checks);
        }

        $this->emitLog($businessId, $checks);

        if (! $this->option('alert')) {
            return self::SUCCESS;
        }

        $hasFail = collect($checks)->contains(fn ($c) => ($c['status'] ?? '') === 'FAIL');
        $hasWarn = collect($checks)->contains(fn ($c) => ($c['status'] ?? '') === 'WARN');

        return $hasFail ? 2 : ($hasWarn ? 1 : 0);
    }

    /**
     * Check 1 — schema canônico Accounting presente. Wave 17 saturou 21 Entities;
     * mínimas pra módulo funcionar: accounts + account_transactions + journal_entries.
     */
    private function checkSchemaCanon(): array
    {
        $required = ['accounts', 'account_transactions', 'journal_entries'];
        $missing = [];
        foreach ($required as $t) {
            if (! Schema::hasTable($t)) {
                $missing[] = $t;
            }
        }

        if (empty($missing)) {
            return [
                'status'   => 'OK',
                'valor'    => count($required),
                'mensagem' => count($required) . ' tabelas canônicas presentes.',
            ];
        }

        return [
            'status'   => 'FAIL',
            'valor'    => count($missing),
            'mensagem' => 'Schema drift — ausentes: ' . implode(', ', $missing),
        ];
    }

    /**
     * Check 2 — catálogo global Accounting (`account_types` business_id=0) preservado.
     * Lição Wave 13/15: catálogo NÃO leva business_id global scope — é compartilhado
     * cross-tenant e DEVE permanecer com business_id=0 (IRREVOGÁVEL).
     */
    private function checkCatalogGlobal(): array
    {
        if (! Schema::hasTable('account_types')) {
            return [
                'status'   => 'WARN',
                'valor'    => null,
                'mensagem' => 'Tabela account_types ausente (esperada como catálogo global).',
            ];
        }

        try {
            $total = DB::table('account_types')->count();
            $global = Schema::hasColumn('account_types', 'business_id')
                ? DB::table('account_types')->where('business_id', 0)->orWhereNull('business_id')->count()
                : $total;
        } catch (\Throwable $e) {
            return [
                'status'   => 'WARN',
                'valor'    => null,
                'mensagem' => 'Erro consultando catálogo: ' . substr($e->getMessage(), 0, 80),
            ];
        }

        if ($total === 0) {
            return ['status' => 'WARN', 'valor' => 0, 'mensagem' => 'Catálogo account_types vazio (esperado seed inicial).'];
        }

        // Drift: se catálogo tem linhas com business_id != 0 e != null, virou tenant data.
        $tenantContaminated = $total - $global;
        if ($tenantContaminated > 0) {
            return [
                'status'   => 'WARN',
                'valor'    => $tenantContaminated,
                'mensagem' => "{$tenantContaminated} linha(s) com business_id≠0 (catálogo contaminou com tenant data).",
            ];
        }

        return [
            'status'   => 'OK',
            'valor'    => $global,
            'mensagem' => "{$global} account_types globais (business_id=0) — catálogo íntegro.",
        ];
    }

    /**
     * Check 3 — última transação contábil. Empresa ativa lança ao menos 1x/semana
     * (compras, vendas, despesas). >168h = candidato a inativa.
     */
    private function checkLancamentosRecentes(?int $businessId): array
    {
        if (! Schema::hasTable('journal_entries')) {
            return ['status' => 'WARN', 'valor' => null, 'mensagem' => 'Tabela journal_entries ausente.'];
        }

        $query = DB::table('journal_entries')->whereNotNull('created_at');
        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $last = $query->max('created_at');
        if (! $last) {
            return ['status' => 'OK', 'valor' => 0, 'mensagem' => 'Nenhum lançamento ainda (módulo zerado).'];
        }

        $idadeHoras = Carbon::parse($last)->diffInHours(now());

        $status = $idadeHoras >= self::LANCAMENTO_LAG_FAIL_HORAS
            ? 'FAIL'
            : ($idadeHoras >= self::LANCAMENTO_LAG_WARN_HORAS ? 'WARN' : 'OK');

        $scope = $businessId !== null ? "biz={$businessId}" : 'global';

        return [
            'status'   => $status,
            'valor'    => $idadeHoras,
            'mensagem' => "Último lançamento ({$scope}): há {$idadeHoras}h.",
        ];
    }

    /**
     * Check 4 — `account_transactions` ÓRFÃS (sem journal_entry_id) indicam fluxo
     * legacy AccountTransaction direto (sem entrada contábil real). Drift catalogável.
     */
    private function checkTransactionsOrphan(?int $businessId): array
    {
        if (! Schema::hasTable('account_transactions')) {
            return ['status' => 'OK', 'valor' => 0, 'mensagem' => 'Tabela account_transactions ausente (skip).'];
        }

        if (! Schema::hasColumn('account_transactions', 'journal_entry_id')) {
            return ['status' => 'OK', 'valor' => 0, 'mensagem' => 'Coluna journal_entry_id inexistente (skip).'];
        }

        try {
            $query = DB::table('account_transactions')->whereNull('journal_entry_id');
            if ($businessId !== null && Schema::hasColumn('account_transactions', 'business_id')) {
                $query->where('business_id', $businessId);
            }
            // Sample limitado pra command rápido (não scan full).
            $orphans = $query->limit(10000)->count();
        } catch (\Throwable $e) {
            return [
                'status'   => 'WARN',
                'valor'    => null,
                'mensagem' => 'Erro consultando órfãs: ' . substr($e->getMessage(), 0, 80),
            ];
        }

        $status = $orphans === 0 ? 'OK' : ($orphans <= 100 ? 'WARN' : 'FAIL');

        return [
            'status'   => $status,
            'valor'    => $orphans,
            'mensagem' => "{$orphans} account_transactions órfãs (sem journal_entry_id).",
        ];
    }

    /**
     * Check 5 — cobertura tenant. Conta accounts ativas agregadas por business.
     * Empresa configurada deve ter ≥1 account. Sample top 10 businesses.
     */
    private function checkAccountsByBusiness(?int $businessId): array
    {
        if (! Schema::hasTable('accounts') || ! Schema::hasColumn('accounts', 'business_id')) {
            return ['status' => 'WARN', 'valor' => null, 'mensagem' => 'Tabela accounts/business_id ausente.'];
        }

        $query = DB::table('accounts')->whereNull('deleted_at');
        if ($businessId !== null) {
            $query->where('business_id', $businessId);
            $count = $query->count();

            return [
                'status'   => $count > 0 ? 'OK' : 'WARN',
                'valor'    => $count,
                'mensagem' => "biz={$businessId} tem {$count} accounts ativas.",
            ];
        }

        $rows = $query
            ->select('business_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('business_id')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            return ['status' => 'OK', 'valor' => 0, 'mensagem' => 'Nenhuma account ativa em qualquer business (módulo zerado).'];
        }

        $total = $rows->sum('cnt');

        return [
            'status'   => 'OK',
            'valor'    => $total,
            'mensagem' => "{$rows->count()} business(es) com accounts (top: biz={$rows->first()->business_id} com {$rows->first()->cnt}).",
        ];
    }

    private function renderTable(array $checks): void
    {
        $rows = [];
        foreach ($checks as $name => $c) {
            $rows[] = [
                $name,
                $c['status'] ?? 'UNKNOWN',
                $c['valor'] ?? '-',
                $c['mensagem'] ?? '',
            ];
        }
        $this->table(['Check', 'Status', 'Valor', 'Mensagem'], $rows);

        if ($this->option('detail')) {
            $this->line('');
            $this->line('Detalhes JSON:');
            $this->line(json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    private function emitLog(?int $businessId, array $checks): void
    {
        $worstStatus = 'OK';
        foreach ($checks as $c) {
            if (($c['status'] ?? '') === 'FAIL') { $worstStatus = 'FAIL'; break; }
            if (($c['status'] ?? '') === 'WARN') { $worstStatus = 'WARN'; }
        }

        Log::info('accounting.health.check.executado', [
            'business_id'  => $businessId,
            'worst_status' => $worstStatus,
            'checks'       => array_map(fn ($c) => [
                'status' => $c['status'] ?? null,
                'valor'  => $c['valor'] ?? null,
            ], $checks),
        ]);
    }
}
