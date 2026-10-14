// Menu "Ações" da linha da lista de produtos — o item "Dados fiscais (NCM/CFOP)".
//
// Cobre UC-PIDX-17 (resources/js/Pages/Produto/Index.casos.md). No protótipo
// (produto-blade.jsx · `acoesDe`) o item fica no grupo de "Comprar este produto" e abre o painel de
// detalhe; aqui ele avisa a tela com a ação `fiscal`, e a tela abre o painel na aba Fiscal
// (o lado do painel está em tests/js/produto-painel-detalhe.test.tsx).
//
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-menu-da-linha.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';
import AcoesProduto from '@/Pages/Produto/_components/lista/AcoesProduto';
import type { LinhaProduto, PermissoesLista } from '@/Pages/Produto/_components/lista/tipos';

const LINHA: LinhaProduto = {
  id: 7, nome: 'Camiseta', sku: 'CAM-7', tipo: 'single', categoria: 'Camisetas', subcategoria: null, unidade: 'Unidade', unidade_curta: 'Un',
  marca: 'Hering', imposto: null, imagem: null, controla_estoque: true, estoque: 5, alerta: null, abaixo_do_alerta: false,
  inativo: false, nao_para_venda: false, serie: false, locais: ['Matriz'], variacoes: 1, campos: {}, anexo: null,
  compra_min: 10, compra_max: 10, venda_min: 20, venda_max: 20,
};
const TUDO: PermissoesLista = {
  create: true, update: true, delete: true, opening_stock: true, view: true, stock_report: true,
  etiquetas: true, comprar: true, view_purchase_price: true, access_default_selling_price: true,
};
const FISCAL = 'Dados fiscais (NCM/CFOP)';

function abrirMenu(permissoes: PermissoesLista, linha: LinhaProduto = LINHA) {
  const aoEscolher = vi.fn();
  render(<AcoesProduto linha={linha} permissoes={permissoes} gruposDePreco={1} aoEscolher={aoEscolher} />);
  // O menu do Radix abre pelo teclado no jsdom (o clique dele depende de pointer events).
  fireEvent.keyDown(screen.getByRole('button', { name: `Ações do produto ${linha.nome}` }), { key: 'Enter' });
  const itens = () => screen.getAllByRole('menuitem').map((i) => i.textContent);
  return { aoEscolher, itens };
}

afterEach(() => cleanup());

describe('UC-PIDX-17 · "Dados fiscais" no menu da linha', () => {
  it('quem pode ver o produto tem o item, logo depois de "Comprar este produto"', () => {
    const { itens } = abrirMenu(TUDO);
    const lista = itens();
    expect(lista).toContain(FISCAL);
    expect(lista.indexOf(FISCAL)).toBe(lista.indexOf('Comprar este produto') + 1);
  });

  it('escolher o item avisa a tela com a ação `fiscal` e a linha', () => {
    const { aoEscolher } = abrirMenu(TUDO);
    fireEvent.click(screen.getByRole('menuitem', { name: FISCAL }));
    expect(aoEscolher).toHaveBeenCalledTimes(1);
    expect(aoEscolher).toHaveBeenCalledWith('fiscal', LINHA);
  });

  it('sem permissão de ver o produto, o item não aparece (anti-vácuo: o menu abriu e tem outros itens)', () => {
    const { itens } = abrirMenu({ ...TUDO, view: false });
    const lista = itens();
    expect(lista).toContain('Editar');
    expect(lista).not.toContain(FISCAL);
    expect(lista).not.toContain('Ver');
  });

  it('o item não depende de estoque nem de o produto estar ativo (o "Comprar" depende)', () => {
    const { itens } = abrirMenu(TUDO, { ...LINHA, controla_estoque: false, inativo: true });
    const lista = itens();
    expect(lista).toContain(FISCAL);
    expect(lista).not.toContain('Comprar este produto');
  });

  it('os outros itens do menu continuam os mesmos, na mesma ordem', () => {
    const { itens } = abrirMenu(TUDO);
    expect(itens()).toEqual([
      'Ver', 'Editar', 'Adicionar ou editar estoque inicial', 'Histórico de estoque do produto',
      'Duplicar produto', 'Gerar código de barras', 'Adicionar ou editar preços por grupo',
      'Comprar este produto', FISCAL,
      'Desativar', 'Excluir',
    ]);
  });
});
