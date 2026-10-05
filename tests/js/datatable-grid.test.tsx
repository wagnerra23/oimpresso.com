/**
 * `shared/DataTable` — `density="grid"` (anatomia do `DataGrid` do DS), seleção por caixinha,
 * paginador/endpoint opcionais e `rowClickable`. Tudo ADITIVO: quem não passa nada disso não
 * muda — a guarda do default/dense continua em `datatable-density.test.tsx`.
 *
 * De onde vêm os números: `prototipo-ui/design-system/components/DataGrid/DataGrid.jsx`
 * (th 10px .05em sobre `--bg-2`, pad 7×10 · td 5×10 · listra `color-mix` 55% · rodapé
 * "a–b de N <rótulo>"), medidos no protótipo da Fabricação em 2026-10-01. O vitest roda em jsdom
 * com `css: false`: aqui se prova a CLASSE que carrega cada número e o COMPORTAMENTO.
 */
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { router } from '@inertiajs/react';
import DataTable, { type PaginatorShape, type SelecaoDaTabela } from '@/Components/shared/DataTable';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));

type Linha = { id: number; nome: string; valor: string };
const LINHAS: Linha[] = [
  { id: 1, nome: 'Primeira', valor: '10' },
  { id: 2, nome: 'Segunda', valor: '20' },
  { id: 3, nome: 'Terceira', valor: '30' },
];
const PAG: PaginatorShape<Linha> = {
  data: LINHAS, total: 32, current_page: 1, last_page: 4, from: 1, to: 3,
  links: [
    { url: null, label: '&laquo; Anterior', active: false },
    { url: '/x?page=1', label: '1', active: true },
    { url: '/x?page=2', label: '2', active: false },
    { url: null, label: '...', active: false },
    { url: '/x?page=4', label: '4', active: false },
    { url: '/x?page=2', label: 'Próxima &raquo;', active: false },
  ],
};
const PAG_UMA: PaginatorShape<Linha> = {
  data: LINHAS, total: 3, current_page: 1, last_page: 1, from: 1, to: 3,
  links: [
    { url: null, label: '&laquo; Anterior', active: false },
    { url: '/x?page=1', label: '1', active: true },
    { url: null, label: 'Próxima &raquo;', active: false },
  ],
};
const COLUNAS: any = [
  { accessorKey: 'nome', header: 'Nome' },
  { accessorKey: 'valor', header: 'Valor', enableSorting: false, meta: { align: 'right', mono: true } },
];

const cls = (el: Element | null) => (el?.className ?? '').split(/\s+/);

function montar(extra: Record<string, unknown> = {}) {
  const onRowClick = vi.fn();
  const r = render(
    <DataTable<Linha>
      columns={COLUNAS}
      data={LINHAS}
      pagination={PAG}
      endpoint="/x"
      caption="Receitas"
      showSearch={false}
      density="grid"
      totalLabel="receitas"
      rowKey={(l) => l.id}
      onRowClick={onRowClick}
      {...extra}
    />,
  );
  const table = r.container.querySelector('table') as HTMLTableElement;
  const linhas = Array.from(table.querySelectorAll('tbody tr')) as HTMLElement[];
  return { ...r, table, linhas, onRowClick };
}

function selecao(over: Partial<SelecaoDaTabela<Linha>> = {}): SelecaoDaTabela<Linha> {
  return {
    isSelected: (l) => l.id === 2,
    onToggle: vi.fn(),
    allState: 'some',
    onToggleAll: vi.fn(),
    rowLabel: (l) => l.nome,
    ...over,
  };
}

beforeEach(() => {
  vi.mocked(router.get).mockClear();
  vi.mocked(router.visit).mockClear();
});

