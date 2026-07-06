import { Head, router } from '@inertiajs/react';
import { useState, useCallback } from 'react';
import type { ReactNode, KeyboardEvent } from 'react';
import { SlidersHorizontal } from 'lucide-react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Badge } from '@/Components/ui/badge';
import { Switch } from '@/Components/ui/switch';
import { Label } from '@/Components/ui/label';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/Components/ui/popover';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

/**
 * Catálogo Unificado · módulo Produto · Cockpit V2.
 * 5 sub-telas em uma rota: produtos | categorias | insumos | tabelas | historico.
 * Persona-foco: Larissa · 1280×1024 · density-first.
 *
 * Camadas (ADR UI-0013): Fundações (tokens) → Shell (AppShellV2) → PT-01 Lista densa.
 * Controles via Design System (@/Components/ui/*); cores só via tokens.
 */

type Props = {
  tela: 'produtos' | 'categorias' | 'insumos' | 'tabelas' | 'historico';
  filters: {
    tela: string;
    tab: string;
    busca: string;
    categoria: string;
    view: 'table' | 'grid';
    densidade: 'compact' | 'comfortable' | 'cozy';
  };
  kpis: {
    catalogo_ativo: number;
    populares: number;
    saidas_30d: number;
    margem_media: number;
    sem_giro: number;
  };
  produtos: ProdutoRow[];
  categorias: CategoriaRow[];
  insumos: InsumoRow[];
  tabelas: TabelaRow[];
  historico: HistoricoRow[];
};

type ProdutoRow = {
  id: number;
  sku: string;
  name: string;
  cat: string | null;
  cat_label: string | null;
  unit: string;
  price: number;
  cost: number;
  margin: number;
  stockKind: 'estoque' | 'sob_demanda';
  stockQty: number | null;
  uses30: number;
  active: boolean;
  updated: string | null;
  bomCount: number;
};
type CategoriaRow  = { id: number; slug: string; label: string; count: number };
type InsumoRow     = { id: number; name: string; unit: string; cost: number; stock: number; fornecedor: string | null };
type TabelaRow     = { id: string; label: string; desc: string; mult: number };
type HistoricoRow  = { os: string; date: string; prodId: string; prodName: string; cat: string | null; unit: string; client: string | null; qty: number; value: number };

type Tweaks = { density: 'compact' | 'comfortable' | 'cozy'; view: 'table' | 'grid'; showCost: boolean };

const STORAGE_KEY = 'oimpresso.produto.tweaks';

const fmtBRL = (n: number) =>
  n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const fmtPct = (n: number) => Math.round(n * 100) + '%';

function ProdutoUnificadoIndex({ tela, filters, kpis, produtos, categorias, insumos, tabelas, historico }: Props) {
  // Tweaks persistidos (densidade, view, mostrar custo).
  const [tweaks, setTweaksState] = useState<Tweaks>(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) return { density: 'comfortable', view: 'table', showCost: true, ...JSON.parse(raw) };
    } catch {}
    return { density: filters.densidade, view: filters.view, showCost: true };
  });
  const setTweak = useCallback((edits: Partial<Tweaks>) => {
    setTweaksState((prev) => {
      const next = { ...prev, ...edits };
      try { localStorage.setItem(STORAGE_KEY, JSON.stringify(next)); } catch {}
      return next;
    });
  }, []);

  const setSubTela = (t: Props['tela']) =>
    // D-14: partial reload — só re-busca o que muda com a sub-tela.
    // kpis/produtos/categorias são closures no controller (não mudam com `tela`) — pulam.
    router.get(route('products.unificado.index'), { ...filters, tela: t }, {
      preserveState: true, preserveScroll: true, replace: true,
      only: ['tela', 'filters', 'insumos', 'tabelas', 'historico'],
    });

  return (
    <>
      <Head title="Catálogo · Produto" />
      <div className="min-h-screen bg-background text-foreground">
        <header className="sticky top-0 z-30 bg-card/85 backdrop-blur border-b border-border">
          <div className="px-6 h-14 flex items-center gap-4">
            <div className="flex items-center gap-1.5 text-[12px] text-muted-foreground">
              <span>Produto</span>
              <span className="text-muted-foreground/60">›</span>
              <span className="text-foreground font-medium">Catálogo</span>
            </div>
          </div>
          <div className="px-6 pt-4 pb-3 flex items-baseline gap-3">
            <h1 className="text-[24px] font-semibold tracking-tight">Catálogo</h1>
            <span className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              {kpis.catalogo_ativo} produtos · ROTA LIVRE
            </span>
          </div>

          <nav className="px-6 pb-2 flex items-center gap-1 text-[12.5px]" aria-label="Sub-telas do catálogo">
            {([
              ['produtos',   'Produtos'],
              ['categorias', 'Categorias'],
              ['insumos',    'Insumos · BOM'],
              ['tabelas',    'Tabelas de preço'],
              ['historico',  'Histórico de uso'],
            ] as const).map(([id, label]) => (
              <button
                key={id}
                type="button"
                aria-current={tela === id ? 'page' : undefined}
                onClick={() => setSubTela(id)}
                className={`h-7 px-3 rounded-md transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                  tela === id
                    ? 'bg-primary text-primary-foreground font-medium'
                    : 'text-muted-foreground hover:bg-muted'
                }`}
              >
                {label}
              </button>
            ))}
          </nav>
        </header>

        <div className="pb-16">
          {/* KPI strip */}
          <section className="mx-6 mt-4 rounded-md bg-card border border-border shadow-sm grid grid-cols-5 divide-x divide-border overflow-hidden">
            <Kpi label="Catálogo ativo" value={kpis.catalogo_ativo} emphasize />
            <Kpi label="Populares · 30d" value={kpis.populares} sub="≥30 vendas/mês" />
            <Kpi label="Saídas em 30 dias" value={kpis.saidas_30d.toLocaleString('pt-BR')} tone="pos" />
            <Kpi label="Margem média" value={fmtPct(kpis.margem_media || 0)} />
            <Kpi label="Sem giro" value={kpis.sem_giro} tone="neg" sub="0 saídas em 30d" />
          </section>

          {/* Conteúdo por sub-tela */}
          {tela === 'produtos'   && <TabelaProdutos rows={produtos} tweaks={tweaks} onOpen={(r) => router.visit(`/products/${r.id}`)} />}
          {tela === 'categorias' && <ListaCategorias rows={categorias} />}
          {tela === 'insumos'    && <ListaInsumos rows={insumos} />}
          {tela === 'tabelas'    && <ListaTabelas rows={tabelas} produtos={produtos} />}
          {tela === 'historico'  && <ListaHistorico rows={historico} />}
        </div>

        {/* Tweaks panel (canto inferior direito) */}
        <TweaksPanel tweaks={tweaks} setTweak={setTweak} />
      </div>
    </>
  );
}

ProdutoUnificadoIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Produto — Catálogo"
    breadcrumbItems={[{ label: 'Produto', href: '/products' }, { label: 'Catálogo' }]}
  >
    {page}
  </AppShellV2>
);

export default ProdutoUnificadoIndex;

/* ─── Subcomponentes ────────────────────────────────────────────────── */

function Kpi({ label, value, sub, tone = 'default', emphasize = false }: {
  label: string; value: string | number; sub?: string;
  tone?: 'default' | 'pos' | 'neg'; emphasize?: boolean;
}) {
  const toneClass = tone === 'pos' ? 'text-success-fg' : tone === 'neg' ? 'text-destructive-fg' : 'text-foreground';
  return (
    <div className={`px-5 py-4 ${emphasize ? 'bg-primary text-primary-foreground' : 'bg-card'}`}>
      <div className={`text-[10px] uppercase tracking-widest font-medium ${emphasize ? 'text-primary-foreground/70' : 'text-muted-foreground'}`}>{label}</div>
      <div className={`mt-1 text-[28px] leading-none font-semibold tracking-tight ${emphasize ? 'text-primary-foreground' : toneClass}`}>{value}</div>
      {sub && <div className={`mt-2 text-[11.5px] ${emphasize ? 'text-primary-foreground/70' : 'text-muted-foreground'}`}>{sub}</div>}
    </div>
  );
}

