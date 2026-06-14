<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services;

use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use Modules\Financeiro\Models\Titulo;
use Modules\PaymentGateway\Events\CobrancaPaga;
use Modules\PaymentGateway\Models\Cobranca;

/**
 * Reconciliação canônica "cobrança virou paga" — fonte única de verdade.
 *
 * Compartilhado entre os dois caminhos de confirmação de pagamento Inter:
 *   - PUSH: `ProcessarWebhookPixInterJob` (webhook PIX recebido → US-FIN-032)
 *   - PULL: `InterReconcilePixCommand` (polling de fallback — Inter não exige
 *     webhook; consulta GET /pix/v2/cob/{txid} e reconcilia o que pagou)
 *
 * Garante que os dois caminhos façam EXATAMENTE a mesma coisa ao marcar paga:
 *   1. Cobranca.status='paga' + valor_pago + paga_em + forma_pagamento
 *   2. Títulos vinculados (origem='manual' + origem_id=cobranca.id) → quitado
 *   3. Dispara evento `CobrancaPaga` (listener Financeiro cria Titulo + Baixa)
 *
 * NÃO abre transação — o caller (job/command) envolve em DB::transaction.
 *
 * Multi-tenant Tier 0 (ADR 0093): $businessId é passado explícito pelo caller
 * (queue worker / console não têm session()). Queries usam withoutGlobalScopes.
 */
class ReconciliarCobrancaService
{
    /**
     * Marca a cobrança como paga + quita títulos + dispara CobrancaPaga.
     *
     * Idempotência: a atualização da Cobranca é guardada por `status !== 'paga'`
     * (igual ao job original). O dispatch do evento é seguro pra re-rodar porque
     * o listener canônico `OnCobrancaPagaCreateFinanceiroTitulo` é idempotente
     * (checa origem_id antes de criar). Quem chama no polling já filtra
     * status='emitida', então não há re-dispatch redundante na prática.
     */
    public function marcarPaga(
        Cobranca $cobranca,
        int $businessId,
        int $valorPagoCentavos,
        \DateTimeImmutable $pagaEm,
        string $formaPagamento = 'pix',
    ): void {
        if ($cobranca->getAttribute('status') !== 'paga') {
            $cobranca->update([
                'status'              => 'paga',
                'valor_pago_centavos' => $valorPagoCentavos,
                'paga_em'             => $pagaEm,
                'forma_pagamento'     => $formaPagamento,
            ]);
        }

        $this->marcarTitulosQuitados($businessId, (int) $cobranca->id);

        // getAttribute() em vez de propriedade mágica: o Larastan não conhece
        // as colunas do Eloquent e o ratchet PHPStan barra acesso direto novo.
        $payerCpfCnpj = $cobranca->getAttribute('payer_cpf_cnpj');
        $origemType = $cobranca->getAttribute('origem_type');
        $origemId = $cobranca->getAttribute('origem_id');

        event(new CobrancaPaga(
            cobrancaId: (int) $cobranca->id,
            businessId: $businessId,
            valorPagoCentavos: $valorPagoCentavos,
            pagaEm: $pagaEm,
            formaPagamento: $formaPagamento,
            occurredAt: new \DateTimeImmutable(),
            payerCpfCnpj: $payerCpfCnpj !== null ? (string) $payerCpfCnpj : null,
            origemType: $origemType !== null ? (string) $origemType : null,
            origemId: $origemId !== null ? (int) $origemId : null,
        ));
    }

    /**
     * Marca título(s) vinculado(s) à cobrança como quitado.
     *
     * Estado atual (Onda 26): Titulo NÃO usa trait GuardsFsmTransitions
     * (FSM Pipeline ainda escopo Sells/Repair — ADR 0143). Update direto OK.
     * O FsmAuthorizationFlag::mark() é defensivo — se a trait for adicionada
     * antes deste worker ser refatorado, a transição PRIMEIRA não barra.
     */
    private function marcarTitulosQuitados(int $businessId, int $cobrancaId): void
    {
        // SUPERADMIN: chamado por job/command sem sessão (caller passa businessId explícito); quita títulos vinculados filtrando por esse business_id.
        $titulos = Titulo::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('origem', 'manual')
            ->where('origem_id', $cobrancaId)
            ->whereIn('status', ['aberto', 'parcial'])
            ->get();

        foreach ($titulos as $titulo) {
            FsmAuthorizationFlag::mark(Titulo::class, $titulo->id);

            $titulo->update([
                'status'       => 'quitado',
                'valor_aberto' => 0,
            ]);
        }
    }
}
