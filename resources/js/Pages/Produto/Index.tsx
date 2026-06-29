// Wave 2 B4 Produto · F3 FRONTEND — Pages/Produto/Index.tsx
// Migração MWART (ADR 0104) — Blade product.index → Inertia/React Cowork blueprint
// Pattern reuse blueprint: prototipo-ui/prototipos/produto-cockpit/ (ADR 0149)
// Refs: RUNBOOK-produto-index.md · Index.charter.md · produto-index-visual-comparison.md
// Agent W2-C paralelo · 2026-05-15
//
// Tier 0 IRREVOGÁVEL:
//   - business_id global scope no controller (não no client)
//   - PT-BR em texto UI
//   - Inertia::defer() em props caras (KPIs, rows, categorias)
//   - sem any TypeScript
//   - sem session-Storage (usar localStorage com prefixo oimpresso.produto.)

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { Plus, Upload, Search, Package } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';

const STORAGE_KEY_INACTIVE = 'oimpresso.produto.index.showInactive';
const STORAGE_KEY_ACTIVE_TAB = 'oimpresso.produto.index.activeTab';

interface ProdutoIndexPermissions {
  create: boolean;
  update: boolean;
  delete: boolean;
  opening_stock: boolean;
}

interface ProdutoIndexKpis {
  total: number;
  ativos: number;
  categorias: number;
  populares: number;
}

interface ProdutoCategoria {
  id: number;
  slug: string;
  label: string;
  count: number;
}

interface ProdutoRow {
  id: number;
  sku: string;
  name: string;
  categoryId: number | null;
  categoryLabel: string | null;
  unit: string | null;
  price: number;
  cost: number | null;
  margin: number | null;
  stockQty: number | null;
  stockKind: 'estoque' | 'sob_demanda' | 'servico';
  popularity: number;
  active: boolean;
  updatedAt: string | null;
}

export interface ProdutoIndexPageProps {
  filters: {
    busca: string;
    categoria: string;
    mostrarInativos: boolean;
  };
  kpis?: ProdutoIndexKpis;
  rows?: ProdutoRow[];
  categorias?: ProdutoCategoria[];
  permissions: ProdutoIndexPermissions;
}

const fmtBRL = (n: number): string =>
  n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function ProdutoIndex(props: ProdutoIndexPageProps) {
  const { filters, permissions } = props;

  const [busca, setBusca] = useState(filters.busca ?? '');
  const [activeTab, setActiveTab] = useState<string>(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY_ACTIVE_TAB);
      return raw ?? filters.categoria ?? 'todos';
    } catch {
      return filters.categoria ?? 'todos';
    }
  });
  const [mostrarInativos, setMostrarInativos] = useState<boolean>(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY_INACTIVE);
      return raw === '1';
    } catch {
      return filters.mostrarInativos ?? false;
    }
  });

  const handleToggleInactive = (next: boolean) => {
    setMostrarInativos(next);
    try {
      localStorage.setItem(STORAGE_KEY_INACTIVE, next ? '1' : '0');
    } catch {
      // localStorage indisponível — ignora silenciosamente
    }
  };

  const handleSelectTab = (tab: string) => {
    setActiveTab(tab);
    try {
      localStorage.setItem(STORAGE_KEY_ACTIVE_TAB, tab);
    } catch {
      // ignora
    }
  };

  const filterRows = (rows: ProdutoRow[] | undefined): ProdutoRow[] => {
    if (!rows) return [];
    let out = rows;
    if (!mostrarInativos) {
      out = out.filter((r) => r.active);
    }
    if (activeTab !== 'todos') {
      out = out.filter((r) => String(r.categoryId ?? '') === activeTab);
    }
    if (busca.trim()) {
      const q = busca.trim().toLowerCase();
      out = out.filter(
        (r) => r.name.toLowerCase().includes(q) || r.sku.toLowerCase().includes(q),
      );
    }
    return out;
  };

  return (
    <>
      <Head title="Produtos · Catálogo" />

      <div className="min-h-screen bg-stone-50 text-stone-900">
        {/* Header sticky */}
        <header className="sticky top-0 z-30 bg-white/85 backdrop-blur border-b border-stone-200">
          <div className="px-6 pt-4 pb-3 flex items-center justify-between">
            <div>
              <div className="flex items-center gap-1.5 text-[12px] text-stone-500">
                <span>Inventário</span>
                <span className="text-stone-400">›</span>
                <span className="text-stone-900 font-medium">Produtos</span>
              </div>
              <h1 className="mt-1 text-[24px] font-semibold tracking-tight">Produtos</h1>
            </div>
            <div className="flex items-center gap-2">
              <Button variant="outline" asChild>
                <a href="/import-products" rel="noopener noreferrer">
                  <Upload className="w-4 h-4 mr-1.5" />
                  Importar
                </a>
              </Button>
              {permissions.create && (
                <Button asChild>
                  <Link href="/products/create">
                    <Plus className="w-4 h-4 mr-1.5" />
                    Novo produto
                  </Link>
                </Button>
              )}
            </div>
          </div>
        </header>

        <div className="pb-16">
          {/* KPI strip */}
          <section className="mx-6 mt-4 rounded-md bg-white border border-stone-200 shadow-sm">
            <Deferred data="kpis" fallback={<KpisSkeleton />}>
              <KpisStrip kpis={props.kpis ?? { total: 0, ativos: 0, categorias: 0, populares: 0 }} />
            </Deferred>
          </section>

          {/* Filtros + tabs categoria */}
          <section className="mx-6 mt-4 flex items-center justify-between gap-3 flex-wrap">
            <div className="flex items-center gap-2">
              <div className="relative">
                <Search className="absolute left-2.5 top-2.5 w-4 h-4 text-stone-500" />
                <Input
                  type="search"
                  value={busca}
                  onChange={(e) => setBusca(e.target.value)}
                  placeholder="Buscar por nome ou SKU"
                  className="pl-8 w-72"
                  aria-label="Buscar produto"
                />
              </div>
              <label className="flex items-center gap-2 text-[12.5px] text-stone-700">
                <input
                  type="checkbox"
                  checked={mostrarInativos}
                  onChange={(e) => handleToggleInactive(e.target.checked)}
                  className="rounded border-stone-300"
                />
                Mostrar inativos
              </label>
            </div>

            <Deferred data="categorias" fallback={<TabsSkeleton />}>
              <CategoriaTabs
                categorias={props.categorias ?? []}
                activeTab={activeTab}
                onSelect={handleSelectTab}
              />
            </Deferred>
          </section>

          {/* Lista cards */}
          <section className="mx-6 mt-4">
            <Deferred data="rows" fallback={<RowsSkeleton />}>
              <ProdutoCards rows={filterRows(props.rows)} permissions={permissions} />
            </Deferred>
          </section>
        </div>
      </div>
    </>
  );
}

ProdutoIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Produtos — Catálogo"
    breadcrumbItems={[{ label: 'Inventário', href: '/products' }, { label: 'Produtos' }]}
  >
    {page}
  </AppShellV2>
);

export default ProdutoIndex;

/* ─── Subcomponentes ────────────────────────────────────────────────── */

function KpisStrip({ kpis }: { kpis: ProdutoIndexKpis }) {
  return (
    <div className="grid grid-cols-4 divide-x divide-stone-200">
      <Kpi label="Total de produtos" value={kpis.total} emphasize />
      <Kpi label="Ativos" value={kpis.ativos} tone="pos" />
      <Kpi label="Categorias" value={kpis.categorias} />
      <Kpi label="Populares · 30d" value={kpis.populares} sub="≥30 vendas/mês" />
    </div>
  );
}

function Kpi({
  label,
  value,
  sub,
  tone = 'default',
  emphasize = false,
}: {
  label: string;
  value: string | number;
  sub?: string;
  tone?: 'default' | 'pos' | 'neg';
  emphasize?: boolean;
}) {
  const toneClass =
    tone === 'pos' ? 'text-emerald-700' : tone === 'neg' ? 'text-rose-700' : 'text-stone-900';
  return (
    <div className={`px-5 py-4 ${emphasize ? 'bg-stone-900 text-stone-50' : 'bg-white'}`}>
      <div
        className={`text-[10px] uppercase tracking-widest font-medium ${
          emphasize ? 'text-stone-400' : 'text-stone-500'
        }`}
      >
        {label}
      </div>
      <div
        className={`mt-1 text-[28px] leading-none font-semibold tracking-tight tabular-nums ${
          emphasize ? 'text-stone-50' : toneClass
        }`}
      >
        {value}
      </div>
      {sub && (
        <div className={`mt-2 text-[11.5px] ${emphasize ? 'text-stone-400' : 'text-stone-500'}`}>
          {sub}
        </div>
      )}
    </div>
  );
}

function KpisSkeleton() {
  return (
    <div className="grid grid-cols-4 divide-x divide-stone-200">
      {[0, 1, 2, 3].map((i) => (
        <div key={i} className="px-5 py-4">
          <div className="h-3 w-24 bg-stone-200 rounded animate-pulse" />
          <div className="mt-2 h-7 w-16 bg-stone-200 rounded animate-pulse" />
        </div>
      ))}
    </div>
  );
}

