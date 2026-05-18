// Faturas — cobrança recorrente · Onda 7 v9,75.
// Visual canon: família Cowork da Page principal RecurringBilling/Index.tsx.
// Charter: ./Index.charter.md
// Refs: ADR 0104 MWART · 0107 visual gate · 0114 Cowork loop · 0093 multi-tenant Tier 0

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import {
  AlertCircle,
  Ban,
  Banknote,
  CheckCircle2,
  Clock,
  CreditCard,
  Plus,
  Search,
  TrendingUp,
  Wallet,
  type LucideIcon,
} from 'lucide-react';
import {
  useEffect,
  useRef,
  useState,
  type ReactNode,
} from 'react';

// ────────────────────────────────────────────────────────────────
// TIPOS — espelham InvoiceController@index payload
// ────────────────────────────────────────────────────────────────

type InvoiceStatus = 'open' | 'paid' | 'overdue' | 'canceled' | 'refunded';
type Gateway = 'inter' | 'c6' | 'asaas';
type StatusFilter = 'all' | InvoiceStatus;
type GatewayFilter = 'all' | Gateway;
type PeriodoFilter = 'all' | 'mes_atual' | 'proximo_mes' | 'atrasados';

interface Filters {
  status: string;
  gateway: string;
  periodo: string;
  busca: string;
}

interface Kpis {
  total_pago_mes: number;
  total_pendente: number;
  total_atrasado: number;
  count_overdue: number;
  total_faturas: number;
}

interface InvoiceRow {
  id: number;
  numero_documento: string | null;
  cliente_nome: string;
  cliente_cnpj: string | null;
  subscription_id: number | null;
  plano_nome: string | null;
  valor: number;
  vencimento: string | null;
  dias_delta_venc: number | null; // positivo = futuro · negativo = atrasado · 0 = hoje
  is_overdue: boolean;
  pago_em: string | null;
  status: InvoiceStatus;
  gateway: Gateway | null;
  gateway_ref: string | null;
  is_cancelavel: boolean;
}

interface InvoicesPaginated {
  data: InvoiceRow[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}

interface PageProps {
  filters: Filters;
  kpis?: Kpis;
  invoices?: InvoicesPaginated;
}

// ────────────────────────────────────────────────────────────────
// HELPERS
// ────────────────────────────────────────────────────────────────

const BRL = (n: number) =>
  n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function dateBR(iso: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (isNaN(d.getTime())) return iso;
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: '2-digit' });
}

function dueLabel(deltaDias: number | null): string {
  if (deltaDias === null) return '—';
  if (deltaDias === 0) return 'hoje';
  if (deltaDias === 1) return 'amanhã';
  if (deltaDias === -1) return 'ontem';
  if (deltaDias > 0) return `em ${deltaDias}d`;
  return `há ${Math.abs(deltaDias)}d`;
}

const STATUS_STYLES: Record<InvoiceStatus, { label: string; classes: string }> = {
  open: {
    label: 'pendente',
    classes: 'bg-amber-50 text-amber-700 ring-amber-200',
  },
  paid: {
    label: 'paga',
    classes: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  },
  overdue: {
    label: 'atrasada',
    classes: 'bg-rose-50 text-rose-700 ring-rose-200',
  },
  canceled: {
    label: 'cancelada',
    classes: 'bg-zinc-100 text-zinc-400 ring-zinc-200 line-through',
  },
  refunded: {
    label: 'reembolsada',
    classes: 'bg-zinc-100 text-zinc-600 ring-zinc-200',
  },
};

const GATEWAY_STYLES: Record<Gateway, { label: string; classes: string }> = {
  inter: { label: 'Inter', classes: 'bg-orange-50 text-orange-700 ring-orange-200' },
  c6: { label: 'C6', classes: 'bg-zinc-900 text-white ring-zinc-700' },
  asaas: { label: 'Asaas', classes: 'bg-sky-50 text-sky-700 ring-sky-200' },
};

const STATUS_PILLS: Array<{ key: StatusFilter; label: string; dot: string }> = [
  { key: 'all', label: 'Todas', dot: 'bg-zinc-300' },
  { key: 'paid', label: 'Pagas', dot: 'bg-emerald-500' },
  { key: 'open', label: 'Pendentes', dot: 'bg-amber-500' },
  { key: 'overdue', label: 'Atrasadas', dot: 'bg-rose-500' },
  { key: 'canceled', label: 'Canceladas', dot: 'bg-zinc-400' },
];

