<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use App\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Services\Sla\SlaEnforcer;

/**
 * SlaScanCommand — CYCLE-07 PR-2.
 *
 * Roda via schedule `everyFiveMinutes()->withoutOverlapping(10)` em
 * `app/Console/Kernel.php`. Delega scan/dispatch pra `SlaEnforcer` (service)
 * — esse Command só lida com CLI input/output + log.
 *
 * Uso:
 *   php artisan whatsapp:sla-scan                       # todos businesses, persiste
 *   php artisan whatsapp:sla-scan --business=1          # 1 business
 *   php artisan whatsapp:sla-scan --business=all        # explícito todos
 *   php artisan whatsapp:sla-scan --dry-run             # não publica nem persiste
 *
 * **Multi-tenant Tier 0 (ADR 0093):** Command CLI sem session — Enforcer
 * faz cross-tenant scan com WHERE explícito por policy.business_id (sem leak).
 *
 * @see Modules/Whatsapp/Services/Sla/SlaEnforcer.php
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md
 */
class SlaScanCommand extends Command
{
    protected $signature = 'whatsapp:sla-scan
                            {--business=all : business_id alvo (numeric) ou "all"}
                            {--dry-run : Só conta, não persiste nem publica}';

    protected $description = 'Scan SLA policies e dispara actions (Centrifugo/reassign/set_status).';

    public function handle(SlaEnforcer $enforcer): int
    {
        $businessOpt = (string) $this->option('business');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] Nenhuma row será persistida e nada será publicado.');
        }

        $businessId = $this->resolveBusinessId($businessOpt);
        if ($businessId === false) {
            return self::FAILURE;
        }

        $startedAt = microtime(true);
        $result = $enforcer->scanAndAlert($businessId, $dryRun);
        $tookMs = (int) round((microtime(true) - $startedAt) * 1000);

        $scope = $businessId !== null ? "business={$businessId}" : 'all businesses';
        $this->info(sprintf(
            '✓ Scan SLA concluído (%s) em %dms · %d policies · %d alerts · %d locked-skipped',
            $scope,
            $tookMs,
            $result['policies_scanned'],
            $result['alerts_fired'],
            $result['locked_skipped'],
        ));

        if (! empty($result['by_policy'])) {
            $rows = [];
            foreach ($result['by_policy'] as $policyId => $meta) {
                $rows[] = [
                    $policyId,
                    $meta['business_id'],
                    $meta['label'],
                    $meta['triggers_on'],
                    $meta['action_kind'],
                    $meta['fired'],
                    $meta['locked_skipped'],
                ];
            }
            $this->table(
                ['Policy', 'Biz', 'Label', 'Trigger', 'Action', 'Fired', 'Skipped'],
                $rows,
            );
        }

        Log::info('[whatsapp.sla-scan.completed]', [
            'business_filter' => $businessOpt,
            'dry_run' => $dryRun,
            'took_ms' => $tookMs,
            'result' => array_diff_key($result, ['by_policy' => true]),
        ]);

        return self::SUCCESS;
    }

    /**
     * @return int|null|false  int=biz específico, null=todos, false=input inválido
     */
    private function resolveBusinessId(string $businessOpt): int|null|false
    {
        if ($businessOpt === 'all') {
            return null;
        }

        $bizId = (int) $businessOpt;
        if ($bizId <= 0) {
            $this->error("--business={$businessOpt} inválido (esperado inteiro > 0 ou 'all').");
            return false;
        }

        if (! Business::query()->where('id', $bizId)->exists()) {
            $this->warn("Business #{$bizId} não existe — nada a fazer.");
            return false;
        }

        return $bizId;
    }
}
