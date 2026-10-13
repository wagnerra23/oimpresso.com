// Cadastro rápido de produto — o que NÃO é tela: o formulário, o que impede de salvar, o pedido que
// vai ao servidor e a chamada. Mora fora do componente (CadastroRapido.tsx) para o arquivo da janela
// exportar só a janela.
//
// Nenhum preço é calculado aqui: os dois digitados vão como texto com vírgula decimal e sem
// separador de milhar (`paraNumUf`), e ProductController::cadastroRapido monta o resto.

import { paraNumUf } from '@/Lib/numberPtBR';
import type { Opcao } from './tipos';

/** Casas dos preços na janela — as mesmas que vão ao servidor (o que a tela mostra é o que grava). */
export const CASAS = 2;

export interface FormCadastroRapido {
  nome: string;
  sku: string;
  unidade: string;
  categoria: string;
  compra: number;
  venda: number;
  controlaEstoque: boolean;
  locais: string[];
}

export interface PedidoCadastroRapido {
  name: string;
  sku: string;
  unit_id: number;
  category_id: number | null;
  compra: string;
  venda: string;
  enable_stock: boolean;
  product_locations: number[];
}

export type ErrosCadastroRapido = Partial<Record<'name' | 'sku' | 'unit_id' | 'category_id' | 'compra' | 'venda' | 'product_locations', string>>;

/** Formulário em branco: a 1ª unidade já escolhida (como o formulário Blade) e estoque gerenciado. */
export function formInicial(unidades: Opcao[]): FormCadastroRapido {
  return { nome: '', sku: '', unidade: unidades[0]?.id ?? '', categoria: '', compra: 0, venda: 0, controlaEstoque: true, locais: [] };
}

/** O que falta para poder salvar. Com um local só, a janela não pergunta o local. */
export function errosDoForm(f: FormCadastroRapido, locais: Opcao[]): ErrosCadastroRapido {
  const e: ErrosCadastroRapido = {};
  if (f.nome.trim() === '') e.name = 'Informe o nome do produto.';
  if (f.unidade === '') e.unit_id = 'Escolha a unidade.';
  if (!Number.isFinite(f.compra) || f.compra < 0) e.compra = 'O preço de compra não pode ser negativo.';
  if (!Number.isFinite(f.venda) || f.venda < 0) e.venda = 'O preço de venda não pode ser negativo.';
  if (locais.length > 1 && f.locais.length === 0) e.product_locations = 'Escolha ao menos um local do negócio.';
  return e;
}

/** O pedido que vai ao servidor. Nenhum preço é calculado aqui. */
export function pedidoDoForm(f: FormCadastroRapido): PedidoCadastroRapido {
  return {
    name: f.nome.trim(),
    sku: f.sku.trim(),
    unit_id: Number(f.unidade),
    category_id: f.categoria === '' ? null : Number(f.categoria),
    compra: paraNumUf(f.compra, CASAS),
    venda: paraNumUf(f.venda, CASAS),
    enable_stock: f.controlaEstoque,
    product_locations: f.locais.map(Number),
  };
}

const csrf = () => document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

export type RespostaCadastroRapido = { ok: true; msg: string } | { ok: false; msg: string; erros: ErrosCadastroRapido };

/** POST /products/cadastro-rapido. Erro de validação (422) volta campo a campo. */
export async function enviarCadastroRapido(pedido: PedidoCadastroRapido): Promise<RespostaCadastroRapido> {
  const falha = 'Não foi possível criar o produto.';
  try {
    const r = await fetch('/products/cadastro-rapido', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify(pedido),
    });
    const j = (await r.json().catch(() => null)) as { success?: boolean; msg?: string; message?: string; errors?: Record<string, string[]> } | null;
    if (r.ok && j?.success === true) return { ok: true, msg: j.msg ?? 'Produto criado.' };
    const erros: ErrosCadastroRapido = {};
    for (const [campo, mensagens] of Object.entries(j?.errors ?? {})) {
      const primeira = mensagens[0];
      if (primeira) erros[campo as keyof ErrosCadastroRapido] = primeira;
    }
    return { ok: false, msg: Object.keys(erros).length > 0 ? 'Confira os campos marcados.' : (j?.msg ?? falha), erros };
  } catch {
    return { ok: false, msg: falha, erros: {} };
  }
}
