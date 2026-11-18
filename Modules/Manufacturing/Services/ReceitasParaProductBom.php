<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Services;

use App\Contracts\Fabricacao\InsumosDaReceita;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * ADR 0448, etapa 2: copia as linhas das receitas da Fabricação (`mfg_recipe_ingredients`) para o
 * `product_bom`, que passa a ser a fonte única da composição (D2). Ninguém lê o `product_bom` até a
 * etapa 3, então a cópia não muda estoque, custo nem preço.
 *
 * Fidelidade ao que vale hoje: o contrato `InsumosDaReceita` usa UMA receita por variação, a de
 * menor id com rendimento maior que zero. A cópia leva só essa; as outras saem no relatório, sem
 * cópia. Cada linha da receita vira uma linha do `product_bom`:
 *
 *   produto e variação da receita ...... parent_product_id, parent_variation_id
 *   variation_id do insumo ............. component_variation_id (e o produto dela)
 *   quantity ........................... qty_required (na sub-unidade do insumo, como na receita)
 *   sub_unit_id do insumo .............. sub_unit_id
 *   mfg_ingredient_group_id ............ ingredient_group_id
 *   sort_order ......................... sort_order
 *   rendimento × sub-unidade da receita  por_quantidade (na unidade base do produto feito)
 *   waste_percent ...................... não vai (D4: desperdício sai da composição)
 *
 * Conferência (REGRA MESTRE, dupla prova): para cada receita, o consumo de uma unidade do produto
 * calculado pelas linhas novas tem de ser igual ao que o `InsumosDaReceita` devolve hoje. A gravação
 * só acontece se todas as receitas da empresa baterem.
 *
 * Tier 0 (ADR 0093): `mfg_recipes` não tem `business_id`; a empresa vem do produto da receita, e o
 * insumo também precisa ser da empresa. Insumo de outra empresa ou apagado fica fora e é listado.
 * Idempotente: variação que já tem linha no `product_bom` não recebe cópia de novo.
 */
final class ReceitasParaProductBom
{
    public function __construct(private readonly InsumosDaReceita $insumosDaReceita)
    {
    }

    /**
     * @return array{receitas: list<array<string, mixed>>, linhas: list<array<string, mixed>>, ignoradas: list<array<string, mixed>>, divergencias: list<array<string, mixed>>}
     */
    public function planejar(int $businessId): array
    {
        $receitas = DB::table('mfg_recipes as r')
            ->join('variations as rv', 'rv.id', '=', 'r.variation_id')
            ->join('products as rp', 'rp.id', '=', 'rv.product_id')
            ->leftJoin('units as rsu', 'rsu.id', '=', 'r.sub_unit_id')
            ->where('rp.business_id', $businessId)
            ->orderBy('r.variation_id')->orderBy('r.id')
            ->get(['r.id', 'r.variation_id', 'rv.product_id', 'rp.name as produto', 'r.total_quantity',
                DB::raw('r.total_quantity * COALESCE(rsu.base_unit_multiplier, 1) as por_quantidade')]);

        $plano = ['receitas' => [], 'linhas' => [], 'ignoradas' => [], 'divergencias' => []];
        $escolhida = [];
        foreach ($receitas as $r) {
            $base = ['receita_id' => (int) $r->id, 'variation_id' => (int) $r->variation_id, 'produto' => $r->produto];
            if (BigDecimal::of((string) $r->total_quantity)->isLessThanOrEqualTo(0)) {
                $plano['ignoradas'][] = $base + ['motivo' => 'rendimento zero: a receita não é usada hoje'];

                continue;
            }
            if (isset($escolhida[$r->variation_id])) {
                $plano['ignoradas'][] = $base + ['motivo' => 'outra receita da mesma variação (receita '.$escolhida[$r->variation_id].') é a usada hoje'];

                continue;
            }
            $escolhida[$r->variation_id] = (int) $r->id;

            $jaTem = DB::table('product_bom')->where('business_id', $businessId)
                ->where('parent_variation_id', $r->variation_id)->exists();
            $plano['receitas'][] = $base + ['por_quantidade' => $this->quatroCasas((string) $r->por_quantidade), 'ja_copiada' => $jaTem];
            if ($jaTem) {
                continue;
            }

            foreach ($this->linhasDaReceita($businessId, $r) as $linha) {
                if (isset($linha['motivo'])) {
                    $plano['ignoradas'][] = $base + $linha;
                } else {
                    $plano['linhas'][] = $linha;
                }
            }
        }

        $plano['divergencias'] = $this->conferir($businessId, $plano);

        return $plano;
    }

    /** Grava o plano, se a conferência bateu em todas as receitas. Devolve as linhas gravadas. */
    public function aplicar(int $businessId): int
    {
        $plano = $this->planejar($businessId);
        if ($plano['divergencias'] !== []) {
            throw new \RuntimeException(count($plano['divergencias']).' receita(s) com consumo diferente do de hoje: nada foi gravado.');
        }
        if ($plano['linhas'] === []) {
            return 0;
        }

        $agora = now();
        DB::transaction(function () use ($plano, $agora) {
            foreach (array_chunk($plano['linhas'], 200) as $lote) {
                DB::table('product_bom')->insert(array_map(fn (array $l) => array_diff_key($l, ['receita_id' => 1, 'insumo' => 1])
                    + ['created_at' => $agora, 'updated_at' => $agora], $lote));
            }
        });

        return count($plano['linhas']);
    }

