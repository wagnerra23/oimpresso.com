<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

/**
 * Missões do dia (US-FISIO-008, T-08c, etapa 02 §3.4) — serviço PURO.
 *
 * O pool nasce SÓ do programa prescrito: cada missão aponta para um item do programa ou para o
 * registro do dia. Não existe missão de volume ("faça mais", "bata seu recorde", "dois dias sem
 * descanso"): nenhum tipo do pool pede além do que o profissional prescreveu.
 *
 *   registro  "Registre como foi hoje"                     cumprida com o check-in do dia
 *   exercicio "Faça {exercício} do seu programa"           cumprida com o item marcado como feito
 *   series    "Faça todas as séries de {exercício}"        só para item com times_per_day ≥ 2;
 *                                                          cumprida com as vezes prescritas
 *
 * Fora do pool por falta de fonte (decisão [W] pendente, jogo-constancia.casos.md §Backlog):
 * "Veja a dica do profissional" (não há texto de dica aprovado) e "Faça no horário combinado"
 * (não há horário combinado por paciente).
 *
 * Sorteio determinístico por (empresa, paciente, dia): o paciente vê as mesmas missões o dia
 * todo, e o reenvio do check-in avalia as mesmas. Contrato: Tests/Unit/MissoesCalculatorTest.php.
 */
final class MissoesCalculator
{
    public const POR_DIA = 3;

    public const TIPO_REGISTRO = 'registro';

    public const TIPO_EXERCICIO = 'exercicio';

    public const TIPO_SERIES = 'series';

    /**
     * @param  list<array{id:int, nome:string, times_per_day:int}>  $prescritos
     * @return list<array{chave:string, tipo:string, item_id:?int, texto:string}>
     */
    public function pool(array $prescritos): array
    {
        $pool = [['chave' => self::TIPO_REGISTRO, 'tipo' => self::TIPO_REGISTRO, 'item_id' => null, 'texto' => 'Registre como foi hoje']];
        foreach ($prescritos as $i) {
            $id = (int) $i['id'];
            $pool[] = ['chave' => self::TIPO_EXERCICIO.':'.$id, 'tipo' => self::TIPO_EXERCICIO, 'item_id' => $id, 'texto' => "Faça {$i['nome']} do seu programa"];
            if ((int) $i['times_per_day'] >= 2) {
                $pool[] = ['chave' => self::TIPO_SERIES.':'.$id, 'tipo' => self::TIPO_SERIES, 'item_id' => $id, 'texto' => "Faça todas as séries de {$i['nome']}"];
            }
        }

        return $pool;
    }

    /**
     * As missões do dia: até POR_DIA do pool, em ordem estável para a mesma semente.
     *
     * @param  list<array{id:int, nome:string, times_per_day:int}>  $prescritos
     * @return list<array{chave:string, tipo:string, item_id:?int, texto:string}>
     */
    public function doDia(array $prescritos, string $semente): array
    {
        $pool = $this->pool($prescritos);
        usort($pool, fn (array $a, array $b) => [crc32($semente.'|'.$a['chave']), $a['chave']] <=> [crc32($semente.'|'.$b['chave']), $b['chave']]);

        return array_slice($pool, 0, self::POR_DIA);
    }

    /**
     * Cumprida pelo relato do dia? Vezes acima do prescrito não contam a mais (só se exige o
     * prescrito); item fora do programa nunca cumpre missão, porque missão só aponta para item do pool.
     *
     * @param  array{chave:string, tipo:string, item_id:?int}  $missao
     * @param  array<int,int>  $vezesFeitas  item_id => vezes (só itens marcados como feitos)
     * @param  array<int,int>  $vezesPrescritas  item_id => times_per_day
     */
    public function cumprida(array $missao, bool $registrou, array $vezesFeitas, array $vezesPrescritas): bool
    {
        $id = $missao['item_id'];

        return match ($missao['tipo']) {
            self::TIPO_REGISTRO => $registrou,
            self::TIPO_EXERCICIO => $id !== null && ($vezesFeitas[$id] ?? 0) >= 1,
            self::TIPO_SERIES => $id !== null && isset($vezesPrescritas[$id]) && ($vezesFeitas[$id] ?? 0) >= $vezesPrescritas[$id],
            default => false,
        };
    }
}
