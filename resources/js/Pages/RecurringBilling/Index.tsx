// Cobrança Recorrente — primeiro Page Inertia (v9,75 Ondas 3+4+5).
// Visual canon: prototipo-ui/prototipos/recurring/recurring-page.jsx (Refino #1 — 3-col base).
// Charter: ./Index.charter.md
// Refs: ADR 0104 MWART · 0107 visual gate · 0114 Cowork loop · 0093 multi-tenant Tier 0

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import {
  Banknote,
  CreditCard,
  Pause,
  Pencil,
  Play,
  Plus,
  RefreshCw,
  Search,
  Star,
  TrendingUp,
  XCircle,
  Zap,
  type LucideIcon,
} from 'lucide-react';
import {
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react';

// ────────────────────────────────────────────────────────────────
// TIPOS — espelham SubscriptionIndexPresenter (PHP)
// ────────────────────────────────────────────────────────────────

type VisualStatus = 'em_dia' | 'retentando' | 'falhou' | 'pausada' | 'cancelada';
type PaymentMethod = 'pix' | 'boleto' | 'card';
type FiscalType = 'nfe' | 'nfse' | 'none';
type Tab = 'assinaturas' | 'planos' | 'faturas' | 'configuracoes';

interface Filters {
  status_visual: string;
  when: string;
  busca: string;
}

interface Kpis {
  mrr: number;
  mrr_delta: number;
  churn_count: number;
  churn_rate: number;
  next_charge_when: string;
  next_charge_value: number;
  next_charge_count: number;
  failed_count: number;
  retrying_count: number;
  active_count: number;
  paused_count: number;
  total_ltv: number;
}

interface SubRow {
  id: number;
  client: string;
  cnpj: string | null;
  plan_id: number;
  plan_name: string;
  plan_cycle: string;
  since: string | null;
  method: PaymentMethod;
  status: VisualStatus;
  retry: number | null;
  retry_max: number;
  next_at: string | null;
  next_value: number;
  os: string | null;
  is_pinned: boolean;
  paid: number;
  missed: number;
  ltv: number;
  contact: { name: string; phone: string; email: string | null };
  note: { body: string; by: string; at: string | null } | null;
  fiscal: { type: FiscalType; channels: string[]; last_nf: string | null };
  churn_reason: string | null;
  paused_until: string | null;
  canceled_at: string | null;
}

interface SubsPaginated {
  data: SubRow[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

interface PlanRow {
  id: number;
  name: string;
  cycle: string;
  cycle_label: string;
  price: number;
  items: string | null;
  fiscal_type: FiscalType;
}

interface PageProps {
  filters: Filters;
  tab: Tab;
  kpis?: Kpis;
  subscriptions?: SubsPaginated;
  plans?: PlanRow[];
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

function hueFor(name: string): number {
  let h = 0;
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0;
  return h % 360;
}

function initials(name: string): string {
  return (name || '')
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0] || '')
    .join('')
    .toUpperCase()
    .slice(0, 2);
}

function daysAgoLabel(iso: string | null): string | null {
  if (!iso) return null;
  const d = new Date(iso);
  if (isNaN(d.getTime())) return null;
  const diff = Math.floor((Date.now() - d.getTime()) / 86400000);
  if (diff <= 0) return 'hoje';
  if (diff === 1) return 'ontem';
  if (diff < 30) return `há ${diff}d`;
  if (diff < 365) return `há ${Math.floor(diff / 30)}m`;
  return `há ${Math.floor(diff / 365)}a`;
}

function nextLabel(iso: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (isNaN(d.getTime())) return iso;
  const diff = Math.ceil((d.getTime() - Date.now()) / 86400000);
  if (diff === 0) return 'hoje';
  if (diff === 1) return 'amanhã';
  if (diff < 0) return `há ${-diff}d`;
  return `em ${diff} dias`;
}

function nextDateBR(iso: string | null): string | null {
  if (!iso) return null;
  const d = new Date(iso);
  if (isNaN(d.getTime())) return null;
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
}

const STATUS_STYLES: Record<VisualStatus, { label: string; classes: string; dot: string }> = {
  em_dia: {
    label: 'em dia',
    classes: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    dot: 'bg-emerald-500',
  },
  retentando: {
    label: 'retentando',
    classes: 'bg-amber-50 text-amber-700 ring-amber-200',
    dot: 'bg-amber-500',
  },
  falhou: {
    label: 'falhou',
    classes: 'bg-rose-50 text-rose-700 ring-rose-200',
    dot: 'bg-rose-500',
  },
  pausada: {
    label: 'pausada',
    classes: 'bg-zinc-100 text-zinc-600 ring-zinc-200',
    dot: 'bg-zinc-400',
  },
  cancelada: {
    label: 'cancelada',
    classes: 'bg-zinc-100 text-zinc-400 ring-zinc-200 line-through',
    dot: 'bg-zinc-300',
  },
};

const METHOD_ICONS: Record<PaymentMethod, LucideIcon> = {
  pix: Zap,
  boleto: Banknote,
  card: CreditCard,
};

// ────────────────────────────────────────────────────────────────
// SUB-COMPONENTES
// ────────────────────────────────────────────────────────────────

function Avatar({ name, size = 28 }: { name: string; size?: number }) {
  const h = hueFor(name);
  return (
    <span
      className="inline-flex items-center justify-center rounded-full font-semibold text-white shrink-0"
      style={{
        width: size,
        height: size,
        fontSize: size * 0.42,
        background: `linear-gradient(135deg, oklch(0.70 0.10 ${h}), oklch(0.50 0.13 ${h}))`,
      }}
    >
      {initials(name)}
    </span>
  );
}

function StatusBadge({ status, retry, retryMax }: { status: VisualStatus; retry?: number | null; retryMax?: number }) {
  const s = STATUS_STYLES[status];
  if (status === 'retentando' && retry != null) {
    const max = retryMax || 3;
    return (
      <span className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ${s.classes}`}>
        <span className="flex gap-0.5">
          {Array.from({ length: max }, (_, i) => (
            <span
              key={i}
              className={`h-1 w-1 rounded-full ${i < retry ? 'bg-amber-600' : 'bg-amber-200'}`}
            />
          ))}
        </span>
        retentando {retry}/{max}
      </span>
    );
  }
  if (status === 'falhou' && retry != null) {
    return (
      <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ${s.classes}`}>
        falhou {retry}x
      </span>
    );
  }
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ${s.classes}`}>
      {s.label}
    </span>
  );
}

function MethodIcon({ method, size = 12 }: { method: PaymentMethod; size?: number }) {
  const Icon = METHOD_ICONS[method];
  return <Icon size={size} className="text-zinc-500" />;
}

function KpiCard({ label, value, delta, deltaTone = 'neutral', hero = false }: {
  label: string;
  value: ReactNode;
  delta?: string;
  deltaTone?: 'ok' | 'warn' | 'bad' | 'neutral';
  hero?: boolean;
}) {
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
          <TrendingUp size={14} className="text-emerald-400" />
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

// ────────────────────────────────────────────────────────────────
// PAGE
// ────────────────────────────────────────────────────────────────

export default function RecurringBillingIndex(props: PageProps) {
  const { filters, kpis, subscriptions, plans } = props;
  const [tab, setTab] = useState<Tab>(props.tab || 'assinaturas');
  const [activeId, setActiveId] = useState<number | null>(null);
  const [statusFilter, setStatusFilter] = useState<string>(filters.status_visual || 'all');
  const [whenFilter, setWhenFilter] = useState<string>(filters.when || 'any');
  const [search, setSearch] = useState<string>(filters.busca || '');
  const [onlyPinned, setOnlyPinned] = useState(false);
  const searchRef = useRef<HTMLInputElement>(null);

  // Atalho `/` focus search
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
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  // Reload server-side on filter change (Inertia partial reload — só re-fetch subscriptions+kpis)
  function applyFilters(next: Partial<{ status: string; when: string; q: string }>) {
    router.reload({
      data: {
        status: next.status ?? statusFilter,
        when: next.when ?? whenFilter,
        q: next.q ?? search,
      },
      only: ['subscriptions', 'kpis'],
    });
  }

  const subsData = subscriptions?.data || [];

  // Filtros client-side adicionais (favoritos)
  const filtered = useMemo(() => {
    if (!onlyPinned) return subsData;
    return subsData.filter((s) => s.is_pinned);
  }, [subsData, onlyPinned]);

  const active = useMemo(() => {
    if (!activeId) return filtered[0] ?? null;
    return filtered.find((s) => s.id === activeId) ?? filtered[0] ?? null;
  }, [filtered, activeId]);

  const filteredMrr = filtered
    .filter((s) => s.status !== 'cancelada')
    .reduce((acc, s) => acc + (s.next_value || 0), 0);

  // ── Tabs sub-rota
  const TABS: Array<{ key: Tab; label: string; count?: number }> = [
    { key: 'assinaturas', label: 'Assinaturas', count: subscriptions?.meta?.total },
    { key: 'planos', label: 'Planos', count: plans?.length },
    { key: 'faturas', label: 'Faturas' },
    { key: 'configuracoes', label: 'Configurações' },
  ];

  // ── Filtros temporais
  const WHEN_FILTERS: Array<{ key: string; label: string }> = [
    { key: 'any', label: 'Qualquer data' },
    { key: 'today', label: 'Hoje' },
    { key: 'tomorrow', label: 'Amanhã' },
    { key: 'week', label: 'Esta semana' },
    { key: 'month', label: 'Próx. 30 dias' },
  ];

  // ── Filtros status
  const STATUS_FILTERS: Array<{ key: string; label: string; dot: string }> = [
    { key: 'all', label: 'Todas', dot: 'bg-zinc-300' },
    { key: 'em_dia', label: 'Em dia', dot: 'bg-emerald-500' },
    { key: 'retentando', label: 'Retentando', dot: 'bg-amber-500' },
    { key: 'falhou', label: 'Falharam', dot: 'bg-rose-500' },
    { key: 'pausada', label: 'Pausadas', dot: 'bg-zinc-400' },
    { key: 'cancelada', label: 'Canceladas', dot: 'bg-zinc-300' },
  ];

  return (
    <>
      <Head title="Cobrança Recorrente" />

      <div className="min-h-screen bg-zinc-50 p-4 md:p-6">
        {/* ── HEADER ── */}
        <header className="mb-4">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold tracking-tight text-zinc-900">Cobrança recorrente</h1>
              <div className="mt-1 font-mono text-[11px] uppercase tracking-wider text-zinc-500">
                {kpis ? (
                  <>
                    {kpis.active_count} ATIVAS · MRR {BRL(kpis.mrr)} · CHURN {kpis.churn_rate}%
                  </>
                ) : (
                  'carregando…'
                )}
              </div>
            </div>
            <div className="flex items-center gap-2">
              <nav className="flex gap-1 rounded-lg bg-white p-1 shadow-sm ring-1 ring-zinc-200">
                {TABS.map((t) => (
                  <button
                    key={t.key}
                    type="button"
                    onClick={() => {
                      // Onda 9 v9,75 — tabs navegam pras Pages reais (rotas dedicadas).
                      if (t.key === 'planos') {
                        router.visit('/recurring-billing/planos');
                      } else if (t.key === 'faturas') {
                        router.visit('/recurring-billing/faturas');
                      } else if (t.key === 'configuracoes') {
                        router.visit('/recurring-billing/configuracoes');
                      } else {
                        setTab(t.key);
                      }
                    }}
                    className={`flex items-center gap-1.5 rounded px-3 py-1.5 text-sm font-medium transition ${
                      tab === t.key
                        ? 'bg-violet-100 text-violet-900'
                        : 'text-zinc-600 hover:bg-zinc-100'
                    }`}
                  >
                    {t.label}
                    {t.count !== undefined && (
                      <span className="rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-zinc-700">
                        {t.count}
                      </span>
                    )}
                  </button>
                ))}
              </nav>
              <button
                type="button"
                className="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-violet-700"
                title="Nova assinatura (em breve)"
              >
                <Plus size={14} />
                Nova assinatura
                <kbd className="ml-1 rounded bg-violet-700 px-1 text-[10px] font-mono">N</kbd>
              </button>
            </div>
          </div>
        </header>

        {/* ── 4 KPI CARDS ── */}
        <Deferred data="kpis" fallback={<KpiSkeleton />}>
          {kpis && (
            <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <KpiCard
                label="MRR · receita recorrente"
                value={BRL(kpis.mrr)}
                delta={kpis.mrr_delta ? `↑ ${BRLshort(kpis.mrr_delta)} vs mês anterior` : 'baseline'}
                deltaTone="ok"
                hero
              />
              <KpiCard
                label="Churn este mês"
                value={`${kpis.churn_count} ${kpis.churn_count === 1 ? 'cancelamento' : 'cancelamentos'}`}
                delta={`taxa ${kpis.churn_rate}%`}
                deltaTone="warn"
              />
              <KpiCard
                label="Próxima cobrança"
                value={kpis.next_charge_when}
                delta={`${BRL(kpis.next_charge_value)} · ${kpis.next_charge_count} cobranças`}
                deltaTone="neutral"
              />
              <KpiCard
                label="Retentado falhos"
                value={kpis.failed_count}
                delta="requer ação"
                deltaTone="bad"
              />
            </div>
          )}
        </Deferred>

        {/* ── Placeholder pras outras 3 tabs ── */}
        {tab !== 'assinaturas' && (
          <div className="rounded-2xl bg-white p-12 text-center shadow-sm ring-1 ring-zinc-200">
            <div className="text-lg font-medium text-zinc-700">
              Aba <strong className="text-violet-700">{TABS.find((t) => t.key === tab)?.label}</strong> em construção
            </div>
            <div className="mt-2 text-sm text-zinc-500">
              Próximas ondas: Planos · Faturas · Configurações. Por ora use a aba Assinaturas.
            </div>
            <button
              type="button"
              onClick={() => setTab('assinaturas')}
              className="mt-4 inline-flex rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
            >
              Voltar a Assinaturas
            </button>
          </div>
        )}

        {/* ── 3-COL BODY (sub-tab Assinaturas) ── */}
        {tab === 'assinaturas' && (
          <div className="grid grid-cols-1 gap-3 lg:grid-cols-[220px_1fr_340px]">
            {/* COL 1 · FILTROS */}
            <aside className="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-zinc-200">
              <button
                type="button"
                onClick={() => setOnlyPinned((p) => !p)}
                className={`mb-3 flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium transition ${
                  onlyPinned
                    ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-200'
                    : 'text-zinc-700 hover:bg-zinc-50'
                }`}
              >
                <Star size={14} className={onlyPinned ? 'fill-amber-500 text-amber-500' : ''} />
                <span className="flex-1 text-left">
                  {onlyPinned ? 'Mostrando favoritos' : 'Mostrar só favoritos'}
                </span>
                <span className="rounded bg-zinc-200 px-1.5 py-0.5 text-[10px] tabular-nums">
                  {subsData.filter((s) => s.is_pinned).length}
                </span>
              </button>

              <div className="mt-3 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                Próxima cobrança
              </div>
              <ul className="mt-1">
                {WHEN_FILTERS.map((f) => (
                  <li
                    key={f.key}
                    onClick={() => {
                      setWhenFilter(f.key);
                      applyFilters({ when: f.key });
                    }}
                    className={`flex cursor-pointer items-center justify-between rounded-lg px-2 py-1.5 text-sm transition ${
                      whenFilter === f.key
                        ? 'bg-violet-50 font-medium text-violet-900'
                        : 'text-zinc-700 hover:bg-zinc-50'
                    }`}
                  >
                    <span>{f.label}</span>
                  </li>
                ))}
              </ul>

              <div className="mt-4 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                Status
              </div>
              <ul className="mt-1">
                {STATUS_FILTERS.map((f) => (
                  <li
                    key={f.key}
                    onClick={() => {
                      setStatusFilter(f.key);
                      applyFilters({ status: f.key });
                    }}
                    className={`flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm transition ${
                      statusFilter === f.key
                        ? 'bg-violet-50 font-medium text-violet-900'
                        : 'text-zinc-700 hover:bg-zinc-50'
                    }`}
                  >
                    <span className={`h-2 w-2 rounded-full ${f.dot}`} />
                    <span className="flex-1">{f.label}</span>
                  </li>
                ))}
              </ul>

              {plans && plans.length > 0 && (
                <>
                  <div className="mt-4 text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                    Plano
                  </div>
                  <ul className="mt-1 space-y-1">
                    {plans.map((p) => {
                      const n = subsData.filter((s) => s.plan_id === p.id && s.status !== 'cancelada').length;
                      return (
                        <li key={p.id} className="rounded-lg px-2 py-1.5 text-xs text-zinc-700">
                          <div className="font-medium">{p.name}</div>
                          <div className="text-zinc-500 tabular-nums">
                            {BRL(p.price)} · {n} ativ.
                          </div>
                        </li>
                      );
                    })}
                  </ul>
                </>
              )}

              <div className="mt-4 border-t border-zinc-200 pt-3">
                <div className="text-[11px] font-semibold uppercase tracking-wider text-zinc-400">
                  MRR filtrado
                </div>
                <div className="mt-1 font-mono text-base font-bold text-zinc-900 tabular-nums">
                  {BRL(filteredMrr)}
                </div>
                <div className="text-[11px] text-zinc-500">
                  {filtered.filter((s) => s.status !== 'cancelada').length} ativ. de {subsData.length}
                </div>
              </div>
            </aside>

            {/* COL 2 · LISTA */}
            <section className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200">
              <div className="flex items-center gap-2 border-b border-zinc-200 p-2.5">
                <div className="flex flex-1 items-center gap-2 rounded-lg bg-zinc-100 px-3 py-1.5">
                  <Search size={14} className="text-zinc-400" />
                  <input
                    ref={searchRef}
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') applyFilters({ q: search });
                    }}
                    placeholder="Buscar (/) — cliente, CNPJ, OS"
                    className="flex-1 bg-transparent text-sm outline-none placeholder:text-zinc-400"
                  />
                  <kbd className="rounded bg-white px-1.5 py-0.5 text-[10px] font-mono text-zinc-500 ring-1 ring-zinc-200">
                    /
                  </kbd>
                </div>
                <span className="text-xs text-zinc-500 tabular-nums">
                  {filtered.length} / {subsData.length}
                </span>
              </div>

              <Deferred data="subscriptions" fallback={<ListSkeleton />}>
                <div className="max-h-[calc(100vh-360px)] overflow-y-auto">
                  {filtered.length === 0 && (
                    <div className="p-8 text-center">
                      <div className="font-medium text-zinc-700">Nada por aqui.</div>
                      <div className="mt-1 text-sm text-zinc-500">
                        Nenhuma assinatura com este filtro + busca.
                      </div>
                    </div>
                  )}
                  {filtered.map((s) => {
                    const isActive = active?.id === s.id;
                    return (
                      <div
                        key={s.id}
                        onClick={() => setActiveId(s.id)}
                        className={`flex cursor-pointer items-center gap-3 border-b border-zinc-100 px-3 py-2.5 transition ${
                          isActive
                            ? 'border-l-2 border-l-violet-500 bg-violet-50/50'
                            : 'hover:bg-zinc-50'
                        }`}
                      >
                        <Avatar name={s.client} />
                        <div className="min-w-0 flex-1">
                          <div className="flex items-center gap-1.5 truncate text-sm font-semibold text-zinc-900">
                            {s.is_pinned && (
                              <Star size={11} className="shrink-0 fill-amber-500 text-amber-500" />
                            )}
                            <span className="truncate">{s.client}</span>
                          </div>
                          <div className="truncate text-xs text-zinc-500">
                            {s.plan_name} · {s.plan_cycle} · desde {daysAgoLabel(s.since) || '—'}
                          </div>
                        </div>
                        <div className="flex flex-col items-end gap-1">
                          <StatusBadge status={s.status} retry={s.retry} retryMax={s.retry_max} />
                          {s.status !== 'cancelada' && (
                            <span className="inline-flex items-center gap-1 text-xs font-medium tabular-nums text-zinc-700">
                              <MethodIcon method={s.method} size={11} />
                              {BRL(s.next_value)}
                            </span>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              </Deferred>
            </section>

            {/* COL 3 · DETAIL DRAWER */}
            <aside className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-zinc-200">
              {!active && (
                <div className="flex h-full items-center justify-center text-sm text-zinc-400">
                  Selecione uma assinatura
                </div>
              )}
              {active && <DetailDrawer sub={active} />}
            </aside>
          </div>
        )}
      </div>
    </>
  );
}

// ────────────────────────────────────────────────────────────────
// DETAIL DRAWER (coluna 3)
// ────────────────────────────────────────────────────────────────

// Onda 9 v9,75 — handlers POST pra ações executáveis do drawer.
function postAction(url: string, payload: Record<string, unknown> = {}, confirmMsg?: string) {
  if (confirmMsg && !window.confirm(confirmMsg)) return;
  router.post(url, payload, {
    preserveScroll: true,
    onSuccess: () => router.reload({ only: ['subscriptions', 'kpis'] }),
  });
}

function DetailDrawer({ sub }: { sub: SubRow }) {
  const fiscalLabels: Record<FiscalType, { label: string; long: string }> = {
    nfe: { label: 'NFe', long: 'NFe · Nota Fiscal Eletrônica' },
    nfse: { label: 'NFS-e', long: 'NFS-e · Nota Fiscal de Serviços' },
    none: { label: 'Não emite', long: 'Sem emissão de nota fiscal' },
  };
  const fiscal = fiscalLabels[sub.fiscal?.type ?? 'none'];

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-start gap-3 border-b border-zinc-100 pb-3">
        <Avatar name={sub.client} size={40} />
        <div className="min-w-0 flex-1">
          <h3 className="truncate text-base font-semibold text-zinc-900">{sub.client}</h3>
          <div className="truncate text-xs text-zinc-500">{sub.cnpj || '—'}</div>
        </div>
        <StatusBadge status={sub.status} retry={sub.retry} retryMax={sub.retry_max} />
      </div>

      {/* Card próxima cobrança */}
      {sub.status !== 'cancelada' && (
        <div className={`rounded-xl p-3 ${
          sub.status === 'falhou'
            ? 'bg-rose-50 ring-1 ring-rose-200'
            : sub.status === 'retentando'
              ? 'bg-amber-50 ring-1 ring-amber-200'
              : 'bg-violet-50 ring-1 ring-violet-200'
        }`}>
          <div className="text-[11px] font-semibold uppercase tracking-wider text-zinc-600">
            {sub.status === 'falhou' ? 'Ação manual' : 'Próxima cobrança'}
          </div>
          <div className="mt-1 flex items-end justify-between">
            <div>
              <div className="text-lg font-bold text-zinc-900">{nextLabel(sub.next_at)}</div>
              <div className="text-xs text-zinc-600">
                {nextDateBR(sub.next_at)} · ciclo {sub.plan_cycle}
              </div>
            </div>
            <div className="text-right">
              <div className="font-mono text-base font-semibold tabular-nums text-zinc-900">
                {BRL(sub.next_value)}
              </div>
              <div className="mt-0.5 inline-flex items-center gap-1 text-[11px] text-zinc-600">
                <MethodIcon method={sub.method} size={10} />
                {sub.method === 'pix' ? 'Pix' : sub.method === 'boleto' ? 'Boleto' : 'Cartão'}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* KV grid */}
      <dl className="grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
        <dt className="text-zinc-500">Plano</dt>
        <dd className="font-medium text-zinc-800">{sub.plan_name}</dd>
        <dt className="text-zinc-500">Ciclo</dt>
        <dd className="text-zinc-800">{sub.plan_cycle}</dd>
        <dt className="text-zinc-500">Desde</dt>
        <dd className="text-zinc-800">{daysAgoLabel(sub.since) || '—'}</dd>
        <dt className="text-zinc-500">Cobranças pagas</dt>
        <dd className="font-mono tabular-nums text-zinc-800">{sub.paid}</dd>
        <dt className="text-zinc-500">Falhas</dt>
        <dd className={`font-mono tabular-nums ${sub.missed > 0 ? 'text-rose-600 font-semibold' : 'text-zinc-800'}`}>
          {sub.missed}
        </dd>
        <dt className="text-zinc-500">LTV</dt>
        <dd className="font-mono tabular-nums text-zinc-800">{BRL(sub.ltv)}</dd>
        <dt className="text-zinc-500">Contato</dt>
        <dd className="truncate text-zinc-800">
          {sub.contact.name} · <span className="font-mono">{sub.contact.phone}</span>
        </dd>
        {sub.os && (
          <>
            <dt className="text-zinc-500">OS recente</dt>
            <dd className="font-mono text-zinc-800">{sub.os}</dd>
          </>
        )}
        {sub.churn_reason && (
          <>
            <dt className="text-zinc-500">Motivo cancelamento</dt>
            <dd className="text-zinc-800">{sub.churn_reason}</dd>
          </>
        )}
      </dl>

      {/* Nota pinada */}
      {sub.note && (
        <div className="rounded-lg bg-amber-50 p-3 ring-1 ring-amber-200">
          <div className="text-[10px] font-semibold uppercase tracking-wider text-amber-800">Nota pinada</div>
          <p className="mt-1 text-xs text-amber-900">{sub.note.body}</p>
        </div>
      )}

      {/* Bloco Fiscal */}
      <div className="rounded-lg border border-zinc-200 p-3">
        <div className="flex items-center justify-between">
          <div>
            <span className={`inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold ${
              sub.fiscal?.type === 'nfe'
                ? 'bg-blue-100 text-blue-700'
                : sub.fiscal?.type === 'nfse'
                  ? 'bg-emerald-100 text-emerald-700'
                  : 'bg-zinc-100 text-zinc-500'
            }`}>
              {fiscal.label}
            </span>
            <div className="mt-1 text-[10px] text-zinc-500">{fiscal.long}</div>
          </div>
          {sub.fiscal?.last_nf && (
            <button
              type="button"
              className="inline-flex items-center gap-1 rounded border border-zinc-300 px-2 py-1 text-[11px] text-zinc-600 hover:bg-zinc-50"
              title="Em breve"
            >
              <RefreshCw size={10} />
              Reenviar
            </button>
          )}
        </div>
      </div>

      {/* Ações executáveis — Onda 9 v9,75 wiring real. */}
      {sub.status !== 'cancelada' && (
        <div className="flex flex-wrap gap-2 pt-2">
          {sub.status === 'em_dia' && (
            <>
              <ActionBtn
                icon={Pause}
                label="Pausar"
                hint="P"
                onClick={() => postAction(`/recurring-billing/${sub.id}/pausar`, {}, 'Pausar esta assinatura?')}
              />
              <ActionBtn
                icon={XCircle}
                label="Cancelar"
                onClick={() => {
                  const motivo = window.prompt('Motivo do cancelamento (preço / loja fechou / inadimplência / trocou fornecedor / outro):', 'outro');
                  if (motivo) postAction(`/recurring-billing/${sub.id}/cancelar`, { churn_reason: motivo });
                }}
              />
            </>
          )}
          {(sub.status === 'retentando' || sub.status === 'falhou') && (
            <>
              <ActionBtn
                icon={Pause}
                label="Pausar"
                hint="P"
                onClick={() => postAction(`/recurring-billing/${sub.id}/pausar`, {}, 'Pausar enquanto resolve inadimplência?')}
              />
              <ActionBtn
                icon={XCircle}
                label="Cancelar"
                onClick={() => {
                  const motivo = window.prompt('Motivo do cancelamento:', 'inadimplência');
                  if (motivo) postAction(`/recurring-billing/${sub.id}/cancelar`, { churn_reason: motivo });
                }}
              />
            </>
          )}
          {sub.status === 'pausada' && (
            <ActionBtn
              icon={Play}
              label="Reativar"
              hint="R"
              primary
              onClick={() => postAction(`/recurring-billing/${sub.id}/reativar`, {}, 'Reativar esta assinatura?')}
            />
          )}
        </div>
      )}
    </div>
  );
}

function ActionBtn({ icon: Icon, label, hint, primary = false, onClick }: {
  icon: LucideIcon;
  label: string;
  hint?: string;
  primary?: boolean;
  onClick?: () => void;
}) {
  const cls = primary
    ? 'bg-violet-600 text-white hover:bg-violet-700'
    : 'bg-white text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-50';
  return (
    <button
      type="button"
      onClick={onClick}
      className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition ${cls}`}
    >
      <Icon size={12} />
      {label}
      {hint && (
        <kbd className={`rounded px-1 text-[10px] font-mono ${
          primary ? 'bg-violet-700' : 'bg-zinc-100 text-zinc-500 ring-1 ring-zinc-200'
        }`}>{hint}</kbd>
      )}
    </button>
  );
}

// ────────────────────────────────────────────────────────────────
// SKELETONS (Inertia::defer fallback)
// ────────────────────────────────────────────────────────────────

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
      {Array.from({ length: 6 }).map((_, i) => (
        <div key={i} className="flex items-center gap-3 px-3 py-2.5">
          <div className="h-7 w-7 animate-pulse rounded-full bg-zinc-200" />
          <div className="flex-1 space-y-1">
            <div className="h-3 w-32 animate-pulse rounded bg-zinc-200" />
            <div className="h-2 w-48 animate-pulse rounded bg-zinc-100" />
          </div>
          <div className="h-4 w-16 animate-pulse rounded-full bg-zinc-100" />
        </div>
      ))}
    </div>
  );
}

RecurringBillingIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
