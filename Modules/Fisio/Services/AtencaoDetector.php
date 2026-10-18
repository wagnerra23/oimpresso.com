<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use InvalidArgumentException;

/**
 * "Precisa de atenção" do painel (US-FISIO-010) — serviço PURO.
 *
 * Só SINALIZA para o profissional: não muda programa, não manda mensagem, não sugere
 * conduta (ADR 0437 §5 — fora do escopo de SaMD). Motivos, nesta ordem e sem repetição:
 *   'dor_subiu'       — entre os 2 últimos registros COM dor informada, subiu ≥ 2.
 *   'dor_alta'        — último registro com dor informada ≥ limiar (padrão 7; o do programa).
 *   'sem_registro'    — programa ativo e ≥ 4 dias desde o último check-in (ou desde
 *                       starts_at, se nunca registrou ou se o programa começou depois).
 *   'sinal_de_alerta' — o último check-in tem red_flags.
 * Sem programa ativo ($programaStartsAt null): 'sem_registro' nunca.
 *
 * As filas são SEPARADAS (separar()): alerta clínico (dor/sinal) ≠ abandono (sem registro).
 *
 * Contrato: Modules/Fisio/Tests/Unit/AtencaoDetectorTest.php.
 */
final class AtencaoDetector
{
    public const LIMIAR_DOR_PADRAO = 7;

    public const SUBIDA_MINIMA_DOR = 2;

    public const DIAS_SEM_REGISTRO = 4;

    public const MOTIVOS_CLINICOS = ['dor_subiu', 'dor_alta', 'sinal_de_alerta'];

    public const MOTIVOS_ABANDONO = ['sem_registro'];

    private const ORDEM = ['dor_subiu', 'dor_alta', 'sem_registro', 'sinal_de_alerta'];

    /**
     * @param  list<array{date:string, pain:?int, red_flags?:list<string>}>  $checkins
     * @param  ?string  $programaStartsAt  'Y-m-d' do programa ativo; null = sem programa ativo
     * @return list<string>
     *
     * @throws InvalidArgumentException dia mal formado
     */
    public function motivos(array $checkins, ?string $programaStartsAt, string $hoje, int $limiarDor = self::LIMIAR_DOR_PADRAO): array
    {
        DiaLocal::parse($hoje);

        $ordenados = [];
        foreach ($checkins as $c) {
            $dia = DiaLocal::parse((string) $c['date'])->format('Y-m-d');
            if ($dia <= $hoje) {
                $ordenados[] = ['date' => $dia, 'pain' => $c['pain'] ?? null, 'red_flags' => $c['red_flags'] ?? []];
            }
        }
        usort($ordenados, fn (array $a, array $b) => strcmp($a['date'], $b['date']));

        $motivos = [];
        $dores = array_values(array_filter(array_column($ordenados, 'pain'), fn ($p) => $p !== null));
        $n = count($dores);
        if ($n >= 2 && (int) $dores[$n - 1] - (int) $dores[$n - 2] >= self::SUBIDA_MINIMA_DOR) {
            $motivos[] = 'dor_subiu';
        }
        if ($n >= 1 && (int) $dores[$n - 1] >= $limiarDor) {
            $motivos[] = 'dor_alta';
        }

        if ($programaStartsAt !== null) {
            $inicio = DiaLocal::parse($programaStartsAt)->format('Y-m-d');
            $ultimo = $ordenados === [] ? null : $ordenados[count($ordenados) - 1]['date'];
            $referencia = $ultimo === null ? $inicio : max($ultimo, $inicio);
            if (DiaLocal::diferenca($referencia, $hoje) >= self::DIAS_SEM_REGISTRO) {
                $motivos[] = 'sem_registro';
            }
        }

        if ($ordenados !== [] && ! empty($ordenados[count($ordenados) - 1]['red_flags'])) {
            $motivos[] = 'sinal_de_alerta';
        }

        return array_values(array_intersect(self::ORDEM, $motivos));
    }

    /**
     * Separa os motivos nas duas filas do painel. Um paciente pode estar nas duas.
     *
     * @param  list<string>  $motivos
     * @return array{clinico: list<string>, abandono: list<string>}
     */
    public function separar(array $motivos): array
    {
        return [
            'clinico' => array_values(array_intersect($motivos, self::MOTIVOS_CLINICOS)),
            'abandono' => array_values(array_intersect($motivos, self::MOTIVOS_ABANDONO)),
        ];
    }
}
