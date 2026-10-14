// Aba "Relatório de estoque" da lista de produtos — lucro potencial com cor e linhas por página.
//
// Cobre UC-PIDX-18 e a linha vermelha do UC-PIDX-08 (resources/js/Pages/Produto/Index.casos.md). O lado do servidor (o tamanho de
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
/** A cor que a célula PEDE em `style` (o jsdom não resolve `var()`; a cor resolvida se confere no navegador). */
const corPedida = (el: HTMLElement) => el.style.color;

afterEach(() => cleanup());

// ⚠️ A cor vai em `style`, não em classe: no bundle da tela `.pb-tbl tbody td { color: var(--text) }`
// fica fora das camadas do Tailwind e vence `text-[var(--pos)]`. Medido em produção em 2026-10-14 — a
// classe chegava ao DOM e a célula continuava na cor comum. Os asserts abaixo leem `style`, e um deles
// prova que nenhuma classe de cor sobrou (a que era inerte).
describe('UC-PIDX-18 · lucro potencial com cor', () => {
  it('lucro positivo pede verde (`--pos`), como no protótipo', () => {
    montar(dados([linha('A1', 60, 100)]));
    const td = celulaDoLucro('A1');
    expect(td.textContent).toBe(brl(40));
    expect(corPedida(td)).toBe('var(--pos)');
  });

  it('lucro negativo (custo acima do preço de venda) pede vermelho, não verde', () => {
    montar(dados([linha('B2', 100, 60)]));
    const td = celulaDoLucro('B2');
    expect(td.textContent).toBe(brl(-40));
    expect(corPedida(td)).toBe('var(--neg)');
  });

  it('lucro zero não pede cor', () => {
    montar(dados([linha('C3', 80, 80)]));
    expect(corPedida(celulaDoLucro('C3'))).toBe('');
  });

  it('só o lucro pede cor: as outras células da linha ficam como estavam', () => {
    montar(dados([linha('A1', 60, 100)]));
    const tr = screen.getByText('A1').closest('tr') as HTMLElement;
    const coloridas = Array.from(tr.querySelectorAll('td')).filter((td) => td.style.color !== '');
    expect(coloridas).toHaveLength(1);
    expect(coloridas[0]).toBe(celulaDoLucro('A1'));
  });

  it('nenhuma classe utilitária de cor sobrou nas células (a forma que era inerte)', () => {
    montar(dados([linha('A1', 60, 100), linha('B2', 100, 60)]));
    const tabela = screen.getByRole('table');
    expect(tabela.querySelector('tbody [class*="text-[var(--pos)]"], tbody [class*="text-[var(--neg)]"]')).toBeNull();
  });

  it('o total do rodapé não pede cor (no protótipo é só negrito)', () => {
    montar(dados([linha('A1', 60, 100)]));
    const rodape = document.querySelector('tfoot') as HTMLElement;
    expect(within(rodape).getByText(brl(120))).toBeTruthy();
    expect(Array.from(rodape.querySelectorAll('td')).every((td) => td.style.color === '')).toBe(true);
  });

  it('sem permissão de ver valores, a coluna não existe', () => {
    montar(dados([linha('A1', null, null)], { mostra_valor: false }));
    expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).not.toContain('Lucro potencial');
    expect(Array.from(document.querySelectorAll('tbody td')).every((td) => (td as HTMLElement).style.color === '')).toBe(true);
  });
});

describe('UC-PIDX-08 · linha de estoque em alerta fica vermelha (como na Blade)', () => {
  it('linha em alerta troca o `--text` dela para o vermelho — é o que as células usam', () => {
    montar(dados([linha('A1', 60, 100, { alerta: true }), linha('B2', 60, 100)]));
    const emAlerta = screen.getByText('A1').closest('tr') as HTMLElement;
    const normal = screen.getByText('B2').closest('tr') as HTMLElement;
    expect(emAlerta.style.getPropertyValue('--text')).toBe('var(--neg)');
    expect(normal.style.getPropertyValue('--text')).toBe('');
  });

  it('a linha não depende mais de classe no `<tr>` (a forma que era inerte)', () => {
    montar(dados([linha('A1', 60, 100, { alerta: true })]));
    const emAlerta = screen.getByText('A1').closest('tr') as HTMLElement;
    expect(emAlerta.className).toBe('');
  });

  it('numa linha em alerta, o lucro segue a regra dele (positivo pede verde)', () => {
    montar(dados([linha('A1', 60, 100, { alerta: true })]));
    expect(corPedida(celulaDoLucro('A1'))).toBe('var(--pos)');
  });
});

describe('UC-PIDX-18 · lista vazia', () => {
  it('a mensagem de lista vazia pede a cor apagada em `style` (a classe perdia para o bundle)', () => {
    montar(dados([]));
    const td = screen.getByText('Nenhum produto com estoque para esses filtros.').closest('td') as HTMLElement;
    expect(td.style.color).toBe('var(--text-mute)');
    expect(td.className).not.toContain('text-[var(');
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
