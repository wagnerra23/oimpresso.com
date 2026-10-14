// Aba "Relatório de estoque" da lista de produtos — lucro potencial com cor e linhas por página.
//
// Cobre UC-PIDX-18 (resources/js/Pages/Produto/Index.casos.md). O lado do servidor (o tamanho de
// página de verdade, os totais iguais em qualquer tamanho) está em
// tests/Feature/Produto/ProdutoListaEstoqueAbaTest.php, que roda em lane.
//
// Protótipo (produto-blade.jsx · `RelatorioEstoque`): o lucro potencial da LINHA é verde
// (`color: var(--pos)`); o total do rodapé não tem cor; o rodapé tem o seletor de tamanho de página.
//
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-relatorio-estoque.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';
import RelatorioEstoqueAba from '@/Pages/Produto/_components/lista/RelatorioEstoqueAba';
import type { LinhaRelatorioEstoque, RelatorioEstoqueLista } from '@/Pages/Produto/_components/lista/tipos';
// Formatador de moeda da própria tela: o que se prova aqui é a COR e o seletor, não o formato.
import { brl } from '@/Pages/Produto/Unificado/_components/catalogo';

const linha = (sku: string, compra: number | null, venda: number | null, parte: Partial<LinhaRelatorioEstoque> = {}): LinhaRelatorioEstoque => ({
  sku, produto: `Produto ${sku}`, variacao: '', categoria: 'Camisetas', local: 'Matriz', unidade: 'Un',
  preco: 20, estoque: 5, valor_compra: compra, valor_venda: venda,
  lucro_potencial: compra === null || venda === null ? null : venda - compra,
  vendido: 0, transferido: 0, ajustado: 0, alerta: false,
  ...parte,
});

const dados = (linhas: LinhaRelatorioEstoque[], parte: Partial<RelatorioEstoqueLista> = {}): RelatorioEstoqueLista => ({
  linhas,
  totais: { linhas: linhas.length, estoque: 15, vendido: 0, transferido: 0, ajustado: 0, valor_compra: 300, valor_venda: 420, lucro_potencial: 120, em: null },
  mostra_preco: true,
  mostra_valor: true,
  paginacao: { atual: 1, ultima: 1, total: linhas.length, por_pagina: 25 },
  ...parte,
});

function montar(d: RelatorioEstoqueLista) {
  const irParaPagina = vi.fn();
  const aoMudarPorPagina = vi.fn();
  render(<RelatorioEstoqueAba dados={d} irParaPagina={irParaPagina} aoMudarPorPagina={aoMudarPorPagina} />);
  return { irParaPagina, aoMudarPorPagina };
}

/** A célula "Lucro potencial" da linha daquele SKU (10ª coluna com preço e valores à mostra). */
const celulaDoLucro = (sku: string) => {
  const tr = screen.getByText(sku).closest('tr') as HTMLElement;
  const cabecalhos = screen.getAllByRole('columnheader').map((th) => th.textContent);
  return tr.querySelectorAll('td')[cabecalhos.indexOf('Lucro potencial')] as HTMLElement;
};
const classes = (el: HTMLElement) => el.className.split(/\s+/);

afterEach(() => cleanup());

