<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Exceptions;

use RuntimeException;

/**
 * Action não existe pro current_stage_id, OU stage atual é terminal (ADR 0129).
 */
class InvalidActionForCurrentStageException extends RuntimeException
{
}
