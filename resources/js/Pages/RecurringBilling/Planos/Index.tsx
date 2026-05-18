// Planos — CRUD Inertia (v9,75 Onda 6 + Onda 21 refinos Cowork).
// Charter: ./Index.charter.md
// Refs: ADR 0104 MWART · 0107 visual gate · 0093 multi-tenant Tier 0 · US-RB-001
// Onda 21 v9,75 — refinos Cowork 11/13/14/15/18 aplicados (Sparkline, CmdPalette ⌘K,
//                 Tour onboarding compartilhado, CheatSheet ?, atalhos teclado J/K/N//)

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link, router, usePage } from '@inertiajs/react';
import {
  CheckCircle2,
  Pencil,
  Plus,
  Search,
  Trash2,
  TrendingUp,
  XCircle,
  type LucideIcon,
} from 'lucide-react';
import {
  useEffect,
  useMemo,
  useRef,
  useState,
  type FormEvent,
  type ReactNode,
} from 'react';

// Onda 21 v9,75 — sub-components Cowork refinos compartilhados.
import Sparkline from '../_components/Sparkline';
import CmdPalette from '../_components/CmdPalette';
import TourOnboarding, { TOUR_DONE_KEY } from '../_components/TourOnboarding';
import CheatSheet from '../_components/CheatSheet';

// ────────────────────────────────────────────────────────────────
// TIPOS
// ────────────────────────────────────────────────────────────────

type Ciclo = 'monthly' | 'quarterly' | 'semiannual' | 'yearly' | 'custom';
type FiscalType = 'nfe' | 'nfse' | 'none';

interface PlanRow {
  id: number;
  name: string;
  slug: string;
  descricao_curta: string | null;
  valor: number;
  ciclo: Ciclo;
  ciclo_label: string;
  ciclo_dias: number | null;
  trial_days: number;
  ativo: boolean;
  fiscal_type: FiscalType;
  assinaturas_ativas_count: number;
  created_at: string | null;
}