describe('UC-PIDX-18 · lucro potencial com cor', () => {
  it('lucro positivo fica verde (`--pos`), como no protótipo', () => {
    montar(dados([linha('A1', 60, 100)]));
    const td = celulaDoLucro('A1');
    expect(td.textContent).toBe(brl(40));
    expect(classes(td)).toContain('text-[var(--pos)]');
    expect(classes(td)).not.toContain('text-[var(--neg)]');
  });

  it('lucro negativo (custo acima do preço de venda) fica vermelho, não verde', () => {
    montar(dados([linha('B2', 100, 60)]));
    const td = celulaDoLucro('B2');
    expect(td.textContent).toBe(brl(-40));
    expect(classes(td)).toContain('text-[var(--neg)]');
    expect(classes(td)).not.toContain('text-[var(--pos)]');
  });

  it('lucro zero não ganha cor', () => {
    montar(dados([linha('C3', 80, 80)]));
    const c = classes(celulaDoLucro('C3'));
    expect(c).not.toContain('text-[var(--pos)]');
    expect(c).not.toContain('text-[var(--neg)]');
  });

  it('só o lucro ganha cor: valor pelo custo e valor pela venda da mesma linha ficam como estavam', () => {
    montar(dados([linha('A1', 60, 100)]));
    const tr = screen.getByText('A1').closest('tr') as HTMLElement;
    const coloridas = Array.from(tr.querySelectorAll('td')).filter((td) => /text-\[var\(--(pos|neg)\)\]/.test(td.className));
    expect(coloridas).toHaveLength(1);
    expect(coloridas[0]).toBe(celulaDoLucro('A1'));
  });

  it('o total do rodapé não tem cor (no protótipo é só negrito)', () => {
    montar(dados([linha('A1', 60, 100)]));
    const rodape = document.querySelector('tfoot') as HTMLElement;
    expect(within(rodape).getByText(brl(120))).toBeTruthy();
    expect(rodape.innerHTML).not.toMatch(/text-\[var\(--(pos|neg)\)\]/);
  });

  it('sem permissão de ver valores, a coluna não existe', () => {
    montar(dados([linha('A1', null, null)], { mostra_valor: false }));
    expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).not.toContain('Lucro potencial');
    expect(document.querySelector('[class*="--pos"]')).toBeNull();
  });
});

describe('UC-PIDX-18 · linhas por página', () => {
  const seletor = () => screen.getByRole('combobox', { name: 'Linhas do relatório por página' });

  it('o seletor mostra o tamanho que o servidor informou', () => {
    montar(dados([linha('A1', 60, 100)], { paginacao: { atual: 1, ultima: 3, total: 120, por_pagina: 50 } }));
    expect(seletor().textContent).toBe('50');
  });

  it('"todas as linhas" (-1) aparece como "Todos"', () => {
    montar(dados([linha('A1', 60, 100)], { paginacao: { atual: 1, ultima: 1, total: 1, por_pagina: -1 } }));
    expect(seletor().textContent).toBe('Todos');
  });

  it('as opções são as da lista: 25, 50, 100, 200, 500, 1000 e Todos', () => {
    montar(dados([linha('A1', 60, 100)]));
    fireEvent.keyDown(seletor(), { key: 'ArrowDown' });
    expect(screen.getAllByRole('option').map((o) => o.textContent)).toEqual(['25', '50', '100', '200', '500', '1000', 'Todos']);
  });

  it('escolher um tamanho avisa a tela com o número', () => {
    const { aoMudarPorPagina, irParaPagina } = montar(dados([linha('A1', 60, 100)]));
    fireEvent.keyDown(seletor(), { key: 'ArrowDown' });
    fireEvent.click(screen.getByRole('option', { name: '100' }));
    expect(aoMudarPorPagina).toHaveBeenCalledTimes(1);
    expect(aoMudarPorPagina).toHaveBeenCalledWith(100);
    expect(irParaPagina).not.toHaveBeenCalled();
  });

  it('escolher "Todos" avisa com -1', () => {
    const { aoMudarPorPagina } = montar(dados([linha('A1', 60, 100)]));
    fireEvent.keyDown(seletor(), { key: 'ArrowDown' });
    fireEvent.click(screen.getByRole('option', { name: 'Todos' }));
    expect(aoMudarPorPagina).toHaveBeenCalledWith(-1);
  });

  it('Anterior e Próxima continuam trocando de página', () => {
    const { irParaPagina } = montar(dados([linha('A1', 60, 100)], { paginacao: { atual: 2, ultima: 3, total: 60, por_pagina: 25 } }));
    fireEvent.click(screen.getByRole('button', { name: 'Próxima' }));
    fireEvent.click(screen.getByRole('button', { name: 'Anterior' }));
    expect(irParaPagina.mock.calls).toEqual([[3], [1]]);
  });
});
