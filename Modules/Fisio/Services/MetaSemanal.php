<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use InvalidArgumentException;

/** Meta semanal (`fisio_patients.sessions_per_week`): sempre 1..7. Nenhuma conta divide por zero. */
final class MetaSemanal
{
    public static function validar(int $sessionsPerWeek): void
    {
        if ($sessionsPerWeek < 1 || $sessionsPerWeek > 7) {
            throw new InvalidArgumentException("sessions_per_week deve estar em 1..7 (veio {$sessionsPerWeek}).");
        }
    }
}
