<?php

declare(strict_types=1);

namespace App\Domain\Fsm\SideEffects;

use App\Domain\Fsm\Contracts\SideEffectInterface;
use App\Domain\Fsm\Models\SaleProcess;
use App\Domain\Fsm\Models\SaleProcessStage;
use App\Transaction;
use App\TransactionSellLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\NfeBrasil\Models\NfeEmissao;

/**
 * US-SELL-029 — Side-effect "Emitir nova NFe após cancelamento SEFAZ".
 *
 * Cria NOVA Transaction (clone da original cancelada) que pode ser re-emitida
 * fiscalmente. A venda original é PRESERVADA (CONFAZ SINIEF 07/2005 Art. 14 —
 * número fiscal cancelado via SEFAZ é imutável e fica registrado no banco
 * por rastreabilidade fiscal).
 *
 * Pré-condição validada:
 *   - Subject é App\Transaction
 *   - Existe NfeEmissao com status='cancelada' pra (business_id, transaction_id)
 *
 * Linkage entre venda original e nova:
 *   Não existe coluna `parent_transaction_id` em `transactions` (tabela CORE
 *   UltimatePOS — append-only). Linkage canônica é:
 *     1. `additional_notes` da nova transaction recebe marker textual
 *        `[FSM:emitido_apos_cancelamento_de=tx_id={original_id} ref_no={original_ref}]`
 *     2. `sale_stage_history.payload_snapshot` (escrito pelo
 *        ExecuteStageActionService) registra payload merged que pode incluir
 *        `motivo` original — bridge auditável.
 *
 * Multi-tenant Tier 0 (ADR 0093): nova transaction SEMPRE herda business_id
 * da parent. Pest cross-tenant biz=99 valida isolation.
 *
 * Pain point Wagner 2026-05-12:
 *   "cancelam nota perdem número pula sequencial"
 *
 * Refs:
 *   - ADR 0143 §FSM Pipeline LIVE prod biz=1
 *   - SPEC.md US-SELL-029
 *   - CONFAZ Ajuste SINIEF 07/2005 Art. 14
 *   - memory/requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md §CU-01 G1
 */
class EmitirNovaAposCancelamento implements SideEffectInterface
{
    public function execute(Model $subject, array $payload = []): void
    {
        if (! $subject instanceof Transaction) {
            throw new InvalidArgumentException(
                'EmitirNovaAposCancelamento: subject deve ser App\\Transaction (recebido ' .
                $subject::class . ')'
            );
        }

        $businessId = (int) $subject->business_id;
        $originalId = (int) $subject->getKey();
        $motivo = (string) ($payload['motivo'] ?? 'Re-emissão após cancelamento SEFAZ');

        // ── Pré-condição: NFe da venda original DEVE estar cancelada via SEFAZ ─
        // Sem essa guard, action vira escape valve pra clonar venda arbitrariamente.
        $emissaoCancelada = NfeEmissao::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('transaction_id', $originalId)
            ->where('status', 'cancelada')
            ->first();

        if (! $emissaoCancelada) {
            throw new InvalidArgumentException(
                "EmitirNovaAposCancelamento: transaction {$originalId} não tem NFe " .
                'cancelada via SEFAZ (status=cancelada em nfe_emissoes). ' .
                'Action só faz sentido após cancelamento fiscal aceito (CONFAZ SINIEF 07/2005 Art. 14).'
            );
        }

        DB::transaction(function () use ($subject, $businessId, $originalId, $motivo, $emissaoCancelada) {
            $novo = $this->cloneTransaction($subject, $businessId, $originalId, $motivo);
            $this->cloneSellLines($subject, $novo);

            Log::info('SideEffect EmitirNovaAposCancelamento: nova venda criada', [
                'business_id' => $businessId,
                'original_transaction_id' => $originalId,
                'original_ref_no' => $subject->ref_no,
                'original_nfe_emissao_id' => $emissaoCancelada->id,
                'original_nfe_numero' => $emissaoCancelada->numero,
                'novo_transaction_id' => $novo->id,
                'novo_ref_no' => $novo->ref_no,
                'motivo' => $motivo,
            ]);
        });
    }

    /**
     * Clona Transaction preservando business_id e zerando campos que devem
     * ser regenerados (PK, current_stage_id, transaction_date, invoice_no).
     *
     * Linkage com original via `additional_notes` (text field — única coluna
     * livre em `transactions` sem migration nova; tabela CORE UltimatePOS).
     */
    private function cloneTransaction(
        Transaction $original,
        int $businessId,
        int $originalId,
        string $motivo,
    ): Transaction {
        $novo = $original->replicate(['current_stage_id', 'invoice_no']);

        // Tier 0: business_id SEMPRE da parent (defesa contra payload spoofing)
        $novo->business_id = $businessId;

        // Stage inicial do processo "Venda Com Produção" (fallback null se ausente)
        $novo->current_stage_id = $this->resolveInitialStageId($businessId);

        // Data nova — venda re-emitida tem data própria
        $novo->transaction_date = now();

        // ref_no: appenda sufixo pra rastreabilidade visual; controller pode regenerar
        // de fato no momento da emissão NFe. Aqui evita colisão de unique se houver.
        $novo->ref_no = $original->ref_no
            ? "{$original->ref_no}-REE"
            : null;

        // invoice_no = null — será atribuído quando NFe for emitida na nova venda
        $novo->invoice_no = null;

        // Linkage textual (única opção sem migration na tabela CORE UltimatePOS)
        $marker = sprintf(
            '[FSM:emitido_apos_cancelamento_de=tx_id=%d ref_no=%s motivo=%s]',
            $originalId,
            $original->ref_no ?? '-',
            $motivo,
        );
        $notasOriginais = (string) ($original->additional_notes ?? '');
        $novo->additional_notes = trim($notasOriginais . "\n" . $marker);

        $novo->save();

        return $novo;
    }

    /**
     * Resolve initial_stage_id do processo "venda_com_producao" pra biz.
     * Retorna null se processo/stage não existir (caller decide fallback).
     */
    private function resolveInitialStageId(int $businessId): ?int
    {
        $process = SaleProcess::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('key', 'venda_com_producao')
            ->first();

        if (! $process) {
            return null;
        }

        $initialStage = SaleProcessStage::withoutGlobalScope(ScopeByBusiness::class)
            ->where('process_id', $process->id)
            ->where('is_initial', true)
            ->first();

        return $initialStage?->id;
    }

    /**
     * Clona linhas de venda (produtos) preservando relacionamentos.
     * Cada linha é replicada com novo transaction_id (FK pra nova venda).
     */
    private function cloneSellLines(Transaction $original, Transaction $novo): void
    {
        $original->sell_lines()->get()->each(function (TransactionSellLine $linha) use ($novo) {
            $novaLinha = $linha->replicate();
            $novaLinha->transaction_id = $novo->id;
            $novaLinha->save();
        });
    }
}
