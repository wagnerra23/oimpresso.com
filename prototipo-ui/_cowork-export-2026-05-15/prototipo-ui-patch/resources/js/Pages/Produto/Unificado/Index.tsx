import { Head, router, usePage } from '@inertiajs/react';
import { useState, useMemo, useCallback, useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';

/**
 * Catálogo Unificado · módulo Produto · Cockpit V2.
 * Origem: protótipo Cowork [CC] aprovado por [W] em 2026-05-09.
 * Tradução F3 [CL] — segue tokens em CLAUDE_DESIGN_BRIEFING §4.
 *
 * 5 sub-telas em uma rota: produtos | categorias | insumos | tabelas | historico.
 * Persona-foco: Larissa [L] · 1280×1024 · density-first.
 *
 * TODO [CL]:
 * - Importar componentes UI compartilhados (KPIStrip, FilterBar, DensityToggle)
 *   de `resources/js/Components/Cockpit/` quando existirem.
 * - Trocar inputs nativos pelos do design system (`<Input>`, `<Button>`, etc.).
 * - Adicionar tradução i18n (chaves PT-BR já estão inline).
 * - Plugar drawer real (BOM expandida) — copiar do protótipo Cowork em
 *   `prototipo-ui/prototipos/produto/produto-app.jsx`.
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

const STORAGE_KEY = 'oimpresso.produto.tweaks';

const fmtBRL = (n: number) =>
  n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const fmtPct = (n: number) => Math.round(n * 100) + '%';

export default function ProdutoUnificadoIndex({ tela, filters, kpis, produtos, categorias, insumos, tabelas, historico }: Props) {
  // Tweaks persistidos (densidade, view, mostrar custo) — espelha protótipo Cowork.
  const [tweaks, setTweaksState] = useState(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) return { density: 'comfortable', view: 'table', showCost: true, ...JSON.parse(raw) };
    } catch {}
    return { density: filters.densidade, view: filters.view, showCost: true };
  });
  const setTweak = useCallback((edits: Partial<typeof tweaks>) => {
    setTweaksState((prev) => {
      const next = { ...prev, ...edits };
      try { localStorage.setItem(STORAGE_KEY, JSON.stringify(next)); } catch {}
      return next;
    });
  }, []);

  const setSubTela = (t: Props['tela']) =>
    router.get(route('produto.unificado.index'), { ...filters, tela: t }, { preserveState: true, preserveScroll: true, replace: true });

  return (
    <AppLayout>
      <Head title="Catálogo · Produto" />
      <div className="min-h-screen bg-stone-50 text-stone-900">
        {/* Sidebar + Header → reutilizar layout Cockpit V2 do Financeiro/Unificado.
            TODO [CL]: extrair `<CockpitShell>` em `Components/Cockpit/Shell.tsx` quando o 2º módulo (Produto) for traduzido — agora é o momento. */}

        <header className="sticky top-0 z-30 bg-white/85 backdrop-blur border-b border-stone-200">
          <div className="px-6 h-14 flex items-center gap-4">
            <div className="flex items-center gap-1.5 text-[12px] text-stone-500">
              <span>Produto</span>
              <span className="text-stone-400">›</span>
              <span className="text-stone-900 font-medium">Catálogo</span>
            </div>
          </div>
          <div className="px-6 pt-4 pb-3 flex items-baseline gap-3">
            <h1 className="text-[24px] font-semibold tracking-tight">Catálogo</h1>
            <span className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">
              {kpis.catalogo_ativo} produtos · ROTA LIVRE
            </span>
          </div>

          <nav className="px-6 pb-2 flex items-center gap-1 text-[12.5px]">
            {([
              ['produtos',   'Produtos'],
              ['categorias', 'Categorias'],
              ['insumos',    'Insumos · BOM'],
              ['tabelas',    'Tabelas de preço'],
              ['historico',  'Histórico de uso'],
            ] as const).map(([id, label]) => (
              <button
                key={id}
                onClick={() => setSubTela(id)}
                className={`h-7 px-3 rounded-md transition-colors duration-150 ${
                  tela === id
                    ? 'bg-stone-900 text-stone-50 font-medium'
                    : 'text-stone-600 hover:bg-stone-100'
                }`}
              >
                {label}
              </button>
            ))}
          </nav>
        </header>

        <main className="pb-16">
          {/* KPI strip */}
          <section className="mx-6 mt-4 rounded-md bg-white border border-stone-200 shadow-sm grid grid-cols-5 divide-x divide-stone-200 overflow-hidden">
            <Kpi label="Catálogo ativo" value={kpis.catalogo_ativo} emphasize />
            <Kpi label="Populares · 30d" value={kpis.populares} sub="≥30 vendas/mês" />
            <Kpi label="Saídas em 30 dias" value={kpis.saidas_30d.toLocaleString('pt-BR')} tone="pos" />
            <Kpi label="Margem média" value={fmtPct(kpis.margem_media || 0)} />
            <Kpi label="Sem giro" value={kpis.sem_giro} tone="neg" sub="0 saídas em 30d" />
          </section>

          {/* Conteúdo por sub-tela */}
          {tela === 'produtos'   && <TabelaProdutos rows={produtos} tweaks={tweaks} />}
          {tela === 'categorias' && <ListaCategorias rows={categorias} />}
          {tela === 'insumos'    && <ListaInsumos rows={insumos} />}
          {tela === 'tabelas'    && <ListaTabelas rows={tabelas} produtos={produtos} />}
          {tela === 'historico'  && <ListaHistorico rows={historico} />}
        </main>

        {/* Tweaks panel (canto inferior direito) — reutilizar `<TweaksPanel>` do design system. */}
        <TweaksPanel tweaks={tweaks} setTweak={setTweak} />
      </div>
    </AppLayout>
  );
}