const GATEWAY_OPTIONS: Array<{ key: GatewayFilter; label: string }> = [
  { key: 'all', label: 'Todos gateways' },
  { key: 'inter', label: 'Inter' },
  { key: 'c6', label: 'C6' },
  { key: 'asaas', label: 'Asaas' },
];

const PERIODO_OPTIONS: Array<{ key: PeriodoFilter; label: string }> = [
  { key: 'all', label: 'Qualquer período' },
  { key: 'mes_atual', label: 'Mês atual' },
  { key: 'proximo_mes', label: 'Próximo mês' },
  { key: 'atrasados', label: 'Apenas atrasadas' },
];

// ────────────────────────────────────────────────────────────────
// SUB-COMPONENTES
// ────────────────────────────────────────────────────────────────

function StatusBadge({ status }: { status: InvoiceStatus }) {
  const s = STATUS_STYLES[status];
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ${s.classes}`}>
      {s.label}
    </span>
  );
}

function GatewayBadge({ gateway }: { gateway: Gateway | null }) {
  if (!gateway) {
    return (
      <span className="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium text-zinc-400 ring-1 ring-zinc-200">
        —
      </span>
    );
  }
  const g = GATEWAY_STYLES[gateway];
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ${g.classes}`}>
      {g.label}
    </span>
  );
}

