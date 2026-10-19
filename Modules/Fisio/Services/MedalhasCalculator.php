<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

/**
 * Medalhas (US-FISIO-008, T-08c, etapa 02 §3.4) — serviço PURO, DERIVADO.
 *
 * Medalha não é XP (vale 0) e não tem coluna: sai do histórico de check-ins e do nível, que são
 * imutáveis para trás. Por isso uma medalha ganha não some quando a sequência quebra — o critério
 * é "já aconteceu", nunca "está acontecendo".
 *
 * 10 das 12 da §3.4. Fora por falta de fonte (decisão [W] pendente): "Horário combinado 10 vezes"
 * (não há horário combinado) e "Programa concluído (alta)" (não há estado de alta no programa).
 *
 * Semana completa = semana (segunda a domingo) com dias que somam ≥ ceil(80% × meta semanal).
 * Voltou depois de pausa = dia que soma, depois de um dia em pausa (dor ≥ limiar ou alerta).
 * Contrato: Tests/Unit/MedalhasCalculatorTest.php.
 */
final class MedalhasCalculator
{
    /** código => nome mostrado ao paciente, na ordem da §3.4 */
    public const MEDALHAS = [
        'primeiro_registro' => 'Primeiro registro',
        'semana_completa_1' => '1ª semana completa',
        'semanas_completas_4' => '4 semanas completas',
        'sequencia_7' => 'Sequência 7',
        'sequencia_21' => 'Sequência 21',
        'sequencia_42' => 'Sequência 42',
        'voltou_depois_de_pausa' => 'Voltou depois de pausa',
        'registro_honesto' => 'Registro honesto',
        'nivel_5' => 'Nível 5',
        'nivel_10' => 'Nível 10',
    ];

    public const REGISTROS_HONESTOS = 10;

    public function __construct(private readonly SequenciaCalculator $sequencia) {}

    /**
     * @param  list<array{date:string, done_count:int, pain:?int, red_flags?:list<string>, crise?:bool}>  $checkins  até hoje
     * @return list<string> códigos ganhos, na ordem de MEDALHAS
     */
    public function ganhas(array $checkins, int $sessionsPerWeek, int $limiarDor, int $nivel): array
    {
        $soma = fn (array $c) => $c['done_count'] >= 1 || ! empty($c['crise'] ?? false);
        $pausa = fn (array $c) => ($c['pain'] !== null && $c['pain'] >= $limiarDor) || ! empty($c['red_flags'] ?? []);
        usort($checkins, fn (array $a, array $b) => strcmp($a['date'], $b['date']));

        $semanas = [];
        $voltou = false;
        $houvePausa = false;
        $honestos = 0;
        foreach ($checkins as $c) {
            if ($soma($c)) {
                $seg = DiaLocal::somar($c['date'], 1 - (int) DiaLocal::parse($c['date'])->format('N'));
                $semanas[$seg][$c['date']] = true;
                $voltou = $voltou || $houvePausa;
            }
            $houvePausa = $houvePausa || $pausa($c);
            $honestos += $c['pain'] !== null ? 1 : 0;
        }
        $minimo = (int) ceil(0.8 * $sessionsPerWeek);
        $completas = count(array_filter($semanas, fn (array $dias) => count($dias) >= $minimo));
        $melhor = $this->melhorSequencia($checkins, $sessionsPerWeek, $limiarDor);

        $criterio = [
            'primeiro_registro' => $checkins !== [],
            'semana_completa_1' => $completas >= 1,
            'semanas_completas_4' => $completas >= 4,
            'sequencia_7' => $melhor >= 7,
            'sequencia_21' => $melhor >= 21,
            'sequencia_42' => $melhor >= 42,
            'voltou_depois_de_pausa' => $voltou,
            'registro_honesto' => $honestos >= self::REGISTROS_HONESTOS,
            'nivel_5' => $nivel >= 5,
            'nivel_10' => $nivel >= 10,
        ];

        return array_keys(array_filter($criterio));
    }

    /**
     * Melhor sequência já vivida: a sequência do SequenciaCalculator medida em cada dia de
     * check-in. A regra de quebra é a mesma da sequência de hoje.
     *
     * @param  list<array{date:string, done_count:int, pain:?int, red_flags?:list<string>, crise?:bool}>  $checkins
     */
    public function melhorSequencia(array $checkins, int $sessionsPerWeek, int $limiarDor): int
    {
        $melhor = 0;
        foreach (array_unique(array_column($checkins, 'date')) as $dia) {
            $melhor = max($melhor, $this->sequencia->calcular($checkins, $sessionsPerWeek, (string) $dia, $limiarDor)['sequencia']);
        }

        return $melhor;
    }
}
