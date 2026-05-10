<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Exceptions;

use RuntimeException;

/**
 * User não tem nenhuma das roles exigidas pela action, OU subject business_id
 * diverge do process business_id (cross-tenant attempt) (ADR 0129 + 0093).
 */
class UnauthorizedActionException extends RuntimeException
{
}
