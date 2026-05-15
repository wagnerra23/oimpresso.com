// US-PROD-001 — Lista de Produtos Inertia/React.
// Migração Blade legacy `product/index.blade.php` (DataTables + jQuery + Select2)
// para Cockpit Pattern V2 (ADR 0110). Canary: Lara (filha Martinho) biz=164.
//
// Pain-point #1 reunião 2026-05-14: velocidade pra encontrar peça + cadastrar
// produto novo. Lara passa o dia em /products. Monitor 1280px, não-técnica.
//
// Refs: ADR 0104 (processo MWART), ADR 0110 (Cockpit V2), ADR 0093 (Tier 0),
//        ADR 0141 (skill migracao-blade-react), Pages/Crm/Contacts/Index.tsx (mais recente).

import AppShellV2 from '@/Layouts/AppShellV2';
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import {
  AlertTriangle,
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Copy,
  Edit,
  Eye,
  History,
  Layers,
  Loader2,
  MoreVertical,
  Package,
  Plus,
  Power,
  Search,
  Trash2,
  X,
} from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

// ── Types ────────────────────────────────────────────────────────────────────

interface ProductKpis {
  total: number;
  with_stock: number;
  in_alert: number;
  inactive: number;
}

interface ProductRow {
  id: number;
  name: string;
  sku: string | null;
  type: 'single' | 'variable' | 'combo' | string;
  enable_stock: number;
  is_inactive: number;
  not_for_selling: number;
  alert_quantity: string | number | null;
  image: string | null;
  product_custom_field1: string | null;
  brand: string | null;
  unit: string | null;
  category: string | null;
  current_stock: string | number | null;
  min_price: string | number | null;
  max_price: string | number | null;
  min_purchase_price: string | number | null;
  max_purchase_price: string | number | null;
}

interface ListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

type StatusFilter = '' | 'with_stock' | 'in_alert' | 'inactive';
type SortKey = 'name' | 'sku' | 'category' | 'brand' | 'current_stock' | 'min_price';
type SortDir = 'asc' | 'desc';

interface DropdownOption {
  [id: number]: string;
}

export interface ProductsIndexPageProps {
  kpis: ProductKpis;
  permissions: {
    create: boolean;
    update: boolean;
    delete: boolean;
    view: boolean;
    opening_stock: boolean;
  };
  filterOptions: {
    categories: DropdownOption;
    brands: DropdownOption;
    units: DropdownOption;
  };
}

const PER_PAGE_OPTIONS = [10, 25, 50, 100];
const STATUS_FILTER_STORAGE_KEY = 'oimpresso.products.lastStatus';
const SORT_KEY_STORAGE_KEY = 'oimpresso.products.sortKey';
const SORT_DIR_STORAGE_KEY = 'oimpresso.products.sortDir';
const TYPE_FILTER_STORAGE_KEY = 'oimpresso.products.lastType';

// ── Formatters ───────────────────────────────────────────────────────────────

function formatNumber(raw: string | number | null | undefined): string {
  if (raw === null || raw === undefined || raw === '') return '—';
  const n = typeof raw === 'string' ? parseFloat(raw) : raw;
  if (!isFinite(n)) return '—';
  // pt-BR num localized — preserva decimais até 3 dígitos significativos
  return n.toLocaleString('pt-BR', { maximumFractionDigits: 3, minimumFractionDigits: 0 });
}

