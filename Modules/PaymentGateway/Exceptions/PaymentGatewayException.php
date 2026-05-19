<?php

namespace Modules\PaymentGateway\Exceptions;

use RuntimeException;

/**
 * Raiz da hierarquia de exceções públicas do módulo PaymentGateway.
 *
 * Qualquer subclasse extends desta — listeners podem fazer catch genérico
 * sem precisar conhecer cada driver-specific exception.
 *
 * ADR 0170 — CONTRACTS.md §4.
 */
class PaymentGatewayException extends RuntimeException
{
}
