// Tipos do payload da tela de Fabricação (Manufacturing/Recipes).
// Espelho 1:1 de RecipeBomService::presentRecipe() — TODO número aqui é DERIVADO no
// servidor (§9 do handoff: "o protótipo calcula para dar retorno imediato; a autoridade
// é o servidor"). O cliente formata, nunca recalcula.

export interface Ingrediente {
  id: number;
  nome: string;
  sku: string;
  quantidade: number;
  /** Sub-unidade escolhida na linha, ou a unidade base do insumo. */
  unidade: string;
  unidade_base: string;
  /** `units.base_unit_multiplier` da sub-unidade; 1 quando não há. */
  multiplicador: number;
  /** `variations.dpp_inc_tax` — preço de compra com imposto, de hoje. */
  custo_unitario: number;
  subtotal: number;
}

export interface GrupoIngredientes {
  /** Nome vindo de `mfg_ingredient_groups.name` (ou "Sem grupo"). */
  g: string;
  itens: Ingrediente[];
  subtotal: number;
}

export interface CustosReceita {
  ingredientes: number;
  extra: number;
  total: number;
  /** `total_quantity − total_quantity × waste/100` (§16). */
  qtd_liq: number;
  /** §7.1 — divide por `total_quantity`, NÃO pelo rendimento. */
  unit: number;
  margem: number;
}

export interface Receita {
  id: number;
  variation_id: number;
  name: string;
  sku: string;
  cat: string;
  sub: string;
  qtd: number;
  un: string;
  waste: number;
  extra: number;
  /** `[FECHADA] {fixed, percentage, per_unit}` — migration 2020_08_19_103831. */
  custo_tipo: 'fixed' | 'percentage' | 'per_unit';
  venda: number;
  atualizado: string | null;
  sub_un: string | null;
  sub_fator: number | null;
  grupos: GrupoIngredientes[];
  n_ingredientes: number;
  custos: CustosReceita;
}

export interface Permissoes {
  criar: boolean;
  editar: boolean;
  prod: boolean;
}

export interface ContadoresProducao {
  total: number;
  rascunhos: number;
  mes_final: number;
  mes_rascunho: number;
}
