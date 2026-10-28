<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Services\Tef;

use Modules\PaymentGateway\Contracts\TefDriver;
use Modules\PaymentGateway\Dto\TefRespostaTerminal;
use Modules\PaymentGateway\Models\TefTransacao;

/**
 * Terminal simulado — homologação do fluxo do caixa sem adquirente.
 *
 * Imita o comportamento de um terminal em nuvem: `iniciar` devolve pendente
 * (o cliente ainda está na máquina) e `consultar` dá a resposta final.
 * Regra fixa para poder testar a recusa: valor terminado em 99 centavos é
 * negado; qualquer outro é aprovado.
 */
final class SimuladorTefDriver implements TefDriver
{
    public function key(): string
    {
        return 'simulador';
    }

    public function iniciar(TefTransacao $tef): TefRespostaTerminal
    {
        return new TefRespostaTerminal(
            status: TefTransacao::PENDENTE,
            externoId: 'SIM-'.$tef->id,
            mensagem: 'Aguardando o cliente na maquininha',
        );
    }

    public function consultar(TefTransacao $tef): TefRespostaTerminal
    {
        if ($tef->valor_centavos % 100 === 99) {
            return new TefRespostaTerminal(
                status: TefTransacao::NEGADA,
                externoId: 'SIM-'.$tef->id,
                mensagem: 'Transação negada pelo emissor (simulador)',
            );
        }

        return new TefRespostaTerminal(
            status: TefTransacao::APROVADA,
            bandeira: $tef->modalidade === 'pix' ? 'pix' : 'visa',
            nsu: str_pad((string) $tef->id, 9, '0', STR_PAD_LEFT),
            autorizacao: 'A'.str_pad((string) $tef->id, 5, '0', STR_PAD_LEFT),
            externoId: 'SIM-'.$tef->id,
            mensagem: 'Aprovada (simulador)',
        );
    }
}
