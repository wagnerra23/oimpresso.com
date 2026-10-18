<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use InvalidArgumentException;

/**
 * Adesão dos últimos 7 dias (US-FISIO-010, painel) — serviço PURO: sem banco, sem sessão,
 * sem relógio global (o "hoje" entra por parâmetro, já no fuso do paciente — DiaLocal::de).
 *
 * adesão = nº de DIAS distintos em [hoje-6, hoje] com check-in que tem ≥1 exercício feito
 *          ÷ sessions_per_week, ×100, arredondado meio para cima, teto 100.
 * Sem programa ativo → null ("sem meta" ≠ "não cumpriu"; não derruba a média do painel).
 *
 * Contrato: Modules/Fisio/Tests/Unit/AdesaoCalculatorTest.php.
 */
final class AdesaoCalculator
{
    public const JANELA_DIAS = 7;

    /**
     * @param  list<array{date:string, done_count:int}>  $checkins
     * @param  string  $hoje  dia civil do paciente, 'Y-m-d'
     *
     * @throws InvalidArgumentException sessions_per_week fora de 1..7, ou dia mal formado
     */
    public function calcular(array $checkins, int $sessionsPerWeek, string $hoje, bool $programaAtivo = true): ?int
    {
        MetaSemanal::validar($sessionsPerWeek);
        DiaLocal::parse($hoje);
        if (! $programaAtivo) {
            return null;
        }

        $inicio = DiaLocal::somar($hoje, -(self::JANELA_DIAS - 1));
        $dias = [];
        foreach ($checkins as $c) {
            $dia = DiaLocal::parse((string) $c['date'])->format('Y-m-d');
            if ($c['done_count'] >= 1 && $dia >= $inicio && $dia <= $hoje) {
                $dias[$dia] = true;
            }
        }

        $pct = (int) round(count($dias) * 100 / $sessionsPerWeek, 0, PHP_ROUND_HALF_UP);

        return min(100, $pct);
    }
}
