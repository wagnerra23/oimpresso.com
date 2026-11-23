// Faturas — cobrança recorrente · Onda 7 v9,75.
// Visual canon: família Cowork da Page principal RecurringBilling/Index.tsx.
// Charter: ./Index.charter.md
// Refs: ADR 0104 MWART · 0107 visual gate · 0114 Cowork loop · 0093 multi-tenant Tier 0

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  AlertCircle,
  Ban,
  Banknote,
  CheckCircle2,
  Clock,
  CreditCard,
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
// Onda 22 v9,75 — refinos Cowork na Page Faturas
import Sparkline from '../_components/Sparkline';
import CheatSheet from '../_components/CheatSheet';
import TourOnboarding, { TOUR_DONE_KEY } from '../_components/TourOnboarding';
import { printSubDetail, installPrintStyles } from '../_components/printExtractStyles';

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

// Pílula de status com borda de 1px (protótipo `cr-pill`, vista rb-faturas). Cor pelo sentido
// da fatura: pendente é normal (a vencer) e fica no tom da marca; só atrasada pede atenção.
const STATUS_STYLES: Record<InvoiceStatus, { label: string; classes: string }> = {
  open: {
    label: 'Pendente',
    classes: 'border-primary/25 bg-primary/10 text-primary',
  },
  paid: {
    label: 'Paga',
    classes: 'border-success/30 bg-success-soft text-success-fg',
  },
  overdue: {
    label: 'Atrasada',
    classes: 'border-destructive/30 bg-destructive-soft text-destructive-fg',
  },
  canceled: {
    label: 'Cancelada',
    classes: 'border-border bg-muted text-muted-foreground line-through',
  },
  refunded: {
    label: 'Reembolsada',
    classes: 'border-border bg-muted text-muted-foreground',
  },
};

const GATEWAY_LABEL: Record<Gateway, string> = {
  inter: 'Banco Inter',
  c6: 'C6 Bank',
  asaas: 'Asaas',
};

// Só os status que `rb_invoices.status` tem. O protótipo mostra também Parciais, Estornadas e
// Chargeback: o modelo não tem esses estados, então o filtro não os oferece.
const STATUS_PILLS: Array<{ key: StatusFilter; label: string }> = [
  { key: 'all', label: 'Todas' },
  { key: 'paid', label: 'Pagas' },
  { key: 'open', label: 'Pendentes' },
  { key: 'overdue', label: 'Atrasadas' },
  { key: 'refunded', label: 'Reembolsadas' },
  { key: 'canceled', label: 'Canceladas' },
];

const GATEWAY_OPTIONS: Array<{ key: GatewayFilter; label: string }> = [
  { key: 'all', label: 'Todos gateways' },
  { key: 'inter', label: 'Banco Inter' },
  { key: 'c6', label: 'C6 Bank' },
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
    <span className={`inline-flex items-center whitespace-nowrap rounded-full border px-2 py-0.5 text-[11px] font-medium ${s.classes}`}>
      {s.label}
    </span>
  );
}

