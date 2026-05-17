<?php

declare(strict_types=1);

namespace Modules\Woocommerce\Console\Commands;

use App\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Woocommerce\Services\WoocommerceAuthorizationService;
use Modules\Woocommerce\Services\WoocommerceResetService;
use Modules\Woocommerce\Services\WoocommerceSyncService;

/**
 * WoocommerceHealthCommand — health check do módulo Woocommerce (D9 Wave 18).
 *
 * Verifica saúde estrutural sem hit em API externa (zero custo):
 *  1. Tabela `woocommerce_sync_logs` acessível (schema OK)
 *  2. Container resolve 3 Services canon (DI sanity — D4 boost)
 *  3. Business `--business-id` tem credenciais cadastradas em `business.woocommerce_*`
 *     (ou warning se vazias — não bloqueia, só sinaliza)
 *  4. Última sync registrada (alerta se >7 dias OU nunca)
 *
 * Multi-tenant Tier 0 (ADR 0093): respeita `--business-id` obrigatório.
 * Convenção `--detail` ([.claude/rules/commands.md]) — NUNCA `--verbose`
 * (Symfony reserved).
 *
 * Uso:
 *   php artisan woocommerce:health --business-id=1
 *   php artisan woocommerce:health --business-id=1 --detail
 *
 * Exit codes:
 *   0 = healthy
 *   1 = degraded (config faltando OU sync velha)
 *   2 = error (DB/container falhou)
 *
 * @see Modules\Woocommerce\Services\WoocommerceSyncService
 * @see app\Util\OtelHelper (spans aplicados nos Services D9 Wave 18)
 */
class WoocommerceHealthCommand extends Command
{
    protected $signature = 'woocommerce:health
                            {--business-id= : ID do business (multi-tenant Tier 0 ADR 0093)}
                            {--detail : Log detalhado de cada check}';

    protected $description = 'Health check do módulo Woocommerce — DI/schema/credenciais/last-sync (D9 observability)';

    /**
     * Janela de alerta — sync mais antiga que isso vira degraded.
     */
    private const SYNC_STALE_DAYS = 7;

    public function handle(): int
    {
        $bizId = (int) $this->option('business-id');
        $detail = (bool) $this->option('detail');

        if ($bizId <= 0) {
            $this->error('--business-id obrigatório (multi-tenant Tier 0 ADR 0093)');

            return 2;
        }

        $business = Business::find($bizId);
        if (! $business) {
            $this->error("Business {$bizId} não existe.");

            return 2;
        }

        $this->line("Woocommerce health check — business_id={$bizId} ({$business->name})");
        $this->newLine();

        $issues = [];

        // Check 1 — Schema (tabela sync logs)
        try {
            $logsCount = DB::table('woocommerce_sync_logs')
                ->where('business_id', $bizId)
                ->count();
            $this->logCheck("Schema woocommerce_sync_logs acessível ({$logsCount} entries biz={$bizId})", true, $detail);
        } catch (\Throwable $e) {
            $this->logCheck('Schema woocommerce_sync_logs FALHOU: '.$e->getMessage(), false, $detail);
            $issues[] = 'schema';
        }

        // Check 2 — DI container resolve Services canon
        $services = [
            WoocommerceSyncService::class,
            WoocommerceResetService::class,
            WoocommerceAuthorizationService::class,
        ];
        foreach ($services as $svc) {
            try {
                $instance = app($svc);
                $this->logCheck("DI resolve {$svc}", $instance instanceof $svc, $detail);
            } catch (\Throwable $e) {
                $this->logCheck("DI FALHOU {$svc}: ".$e->getMessage(), false, $detail);
                $issues[] = 'di';
            }
        }

        // Check 3 — Credenciais WooCommerce no business
        $hasCreds = $business->woocommerce_app_url
            && $business->woocommerce_consumer_key
            && $business->woocommerce_consumer_secret;

        if (! $hasCreds) {
            $this->logCheck('Credenciais Woocommerce ausentes (woocommerce_app_url/consumer_key/consumer_secret)', false, $detail);
            $issues[] = 'creds';
        } else {
            $this->logCheck('Credenciais Woocommerce cadastradas (sem hit em API — verificação só local)', true, $detail);
        }

        // Check 4 — Última sync registrada
        $lastSync = DB::table('woocommerce_sync_logs')
            ->where('business_id', $bizId)
            ->orderByDesc('created_at')
            ->first();

        if (! $lastSync) {
            $this->logCheck('Nunca houve sync registrada (sync logs vazios pro business)', false, $detail);
            $issues[] = 'no-sync';
        } else {
            $daysAgo = now()->diffInDays($lastSync->created_at);
            if ($daysAgo > self::SYNC_STALE_DAYS) {
                $this->logCheck("Última sync há {$daysAgo} dias (>= ".self::SYNC_STALE_DAYS.' dias — stale)', false, $detail);
                $issues[] = 'stale-sync';
            } else {
                $this->logCheck("Última sync há {$daysAgo} dias (fresh)", true, $detail);
            }
        }

        $this->newLine();
        if (empty($issues)) {
            $this->info('Healthy: todos os checks passaram.');

            return 0;
        }

        $this->warn('Degraded: '.implode(', ', $issues));

        return 1;
    }

    private function logCheck(string $label, bool $ok, bool $detail): void
    {
        $symbol = $ok ? '[OK]' : '[FAIL]';
        if ($detail || ! $ok) {
            $this->line("  {$symbol} {$label}");
        }
    }
}
