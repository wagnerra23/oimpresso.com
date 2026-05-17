<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Crm\Entities\Deal;
use RuntimeException;

/**
 * W27 — DealPipelineService.
 *
 * Encapsula operações de pipeline Kanban (Pipedrive/HubSpot-like) sobre `crm_deals`:
 *   - moverStage(): transição validada com audit trail (LogsActivity)
 *   - pipelineSummary(): agregação Kanban (count + valor por stage)
 *   - forecastFechamento(): weighted forecast por período (amount × probability)
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - Toda assinatura recebe `int $businessId` resolvido pelo caller
 *   - Service NUNCA toca session — facilita Jobs assíncronos
 *   - Queries SEMPRE explicitam `where('business_id', $businessId)` mesmo com HasBusinessScope
 *     (defesa-em-profundidade — paranoid mode)
 *
 * LGPD:
 *   - LogsActivity (no Model Deal) registra dirty fields sem expor PII em description
 *   - `razao` opcional em moverStage() vai pra metadata, não pra description
 *
 * Best practices 2026:
 *   - Forecast weighted: Σ(valor_estimado × probabilidade_stage) — HubSpot pattern
 *   - Stages enum hardcoded — evita stage-soup multi-tenant (ADR proposta futura per-biz)
 *
 * @see Modules/Crm/Entities/Deal.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class DealPipelineService
{
    /**
     * Move um Deal de stage com validação e audit trail.
     *
     * Trigger automatico de LogsActivity (configurado em Deal::getActivitylogOptions()):
     * registra dirty fields ['stage'] no `activity_log` sem expor PII.
     *
     * @param  int          $businessId  Tier 0 obrigatório (ADR 0093)
     * @param  int          $dealId      ID do Deal (scopado por $businessId)
     * @param  string       $newStage    Stage destino (deve estar em Deal::STAGES)
     * @param  string|null  $razao       Razão opcional (gravado em metadata.transitions[])
     *
     * @throws InvalidArgumentException  Se $newStage não estiver em Deal::STAGES
     * @throws RuntimeException          Se Deal não existir em $businessId ou já estiver em stage terminal
     */
    public function moverStage(int $businessId, int $dealId, string $newStage, ?string $razao = null): Deal
    {
        if (! in_array($newStage, Deal::STAGES, true)) {
            throw new InvalidArgumentException(
                "Stage inválido: '{$newStage}'. Stages permitidos: ".implode(', ', Deal::STAGES)
            );
        }

        return DB::transaction(function () use ($businessId, $dealId, $newStage, $razao) {
            // Defesa-em-profundidade: HasBusinessScope filtra, mas explicitamos where pra paranoid mode.
            /** @var Deal|null $deal */
            $deal = Deal::where('business_id', $businessId)
                ->where('id', $dealId)
                ->first();

            if (! $deal) {
                throw new RuntimeException("Deal {$dealId} não encontrado em business {$businessId}");
            }

            if ($deal->isTerminal() && $deal->stage !== $newStage) {
                throw new RuntimeException(
                    "Deal {$dealId} está em stage terminal '{$deal->stage}' — não pode mover pra '{$newStage}'. ".
                    'Reabra via reset metadata (backlog).'
                );
            }

            $stageAnterior = $deal->stage;

            // Append transition no metadata pra histórico além do activity_log (rotting/SLA futuro).
            $metadata = $deal->metadata ?? [];
            $transitions = $metadata['transitions'] ?? [];
            $transitions[] = [
                'from' => $stageAnterior,
                'to' => $newStage,
                'razao' => $razao,
                'at' => now()->toIso8601String(),
            ];
            $metadata['transitions'] = $transitions;

            $deal->stage = $newStage;
            $deal->metadata = $metadata;
            $deal->save(); // dispara LogsActivity automático (ADR 0093 audit)

            return $deal->fresh();
        });
    }

    /**
     * Resumo Kanban — count + soma valor por stage.
     *
     * Output canônico (todos os 6 stages presentes mesmo com 0 deals):
     *   [
     *     'lead' => ['count' => 12, 'valor_total' => 45000.00, 'valor_ponderado' => 4500.00],
     *     'qualificacao' => [...],
     *     ...
     *     'totais' => ['count' => N, 'valor_total' => X, 'valor_ponderado' => Y],
     *   ]
     *
     * Multi-tenant: filtra por $businessId explícito (defesa-em-profundidade).
     *
     * @return array<string, array{count: int, valor_total: float, valor_ponderado: float}>
     */
    public function pipelineSummary(int $businessId): array
    {
        $rows = Deal::where('business_id', $businessId)
            ->groupBy('stage')
            ->selectRaw('stage, COUNT(*) as total, SUM(valor_estimado) as soma')
            ->get()
            ->keyBy('stage');

        $resultado = [];
        $totalCount = 0;
        $totalValor = 0.0;
        $totalPonderado = 0.0;

        foreach (Deal::STAGES as $stage) {
            $row = $rows->get($stage);
            $count = $row ? (int) $row->total : 0;
            $valorTotal = $row ? (float) $row->soma : 0.0;
            $probabilidade = Deal::PROBABILIDADES_DEFAULT[$stage] ?? 0.0;
            $valorPonderado = $valorTotal * $probabilidade;

            $resultado[$stage] = [
                'count' => $count,
                'valor_total' => round($valorTotal, 2),
                'valor_ponderado' => round($valorPonderado, 2),
            ];

            $totalCount += $count;
            $totalValor += $valorTotal;
            $totalPonderado += $valorPonderado;
        }

        $resultado['totais'] = [
            'count' => $totalCount,
            'valor_total' => round($totalValor, 2),
            'valor_ponderado' => round($totalPonderado, 2),
        ];

        return $resultado;
    }

    /**
     * Forecast weighted de fechamento até $periodEnd.
     *
     * Soma valor_ponderado (valor_estimado × probabilidade_stage) de todos os deals
     * ABERTOS (não-terminais) com data_fechamento_prevista <= $periodEnd.
     *
     * Pattern HubSpot/Pipedrive 2026: forecast = Σ(amount × stage_probability).
     *
     * Edge case: deals sem data_fechamento_prevista NÃO entram no forecast (intencional —
     * forçar reps cadastrarem data; alarme via crm:health backlog).
     */
    public function forecastFechamento(int $businessId, Carbon $periodEnd): float
    {
        $rows = Deal::where('business_id', $businessId)
            ->abertos()
            ->whereNotNull('data_fechamento_prevista')
            ->where('data_fechamento_prevista', '<=', $periodEnd->copy()->endOfDay())
            ->groupBy('stage')
            ->selectRaw('stage, SUM(valor_estimado) as soma')
            ->get();

        $forecast = 0.0;
        foreach ($rows as $row) {
            $probabilidade = Deal::PROBABILIDADES_DEFAULT[$row->stage] ?? 0.0;
            $forecast += ((float) $row->soma) * $probabilidade;
        }

        return round($forecast, 2);
    }
}
