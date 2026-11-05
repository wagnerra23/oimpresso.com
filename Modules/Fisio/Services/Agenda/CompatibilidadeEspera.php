<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Agenda;

use Carbon\CarbonInterface;

/**
 * Quem da lista de espera serve numa vaga (etapa 04 A7). Regra pura, sem banco.
 *
 * Preferência vazia aceita qualquer coisa. `dias` = dias ISO (1=seg..7=dom); `turnos` =
 * manha (antes de 12h), tarde (12h–18h), noite (18h em diante); `ate` = última data aceita.
 */
final class CompatibilidadeEspera
{
    /** @param  array{dias?: list<int>, turnos?: list<string>, ate?: string}|null  $pref */
    public static function serve(?array $pref, CarbonInterface $vaga): bool
    {
        $pref ??= [];
        if (! empty($pref['dias']) && ! in_array($vaga->dayOfWeekIso, array_map('intval', $pref['dias']), true)) {
            return false;
        }
        if (! empty($pref['turnos']) && ! in_array(self::turno($vaga), $pref['turnos'], true)) {
            return false;
        }

        return empty($pref['ate']) || $vaga->toDateString() <= $pref['ate'];
    }

    public static function turno(CarbonInterface $t): string
    {
        return match (true) {
            $t->hour < 12 => 'manha',
            $t->hour < 18 => 'tarde',
            default => 'noite',
        };
    }
}