/* ─── Subcomponentes ────────────────────────────────────────────────── */

function Kpi({ label, value, sub, tone = 'default', emphasize = false }: {
  label: string; value: string | number; sub?: string;
  tone?: 'default' | 'pos' | 'neg'; emphasize?: boolean;
}) {
  const toneClass = tone === 'pos' ? 'text-emerald-700' : tone === 'neg' ? 'text-rose-700' : 'text-stone-900';
  return (
    <div className={`px-5 py-4 ${emphasize ? 'bg-stone-900 text-stone-50' : 'bg-white'}`}>
      <div className={`text-[10px] uppercase tracking-widest font-medium ${emphasize ? 'text-stone-400' : 'text-stone-500'}`}>{label}</div>
      <div className={`mt-1 text-[28px] leading-none font-semibold tracking-tight ${emphasize ? 'text-stone-50' : toneClass}`}>{value}</div>
      {sub && <div className={`mt-2 text-[11.5px] ${emphasize ? 'text-stone-400' : 'text-stone-500'}`}>{sub}</div>}
    </div>
  );
}

function TabelaProdutos({ rows, tweaks }: { rows: ProdutoRow[]; tweaks: { density: string; showCost: boolean } }) {
  const rowH = tweaks.density === 'compact' ? 36 : tweaks.density === 'cozy' ? 56 : 44;
  return (
    <div className="mx-6 mt-3 rounded-md bg-white border border-stone-200 shadow-sm overflow-hidden">
      <table className="w-full text-left">
        <thead className="text-[10.5px] uppercase tracking-widest text-stone-500 font-medium">
          <tr className="border-b border-stone-200 bg-stone-50/40">
            <th className="pl-6 pr-3 py-2 w-20">SKU</th>
            <th className="pr-3 py-2">Produto</th>
            <th className="pr-3 py-2 w-32">Categoria</th>
            <th className="pr-3 py-2 w-24 text-right">Preço</th>
            {tweaks.showCost && <th className="pr-3 py-2 w-24 text-right">Custo · margem</th>}
            <th className="pr-3 py-2 w-24 text-right">Estoque</th>
            <th className="pr-6 py-2 w-20 text-right">30d</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.id} className="border-b border-stone-100 hover:bg-stone-50/60 cursor-pointer" style={{ height: rowH }}>
              <td className="pl-6 pr-3 font-mono text-[11.5px] text-stone-500">{r.sku}</td>
              <td className="pr-3 text-[13px] font-medium">{r.name}</td>
              <td className="pr-3 text-[12px] text-stone-600">{r.cat_label}</td>
              <td className="pr-3 text-[13px] text-right font-semibold">{fmtBRL(r.price)}</td>
              {tweaks.showCost && (
                <td className="pr-3 text-[12px] text-right">
                  <div className="text-stone-700">{fmtBRL(r.cost)}</div>
                  <div className={r.margin >= 0.5 ? 'text-emerald-700 text-[11px]' : r.margin >= 0.3 ? 'text-stone-500 text-[11px]' : 'text-rose-700 text-[11px]'}>
                    {fmtPct(r.margin)}
                  </div>
                </td>
              )}
              <td className="pr-3 text-[12.5px] text-right text-stone-700">
                {r.stockKind === 'estoque' ? `${r.stockQty} ${r.unit}` : 'sob demanda'}
              </td>
              <td className="pr-6 text-[12.5px] text-right">{r.uses30}</td>
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
        <div key={c.id} className="p-4 rounded-md bg-white border border-stone-200">
          <div className="text-[14px] font-semibold">{c.label}</div>
          <div className="mt-1 text-[12px] text-stone-500">{c.count} produtos</div>
        </div>
      ))}
    </div>
  );
}

