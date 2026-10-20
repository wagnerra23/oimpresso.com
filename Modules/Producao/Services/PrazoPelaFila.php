<?php

declare(strict_types=1);

namespace Modules\Producao\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Producao\Entities\Ordem;

/**
 * Prazo de entrega calculado pela fila (UC-FLX-06 · US-PCP-028). Só leitura: não grava data na
 * venda nem mexe nas programações — quem grava, na aprovação, é o `PrometerPrazoDaVenda`.
 *
 * Para cada linha da venda (ou orçamento) cujo produto tem roteiro (`pcp_produto_roteiro`), as
 * etapas não opcionais entram em sequência por bloco; as trilhas de um bloco começam juntas e o
 * bloco termina quando a trilha mais longa termina (a mesma leitura do `CriarOsDaVenda`).
 *
 * A fila: cada posto começa livre só depois do que já está programado nele e ainda não acabou —
 * Σ (fim_previsto − inicio_previsto) das programações `pendente` e `iniciada` daquele posto, de
 * qualquer OS da empresa, menos as OS desta própria venda (senão ela entraria na fila de si mesma).
 * As etapas desta venda também ocupam o posto: a 2ª linha que passa no mesmo posto espera a 1ª.
 *
 * Tempo em dias de produção: horas da etapa ÷ `pcp_postos.horas_por_dia`. Posto sem horas por dia
 * (zero ou vazio) e etapa sem posto usam HORAS_DIA_PADRAO, e isso vem nas `premissas` da resposta.
 * Cada dia de produção vale um dia útil da empresa: a entrega é agora + dias × horas do turno,
 * contadas só dentro do turno, pulando folgas e feriados cadastrados (decisão [W] 2026-10-20 —
 * período de funcionamento da empresa, `CalendarioDeProducao`; feriados da loja da venda também).
 *
 * Tier 0: toda consulta filtra pelo `$businessId` recebido; venda de outra empresa → null (404).
 */
final class PrazoPelaFila
{
    public const HORAS_DIA_PADRAO = 8.0;

    public function __construct(private readonly CalendarioDeProducao $calendario) {}

