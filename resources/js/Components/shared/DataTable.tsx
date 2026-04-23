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
}: Props<T>) {
  const [searchTerm, setSearchTerm] = useState(initialSearch);

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
          <Input
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder={searchPlaceholder}
            className="pl-9 pr-9"
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
        <table className="w-full text-sm">
          <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
            {table.getHeaderGroups().map((group) => (
              <tr key={group.id}>
                {group.headers.map((header) => {
                  const canSort = header.column.getCanSort();
                  return (
                    <th
                      key={header.id}
                      className="text-left p-3 font-medium whitespace-nowrap"
                    >
                      {canSort ? (
                        <button
                          type="button"
                          onClick={() => handleSort(header.id)}
                          className="flex items-center gap-1 hover:text-foreground"
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
              table.getRowModel().rows.map((row) => (
                <tr
                  key={rowKey ? rowKey(row.original) : row.id}
                  className="hover:bg-accent/30"
                >
                  {row.getVisibleCells().map((cell) => (
                    <td key={cell.id} className="p-3 align-top">
                      {flexRender(cell.column.columnDef.cell, cell.getContext()) as ReactNode}
                    </td>
                  ))}
                </tr>
              ))
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
