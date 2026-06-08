<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * rb:health — Health check do Modules/RecurringBilling (D9.c — ADR 0155 module-grade-v3).
 *
 * Dashboard de saúde do módulo de assinaturas / cobrança recorrente:
 *
 *   1. credentials_table       — rb_boleto_credentials presente
 *   2. invoices_table          — rb_invoices presente
 *   3. subscriptions_table     — rb_subscriptions presente
 *   4. credentials_per_business — quantos businesses têm credencial ativa
 *   5. saldo_cached_freshness  — última sync saldo bancário (alerta se > 24h)
 *
 * Multi-tenant Tier 0 (ADR 0093): command CLI sem session.
 *   - Sem --business: admin global
 *   - Com --business: filtra explicitamente
 *
 * Read-only — NUNCA INSERT/UPDATE/DELETE.
 *
 * Exit code:
 *   - Sem --alert: sempre 0
 *   - Com --alert: 2 FAIL, 1 WARN, 0 OK
 *
 * Uso:
 *   php artisan rb:health
 *   php artisan rb:health --business=1
 *   php artisan rb:health --json
 *   php artisan rb:health --alert
 *
 * @see memory/decisions/0155-module-grade-v3.md D9.c
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules/RecurringBilling/SCOPE.md (US-RB-044 NFe-de-boleto, US-RB-045 sync bank)
 */
class RecurringHealthCommand extends Command
{
    protected $signature = 'rb:health
        {--business= : Filtra por business_id (default: todos)}
        {--alert : Exit code 2 FAIL, 1 WARN (cron + monitoring)}
        {--json : Output JSON estruturado}';

    protected $description = 'Health check Modules/RecurringBilling — 5 sinais críticos (ADR 0155 D9.c).';

    /** Horas sem sync saldo antes de WARN. */
    private const SALDO_LAG_WARN_HOURS = 24;

    public function handle(): int
    {
        $businessId = $this->option('business') !== null
            ? (int) $this->option('business')
            : null;

        $asJson = (bool) $this->option('json');
        $alert  = (bool) $this->option('alert');

        $checks = [
            $this->checkCredentialsTable(),
            $this->checkInvoicesTable(),
            $this->checkSubscriptionsTable(),
            $this->checkCredentialsPerBusiness($businessId),
            $this->checkSaldoCachedFreshness($businessId),
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

    private function checkCredentialsTable(): array
    {
        if (! Schema::hasTable('rb_boleto_credentials')) {
            return $this->makeCheck('credentials_table', 'FAIL', 0, '1', 'rb_boleto_credentials ausente', 'Rode `module:migrate RecurringBilling`.');
        }
        return $this->makeCheck('credentials_table', 'OK', 1, '1', 'rb_boleto_credentials presente', 'Schema OK.');
    }

    private function checkInvoicesTable(): array
    {
        if (! Schema::hasTable('rb_invoices')) {
            return $this->makeCheck('invoices_table', 'FAIL', 0, '1', 'rb_invoices ausente', 'Rode `module:migrate RecurringBilling`.');
        }
        return $this->makeCheck('invoices_table', 'OK', 1, '1', 'rb_invoices presente', 'Schema OK.');
    }

    private function checkSubscriptionsTable(): array
    {
        if (! Schema::hasTable('rb_subscriptions')) {
            return $this->makeCheck('subscriptions_table', 'FAIL', 0, '1', 'rb_subscriptions ausente', 'Rode `module:migrate RecurringBilling`.');
        }
        return $this->makeCheck('subscriptions_table', 'OK', 1, '1', 'rb_subscriptions presente', 'Schema OK.');
    }

    /**
     * Check 4: quantos businesses têm credencial boleto ativa.
     */
    private function checkCredentialsPerBusiness(?int $businessId): array
    {
        if (! Schema::hasTable('rb_boleto_credentials')) {
            return $this->makeCheck('credentials_per_business', 'WARN', null, '>=1', 'Tabela ausente', 'Rode migrate.');
        }

        $query = DB::table('rb_boleto_credentials')->where('ativo', true);
        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $count = (clone $query)->count();
        $distintos = (clone $query)->distinct('business_id')->count('business_id');

        if ($count === 0 && $businessId !== null) {
            return $this->makeCheck(
                'credentials_per_business',
                'WARN',
                0,
                '>=1',
                "business_id={$businessId} sem credencial boleto ativa",
                'Sem credencial, emissão de boleto/cobrança não funciona pra este business.'
            );
        }

        if ($count === 0) {
            return $this->makeCheck(
                'credentials_per_business',
                'WARN',
                0,
                '>=1',
                'Nenhuma credencial boleto ativa no sistema',
                'Cadastre credencial em /recurring-billing/credentials por business.'
            );
        }

        return $this->makeCheck(
            'credentials_per_business',
            'OK',
            $count,
            '>=1',
            "{$count} credencial(is) ativa(s) em {$distintos} business(es)",
            'Credenciais boleto configuradas.'
        );
    }

    /**
     * Check 5: saldo_cached freshness (US-RB-045).
     *
     * Sem sync saldo > 24h sinaliza scheduler/job parado ou credencial bloqueada.
     */
    private function checkSaldoCachedFreshness(?int $businessId): array
    {
        if (! Schema::hasTable('fin_contas_bancarias')) {
            return $this->makeCheck(
                'saldo_cached_freshness',
                'WARN',
                null,
                self::SALDO_LAG_WARN_HOURS . 'h',
                'fin_contas_bancarias ausente — Modules/Financeiro não instalado',
                'Sync saldo bancário requer Modules/Financeiro instalado.'
            );
        }

        $query = DB::table('fin_contas_bancarias')
            ->whereNotNull('rb_gateway_credential_id')
            ->whereNotNull('saldo_atualizado_em');

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $row = $query->select(DB::raw('MAX(saldo_atualizado_em) as ultima_sync'))->first();
        $ultimaSync = $row->ultima_sync ?? null;

        if ($ultimaSync === null) {
            return $this->makeCheck(
                'saldo_cached_freshness',
                'WARN',
                null,
                self::SALDO_LAG_WARN_HOURS . 'h',
                'Nenhuma sync de saldo registrada (saldo_cached NULL em todas as contas)',
                'Rode `php artisan rb:sync-bank-balances --sync` ou aguarde scheduler hourly.'
            );
        }

        $ultimaSyncAt = \Carbon\Carbon::parse($ultimaSync);
        $diffHours = $ultimaSyncAt->diffInHours(now(), true);

        if ($diffHours > self::SALDO_LAG_WARN_HOURS) {
            return $this->makeCheck(
                'saldo_cached_freshness',
                'WARN',
                $diffHours,
                self::SALDO_LAG_WARN_HOURS . 'h',
                "Última sync: {$ultimaSync} ({$diffHours}h atrás) — acima do limite",
                'Verifique scheduler hourly + credencial boleto ativa. Rode rb:sync-bank-balances --sync.'
            );
        }

        return $this->makeCheck(
            'saldo_cached_freshness',
            'OK',
            $diffHours,
            self::SALDO_LAG_WARN_HOURS . 'h',
            "Última sync: {$ultimaSync} ({$diffHours}h atrás)",
            'Saldo bancário fresh.'
        );
    }

    private function outputTable(array $checks, array $summary, ?int $businessId, bool $alert): int
    {
        $bizLabel = $businessId !== null ? "business_id={$businessId}" : 'todos businesses (admin)';
        $this->line('');
        $this->info('RecurringBilling Health Check — ' . now()->toDateTimeString());
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
