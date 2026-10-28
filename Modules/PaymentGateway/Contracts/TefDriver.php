<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Contracts;

use Modules\PaymentGateway\Dto\TefRespostaTerminal;
use Modules\PaymentGateway\Models\TefTransacao;

/**
 * Driver de maquininha (TEF) — US-SELL-075.
 *
 * Separado do PaymentDriverContract porque é outro mundo: cobrança presencial,
 * o valor vai para um terminal físico e a resposta pode demorar (o cliente está
 * digitando a senha). Por isso o contrato tem dois passos: `iniciar` manda o
 * valor e pode devolver `pendente`; `consultar` é chamado até a resposta final.
 *
 * Drivers: `simulador` (homologação). O driver da adquirente real entra quando
 * o [W] escolher o integrador.
 */
interface TefDriver
{
    public function key(): string;

    public function iniciar(TefTransacao $tef): TefRespostaTerminal;

    public function consultar(TefTransacao $tef): TefRespostaTerminal;
}
