<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use InvalidArgumentException;

/**
 * XP do dia de um check-in (US-FISIO-008, ADR 0437 §6 e §13.3) — serviço PURO.
 *
 * Números de `etapas/02-f1-constancia.md` §3.4, que chegam por parâmetro (o app lê de
 * `config('fisio.jogo')`):
 *
 *   exercicio_concluido = xpExercicio × (vezes feitas ÷ vezes prescritas) × base ÷ n
 *   checkin             = xpCheckin × base ÷ n          — SEMPRE, com qualquer dor
 *
 * onde n = sessions_per_week (1..7) e base = normalizacao_dias_base (4). Cada item conta no
 * máximo `times_per_day` vezes e item fora da prescrição não conta: fazer além do prescrito
 * dá 0 (ADR 0437 §6). Arredondamento meio-para-cima, uma vez, no total do dia.
 *
 * O valor é o ALVO do dia. Quem lança no ledger (JogoService) grava só a diferença positiva
 * entre o alvo e o que já foi lançado no dia, limitada pelo teto semanal (aLancar()): o
 * ledger é append-only e nunca estorna.
 *
 * Contrato: Modules/Fisio/Tests/Unit/XpDoDiaCalculatorTest.php.
 */
final class XpDoDiaCalculator
{
    public const RAZAO_EXERCICIO = 'exercicio_concluido';

    public const RAZAO_CHECKIN = 'checkin';

    /**
     * @param  list<array{id:int, times_per_day:int}>  $prescritos  itens do programa do dia
     * @param  array<int,int>  $vezesFeitas  item_id => vezes feitas (só itens marcados como feitos)
     * @return array{exercicio_concluido:int, checkin:int}
     *
     * @throws InvalidArgumentException sessions_per_week fora de 1..7, base < 1 ou XP negativo
     */
    public function calcular(array $prescritos, array $vezesFeitas, int $sessionsPerWeek, int $xpExercicio, int $xpCheckin, int $baseNormalizacao): array
    {
        MetaSemanal::validar($sessionsPerWeek);
        if ($baseNormalizacao < 1) {
            throw new InvalidArgumentException("normalizacao_dias_base deve ser >= 1 (veio {$baseNormalizacao}).");
        }
        if ($xpExercicio < 0 || $xpCheckin < 0) {
            throw new InvalidArgumentException('XP configurado não pode ser negativo.');
        }

        $prescritas = 0;
        $validas = 0;
        foreach ($prescritos as $item) {
            $teto = max(0, (int) $item['times_per_day']);
            $prescritas += $teto;
            $validas += min($teto, max(0, (int) ($vezesFeitas[(int) $item['id']] ?? 0)));
        }

        $exercicio = $prescritas === 0
            ? 0
            : (int) round($xpExercicio * $validas * $baseNormalizacao / ($prescritas * $sessionsPerWeek), 0, PHP_ROUND_HALF_UP);

        return [
            self::RAZAO_EXERCICIO => $exercicio,
            self::RAZAO_CHECKIN => (int) round($xpCheckin * $baseNormalizacao / $sessionsPerWeek, 0, PHP_ROUND_HALF_UP),
        ];
    }

    /**
     * Quanto lançar agora: a diferença positiva entre o alvo e o já lançado no dia, por razão,
     * sem passar do teto semanal. O registro do dia entra antes do exercício — registrar com
     * honestidade é o que o jogo mais protege.
     *
     * @param  array{exercicio_concluido:int, checkin:int}  $alvo
     * @param  array<string,int>  $jaLancadoNoDia  razão => XP já no ledger nesta data
     * @param  int  $xpNaSemana  XP já no ledger na semana (segunda a domingo) desta data
     * @return array{checkin:int, exercicio_concluido:int} nunca negativo
     */
    public function aLancar(array $alvo, array $jaLancadoNoDia, int $xpNaSemana, int $tetoSemanal): array
    {
        $restante = max(0, $tetoSemanal - $xpNaSemana);
        $out = [self::RAZAO_CHECKIN => 0, self::RAZAO_EXERCICIO => 0];
        foreach (array_keys($out) as $razao) {
            $falta = max(0, (int) $alvo[$razao] - (int) ($jaLancadoNoDia[$razao] ?? 0));
            $out[$razao] = min($falta, $restante);
            $restante -= $out[$razao];
        }

        return $out;
    }
}