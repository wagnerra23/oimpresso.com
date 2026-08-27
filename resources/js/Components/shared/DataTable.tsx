import { useState, useEffect, ReactNode } from 'react';
import { router } from '@inertiajs/react';
import {
  ColumnDef,
  flexRender,
  getCoreRowModel,
  useReactTable,
} from '@tanstack/react-table';
import { ArrowUpDown, ChevronLeft, ChevronRight, Search, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

/**
 * DataTable reusável com TanStack Table v8 + server-side pagination
 * (Inertia paginator + withQueryString). Integra com Laravel Scout
 * quando o controller aceita `?q=` (ADR arq/0006).
 *
 * Features built-in:
 * - Busca debounced (300ms) via query param
 * - Sort via query param (?sort=X&dir=asc)
 * - Paginação server-side via Inertia links
 * - Colunas tipadas em TypeScript
 * - Zero jQuery, zero CSS externo — só Tailwind + shadcn primitives
 *
 * Uso:
 *   const columns: ColumnDef<Role>[] = [
 *     { accessorKey: 'name', header: 'Nome' },
 *     { accessorKey: 'users_count', header: 'Usuários', enableSorting: true },
 *     { id: 'actions', cell: ({ row }) => <Button>Editar</Button> },
 *   ];
 *
 *   <DataTable
 *     columns={columns}
 *     data={paginator.data}
 *     pagination={paginator}
 *     endpoint="/roles"
 *     filters={{ status: 'active' }}
 *     searchPlaceholder="Buscar roles..."
 *   />
 */

/**
 * GEOMETRIA DA COLUNA — o vocabulário que o protótipo já falava e a travessia não tinha
 * onde pousar.
 *
 * Os protótipos Cowork declaram `columns[] = { width, align, mono }` e `rows[].state` desde
 * sempre. O `ColumnDef` do TanStack não tem campo pra nada disso, então o `meta` chegava aqui
 * e era **ignorado em silêncio** — não dava erro de tipo, não dava aviso, simplesmente não
 * acontecia. Foi assim que a Arquivos/Index perdeu as 7 larguras, o alinhamento à direita da
 * coluna Tamanho e a trilha de urgência, enquanto o `mono` (que dava pra fazer à mão dentro
 * da célula) chegou. Reportado por [W] em 2026-08-27.
 *
 * Declarado por module augmentation, e não como prop paralela, de propósito: assim o campo
 * aparece no autocomplete de QUALQUER `ColumnDef` do repo. O que não se enxerga não se aplica.
 */
declare module '@tanstack/react-table' {
  // Os dois parametros existem so pra casar a assinatura do tipo original do TanStack —
  // este `meta` nao depende de nenhum dos dois.
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  interface ColumnMeta<TData, TValue> {
    /** Largura fixa em px. Uma largura declarada já põe a tabela em `table-layout: fixed`. */
    width?: number;
    /** Alinhamento da CÉLULA (`<th>` e `<td>`) — nunca de um filho dela. Ver nota abaixo. */
    align?: 'left' | 'center' | 'right';
    /** Fonte monoespaçada + `tabular-nums` na célula inteira. */
    mono?: boolean;
  }
}

/**
 * Estado visual da LINHA — o `rows[].state` do protótipo.
 * `urgent` = trilha vermelha à esquerda · `archived` = esmaecida · `selected` = fundo accent.
 */
export type EstadoDaLinha = 'urgent' | 'archived' | 'selected';

/** Classe de alinhamento aplicada à célula. `undefined` mantém o padrão (esquerda). */
const CLASSE_ALINHAMENTO: Record<'left' | 'center' | 'right', string> = {
  left: 'text-left',
  center: 'text-center',
  right: 'text-right',
};

/** O cabeçalho ordenável é um flex — `text-*` não move flex, `justify-*` move. */
const CLASSE_JUSTIFICA: Record<'left' | 'center' | 'right', string> = {
  left: 'justify-start',
  center: 'justify-center',
  right: 'justify-end',
};

/**
 * A trilha vai no `td:first-child`, não no `<tr>`.
 *
 * Não é preciosismo: `box-shadow` em `<tr>` só pinta quando `border-collapse: separate`, e
 * qualquer folha que colapse a tabela apaga a trilha sem erro nenhum. `<td>` pinta sempre.
 * O `opacity` do arquivado, ao contrário, herda bem e fica na própria linha.
 */
const CLASSE_ESTADO: Record<EstadoDaLinha, string> = {
  urgent: '[&>td:first-child]:shadow-[inset_3px_0_0_var(--color-destructive)]',
  archived: 'opacity-60',
  selected: 'bg-accent/50',
};

export interface PaginatorShape<T> {
  data: T[];
  total: number;
  current_page: number;
  last_page: number;
  from: number | null;
  to: number | null;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props<T> {
  columns: ColumnDef<T, any>[];
  data: T[];
  pagination: PaginatorShape<T>;
  endpoint: string;
  filters?: Record<string, string | number | null | undefined>;
  searchPlaceholder?: string;
  emptyMessage?: string;
  rowKey?: (row: T) => string | number;
  /** Se true, mostra SearchBar integrada com Scout (?q=) */
  showSearch?: boolean;
  /** Valor inicial da busca vindo do backend */
  initialSearch?: string;
  /**
   * Estado visual por linha — o `rows[].state` do protótipo. Devolva `undefined` pra linha
   * sem estado. É a linha que decide, com o dado dela: a tabela não adivinha.
   */
  rowState?: (row: T) => EstadoDaLinha | undefined;
  /**
   * Piso de largura da tabela, em px. Só vale quando alguma coluna declara `meta.width`.
   *
   * Sem piso, `table-layout: fixed` num container estreito espreme a coluna fluida até zero
   * em vez de rolar. O default é a SOMA das larguras declaradas — o menor número que não é
   * inventado: garante que nenhuma coluna com largura declarada seja espremida, e deixa a
   * rolagem horizontal do wrapper fazer o resto. Passe um valor maior quando o protótipo
   * declarar um (ex.: `.arq-lista table{min-width:1020px}`).
   */
  minTableWidth?: number;
}

export default function DataTable<T>({
  columns,
  data,
  pagination,
  endpoint,
  filters = {},
  searchPlaceholder = 'Buscar...',
  emptyMessage = 'Nenhum resultado.',
  rowKey,
  showSearch = true,
  initialSearch = '',
  rowState,
  minTableWidth,
}: Props<T>) {
  const [searchTerm, setSearchTerm] = useState(initialSearch);

  // A geometria é lida das colunas UMA vez e vira `<colgroup>` — que é a forma canônica de
  // declarar largura em tabela HTML, e a única que o navegador respeita sob `table-layout:
  // fixed`. Largura em `<td>` sob layout fixo é ignorada; foi por isso que declarar no
  // `className` da célula nunca teria funcionado.
  const larguras = columns.map((c) => c.meta?.width);
  const temLargura = larguras.some((w) => typeof w === 'number' && w > 0);
  const somaDeclarada = larguras.reduce<number>((s, w) => s + (typeof w === 'number' ? w : 0), 0);
  const pisoDaTabela = temLargura ? (minTableWidth ?? somaDeclarada) : undefined;

  // Debounce busca — envia pro backend (Scout faz keyword/vector lookup)
  useEffect(() => {
    if (searchTerm === initialSearch) return;
    const handle = setTimeout(() => {
      router.get(
        endpoint,
        { ...filters, q: searchTerm || undefined },
        { preserveScroll: true, preserveState: true, replace: true }
      );
    }, 300);
    return () => clearTimeout(handle);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchTerm]);

  const table = useReactTable({
    data,
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,   // paginação é server-side
    manualSorting: true,      // sort é server-side
    pageCount: pagination.last_page,
  });

  const handleSort = (columnId: string) => {
    const currentSort = (filters as any).sort as string | undefined;
    const currentDir = (filters as any).dir as string | undefined;
    let newDir: 'asc' | 'desc' = 'asc';
    if (currentSort === columnId) newDir = currentDir === 'asc' ? 'desc' : 'asc';

    router.get(
      endpoint,
      { ...filters, q: searchTerm || undefined, sort: columnId, dir: newDir },
      { preserveScroll: true, preserveState: true, replace: true }
    );
  };

  return (
    <div className="space-y-3">
      {showSearch && (
        <div className="relative">
          <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          {/* O className abaixo NÃO volta a ser `pl-9 pr-9`: o <Input> nasce `variant="cowork"`,
              cujo `.cw-input` é UNLAYERED (cowork-fields.css entra por @import sem @layer) e usa
              o shorthand `padding: 0 8px`. As utilitárias do Tailwind v4 vivem em
              `@layer utilities`, e pela cascata de layers estilo unlayered SEMPRE vence layered —
              então `pl-9` era simplesmente ignorado, o texto começava em 8px e a lupa
              (`left-3` = 12px) ficava encavalada sobre a primeira letra do placeholder.
              As longhands são a solução canônica dessa colisão, criada por Wagner em 2026-06-13
              pro MESMO bug na lista de clientes. Quatro telas já as usavam à mão e este DataTable
              COMPARTILHADO tinha ficado de fora — por isso o defeito aparecia em toda tela que
              usa a busca dele, não só na Arquivos onde foi reportado. */}
          <Input
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder={searchPlaceholder}
            className="cw-input-icon-left cw-input-icon-right"
          />
          {searchTerm && (
            <button
              type="button"
              onClick={() => setSearchTerm('')}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
              aria-label="Limpar busca"
            >
              <X size={14} />
            </button>
          )}
        </div>
      )}

      <div className="border border-border rounded overflow-x-auto">
        <table
          className={`w-full text-sm${temLargura ? ' table-fixed' : ''}`}
          style={pisoDaTabela ? { minWidth: pisoDaTabela } : undefined}
        >
          {temLargura && (
            <colgroup>
              {larguras.map((w, i) => (
                // Coluna sem largura declarada fica sem `<col style>` de propósito: ela é a
                // FLUIDA, e absorve a sobra. Declarar todas tira essa folga.
                <col key={i} style={typeof w === 'number' && w > 0 ? { width: w } : undefined} />
              ))}
            </colgroup>
          )}
          <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
            {table.getHeaderGroups().map((group) => (
              <tr key={group.id}>
                {group.headers.map((header) => {
                  const canSort = header.column.getCanSort();
                  const align = header.column.columnDef.meta?.align;
                  return (
                    <th
                      key={header.id}
                      // O alinhamento é da CÉLULA. Escrever `text-right` num <span> dentro
                      // dela move o texto e deixa o cabeçalho à esquerda — número à direita
                      // sob rótulo à esquerda foi exatamente o defeito reportado.
                      className={`${CLASSE_ALINHAMENTO[align ?? 'left']} p-3 font-medium whitespace-nowrap`}
                    >
                      {canSort ? (
                        <button
                          type="button"
                          onClick={() => handleSort(header.id)}
                          className={`flex w-full items-center gap-1 hover:text-foreground ${CLASSE_JUSTIFICA[align ?? 'left']}`}
                        >
                          {flexRender(header.column.columnDef.header, header.getContext()) as ReactNode}
                          <ArrowUpDown size={11} className="opacity-50" />
                        </button>
                      ) : (
                        flexRender(header.column.columnDef.header, header.getContext()) as ReactNode
                      )}
                    </th>
                  );
                })}
              </tr>
            ))}
          </thead>
          <tbody className="divide-y divide-border">
            {table.getRowModel().rows.length === 0 ? (
              <tr>
                <td colSpan={columns.length} className="p-12 text-center text-sm text-muted-foreground">
                  {emptyMessage}
                </td>
              </tr>
            ) : (
              table.getRowModel().rows.map((row) => {
                const estado = rowState?.(row.original);
                return (
                  <tr
                    key={rowKey ? rowKey(row.original) : row.id}
                    className={`hover:bg-accent/30${estado ? ' ' + CLASSE_ESTADO[estado] : ''}`}
                    data-estado={estado}
                  >
                    {row.getVisibleCells().map((cell) => {
                      const meta = cell.column.columnDef.meta;
                      const align = meta?.align;
                      return (
                        <td
                          key={cell.id}
                          className={`${CLASSE_ALINHAMENTO[align ?? 'left']} p-3 align-top${meta?.mono ? ' font-mono tabular-nums' : ''}`}
                        >
                          {flexRender(cell.column.columnDef.cell, cell.getContext()) as ReactNode}
                        </td>
                      );
                    })}
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      {pagination.last_page > 1 && (
        <div className="flex items-center justify-between text-xs text-muted-foreground">
          <span>
            Página {pagination.current_page} de {pagination.last_page} · {pagination.total} item(s)
          </span>
          <div className="flex gap-1">
            {pagination.links.map((link, i) => {
              const isPrev = link.label.includes('Previous') || link.label.includes('&laquo;');
              const isNext = link.label.includes('Next') || link.label.includes('&raquo;');
              const content = isPrev ? <ChevronLeft size={12} /> : isNext ? <ChevronRight size={12} /> : link.label;
              return (
                <Button
                  key={i}
                  variant={link.active ? 'default' : 'outline'}
                  size="sm"
                  className="h-7 min-w-8 px-2 text-xs"
                  disabled={!link.url}
                  onClick={() => link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })}
                  dangerouslySetInnerHTML={typeof content === 'string' ? { __html: content } : undefined}
                  children={typeof content === 'string' ? undefined : content}
                />
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
