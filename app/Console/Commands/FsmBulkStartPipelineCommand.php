<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageHistory;
use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use App\Transaction;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Throwable;

/**
 * US-SELL-035 ext — bulk start pipeline FSM em vendas legadas.
 *
 * Migra em lote vendas com `current_stage_id IS NULL` pro pipeline FSM,
 * mapeando status legacy (draft/final + payment_status + sub_status) pro
 * stage_key inicial apropriado. Replica a lógica do
 * {@see \App\Http\Controllers\SaleFsmActionController::startPipeline()} pra
 * uma venda → N vendas (chunkById, transação por venda).
 *
 * Conservador por design:
 *   - dry-run mostra o que faria sem persistir
 *   - opt-in por business_id (nunca varre tudo)
 *   - transação por venda (falha em uma não derruba o batch)
 *   - 422 se business não tem processo cadastrado (sem fallback silencioso)
 *
 * Multi-tenant Tier 0 (ADR 0093): filtra `business_id` explicitamente em
 * toda query. Transaction não tem global scope — daí o where manual.
 *
 * Performance: usa chunkById pra aguentar 10k+ vendas sem timeout/OOM.
 *
 * TODO: extrair mapping status → stage_key pra service
 *       {@see App\Domain\Fsm\Services\InitialStageResolver} pra reuso entre
 *       este command e o Controller. Adiado pra evitar overlap com outros
 *       agentes mexendo no Controller. Por ora, lógica duplicada (15 linhas).
 */
class FsmBulkStartPipelineCommand extends Command
{
    protected $signature = 'fsm:bulk-start-pipeline
                            {business_id : ID do business a migrar}
                            {--process=venda_com_producao : Process key}
                            {--limit=100 : Max vendas por execução (chunk)}
                            {--dry-run : Não persiste, só mostra o que faria}
                            {--type=sell : Type da transaction a migrar (sell, sells_return, etc)}';

    protected $description = 'Migra vendas legadas (current_stage_id=NULL) pro pipeline FSM em lote, mapeando status legacy → stage inicial';

