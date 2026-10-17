// Encaixe das colunas da lista de produtos no espaço que a tela tem — UC-PIDX-26.
//
// Por que existe (medido em produção em 2026-10-17, janela de 1366×648 — o que sobra de um monitor
// 1366×768 depois da barra de tarefas e da barra do navegador —, menu lateral aberto, que é o padrão
// nessa largura): a tabela tinha 1.034 px de espaço e exigia 1.704 px, a soma das larguras do
// protótipo. Tipo, Categoria, Marca, Imposto e SKU ficavam fora da tela, e a ROTA LIVRE estreitava
// as colunas à mão a cada visita.
//
// A regra:
// - enquanto a soma cabe, valem as larguras do protótipo (tela larga não muda nada);
// - quando não cabe, cada coluna que pode encolher cede a MESMA fração da sua folga
//   (largura do protótipo − piso): as largas cedem mais, nenhuma passa do piso;
// - Ação e Imagem não encolhem (o protótipo as fixa) e a coluna que a pessoa arrastou também não —
//   a escolha dela vale;
// - se nem os pisos cabem, a tabela fica nos pisos e a sobra rola para o lado.
//
// Os pisos (medidos em produção em 2026-10-17, fonte da célula IBM Plex Sans 12,5px, com os 20px de
// respiro da célula):
// - preço: "R$ 9.999,99" ocupa 71px → 92. A faixa de produto variável ("R$ 10,00 – R$ 30,00") não
//   cabe numa linha: quebra em duas depois do traço (ver `colunas.tsx`), em vez de cortar o valor;
// - estoque: "4010 UNID", o mais largo da empresa 1, pede 88;
// - tipo: a pílula mais larga, "Composição", ocupa 76px → 96;
// - os de texto (produto, locais, categoria, marca, imposto, SKU, campos) cortam com "…" e mostram o
//   texto inteiro ao passar o mouse.
// Nos pisos, as 12 colunas de saída somam 974px (804 das que encolhem + Ação 96 + Imagem 74); com a
// seleção (34) e a barra de rolagem vertical da grade (16), 1.024px — cabem nos 1.034px de 1366.

/** Piso de cada coluna que encolhe. Coluna fora desta lista não encolhe. */
export const PISO_DA_COLUNA: Record<string, number> = {
  produto: 120,
  locais: 60,
  compra: 92,
  venda: 92,
  estoque: 88,
  tipo: 96,
  categoria: 66,
  marca: 64,
  imposto: 60,
  sku: 66,
  serie: 64,
  cf1: 80,
  cf2: 80,
  cf3: 80,
  cf4: 80,
  cf5: 80,
  cf6: 80,
  cf7: 80,
};

export interface ColunaParaEncaixe {
  id: string;
  /** Largura do protótipo (`meta.width`). */
  largura: number;
}

/**
 * Larguras que cabem em `disponivel` (px, já descontados a seleção e a barra de rolagem).
 *
 * Devolve só as colunas cuja largura mudou; as demais ficam com a do protótipo. `arrastadas`
 * entram como fixas, com a largura que a pessoa escolheu.
 */
export function encaixarColunas(
  colunas: ColunaParaEncaixe[],
  disponivel: number,
  arrastadas: Record<string, number> = {},
): Record<string, number> {
  if (!(disponivel > 0)) return {};
  const encolhem = colunas.filter((c) => PISO_DA_COLUNA[c.id] !== undefined && arrastadas[c.id] === undefined);
  const fixas = colunas.filter((c) => !encolhem.includes(c));
  const ocupadoPelasFixas = fixas.reduce((s, c) => s + (arrastadas[c.id] ?? c.largura), 0);
  const total = ocupadoPelasFixas + encolhem.reduce((s, c) => s + c.largura, 0);
  const falta = total - disponivel;
  if (falta <= 0) return {};

  const piso = (c: ColunaParaEncaixe) => PISO_DA_COLUNA[c.id] ?? c.largura;
  const folga = (c: ColunaParaEncaixe) => Math.max(0, c.largura - piso(c));
  const folgaTotal = encolhem.reduce((s, c) => s + folga(c), 0);
  if (folgaTotal <= 0) return {};
  const fracao = Math.min(1, falta / folgaTotal);

  const out: Record<string, number> = {};
  for (const c of encolhem) {
    // Arredonda a largura para BAIXO: assim a soma nunca passa do disponível por arredondamento
    // (o resto, menos de 1px por coluna, sobra no fim da tabela).
    const nova = Math.max(piso(c), Math.floor(c.largura - folga(c) * fracao));
    if (nova !== c.largura) out[c.id] = nova;
  }
  return out;
}
