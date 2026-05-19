<?php

namespace Modules\PaymentGateway\Exceptions;

/**
 * Emissor recusou cobrança em cartão (saldo, antifraude, validade).
 * NÃO é retry imediato — humano tenta outro cartão ou método.
 */
class CardDeclinedException extends PaymentGatewayException
{
}
