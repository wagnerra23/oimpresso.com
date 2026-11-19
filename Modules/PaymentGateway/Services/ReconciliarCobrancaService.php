<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services;

use App\Domain\Fsm\Support\FsmAuthorizationFlag;
use Illuminate\Support\Facades\DB;
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
 * Abre a própria transação (savepoint quando o caller já está numa) para
 * travar a linha da cobrança — ver marcarPaga().
 *
 * Multi-tenant Tier 0 (ADR 0093): $businessId é passado explícito pelo caller
 * (queue worker / console não têm session()). Queries usam withoutGlobalScopes.
 */
class ReconciliarCobrancaService
{
    /**
     * Marca a cobrança como paga + quita títulos + dispara CobrancaPaga.
     *
     * Devolve true quando ESTA chamada fez a transição para `paga`; false quando
     * a cobrança já estava paga (ou não existe mais no business informado).
     *
     * Idempotência (auditoria PG lote 2.1): webhook e polling podem chegar juntos
     * com a mesma cobrança. A linha é relida com lockForUpdate dentro de uma
     * transação, e a decisão "já paga?" sai do valor TRAVADO no banco, não da
     * instância em memória do caller (que pode estar velha). Já paga → nada é
     * gravado e `CobrancaPaga` NÃO é redisparado: os listeners (venda, Financeiro,
     * RecurringBilling, Superadmin) recebem o evento uma vez por cobrança.
     *
     * O evento continua sendo despachado dentro da transação, como antes: se um
     * listener síncrono falha, a marcação de paga volta junto (o caller costuma
     * envolver em DB::transaction; aqui vira savepoint).
     */
    public function marcarPaga(
        Cobranca $cobranca,
        int $businessId,
        int $valorPagoCentavos,
        \DateTimeImmutable $pagaEm,
        string $formaPagamento = 'pix',
    ): bool {
        return DB::transaction(function () use ($cobranca, $businessId, $valorPagoCentavos, $pagaEm, $formaPagamento): bool {
            // SUPERADMIN: chamado por job/command sem sessão; relê a cobrança filtrando pelo business_id explícito do caller.
            $travada = Cobranca::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->whereKey($cobranca->getKey())
                ->lockForUpdate()
                ->first();

            if ($travada === null) {
                return false;
            }

            if ($travada->getAttribute('status') === 'paga') {
                $cobranca->setRawAttributes($travada->getAttributes(), true);

                return false;
            }

            $travada->update([
                'status'              => 'paga',
                'valor_pago_centavos' => $valorPagoCentavos,
                'paga_em'             => $pagaEm,
                'forma_pagamento'     => $formaPagamento,
            ]);
            $cobranca->setRawAttributes($travada->getAttributes(), true);

            $this->marcarTitulosQuitados($businessId, (int) $travada->getKey());

            // getAttribute() em vez de propriedade mágica: o Larastan não conhece
            // as colunas do Eloquent e o ratchet PHPStan barra acesso direto novo.
            $payerCpfCnpj = $travada->getAttribute('payer_cpf_cnpj');
            $origemType = $travada->getAttribute('origem_type');
            $origemId = $travada->getAttribute('origem_id');

            event(new CobrancaPaga(
                cobrancaId: (int) $travada->getKey(),
                businessId: $businessId,
                valorPagoCentavos: $valorPagoCentavos,
                pagaEm: $pagaEm,
                formaPagamento: $formaPagamento,
                occurredAt: new \DateTimeImmutable(),
                payerCpfCnpj: $payerCpfCnpj !== null ? (string) $payerCpfCnpj : null,
                origemType: $origemType !== null ? (string) $origemType : null,
                origemId: $origemId !== null ? (int) $origemId : null,
            ));

            return true;
        });
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
