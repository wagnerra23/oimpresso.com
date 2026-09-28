/**
 * `shared/DataTable` — `density="dense"` é ADITIVA (playbook `ds-atomos` thread 04 · D-GRADE).
 *
 * De onde vêm os números: a §Alvo do `00-INDICE.md` do playbook (medidos no protótipo servido,
 * `table.pt-tbl`, dark, 1280px) — não do componente. O vitest roda em jsdom com `css: false`,
 * então aqui se prova a CLASSE que carrega cada número; o valor computado (`getComputedStyle`)
 * foi medido no browser com o CSS buildado e está no recibo `_saida-04.md`.
 *
 * A guarda do default NÃO compara contra HTML congelado (seria teste derivado do código, §5
 * 2026-06-05). Ela asserta a ausência de toda marca do `dense`, que é o que as telas
 * consumidoras dependem. A comparação byte-a-byte com o arquivo anterior foi feita uma vez,
 * fora do commit, e está no recibo.
 */
import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));

type Linha = { id: number; nome: string; valor: string };
const LINHAS: Linha[] = [
  { id: 1, nome: 'Primeira', valor: '10' },
  { id: 2, nome: 'Segunda', valor: '20' },
];
const PAG: PaginatorShape<Linha> = {
  data: LINHAS, total: 40, current_page: 1, last_page: 2, from: 1, to: 2,
  links: [
    { url: null, label: '&laquo; Previous', active: false },
    { url: '/x?page=1', label: '1', active: true },
    { url: '/x?page=2', label: '2', active: false },
    { url: '/x?page=2', label: 'Next &raquo;', active: false },
  ],
};
const COLUNAS: any = [
  { accessorKey: 'nome', header: 'Nome', enableSorting: true, meta: { width: 160 } },
  { accessorKey: 'valor', header: 'Valor', meta: { align: 'right', mono: true } },
];

// Classes que SÓ o `dense` introduz. Se alguma vazar pro default, o default mudou.
const MARCAS_TH = ['px-2.5', 'py-2', 'text-[11px]', 'uppercase', 'tracking-[.07em]', 'font-semibold'];
const MARCAS_TD = ['px-2.5', 'py-[7px]', 'text-[12.5px]'];

const cls = (el: Element | null) => (el?.className ?? '').split(/\s+/);

function montar(extra: Record<string, unknown> = {}) {
  const onRowClick = vi.fn();
  const r = render(
    <DataTable<Linha>
      columns={COLUNAS}
      data={LINHAS}
      pagination={PAG}
      endpoint="/x"
      caption="Tabela de teste"
      rowState={(l) => (l.id === 1 ? 'urgent' : undefined)}
      onRowClick={onRowClick}
      {...extra}
    />,
  );
  const table = r.container.querySelector('table') as HTMLTableElement;
  return { ...r, table, onRowClick };
}

describe('guarda — sem `density` nada muda (telas consumidoras)', () => {
  it.each([[{}], [{ density: 'default' }]])('%o: th, td, tbody e tr com as classes de sempre', (extra) => {
    const { table } = montar(extra);
    const th = table.querySelector('th');
    const td = table.querySelector('tbody td');
    for (const m of MARCAS_TH) expect(cls(th), `"${m}" é do dense`).not.toContain(m);
    for (const m of MARCAS_TD) expect(cls(td), `"${m}" é do dense`).not.toContain(m);
    expect(cls(th)).toEqual(expect.arrayContaining(['p-3', 'font-medium', 'whitespace-nowrap']));
    expect(cls(td)).toEqual(expect.arrayContaining(['p-3', 'align-top']));
    expect(cls(table.querySelector('tbody'))).toContain('divide-border');
    expect(cls(table.querySelector('tbody tr'))).toContain('hover:bg-accent/30');
    expect(cls(table.querySelector('tbody tr'))).not.toContain('hover:bg-primary/5');
  });
});

describe('dense — anatomia da §Alvo', () => {
  it('th: 11px · uppercase · .07em · 600 · pad 8×10', () => {
    const { table } = montar({ density: 'dense' });
    for (const th of table.querySelectorAll('th')) {
      expect(cls(th)).toEqual(expect.arrayContaining(MARCAS_TH));
      expect(cls(th)).not.toContain('p-3');
      expect(cls(th)).not.toContain('font-medium');
    }
  });

  it('td: 12.5px · pad 7×10 (alinhamento vertical preservado)', () => {
    const { table } = montar({ density: 'dense' });
    for (const td of table.querySelectorAll('tbody td')) {
      expect(cls(td)).toEqual(expect.arrayContaining([...MARCAS_TD, 'align-top']));
      expect(cls(td)).not.toContain('p-3');
    }
  });

  it('divisória --border a 60% e hover no roxo da marca a 5% (não o accent neutro)', () => {
    const { table } = montar({ density: 'dense' });
    expect(cls(table.querySelector('tbody'))).toEqual(expect.arrayContaining(['divide-y', 'divide-border/60']));
    const tr = table.querySelector('tbody tr');
    expect(cls(tr)).toContain('hover:bg-primary/5');
    expect(cls(tr)).not.toContain('hover:bg-accent/30');
  });
});

describe('dense — o que já funcionava continua funcionando', () => {
  it('nome acessível, scope, largura, alinhamento e mono', () => {
    const { table } = montar({ density: 'dense' });
    expect(screen.getByRole('table', { name: 'Tabela de teste' })).toBe(table);
    for (const th of table.querySelectorAll('th')) expect(th.getAttribute('scope')).toBe('col');
    expect(table.querySelector('colgroup col')?.getAttribute('style')).toContain('width: 160px');
    const ths = table.querySelectorAll('th');
    const thNome = ths[0]!;
    const thValor = ths[1]!;
    expect(cls(thNome)).toContain('text-left');
    expect(cls(thValor)).toContain('text-right');
    const tdValor = table.querySelectorAll('tbody tr')[0]!.querySelectorAll('td')[1]!;
    expect(cls(tdValor)).toEqual(expect.arrayContaining(['text-right', 'font-mono', 'tabular-nums']));
  });

  it('rowState e onRowClick (mouse e teclado)', () => {
    const { table, onRowClick } = montar({ density: 'dense' });
    const [l1, l2] = Array.from(table.querySelectorAll('tbody tr')) as [HTMLElement, HTMLElement];
    expect(l1.getAttribute('data-estado')).toBe('urgent');
    expect(l2.getAttribute('data-estado')).toBeNull();
    expect(l1.getAttribute('role')).toBe('button');
    fireEvent.click(l1);
    fireEvent.keyDown(l2, { key: 'Enter' });
    expect(onRowClick).toHaveBeenCalledTimes(2);
    expect(onRowClick.mock.calls[1]?.[0]).toEqual(LINHAS[1]);
  });

  it('cabeçalho ordenável e paginação seguem no lugar', () => {
    montar({ density: 'dense' });
    expect(screen.getByRole('button', { name: 'Nome' })).toBeTruthy();
    expect(screen.getByText(/Página 1 de 2/)).toBeTruthy();
  });
});
