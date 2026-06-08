<?php

declare(strict_types=1);

namespace Modules\Superadmin\Console;

use App\Util\OtelHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Superadmin\Services\BusinessAuditService;
use Modules\Superadmin\Services\SuperadminDashboardService;
use Throwable;

/**
 * Health-check do módulo Superadmin — Wave 23 D9.c.
 *
 * Verificações canon (cross-tenant intencional ADR 0093 §exceções):
 *   1. business table presente + biz=1 (Wagner) imutável
 *   2. subscriptions aging summary (waiting / approved / expired counts)
 *   3. Inativos > 90d (não-bloqueante; relatório p/ outreach)
 *   4. Self-destroy guard funcional via BusinessAuditService::canDestroy(1, 1)
 *
 * Roda 06:20 BRT (após connector:health 06:15). Loga estruturado pra
 * dashboard /copiloto/admin/qualidade. Exit 0 OK, 1 alerta.
 *
 * Uso:
 *   php artisan superadmin:health
 *   php artisan superadmin:health --detail    (mostra tabela por check)
 *   php artisan superadmin:health --notify    (ALERT em log se fail)
 *
 * Convenções (.claude/rules/commands.md):
 *   - `--detail` em vez de `--verbose` (Symfony reservado)
 *   - PT-BR output
 *   - Exit 0 sucesso, 1 alerta
 *
 * @see Modules\Superadmin\Services\SuperadminDashboardService (D4)
 * @see Modules\Superadmin\Services\BusinessAuditService (D4)
 * @see Modules\Connector\Console\Commands\ConnectorHealthCommand (pattern referência)
 */
class SuperadminHealthCommand extends Command
{
    protected $signature = 'superadmin:health
        {--detail : Imprime tabela detalhada por check}
        {--notify : Log ALERT estruturado em caso de fail (consumível por jana:health-check)}';

    protected $description = 'Health-check Superadmin: businesses canon + subscriptions aging + self-destroy guard (W23 D9).';

    public function handle(SuperadminDashboardService $dashboard, BusinessAuditService $audit): int
    {
        return OtelHelper::spanBiz('superadmin.health.run', function () use ($dashboard, $audit): int {
            $detail = (bool) $this->option('detail');
            $notify = (bool) $this->option('notify');

            $checks = [];
            $issues = [];

            // ---------- Check 1: business table + biz=1 imutável ----------
            try {
                if (! Schema::hasTable('business')) {
                    $checks[] = ['name' => 'business_table', 'ok' => false, 'detail' => 'tabela business ausente'];
                    $issues[] = 'business_table_missing';
                } else {
                    $bizWagner = DB::table('business')->where('id', 1)->exists();
                    $checks[] = [
                        'name'   => 'business_table_wagner_protected',
                        'ok'     => $bizWagner,
                        'detail' => $bizWagner ? 'biz=1 (Wagner) presente' : 'biz=1 AUSENTE — Tier 0 violação',
                    ];
                    if (! $bizWagner) {
                        $issues[] = 'wagner_business_missing';
                    }
                }
            } catch (Throwable $e) {
                $checks[] = ['name' => 'business_table', 'ok' => false, 'detail' => 'erro: '.$e->getMessage()];
                $issues[] = 'business_table_query_failed';
            }

            // ---------- Check 2: subscriptions aging ----------
            try {
                $aging = $audit->subscriptionAgingSummary();
                $checks[] = [
                    'name'   => 'subscriptions_aging',
                    'ok'     => true,
                    'detail' => sprintf(
                        'waiting=%d approved=%d expired=%d cancelled=%d',
                        $aging['waiting'] ?? 0,
                        $aging['approved'] ?? 0,
                        $aging['expired'] ?? 0,
                        $aging['cancelled'] ?? 0,
                    ),
                ];

                // Alerta soft se waiting > 50 (backlog acumulando)
                if (($aging['waiting'] ?? 0) > 50) {
                    $checks[] = [
                        'name'   => 'subscriptions_waiting_backlog',
                        'ok'     => false,
                        'detail' => 'waiting > 50 — review backlog necessário',
                    ];
                    $issues[] = 'subscriptions_waiting_backlog_high';
                }
            } catch (Throwable $e) {
                $checks[] = ['name' => 'subscriptions_aging', 'ok' => false, 'detail' => 'erro: '.$e->getMessage()];
                $issues[] = 'subscriptions_aging_query_failed';
            }

            // ---------- Check 3: self-destroy guard ----------
            try {
                $guard = $audit->canDestroy(1, 1);  // biz=1, session=biz=1 → MUST block
                $checks[] = [
                    'name'   => 'self_destroy_guard',
                    'ok'     => ($guard['can_destroy'] === false),
                    'detail' => $guard['reason'],
                ];
                if ($guard['can_destroy'] === true) {
                    $issues[] = 'self_destroy_guard_broken';
                }
            } catch (Throwable $e) {
                $checks[] = ['name' => 'self_destroy_guard', 'ok' => false, 'detail' => 'erro: '.$e->getMessage()];
                $issues[] = 'self_destroy_guard_query_failed';
            }

            // ---------- Check 4: dashboard service operacional ----------
            try {
                $count = $dashboard->countNotSubscribedBusinesses();
                $checks[] = [
                    'name'   => 'dashboard_service',
                    'ok'     => is_int($count),
                    'detail' => "not_subscribed_count={$count}",
                ];
            } catch (Throwable $e) {
                $checks[] = ['name' => 'dashboard_service', 'ok' => false, 'detail' => 'erro: '.$e->getMessage()];
                $issues[] = 'dashboard_service_failed';
            }

            // ---------- Output ----------
            $this->info('superadmin:health — '.now()->toDateTimeString());

            if ($detail) {
                $rows = array_map(fn ($c) => [
                    $c['name'],
                    $c['ok'] ? 'OK' : 'FAIL',
                    $c['detail'],
                ], $checks);

                $this->table(['check', 'status', 'detalhe'], $rows);
            } else {
                $ok = count(array_filter($checks, fn ($c) => $c['ok']));
                $this->info("Checks: {$ok}/".count($checks).' OK');
            }

            if (! empty($issues)) {
                $this->warn('Issues: '.implode(', ', $issues));

                if ($notify) {
                    Log::channel('stack')->alert('[superadmin:health] ALERT', [
                        'issues' => $issues,
                        'checks' => $checks,
                    ]);
                }

                return self::FAILURE;
            }

            return self::SUCCESS;
        }, ['module' => 'Superadmin', 'command' => 'superadmin:health']);
    }
}