function CategoriaTabs({
  categorias,
  activeTab,
  onSelect,
}: {
  categorias: ProdutoCategoria[];
  activeTab: string;
  onSelect: (tab: string) => void;
}) {
  const total = categorias.reduce((acc, c) => acc + c.count, 0);
  return (
    <nav className="flex items-center gap-1 text-[12.5px]" aria-label="Filtrar por categoria">
      <button
        type="button"
        onClick={() => onSelect('todos')}
        className={`h-7 px-3 rounded-md transition-colors duration-150 ${
          activeTab === 'todos'
            ? 'bg-stone-900 text-stone-50 font-medium'
            : 'text-stone-600 hover:bg-stone-100'
        }`}
      >
        Todos <span className="text-[11px] opacity-70">· {total}</span>
      </button>
      {categorias.map((c) => (
        <button
          key={c.id}
          type="button"
          onClick={() => onSelect(String(c.id))}
          className={`h-7 px-3 rounded-md transition-colors duration-150 ${
            activeTab === String(c.id)
              ? 'bg-stone-900 text-stone-50 font-medium'
              : 'text-stone-600 hover:bg-stone-100'
          }`}
        >
          {c.label} <span className="text-[11px] opacity-70">· {c.count}</span>
        </button>
      ))}
    </nav>
  );
}

function TabsSkeleton() {
  return (
    <div className="flex items-center gap-1">
      {[0, 1, 2, 3].map((i) => (
        <div key={i} className="h-7 w-24 bg-stone-200 rounded-md animate-pulse" />
      ))}
    </div>
  );
}

function ProdutoCards({
  rows,
  permissions,
}: {
  rows: ProdutoRow[];
  permissions: ProdutoIndexPermissions;
}) {
  if (rows.length === 0) {
    return <EmptyState />;
  }
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
      {rows.map((r) => (
        <ProdutoCard key={r.id} row={r} canUpdate={permissions.update} />
      ))}
    </div>
  );
}

function ProdutoCard({ row, canUpdate }: { row: ProdutoRow; canUpdate: boolean }) {
  const baseClass = row.active
    ? 'bg-white border-stone-200 hover:border-stone-400'
    : 'bg-stone-100 border-stone-200 opacity-70';

  const content = (
    <article
      className={`p-4 rounded-md border ${baseClass} transition-colors duration-150 cursor-pointer`}
    >
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2 mb-1">
            {row.categoryLabel && (
              <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] uppercase tracking-wider bg-stone-200 text-stone-700">
                {row.categoryLabel}
              </span>
            )}
            {!row.active && (
              <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] uppercase tracking-wider bg-destructive-soft text-destructive-fg">
                Inativo
              </span>
            )}
          </div>
          <h3 className="text-[14px] font-medium text-stone-900 truncate">{row.name}</h3>
          <div className="mt-1 font-mono text-[11.5px] text-stone-500">{row.sku}</div>
        </div>
        <Package className="w-4 h-4 text-stone-400 shrink-0" />
      </div>

      <div className="mt-3 flex items-end justify-between">
        <div>
          <div className="text-[10.5px] uppercase tracking-widest text-stone-500">Preço</div>
          <div className="text-[16px] font-semibold tabular-nums">{fmtBRL(row.price)}</div>
          {row.unit && <div className="text-[11px] text-stone-500">/ {row.unit}</div>}
        </div>
        <div className="text-right">
          <div className="text-[10.5px] uppercase tracking-widest text-stone-500">Popularidade</div>
          <div className="mt-1 h-1.5 w-24 bg-stone-200 rounded-full overflow-hidden">
            <div
              className={`h-full rounded-full ${
                row.popularity >= 70 ? 'bg-emerald-500' : 'bg-stone-400'
              }`}
              style={{ width: `${Math.min(100, row.popularity)}%` }}
            />
          </div>
          <div className="text-[10.5px] text-stone-500 mt-0.5">{row.popularity}%</div>
        </div>
      </div>
    </article>
  );

  if (canUpdate) {
    return <Link href={`/products/${row.id}`}>{content}</Link>;
  }
  return content;
}

function RowsSkeleton() {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
      {[0, 1, 2, 3, 4, 5, 6, 7].map((i) => (
        <div key={i} className="p-4 rounded-md border border-stone-200 bg-white">
          <div className="h-3 w-16 bg-stone-200 rounded animate-pulse mb-2" />
          <div className="h-4 w-3/4 bg-stone-200 rounded animate-pulse" />
          <div className="mt-2 h-3 w-1/2 bg-stone-200 rounded animate-pulse" />
          <div className="mt-4 flex justify-between">
            <div className="h-6 w-20 bg-stone-200 rounded animate-pulse" />
            <div className="h-6 w-24 bg-stone-200 rounded animate-pulse" />
          </div>
        </div>
      ))}
    </div>
  );
}

function EmptyState() {
  return (
    <div className="py-16 text-center">
      <Package className="w-10 h-10 mx-auto text-stone-400" />
      <h3 className="mt-3 text-[16px] font-medium text-stone-700">Nenhum produto encontrado</h3>
      <p className="mt-1 text-[13px] text-stone-500">
        Ajuste filtros ou cadastre o primeiro produto.
      </p>
    </div>
  );
}
