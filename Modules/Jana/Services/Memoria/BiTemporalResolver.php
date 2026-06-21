<?php

namespace Modules\Jana\Services\Memoria;

use Illuminate\Support\Carbon;

/**
 * BiTemporalResolver — predicado de EVENT-TIME da memoria Jana (ADR 0295, T4 slice 1).
 *
 * Bi-temporal (Zep/Graphiti): alem do system-time (valid_from/valid_until — quando
 * o SISTEMA soube), o event-time (event_valid_from/until — quando o fato valeu NO
 * MUNDO). Time-travel "as_of T" = quais fatos eram EVENT-validos no instante T.
 *
 * Logica PURA (testavel no CI sem DB). A traducao pra SQL (buscarHistorico) e a
 * tool MCP memoria-historica ficam pro slice 2. FAILSAFE: parse tolerante (nunca lanca).
 */
class BiTemporalResolver
{
    /**
     * Fato era event-valido em $asOf?
     * Inicio inclusivo, fim exclusivo. Null-from = "desde sempre"; null-until = "ainda vale".
     *
     * @param  mixed  $from   event_valid_from (Carbon|DateTimeInterface|string|null)
     * @param  mixed  $until  event_valid_until
     * @param  mixed  $asOf   instante-mundo de referencia
     */
    public static function vigenteEm($from, $until, $asOf): bool
    {
        $asOf = self::ts($asOf);
        if ($asOf === null) {
            return false; // sem instante de referencia, nada a afirmar
        }

        $from = self::ts($from);
        $until = self::ts($until);

        $depoisDoInicio = $from === null || $from <= $asOf;   // inclusivo no inicio
        $antesDoFim     = $until === null || $until > $asOf;   // exclusivo no fim

        return $depoisDoInicio && $antesDoFim;
    }

    /** Normaliza pra timestamp (segundos) ou null. FAILSAFE — nao lanca. */
    private static function ts($v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->getTimestamp();
        }
        try {
            return Carbon::parse((string) $v)->getTimestamp();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
