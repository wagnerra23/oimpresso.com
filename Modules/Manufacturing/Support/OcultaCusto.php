<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Support;

/**
 * Custo só sai do servidor para quem pode ver preço de compra — auditoria do Manufacturing, lote 2.6.
 *
 * Decisão B3 de [W] (2026-11-09): o custo da Fabricação fica atrás de `view_purchase_price`, a mesma
 * permissão que o núcleo usa para esconder preço de compra. Superadmin da plataforma vê.
 *
 * Esconder é PÔR NULO nas chaves de dinheiro, mantendo a forma do payload: as telas leem
 * `r.custos.total`, `detalhe.custo.gravado` e afins, e um objeto que some quebraria o render. O
 * formatador das telas (`_lib/formato.ts`) mostra "—" para nulo.
 *
 * Cada método conhece UMA forma de payload, por nome: nada aqui varre chave por padrão de texto.
 */
final class OcultaCusto
{
    public static function podeVer(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('view_purchase_price') || $user->can('superadmin'));
    }

    /** Linha de `RecipeBomService::presentRecipe` (lista, painel e ficha de Receitas). */
    public static function receita(array $r): array
    {
        $r['venda'] = null;
        $r['extra'] = null;
        foreach (['ingredientes', 'extra', 'total', 'unit', 'margem'] as $k) {
            if (array_key_exists('custos', $r) && is_array($r['custos'])) {
                $r['custos'][$k] = null;
            }
        }
        foreach ($r['grupos'] ?? [] as $i => $g) {
            $r['grupos'][$i]['subtotal'] = null;
            foreach ($g['itens'] ?? [] as $j => $_) {
                $r['grupos'][$i]['itens'][$j]['custo_unitario'] = null;
                $r['grupos'][$i]['itens'][$j]['subtotal'] = null;
            }
        }

        return $r;
    }

    /** `RecipeBomService::kpis`: custo médio e contagem por faixa de margem. */
    public static function kpisReceitas(array $k): array
    {
        $k['custo_medio'] = null;
        $k['margem_baixa'] = null;

        return $k;
    }

    /** Grupos do editor (`editorDaReceita`) e resultado da busca de insumo (`buscarInsumos`). */
    public static function itensDoEditor(array $grupos): array
    {
        foreach ($grupos as $i => $g) {
            foreach ($g['itens'] ?? [] as $j => $_) {
                $grupos[$i]['itens'][$j]['custo_unitario'] = null;
            }
        }

        return $grupos;
    }

    /** @param array<int, array<string, mixed>> $insumos */
    public static function insumosDaBusca(array $insumos): array
    {
        return array_map(fn (array $i) => ['custo_unitario' => null] + $i, $insumos);
    }

    /** `listInsumosComUso`. */
    public static function insumos(array $linhas): array
    {
        return array_map(function (array $l) {
            $l['custo'] = null;

            return $l;
        }, $linhas);
    }

    /** `usosDoInsumo`: custo por receita antes e depois do preço simulado, e a margem nova. */
    public static function usos(array $linhas): array
    {
        return array_map(function (array $l) {
            $l['unit_atual'] = null;
            $l['unit_novo'] = null;
            $l['margem_nova'] = null;

            return $l;
        }, $linhas);
    }

    /** Linhas de `ProductionService::enrichProductionRows`. */
    public static function ordens(array $linhas): array
    {
        return array_map(function (array $l) {
            $l['final_total'] = null;
            $l['custo_unitario'] = null;

            return $l;
        }, $linhas);
    }

    /** `ProductionService::summary`. */
    public static function resumo(array $s): array
    {
        $s['total_value'] = null;

        return $s;
    }

    /** `ProductionService::detalheOrdem` (painel lateral da ordem). */
    public static function detalheOrdem(?array $d): ?array
    {
        if ($d === null) {
            return null;
        }
        if (isset($d['custo']) && is_array($d['custo'])) {
            foreach (array_keys($d['custo']) as $k) {
                $d['custo'][$k] = null;
            }
        }
        foreach ($d['linhas'] ?? [] as $i => $_) {
            $d['linhas'][$i]['custo_unitario'] = null;
            $d['linhas'][$i]['subtotal'] = null;
        }

        return $d;
    }

    /** `ProductionService::reportByProduct`. */
    public static function relatorio(array $rel): array
    {
        $rel['total'] = null;
        $rel['linhas'] = array_map(function (array $l) {
            $l['custo_total'] = null;
            $l['custo_medio'] = null;
            $l['percentual'] = null;

            return $l;
        }, $rel['linhas'] ?? []);

        return $rel;
    }
}
