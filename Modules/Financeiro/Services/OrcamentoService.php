<?php

namespace Modules\Financeiro\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Financeiro\Models\Orcamento;
use Modules\Financeiro\Models\TituloBaixa;
use Modules\Financeiro\Models\Titulo;

/**
 * Orçado × realizado por conta do plano de contas, no mês (US-FIN-076).
 *
 * ⚠️ REGRA MESTRE — VALOR: orçado, realizado e desvio são somados em centavos inteiros
 * (RateioService::paraInteiro); só a saída vira reais.
 *
 * Centro de custo segue o CentroCustoFiltro, o mesmo da DRE e do fluxo:
 *  - null  → orçado = todas as linhas do mês (gerais e por centro); realizado = valor cheio.
 *  - 0     → orçado = só linhas gerais (sem centro); realizado = títulos sem rateio.
 *  - N > 0 → orçado = linhas do centro N; realizado = a fatia do centro N.
 *
 * Regime: `competencia` soma os títulos não cancelados da competência (como a DRE);
 * `caixa` soma as baixas do mês, sem estornos (como o fluxo realizado).
 * Desvio = realizado − orçado. Favorável: receita acima do orçado, despesa/custo abaixo.
 */
class OrcamentoService
{
    public const REGIMES = ['competencia', 'caixa'];

    /** Grava (ou zera) o orçado de uma conta no mês. Valor vazio ou zero apaga a linha. */
    public function definir(int $businessId, string $mes, int $planoContaId, ?int $centroCustoId, string $valor, ?int $userId = null): ?Orcamento
    {
        $this->validarMes($mes);
        $centavos = RateioService::paraInteiro($valor === '' ? '0' : $valor, 2);
        if ($centavos < 0) {
            throw ValidationException::withMessages(['valor' => 'O orçado não pode ser negativo.']);
        }

        $conta = DB::table('fin_planos_conta')->where('business_id', $businessId)->where('id', $planoContaId)
            ->whereNull('deleted_at')->first(['id', 'aceita_lancamento']);
        if (! $conta || ! $conta->aceita_lancamento) {
            throw ValidationException::withMessages(['plano_conta_id' => 'Conta inexistente, de outra empresa ou que não aceita lançamento.']);
        }
        if ($centroCustoId !== null && ! DB::table('fin_centros_custo')->where('business_id', $businessId)
            ->where('id', $centroCustoId)->whereNull('deleted_at')->exists()) {
            throw ValidationException::withMessages(['centro_custo_id' => 'Centro de custo inexistente ou de outra empresa.']);
        }

        $chave = ['business_id' => $businessId, 'competencia_mes' => $mes, 'plano_conta_id' => $planoContaId, 'centro_custo_id' => $centroCustoId];
        $existente = Orcamento::query()->where($chave)->first();

        if ($centavos === 0) {
            $existente?->delete();

            return null;
        }

        $dados = ['valor' => RateioService::deInteiro($centavos, 2), 'updated_by' => $userId];
        if ($existente) {
            $existente->update($dados);

            return $existente;
        }

        return Orcamento::query()->create($chave + $dados);
    }