    /** @return list<array<string, mixed>> */
    private function linhasDaReceita(int $businessId, object $r): array
    {
        $ingredientes = DB::table('mfg_recipe_ingredients as i')
            ->leftJoin('variations as v', 'v.id', '=', 'i.variation_id')
            ->leftJoin('products as p', 'p.id', '=', 'v.product_id')
            ->where('i.mfg_recipe_id', $r->id)->orderBy('i.id')
            ->get(['i.id', 'i.variation_id', 'i.quantity', 'i.sub_unit_id', 'i.mfg_ingredient_group_id', 'i.sort_order',
                'v.product_id', 'v.deleted_at', 'p.business_id', 'p.name as insumo']);

        return $ingredientes->map(function ($i) use ($businessId, $r) {
            if ($i->product_id === null || $i->deleted_at !== null) {
                return ['insumo_variation_id' => (int) $i->variation_id, 'motivo' => 'o insumo não existe mais'];
            }
            if ((int) $i->business_id !== $businessId) {
                return ['insumo_variation_id' => (int) $i->variation_id, 'motivo' => 'o insumo é de outra empresa'];
            }

            return [
                'receita_id' => (int) $r->id,
                'insumo' => $i->insumo,
                'business_id' => $businessId,
                'parent_product_id' => (int) $r->product_id,
                'parent_variation_id' => (int) $r->variation_id,
                'component_product_id' => (int) $i->product_id,
                'component_variation_id' => (int) $i->variation_id,
                'qty_required' => $this->quatroCasas((string) $i->quantity),
                'por_quantidade' => $this->quatroCasas((string) $r->por_quantidade),
                'sub_unit_id' => $i->sub_unit_id === null ? null : (int) $i->sub_unit_id,
                'ingredient_group_id' => $i->mfg_ingredient_group_id === null ? null : (int) $i->mfg_ingredient_group_id,
                'sort_order' => (int) ($i->sort_order ?? 0),
            ];
        })->all();
    }

    /**
     * Para cada receita que vai ser copiada, o consumo de UMA unidade calculado pelas linhas novas,
     * com a mesma conta e o mesmo filtro do contrato (só insumo com estoque; insumo repetido soma),
     * comparado com o que o contrato devolve hoje.
     *
     * @return list<array<string, mixed>>
     */
    private function conferir(int $businessId, array $plano): array
    {
        $porReceita = [];
        foreach ($plano['linhas'] as $l) {
            $porReceita[$l['receita_id']][] = $l;
        }

        $divergencias = [];
        foreach ($plano['receitas'] as $receita) {
            if ($receita['ja_copiada']) {
                continue;
            }
            $hoje = $this->porVariacao($this->insumosDaReceita->paraQuantidade($businessId, $receita['variation_id'], '1'));
            $novo = $this->consumoPelasLinhas($businessId, $porReceita[$receita['receita_id']] ?? []);
            if ($hoje !== $novo) {
                $divergencias[] = $receita + ['hoje' => $hoje, 'copia' => $novo];
            }
        }

        return $divergencias;
    }

    /** @return array<int, string> variação do insumo → quantidade por unidade */
    private function porVariacao(array $insumos): array
    {
        $mapa = [];
        foreach ($insumos as $insumo) {
            $mapa[(int) $insumo['variation_id']] = $insumo['quantidade'];
        }
        ksort($mapa);

        return $mapa;
    }

    /** @return array<int, string> */
    private function consumoPelasLinhas(int $businessId, array $linhas): array
    {
        $soma = [];
        $porQuantidade = '1';
        foreach ($linhas as $l) {
            $estoque = DB::table('products')->where('business_id', $businessId)->where('id', $l['component_product_id'])->value('enable_stock');
            if ((int) $estoque !== 1) {
                continue;
            }
            $multiplicador = $l['sub_unit_id'] === null ? null
                : DB::table('units')->where('id', $l['sub_unit_id'])->value('base_unit_multiplier');
            $base = BigDecimal::of($l['qty_required'])->multipliedBy((string) ($multiplicador ?? '1'));
            $soma[$l['component_variation_id']] = ($soma[$l['component_variation_id']] ?? BigDecimal::zero())->plus($base);
            $porQuantidade = $l['por_quantidade'];
        }

        $mapa = [];
        foreach ($soma as $variacao => $total) {
            // Meio para cima em 4 casas, como o ROUND do MySQL que o contrato usa.
            $mapa[(int) $variacao] = (string) $total->dividedBy($porQuantidade, 8, RoundingMode::HALF_UP)->toScale(4, RoundingMode::HALF_UP);
        }
        ksort($mapa);

        return $mapa;
    }

    private function quatroCasas(string $valor): string
    {
        return (string) BigDecimal::of(trim($valor) === '' ? '0' : trim($valor))->toScale(4, RoundingMode::HALF_UP);
    }
}
