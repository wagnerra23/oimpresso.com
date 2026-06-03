<?php

namespace Modules\Financeiro\Strategies;

use Modules\Financeiro\Contracts\BoletoStrategy;
use Modules\Financeiro\Models\BoletoRemessa;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;

/**
 * Resolver que delega pra strategy concreta com base na ContaBancaria.
 *
 * Regra atual:
 *  - banco_codigo='077' + tem client_id + tem certificado_path → InterApiStrategy
 *  - resto → CnabDirectStrategy (mock offline, status='gerado_mock')
 *
 * Em ondas futuras adicionar:
 *  - GatewayStrategy (Asaas/Iugu) com discriminador metadata['gateway']
 *  - mais bancos com API própria (Sicoob, Caixa, BB...)
 */
class BoletoStrategyResolver implements BoletoStrategy
{
    public function __construct(
        private InterApiStrategy $inter,
        private CnabDirectStrategy $cnab
    ) {
    }

    public function emitir(Titulo $titulo, ContaBancaria $conta): BoletoRemessa
    {
        return $this->resolve($conta)->emitir($titulo, $conta);
    }

    public function cancelar(BoletoRemessa $remessa, string $motivo = ''): void
    {
        $this->resolveByStrategyName($remessa)->cancelar($remessa, $motivo);
    }

    public function statusAtual(BoletoRemessa $remessa): string
    {
        return $this->resolveByStrategyName($remessa)->statusAtual($remessa);
    }

    public function resolve(ContaBancaria $conta): BoletoStrategy
    {
        if ($this->ehInterApi($conta)) {
            return $this->inter;
        }

        return $this->cnab;
    }

    private function resolveByStrategyName(BoletoRemessa $remessa): BoletoStrategy
    {
        if ($remessa->strategy === BoletoRemessa::STRATEGY_GATEWAY) {
            return $this->inter;
        }

        return $this->cnab;
    }

    private function ehInterApi(ContaBancaria $conta): bool
    {
        return $conta->banco_codigo === '077'
            && filled($conta->inter_client_id_encrypted)
            && filled($conta->certificado_path)
            && filled($conta->certificado_chave_path);
    }
}
