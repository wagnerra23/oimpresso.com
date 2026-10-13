// Estoque inicial em janela — o que NÃO é tela: os dados que chegam, as linhas em branco, o
// subtotal exibido, o pedido que vai ao servidor e as chamadas.
//
// A tela não grava nem calcula estoque. O subtotal (quantidade × custo) é só exibição: não é
// enviado. Quantidade e custo vão como texto com vírgula decimal e sem separador de milhar
// (`paraNumUf`) — o único formato que OpeningStockController::salvarDaJanela aceita.
//
// ⚠️ O pedido leva SEMPRE todas as linhas que a janela recebeu. A gravação antiga apaga, em cada
// local enviado, a linha de estoque inicial que não vier — e desconta o saldo. O servidor recusa
// pedido incompleto; aqui a regra é nunca montar um.

import { paraNumUf } from '@/Lib/numberPtBR';

export interface LinhaEstoqueInicial {
  /** null = linha nova, ainda não lançada. */
  purchase_line_id: number | null;
  /** Quantidade que RESTA da linha (lançada − usada). */
  quantidade: number;
  custo: number;
  /** ISO `AAAA-MM-DD`. */
  validade: string | null;
  lote: string | null;
  /** ISO `AAAA-MM-DDTHH:MM`; null = o servidor usa o início do ano fiscal. */
  data: string | null;
  observacao: string | null;
  segunda_unidade_qtd: number;
}

export interface VariacaoEstoqueInicial {
  id: number;
  /** Vazio em produto simples. */
  nome: string;
  custo_padrao: number;
  linhas: LinhaEstoqueInicial[];
}

export interface LocalEstoqueInicial {
  id: number;
  nome: string;
  variacoes: VariacaoEstoqueInicial[];
}

export interface DadosEstoqueInicial {
  produto: { id: number; nome: string; unidade: string; segunda_unidade: string | null };
  usa_validade: boolean;
  usa_lote: boolean;
  casas: { quantidade: number; preco: number };
  locais: LocalEstoqueInicial[];
}

export interface PedidoEstoqueInicial {
  product_id: number;
  locais: Array<{
    location_id: number;
    variacoes: Array<{
      variation_id: number;
      linhas: Array<{
        purchase_line_id: number | null;
        quantidade: string;
        custo: string;
        validade: string | null;
        lote: string | null;
        data: string | null;
        observacao: string | null;
        segunda_unidade_qtd: string;
      }>;
    }>;
  }>;
}

/** O servidor aceita até 4 casas depois da vírgula. */
const casasAte4 = (n: number) => Math.max(0, Math.min(4, Math.trunc(n)));

export function linhaEmBranco(custo: number): LinhaEstoqueInicial {
  return { purchase_line_id: null, quantidade: 0, custo, validade: null, lote: null, data: null, observacao: null, segunda_unidade_qtd: 0 };
}

/** Como a tela Blade: variação sem linha num local ganha uma linha em branco, com o custo do cadastro. */
export function comLinhasEmBranco(d: DadosEstoqueInicial): DadosEstoqueInicial {
  return {
    ...d,
    locais: d.locais.map((l) => ({
      ...l,
      variacoes: l.variacoes.map((v) => ({ ...v, linhas: v.linhas.length > 0 ? v.linhas : [linhaEmBranco(v.custo_padrao)] })),
    })),
  };
}

/** Subtotal sem imposto de uma linha — só exibição. */
export const subtotalDaLinha = (l: LinhaEstoqueInicial): number => l.quantidade * l.custo;

/** Total sem imposto de um local — só exibição. */
export const totalDoLocal = (l: LocalEstoqueInicial): number =>
  l.variacoes.reduce((a, v) => a + v.linhas.reduce((b, x) => b + subtotalDaLinha(x), 0), 0);

