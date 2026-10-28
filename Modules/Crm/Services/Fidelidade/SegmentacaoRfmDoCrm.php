<?php

declare(strict_types=1);

namespace Modules\Crm\Services\Fidelidade;

use App\Contracts\Crm\SegmentacaoRfm;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Segmentação RFM (US-CRM-098): recência, frequência e valor por cliente, com segmentos que a
 * campanha e a régua de relacionamento usam pelo contrato `App\Contracts\Crm\SegmentacaoRfm`.
 *
 * Medidas, por cliente da empresa com venda `sell` final:
 *   R = dias desde a última compra (qualquer data)
 *   F = número de vendas nos últimos 365 dias
 *   M = soma de final_total dessas vendas (DECIMAL no MySQL)
 * Notas de 1 a 5 por quintil DENTRO da empresa: nota = 1 + ⌊5 × (quantos têm valor pior) / n⌋.
 * Valores iguais ficam com a mesma nota. Na recência, menos dias é melhor.
 *
 * Segmento, nesta ordem (FM = média de F e M arredondada para cima):
 *   campeoes   R ≥ 4 e FM ≥ 4
 *   novos      R ≥ 4 e uma compra só no ano
 *   leais      R ≥ 3 e FM ≥ 3
 *   promissores R ≥ 3
 *   em_risco   R ≤ 2 e FM ≥ 3
 *   hibernando R = 2
 *   perdidos   R = 1
 *
 * O cálculo só lê vendas; grava em `crm_rfm_clientes` (substitui o cálculo anterior da empresa).
 * Tier 0 (ADR 0093): tudo filtrado pela empresa recebida.
 */
final class SegmentacaoRfmDoCrm implements SegmentacaoRfm
{
    public const SEGMENTOS = [
        'campeoes' => 'Campeões',
        'novos' => 'Novos',
        'leais' => 'Leais',
        'promissores' => 'Promissores',
        'em_risco' => 'Em risco',
        'hibernando' => 'Hibernando',
        'perdidos' => 'Perdidos',
    ];

    public function segmentos(): array
    {
        return self::SEGMENTOS;
    }

    public function contatosDoSegmento(int $businessId, string $segmento): array
    {
        return DB::table('crm_rfm_clientes')
            ->where('business_id', $businessId)->where('segmento', $segmento)
            ->orderBy('contact_id')->pluck('contact_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array<string, int> quantos clientes por segmento */
    public function calcular(int $businessId, CarbonImmutable $hoje): array
    {
        $inicioAno = $hoje->subDays(365)->startOfDay()->toDateTimeString();
        $linhas = DB::table('transactions as t')
            ->join('contacts as c', fn ($j) => $j->on('c.id', '=', 't.contact_id')->on('c.business_id', '=', 't.business_id'))
            ->where('t.business_id', $businessId)->where('t.type', 'sell')->where('t.status', 'final')
            ->whereIn('c.type', ['customer', 'both'])->where('c.is_default', 0)->whereNull('c.deleted_at')
            ->groupBy('t.contact_id')
            ->selectRaw('t.contact_id, DATE(MAX(t.transaction_date)) AS ultima,
                SUM(t.transaction_date >= ?) AS freq,
                CAST(COALESCE(SUM(IF(t.transaction_date >= ?, t.final_total, 0)), 0) AS DECIMAL(22,4)) AS valor', [$inicioAno, $inicioAno])
            ->get();

        $n = $linhas->count();
        $dias = $linhas->mapWithKeys(fn ($l) => [$l->contact_id => CarbonImmutable::parse($l->ultima)->diffInDays($hoje->startOfDay())]);
        $notaR = self::quintis($dias->map(fn ($d) => -$d)->all(), $n);
        $notaF = self::quintis($linhas->mapWithKeys(fn ($l) => [$l->contact_id => (int) $l->freq])->all(), $n);
        $notaM = self::quintis($linhas->mapWithKeys(fn ($l) => [$l->contact_id => (float) $l->valor])->all(), $n);

        $agora = now();
        $registros = $linhas->map(function ($l) use ($businessId, $dias, $notaR, $notaF, $notaM, $agora) {
            $id = $l->contact_id;
            $r = $notaR[$id];
            $fm = (int) ceil(($notaF[$id] + $notaM[$id]) / 2);

            return [
                'business_id' => $businessId, 'contact_id' => (int) $id,
                'recencia_dias' => (int) $dias[$id], 'frequencia' => (int) $l->freq, 'valor' => (string) $l->valor,
                'nota_r' => $r, 'nota_f' => $notaF[$id], 'nota_m' => $notaM[$id],
                'segmento' => self::segmento($r, $fm, (int) $l->freq), 'calculado_em' => $agora,
            ];
        })->values();

        DB::transaction(function () use ($businessId, $registros) {
            DB::table('crm_rfm_clientes')->where('business_id', $businessId)->delete();
            foreach ($registros->chunk(500) as $lote) {
                DB::table('crm_rfm_clientes')->insert($lote->all());
            }
        });

        return $registros->countBy('segmento')->all();
    }

    public static function segmento(int $r, int $fm, int $frequenciaNoAno): string
    {
        return match (true) {
            $r >= 4 && $fm >= 4 => 'campeoes',
            $r >= 4 && $frequenciaNoAno === 1 => 'novos',
            $r >= 3 && $fm >= 3 => 'leais',
            $r >= 3 => 'promissores',
            $fm >= 3 => 'em_risco',
            $r === 2 => 'hibernando',
            default => 'perdidos',
        };
    }

    /**
     * @param  array<int|string, int|float>  $valores  maior = melhor
     * @return array<int|string, int> nota 1..5
     */
    public static function quintis(array $valores, int $n): array
    {
        $ordenados = array_values($valores);
        sort($ordenados);
        $notas = [];
        foreach ($valores as $chave => $v) {
            $piores = 0;
            foreach ($ordenados as $o) {
                if ($o >= $v) {
                    break;
                }
                $piores++;
            }
            $notas[$chave] = min(5, 1 + intdiv(5 * $piores, max(1, $n)));
        }

        return $notas;
    }
}
