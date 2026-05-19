<?php

namespace Modules\PaymentGateway\Exceptions;

/**
 * Gateway externo respondeu 5xx, timeout, ou conexão recusada.
 * Operação pode ser retry-safe (depende do contexto — ver listener).
 */
class GatewayUnavailableException extends PaymentGatewayException
{
}