function formatCurrency(raw: string | number | null | undefined): string {
  if (raw === null || raw === undefined || raw === '') return '—';
  const n = typeof raw === 'string' ? parseFloat(raw) : raw;
  if (!isFinite(n)) return '—';
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function readStoredFilter<T extends string>(
  key: string,
  allowed: readonly T[],
  fallback: T,
): T {
  if (typeof window === 'undefined') return fallback;
  try {
    const params = new URLSearchParams(window.location.search);
    const fromUrl = params.get(key);
    if (fromUrl !== null && (allowed as readonly string[]).includes(fromUrl)) {
      return fromUrl as T;
    }
  } catch (_) {
    /* SSR */
  }
  try {
    const v = window.localStorage.getItem(key);
    if (v !== null && (allowed as readonly string[]).includes(v)) {
      return v as T;
    }
  } catch (_) {
    /* localStorage indisponível */
  }
  return fallback;
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function ProductsIndex(props: ProductsIndexPageProps) {
  const { kpis, permissions, filterOptions } = props;

  const STATUS_VALUES = ['', 'with_stock', 'in_alert', 'inactive'] as const;
  const TYPE_VALUES = ['', 'single', 'variable', 'combo'] as const;

  const [statusFilter, setStatusFilter] = useState<StatusFilter>(() =>
    readStoredFilter<StatusFilter>(STATUS_FILTER_STORAGE_KEY, STATUS_VALUES, ''),
  );
  const [typeFilter, setTypeFilter] = useState<string>(() =>
    readStoredFilter<(typeof TYPE_VALUES)[number]>(TYPE_FILTER_STORAGE_KEY, TYPE_VALUES, ''),
  );
  const [categoryFilter, setCategoryFilter] = useState<string>('');
  const [brandFilter, setBrandFilter] = useState<string>('');

  const [rows, setRows] = useState<ProductRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [meta, setMeta] = useState<ListMeta | null>(null);

  // Busca livre debounce 300ms
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  useEffect(() => {
    const t = setTimeout(() => setSearch(searchInput.trim()), 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  // Paginação + ordenação
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [sortKey, setSortKey] = useState<SortKey>(() => {
    if (typeof window === 'undefined') return 'name';
    try {
      const v = window.localStorage.getItem(SORT_KEY_STORAGE_KEY);
      if (
        v === 'name' ||
        v === 'sku' ||
        v === 'category' ||
        v === 'brand' ||
        v === 'current_stock' ||
        v === 'min_price'
      )
        return v;
    } catch (_) {
      /* */
    }
    return 'name';
  });
  const [sortDir, setSortDir] = useState<SortDir>(() => {
    if (typeof window === 'undefined') return 'asc';
    try {
      const v = window.localStorage.getItem(SORT_DIR_STORAGE_KEY);
      if (v === 'asc' || v === 'desc') return v;
    } catch (_) {
      /* */
    }
    return 'asc';
  });

  // Persist filters
  useEffect(() => {
    try {
      window.localStorage.setItem(STATUS_FILTER_STORAGE_KEY, statusFilter);
    } catch (_) {
      /* */
    }
  }, [statusFilter]);

  useEffect(() => {
    try {
      window.localStorage.setItem(TYPE_FILTER_STORAGE_KEY, typeFilter);
    } catch (_) {
      /* */
    }
  }, [typeFilter]);

  useEffect(() => {
    try {
      window.localStorage.setItem(SORT_KEY_STORAGE_KEY, sortKey);
      window.localStorage.setItem(SORT_DIR_STORAGE_KEY, sortDir);
    } catch (_) {
      /* */
    }
  }, [sortKey, sortDir]);

  // Reset página quando muda filtro
  useEffect(() => {
    setPage(1);
  }, [statusFilter, typeFilter, categoryFilter, brandFilter, search, sortKey, sortDir, perPage]);

  // Atalho `/` foca busca, `Esc` limpa
  useEffect(() => {
    function handleKey(e: KeyboardEvent) {
      if (e.key === '/' && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA') {
        e.preventDefault();
        document.querySelector<HTMLInputElement>('input[type=search]')?.focus();
      }
    }
    window.addEventListener('keydown', handleKey);
    return () => window.removeEventListener('keydown', handleKey);
  }, []);

  // Fetch
  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    const params = new URLSearchParams();
    if (search) params.set('q', search);
    if (typeFilter) params.set('type', typeFilter);
    if (categoryFilter) params.set('category_id', categoryFilter);
    if (brandFilter) params.set('brand_id', brandFilter);
    // Map statusFilter pra params backend
    if (statusFilter === 'with_stock') params.set('enable_stock', 'yes');
    if (statusFilter === 'in_alert') {
      params.set('enable_stock', 'yes');
      params.set('in_alert', '1');
    }
    if (statusFilter === 'inactive') params.set('status', 'inactive');
    params.set('per_page', String(perPage));
    params.set('page', String(page));
    params.set('sort', sortKey);
    params.set('dir', sortDir);
    fetch(`/products/list-json?${params.toString()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((json) => {
        if (cancelled) return;
        setRows(Array.isArray(json.data) ? json.data : []);
        setMeta(json.meta ?? null);
      })
      .catch(() => {
        if (cancelled) return;
        setRows([]);
        setMeta(null);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [
    search,
    typeFilter,
    categoryFilter,
    brandFilter,
    statusFilter,
    page,
    perPage,
    sortKey,
    sortDir,
  ]);

  const refetch = useCallback(() => {
    setPage((p) => p); // trigger re-fetch via effect dependency
    const ev = new Event('refetch-products');
    window.dispatchEvent(ev);
  }, []);

  const handleSort = useCallback(
    (key: SortKey) => {
      setSortKey((prevKey) => {
        if (key === prevKey) return prevKey;
        return key;
      });
      setSortDir((prevDir) => {
        if (key === sortKey) {
          return prevDir === 'asc' ? 'desc' : 'asc';
        }
        return key === 'current_stock' || key === 'min_price' ? 'desc' : 'asc';
      });
    },
    [sortKey],
  );

  const pills: Array<{ key: StatusFilter; label: string; icon: typeof Layers; count: number; tone?: 'rose' | 'amber' | 'emerald' }> = [
    { key: '', label: 'Todos', icon: Layers, count: kpis.total },
    { key: 'with_stock', label: 'Com estoque', icon: CheckCircle2, count: kpis.with_stock, tone: 'emerald' },
    { key: 'in_alert', label: 'Em alerta', icon: AlertTriangle, count: kpis.in_alert, tone: 'amber' },
    { key: 'inactive', label: 'Inativos', icon: Power, count: kpis.inactive },
  ];

  const categoryEntries = Object.entries(filterOptions.categories ?? {});
  const brandEntries = Object.entries(filterOptions.brands ?? {});

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      {/* Header Cockpit canon */}
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-7xl">
          <div className="flex items-start gap-4">
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">Produtos</h1>
              <p className="text-sm text-muted-foreground mt-1 leading-relaxed">
                Catálogo de produtos — busque rápido por nome, SKU ou código legacy.
              </p>
            </div>
            <div className="flex-shrink-0 flex items-center gap-3">
              {permissions.create && (
                <Button asChild>
                  <a href="/products/create">
                    <Plus className="mr-1.5 h-4 w-4" />
                    Novo produto
                  </a>
                </Button>
              )}
            </div>
          </div>

          {/* 4 KPIs */}
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6">
            <KpiCard label="Total" value={kpis.total} icon={Package} />
            <KpiCard label="Com estoque" value={kpis.with_stock} icon={CheckCircle2} tone="emerald" />
            <KpiCard label="Em alerta" value={kpis.in_alert} icon={AlertTriangle} tone="amber" />
            <KpiCard label="Inativos" value={kpis.inactive} icon={Power} muted />
          </div>

          {/* Filter pills */}
          <nav className="flex items-center gap-2 mt-6 flex-wrap" aria-label="Filtro de status do produto">
            {pills.map((pill) => {
              const isActive = statusFilter === pill.key;
              const Icon = pill.icon;
              const toneClass = isActive
                ? pill.tone === 'amber'
                  ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'
                  : pill.tone === 'emerald'
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                    : 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground';
              return (
                <button
                  key={pill.key || 'all'}
                  type="button"
                  onClick={() => setStatusFilter(pill.key)}
                  className={
                    'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors ' +
                    toneClass
                  }
                  aria-current={isActive ? 'true' : undefined}
                >
                  <Icon size={13} />
                  {pill.label}
                  {pill.count > 0 && (
                    <span
                      className={
                        'ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] tabular-nums ' +
                        (isActive ? 'bg-white/60 dark:bg-black/30' : 'bg-background')
                      }
                    >
                      {pill.count}
                    </span>
                  )}
                </button>
              );
            })}
          </nav>
        </div>
      </div>

      <div className="container mx-auto px-8 py-6 max-w-7xl">
        {/* Busca + filtros dropdown */}
        <div className="mb-4 flex flex-wrap items-center gap-2">
          <div className="relative flex-1 min-w-[260px] max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
            <Input
              type="search"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Buscar nome, SKU ou código…"
              className="pl-9 pr-9 h-9"
              aria-label="Buscar produto"
              autoFocus
            />
            {searchInput && (
              <button
                type="button"
                onClick={() => setSearchInput('')}
                aria-label="Limpar busca"
                className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground hover:text-foreground"
              >
                <X className="h-4 w-4" />
              </button>
            )}
          </div>

          <SelectFilter
            value={typeFilter}
            onChange={setTypeFilter}
            placeholder="Tipo"
            options={[
              ['single', 'Simples'],
              ['variable', 'Variável'],
              ['combo', 'Combo'],
            ]}
          />
          <SelectFilter
            value={categoryFilter}
            onChange={setCategoryFilter}
            placeholder="Categoria"
            options={categoryEntries as Array<[string, string]>}
          />
          <SelectFilter
            value={brandFilter}
            onChange={setBrandFilter}
            placeholder="Marca"
            options={brandEntries as Array<[string, string]>}
          />

          {loading && search && (
            <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
          )}
        </div>

        {/* Tabela */}
        <div className="rounded-lg border border-border bg-background overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-muted/50">
                <tr className="border-b border-border">
                  <SortableTh sortKey="name" current={sortKey} dir={sortDir} onSort={handleSort}>
                    Produto
                  </SortableTh>
                  <SortableTh
                    sortKey="category"
                    current={sortKey}
                    dir={sortDir}
                    onSort={handleSort}
                    className="w-44"
                  >
                    Categoria · Marca
                  </SortableTh>
                  <Th className="w-20">Un.</Th>
                  <SortableTh
                    sortKey="min_price"
                    current={sortKey}
                    dir={sortDir}
                    onSort={handleSort}
                    className="w-32 text-right"
                  >
                    Preço
                  </SortableTh>
                  <SortableTh
                    sortKey="current_stock"
                    current={sortKey}
                    dir={sortDir}
                    onSort={handleSort}
                    className="w-28 text-right"
                  >
                    Estoque
                  </SortableTh>
                  <Th className="w-12 text-right pr-4">&nbsp;</Th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={6} className="text-center py-12 text-muted-foreground text-xs">
                      Carregando…
                    </td>
                  </tr>
                ) : rows.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="text-center py-12 text-muted-foreground text-xs">
                      {search ? `Nenhum produto encontrado pra "${search}".` : 'Nenhum produto cadastrado ainda.'}
                    </td>
                  </tr>
                ) : (
                  rows.map((row) => {
                    const isInactive = row.is_inactive === 1;
                    const stock = row.current_stock !== null ? parseFloat(String(row.current_stock)) : 0;
                    const alert = row.alert_quantity !== null ? parseFloat(String(row.alert_quantity)) : null;
                    const isInAlert = row.enable_stock === 1 && alert !== null && stock < alert;
                    const minP = row.min_price !== null ? parseFloat(String(row.min_price)) : null;
                    const maxP = row.max_price !== null ? parseFloat(String(row.max_price)) : null;
                    const showRange = row.type === 'variable' && minP !== null && maxP !== null && minP !== maxP;
                    return (
                      <tr
                        key={row.id}
                        className={
                          'border-b border-border cursor-pointer transition-colors hover:bg-muted/40 ' +
                          (isInactive ? 'opacity-60' : '')
                        }
                        onClick={() => {
                          window.location.href = `/products/${row.id}`;
                        }}
                      >
                        <td className="px-4 py-3">
                          <div className="text-foreground font-medium leading-tight flex items-center gap-1.5">
                            {isInAlert && <AlertTriangle size={13} className="text-amber-600 flex-shrink-0" />}
                            {row.name}
                            {row.not_for_selling === 1 && (
                              <span className="ml-1 text-[10px] uppercase tracking-wide text-muted-foreground border border-border rounded px-1">
                                Não vende
                              </span>
                            )}
                          </div>
                          <div className="text-[11px] text-muted-foreground leading-tight mt-0.5 flex items-center gap-2">
                            {row.sku && <span className="tabular-nums">SKU {row.sku}</span>}
                            {row.product_custom_field1 && (
                              <span className="tabular-nums">#{row.product_custom_field1}</span>
                            )}
                          </div>
                        </td>
                        <td className="px-4 py-3 text-xs">
                          <div className="text-foreground">{row.category ?? '—'}</div>
                          {row.brand && <div className="text-muted-foreground mt-0.5">{row.brand}</div>}
                        </td>
                        <td className="px-4 py-3 text-xs text-muted-foreground">{row.unit ?? '—'}</td>
                        <td className="px-4 py-3 text-right tabular-nums text-foreground">
                          {showRange
                            ? `${formatCurrency(minP)} – ${formatCurrency(maxP)}`
                            : formatCurrency(minP)}
                        </td>
                        <td className="px-4 py-3 text-right tabular-nums">
                          {row.enable_stock === 1 ? (
                            <span className={isInAlert ? 'text-amber-700 font-medium' : 'text-foreground'}>
                              {formatNumber(row.current_stock)} {row.unit ? <span className="text-muted-foreground text-[11px]">{row.unit}</span> : null}
                            </span>
                          ) : (
                            <span className="text-muted-foreground/60 text-xs">—</span>
                          )}
                        </td>
                        <td className="px-2 py-3 text-right pr-4" onClick={(e) => e.stopPropagation()}>
                          <ActionsMenu row={row} permissions={permissions} onChange={refetch} />
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>

        {meta && meta.total > 0 && (
          <Pagination meta={meta} perPage={perPage} onPageChange={setPage} onPerPageChange={setPerPage} />
        )}
      </div>
    </div>
  );
}

ProductsIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

// ─── Subcomponents ───────────────────────────────────────────────────────────

function KpiCard({
  label,
  value,
  icon: Icon,
  muted,
  tone,
}: {
  label: string;
  value: number;
  icon: typeof Package;
  muted?: boolean;
  tone?: 'rose' | 'amber' | 'emerald';
}) {
  const valueClass = muted
    ? 'text-muted-foreground'
    : tone === 'amber'
      ? 'text-amber-700 dark:text-amber-300'
      : tone === 'emerald'
        ? 'text-emerald-700 dark:text-emerald-300'
        : 'text-foreground';
  return (
    <div className="rounded-xl border border-border bg-background p-5 shadow-sm">
      <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">{label}</div>
      <div className="flex items-end justify-between mt-3">
        <div className={'text-4xl font-semibold tabular-nums ' + valueClass}>
          {value.toLocaleString('pt-BR')}
        </div>
        <Icon size={28} className={muted ? 'text-muted-foreground/40' : 'text-muted-foreground/60'} strokeWidth={1.5} />
      </div>
    </div>
  );
}

function SelectFilter({
  value,
  onChange,
  placeholder,
  options,
}: {
  value: string;
  onChange: (v: string) => void;
  placeholder: string;
  options: Array<[string, string]>;
}) {
  if (options.length === 0) return null;
  return (
    <select
      value={value}
      onChange={(e) => onChange(e.target.value)}
      className="h-9 rounded border border-border bg-background px-3 text-xs text-foreground min-w-[120px]"
      aria-label={placeholder}
    >
      <option value="">{placeholder}</option>
      {options.map(([id, label]) => (
        <option key={id} value={id}>
          {label}
        </option>
      ))}
    </select>
  );
}

function Th({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <th
      className={
        'text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' + className
      }
    >
      {children}
    </th>
  );
}

function SortableTh({
  children,
  sortKey,
  current,
  dir,
  onSort,
  className = '',
}: {
  children: ReactNode;
  sortKey: SortKey;
  current: SortKey;
  dir: SortDir;
  onSort: (k: SortKey) => void;
  className?: string;
}) {
  const active = current === sortKey;
  const Icon = !active ? ArrowUpDown : dir === 'asc' ? ArrowUp : ArrowDown;
  return (
    <th
      className={
        'text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' + className
      }
    >
      <button
        type="button"
        onClick={() => onSort(sortKey)}
        className={
          'inline-flex items-center gap-1 transition-colors hover:text-foreground ' + (active ? 'text-foreground' : '')
        }
        aria-sort={active ? (dir === 'asc' ? 'ascending' : 'descending') : 'none'}
      >
        {children}
        <Icon size={12} className={active ? '' : 'opacity-40'} />
      </button>
    </th>
  );
}

function ActionsMenu({
  row,
  permissions,
  onChange,
}: {
  row: ProductRow;
  permissions: ProductsIndexPageProps['permissions'];
  onChange: () => void;
}) {
  const handleDelete = async () => {
    if (!confirm(`Excluir o produto "${row.name}"? Essa ação não pode ser desfeita.`)) return;
    try {
      const meta = document.querySelector('meta[name="csrf-token"]');
      const csrf = meta?.getAttribute('content') ?? '';
      const res = await fetch(`/products/${row.id}`, {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
      });
      if (!res.ok) {
        const json = await res.json().catch(() => null);
        alert(json?.msg ?? 'Falha ao excluir produto.');
        return;
      }
      const json = await res.json().catch(() => null);
      if (json && json.success === 0) {
        alert(json.msg ?? 'Falha ao excluir produto.');
        return;
      }
      onChange();
      window.location.reload();
    } catch (e) {
      alert('Erro ao excluir: ' + String((e as Error)?.message || e));
    }
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          className="inline-flex h-8 w-8 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
          aria-label="Ações do produto"
          onClick={(e) => e.stopPropagation()}
        >
          <MoreVertical size={16} />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-52">
        {permissions.view && (
          <DropdownMenuItem asChild>
            <a href={`/products/${row.id}`}>
              <Eye size={14} className="mr-2" />
              Ver detalhes
            </a>
          </DropdownMenuItem>
        )}
        {permissions.update && (
          <DropdownMenuItem asChild>
            <a href={`/products/${row.id}/edit`}>
              <Edit size={14} className="mr-2" />
              Editar
            </a>
          </DropdownMenuItem>
        )}
        {permissions.view && row.enable_stock === 1 && (
          <DropdownMenuItem asChild>
            <a href={`/products/stock-history/${row.id}`}>
              <History size={14} className="mr-2" />
              Histórico de estoque
            </a>
          </DropdownMenuItem>
        )}
        {permissions.create && (
          <DropdownMenuItem asChild>
            <a href={`/products/create?d=${row.id}`}>
              <Copy size={14} className="mr-2" />
              Duplicar
            </a>
          </DropdownMenuItem>
        )}
        {permissions.update && row.is_inactive === 1 && (
          <DropdownMenuItem asChild>
            <a href={`/products/activate/${row.id}`}>
              <Power size={14} className="mr-2" />
              Reativar
            </a>
          </DropdownMenuItem>
        )}
        {permissions.delete && (
          <DropdownMenuItem
            onClick={handleDelete}
            className="text-rose-600 focus:bg-rose-50 focus:text-rose-700 dark:text-rose-400 dark:focus:bg-rose-950/40"
          >
            <Trash2 size={14} className="mr-2" />
            Excluir
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

function Pagination({
  meta,
  perPage,
  onPageChange,
  onPerPageChange,
}: {
  meta: ListMeta;
  perPage: number;
  onPageChange: (p: number) => void;
  onPerPageChange: (n: number) => void;
}) {
  const { current_page, last_page, total, from, to } = meta;
  const canPrev = current_page > 1;
  const canNext = current_page < last_page;
  return (
    <div className="flex flex-wrap items-center justify-between gap-3 mt-3 px-1">
      <div className="text-xs text-muted-foreground">
        {total === 0
          ? 'Nenhum produto'
          : `Mostrando ${from ?? 0}–${to ?? 0} de ${total.toLocaleString('pt-BR')}`}
      </div>
      <div className="flex items-center gap-3">
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          <span>Por página</span>
          <select
            value={perPage}
            onChange={(e) => onPerPageChange(Number(e.target.value))}
            className="h-7 rounded border border-border bg-background px-2 text-xs text-foreground"
            aria-label="Itens por página"
          >
            {PER_PAGE_OPTIONS.map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </select>
        </div>
        <div className="flex items-center gap-1">
          <PageBtn onClick={() => onPageChange(1)} disabled={!canPrev} aria-label="Primeira página">
            <ChevronsLeft size={14} />
          </PageBtn>
          <PageBtn onClick={() => onPageChange(current_page - 1)} disabled={!canPrev} aria-label="Página anterior">
            <ChevronLeft size={14} />
          </PageBtn>
          <span className="px-2 text-xs tabular-nums text-foreground">
            {current_page} <span className="text-muted-foreground">/ {last_page}</span>
          </span>
          <PageBtn onClick={() => onPageChange(current_page + 1)} disabled={!canNext} aria-label="Próxima página">
            <ChevronRight size={14} />
          </PageBtn>
          <PageBtn onClick={() => onPageChange(last_page)} disabled={!canNext} aria-label="Última página">
            <ChevronsRight size={14} />
          </PageBtn>
        </div>
      </div>
    </div>
  );
}

function PageBtn({
  children,
  onClick,
  disabled,
  ...rest
}: React.ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      {...rest}
      className="inline-flex h-7 w-7 items-center justify-center rounded border border-border bg-background text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-background"
    >
      {children}
    </button>
  );
}