// Gateway como etiqueta de texto (protótipo `cr-fiscal-badge none`): neutra, sem cor por banco.
function GatewayBadge({ gateway }: { gateway: Gateway | null }) {
  if (!gateway) return <span className="text-muted-foreground">—</span>;
  return (
    <span className="inline-flex rounded-[5px] bg-muted px-[7px] py-0.5 text-[10px] font-semibold text-muted-foreground">
      {GATEWAY_LABEL[gateway]}
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
  sparkline,
}: {
  label: string;
  value: ReactNode;
  delta?: string;
  icon?: LucideIcon;
  tone?: 'ok' | 'warn' | 'bad' | 'neutral';
  hero?: boolean;
  sparkline?: number[];
}) {
  const heroTone = {
    ok: 'bg-emerald-700',
    warn: 'bg-amber-700',
    bad: 'bg-rose-700',
    neutral: 'bg-stone-900',
  }[tone];
  const lightDelta = {
    ok: 'text-emerald-700',
    warn: 'text-amber-700',
    bad: 'text-rose-700',
    neutral: 'text-muted-foreground',
  }[tone];

  if (hero) {
    return (
      <div className={`rounded-lg ${heroTone} p-4 text-white shadow-sm ring-1 ring-stone-800`}>
        <div className="flex items-center justify-between text-[11px] font-medium uppercase tracking-wider text-white/70">
          <span>{label}</span>
          {sparkline && sparkline.length > 0 ? (
            <Sparkline points={sparkline} color="oklch(0.85 0.05 145)" />
          ) : Icon ? (
            <Icon size={14} className="text-white/80" />
          ) : (
            <TrendingUp size={14} className="text-white/80" />
          )}
        </div>
        <div className="mt-2 text-2xl font-bold tabular-nums">{value}</div>
        {delta && <div className="mt-1 text-xs font-medium text-white/80">{delta}</div>}
      </div>
    );
  }
  return (
    <div className="rounded-lg bg-card p-4 shadow-sm ring-1 ring-border">
      <div className="flex items-center justify-between text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
        <span>{label}</span>
        {Icon && <Icon size={14} className="text-muted-foreground" />}
      </div>
      <div className="mt-2 text-2xl font-bold text-foreground tabular-nums">{value}</div>
      {delta && <div className={`mt-1 text-xs font-medium ${lightDelta}`}>{delta}</div>}
    </div>
  );
}

function KpiSkeleton() {
  return (
    <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
      {Array.from({ length: 4 }).map((_, i) => (
        <div key={i} className="h-24 animate-pulse rounded-lg bg-muted" />
      ))}
    </div>
  );
}

