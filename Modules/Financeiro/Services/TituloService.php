<?php

namespace Modules\Financeiro\Services;

use Modules\Financeiro\Contracts\BoletoStrategy;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;

/**
 * Orquestrador do ciclo de vida de Titulo + emissao/cancelamento de boleto.
 *
 * Camada Controller -> TituloService -> BoletoStrategy.
 * Controllers nao conhecem strategy; o binding e resolvido no
 * FinanceiroServiceProvider (default: CnabDirectStrategy).
 */
class TituloService
{
    public function __construct(private BoletoStrategy $strategy)
    {
    }

    /**
     * Emite boleto pra um titulo. Resolve a conta bancaria do titulo
     * (se conta_bancaria_id explicito ou default ativa do business).
     */
    public function emitirBoleto(Titulo $titulo, ?int $contaBancariaId = null): BoletoRemessa
    {
        $conta = $this->resolverConta($titulo, $contaBancariaId);

        return $this->emitirBoletoComConta($titulo, $conta);
    }

    /**
     * Variante explicita — recebe conta direto, pula resolucao.
     * Util pra testes e cenarios em que a conta ja esta resolvida.
     */
    public function emitirBoletoComConta(Titulo $titulo, ContaBancaria $conta): BoletoRemessa
    {
        return $this->strategy->emitir($titulo, $conta);
    }

    public function cancelarBoleto(BoletoRemessa $remessa, string $motivo = ''): void
    {
        $this->strategy->cancelar($remessa, $motivo);
    }

    public function statusBoleto(BoletoRemessa $remessa): string
    {
        return $this->strategy->statusAtual($remessa);
    }

    /**
     * Resolve qual conta usar para emissao:
     *  - se $contaBancariaId explicito: busca essa conta (validando business_id + ativo_para_boleto)
     *  - default: primeira conta ativa para boleto do business
     */
    private function resolverConta(Titulo $titulo, ?int $contaBancariaId): ContaBancaria
    {
        if ($contaBancariaId !== null) {
            $conta = ContaBancaria::where('business_id', $titulo->business_id)
                ->where('id', $contaBancariaId)
                ->where('ativo_para_boleto', true)
                ->first();

            if (! $conta) {
                throw new \DomainException(
                    "Conta bancaria {$contaBancariaId} nao encontrada, ".
                    'fora do business ou inativa para boleto.'
                );
            }

            return $conta;
        }

        $conta = ContaBancaria::where('business_id', $titulo->business_id)
            ->where('ativo_para_boleto', true)
            ->orderBy('id')
            ->first();

        if (! $conta) {
            throw new \DomainException(
                "Business {$titulo->business_id} nao tem conta bancaria configurada para boleto. ".
                'Cadastre uma em /financeiro/contas-bancarias.'
            );
        }

        return $conta;
    }
}
