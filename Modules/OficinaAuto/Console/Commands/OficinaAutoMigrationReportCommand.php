<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * CYCLE-06 — Relatório pós-migração cliente legacy OficinaAuto.
 *
 * Gera markdown auditável com snapshot do estado migrado:
 *   - Contagens por entidade (vehicles, service_orders, transactions ligadas)
 *   - Vendas órfãs (transactions sem service_order ligada via legacy_id)
 *   - OS pendentes (status != concluida/entregue)
 *   - OS completas sem NFe (invoice_no NULL)
 *
 * Output em:
 *   storage/reports/oficina-migration-{biz}-{timestamp}.md
 *
 * Uso:
 *   php artisan oficina:migration-report 1
 *   php artisan oficina:migration-report 1 --detail
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * `{biz}` obrigatório. NOTA: `--detail` (não `--verbose` — Symfony reserved).
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md
 */
class OficinaAutoMigrationReportCommand extends Command
{
    protected $signature = 'oficina:migration-report
                            {biz : business_id obrigatório (CLI sem session — ADR 0093)}
                            {--detail : Inclui tabelas detalhadas com IDs}';

    protected $description = 'Gera relatório markdown pós-migração OficinaAuto em storage/reports/.';

    public function handle(): int
    {
        $businessId = (int) $this->argument('biz');
        if ($businessId <= 0) {
            $this->error('biz argument inválido. Use: php artisan oficina:migration-report {biz_id}');

            return self::FAILURE;
        }

        $detail = (bool) $this->option('detail');
        $timestamp = Carbon::now()->format('Y-m-d_His');

        $reportsDir = storage_path('reports');
        if (! File::isDirectory($reportsDir)) {
            File::makeDirectory($reportsDir, 0o755, true);
        }

        $path = "{$reportsDir}/oficina-migration-{$businessId}-{$timestamp}.md";

        // Coletar contagens
        $counts = $this->coletarContagens($businessId);
        $orfas = $this->vendasOrfas($businessId, $detail);
        $pendentes = $this->osPendentes($businessId, $detail);
        $semNfe = $this->osSemNfe($businessId, $detail);

        $md = $this->montarMarkdown($businessId, $timestamp, $counts, $orfas, $pendentes, $semNfe, $detail);

        File::put($path, $md);

        $this->info("Relatório gerado: {$path}");
        $this->line("  vehicles={$counts['vehicles']}, service_orders={$counts['service_orders']}, "
            . "transactions={$counts['transactions']}");
        $this->line("  vendas_orfas={$orfas['count']}, os_pendentes={$pendentes['count']}, "
            . "os_sem_nfe={$semNfe['count']}");

        return self::SUCCESS;
    }

    /**
     * @return array{vehicles:int, service_orders:int, transactions:int}
     */
    private function coletarContagens(int $businessId): array
    {
        $vehicles = Schema::hasTable('vehicles')
            ? DB::table('vehicles')->where('business_id', $businessId)->whereNull('deleted_at')->count()
            : 0;

        $serviceOrders = Schema::hasTable('service_orders')
            ? DB::table('service_orders')->where('business_id', $businessId)->whereNull('deleted_at')->count()
            : 0;

        // transactions ligadas a OS
        $transactions = 0;
        if (Schema::hasTable('transactions') && Schema::hasTable('service_orders')) {
            $transactions = DB::table('transactions as tx')
                ->join('service_orders as so', 'so.transaction_id', '=', 'tx.id')
                ->where('tx.business_id', $businessId)
                ->whereNull('so.deleted_at')
                ->count();
        }

        return [
            'vehicles' => $vehicles,
            'service_orders' => $serviceOrders,
            'transactions' => $transactions,
        ];
    }