describe('grid — anatomia do DataGrid do DS', () => {
  it('th: 10px · uppercase · .05em · 600 · pad 7×10 · fundo --bg-2 · fixo no topo', () => {
    const { table } = montar();
    for (const th of table.querySelectorAll('th')) {
      expect(cls(th)).toEqual(
        expect.arrayContaining(['text-[10px]', 'uppercase', 'tracking-[.05em]', 'font-semibold', 'px-2.5', 'py-[7px]', 'bg-[var(--bg-2)]', 'sticky', 'top-0']),
      );
      // não é o `dense` (pt-tbl do Ponto) nem o default
      expect(cls(th)).not.toContain('text-[11px]');
      expect(cls(th)).not.toContain('p-3');
    }
    expect(cls(table.querySelector('thead'))).toContain('text-[var(--text-dim)]');
  });

  it('td: 12.5px herdado da tabela · altura de linha 1,45 · pad 5×10 · borda --border-2 · listra só nas pares', () => {
    const { table, linhas } = montar();
    const td = (l: HTMLElement) => l.querySelector('td');
    // O 12,5 mora no <table> e a célula herda — a célula mono pode então pôr 12px sem disputa.
    expect(cls(table)).toContain('text-[12.5px]');
    expect(cls(td(linhas[0]!))).toEqual(expect.arrayContaining(['py-[5px]', 'px-2.5', 'leading-[1.45]', 'border-[var(--border-2)]']));
    expect(cls(td(linhas[0]!))).not.toContain('text-[12.5px]');
    const LISTRA = 'bg-[color-mix(in_oklch,var(--bg-2)_55%,transparent)]';
    expect(cls(td(linhas[0]!))).not.toContain(LISTRA);
    expect(cls(td(linhas[1]!))).toContain(LISTRA);
    expect(cls(td(linhas[2]!))).not.toContain(LISTRA);
  });

  it('número em mono sai 12px com −0,01em no grid; fora do grid fica como sempre', () => {
    const { linhas } = montar();
    const tdValor = linhas[0]!.querySelectorAll('td')[1]!;
    expect(cls(tdValor)).toEqual(expect.arrayContaining(['font-mono', 'tabular-nums', 'text-[12px]', 'tracking-[-.01em]']));
    const fora = montar({ density: 'default' }).linhas[0]!.querySelectorAll('td')[1]!;
    expect(cls(fora)).toEqual(expect.arrayContaining(['font-mono', 'tabular-nums']));
    expect(cls(fora)).not.toContain('text-[12px]');
  });

  it('moldura com o rodapé DENTRO, e sem o rodapé antigo', () => {
    const { container } = montar();
    const rodape = container.querySelector('[data-slot="datatable-rodape"]') as HTMLElement;
    expect(rodape).toBeTruthy();
    expect(rodape.parentElement?.contains(container.querySelector('table'))).toBe(true);
    expect(screen.queryByText(/Página 1 de 4/)).toBeNull();
  });
});

describe('grid — rodapé "a–b de N <rótulo>"', () => {
  it('meta com o rótulo da tela e a página atual marcada', () => {
    montar();
    const nav = screen.getByRole('navigation', { name: 'Paginação' });
    expect(nav.textContent).toContain('1–3 de 32 receitas');
    expect(within(nav).getByRole('button', { name: '1' }).getAttribute('aria-current')).toBe('page');
    expect(within(nav).getByRole('button', { name: '2' }).getAttribute('aria-current')).toBeNull();
    expect(within(nav).getByText('…')).toBeTruthy();
  });

  it('as setas usam as URLs do servidor; "anterior" desabilita na 1ª página', () => {
    montar();
    const anterior = screen.getByRole('button', { name: 'Página anterior' }) as HTMLButtonElement;
    expect(anterior.disabled).toBe(true);
    fireEvent.click(screen.getByRole('button', { name: 'Próxima página' }));
    fireEvent.click(screen.getByRole('button', { name: '4' }));
    expect(vi.mocked(router.visit).mock.calls.map((c) => c[0])).toEqual(['/x?page=2', '/x?page=4']);
  });

  it('com UMA página o rodapé continua lá ("‹ 1 ›"), como no protótipo', () => {
    montar({ pagination: PAG_UMA });
    const nav = screen.getByRole('navigation', { name: 'Paginação' });
    expect(nav.textContent).toContain('1–3 de 3 receitas');
    expect((screen.getByRole('button', { name: 'Próxima página' }) as HTMLButtonElement).disabled).toBe(true);
  });

  it('lista vazia: "0–0 de 0"', () => {
    montar({ data: [], pagination: { ...PAG_UMA, data: [], total: 0, from: null, to: null } });
    expect(screen.getByRole('navigation', { name: 'Paginação' }).textContent).toContain('0–0 de 0 receitas');
  });
});