function ListaInsumos({ rows }: { rows: InsumoRow[] }) {
  return (
    <div className="mx-6 mt-3 rounded-md bg-white border border-stone-200 shadow-sm overflow-hidden">
      <table className="w-full text-left">
        <thead className="text-[10.5px] uppercase tracking-widest text-stone-500 font-medium">
          <tr className="border-b border-stone-200 bg-stone-50/40">
            <th className="pl-6 py-2">Insumo</th>
            <th className="py-2 w-20">Unid.</th>
            <th className="py-2 w-28 text-right">Custo</th>
            <th className="py-2 w-24 text-right">Estoque</th>
            <th className="pr-6 py-2 w-44">Fornecedor</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((i) => (
            <tr key={i.id} className="border-b border-stone-100" style={{ height: 40 }}>
              <td className="pl-6 text-[13px] font-medium">{i.name}</td>
              <td className="text-[12px] text-stone-500">{i.unit}</td>
              <td className="text-[12.5px] text-right">{fmtBRL(i.cost)}</td>
              <td className="text-[12.5px] text-right">{i.stock}</td>
              <td className="pr-6 text-[12px] text-stone-600 truncate">{i.fornecedor ?? '—'}</td>
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
            <button key={t.id} onClick={() => setTableId(t.id)}
              className={`text-left p-4 rounded-md border ${active ? 'bg-stone-900 text-stone-50 border-stone-900' : 'bg-white border-stone-200'}`}>
              <div className={`text-[10px] uppercase tracking-widest ${active ? 'text-stone-400' : 'text-stone-500'}`}>Tabela</div>
              <div className="mt-1 text-[16px] font-semibold">{t.label}</div>
              <div className={`mt-1.5 text-[12px] ${active ? 'text-stone-300' : 'text-stone-600'}`}>{t.desc}</div>
              <div className="mt-3 text-[20px] font-semibold">{Math.round(t.mult * 100)}%</div>
            </button>
          );
        })}
      </div>
      {cur && (
        <div className="rounded-md bg-white border border-stone-200 overflow-hidden">
          <table className="w-full text-left">
            <thead className="text-[10.5px] uppercase tracking-widest text-stone-500">
              <tr className="border-b border-stone-200 bg-stone-50/40">
                <th className="pl-6 py-2 w-20">SKU</th>
                <th className="py-2">Produto</th>
                <th className="py-2 w-28 text-right">Balcão</th>
                <th className="py-2 w-28 text-right">Esta tabela</th>
                <th className="pr-6 py-2 w-24 text-right">Margem</th>
              </tr>
            </thead>
            <tbody>
              {produtos.filter((p) => p.active).map((p) => {
                const tab = p.price * cur.mult;
                const m = tab > 0 ? (tab - p.cost) / tab : 0;
                return (
                  <tr key={p.id} className="border-b border-stone-100" style={{ height: 40 }}>
                    <td className="pl-6 font-mono text-[11.5px] text-stone-500">{p.sku}</td>
                    <td className="text-[13px] font-medium">{p.name}</td>
                    <td className="text-[12.5px] text-right text-stone-500">{fmtBRL(p.price)}</td>
                    <td className="text-[13px] text-right font-semibold">{fmtBRL(tab)}</td>
                    <td className={`pr-6 text-[12.5px] text-right ${m >= 0.4 ? 'text-emerald-700' : m >= 0.15 ? 'text-stone-700' : 'text-rose-700'}`}>{fmtPct(m)}</td>
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
    <div className="mx-6 mt-3 rounded-md bg-white border border-stone-200 overflow-hidden">
      <table className="w-full text-left">
        <thead className="text-[10.5px] uppercase tracking-widest text-stone-500">
          <tr className="border-b border-stone-200 bg-stone-50/40">
            <th className="pl-6 py-2 w-24">Data</th>
            <th className="py-2 w-24">OS</th>
            <th className="py-2">Produto</th>
            <th className="py-2 w-44">Cliente</th>
            <th className="py-2 w-16 text-right">Qtd</th>
            <th className="pr-6 py-2 w-28 text-right">Valor</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r, idx) => (
            <tr key={r.os + r.prodId + idx} className="border-b border-stone-100" style={{ height: 36 }}>
              <td className="pl-6 text-[12px]">{r.date}</td>
              <td className="font-mono text-[11.5px] text-sky-700">{r.os}</td>
              <td className="text-[12.5px] font-medium">{r.prodName}</td>
              <td className="text-[12px] text-stone-600">{r.client ?? '—'}</td>
              <td className="text-[12.5px] text-right">{r.qty}</td>
              <td className="pr-6 text-[12.5px] text-right font-medium">{fmtBRL(r.value)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function TweaksPanel({ tweaks, setTweak }: { tweaks: any; setTweak: (e: any) => void }) {
  // TODO [CL]: substituir por <TweaksPanel> compartilhado quando existir.
  return (
    <div className="fixed bottom-4 right-4 w-64 rounded-md bg-white border border-stone-300 shadow-lg p-4 text-[12.5px]">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-2">Tweaks</div>
      <label className="flex items-center justify-between mb-2">
        <span>Densidade</span>
        <select value={tweaks.density} onChange={(e) => setTweak({ density: e.target.value })} className="border border-stone-200 rounded px-1">
          <option value="compact">Compact</option>
          <option value="comfortable">Comfortable</option>
          <option value="cozy">Cozy</option>
        </select>
      </label>
      <label className="flex items-center justify-between mb-2">
        <span>View</span>
        <select value={tweaks.view} onChange={(e) => setTweak({ view: e.target.value })} className="border border-stone-200 rounded px-1">
          <option value="table">Tabela</option>
          <option value="grid">Grade</option>
        </select>
      </label>
      <label className="flex items-center justify-between">
        <span>Mostrar custo</span>
        <input type="checkbox" checked={tweaks.showCost} onChange={(e) => setTweak({ showCost: e.target.checked })} />
      </label>
    </div>
  );
}