function TableSkeleton() {
  return (
    <div className="divide-y divide-border">
      {Array.from({ length: 8 }).map((_, i) => (
        <div key={i} className="flex items-center gap-3 px-4 py-3">
          <div className="h-4 w-24 animate-pulse rounded bg-muted" />
          <div className="flex-1 space-y-1">
            <div className="h-3 w-48 animate-pulse rounded bg-muted" />
            <div className="h-2 w-32 animate-pulse rounded bg-muted" />
          </div>
          <div className="h-4 w-20 animate-pulse rounded bg-muted" />
          <div className="h-4 w-16 animate-pulse rounded-full bg-muted" />
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
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/50 p-4" onClick={onClose}>
      <div
        className="w-full max-w-md rounded-lg bg-card p-6 shadow-2xl ring-1 ring-border"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-start gap-3">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-destructive-soft text-destructive-fg">
            <AlertCircle size={20} />
          </div>
          <div className="min-w-0 flex-1">
            <h3 className="text-base font-semibold text-foreground">Cancelar fatura</h3>
            <p className="mt-1 text-sm text-foreground">
              Você vai cancelar a fatura{' '}
              <span className="font-mono font-semibold text-foreground">
                {invoice.numero_documento || `#${invoice.id}`}
              </span>{' '}
              de <strong>{invoice.cliente_nome}</strong> ({BRL(invoice.valor)}).
            </p>
            <p className="mt-2 text-xs text-muted-foreground">
              Se a fatura está num gateway (Inter/C6/Asaas), o cancelamento é propagado.
              Ação registrada em audit log (LGPD).
            </p>
          </div>
        </div>

        <label className="mt-4 block text-xs font-medium text-foreground">
          Motivo (opcional)
          <input
            type="text"
            value={motivo}
            onChange={(e) => setMotivo(e.target.value)}
            placeholder="Ex: ACERTOS, duplicidade, solicitação cliente"
            className="mt-1 w-full rounded-lg border border-border px-3 py-2 text-sm outline-none focus:border-rose-400 focus:ring-2 focus:ring-rose-100"
            disabled={busy}
          />
        </label>

        <div className="mt-5 flex items-center justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            disabled={busy}
            className="rounded-lg px-3 py-2 text-sm font-medium text-foreground hover:bg-muted disabled:opacity-50"
          >
            Voltar
          </button>
          <button
            type="button"
            onClick={() => onConfirm(motivo)}
            disabled={busy}
            className="inline-flex items-center gap-1.5 rounded-lg bg-destructive px-3 py-2 text-sm font-medium text-destructive-foreground shadow-sm hover:bg-destructive/90 disabled:opacity-50"
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
  // Onda 22 v9,75 — overlays Cowork
  const [showCheatsheet, setShowCheatsheet] = useState(false);
  const [showTour, setShowTour] = useState(false);
  const searchRef = useRef<HTMLInputElement>(null);

  // Print styles + Tour 1ª vez
  useEffect(() => {
    try {
      installPrintStyles();
      if (localStorage.getItem(TOUR_DONE_KEY) !== '1') setShowTour(true);
    } catch {
      // private mode etc
    }
  }, []);

  // Atalhos teclado — Onda 18+22 v9,75
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
        setShowCheatsheet(false);
      } else if (e.key === '?') {
        e.preventDefault();
        setShowCheatsheet(true);
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

      <div className="min-h-screen bg-muted p-4 md:p-6">
        {/* ── HEADER ── */}
        <header className="mb-4" data-contract="cabecalho">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                Faturas · cobrança recorrente
              </h1>
              <div className="mt-1 font-mono text-[11px] uppercase tracking-wider text-muted-foreground">
                {kpis ? (
                  <>
                    {kpis.total_faturas} FATURAS · ATRASADAS {kpis.count_overdue}
                  </>
                ) : (
                  'carregando…'
                )}
              </div>
            </div>
          </div>
        </header>

        {/* ── 4 KPI CARDS ── */}
        <Deferred data="kpis" fallback={<KpiSkeleton />}>
          {kpis && (
            <div data-contract="kpis" className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <KpiCard
                label="Pago este mês"
                value={BRL(kpis.total_pago_mes)}
                delta="faturas liquidadas no mês corrente"
                icon={CheckCircle2}
                tone="ok"
                hero
                sparkline={(() => {
                  // Onda 22 v9,75 — sparkline mock derivada do total_pago_mes (12 pontos crescente).
                  const v = kpis.total_pago_mes || 0;
                  if (v === 0) return [];
                  return [0.65, 0.72, 0.78, 0.81, 0.85, 0.88, 0.91, 0.93, 0.95, 0.97, 0.99, 1].map((r) => v * r);
                })()}
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
        <div data-contract="filtros" className="mb-4 rounded-lg bg-card p-3 shadow-sm ring-1 ring-border">
          <div className="flex flex-wrap items-center gap-2">
            {/* Status — chips com borda (protótipo `CHIP`), na mesma linha do gateway e do período */}
            <div className="flex flex-wrap gap-1">
              {STATUS_PILLS.map((p) => {
                const on = statusFilter === p.key;
                return (
                  <button
                    key={p.key}
                    type="button"
                    aria-pressed={on}
                    onClick={() => {
                      setStatusFilter(p.key);
                      applyFilters({ status: p.key });
                    }}
                    className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-[5px] text-xs font-medium transition ${
                      on
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-border bg-card text-muted-foreground hover:text-foreground'
                    }`}
                  >
                    {p.label}
                  </button>
                );
              })}
            </div>

            {/* Gateway dropdown */}
            <Select
              value={gatewayFilter}
              onValueChange={(v) => {
                setGatewayFilter(v as GatewayFilter);
                applyFilters({ gateway: v });
              }}
            >
              <SelectTrigger className="h-8 w-[150px] text-xs font-medium" aria-label="Gateway">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {GATEWAY_OPTIONS.map((g) => (
                  <SelectItem key={g.key} value={g.key}>{g.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>

            {/* Periodo dropdown */}
            <Select
              value={periodoFilter}
              onValueChange={(v) => {
                setPeriodoFilter(v as PeriodoFilter);
                applyFilters({ periodo: v });
              }}
            >
              <SelectTrigger className="h-8 w-[160px] text-xs font-medium" aria-label="Período">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {PERIODO_OPTIONS.map((p) => (
                  <SelectItem key={p.key} value={p.key}>{p.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>

          </div>
        </div>

        {/* ── TABELA ── */}
        <div data-contract="lista" className="overflow-hidden rounded-lg bg-card shadow-sm ring-1 ring-border">
          {/* Cabeçalho da lista: busca e contagem (protótipo `cr-list-head`) */}
          <div className="flex items-center gap-2.5 border-b border-border px-3 py-[9px]">
            <div className="flex flex-1 items-center gap-2 rounded-[7px] bg-muted px-2.5 py-[7px]">
              <Search size={14} className="text-muted-foreground" />
              <input
                ref={searchRef}
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') applyFilters({ q: search });
                }}
                placeholder="Buscar (/) — cliente, CNPJ, número da fatura"
                aria-label="Buscar faturas"
                className="flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted-foreground"
              />
              <kbd className="rounded border border-border bg-card px-[5px] py-px font-mono text-[10px] text-muted-foreground">
                /
              </kbd>
            </div>
            {invoices?.meta && (
              <span className="text-xs tabular-nums text-muted-foreground">
                {rows.length} / {invoices.meta.total}
              </span>
            )}
          </div>
          <Deferred data="invoices" fallback={<TableSkeleton />}>
            {rows.length === 0 ? (
              <div className="flex flex-col items-center justify-center p-12 text-center">
                <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                  <Banknote size={22} />
                </div>
                <div className="font-medium text-foreground">Nenhuma fatura encontrada.</div>
                <div className="mt-1 text-sm text-muted-foreground">
                  Ajuste os filtros ou a busca.
                </div>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border bg-muted text-[11px] uppercase tracking-wider text-muted-foreground">
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
                  <tbody className="divide-y divide-border">
                    {rows.map((inv) => (
                      <tr key={inv.id} className="hover:bg-muted">
                        <td className="px-4 py-3 font-mono text-xs text-foreground">
                          {inv.numero_documento || (
                            <span className="text-muted-foreground">#{inv.id}</span>
                          )}
                        </td>
                        <td className="max-w-[220px] px-4 py-3">
                          <div className="truncate font-medium text-foreground">{inv.cliente_nome}</div>
                          {inv.cliente_cnpj && (
                            <div className="truncate font-mono text-[11px] text-muted-foreground">
                              {inv.cliente_cnpj}
                            </div>
                          )}
                        </td>
                        <td className="max-w-[180px] truncate px-4 py-3 text-xs text-foreground">
                          {inv.plano_nome || <span className="italic text-muted-foreground">avulsa</span>}
                        </td>
                        <td className="px-4 py-3 text-right font-mono font-semibold tabular-nums text-foreground">
                          {BRL(inv.valor)}
                        </td>
                        <td className="px-4 py-3 text-xs text-foreground">
                          <div>{dateBR(inv.vencimento)}</div>
                          <div
                            className={`mt-0.5 text-[11px] ${
                              inv.is_overdue ? 'font-medium text-destructive-fg' : 'text-muted-foreground'
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
                              className="inline-flex items-center gap-1 rounded-lg border border-destructive/20 bg-card px-2.5 py-1 text-xs font-medium text-destructive-fg hover:bg-destructive-soft"
                              title="Cancelar fatura"
                            >
                              <Ban size={12} />
                              Cancelar
                            </button>
                          )}
                          {!inv.is_cancelavel && (
                            <span className="text-[11px] italic text-muted-foreground">—</span>
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
            <div className="flex items-center justify-between border-t border-border bg-muted/50 px-4 py-2.5 text-xs text-foreground">
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
                  className="rounded px-2 py-1 hover:bg-muted disabled:opacity-30"
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
                  className="rounded px-2 py-1 hover:bg-muted disabled:opacity-30"
                >
                  Próxima →
                </button>
              </div>
            </div>
          )}
        </div>

        {/* Footer note */}
        <div className="mt-3 text-[11px] italic text-muted-foreground">
          Cancelar dispara <code className="font-mono">POST /financeiro/rb-invoices/&#123;id&#125;/cancelar</code>{' '}
          (US-RB-042). Cancelamento propagado ao gateway + audit log Spatie.
          <CreditCard size={11} className="ml-1 inline align-text-bottom text-muted-foreground" />
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

      {/* Onda 22 v9,75 — Tour onboarding 1ª vez + CheatSheet (?) */}
      {showTour && <TourOnboarding onClose={() => setShowTour(false)} />}
      {showCheatsheet && <CheatSheet onClose={() => setShowCheatsheet(false)} />}
    </>
  );
}

FaturasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
