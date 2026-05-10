<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Business;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Domain\Fsm\Models\SaleStageAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Scopes\ScopeByBusiness;

/**
 * US-SELL-012 — Seed canônico dos 3 processos FSM padrão por business.
 *
 * Pivot conceitual (Wagner 2026-05-10): "venda sem nota é caminho feliz, não falha".
 * Cada business ganha 3 opções de processo na criação da venda:
 *
 *   a) `venda_sem_nota`        — DEFAULT pra Contact CF/sem · sem stages emitida/enviada
 *   b) `venda_com_nota_manual` — usuário clica "Emitir NFe" via UI
 *   c) `venda_com_nota_auto`   — listener auto-emite (NfeBrasil) via event_class
 *
 * Idempotente: usa firstOrCreate por (business_id, key).
 *
 * Uso:
 *   php artisan fsm:seed-default-processes               # todas businesses
 *   php artisan fsm:seed-default-processes --business=1  # só biz=1 (Wagner WR2)
 *
 * Ver: ADR 0129 (FSM canônica) + ADR 0093 (multi-tenant Tier 0).
 */
class SeedDefaultFsmProcesses extends Command
{
    protected $signature = 'fsm:seed-default-processes {--business= : ID do business (omitir = todas)}';

    protected $description = 'Cria 3 processos FSM padrão (sem-nota / com-nota-manual / com-nota-auto) por business';

    public function handle(): int
    {
        $businessId = $this->option('business');

        if ($businessId !== null) {
            $businessIds = [(int) $businessId];
        } else {
            $businessIds = Business::query()->pluck('id')->all();
        }

        if (empty($businessIds)) {
            $this->warn('Nenhum business encontrado. Nada a fazer.');
            return self::SUCCESS;
        }

        foreach ($businessIds as $bizId) {
            $created = $this->seedForBusiness((int) $bizId);
            $this->line("  biz={$bizId} → criados/atualizados {$created} processos");
        }

        $this->info("OK · " . count($businessIds) . " business(es) processados");

        return self::SUCCESS;
    }

    /**
     * Cria os 3 processos pra um único business. Retorna nº de processos
     * efetivamente criados nesta execução (firstOrCreate idempotente).
     */
    public function seedForBusiness(int $businessId): int
    {
        $created = 0;

        $created += $this->seedVendaSemNota($businessId);
        $created += $this->seedVendaComNotaManual($businessId);
        $created += $this->seedVendaComNotaAuto($businessId);

        return $created;
    }