function TabelaProdutos({ rows, tweaks, onOpen }: { rows: ProdutoRow[]; tweaks: Tweaks; onOpen: (r: ProdutoRow) => void }) {
  const rowH = tweaks.density === 'compact' ? 36 : tweaks.density === 'cozy' ? 56 : 44;
  const onRowKey = (e: KeyboardEvent<HTMLTableRowElement>, r: ProdutoRow) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      onOpen(r);
    }
  };
  return (
    <div className="mx-6 mt-3 rounded-md bg-card border border-border shadow-sm overflow-hidden">
      <table className="w-full text-left">
        <thead className="text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium">
          <tr className="border-b border-border bg-muted/40">
            <th scope="col" className="pl-6 pr-3 py-2 w-20">SKU</th>
            <th scope="col" className="pr-3 py-2">Produto</th>
            <th scope="col" className="pr-3 py-2 w-32">Categoria</th>
            <th scope="col" className="pr-3 py-2 w-24 text-right">Preço</th>
            {tweaks.showCost && <th scope="col" className="pr-3 py-2 w-24 text-right">Custo · margem</th>}
            <th scope="col" className="pr-3 py-2 w-24 text-right">Estoque</th>
            <th scope="col" className="pr-6 py-2 w-20 text-right">30d</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr
              key={r.id}
              role="button"
              tabIndex={0}
              aria-label={`Abrir produto ${r.name}`}
              onClick={() => onOpen(r)}
              onKeyDown={(e) => onRowKey(e, r)}
              className="border-b border-border/60 hover:bg-muted/60 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
              style={{ height: rowH }}
            >
              <td className="pl-6 pr-3 font-mono text-[11.5px] text-muted-foreground">{r.sku}</td>
              <td className="pr-3 text-[13px] font-medium">{r.name}</td>
              <td className="pr-3 text-[12px] text-muted-foreground">{r.cat_label}</td>
              <td className="pr-3 text-[13px] text-right font-semibold tabular-nums">{fmtBRL(r.price)}</td>
              {tweaks.showCost && (
                <td className="pr-3 text-[12px] text-right tabular-nums">
                  <div className="text-foreground">{fmtBRL(r.cost)}</div>
                  <div className={r.margin >= 0.5 ? 'text-success-fg text-[11px]' : r.margin >= 0.3 ? 'text-muted-foreground text-[11px]' : 'text-destructive-fg text-[11px]'}>
                    {fmtPct(r.margin)}
                  </div>
                </td>
              )}
              <td className="pr-3 text-[12.5px] text-right text-foreground tabular-nums">
                {r.stockKind === 'estoque' ? `${r.stockQty} ${r.unit}` : 'sob demanda'}
              </td>
              <td className="pr-6 text-[12.5px] text-right tabular-nums">{r.uses30}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function ListaCategorias({ rows }: { rows: CategoriaRow[] }) {
  return (
    <div className="mx-6 mt-3 grid grid-cols-3 gap-3">
      {rows.map((c) => (
        <div key={c.id} className="p-4 rounded-md bg-card border border-border">
          <div className="text-[14px] font-semibold">{c.label}</div>
          <div className="mt-1 text-[12px] text-muted-foreground">{c.count} produtos</div>
        </div>
      ))}
    </div>
  );
}

function ListaInsumos({ rows }: { rows: InsumoRow[] }) {
  return (
    <div className="mx-6 mt-3 rounded-md bg-card border border-border shadow-sm overflow-hidden">
      <table className="w-full text-left">
        <thead className="text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium">
          <tr className="border-b border-border bg-muted/40">
            <th scope="col" className="pl-6 py-2">Insumo</th>
            <th scope="col" className="py-2 w-20">Unid.</th>
            <th scope="col" className="py-2 w-28 text-right">Custo</th>
            <th scope="col" className="py-2 w-24 text-right">Estoque</th>
            <th scope="col" className="pr-6 py-2 w-44">Fornecedor</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((i) => (
            <tr key={i.id} className="border-b border-border/60" style={{ height: 40 }}>
              <td className="pl-6 text-[13px] font-medium">{i.name}</td>
              <td className="text-[12px] text-muted-foreground">{i.unit}</td>
              <td className="text-[12.5px] text-right tabular-nums">{fmtBRL(i.cost)}</td>
              <td className="text-[12.5px] text-right tabular-nums">{i.stock}</td>
              <td className="pr-6 text-[12px] text-muted-foreground truncate">{i.fornecedor ?? '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function ListaTabelas({ rows, produtos }: { rows: TabelaRow[]; produtos: ProdutoRow[] }) {
  const [tableId, setTableId] = useState(rows[0]?.id ?? '');
  const cur = rows.find((t) => t.id === tableId);
  return (
    <div className="px-6 mt-3 space-y-4">
      <div className="grid grid-cols-4 gap-3">
        {rows.map((t) => {
          const active = t.id === tableId;
          return (
            <button
              key={t.id}
              type="button"
              aria-pressed={active}
              onClick={() => setTableId(t.id)}
              className={`text-left p-4 rounded-md border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${active ? 'bg-primary text-primary-foreground border-primary' : 'bg-card border-border hover:bg-muted/60'}`}
            >
              <div className={`text-[10px] uppercase tracking-widest ${active ? 'text-primary-foreground/70' : 'text-muted-foreground'}`}>Tabela</div>
              <div className="mt-1 text-[16px] font-semibold">{t.label}</div>
              <div className={`mt-1.5 text-[12px] ${active ? 'text-primary-foreground/80' : 'text-muted-foreground'}`}>{t.desc}</div>
              <div className="mt-3 text-[20px] font-semibold tabular-nums">{Math.round(t.mult * 100)}%</div>
            </button>
          );
        })}
      </div>
      {cur && (
        <div className="rounded-md bg-card border border-border overflow-hidden">
          <table className="w-full text-left">
            <thead className="text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium">
              <tr className="border-b border-border bg-muted/40">
                <th scope="col" className="pl-6 py-2 w-20">SKU</th>
                <th scope="col" className="py-2">Produto</th>
                <th scope="col" className="py-2 w-28 text-right">Balcão</th>
                <th scope="col" className="py-2 w-28 text-right">Esta tabela</th>
                <th scope="col" className="pr-6 py-2 w-24 text-right">Margem</th>
              </tr>
            </thead>
            <tbody>
              {produtos.filter((p) => p.active).map((p) => {
                const tab = p.price * cur.mult;
                const m = tab > 0 ? (tab - p.cost) / tab : 0;
                return (
                  <tr key={p.id} className="border-b border-border/60" style={{ height: 40 }}>
                    <td className="pl-6 font-mono text-[11.5px] text-muted-foreground">{p.sku}</td>
                    <td className="text-[13px] font-medium">{p.name}</td>
                    <td className="text-[12.5px] text-right text-muted-foreground tabular-nums">{fmtBRL(p.price)}</td>
                    <td className="text-[13px] text-right font-semibold tabular-nums">{fmtBRL(tab)}</td>
                    <td className={`pr-6 text-[12.5px] text-right tabular-nums ${m >= 0.4 ? 'text-success-fg' : m >= 0.15 ? 'text-foreground' : 'text-destructive-fg'}`}>{fmtPct(m)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function ListaHistorico({ rows }: { rows: HistoricoRow[] }) {
  return (
    <div className="mx-6 mt-3 rounded-md bg-card border border-border overflow-hidden">
      <table className="w-full text-left">
        <thead className="text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium">
          <tr className="border-b border-border bg-muted/40">
            <th scope="col" className="pl-6 py-2 w-24">Data</th>
            <th scope="col" className="py-2 w-24">OS</th>
            <th scope="col" className="py-2">Produto</th>
            <th scope="col" className="py-2 w-44">Cliente</th>
            <th scope="col" className="py-2 w-16 text-right">Qtd</th>
            <th scope="col" className="pr-6 py-2 w-28 text-right">Valor</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r, idx) => (
            <tr key={r.os + r.prodId + idx} className="border-b border-border/60" style={{ height: 36 }}>
              <td className="pl-6 text-[12px] tabular-nums">{r.date}</td>
              <td>
                <Badge variant="secondary" className="font-mono text-[11px] font-normal">{r.os}</Badge>
              </td>
              <td className="text-[12.5px] font-medium">{r.prodName}</td>
              <td className="text-[12px] text-muted-foreground">{r.client ?? '—'}</td>
              <td className="text-[12.5px] text-right tabular-nums">{r.qty}</td>
              <td className="pr-6 text-[12.5px] text-right font-medium tabular-nums">{fmtBRL(r.value)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function TweaksPanel({ tweaks, setTweak }: { tweaks: Tweaks; setTweak: (e: Partial<Tweaks>) => void }) {
  return (
    <div className="fixed bottom-4 right-4 z-40">
      <Popover>
        <PopoverTrigger asChild>
          <button
            type="button"
            aria-label="Ajustes de exibição"
            className="inline-flex items-center gap-2 h-9 px-3 rounded-md bg-card border border-border shadow-lg text-[12.5px] font-medium text-foreground hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          >
            <SlidersHorizontal className="w-4 h-4" aria-hidden="true" />
            Ajustes
          </button>
        </PopoverTrigger>
        <PopoverContent align="end" side="top" className="w-64">
          <div className="text-[10px] uppercase tracking-widest text-muted-foreground font-medium mb-3">Ajustes</div>

          <div className="flex items-center justify-between mb-3 gap-3">
            <Label htmlFor="tweak-density" className="font-normal">Densidade</Label>
            <Select value={tweaks.density} onValueChange={(v) => setTweak({ density: v as Tweaks['density'] })}>
              <SelectTrigger id="tweak-density" className="h-8 w-32">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="compact">Compacto</SelectItem>
                <SelectItem value="comfortable">Confortável</SelectItem>
                <SelectItem value="cozy">Espaçoso</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="flex items-center justify-between mb-3 gap-3">
            <Label htmlFor="tweak-view" className="font-normal">Visualização</Label>
            <Select value={tweaks.view} onValueChange={(v) => setTweak({ view: v as Tweaks['view'] })}>
              <SelectTrigger id="tweak-view" className="h-8 w-32">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="table">Tabela</SelectItem>
                <SelectItem value="grid">Grade</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="flex items-center justify-between gap-3">
            <Label htmlFor="tweak-cost" className="font-normal">Mostrar custo</Label>
            <Switch
              id="tweak-cost"
              checked={tweaks.showCost}
              onCheckedChange={(v) => setTweak({ showCost: v })}
            />
          </div>
        </PopoverContent>
      </Popover>
    </div>
  );
}