    /**
     * @return array{count:int, rows:array}
     */
    private function vendasOrfas(int $businessId, bool $detail): array
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasTable('service_orders')) {
            return ['count' => 0, 'rows' => []];
        }

        $q = DB::table('transactions as tx')
            ->leftJoin('service_orders as so', 'so.transaction_id', '=', 'tx.id')
            ->where('tx.business_id', $businessId)
            ->where('tx.type', 'sell')
            ->whereNull('so.id');

        return [
            'count' => $q->count(),
            'rows' => $detail
                ? (array) $q->select('tx.id', 'tx.ref_no', 'tx.transaction_date')
                    ->orderBy('tx.id')->limit(50)->get()->toArray()
                : [],
        ];
    }

    /**
     * @return array{count:int, rows:array}
     */
    private function osPendentes(int $businessId, bool $detail): array
    {
        if (! Schema::hasTable('service_orders')) {
            return ['count' => 0, 'rows' => []];
        }

        $q = DB::table('service_orders')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['concluida', 'entregue', 'cancelada', 'recolhida']);

        return [
            'count' => $q->count(),
            'rows' => $detail
                ? (array) $q->select('id', 'vehicle_id', 'status', 'entered_at')
                    ->orderBy('id')->limit(50)->get()->toArray()
                : [],
        ];
    }

    /**
     * @return array{count:int, rows:array}
     */
    private function osSemNfe(int $businessId, bool $detail): array
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasTable('transactions')) {
            return ['count' => 0, 'rows' => []];
        }

        $q = DB::table('service_orders as so')
            ->join('transactions as tx', 'so.transaction_id', '=', 'tx.id')
            ->where('so.business_id', $businessId)
            ->whereNotNull('so.completed_at')
            ->whereNull('tx.invoice_no')
            ->whereNull('so.deleted_at');

        return [
            'count' => $q->count(),
            'rows' => $detail
                ? (array) $q->select('so.id', 'so.vehicle_id', 'tx.ref_no', 'so.completed_at')
                    ->orderBy('so.id')->limit(50)->get()->toArray()
                : [],
        ];
    }

    private function montarMarkdown(
        int $businessId,
        string $timestamp,
        array $counts,
        array $orfas,
        array $pendentes,
        array $semNfe,
        bool $detail,
    ): string {
        $md = "# Relatório migração OficinaAuto — biz={$businessId}\n\n";
        $md .= "Gerado em: {$timestamp}\n\n";
        $md .= "## Contagens\n\n";
        $md .= "| Entidade | Total |\n|---|---|\n";
        $md .= "| Vehicles ativos | {$counts['vehicles']} |\n";
        $md .= "| Service Orders ativas | {$counts['service_orders']} |\n";
        $md .= "| Transactions ligadas a OS | {$counts['transactions']} |\n\n";

        $md .= "## Alertas\n\n";
        $md .= "- Vendas órfãs (transaction sem OS): **{$orfas['count']}**\n";
        $md .= "- OS pendentes (status != concluida/entregue/cancelada/recolhida): **{$pendentes['count']}**\n";
        $md .= "- OS completas sem NFe (invoice_no NULL): **{$semNfe['count']}**\n\n";

        if ($detail) {
            $md .= "## Detalhe — vendas órfãs (top 50)\n\n";
            foreach ($orfas['rows'] as $row) {
                $r = (object) $row;
                $md .= "- tx#{$r->id} ref_no={$r->ref_no} data={$r->transaction_date}\n";
            }
            $md .= "\n## Detalhe — OS pendentes (top 50)\n\n";
            foreach ($pendentes['rows'] as $row) {
                $r = (object) $row;
                $md .= "- os#{$r->id} vehicle={$r->vehicle_id} status={$r->status} entrada={$r->entered_at}\n";
            }
            $md .= "\n## Detalhe — OS sem NFe (top 50)\n\n";
            foreach ($semNfe['rows'] as $row) {
                $r = (object) $row;
                $md .= "- os#{$r->id} vehicle={$r->vehicle_id} ref={$r->ref_no} completada_em={$r->completed_at}\n";
            }
        }

        $md .= "\n---\nGerado por `php artisan oficina:migration-report {$businessId}"
            . ($detail ? ' --detail' : '') . "`\n";

        return $md;
    }
}