function KpiCard({
  label,
  value,
  delta,
  icon: Icon,
  tone = 'neutral',
  hero = false,
}: {
  label: string;
  value: ReactNode;
  delta?: string;
  icon?: LucideIcon;
  tone?: 'ok' | 'warn' | 'bad' | 'neutral';
  hero?: boolean;
}) {
  const heroTone = {
    ok: 'bg-emerald-700',
    warn: 'bg-amber-700',
    bad: 'bg-rose-700',
    neutral: 'bg-zinc-900',
  }[tone];
  const lightDelta = {
    ok: 'text-emerald-700',
    warn: 'text-amber-700',
    bad: 'text-rose-700',
    neutral: 'text-zinc-500',
  }[tone];

  if (hero) {
    return (
      <div className={`rounded-2xl ${heroTone} p-4 text-white shadow-sm ring-1 ring-zinc-800`}>
        <div className="flex items-center justify-between text-[11px] font-medium uppercase tracking-wider text-white/70">
          <span>{label}</span>
          {Icon ? <Icon size={14} className="text-white/80" /> : <TrendingUp size={14} className="text-white/80" />}
        </div>
        <div className="mt-2 text-2xl font-bold tabular-nums">{value}</div>
        {delta && <div className="mt-1 text-xs font-medium text-white/80">{delta}</div>}
      </div>
    );
  }
  return (
    <div className="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-zinc-200">
      <div className="flex items-center justify-between text-[11px] font-medium uppercase tracking-wider text-zinc-500">
        <span>{label}</span>
        {Icon && <Icon size={14} className="text-zinc-400" />}
      </div>
      <div className="mt-2 text-2xl font-bold text-zinc-900 tabular-nums">{value}</div>
      {delta && <div className={`mt-1 text-xs font-medium ${lightDelta}`}>{delta}</div>}
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

function TableSkeleton() {
  return (
    <div className="divide-y divide-zinc-100">
      {Array.from({ length: 8 }).map((_, i) => (
        <div key={i} className="flex items-center gap-3 px-4 py-3">
          <div className="h-4 w-24 animate-pulse rounded bg-zinc-100" />
          <div className="flex-1 space-y-1">
            <div className="h-3 w-48 animate-pulse rounded bg-zinc-200" />
            <div className="h-2 w-32 animate-pulse rounded bg-zinc-100" />
          </div>
          <div className="h-4 w-20 animate-pulse rounded bg-zinc-100" />
          <div className="h-4 w-16 animate-pulse rounded-full bg-zinc-100" />
        </div>
      ))}
    </div>
  );
}

// ────────────────────────────────────────────────────────────────
// CANCEL DIALOG (confirm)
// ────────────────────────────────────────────────────────────────

function CancelDialog({
  invoice,
  onClose,
  onConfirm,
  busy,
}: {
  invoice: InvoiceRow;
  onClose: () => void;
  onConfirm: (motivo: string) => void;
  busy: boolean;
}) {
  const [motivo, setMotivo] = useState('ACERTOS');

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/50 p-4" onClick={onClose}>
      <div
        className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-zinc-200"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-start gap-3">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700">
            <AlertCircle size={20} />
          </div>
          <div className="min-w-0 flex-1">
            <h3 className="text-base font-semibold text-zinc-900">Cancelar fatura</h3>
            <p className="mt-1 text-sm text-zinc-600">
              Você vai cancelar a fatura{' '}
              <span className="font-mono font-semibold text-zinc-900">
                {invoice.numero_documento || `#${invoice.id}`}
              </span>{' '}
              de <strong>{invoice.cliente_nome}</strong> ({BRL(invoice.valor)}).
            </p>
            <p className="mt-2 text-xs text-zinc-500">
              Se a fatura está num gateway (Inter/C6/Asaas), o cancelamento é propagado.
              Ação registrada em audit log (LGPD).
            </p>
          </div>
        </div>

        <label className="mt-4 block text-xs font-medium text-zinc-700">
          Motivo (opcional)
          <input
            type="text"
            value={motivo}
            onChange={(e) => setMotivo(e.target.value)}
            placeholder="Ex: ACERTOS, duplicidade, solicitação cliente"
            className="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-rose-400 focus:ring-2 focus:ring-rose-100"
            disabled={busy}
          />
        </label>

        <div className="mt-5 flex items-center justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            disabled={busy}
            className="rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 disabled:opacity-50"
          >
            Voltar
          </button>
          <button
            type="button"
            onClick={() => onConfirm(motivo)}
            disabled={busy}
            className="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-rose-700 disabled:opacity-50"
          >
            <Ban size={14} />
            {busy ? 'Cancelando…' : 'Confirmar cancelamento'}
          </button>
        </div>
      </div>
    </div>
  );
}

// ────────────────────────────────────────────────────────────────
// PAGE
// ────────────────────────────────────────────────────────────────

export default function FaturasIndex(props: PageProps) {
  const { filters, kpis, invoices } = props;

  const [statusFilter, setStatusFilter] = useState<string>(filters.status || 'all');
  const [gatewayFilter, setGatewayFilter] = useState<string>(filters.gateway || 'all');
  const [periodoFilter, setPeriodoFilter] = useState<string>(filters.periodo || 'all');
  const [search, setSearch] = useState<string>(filters.busca || '');
  const [cancelTarget, setCancelTarget] = useState<InvoiceRow | null>(null);
  const [cancelBusy, setCancelBusy] = useState(false);
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

  function applyFilters(next: Partial<{ status: string; gateway: string; periodo: string; q: string }>) {
    router.reload({
      data: {
        status: next.status ?? statusFilter,
        gateway: next.gateway ?? gatewayFilter,
        periodo: next.periodo ?? periodoFilter,
        q: next.q ?? search,
      },
      only: ['invoices', 'kpis'],
    });
  }

  function handleCancel(motivo: string) {
    if (!cancelTarget) return;
    setCancelBusy(true);
    router.post(
      route('rb-invoices.cancel', cancelTarget.id),
      { motivo },
      {
        preserveScroll: true,
        onFinish: () => {
          setCancelBusy(false);
          setCancelTarget(null);
          // Recarrega kpis + invoices pra refletir mudança
          router.reload({ only: ['invoices', 'kpis'] });
        },
      },
    );
  }

  const rows = invoices?.data || [];

  return (
    <>
      <Head title="Faturas · cobrança recorrente" />

      <div className="min-h-screen bg-zinc-50 p-4 md:p-6">
        {/* ── HEADER ── */}
        <header className="mb-4">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
              <h1 className="text-2xl font-bold tracking-tight text-zinc-900">
                Faturas · cobrança recorrente
              </h1>
              <div className="mt-1 font-mono text-[11px] uppercase tracking-wider text-zinc-500">
                {kpis ? (
                  <>
                    {kpis.total_faturas} FATURAS · ATRASADAS {kpis.count_overdue}
                  </>
                ) : (
                  'carregando…'
                )}
              </div>
            </div>
            <div className="flex items-center gap-2">
              <button
                type="button"
                disabled
                className="inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg bg-zinc-200 px-3 py-2 text-sm font-medium text-zinc-500 shadow-sm"
                title="Nova fatura — em breve"
              >
                <Plus size={14} />
                Nova fatura
                <span className="ml-1 rounded bg-zinc-300 px-1.5 py-0.5 text-[9px] uppercase tracking-wider">
                  em breve
                </span>
              </button>
            </div>
          </div>
        </header>

        {/* ── 4 KPI CARDS ── */}
        <Deferred data="kpis" fallback={<KpiSkeleton />}>
          {kpis && (
            <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <KpiCard
                label="Pago este mês"
                value={BRL(kpis.total_pago_mes)}
                delta="faturas liquidadas no mês corrente"
                icon={CheckCircle2}
                tone="ok"
                hero
              />
              <KpiCard
                label="Pendente"
                value={BRL(kpis.total_pendente)}
                delta="abertas com vencimento futuro"
                icon={Clock}
                tone="neutral"
              />
              <KpiCard
                label="Atrasado"
                value={BRL(kpis.total_atrasado)}
                delta={`${kpis.count_overdue} ${kpis.count_overdue === 1 ? 'fatura' : 'faturas'} vencidas`}
                icon={AlertCircle}
                tone="bad"
              />
              <KpiCard
                label="Total de faturas"
                value={kpis.total_faturas.toLocaleString('pt-BR')}
                delta="histórico completo"
                icon={Wallet}
                tone="neutral"
              />
            </div>
          )}
        </Deferred>

        {/* ── FILTER BAR ── */}
        <div className="mb-4 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-zinc-200">
          <div className="flex flex-wrap items-center gap-2">
            {/* Status pills */}
            <div className="flex items-center gap-1 rounded-lg bg-zinc-100 p-1">
              {STATUS_PILLS.map((p) => (
                <button
                  key={p.key}
                  type="button"
                  onClick={() => {
                    setStatusFilter(p.key);
                    applyFilters({ status: p.key });
                  }}
                  className={`flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition ${
                    statusFilter === p.key
                      ? 'bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-200'
                      : 'text-zinc-600 hover:text-zinc-900'
                  }`}
                >
                  <span className={`h-1.5 w-1.5 rounded-full ${p.dot}`} />
                  {p.label}
                </button>
              ))}
            </div>

            {/* Gateway dropdown */}
            <select
              value={gatewayFilter}
              onChange={(e) => {
                setGatewayFilter(e.target.value);
                applyFilters({ gateway: e.target.value });
              }}
              className="rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 outline-none focus:border-violet-300 focus:ring-2 focus:ring-violet-100"
            >
              {GATEWAY_OPTIONS.map((g) => (
                <option key={g.key} value={g.key}>
                  {g.label}
                </option>
              ))}
            </select>

            {/* Periodo dropdown */}
            <select
              value={periodoFilter}
              onChange={(e) => {
                setPeriodoFilter(e.target.value);
                applyFilters({ periodo: e.target.value });
              }}
              className="rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 outline-none focus:border-violet-300 focus:ring-2 focus:ring-violet-100"
            >
              {PERIODO_OPTIONS.map((p) => (
                <option key={p.key} value={p.key}>
                  {p.label}
                </option>
              ))}
            </select>

            {/* Search */}
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
                placeholder="Buscar (/) — cliente, CNPJ, número da fatura"
                className="flex-1 bg-transparent text-sm outline-none placeholder:text-zinc-400"
              />
              <kbd className="rounded bg-white px-1.5 py-0.5 text-[10px] font-mono text-zinc-500 ring-1 ring-zinc-200">
                /
              </kbd>
            </div>

            {invoices?.meta && (
              <span className="text-xs text-zinc-500 tabular-nums">
                {rows.length} / {invoices.meta.total}
              </span>
            )}
          </div>
        </div>

        {/* ── TABELA ── */}
        <div className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200">
          <Deferred data="invoices" fallback={<TableSkeleton />}>
            {rows.length === 0 ? (
              <div className="flex flex-col items-center justify-center p-12 text-center">
                <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-400">
                  <Banknote size={22} />
                </div>
                <div className="font-medium text-zinc-700">Nenhuma fatura encontrada.</div>
                <div className="mt-1 text-sm text-zinc-500">
                  Ajuste os filtros ou crie uma nova fatura.
                </div>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-zinc-200 bg-zinc-50 text-[11px] uppercase tracking-wider text-zinc-500">
                    <tr>
                      <th className="px-4 py-2.5 text-left font-medium">Número</th>
                      <th className="px-4 py-2.5 text-left font-medium">Cliente</th>
                      <th className="px-4 py-2.5 text-left font-medium">Plano</th>
                      <th className="px-4 py-2.5 text-right font-medium">Valor</th>
                      <th className="px-4 py-2.5 text-left font-medium">Vencimento</th>
                      <th className="px-4 py-2.5 text-left font-medium">Status</th>
                      <th className="px-4 py-2.5 text-left font-medium">Gateway</th>
                      <th className="px-4 py-2.5 text-right font-medium">Ações</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-zinc-100">
                    {rows.map((inv) => (
                      <tr key={inv.id} className="hover:bg-zinc-50">
                        <td className="px-4 py-3 font-mono text-xs text-zinc-700">
                          {inv.numero_documento || (
                            <span className="text-zinc-400">#{inv.id}</span>
                          )}
                        </td>
                        <td className="max-w-[220px] px-4 py-3">
                          <div className="truncate font-medium text-zinc-900">{inv.cliente_nome}</div>
                          {inv.cliente_cnpj && (
                            <div className="truncate font-mono text-[11px] text-zinc-500">
                              {inv.cliente_cnpj}
                            </div>
                          )}
                        </td>
                        <td className="max-w-[180px] truncate px-4 py-3 text-xs text-zinc-600">
                          {inv.plano_nome || <span className="italic text-zinc-400">avulsa</span>}
                        </td>
                        <td className="px-4 py-3 text-right font-mono font-semibold tabular-nums text-zinc-900">
                          {BRL(inv.valor)}
                        </td>
                        <td className="px-4 py-3 text-xs text-zinc-700">
                          <div>{dateBR(inv.vencimento)}</div>
                          <div
                            className={`mt-0.5 text-[11px] ${
                              inv.is_overdue ? 'font-medium text-rose-600' : 'text-zinc-500'
                            }`}
                          >
                            {dueLabel(inv.dias_delta_venc)}
                          </div>
                        </td>
                        <td className="px-4 py-3">
                          <StatusBadge status={inv.status} />
                        </td>
                        <td className="px-4 py-3">
                          <GatewayBadge gateway={inv.gateway} />
                        </td>
                        <td className="px-4 py-3 text-right">
                          {inv.is_cancelavel && (
                            <button
                              type="button"
                              onClick={() => setCancelTarget(inv)}
                              className="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-white px-2.5 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50"
                              title="Cancelar fatura"
                            >
                              <Ban size={12} />
                              Cancelar
                            </button>
                          )}
                          {!inv.is_cancelavel && (
                            <span className="text-[11px] italic text-zinc-400">—</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Deferred>

          {/* Pagination footer */}
          {invoices?.meta && invoices.meta.last_page > 1 && (
            <div className="flex items-center justify-between border-t border-zinc-200 bg-zinc-50/50 px-4 py-2.5 text-xs text-zinc-600">
              <span>
                Página <strong className="tabular-nums">{invoices.meta.current_page}</strong> de{' '}
                <strong className="tabular-nums">{invoices.meta.last_page}</strong> ·{' '}
                {invoices.meta.total} faturas
              </span>
              <div className="flex items-center gap-1">
                <button
                  type="button"
                  disabled={invoices.meta.current_page <= 1}
                  onClick={() =>
                    router.reload({
                      data: { page: invoices.meta.current_page - 1 },
                      only: ['invoices'],
                    })
                  }
                  className="rounded px-2 py-1 hover:bg-zinc-200 disabled:opacity-30"
                >
                  ← Anterior
                </button>
                <button
                  type="button"
                  disabled={invoices.meta.current_page >= invoices.meta.last_page}
                  onClick={() =>
                    router.reload({
                      data: { page: invoices.meta.current_page + 1 },
                      only: ['invoices'],
                    })
                  }
                  className="rounded px-2 py-1 hover:bg-zinc-200 disabled:opacity-30"
                >
                  Próxima →
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Footer note */}
        <div className="mt-3 text-[11px] italic text-zinc-400">
          Cancelar dispara <code className="font-mono">POST /financeiro/rb-invoices/&#123;id&#125;/cancelar</code>{' '}
          (US-RB-042). Cancelamento propagado ao gateway + audit log Spatie.
          <CreditCard size={11} className="ml-1 inline align-text-bottom text-zinc-400" />
        </div>
      </div>

      {/* Cancel dialog */}
      {cancelTarget && (
        <CancelDialog
          invoice={cancelTarget}
          onClose={() => !cancelBusy && setCancelTarget(null)}
          onConfirm={handleCancel}
          busy={cancelBusy}
        />
      )}
    </>
  );
}

FaturasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