    /**
     * a) `venda_sem_nota` — caminho feliz (default pra CF).
     *    Stages: rascunho (initial) → faturada → paga (terminal).
     *    SEM action emitir_nfe.
     */
    private function seedVendaSemNota(int $businessId): int
    {
        return DB::transaction(function () use ($businessId) {
            $existed = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $businessId)
                ->where('key', 'venda_sem_nota')
                ->exists();

            $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
                ->firstOrCreate(
                    ['business_id' => $businessId, 'key' => 'venda_sem_nota'],
                    [
                        'name' => 'Venda Sem Nota',
                        'description' => 'Caminho feliz: vende sem emitir NFe (default pra Consumidor Final).',
                        'default_for_contact_type' => 'cf',
                        'active' => true,
                    ]
                );

            $rascunho = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => 'rascunho'],
                ['name' => 'Rascunho', 'sort_order' => 0, 'is_initial' => true]
            );
            $faturada = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => 'faturada'],
                ['name' => 'Faturada', 'sort_order' => 1]
            );
            $paga = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => 'paga'],
                ['name' => 'Paga', 'sort_order' => 2, 'is_terminal' => true]
            );

            SaleStageAction::firstOrCreate(
                ['stage_id' => $rascunho->id, 'key' => 'faturar'],
                ['label' => 'Faturar', 'target_stage_id' => $faturada->id]
            );
            SaleStageAction::firstOrCreate(
                ['stage_id' => $faturada->id, 'key' => 'receber_pagamento'],
                ['label' => 'Receber Pagamento', 'target_stage_id' => $paga->id]
            );

            return $existed ? 0 : 1;
        });
    }

    /**
     * b) `venda_com_nota_manual` — usuário clica "Emitir NFe" via UI.
     *    Stages: rascunho (initial) → faturada → paga → emitida → enviada (terminal).
     *    Action `emitir_nfe` em stage `paga` SEM event_class (manual).
     */
    private function seedVendaComNotaManual(int $businessId): int
    {
        return DB::transaction(function () use ($businessId) {
            $existed = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $businessId)
                ->where('key', 'venda_com_nota_manual')
                ->exists();

            $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
                ->firstOrCreate(
                    ['business_id' => $businessId, 'key' => 'venda_com_nota_manual'],
                    [
                        'name' => 'Venda Com Nota (Manual)',
                        'description' => 'Operador clica "Emitir NFe" via UI após pagamento.',
                        'default_for_contact_type' => 'pj',
                        'active' => true,
                    ]
                );

            $rascunho = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => 'rascunho'],
                ['name' => 'Rascunho', 'sort_order' => 0, 'is_initial' => true]
            );
            $faturada = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => 'faturada'],
                ['name' => 'Faturada', 'sort_order' => 1]
            );
            $paga = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => 'paga'],
                ['name' => 'Paga', 'sort_order' => 2]
            );
            $emitida = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => 'emitida'],
                ['name' => 'Emitida', 'sort_order' => 3]
            );
            $enviada = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => 'enviada'],
                ['name' => 'Enviada', 'sort_order' => 4, 'is_terminal' => true]
            );

            SaleStageAction::firstOrCreate(
                ['stage_id' => $rascunho->id, 'key' => 'faturar'],
                ['label' => 'Faturar', 'target_stage_id' => $faturada->id]
            );
            SaleStageAction::firstOrCreate(
                ['stage_id' => $faturada->id, 'key' => 'receber_pagamento'],
                ['label' => 'Receber Pagamento', 'target_stage_id' => $paga->id]
            );
            SaleStageAction::firstOrCreate(
                ['stage_id' => $paga->id, 'key' => 'emitir_nfe'],
                [
                    'label' => 'Emitir NFe',
                    'target_stage_id' => $emitida->id,
                    'requires_confirmation' => true,
                    // SEM event_class → manual via UI
                ]
            );
            SaleStageAction::firstOrCreate(
                ['stage_id' => $emitida->id, 'key' => 'enviar_danfe'],
                ['label' => 'Enviar DANFE', 'target_stage_id' => $enviada->id]
            );

            return $existed ? 0 : 1;
        });
    }

    /**
     * c) `venda_com_nota_auto` — listener auto-emite via event_class.
     *    Idem (b), mas action `emitir_nfe` tem event_class definido.
     */
    private function seedVendaComNotaAuto(int $businessId): int
    {
        return DB::transaction(function () use ($businessId) {
            $existed = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $businessId)
                ->where('key', 'venda_com_nota_auto')
                ->exists();

            $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
                ->firstOrCreate(
                    ['business_id' => $businessId, 'key' => 'venda_com_nota_auto'],
                    [
                        'name' => 'Venda Com Nota (Automática)',
                        'description' => 'Listener emite NFe sozinho ao pagamento (diferencial vertical gráfica).',
                        'default_for_contact_type' => 'any',
                        'active' => true,
                    ]
                );

            $rascunho = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => 'rascunho'],
                ['name' => 'Rascunho', 'sort_order' => 0, 'is_initial' => true]
            );
            $faturada = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => 'faturada'],
                ['name' => 'Faturada', 'sort_order' => 1]
            );
            $paga = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => 'paga'],
                ['name' => 'Paga', 'sort_order' => 2]
            );
            $emitida = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => 'emitida'],
                ['name' => 'Emitida', 'sort_order' => 3]
            );
            $enviada = SaleProcessStage::firstOrCreate(
                ['process_id' => $process->id, 'key' => 'enviada'],
                ['name' => 'Enviada', 'sort_order' => 4, 'is_terminal' => true]
            );

            SaleStageAction::firstOrCreate(
                ['stage_id' => $rascunho->id, 'key' => 'faturar'],
                ['label' => 'Faturar', 'target_stage_id' => $faturada->id]
            );
            SaleStageAction::firstOrCreate(
                ['stage_id' => $faturada->id, 'key' => 'receber_pagamento'],
                ['label' => 'Receber Pagamento', 'target_stage_id' => $paga->id]
            );
            SaleStageAction::firstOrCreate(
                ['stage_id' => $paga->id, 'key' => 'emitir_nfe'],
                [
                    'label' => 'Emitir NFe (auto)',
                    'target_stage_id' => $emitida->id,
                    // event_class disparado pós-execução → listener pode auto-emitir
                    'event_class' => '\\Modules\\NfeBrasil\\Events\\NFeEmissaoSolicitada',
                ]
            );
            SaleStageAction::firstOrCreate(
                ['stage_id' => $emitida->id, 'key' => 'enviar_danfe'],
                ['label' => 'Enviar DANFE', 'target_stage_id' => $enviada->id]
            );

            return $existed ? 0 : 1;
        });
    }
}
