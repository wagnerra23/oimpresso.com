/**
 * `shared/DataTable` — `resizable`: arrastar a borda do cabeçalho muda a largura da coluna.
 *
 * De onde vêm os números: `prototipo-ui/design-system/components/DataGrid/DataGrid.jsx`
 * (`startResize`), conferidos com arrasto real no protótipo da lista de produtos em 2026-10-13:
 * +50px levou "Produto" de 300 para 350 e a tabela de 1704 para 1754; encolher além do piso
 * parou em 48; a ordenação não mudou. Sem alça em "Ação", "Imagem" e na última coluna.
 *
 * O jsdom não faz layout (`getBoundingClientRect` devolve 0), então a largura RENDERIZADA do
 * cabeçalho é informada pelo teste — é ela, e não a declarada, que o arrasto usa como partida.
 *
 * ⚠️ Nenhuma lane de CI roda este arquivo (cada lane Vitest lista os próprios specs). O caso que
 * roda em lane está em `tests/a11y-primitives.test.tsx`.
 * Comando: npx vitest run tests/js/datatable-redimensionar.test.tsx
 */
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { useState } from 'react';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import DataTable, { type PaginatorShape, type SelecaoDaTabela } from '@/Components/shared/DataTable';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));

type Linha = { id: number; nome: string; local: string; sku: string };
const LINHAS: Linha[] = [
  { id: 1, nome: 'Camiseta', local: 'Matriz', sku: 'A1' },
  { id: 2, nome: 'Blusa', local: 'Filial', sku: 'B2' },
];
const PAG: PaginatorShape<Linha> = {
  data: LINHAS, total: 2, current_page: 1, last_page: 1, from: 1, to: 2,
  links: [
    { url: null, label: '&laquo; Anterior', active: false },
    { url: '/x?page=1', label: '1', active: true },
    { url: null, label: 'Próxima &raquo;', active: false },
  ],
};
const COLUNAS: ColumnDef<Linha, unknown>[] = [
  { id: 'acao', header: 'Ação', enableSorting: false, meta: { width: 96, resizable: false }, cell: () => '…' },
  { id: 'nome', header: 'Nome', accessorFn: (l) => l.nome, meta: { width: 300 } },
  { id: 'local', header: 'Local', enableSorting: false, meta: { width: 150 }, cell: ({ row }) => row.original.local },
  { id: 'sku', header: 'SKU', accessorFn: (l) => l.sku, meta: { width: 120 } },
];

function Tabela({ colunas = COLUNAS, linhas = LINHAS, ...extra }: { colunas?: ColumnDef<Linha, unknown>[]; linhas?: Linha[] } & Record<string, unknown>) {
  return (
    <DataTable<Linha>
      columns={colunas}
      data={linhas}
      pagination={PAG}
      endpoint="/x"
      caption="Itens"
      showSearch={false}
      density="grid"
      rowKey={(l) => l.id}
      filters={{ sort: 'nome', dir: 'asc' }}
      {...extra}
    />
  );
}

const alcas = () => Array.from(document.querySelectorAll<HTMLElement>('[data-slot="datatable-redimensionar"]'));
const th = (rotulo: string) => screen.getByRole('columnheader', { name: new RegExp(`^${rotulo}`) });
const alcaDe = (rotulo: string) => th(rotulo).querySelector<HTMLElement>('[data-slot="datatable-redimensionar"]');
/** Larguras do `<colgroup>`, na ordem das colunas (sem a da caixinha de seleção). */
const larguras = () => Array.from(document.querySelectorAll('colgroup col')).map((c) => (c as HTMLElement).style.width);
const pisoDaTabela = () => (document.querySelector('table') as HTMLElement).style.minWidth;

/** O jsdom não mede: diz ao cabeçalho quanto ele "renderiza". */
function renderizaCom(rotulo: string, largura: number) {
  const el = th(rotulo);
  el.getBoundingClientRect = () => ({ width: largura, height: 28, top: 0, left: 0, right: largura, bottom: 28, x: 0, y: 0, toJSON: () => ({}) });
}

function arrastar(rotulo: string, de: number, ate: number, soltar = true) {
  const alca = alcaDe(rotulo);
  if (!alca) throw new Error(`"${rotulo}" não tem alça`);
  fireEvent.mouseDown(alca, { clientX: de });
  fireEvent.mouseMove(document, { clientX: ate });
  if (soltar) fireEvent.mouseUp(document);
}

beforeEach(() => vi.clearAllMocks());
afterEach(cleanup);

