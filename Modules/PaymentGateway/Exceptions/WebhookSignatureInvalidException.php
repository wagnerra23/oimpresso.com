<?php

namespace Modules\PaymentGateway\Exceptions;

/**
 * Assinatura HMAC do webhook não confere com secret esperado.
 * Sinal forte de tentativa de spoofing — log + alerta SOC.
 * NUNCA processar payload de webhook que jogou essa exceção.
 */
class WebhookSignatureInvalidException extends PaymentGatewayException
{
}
