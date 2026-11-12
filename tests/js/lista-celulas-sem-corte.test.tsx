/**
 * Lista de produtos: a caixinha de seleção e o botão "Ações" cabem nas colunas deles.
 *
 * Antes, a linha mostrava "☐.." na primeira coluna e "Ações ▾ .." na segunda: a célula da densidade
 * `grid` corta o que transborda com reticências. Medido no navegador em 2026-11-12 (tabela real,
 * CSS de produção, 1280px):
 *
 *   | célula   | largura | espaço útil | conteúdo           | antes        | depois      |
 *   |----------|---------|-------------|--------------------|--------------|-------------|
 *   | seleção  | 34      | 14 → 34     | caixinha 16×16     | transborda   | cabe        |
 *   | Ação     | 96      | 76          | botão 80×32 → 75×26 | transborda  | cabe        |
 *
 * O protótipo (`produto-blade.jsx`, `Kebab` = `os-btn sm`) mede o botão em 72×26 com seta de 12px.
 *
 * O vitest roda em jsdom, sem layout: aqui se prova a CLASSE que produz cada medida. A medida no
 * navegador está no corpo do PR.
 */
import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import DataTable from '@/Components/shared/DataTable';
import AcoesProduto from '@/Pages/Produto/_components/lista/AcoesProduto';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));

type Linha = { id: number; nome: string };
const LINHAS: Linha[] = [{ id: 1, nome: 'Primeira' }];
const COLUNAS = [{ id: 'nome', header: 'Nome', accessorFn: (l: Linha) => l.nome, meta: { width: 200 } }];

const tokens = (el: Element) => (el.getAttribute('class') ?? '').split(/\s+/);

describe('célula da caixinha de seleção', () => {
  for (const density of ['grid', 'dense', 'default'] as const) {
    it(`densidade ${density}: 34px sem margem lateral, então a caixinha de 16px cabe`, () => {
      render(
        <DataTable<Linha>
          columns={COLUNAS as never}
          data={LINHAS}
          density={density}
          rowKey={(l) => l.id}
          selection={{ isSelected: () => false, onToggle: () => {}, allState: 'none', onToggleAll: () => {}, rowLabel: (l) => l.nome }}
        />,
      );
      const celulas = [
        screen.getByRole('checkbox', { name: 'Selecionar todas' }).closest('th')!,
        screen.getByRole('checkbox', { name: 'Selecionar Primeira' }).closest('td')!,
      ];
      for (const c of celulas) {
        const t = tokens(c);
        expect(t).toContain('w-[34px]');
        expect(t).toContain('px-0');
        // Nenhuma margem lateral da densidade sobrevive ao lado do px-0.
        expect(t.some((x) => /^px-(?!0$)/.test(x))).toBe(false);
      }
    });
  }
});

describe('botão "Ações" da linha', () => {
  it('26px de altura e seta de 12px, como o Kebab do protótipo', () => {
    render(
      <AcoesProduto
        linha={{ id: 7, nome: 'Camiseta', tipo: 'single', inativo: false, excluido: false } as never}
        permissoes={{ create: true, update: true, delete: true, opening_stock: true, view: true }}
        gruposDePreco={0}
        emiteNota={false}
        aoEscolher={() => {}}
      />,
    );
    const botao = screen.getByRole('button', { name: 'Ações do produto Camiseta' });
    const t = tokens(botao);
    expect(t).toContain('h-[26px]');
    expect(t).not.toContain('h-8');
    expect(t).toContain('text-[11.5px]');
    const seta = botao.querySelector('svg')!;
    // Com `size-3` a regra do botão (`[&_svg:not([class*='size-'])]:size-4`) não alcança a seta.
    expect(tokens(seta)).toContain('size-3');
  });
});