/** Troca uma linha (local, variação, posição) sem mexer nas outras. */
export function comLinhaAlterada(
  d: DadosEstoqueInicial,
  localId: number,
  variacaoId: number,
  indice: number,
  patch: Partial<LinhaEstoqueInicial>,
): DadosEstoqueInicial {
  return {
    ...d,
    locais: d.locais.map((l) =>
      l.id !== localId
        ? l
        : {
            ...l,
            variacoes: l.variacoes.map((v) =>
              v.id !== variacaoId ? v : { ...v, linhas: v.linhas.map((x, i) => (i === indice ? { ...x, ...patch } : x)) },
            ),
          },
    ),
  };
}

/** Mais uma linha para a variação naquele local (o "+" da tela Blade). */
export function comLinhaNova(d: DadosEstoqueInicial, localId: number, variacaoId: number): DadosEstoqueInicial {
  return {
    ...d,
    locais: d.locais.map((l) =>
      l.id !== localId
        ? l
        : { ...l, variacoes: l.variacoes.map((v) => (v.id !== variacaoId ? v : { ...v, linhas: [...v.linhas, linhaEmBranco(v.custo_padrao)] })) },
    ),
  };
}

/** O que impede de salvar: número negativo ou inválido em alguma linha. */
export function erroDoEstoqueInicial(d: DadosEstoqueInicial): string | null {
  for (const l of d.locais) {
    for (const v of l.variacoes) {
      for (const x of v.linhas) {
        const numeros = [x.quantidade, x.custo, x.segunda_unidade_qtd];
        if (numeros.some((n) => !Number.isFinite(n) || n < 0)) return 'Quantidade e custo não podem ser negativos.';
      }
    }
  }
  return null;
}

/** O pedido: TODAS as linhas de TODOS os locais que a janela recebeu, existentes e novas. */
export function pedidoDoEstoqueInicial(d: DadosEstoqueInicial): PedidoEstoqueInicial {
  const casasQtd = casasAte4(d.casas.quantidade);
  const casasPreco = casasAte4(d.casas.preco);
  const texto = (s: string | null) => (s === null || s.trim() === '' ? null : s);
  return {
    product_id: d.produto.id,
    locais: d.locais.map((l) => ({
      location_id: l.id,
      variacoes: l.variacoes.map((v) => ({
        variation_id: v.id,
        linhas: v.linhas.map((x) => ({
          purchase_line_id: x.purchase_line_id,
          quantidade: paraNumUf(x.quantidade, casasQtd),
          custo: paraNumUf(x.custo, casasPreco),
          validade: texto(x.validade),
          lote: texto(x.lote),
          data: texto(x.data),
          observacao: texto(x.observacao),
          segunda_unidade_qtd: paraNumUf(x.segunda_unidade_qtd, casasQtd),
        })),
      })),
    })),
  };
}

const csrf = () => document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

export type RespostaEstoqueInicial = { ok: true; msg: string } | { ok: false; msg: string };

/** GET /opening-stock/dados/{id}. */
export async function buscarEstoqueInicial(produtoId: number): Promise<DadosEstoqueInicial | { erro: string }> {
  const falha = 'Não foi possível abrir o estoque inicial deste produto.';
  try {
    const r = await fetch(`/opening-stock/dados/${produtoId}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    const j = (await r.json().catch(() => null)) as (DadosEstoqueInicial & { msg?: string }) | null;
    if (!r.ok || !j || !Array.isArray(j.locais)) return { erro: j?.msg ?? falha };
    return j;
  } catch {
    return { erro: falha };
  }
}

/** POST /opening-stock/salvar-da-janela. */
export async function salvarEstoqueInicial(pedido: PedidoEstoqueInicial): Promise<RespostaEstoqueInicial> {
  const falha = 'Não foi possível lançar o estoque inicial.';
  try {
    const r = await fetch('/opening-stock/salvar-da-janela', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify(pedido),
    });
    const j = (await r.json().catch(() => null)) as { success?: boolean; msg?: string; message?: string; errors?: Record<string, string[]> } | null;
    if (r.ok && j?.success === true) return { ok: true, msg: j.msg ?? 'Estoque inicial lançado.' };
    const primeiro = Object.values(j?.errors ?? {})[0]?.[0];
    return { ok: false, msg: primeiro ?? j?.msg ?? falha };
  } catch {
    return { ok: false, msg: falha };
  }
}