    /**
     * @return array{
     *   meta: array{mes:string, regime:string, centro_custo_id:?int},
     *   linhas: array<int, array{plano_conta_id:int, codigo:?string, nome:?string, tipo:?string, orcado:float, realizado:float, desvio:float, desvio_pct:?float, favoravel:bool}>,
     *   totais: array{receitas: array{orcado:float, realizado:float}, despesas: array{orcado:float, realizado:float}, resultado: array{orcado:float, realizado:float, desvio:float}}
     * }
     */
    public function comparar(int $businessId, string $mes, ?int $centroCustoId = null, string $regime = 'competencia'): array
    {
        $this->validarMes($mes);
        if (! in_array($regime, self::REGIMES, true)) {
            $regime = 'competencia';
        }

        $orcado = $this->orcadoPorConta($businessId, $mes, $centroCustoId);
        $realizado = $regime === 'caixa'
            ? $this->realizadoCaixa($businessId, $mes, $centroCustoId)
            : $this->realizadoCompetencia($businessId, $mes, $centroCustoId);

        $ids = array_values(array_unique(array_merge(array_keys($orcado), array_keys($realizado))));
        $contas = DB::table('fin_planos_conta')->where('business_id', $businessId)->whereIn('id', $ids ?: [0])
            ->get(['id', 'codigo', 'nome', 'tipo'])->keyBy('id');

        $linhas = [];
        $tot = ['receitas' => [0, 0], 'despesas' => [0, 0]];
        foreach ($ids as $id) {
            $o = $orcado[$id] ?? 0;
            $r = $realizado[$id] ?? 0;
            $c = $contas[$id] ?? null;
            $receita = $c?->tipo === 'receita';
            $grupo = $receita ? 'receitas' : 'despesas';
            $tot[$grupo][0] += $o;
            $tot[$grupo][1] += $r;
            $desvio = $r - $o;
            $linhas[] = [
                'plano_conta_id' => (int) $id,
                'codigo' => $c?->codigo,
                'nome' => $c?->nome,
                'tipo' => $c?->tipo,
                'orcado' => $o / 100,
                'realizado' => $r / 100,
                'desvio' => $desvio / 100,
                'desvio_pct' => $o !== 0 ? round($desvio * 100 / $o, 1) : null,
                'favoravel' => $receita ? $desvio >= 0 : $desvio <= 0,
            ];
        }
        usort($linhas, fn ($a, $b) => [$a['tipo'] !== 'receita', (string) $a['codigo']] <=> [$b['tipo'] !== 'receita', (string) $b['codigo']]);

        $resO = $tot['receitas'][0] - $tot['despesas'][0];
        $resR = $tot['receitas'][1] - $tot['despesas'][1];

        return [
            'meta' => ['mes' => $mes, 'regime' => $regime, 'centro_custo_id' => $centroCustoId],
            'linhas' => $linhas,
            'totais' => [
                'receitas' => ['orcado' => $tot['receitas'][0] / 100, 'realizado' => $tot['receitas'][1] / 100],
                'despesas' => ['orcado' => $tot['despesas'][0] / 100, 'realizado' => $tot['despesas'][1] / 100],
                'resultado' => ['orcado' => $resO / 100, 'realizado' => $resR / 100, 'desvio' => ($resR - $resO) / 100],
            ],
        ];
    }

    /** @return array<int, int> plano_conta_id → centavos */
    private function orcadoPorConta(int $businessId, string $mes, ?int $centroCustoId): array
    {
        $q = DB::table('fin_orcamentos')->where('business_id', $businessId)->where('competencia_mes', $mes);
        if ($centroCustoId === CentroCustoFiltro::SEM_CENTRO) {
            $q->whereNull('centro_custo_id');
        } elseif ($centroCustoId !== null) {
            $q->where('centro_custo_id', $centroCustoId);
        }

        return $this->centavos($q->groupBy('plano_conta_id')->selectRaw('plano_conta_id AS k, SUM(valor) AS v')->get());
    }

    /** @return array<int, int> */
    private function realizadoCompetencia(int $businessId, string $mes, ?int $centroCustoId): array
    {
        $q = Titulo::query()
            ->where('fin_titulos.business_id', $businessId)
            ->where('fin_titulos.competencia_mes', $mes)
            ->where('fin_titulos.status', '!=', 'cancelado')
            ->whereNotNull('fin_titulos.plano_conta_id');
        $valor = CentroCustoFiltro::aplicarNoTitulo($q, $businessId, $centroCustoId);

        return $this->centavos($q->groupBy('fin_titulos.plano_conta_id')
            ->selectRaw('fin_titulos.plano_conta_id AS k, SUM('.$valor->getValue(DB::connection()->getQueryGrammar()).') AS v')->get());
    }

    /** @return array<int, int> */
    private function realizadoCaixa(int $businessId, string $mes, ?int $centroCustoId): array
    {
        $ini = CarbonImmutable::parse($mes.'-01')->startOfMonth();
        // Sem alias na tabela do próprio model: o BusinessScope usa o nome real (ver FluxoRealizadoService).
        $q = TituloBaixa::query()
            ->join('fin_titulos as t', function ($join) use ($businessId) {
                $join->on('t.id', '=', 'fin_titulo_baixas.titulo_id')->where('t.business_id', '=', $businessId);
            })
            ->where('fin_titulo_baixas.business_id', $businessId)
            ->whereBetween('fin_titulo_baixas.data_baixa', [$ini->toDateString(), $ini->endOfMonth()->toDateString()])
            ->whereNull('fin_titulo_baixas.estorno_de_id')
            ->whereNull('t.deleted_at')
            ->whereNotNull('t.plano_conta_id');
        $valor = CentroCustoFiltro::aplicarNaBaixa($q, $businessId, $centroCustoId, 't', 'fin_titulo_baixas.valor_baixa');

        return $this->centavos($q->groupBy('t.plano_conta_id')
            ->selectRaw('t.plano_conta_id AS k, SUM('.$valor->getValue(DB::connection()->getQueryGrammar()).') AS v')->get());
    }

    /** @return array<int, int> */
    private function centavos(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->k] = RateioService::paraInteiro((string) ($row->v ?? '0'), 2);
        }

        return $out;
    }

    private function validarMes(string $mes): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            throw ValidationException::withMessages(['mes' => 'Mês inválido; use AAAA-MM.']);
        }
    }
}