describe('grid — cabeçalho ordenável', () => {
  it('↕ apagado na coluna parada; ↓ e aria-sort na coluna ordenada', () => {
    const { table } = montar({ filters: { sort: 'nome', dir: 'desc' } });
    const thNome = table.querySelectorAll('th')[0]!;
    expect(thNome.getAttribute('aria-sort')).toBe('descending');
    expect(within(thNome).getByRole('button').textContent).toBe('Nome↓');
  });

  it('sem ordem ativa: ↕ com opacidade e sem aria-sort', () => {
    const { table } = montar();
    const thNome = table.querySelectorAll('th')[0]!;
    expect(thNome.getAttribute('aria-sort')).toBeNull();
    const ind = within(thNome).getByText('↕');
    expect(cls(ind)).toContain('opacity-40');
  });

  it('clicar alterna a direção e manda pro servidor (sem `page` → volta pra 1ª)', () => {
    montar({ filters: { sort: 'nome', dir: 'desc', cat: 'Bolos' } });
    fireEvent.click(screen.getByRole('button', { name: /Nome/ }));
    expect(router.get).toHaveBeenCalledWith('/x', { sort: 'nome', dir: 'asc', cat: 'Bolos', q: undefined }, expect.anything());
  });

  it('com a busca da TELA (showSearch=false), ordenar mantém o `q` dela', () => {
    // Antes de 2026-10-02 o `q` interno (vazio) sobrescrevia o da tela a cada clique de
    // ordenar — a busca digitada sumia. A tela das Receitas tem busca própria.
    montar({ filters: { q: 'lona', sort: 'nome', dir: 'asc' } });
    fireEvent.click(screen.getByRole('button', { name: /Nome/ }));
    const enviado = vi.mocked(router.get).mock.calls[0]?.[1] as Record<string, unknown>;
    expect(enviado.q).toBe('lona');
    expect(enviado.dir).toBe('desc');
  });

  it('com a busca DA TABELA (showSearch=true), o `q` sai do campo dela', () => {
    montar({ showSearch: true, initialSearch: 'placa', filters: { q: 'velho' } });
    fireEvent.click(screen.getByRole('button', { name: /Nome/ }));
    expect((vi.mocked(router.get).mock.calls[0]?.[1] as Record<string, unknown>).q).toBe('placa');
  });

  it('coluna com enableSorting:false não vira botão', () => {
    const { table } = montar();
    expect(within(table.querySelectorAll('th')[1]!).queryByRole('button')).toBeNull();
  });
});