describe('DataTable resizable', () => {
  it('sem a prop, a tabela não ganha alça nenhuma (as outras telas não mudam)', () => {
    render(<Tabela />);
    expect(alcas()).toHaveLength(0);
    expect(larguras()).toEqual(['96px', '300px', '150px', '120px']);
  });

  it('com a prop: alça nas colunas do meio; nenhuma na que se declara fixa nem na última', () => {
    render(<Tabela resizable />);
    expect(alcaDe('Ação')).toBeNull();
    expect(alcaDe('Nome')).not.toBeNull();
    expect(alcaDe('Local')).not.toBeNull();
    expect(alcaDe('SKU')).toBeNull();
    expect(alcas()).toHaveLength(2);
  });

  it('arrastar +50 alarga só aquela coluna, e a tabela cresce o mesmo tanto', () => {
    render(<Tabela resizable />);
    expect(pisoDaTabela()).toBe('666px');
    renderizaCom('Nome', 300);

    arrastar('Nome', 784, 834);

    expect(larguras()).toEqual(['96px', '350px', '150px', '120px']);
    expect(pisoDaTabela()).toBe('716px');
  });

  it('a partida é a largura RENDERIZADA, não a declarada', () => {
    render(<Tabela resizable />);
    renderizaCom('Nome', 420);

    arrastar('Nome', 500, 510);

    expect(larguras()[1]).toBe('430px');
  });

  it('encolher além do piso para em 48px', () => {
    render(<Tabela resizable />);
    renderizaCom('Local', 150);

    arrastar('Local', 984, 784);

    expect(larguras()).toEqual(['96px', '300px', '48px', '120px']);
  });

  it('o piso é exatamente 48: um pixel acima dele ainda acompanha o mouse', () => {
    render(<Tabela resizable />);
    renderizaCom('Local', 150);

    arrastar('Local', 984, 883, false);
    expect(larguras()[2]).toBe('49px');
    fireEvent.mouseMove(document, { clientX: 882 });
    expect(larguras()[2]).toBe('48px');
    fireEvent.mouseMove(document, { clientX: 881 });
    expect(larguras()[2]).toBe('48px');
    fireEvent.mouseUp(document);
  });

  it('arrastar para a esquerda encolhe', () => {
    render(<Tabela resizable />);
    renderizaCom('Nome', 300);

    arrastar('Nome', 784, 734);

    expect(larguras()[1]).toBe('250px');
  });

  it('largura de partida fracionária é arredondada', () => {
    render(<Tabela resizable />);
    renderizaCom('Nome', 299.6);

    arrastar('Nome', 100, 100);

    expect(larguras()[1]).toBe('300px');
  });

  it('a largura acompanha o mouse durante o arrasto e para de mudar depois de soltar', () => {
    render(<Tabela resizable />);
    renderizaCom('Nome', 300);

    arrastar('Nome', 100, 120, false);
    expect(larguras()[1]).toBe('320px');
    fireEvent.mouseMove(document, { clientX: 160 });
    expect(larguras()[1]).toBe('360px');
    fireEvent.mouseUp(document);

    fireEvent.mouseMove(document, { clientX: 400 });
    expect(larguras()[1]).toBe('360px');
  });

  it('arrastar não ordena: nenhuma navegação é disparada', () => {
    render(<Tabela resizable />);
    renderizaCom('Nome', 300);

    arrastar('Nome', 784, 834);

    expect(router.get).not.toHaveBeenCalled();
    expect(router.visit).not.toHaveBeenCalled();
  });

  it('o cabeçalho continua ordenando pelo clique no rótulo', () => {
    render(<Tabela resizable />);
    fireEvent.click(screen.getByRole('button', { name: /Nome/ }));
    expect(router.get).toHaveBeenCalledTimes(1);
  });

  it('a largura é da coluna, não da posição: tirar uma coluna do meio não a passa para a vizinha', () => {
    const { rerender } = render(<Tabela resizable />);
    renderizaCom('Nome', 300);
    arrastar('Nome', 784, 834);
    expect(larguras()).toEqual(['96px', '350px', '150px', '120px']);

    // Some "Ação" (a posição 1 passa a ser "Local") e depois volta.
    rerender(<Tabela resizable colunas={COLUNAS.filter((c) => c.id !== 'acao')} />);
    expect(larguras()).toEqual(['350px', '150px', '120px']);
    rerender(<Tabela resizable colunas={COLUNAS.filter((c) => c.id !== 'nome')} />);
    expect(larguras()).toEqual(['96px', '150px', '120px']);
    rerender(<Tabela resizable />);
    expect(larguras()).toEqual(['96px', '350px', '150px', '120px']);
  });

  it('a largura sobrevive à troca das linhas (ordenar e trocar de página)', () => {
    const { rerender } = render(<Tabela resizable />);
    renderizaCom('Nome', 300);
    arrastar('Nome', 784, 834);

    rerender(<Tabela resizable linhas={[LINHAS[1] as Linha]} />);

    expect(larguras()[1]).toBe('350px');
  });

  it('com caixinha de seleção, a coluna dela não é contada como coluna de dados', () => {
    const selecao: SelecaoDaTabela<Linha> = {
      isSelected: () => false, onToggle: () => undefined, rowLabel: (l) => l.nome, allState: 'none', onToggleAll: () => undefined,
    };
    render(<Tabela resizable selection={selecao} />);
    renderizaCom('Nome', 300);

    arrastar('Nome', 784, 834);

    expect(larguras()).toEqual(['34px', '96px', '350px', '150px', '120px']);
    expect(pisoDaTabela()).toBe('750px');
  });

  it('desmontar no meio do arrasto não deixa a tabela ouvindo o documento', () => {
    const remover = vi.spyOn(document, 'removeEventListener');
    const { unmount } = render(<Tabela resizable />);
    renderizaCom('Nome', 300);
    arrastar('Nome', 100, 120, false);

    unmount();

    const tipos = remover.mock.calls.map((c) => c[0]);
    expect(tipos).toContain('mousemove');
    expect(tipos).toContain('mouseup');
    remover.mockRestore();
  });

  // ── Quem guarda a largura ────────────────────────────────────────────────────────────────
  // Medido em produção em 2026-10-13 (lista de produtos): a tabela mora dentro de `<Deferred>`,
  // que a DESMONTA a cada ordenação/página/filtro. A largura guardada na tabela morria junto —
  // Produto voltava de 348 para 300. Os casos de `rerender` acima não pegam isso: no `rerender`
  // a tabela continua montada.
  it('guardada na tabela (padrão): desmontar e montar de novo PERDE a largura', () => {
    function Tela() {
      const [montada, setMontada] = useState(true);
      return (
        <div>
          <button type="button" onClick={() => setMontada((m) => !m)}>alternar</button>
          {montada ? <Tabela resizable /> : <p>carregando…</p>}
        </div>
      );
    }
    render(<Tela />);
    renderizaCom('Nome', 300);
    arrastar('Nome', 784, 834);
    expect(larguras()[1]).toBe('350px');

    fireEvent.click(screen.getByRole('button', { name: 'alternar' }));
    expect(document.querySelector('table')).toBeNull();
    fireEvent.click(screen.getByRole('button', { name: 'alternar' }));

    expect(larguras()[1]).toBe('300px');
  });

  it('guardada por quem usa (columnWidths + onColumnResize): sobrevive à tabela ser desmontada e montada de novo', () => {
    function Tela() {
      const [montada, setMontada] = useState(true);
      const [guardadas, setGuardadas] = useState<Record<string, number>>({});
      return (
        <div>
          <button type="button" onClick={() => setMontada((m) => !m)}>alternar</button>
          {montada ? (
            <Tabela resizable columnWidths={guardadas} onColumnResize={(id: string, w: number) => setGuardadas((m) => ({ ...m, [id]: w }))} />
          ) : (
            <p>carregando…</p>
          )}
        </div>
      );
    }
    render(<Tela />);
    renderizaCom('Nome', 300);
    arrastar('Nome', 784, 834);
    expect(larguras()).toEqual(['96px', '350px', '150px', '120px']);

    fireEvent.click(screen.getByRole('button', { name: 'alternar' }));
    expect(document.querySelector('table')).toBeNull();
    fireEvent.click(screen.getByRole('button', { name: 'alternar' }));

    expect(larguras()).toEqual(['96px', '350px', '150px', '120px']);
    expect(pisoDaTabela()).toBe('716px');
  });

  it('com dono de fora, a tabela só avisa: id da coluna + largura nova, a cada passo, já com o piso', () => {
    const aoRedimensionar = vi.fn();
    render(<Tabela resizable columnWidths={{ local: 200 }} onColumnResize={aoRedimensionar} />);
    // A largura vem do dono, não da declarada (150).
    expect(larguras()).toEqual(['96px', '300px', '200px', '120px']);
    renderizaCom('Local', 200);

    arrastar('Local', 500, 520, false);
    fireEvent.mouseMove(document, { clientX: 100 });
    fireEvent.mouseUp(document);

    expect(aoRedimensionar.mock.calls).toEqual([['local', 220], ['local', 48]]);
    // O dono não atualizou nada: a tabela não muda sozinha.
    expect(larguras()).toEqual(['96px', '300px', '200px', '120px']);
  });

  it('fora do grid o cabeçalho com alça vira âncora dela (relative); sem alça, não', () => {
    render(<Tabela resizable density="default" />);
    expect(th('Nome').className.split(/\s+/)).toContain('relative');
    expect(th('SKU').className.split(/\s+/)).not.toContain('relative');
  });
});