    /** @return array<string, mixed>|null null quando a venda não é desta empresa (o controller responde 404) */
    public function daVenda(int $businessId, int $transactionId): ?array
    {
        $venda = DB::table('transactions')->where('business_id', $businessId)->where('id', $transactionId)
            ->where('type', 'sell')->first(['id', 'location_id']);
        if (! $venda) {
            return null;
        }

        $linhas = DB::table('transaction_sell_lines')->where('transaction_id', $transactionId)
            ->whereNull('parent_sell_line_id')->orderBy('id')->get(['id', 'product_id', 'quantity']);
        $roteiros = DB::table('pcp_produto_roteiro')->where('business_id', $businessId)
            ->whereIn('product_id', $linhas->pluck('product_id'))->pluck('roteiro_id', 'product_id');
        $etapas = DB::table('pcp_roteiro_etapas')->where('business_id', $businessId)
            ->whereIn('roteiro_id', $roteiros->values()->unique())->whereNull('opcional_por')
            ->orderBy('bloco')->orderBy('trilha')->orderBy('ordem')->get()->groupBy('roteiro_id');
        $postos = DB::table('pcp_postos')->where('business_id', $businessId)
            ->whereIn('id', $etapas->flatten(1)->pluck('posto_id')->filter()->unique())->get(['id', 'nome', 'horas_por_dia'])->keyBy('id');

        $agora = Carbon::now();
        $calendario = $this->calendario->regras($businessId, $venda->location_id !== null ? (int) $venda->location_id : null, $agora);
        $turno = $this->calendario->horasDoTurno($calendario);
        $premissas = [...$calendario['premissas'], sprintf('Cada dia de produção do posto vale um dia útil (%s h de turno).', self::fmt($turno)),
            'Etapa já iniciada na fila conta inteira (o apontado não é descontado).'];
        $horasDia = [];
        foreach ($postos as $p) {
            $horasDia[$p->id] = (float) $p->horas_por_dia > 0 ? (float) $p->horas_por_dia : self::HORAS_DIA_PADRAO;
            if ((float) $p->horas_por_dia <= 0) {
                $premissas[] = sprintf('Posto %s sem horas por dia: considerado %s h.', $p->nome, self::fmt(self::HORAS_DIA_PADRAO));
            }
        }

        $filaHoras = $this->filaHoras($businessId, $transactionId, $postos->keys());
        $livre = [];
        foreach ($postos as $p) {
            $livre[$p->id] = ($filaHoras[$p->id] ?? 0.0) / $horasDia[$p->id];
        }

        $itens = [];
        $semRoteiro = [];
        $semPosto = false;
        foreach ($linhas as $linha) {
            $roteiroId = $roteiros[$linha->product_id] ?? null;
            if ($roteiroId === null) {
                $semRoteiro[] = (int) $linha->id;

                continue;
            }
            $cursor = 0.0;
            $saida = [];
            foreach (($etapas[$roteiroId] ?? collect())->groupBy('bloco') as $bloco) {
                $fimBloco = $cursor;
                foreach ($bloco->groupBy('trilha') as $trilha) {
                    $t = $cursor;
                    foreach ($trilha as $e) {
                        $postoId = $e->posto_id !== null && isset($horasDia[$e->posto_id]) ? (int) $e->posto_id : null;
                        $semPosto = $semPosto || $postoId === null;
                        $inicio = $postoId === null ? $t : max($t, $livre[$postoId]);
                        $fim = $inicio + ((float) $e->horas) / ($postoId === null ? self::HORAS_DIA_PADRAO : $horasDia[$postoId]);
                        if ($postoId !== null) {
                            $livre[$postoId] = $fim;
                        }
                        $saida[] = ['nome' => $e->nome, 'bloco' => (int) $e->bloco, 'trilha' => (int) $e->trilha,
                            'posto_id' => $postoId, 'posto' => $postoId === null ? null : $postos[$postoId]->nome,
                            'horas' => (float) $e->horas, 'inicio_dias' => round($inicio, 4), 'fim_dias' => round($fim, 4)];
                        $t = $fim;
                    }
                    $fimBloco = max($fimBloco, $t);
                }
                $cursor = $fimBloco;
            }
            $itens[] = ['transaction_sell_line_id' => (int) $linha->id, 'product_id' => (int) $linha->product_id,
                'roteiro_id' => (int) $roteiroId, 'fim_dias' => round($cursor, 4), 'etapas' => $saida];
        }
        if ($semPosto) {
            $premissas[] = sprintf('Etapa sem posto (ex.: arte) não disputa fila e usa %s h por dia.', self::fmt(self::HORAS_DIA_PADRAO));
        }

        $dias = $itens === [] ? null : max(array_column($itens, 'fim_dias'));
        $entrega = $dias === null ? null : $this->calendario->somarHoras($calendario, $agora, round($dias, 4) * $turno);

        return [
            'transaction_id' => $transactionId,
            'dias' => $dias,
            'data_prevista' => $entrega?->toDateString(),
            'entrega_prevista' => $entrega?->format('Y-m-d H:i'),
            'fila_horas' => array_map(fn ($h) => round($h, 4), $filaHoras),
            'itens' => $itens,
            'sem_roteiro' => $semRoteiro,
            'premissas' => $premissas,
        ];
    }

    /**
     * Horas já programadas e não concluídas em cada posto, fora as OS desta venda.
     *
     * @param  Collection<int, int>  $postoIds
     * @return array<int, float>
     */
    private function filaHoras(int $businessId, int $transactionId, Collection $postoIds): array
    {
        if ($postoIds->isEmpty()) {
            return [];
        }
        $proprias = DB::table('pcp_ordens')->where('business_id', $businessId)->where('transaction_id', $transactionId)->pluck('id');

        return DB::table('pcp_programacoes')->where('business_id', $businessId)->whereIn('posto_id', $postoIds)
            ->whereIn('status', ['pendente', 'iniciada'])
            ->when($proprias->isNotEmpty(), fn ($q) => $q->where(fn ($w) => $w->where('os_tipo', '!=', Ordem::OS_TIPO)->orWhereNotIn('os_id', $proprias)))
            ->groupBy('posto_id')
            ->selectRaw('posto_id, SUM(TIMESTAMPDIFF(MINUTE, inicio_previsto, fim_previsto)) AS minutos')
            ->pluck('minutos', 'posto_id')
            ->map(fn ($m) => ((float) $m) / 60)
            ->all();
    }

    private static function fmt(float $h): string
    {
        return rtrim(rtrim(number_format($h, 2, ',', ''), '0'), ',');
    }
}