interface PlansPaginated {
  data: PlanRow[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

interface KpisPayload {
  total_planos: number;
  total_ativos: number;
  mrr_potencial: number;
  distribuicao: {
    monthly: number;
    quarterly: number;
    semiannual: number;
    yearly: number;
    custom: number;
  };
}

interface Filters {
  q: string;
  per_page: number;
}

interface PageProps {
  filters: Filters;
  plans?: PlansPaginated;
  kpis?: KpisPayload;
  flash?: { success?: string; error?: string };
  [key: string]: unknown;
}

// ────────────────────────────────────────────────────────────────
// HELPERS
// ────────────────────────────────────────────────────────────────

const BRL = (n: number) =>
  n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const BRLshort = (n: number) =>
  Math.abs(n) >= 1000
    ? `R$ ${(n / 1000).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}k`
    : BRL(n);

const FISCAL_LABEL: Record<FiscalType, { label: string; classes: string }> = {
  nfe:  { label: 'NFe',        classes: 'bg-blue-100 text-blue-700 ring-blue-200' },
  nfse: { label: 'NFS-e',      classes: 'bg-emerald-100 text-emerald-700 ring-emerald-200' },
  none: { label: 'Não emite',  classes: 'bg-zinc-100 text-zinc-500 ring-zinc-200' },
};

const CICLO_LABEL: Record<Ciclo, string> = {
  monthly:    'mensal',
  quarterly:  'trimestral',
  semiannual: 'semestral',
  yearly:     'anual',
  custom:     'customizado',
};

// ────────────────────────────────────────────────────────────────
// SUB-COMPONENTES
// ────────────────────────────────────────────────────────────────

function KpiCard({
  label,
  value,
  delta,
  deltaTone = 'neutral',
  hero = false,
  sparkline,
}: {
  label: string;
  value: ReactNode;
  delta?: string;
  deltaTone?: 'ok' | 'warn' | 'bad' | 'neutral';
  hero?: boolean;
  sparkline?: number[];
}) {
  // Onda 21 v9,75 — paleta delta tons (espelha Index.tsx principal).
  const deltaCls = {
    ok: 'text-emerald-300',
    warn: 'text-amber-400',
    bad: 'text-rose-400',
    neutral: 'text-zinc-400',
  }[deltaTone];
  const deltaClsLight = {
    ok: 'text-emerald-700',
    warn: 'text-amber-700',
    bad: 'text-rose-700',
    neutral: 'text-zinc-500',
  }[deltaTone];

  if (hero) {
    return (
      <div className="rounded-2xl bg-zinc-900 p-4 text-white shadow-sm ring-1 ring-zinc-800">
        <div className="flex items-center justify-between text-[11px] font-medium uppercase tracking-wider text-zinc-400">
          <span>{label}</span>
          {sparkline && sparkline.length > 0 ? (
            <Sparkline points={sparkline} color="oklch(0.75 0.13 145)" />
          ) : (
            <TrendingUp size={14} className="text-emerald-400" />
          )}
        </div>
        <div className="mt-2 text-2xl font-bold tabular-nums">{value}</div>
        {delta && <div className={`mt-1 text-xs font-medium ${deltaCls}`}>{delta}</div>}
      </div>
    );
  }
  return (
    <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-zinc-200">
      <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">{label}</div>
      <div className="mt-2 text-2xl font-bold text-zinc-900 tabular-nums">{value}</div>
      {delta && <div className={`mt-1 text-xs font-medium ${deltaClsLight}`}>{delta}</div>}
    </div>
  );
}

function CicloDistribuicao({ kpis }: { kpis: KpisPayload }) {
  const totals: Array<{ key: Ciclo; label: string; count: number; color: string }> = [
    { key: 'monthly',    label: 'mensal',      count: kpis.distribuicao.monthly,    color: 'bg-violet-500' },
    { key: 'quarterly',  label: 'trimestral',  count: kpis.distribuicao.quarterly,  color: 'bg-blue-500' },
    { key: 'semiannual', label: 'semestral',   count: kpis.distribuicao.semiannual, color: 'bg-cyan-500' },
    { key: 'yearly',     label: 'anual',       count: kpis.distribuicao.yearly,     color: 'bg-emerald-500' },
    { key: 'custom',     label: 'customizado', count: kpis.distribuicao.custom,     color: 'bg-amber-500' },
  ];
  const max = Math.max(...totals.map((t) => t.count), 1);

  return (
    <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-zinc-200">
      <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
        Distribuição ciclos
      </div>
      <ul className="mt-3 space-y-1.5">
        {totals.map((t) => (
          <li key={t.key} className="flex items-center gap-2 text-xs">
            <span className="w-20 text-zinc-600">{t.label}</span>
            <div className="relative h-3 flex-1 overflow-hidden rounded-full bg-zinc-100">
              <div
                className={`absolute inset-y-0 left-0 ${t.color}`}
                style={{ width: `${(t.count / max) * 100}%` }}
              />
            </div>
            <span className="w-6 text-right font-mono tabular-nums text-zinc-700">{t.count}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

function StatusBadge({ ativo }: { ativo: boolean }) {
  return ativo ? (
    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-200">
      <CheckCircle2 size={11} />
      ativo
    </span>
  ) : (
    <span className="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-500 ring-1 ring-zinc-200">
      <XCircle size={11} />
      inativo
    </span>
  );
}

function FiscalBadge({ type }: { type: FiscalType }) {
  const f = FISCAL_LABEL[type];
  return (
    <span className={`inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold ring-1 ${f.classes}`}>
      {f.label}
    </span>
  );
}

function FlashBanner({ flash }: { flash?: { success?: string; error?: string } }) {
  if (!flash || (!flash.success && !flash.error)) return null;
  const isError = !!flash.error;
  const msg = flash.error ?? flash.success;
  return (
    <div
      className={`mb-4 rounded-lg p-3 text-sm font-medium ring-1 ${
        isError
          ? 'bg-rose-50 text-rose-800 ring-rose-200'
          : 'bg-emerald-50 text-emerald-800 ring-emerald-200'
      }`}
    >
      {msg}
    </div>
  );
}

// ────────────────────────────────────────────────────────────────
// PAGE
// ────────────────────────────────────────────────────────────────

export default function PlanosIndex(props: PageProps) {
  const { filters, plans, kpis } = props;
  const { props: pageProps } = usePage<PageProps>();
  const flash = pageProps.flash;

  const [search, setSearch] = useState(filters.q || '');
  const searchRef = useRef<HTMLInputElement>(null);

  // Onda 21 v9,75 — state overlays (CmdPalette ⌘K, CheatSheet ?, Tour onboarding)
  const [activeId, setActiveId] = useState<number | null>(null);
  const [showCmdPalette, setShowCmdPalette] = useState(false);
  const [showCheatsheet, setShowCheatsheet] = useState(false);
  const [showTour, setShowTour] = useState(false);

  const rows = plans?.data ?? [];

  // Onda 21 v9,75 — Tour onboarding 1ª vez (TOUR_DONE_KEY compartilhado com Index Assinaturas).
  useEffect(() => {
    try {
      if (localStorage.getItem(TOUR_DONE_KEY) !== '1') {
        setShowTour(true);
      }
    } catch {
      // silently ignore (private mode etc)
    }
  }, []);

  // Onda 21 v9,75 — Atalhos teclado completos (J/K navegar, N novo, / busca, ? cheatsheet, ⌘K palette, Esc).
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const t = e.target as HTMLElement;
      const inField = t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable;
      if (inField && e.key !== 'Escape') return;
      if (e.key === '/') {
        e.preventDefault();
        searchRef.current?.focus();
      } else if (e.key === 'Escape') {
        (t as HTMLInputElement).blur?.();
      } else if (e.key === 'j' || e.key === 'k') {
        if (rows.length === 0) return;
        e.preventDefault();
        const idx = rows.findIndex((p) => p.id === activeId);
        if (e.key === 'j') {
          const nextIdx = idx < 0 ? 0 : Math.min(idx + 1, rows.length - 1);
          const next = rows[nextIdx];
          if (next) setActiveId(next.id);
        } else {
          const prevIdx = idx <= 0 ? 0 : idx - 1;
          const prev = rows[prevIdx];
          if (prev) setActiveId(prev.id);
        }
      } else if (e.key === 'n' || e.key === 'N') {
        e.preventDefault();
        router.visit('/recurring-billing/planos/novo');
      } else if (e.key === '?') {
        e.preventDefault();
        setShowCheatsheet(true);
      } else if ((e.key === 'k' || e.key === 'K') && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        setShowCmdPalette(true);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [rows, activeId]);

  // Onda 21 v9,75 — KPIs derivados (ticket médio + plano top vendido).
  const ticketMedio = useMemo(() => {
    if (!kpis || kpis.total_planos === 0) return 0;
    // Aproximação: MRR potencial / total planos. Quando backend fornecer mrr_real, trocar.
    return kpis.mrr_potencial / Math.max(kpis.total_planos, 1);
  }, [kpis]);

  const topPlan = useMemo(() => {
    if (rows.length === 0) return null;
    return rows.reduce<PlanRow | null>(
      (best, p) =>
        !best || p.assinaturas_ativas_count > best.assinaturas_ativas_count ? p : best,
      null,
    );
  }, [rows]);

  function handleSearch(e: FormEvent) {
    e.preventDefault();
    router.reload({
      data: { q: search, per_page: filters.per_page },
      only: ['plans', 'kpis'],
    });
  }

  function handleDelete(plan: PlanRow) {
    const aviso =
      plan.assinaturas_ativas_count > 0
        ? `\n\nATENÇÃO: este plano tem ${plan.assinaturas_ativas_count} assinatura(s) ativa(s) — a exclusão será bloqueada (422).`
        : '';
    if (!confirm(`Excluir plano "${plan.name}"?${aviso}`)) return;

    router.delete(`/recurring-billing/planos/${plan.id}`, {
      preserveScroll: true,
      onFinish: () => {
        // Inertia reload props pra refletir contagem + flash.
        router.reload({ only: ['plans', 'kpis', 'flash'] });
      },
    });
  }

  return (
    <>
      <Head title="Planos · Cobrança Recorrente" />

      <div className="min-h-screen bg-zinc-50 p-4 md:p-6">
        {/* HEADER */}
        <header className="mb-4">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold tracking-tight text-zinc-900">
                Planos · cobrança recorrente
              </h1>
              <div className="mt-1 font-mono text-[11px] uppercase tracking-wider text-zinc-500">
                {kpis ? (
                  <>
                    {kpis.total_planos} PLANOS · {kpis.total_ativos} ATIVOS · MRR potencial {BRL(kpis.mrr_potencial)}
                  </>
                ) : (
                  'carregando…'
                )}
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Link
                href="/recurring-billing"
                className="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-medium text-zinc-700 shadow-sm ring-1 ring-zinc-200 hover:bg-zinc-50"
              >
                Voltar
              </Link>
              <button
                type="button"
                onClick={() => setShowCheatsheet(true)}
                title="Atalhos teclado (?)"
                className="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-medium text-zinc-700 shadow-sm ring-1 ring-zinc-200 hover:bg-zinc-50"
              >
                <kbd className="rounded bg-zinc-100 px-1 text-[10px] font-mono text-zinc-500 ring-1 ring-zinc-200">?</kbd>
              </button>
              <Link
                href="/recurring-billing/planos/novo"
                className="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-violet-700"
              >
                <Plus size={14} />
                Novo plano
                <kbd className="ml-1 rounded bg-violet-700 px-1 text-[10px] font-mono">N</kbd>
              </Link>
            </div>
          </div>
        </header>

        <FlashBanner flash={flash} />

        {/* KPIs — Onda 21 v9,75: 4 cards (Ticket médio hero+sparkline, Total planos, Total ativos, Plano top) + Distribuição abaixo */}
        <Deferred data="kpis" fallback={<KpiSkeleton />}>
          {kpis && (
            <>
              <div className="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <KpiCard
                  label="Ticket médio · MRR potencial"
                  value={BRL(ticketMedio)}
                  delta={`MRR total ${BRLshort(kpis.mrr_potencial)}`}
                  deltaTone="ok"
                  hero
                  sparkline={(() => {
                    // Onda 21 v9,75 — sparkline mock 12 pontos crescente (futuro: backend retorna ticket histórico 12m real).
                    if (ticketMedio === 0) return [];
                    return [0.72, 0.76, 0.79, 0.82, 0.85, 0.87, 0.9, 0.92, 0.94, 0.96, 0.98, 1].map((r) => ticketMedio * r);
                  })()}
                />
                <KpiCard
                  label="Total planos"
                  value={kpis.total_planos}
                  delta={`${kpis.total_planos - kpis.total_ativos} inativos`}
                  deltaTone="neutral"
                />
                <KpiCard
                  label="Total ativos"
                  value={kpis.total_ativos}
                  delta="disponíveis pra novas assinaturas"
                  deltaTone="ok"
                />
                <KpiCard
                  label="Plano top vendido"
                  value={topPlan ? topPlan.name : '—'}
                  delta={
                    topPlan && topPlan.assinaturas_ativas_count > 0
                      ? `${topPlan.assinaturas_ativas_count} assin. · ${BRLshort(topPlan.valor)}`
                      : 'sem assinaturas ativas'
                  }
                  deltaTone={topPlan && topPlan.assinaturas_ativas_count > 0 ? 'ok' : 'neutral'}
                />
              </div>
              {/* Distribuição ciclos full-width abaixo dos KPIs */}
              <div className="mb-4">
                <CicloDistribuicao kpis={kpis} />
              </div>
            </>
          )}
        </Deferred>

        {/* Tabela */}
        <section className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200">
          {/* Toolbar */}
          <form
            onSubmit={handleSearch}
            className="flex items-center gap-2 border-b border-zinc-200 p-2.5"
          >
            <div className="flex flex-1 items-center gap-2 rounded-lg bg-zinc-100 px-3 py-1.5">
              <Search size={14} className="text-zinc-400" />
              <input
                ref={searchRef}
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Buscar (/) — nome ou slug"
                className="flex-1 bg-transparent text-sm outline-none placeholder:text-zinc-400"
              />
              <kbd className="rounded bg-white px-1.5 py-0.5 text-[10px] font-mono text-zinc-500 ring-1 ring-zinc-200">
                /
              </kbd>
            </div>
            <button
              type="submit"
              className="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-zinc-800"
            >
              Buscar
            </button>
          </form>

          <Deferred data="plans" fallback={<ListSkeleton />}>
            {rows.length === 0 ? (
              <EmptyState />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="bg-zinc-50 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">
                    <tr>
                      <th className="px-4 py-2 text-left">Plano</th>
                      <th className="px-4 py-2 text-left">Ciclo</th>
                      <th className="px-4 py-2 text-right">Valor</th>
                      <th className="px-4 py-2 text-center">Assinaturas</th>
                      <th className="px-4 py-2 text-center">Fiscal</th>
                      <th className="px-4 py-2 text-center">Status</th>
                      <th className="px-4 py-2 text-right">Ações</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-zinc-100">
                    {rows.map((p) => (
                      <tr
                        key={p.id}
                        onClick={() => setActiveId(p.id)}
                        className={`cursor-pointer transition ${
                          activeId === p.id
                            ? 'border-l-2 border-l-violet-500 bg-violet-50/50'
                            : 'hover:bg-zinc-50'
                        }`}
                      >
                        <td className="px-4 py-2.5">
                          <div className="font-medium text-zinc-900">{p.name}</div>
                          <div className="font-mono text-[11px] text-zinc-500">{p.slug}</div>
                          {p.descricao_curta && (
                            <div className="mt-0.5 text-[11px] text-zinc-500">{p.descricao_curta}</div>
                          )}
                        </td>
                        <td className="px-4 py-2.5 text-zinc-700">
                          {CICLO_LABEL[p.ciclo] ?? p.ciclo_label}
                          {p.ciclo === 'custom' && p.ciclo_dias && (
                            <span className="ml-1 text-[11px] text-zinc-500">({p.ciclo_dias}d)</span>
                          )}
                        </td>
                        <td className="px-4 py-2.5 text-right font-mono tabular-nums text-zinc-900">
                          {BRLshort(p.valor)}
                        </td>
                        <td className="px-4 py-2.5 text-center font-mono tabular-nums text-zinc-700">
                          {p.assinaturas_ativas_count}
                        </td>
                        <td className="px-4 py-2.5 text-center">
                          <FiscalBadge type={p.fiscal_type} />
                        </td>
                        <td className="px-4 py-2.5 text-center">
                          <StatusBadge ativo={p.ativo} />
                        </td>
                        <td className="px-4 py-2.5 text-right">
                          <div className="inline-flex items-center gap-1">
                            <Link
                              href={`/recurring-billing/planos/${p.id}/editar`}
                              className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-zinc-700 hover:bg-zinc-100"
                              title="Editar"
                            >
                              <Pencil size={12} />
                              Editar
                            </Link>
                            <button
                              type="button"
                              onClick={() => handleDelete(p)}
                              className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-rose-600 hover:bg-rose-50"
                              title="Excluir"
                            >
                              <Trash2 size={12} />
                              Excluir
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Deferred>

          {/* Footer paginação */}
          {plans && plans.meta.total > 0 && (
            <div className="flex items-center justify-between border-t border-zinc-200 px-4 py-2 text-xs text-zinc-500">
              <span>
                {plans.meta.total} {plans.meta.total === 1 ? 'plano' : 'planos'} no total
              </span>
              <span>
                Página {plans.meta.current_page} de {plans.meta.last_page}
              </span>
            </div>
          )}
        </section>
      </div>

      {/* Onda 21 v9,75 — Overlays: Tour onboarding (1ª vez compartilhado), CheatSheet (?), CmdPalette (⌘K) */}
      {showTour && (
        <TourOnboarding onClose={() => setShowTour(false)} />
      )}
      {showCheatsheet && <CheatSheet onClose={() => setShowCheatsheet(false)} />}
      {showCmdPalette && (
        <CmdPalette
          subs={[]} /* sem subs na Page Planos — IA fallback continua útil */
          plans={rows.map((p) => ({
            id: p.id,
            name: p.name,
            price: p.valor,
            cycle_label: p.ciclo_label,
          }))}
          onClose={() => setShowCmdPalette(false)}
          onPick={(item) => {
            setShowCmdPalette(false);
            if (item.kind === 'plan') {
              router.visit(`/recurring-billing/planos/${item.id}/editar`);
            } else if (item.kind === 'sub') {
              router.visit('/recurring-billing');
            }
          }}
        />
      )}
    </>
  );
}

// ────────────────────────────────────────────────────────────────
// EMPTY + SKELETONS
// ────────────────────────────────────────────────────────────────

function EmptyState() {
  return (
    <div className="p-12 text-center">
      <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-violet-100">
        <Plus size={20} className="text-violet-600" />
      </div>
      <div className="text-lg font-medium text-zinc-700">Nenhum plano cadastrado.</div>
      <div className="mt-1 text-sm text-zinc-500">
        Crie o primeiro plano pra começar a vincular assinaturas.
      </div>
      <Link
        href="/recurring-billing/planos/novo"
        className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
      >
        <Plus size={14} />
        Criar primeiro plano
      </Link>
    </div>
  );
}

function KpiSkeleton() {
  return (
    <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
      {Array.from({ length: 4 }).map((_, i) => (
        <div key={i} className="h-24 animate-pulse rounded-2xl bg-zinc-100" />
      ))}
    </div>
  );
}

function ListSkeleton() {
  return (
    <div className="divide-y divide-zinc-100">
      {Array.from({ length: 5 }).map((_, i) => (
        <div key={i} className="flex items-center gap-3 px-4 py-3">
          <div className="flex-1 space-y-1">
            <div className="h-3 w-40 animate-pulse rounded bg-zinc-200" />
            <div className="h-2 w-24 animate-pulse rounded bg-zinc-100" />
          </div>
          <div className="h-4 w-16 animate-pulse rounded-full bg-zinc-100" />
          <div className="h-4 w-12 animate-pulse rounded bg-zinc-100" />
        </div>
      ))}
    </div>
  );
}

// Re-export ícone pra silenciar TS-warn (Pencil/Trash2/etc importados acima).
type _Unused = LucideIcon;

PlanosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
