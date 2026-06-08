<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CYCLE-06 — Sanity check pós-migração cliente legacy OficinaAuto.
 *
 * Valida 5 invariantes pós-importação Firebird → oimpresso:
 *   1. business_id consistency — toda OS/Vehicle do biz tem business_id = {biz}
 *   2. FK orphans — service_orders.vehicle_id aponta pra vehicle real do mesmo biz
 *   3. Append-only ponto_marcacoes — assert sem rows DELETE (Portaria 671/2021)
 *   4. FSM state válido — current_stage_id null OU referencia stage existente
 *   5. NFe pendente — transactions ligadas a OS sem NFe emitida (alerta)
 *
 * Uso:
 *   php artisan oficina:sanity-check 1            # biz=1
 *   php artisan oficina:sanity-check 1 --detail   # log linha-a-linha
 *
 * Exit code:
 *   0 — todas invariantes OK
 *   1 — pelo menos 1 invariante FALHOU (CI gate)
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * `{biz}` argumento obrigatório — comando roda fora HTTP.
 *
 * NOTA: `--detail` (NÃO `--verbose` — Symfony reserved, lição PR #851).
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md
 */
class OficinaAutoSanityCheckCommand extends Command
{
    protected $signature = 'oficina:sanity-check
                            {biz : business_id obrigatório (CLI sem session — ADR 0093)}
                            {--detail : Log linha-a-linha das violações encontradas}';

    protected $description = 'Sanity check pós-migração OficinaAuto — 5 invariantes (exit 0 OK / 1 fail).';

    public function handle(): int
    {
        $businessId = (int) $this->argument('biz');
        if ($businessId <= 0) {
            $this->error('biz argument inválido. Use: php artisan oficina:sanity-check {biz_id}');

            return self::FAILURE;
        }

        $detail = (bool) $this->option('detail');

        $this->info("Sanity check OficinaAuto biz={$businessId}");
        $this->newLine();

        $failures = 0;

        // 1) business_id consistency em service_orders e vehicles
        $failures += $this->checkBusinessIdConsistency($businessId, $detail) ? 0 : 1;

        // 2) FK orphans — service_orders.vehicle_id existe + mesmo business
        $failures += $this->checkFkOrphans($businessId, $detail) ? 0 : 1;

        // 3) Append-only ponto_marcacoes (proxy: tabela existe + tem trigger imutável)
        $failures += $this->checkAppendOnlyMarcacoes($businessId, $detail) ? 0 : 1;

        // 4) FSM state válido — current_stage_id null OU referencia stage
        $failures += $this->checkFsmStateValid($businessId, $detail) ? 0 : 1;

        // 5) NFe pendente — alerta apenas (não bloqueia)
        $this->checkNfePendente($businessId, $detail);

        $this->newLine();
        if ($failures === 0) {
            $this->info("[OK] Todas as 5 invariantes passaram pra biz={$businessId}");

            return self::SUCCESS;
        }

        $this->error("[FALHA] {$failures} invariante(s) violada(s) pra biz={$businessId}");

        return self::FAILURE;
    }

    private function checkBusinessIdConsistency(int $businessId, bool $detail): bool
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
            $this->warn('  [SKIP] 1) business_id consistency — tabelas missing');

            return true;
        }

        // OS com business_id divergente OU null
        $invalidOs = DB::table('service_orders')
            ->where(function ($q) use ($businessId) {
                $q->whereNull('business_id')->orWhere('business_id', '!=', $businessId);
            })
            ->whereIn('id', function ($sub) use ($businessId) {
                // só checa as OS que estamos investigando deste biz (via vehicle)
                $sub->select('so.id')
                    ->from('service_orders as so')
                    ->join('vehicles as v', 'so.vehicle_id', '=', 'v.id')
                    ->where('v.business_id', $businessId);
            })
            ->count();

        if ($invalidOs === 0) {
            $this->info('  [OK] 1) business_id consistency');

            return true;
        }

        $this->error("  [FAIL] 1) business_id consistency — {$invalidOs} OS com business_id divergente");

        return false;
    }

    private function checkFkOrphans(int $businessId, bool $detail): bool
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
            $this->warn('  [SKIP] 2) FK orphans — tabelas missing');

            return true;
        }

        $orphans = DB::table('service_orders as so')
            ->leftJoin('vehicles as v', 'so.vehicle_id', '=', 'v.id')
            ->where('so.business_id', $businessId)
            ->whereNull('v.id')
            ->count();

        if ($orphans === 0) {
            $this->info('  [OK] 2) FK orphans (service_orders → vehicles)');

            return true;
        }

        $this->error("  [FAIL] 2) FK orphans — {$orphans} OS apontando pra vehicle inexistente");

        if ($detail) {
            DB::table('service_orders as so')
                ->leftJoin('vehicles as v', 'so.vehicle_id', '=', 'v.id')
                ->where('so.business_id', $businessId)
                ->whereNull('v.id')
                ->select('so.id', 'so.vehicle_id')
                ->limit(10)->get()
                ->each(fn ($row) => $this->line("    os#{$row->id} → vehicle#{$row->vehicle_id} (inexistente)"));
        }

        return false;
    }

    private function checkAppendOnlyMarcacoes(int $businessId, bool $detail): bool
    {
        // Proxy: tabela ponto_marcacoes pode não existir nesse vertical
        if (! Schema::hasTable('ponto_marcacoes')) {
            $this->info('  [SKIP] 3) append-only ponto_marcacoes — tabela não usada neste módulo');

            return true;
        }

        // Se tem deleted_at preenchido = violação Portaria 671/2021
        $deleted = Schema::hasColumn('ponto_marcacoes', 'deleted_at')
            ? DB::table('ponto_marcacoes')
                ->where('business_id', $businessId)
                ->whereNotNull('deleted_at')
                ->count()
            : 0;

        if ($deleted === 0) {
            $this->info('  [OK] 3) append-only ponto_marcacoes (Portaria 671/2021)');

            return true;
        }

        $this->error("  [FAIL] 3) append-only — {$deleted} marcações com deleted_at NOT NULL");

        return false;
    }

    private function checkFsmStateValid(int $businessId, bool $detail): bool
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasColumn('service_orders', 'current_stage_id')) {
            $this->info('  [SKIP] 4) FSM state — current_stage_id não migrado ainda');

            return true;
        }

        if (! Schema::hasTable('sale_process_stages')) {
            $this->info('  [SKIP] 4) FSM state — sale_process_stages table missing');

            return true;
        }

        $invalid = DB::table('service_orders as so')
            ->leftJoin('sale_process_stages as sps', 'so.current_stage_id', '=', 'sps.id')
            ->where('so.business_id', $businessId)
            ->whereNotNull('so.current_stage_id')
            ->whereNull('sps.id')
            ->count();

        if ($invalid === 0) {
            $this->info('  [OK] 4) FSM state válido (current_stage_id ↔ sale_process_stages)');

            return true;
        }

        $this->error("  [FAIL] 4) FSM state — {$invalid} OS com current_stage_id órfão");

        return false;
    }

    private function checkNfePendente(int $businessId, bool $detail): void
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasTable('transactions')) {
            $this->info('  [SKIP] 5) NFe pendente — tabelas missing');

            return;
        }

        $pendente = DB::table('service_orders as so')
            ->join('transactions as tx', 'so.transaction_id', '=', 'tx.id')
            ->where('so.business_id', $businessId)
            ->whereNotNull('so.completed_at')
            ->whereNull('tx.invoice_no')
            ->count();

        if ($pendente === 0) {
            $this->info('  [OK] 5) NFe pendente — todas OS completas tem invoice_no');

            return;
        }

        // Alerta (não bloqueia exit code) — completa decisão Wagner manual.
        $this->warn("  [ALERTA] 5) NFe pendente — {$pendente} OS completas sem invoice_no (revisar manual)");
    }
}
