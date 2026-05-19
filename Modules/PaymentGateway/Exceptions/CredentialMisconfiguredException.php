<?php

namespace Modules\PaymentGateway\Exceptions;

/**
 * Credencial de gateway está em estado inválido: cert vencido, secret
 * rotacionado, escopo OAuth insuficiente. NÃO é retry-safe — humano resolve.
 */
class CredentialMisconfiguredException extends PaymentGatewayException
{
}
