<?php

namespace Modules\Financeiro\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Filtro de relatório por centro de custo (US-FIN-075). Fonte única da regra que a DRE e o
 * fluxo realizado aplicam, para os dois relatórios contarem o mesmo dinheiro do mesmo jeito.
 *
 *  - null  → todos os títulos, valor cheio (comportamento de antes do rateio).
 *  - 0     → "sem centro": só títulos sem linha de rateio, valor cheio.
 *  - N > 0 → só a fatia do centro N: no título, o `valor` gravado do rateio; numa baixa, a
 *            mesma proporção (valor da baixa × fatia ÷ valor do título).
 *
 * A soma dos relatórios de todos os centros + "sem centro" fecha o total de "todos": no título
 * isso é exato (o rateio fecha em centavos); na baixa parcial cada fatia é proporcional e a
 * soma das fatias arredondadas pode diferir do total em centavos de arredondamento por mês.
 */
final class CentroCustoFiltro
{
    public const SEM_CENTRO = 0;

    /**
     * Centros da empresa para o seletor do relatório (ativos e inativos — inativo segue com
     * histórico). Filtro explícito por `business_id` além do global scope.
     *
     * @return array<int, array{id:int, codigo:?string, nome:string, ativo:bool}>
     */
    public static function opcoes(int $businessId): array
    {
        return DB::table('fin_centros_custo')
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->orderByDesc('ativo')->orderBy('nome')
            ->get(['id', 'codigo', 'nome', 'ativo'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'codigo' => $c->codigo, 'nome' => $c->nome, 'ativo' => (bool) $c->ativo])
            ->all();
    }

    /** Lê `?centro_custo=` da query: vazio/ausente = null; "sem" ou "0" = sem centro. */
    public static function daQuery(mixed $valor): ?int
    {
        if ($valor === null || $valor === '' || $valor === 'todos') {
            return null;
        }
        if ($valor === 'sem' || $valor === '0' || $valor === 0) {
            return self::SEM_CENTRO;
        }

        return ctype_digit((string) $valor) && (int) $valor > 0 ? (int) $valor : null;
    }

    /**
     * Restringe a query (que já tem `fin_titulos` como tabela ou join com o alias dado) ao
     * centro e devolve a expressão de valor do título para o SUM.
     */
    public static function aplicarNoTitulo(Builder $q, int $businessId, ?int $centroId, string $tituloAlias = 'fin_titulos'): Expression
    {
        if ($centroId === null) {
            return DB::raw("{$tituloAlias}.valor_total");
        }
        if ($centroId === self::SEM_CENTRO) {
            $q->whereNotExists(function ($sub) use ($tituloAlias, $businessId) {
                $sub->from('fin_titulo_rateios as rsem')
                    ->whereColumn('rsem.titulo_id', "{$tituloAlias}.id")
                    ->where('rsem.business_id', $businessId);
            });

            return DB::raw("{$tituloAlias}.valor_total");
        }

        $q->join('fin_titulo_rateios as rcc', function ($join) use ($tituloAlias, $businessId, $centroId) {
            $join->on('rcc.titulo_id', '=', "{$tituloAlias}.id")
                ->where('rcc.business_id', '=', $businessId)
                ->where('rcc.centro_custo_id', '=', $centroId);
        });

        return DB::raw('rcc.valor');
    }

    /** Mesmo filtro, para uma query de baixas com o título no alias dado. Devolve o valor da baixa. */
    public static function aplicarNaBaixa(Builder $q, int $businessId, ?int $centroId, string $tituloAlias, string $valorBaixa): Expression
    {
        if ($centroId === null || $centroId === self::SEM_CENTRO) {
            self::aplicarNoTitulo($q, $businessId, $centroId, $tituloAlias);

            return DB::raw($valorBaixa);
        }

        self::aplicarNoTitulo($q, $businessId, $centroId, $tituloAlias);

        return DB::raw("({$valorBaixa} * rcc.valor / NULLIF({$tituloAlias}.valor_total, 0))");
    }
}
