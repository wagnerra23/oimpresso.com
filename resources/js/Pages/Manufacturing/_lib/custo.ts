// Custo da ficha no editor de ingredientes (US-MANU-006). É só o RETORNO IMEDIATO da tela: quem
// grava o custo é o servidor (`RecipeController::store` → `RecipeBomService::calculateCost`,
// handoff §9). As fórmulas são as do handoff §7 e as mesmas do servidor:
//   ingredientes = Σ quantidade × custo unitário × multiplicador da sub-unidade
//   custo extra  = percentual: ingredientes × extra / 100 · por unidade: extra × qtd produzida · fixo: extra
//   custo/unidade = total ÷ qtd produzida (NÃO pelo rendimento — §7 ponto 1); ÷ 0 = 0 (§7 ponto 3)
// Nada é arredondado aqui (§7 ponto 6): arredondar é com `fmt`/`num` na hora de mostrar.

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
