<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use InvalidArgumentException;

/**
 * Sequência por DIAS PRESCRITOS (US-FISIO-008, ADR 0437 §6) — serviço PURO.
 *
 * folga(s)     = ceil(7 / sessions_per_week) - 1 → dias seguidos sem elo tolerados
 *                (s=7 → 0 · s=3 → 2 · s=2 → 3 · s=1 → 6). O descanso faz parte do tratamento.
 * Dia que SOMA = check-in com ≥1 exercício feito.
 * Dia de PAUSA = check-in com dor ≥ limiar OU sinal de alerta (red_flags). Não soma, mas é
 *                elo: não quebra e reinicia a contagem de folga ("pausa, não falta").
 *                Com exercício feito no mesmo dia, soma E marca pausa.
 * Check-in sem exercício e sem pausa: não é elo (conta como dia sem registro).
 *
 * De HOJE para trás, a corrente segue enquanto nenhum intervalo (dias sem elo estritamente
 * entre dois elos, ou entre o último elo e hoje, hoje excluído) ultrapassa a folga.
 * sequencia = nº de dias que somam na corrente viva; corrente morta → 0.
 * em_pausa  = a corrente está viva e o elo mais recente é dia de pausa.
 *
 * Não decide conduta: só conta (ADR 0437 §5, SaMD). Contrato:
 * Modules/Fisio/Tests/Unit/SequenciaCalculatorTest.php.
 */
final class SequenciaCalculator
{
    public const LIMIAR_DOR_PADRAO = 7;

    public static function folga(int $sessionsPerWeek): int
    {
        MetaSemanal::validar($sessionsPerWeek);

        return (int) ceil(7 / $sessionsPerWeek) - 1;
    }

    /**
     * @param  list<array{date:string, done_count:int, pain:?int, red_flags?:list<string>}>  $checkins
     * @return array{sequencia:int, em_pausa:bool}
     *
     * @throws InvalidArgumentException sessions_per_week fora de 1..7, ou dia mal formado
     */
    public function calcular(array $checkins, int $sessionsPerWeek, string $hoje, int $limiarDor = self::LIMIAR_DOR_PADRAO): array
    {
        $folga = self::folga($sessionsPerWeek);
        DiaLocal::parse($hoje);

        /** @var array<string, array{soma:bool, pausa:bool}> $elos */
        $elos = [];
        foreach ($checkins as $c) {
            $dia = DiaLocal::parse((string) $c['date'])->format('Y-m-d');
            if ($dia > $hoje) {
                continue;
            }
            $soma = (int) ($c['done_count'] ?? 0) >= 1;
            $dor = $c['pain'] ?? null;
            $pausa = ($dor !== null && (int) $dor >= $limiarDor) || ! empty($c['red_flags'] ?? []);
            if (! $soma && ! $pausa) {
                continue;
            }
            $antes = $elos[$dia] ?? ['soma' => false, 'pausa' => false];
            $elos[$dia] = ['soma' => $antes['soma'] || $soma, 'pausa' => $antes['pausa'] || $pausa];
        }

        krsort($elos);
        $sequencia = 0;
        $emPausa = false;
        $referencia = $hoje;
        $primeiro = true;
        foreach ($elos as $dia => $elo) {
            // Dias sem elo estritamente entre $dia e $referencia (elo de hoje → 0).
            $intervalo = max(0, DiaLocal::diferenca($dia, $referencia) - 1);
            if ($intervalo > $folga) {
                break;
            }
            if ($primeiro) {
                $emPausa = $elo['pausa'];
                $primeiro = false;
            }
            if ($elo['soma']) {
                $sequencia++;
            }
            $referencia = $dia;
        }

        return ['sequencia' => $sequencia, 'em_pausa' => $emPausa];
    }
}
