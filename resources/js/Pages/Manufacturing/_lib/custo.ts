// Custo da ficha no editor de ingredientes (US-MANU-006). É só o RETORNO IMEDIATO da tela: quem
// grava o custo é o servidor (`RecipeController::store` → `RecipeBomService::calculateCost`,
// handoff §9). As fórmulas são as do handoff §7 e as mesmas do servidor:
//   ingredientes = Σ quantidade × custo unitário × multiplicador da sub-unidade
//   custo extra  = percentual: ingredientes × extra / 100 · por unidade: extra × qtd produzida · fixo: extra
//   custo/unidade = total ÷ qtd produzida (NÃO pelo rendimento — §7 ponto 1); ÷ 0 = 0 (§7 ponto 3)
// Nada é arredondado aqui (§7 ponto 6): arredondar é com `fmt`/`num` na hora de mostrar.

import { paraNumUf } from '@/Lib/numberPtBR';

export interface SubUnidade {
  id: number;
  nome: string;
  multiplicador: number;
}

export interface LinhaEditor {
  variation_id: number;
  nome: string;
  sku: string;
  custo_unitario: number;
  unidade_base: string;
  /** Id da unidade base: escolhê-la é "sem sub-unidade" (o `store()` grava null). */
  unidade_base_id: number;
  sub_unidades: SubUnidade[];
  linha_id: number | null;
  quantidade: number;
  sub_unit_id: number | null;
  waste_percent: number;
}

export interface GrupoEditor {
  id: number | null;
  nome: string;
  descricao: string;
  /** Balde das linhas sem grupo: não vai como grupo no salvar. */
  sem_grupo: boolean;
  itens: LinhaEditor[];
}

export type TipoCustoExtra = 'fixed' | 'percentage' | 'per_unit';

export interface CamposReceita {
  total_quantity: number;
  waste_percent: number;
  extra_cost: number;
  production_cost_type: TipoCustoExtra;
}

/** Multiplicador da sub-unidade escolhida na linha; sem sub-unidade (ou a própria unidade) é 1. */
export function multiplicador(linha: LinhaEditor): number {
  const sub = linha.sub_unidades.find((s) => s.id === linha.sub_unit_id);
  return sub && sub.multiplicador > 0 ? sub.multiplicador : 1;
}

export function subtotalDoIngrediente(linha: LinhaEditor): number {
  return linha.quantidade * linha.custo_unitario * multiplicador(linha);
}

export function custosDaFicha(grupos: GrupoEditor[], campos: CamposReceita) {
  const ingredientes = grupos.reduce((s, g) => s + g.itens.reduce((t, l) => t + subtotalDoIngrediente(l), 0), 0);
  const qtd = campos.total_quantity;
  let extra = campos.extra_cost;
  if (campos.production_cost_type === 'percentage') extra = (ingredientes * campos.extra_cost) / 100;
  else if (campos.production_cost_type === 'per_unit') extra = campos.extra_cost * qtd;
  const total = ingredientes + extra;

  return {
    ingredientes,
    extra,
    total,
    unitario: qtd > 0 ? total / qtd : 0,
    rendimento: qtd - (qtd * campos.waste_percent) / 100,
  };
}

// ── Envio para o `RecipeController::store` ───────────────────────────────────────────────
// O servidor lê os números com `Util::num_uf` (heurística pt-BR: vírgula = decimal, ponto =
// milhar). Número cru como `0.5` cairia na heurística do ponto — o vetor do incidente da REGRA
// MESTRE de valor. Por isso todo número vai por `paraNumUf` (@/Lib/numberPtBR): vírgula decimal,
// sem milhar, com as casas EXIBIDAS no campo — o que a tela mostra é o que o banco grava.
const CASAS_QUANTIDADE_INGREDIENTE = 3; // o campo de quantidade da linha mostra 3 casas
const CASAS_CAMPO = 2; // qtd. produzida, desperdício, custo extra e os totais mostram 2

export interface CamposEnvio extends CamposReceita {
  instructions: string;
  sub_unit_id: number | null;
}

/**
 * O formulário do editor no formato do `store()` (o mesmo da janela Blade): grupos por índice em
 * `ingredient_groups`, linhas com `ig_index`, linha existente com `ingredient_line_id`.
 * `total` e `ingredients_cost` vão com a conta da tela, como a Blade mandava. O servidor recalcula
 * os dois desde o #9051 (handoff §9); antes dele, isto mantém o comportamento da Blade.
 */
export function montarEnvio(variationId: number, campos: CamposEnvio, grupos: GrupoEditor[]) {
  const c = custosDaFicha(grupos, campos);
  const ingredient_groups: Record<number, string> = {};
  const ingredient_group_description: Record<number, string> = {};
  const ingredients: Array<Record<string, string | number | null>> = [];
  let ordem = 0;

  grupos.forEach((g, gi) => {
    if (!g.sem_grupo) {
      ingredient_groups[gi] = g.nome;
      ingredient_group_description[gi] = g.descricao;
    }
    for (const l of g.itens) {
      ordem += 1;
      ingredients.push({
        ingredient_id: l.variation_id,
        quantity: paraNumUf(l.quantidade, CASAS_QUANTIDADE_INGREDIENTE),
        waste_percent: paraNumUf(l.waste_percent, CASAS_CAMPO),
        sort_order: ordem,
        sub_unit_id: l.sub_unit_id,
        ig_index: g.sem_grupo ? null : gi,
        mfg_ingredient_group_id: g.sem_grupo ? null : g.id,
        ingredient_line_id: l.linha_id,
      });
    }
  });

  return {
    variation_id: variationId,
    ingredients,
    ingredient_groups,
    ingredient_group_description,
    total: paraNumUf(c.total, CASAS_CAMPO),
    ingredients_cost: paraNumUf(c.ingredientes, CASAS_CAMPO),
    total_quantity: paraNumUf(campos.total_quantity, CASAS_CAMPO),
    waste_percent: paraNumUf(campos.waste_percent, CASAS_CAMPO),
    extra_cost: paraNumUf(campos.extra_cost, CASAS_CAMPO),
    production_cost_type: campos.production_cost_type,
    instructions: campos.instructions,
    sub_unit_id: campos.sub_unit_id,
  };
}