describe('seleção por caixinha', () => {
  it('uma caixinha por linha + a do cabeçalho, com nome acessível', () => {
    montar({ selection: selecao() });
    expect(screen.getAllByRole('checkbox')).toHaveLength(4);
    expect(screen.getByRole('checkbox', { name: 'Selecionar todas' })).toBeTruthy();
    // Margem padrão do navegador, escrita à mão (o reset do Tailwind a zera) — é ela que dá ao
    // cabeçalho os 34px do protótipo.
    for (const c of screen.getAllByRole('checkbox')) expect(cls(c)).toContain('m-[3px_3px_3px_4px]');
    expect((screen.getByRole('checkbox', { name: 'Selecionar Segunda' }) as HTMLInputElement).checked).toBe(true);
    expect((screen.getByRole('checkbox', { name: 'Selecionar Primeira' }) as HTMLInputElement).checked).toBe(false);
  });

  it('o estado do cabeçalho vem de fora: some → indeterminado · all → marcado', () => {
    const { rerender } = montar({ selection: selecao({ allState: 'some' }) });
    const todas = () => screen.getByRole('checkbox', { name: 'Selecionar todas' }) as HTMLInputElement;
    expect(todas().indeterminate).toBe(true);
    expect(todas().checked).toBe(false);
    rerender(
      <DataTable<Linha> columns={COLUNAS} data={LINHAS} pagination={PAG} endpoint="/x" caption="Receitas"
        density="grid" selection={selecao({ allState: 'all' })} />,
    );
    expect(todas().indeterminate).toBe(false);
    expect(todas().checked).toBe(true);
  });

  it('marcar uma linha avisa a tela e NÃO abre a linha (mouse e Espaço)', () => {
    const s = selecao();
    const { onRowClick } = montar({ selection: s });
    const caixa = screen.getByRole('checkbox', { name: 'Selecionar Primeira' });
    fireEvent.click(caixa);
    fireEvent.keyDown(caixa, { key: ' ' });
    expect(s.onToggle).toHaveBeenCalledWith(LINHAS[0]);
    expect(onRowClick).not.toHaveBeenCalled();
  });

  it('"Selecionar todas" entrega a intenção — o escopo é da tela', () => {
    const s = selecao({ allState: 'none' });
    montar({ selection: s });
    fireEvent.click(screen.getByRole('checkbox', { name: 'Selecionar todas' }));
    expect(s.onToggleAll).toHaveBeenCalledWith(true);
  });

  it('grid: linha marcada pinta no roxo da marca e perde o hover; as outras mantêm', () => {
    const { linhas } = montar({ selection: selecao() });
    const marcada = linhas[1]!;
    expect(marcada.getAttribute('data-selecionada')).toBe('true');
    for (const td of marcada.querySelectorAll('td')) expect(cls(td)).toContain('bg-primary/15');
    expect(cls(marcada)).not.toContain('hover:[&>td]:bg-[var(--bg-2)]');
    expect(cls(linhas[0]!)).toContain('hover:[&>td]:bg-[var(--bg-2)]');
  });

  it('fora do grid a linha marcada vira o estado `selected` de sempre', () => {
    const { linhas } = montar({ density: 'default', selection: selecao() });
    expect(linhas[1]!.getAttribute('data-estado')).toBe('selected');
    expect(linhas[0]!.getAttribute('data-estado')).toBeNull();
  });

  it('largura: a coluna da caixinha entra no colgroup e no piso da tabela', () => {
    const cols = [{ ...COLUNAS[0], meta: { width: 200 } }, COLUNAS[1]];
    const { table } = montar({ columns: cols, selection: selecao() });
    const col = table.querySelectorAll('colgroup col');
    expect(col).toHaveLength(3);
    expect(col[0]!.getAttribute('style')).toContain('width: 34px');
    expect(table.getAttribute('style')).toContain('min-width: 234px');
  });

  it('lista vazia ocupa as colunas + a da caixinha', () => {
    const { table } = montar({ data: [], selection: selecao() });
    expect(table.querySelector('tbody td')?.getAttribute('colspan')).toBe('3');
  });
});

describe('sem paginador e sem endpoint (lista que cabe numa página)', () => {
  it('sem busca, sem rodapé, cabeçalho não vira botão', () => {
    const { table, container } = montar({ pagination: undefined, endpoint: undefined, showSearch: true });
    expect(container.querySelector('input:not([type="checkbox"])')).toBeNull();
    expect(container.querySelector('[data-slot="datatable-rodape"]')).toBeNull();
    expect(within(table.querySelectorAll('th')[0]!).queryByRole('button')).toBeNull();
    expect(table.querySelectorAll('tbody tr')).toHaveLength(3);
  });
});

describe('rowClickable', () => {
  it('só a linha que devolve true vira controle', () => {
    const { linhas, onRowClick } = montar({ rowClickable: (l: Linha) => l.id !== 2 });
    expect(linhas[0]!.getAttribute('role')).toBe('button');
    expect(linhas[1]!.getAttribute('role')).toBeNull();
    expect(linhas[1]!.getAttribute('tabindex')).toBeNull();
    fireEvent.click(linhas[1]!);
    expect(onRowClick).not.toHaveBeenCalled();
    fireEvent.click(linhas[0]!);
    expect(onRowClick).toHaveBeenCalledWith(LINHAS[0]);
  });
});
