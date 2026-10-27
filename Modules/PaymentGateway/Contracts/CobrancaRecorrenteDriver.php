<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Contracts;

use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;

/**
 * Driver que emite a cobrança de UM ciclo de uma recorrência já autorizada (Pix Automático).
 *
 * Na API Pix do BCB (v2.10.0) é o recebedor quem cria cada cobrança do ciclo (`PUT /cobr/{txid}`),
 * de 10 a 2 dias antes do vencimento, apontando o `idRec` do mandato. Fica numa interface própria
 * para não obrigar os drivers que não fazem Pix Automático a implementar um método que lança.
 */
interface CobrancaRecorrenteDriver
{
    /** @param string $idRec id da recorrência (mandato) já aprovada pelo pagador */
    public function emitirCobrancaRecorrente(EmitirCobrancaInput $input, object $cred, string $idRec): CobrancaEmitidaResult;
}
