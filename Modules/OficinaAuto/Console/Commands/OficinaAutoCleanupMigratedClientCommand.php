<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Console\Commands;

use App\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;

/**
 * CYCLE-06 — Cleanup pós-migração cliente legacy (PR #555).
 *
 * Remove resíduos de teste/seed após migração Firebird → oimpresso de
 * cliente legacy OficinaAuto. Operação CONSERVADORA — dry-run default ON.
 *
 * Limpa:
 *  1. Vendas teste (transactions.ref_no LIKE 'TEST%' do business)
 *  2. OS órfãs (status NULL/string vazia + created_at > 30d)
 *  3. Veículos fixture auto-criados (plate LIKE 'FIXTURE%')
 *
 * Uso:
 *   php artisan oficina:cleanup-migrated 1                 # dry-run (default ON)
 *   php artisan oficina:cleanup-migrated 1 --dry-run=false # executa de fato
 *   php artisan oficina:cleanup-migrated 1 --detail        # log detalhado por item
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * `{biz}` argumento obrigatório — comando roda fora HTTP (sem session).
 *
 * NOTA: `--detail` (NÃO `--verbose` — colide com Symfony Console reserved,
 * lição PR #851).
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 */
class OficinaAutoCleanupMigratedClientCommand extends Command
{
    /**
     * NOTA: dry-run default ON (true) — segurança Tier 0.
     */
    protected $signature = 'oficina:cleanup-migrated
                            {biz : business_id obrigatório (CLI sem session — ADR 0093)}
                            {--dry-run=true : Preview sem modificar DB (default ON)}
                            {--detail : Log detalhado por item removido}';

    protected $description = 'Cleanup pós-migração cliente legacy OficinaAuto — remove vendas teste, OS órfãs, fixtures.';

    public function handle(): int
    {
        $businessId = (int) $this->argument('biz');
        if ($businessId <= 0) {
            $this->error('biz argument inválido. Use: php artisan oficina:cleanup-migrated {biz_id}');

            return self::FAILURE;
        }

        // dry-run STRING default 'true' — só executa se passado explicitamente como 'false' ou '0'
        $dryRunOption = $this->option('dry-run');
        $dryRun = ! in_array(strtolower((string) $dryRunOption), ['false', '0', 'no'], true);

        $detail = (bool) $this->option('detail');

        $prefix = $dryRun ? '[DRY RUN] ' : '[EXECUTANDO] ';
        $this->info($prefix . "Cleanup pós-migração OficinaAuto biz={$businessId}");

        if ($dryRun) {
            $this->warn('Dry-run ON (default). Para executar de fato: --dry-run=false');
        }

        $totals = [
            'transactions_teste' => 0,
            'service_orders_orfas' => 0,
            'vehicles_fixture' => 0,
        ];

        // 1) Vendas teste (transactions.ref_no LIKE 'TEST%')
        if (Schema::hasTable('transactions')) {
            $txQuery = DB::table('transactions')
                ->where('business_id', $businessId)
                ->where('ref_no', 'LIKE', 'TEST%');

            $txCount = $txQuery->count();
            $totals['transactions_teste'] = $txCount;

            if ($detail && $txCount > 0) {
                $txQuery->select('id', 'ref_no')->orderBy('id')->limit(20)->get()
                    ->each(fn ($row) => $this->line("  tx#{$row->id} ref_no={$row->ref_no}"));
            }

            if (! $dryRun && $txCount > 0) {
                $deleted = $txQuery->delete();
                $this->info("  - Deletadas {$deleted} transactions teste");
            }
        } else {
            $this->warn('  transactions table missing — skip vendas teste');
        }

        // 2) OS órfãs (status NULL ou '' + criado >30d)
        $cutoffDate = Carbon::now()->subDays(30);

        if (Schema::hasTable('service_orders')) {
            $osQuery = ServiceOrder::withoutGlobalScopes() // SUPERADMIN: CLI sem session multi-tenant
                ->where('business_id', $businessId)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '');
                })
                ->where('created_at', '<', $cutoffDate);

            $osCount = $osQuery->count();
            $totals['service_orders_orfas'] = $osCount;

            if ($detail && $osCount > 0) {
                $osQuery->select('id', 'vehicle_id', 'created_at')->orderBy('id')->limit(20)->get()
                    ->each(fn ($row) => $this->line("  os#{$row->id} vehicle={$row->vehicle_id} criado_em={$row->created_at}"));
            }

            if (! $dryRun && $osCount > 0) {
                $deleted = $osQuery->forceDelete();
                $this->info("  - Force-deletadas {$deleted} OS órfãs");
            }
        } else {
            $this->warn('  service_orders table missing — skip OS órfãs');
        }

        // 3) Veículos fixture (plate LIKE 'FIXTURE%')
        if (Schema::hasTable('vehicles')) {
            $vQuery = Vehicle::withoutGlobalScopes() // SUPERADMIN: CLI sem session multi-tenant
                ->where('business_id', $businessId)
                ->where('plate', 'LIKE', 'FIXTURE%');

            $vCount = $vQuery->count();
            $totals['vehicles_fixture'] = $vCount;

            if ($detail && $vCount > 0) {
                $vQuery->select('id', 'plate')->orderBy('id')->limit(20)->get()
                    ->each(fn ($row) => $this->line("  vehicle#{$row->id} plate={$row->plate}"));
            }

            if (! $dryRun && $vCount > 0) {
                $deleted = $vQuery->forceDelete();
                $this->info("  - Force-deletados {$deleted} veículos fixture");
            }
        } else {
            $this->warn('  vehicles table missing — skip fixtures');
        }

        $total = array_sum($totals);
        $this->newLine();
        $this->info($prefix . "Resumo biz={$businessId}: vendas_teste={$totals['transactions_teste']}, "
            . "os_orfas={$totals['service_orders_orfas']}, vehicles_fixture={$totals['vehicles_fixture']} "
            . "(total {$total})");

        return self::SUCCESS;
    }
}