    public function handle(): int
    {
        $businessId = (int) $this->argument('business_id');
        $processKey = (string) $this->option('process');
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $type = (string) $this->option('type');

        $this->line("Bulk start pipeline FSM — business={$businessId} process={$processKey}" . ($dryRun ? ' (DRY-RUN)' : ''));
        $this->line('');

        // 1. Resolve processo + carrega stages indexados por key
        $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('key', $processKey)
            ->where('active', true)
            ->first();

        if (! $process) {
            $this->error("Processo '{$processKey}' não cadastrado pro business {$businessId} (422).");
            $this->line('Rode FsmProcessoVendaComProducaoSeeder ou ajuste --process.');

            return 1;
        }

        /** @var array<string, SaleProcessStage> $stagesByKey */
        $stagesByKey = $process->stages()->get()->keyBy('key')->all();

        if (empty($stagesByKey)) {
            $this->error("Processo '{$processKey}' não tem stages cadastrados.");

            return 1;
        }

        // 2. Resolve user superadmin pro audit user_id
        $auditUserId = User::where('business_id', $businessId)
            ->orderBy('id')
            ->value('id');

        if ($auditUserId === null) {
            $this->warn("Nenhum user encontrado pro business {$businessId} — sale_stage_history.user_id ficará NULL.");
        }

        // 3. Query candidatas — count + chunk
        $baseQuery = Transaction::where('business_id', $businessId)
            ->where('type', $type)
            ->whereNull('current_stage_id');

        $totalCandidatas = (clone $baseQuery)->count();

        $this->line("Resolvendo vendas legadas (current_stage_id=NULL)...");
        $this->line("Total candidatas: {$totalCandidatas}" . ($totalCandidatas > $limit ? " (mostrando primeiras {$limit})" : ''));
        $this->line('');

        if ($totalCandidatas === 0) {
            $this->info('Nada a migrar. OK.');

            return 0;
        }

        $alvo = min($totalCandidatas, $limit);
        $bar = $this->output->createProgressBar($alvo);
        $bar->start();

        $stats = [
            'processadas' => 0,
            'skipped' => 0,
            'por_stage' => [],
        ];
        $tInicio = microtime(true);
        $restante = $limit;

        // chunkById robusto pra ordenação previsível + sem OOM
        (clone $baseQuery)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->chunkById(min(200, $limit), function ($vendas) use (
                $stagesByKey, $businessId, $processKey, $auditUserId, $dryRun, &$stats, $bar, &$restante
            ) {
                foreach ($vendas as $venda) {
                    if ($restante <= 0) {
                        return false;
                    }
                    $restante--;

                    $stageKey = $this->resolveInitialStage($venda);
                    $stage = $stagesByKey[$stageKey] ?? null;

                    if (! $stage) {
                        $stats['skipped']++;
                        Log::warning('fsm:bulk-start-pipeline stage_key ausente no processo', [
                            'business_id' => $businessId,
                            'transaction_id' => $venda->id,
                            'stage_key' => $stageKey,
                            'process_key' => $processKey,
                        ]);
                        $bar->advance();

                        continue;
                    }

                    if ($dryRun) {
                        // Não emite linha por venda pra não poluir progress bar — só agrega
                        $stats['processadas']++;
                        $stats['por_stage'][$stageKey] = ($stats['por_stage'][$stageKey] ?? 0) + 1;
                        $bar->advance();

                        continue;
                    }

                    try {
                        DB::transaction(function () use ($venda, $stage, $businessId, $processKey, $auditUserId, $stageKey) {
                            FsmAuthorizationFlag::mark($venda::class, $venda->getKey());
                            $venda->current_stage_id = $stage->id;
                            $venda->save();

                            SaleStageHistory::withoutGlobalScope(ScopeByBusiness::class)->create([
                                'business_id' => $businessId,
                                'transaction_id' => $venda->id,
                                'action_id' => null,
                                'from_stage_id' => null,
                                'to_stage_id' => $stage->id,
                                'user_id' => $auditUserId,
                                'payload_snapshot' => [
                                    'pipeline_started' => true,
                                    'process_key' => $processKey,
                                    'mapped_from' => "status={$venda->status} payment_status={$venda->payment_status} sub_status={$venda->sub_status}",
                                    'bulk_command' => 'fsm:bulk-start-pipeline',
                                ],
                                'executed_at' => now(),
                            ]);
                        });

                        $stats['processadas']++;
                        $stats['por_stage'][$stageKey] = ($stats['por_stage'][$stageKey] ?? 0) + 1;
                    } catch (Throwable $e) {
                        $stats['skipped']++;
                        Log::error('fsm:bulk-start-pipeline falha individual', [
                            'business_id' => $businessId,
                            'transaction_id' => $venda->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $bar->advance();
                }

                return true;
            }, 'id', 'id');

        $bar->finish();
        $tFim = microtime(true);
        $duracao = number_format($tFim - $tInicio, 2);

        $this->line('');
        $this->line('');
        $this->info(($dryRun ? '[DRY] Simulação' : 'Migração') . " concluída em {$duracao}s");
        $this->line('');

        $this->line('Por stage:');
        foreach ($stats['por_stage'] as $key => $count) {
            $this->line("  {$key}: {$count}");
        }
        if (empty($stats['por_stage'])) {
            $this->line('  (nenhum)');
        }

        $this->line('');
        $this->line("Processadas: {$stats['processadas']}");
        $this->line("Skipped: {$stats['skipped']}");

        return 0;
    }

    /**
     * Mapeia status legacy da Transaction pro stage FSM inicial.
     *
     * Espelho da lógica em
     * {@see \App\Http\Controllers\SaleFsmActionController::resolveInitialStage()}.
     * Manter em sincronia (TODO: extrair pra InitialStageResolver service).
     */
    private function resolveInitialStage(Transaction $venda): string
    {
        $status = $venda->status ?? 'final';
        $paymentStatus = $venda->payment_status ?? 'due';
        $subStatus = $venda->sub_status ?? null;

        if ($status === 'draft') {
            return $subStatus === 'quotation' ? 'quote_sent' : 'quote_draft';
        }

        if ($status === 'final') {
            return match ($paymentStatus) {
                'paid' => 'paid',
                'partial' => 'invoiced',
                default => 'invoiced',
            };
        }

        return 'quote_draft';
    }
}
