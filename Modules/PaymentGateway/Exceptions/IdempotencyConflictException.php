<?php

namespace Modules\PaymentGateway\Exceptions;

/**
 * Tentativa de reemitir cobrança com idempotency_key já registrada em
 * status paga|cancelada — terminal, não pode ser sobrescrito.
 * Retorne a Cobranca existente em vez de criar nova.
 */
class IdempotencyConflictException extends PaymentGatewayException
{
}
