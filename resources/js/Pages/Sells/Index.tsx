// Sells/Index — cópia visual KB-9.75 (prototype Cowork chat10 2026-05-16, score 9.75/10).
// Refs:
//  - prototipo-ui/prototipos/sells-index/vendas-page.jsx (canonical visual-source)
//  - memory/requisitos/Sells/index-r1-visual-comparison.md (15 dimensões + plug-points)
//  - memory/reference/feedback-design-literal-copy-quando-aprovado.md
//  - ADR 0104 MWART · 0107 visual gate · 0114 Cowork loop · 0143 FSM live biz=1
// Backup do legacy: Index.tsx.legacy-bkp-cowork

import AppShellV2 from '@/Layouts/AppShellV2';
import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type CSSProperties,
  type ReactNode,
} from 'react';
import { usePage } from '@inertiajs/react';
import {
  Archive,
  CheckCircle2,
  ChevronDown,
  DollarSign,
  FileText,
  Folder,
  Plus,
  Printer,
  Search,
  SlidersHorizontal,
  X,
} from 'lucide-react';
import SaleSheet from './_components/SaleSheet';
import QuickPaymentDialog from './_components/QuickPaymentDialog';
// PR follow-up Cowork — filtros legacy reintegrados via barra colapsável "Filtros avançados ▾".
// Refs: Index.charter.md v2 Goals · feedback-design-literal-copy §How to apply #5.
import SellsDateFilter, {
  computePresetRange,
  type DateFilterPreset,
} from './_components/SellsDateFilter';
import SellsToggleViewMode, { type SellsViewMode } from './_components/SellsToggleViewMode';
import SellsTabsVisao, { type SellsVisao } from './_components/SellsTabsVisao';
import SellsGroupByDropdown, { type GroupByField } from './_components/SellsGroupByDropdown';
import SellsGradeAvancada from './_components/SellsGradeAvancada';
import type { SellsTotals } from './_components/SellsTotalsRow';

// ──────────────────────────────────────────────────────────────
// TIPOS
// ──────────────────────────────────────────────────────────────
type SlaKind = 'fresh' | 'warning' | 'overdue' | 'paid';
type PaymentStatus = 'paid' | 'due' | 'partial' | string;
type FiscalStatus = 'pendente' | 'autorizada' | 'rejeitada' | 'denegada' | 'cancelada' | null;
type PillKey = 'todas' | 'paga' | 'pendente' | 'faturada' | 'cancelada';
type FocoKey = 'caixa' | 'faturamento' | 'comissao';

interface SaleRow {
  id: number;
  transaction_date: string;
  display_date: string | null;
  invoice_no: string;
  final_total: number;
  total_paid: number;
  payment_status: PaymentStatus;
  shipping_status: string | null;
  customer_name: string | null;
  customer_secondary: string | null;
  location_name: string | null;
  is_overdue: boolean;
  fiscal_status: FiscalStatus;
  fiscal_modelo: '55' | '65' | null;
  current_stage_key: string | null;
  is_grouped_invoice: boolean;
  // Cowork additions (US-SELL-COWORK, ver SellController::inertiaList)
  sla_kind: SlaKind;
  days_to_due: number | null;
  pay_term_number: number | null;
  pay_term_type: 'days' | 'months' | null;
  pipeline_step: number | null;
  pipeline_total: number | null;
  pipeline_label: string | null;
  pipeline_color: string | null;
  seller_id: number | null;
  seller_name: string | null;
  seller_abbr: string | null;
  seller_origin: string;
  items_summary: string | null;
  items_count: number;
  payment_method_label: string | null;
  installments: number;
  // US-SELL-COWORK-COMMISSION — comissionado (gap PR #1043).
  // Coluna só renderiza se coworkCommissionEnabled=true (setting business.sales_cmsn_agnt ≠ 'disable').
  commission_agent_id?: number | null;
  commission_agent_name?: string | null;
}

interface SellKpis {
  total: number;
  paid: number;
  due: number;
  partial: number;
  overdue: number;
}

interface ListMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  sort: string;
  dir: 'asc' | 'desc';
  date_field?: string;
}

interface TotalsSummary {
  count: number;
  sum_final_total: number;
  sum_total_paid: number;
  sum_due: number;
}

// US-SELL-COWORK-R5-POLISH — agregados deferred (sparkline real 30d + deltas + top vendedor).
interface CoworkAggregates {
  sparkline: number[]; // 30 days, oldest → newest, sum(final_total) per day
  deltaRevenueVsYesterday: number | null; // pct round int (today vs yesterday); null se ontem=0
  deltaTicketVsLastWeek: number | null;   // pct round int (this week ticket médio vs last week); null se 0
  topSeller: { name: string; total: number } | null;
}

export interface SellsIndexPageProps {
  sellKpis: SellKpis;
  permissions: {
    create: boolean;
    view: boolean;
  };
  /** US-SELL-COWORK-R5-POLISH — Inertia::defer prop; undefined enquanto carrega. */
  coworkAggregates?: CoworkAggregates;
  /**
   * US-SELL-COWORK-COMMISSION — flag liga/desliga coluna Comissão na grade (gap PR #1043).
   * true quando business_details.sales_cmsn_agnt ≠ 'disable'; false oculta coluna inteira.
   * Default false (back-compat: sem setting habilitado, coluna não aparece).
   */
  coworkCommissionEnabled?: boolean;
  sells?: {
    viewMode?: {
      default?: string;
    };
  };
}

// ──────────────────────────────────────────────────────────────
// HELPERS — formatação + storage
// ──────────────────────────────────────────────────────────────
const fmt = (n: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);

const fmtShort = (n: number) =>
  n >= 1000 ? 'R$ ' + (n / 1000).toFixed(1).replace('.', ',') + 'k' : fmt(n);

const fmtDateDM = (iso: string | null) => {
  if (!iso) return '—';
  // aceita 'YYYY-MM-DD' OU 'YYYY-MM-DD HH:MM:SS' OU ISO completo
  const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
  return m ? `${m[3]}/${m[2]}` : iso.slice(0, 5);
};

const fmtTime = (iso: string | null) => {
  if (!iso) return '';
  const m = iso.match(/(\d{2}):(\d{2})/);
  return m ? `${m[1]}:${m[2]}` : '';
};

// Tier 0 multi-tenant (ADR 0093): preferências de UI da Sells escopo
// per-business pra não vazar entre tenants no mesmo browser (Wagner WR2 biz=1
// ↔ Larissa biz=4). Chave: `oimpresso.sells.b<businessId>.<k>` ou `.guest.<k>`.
// Sinal: Larissa @ ROTA LIVRE biz=4 reportou filtro abrindo em "Caixa" em vez
// de "Todas" 2026-05-21 (ADR 0105 cliente-sinal).
function useBizStorage() {
  const page = usePage<{ auth?: { user?: { business_id?: number } } }>();
  const bizId = page.props.auth?.user?.business_id ?? null;
  const prefix = `oimpresso.sells.${bizId ? `b${bizId}` : 'guest'}.`;
  return useMemo(
    () => ({
      get: (k: string, d = ''): string => {
        if (typeof window === 'undefined') return d;
        try {
          return window.localStorage.getItem(prefix + k) ?? d;
        } catch (_) {
          return d;
        }
      },
      set: (k: string, v: string): void => {
        if (typeof window === 'undefined') return;
        try {
          window.localStorage.setItem(prefix + k, v);
        } catch (_) {
          /* localStorage indisponível */
        }
      },
      key: (k: string): string => prefix + k,
    }),
    [prefix],
  );
}

// SLA pill — mirrors backend sla_kind but supports rendering when ausente.
const SLA_ICON: Record<SlaKind, string> = {
  fresh: '●',
  warning: '▲',
  overdue: '✕',
  paid: '✓',
};
function slaLabel(kind: SlaKind, days: number | null): string {
  if (kind === 'paid') return 'paga';
  if (kind === 'overdue') return `estourado · ${Math.abs(days ?? 0)}d`;
  if (kind === 'warning') return `atrasando · ${days ?? 0}d`;
  return days != null ? `vence ${days}d` : 'fresco';
}
function slaShort(kind: SlaKind, days: number | null): string {
  if (kind === 'paid') return '✓';
  if (days == null) return '—';
  if (kind === 'overdue') return `-${Math.abs(days)}d`;
  return `${days}d`;
}

// status pill labels
const STATUS_LABEL: Record<PillKey, string> = {
  todas: 'Todas',
  paga: 'Paga',
  pendente: 'Pendente',
  faturada: 'Faturada',
  cancelada: 'Cancelada',
};

// classifier — what pill does a row belong to (frontend cheap derivation).
function classifyPill(r: SaleRow): PillKey {
  if (r.fiscal_status === 'cancelada') return 'cancelada';
  if (r.fiscal_status === 'autorizada') return 'faturada';
  if (r.payment_status === 'paid') return 'paga';
  return 'pendente';
}

// avatar palette index by seller_id (consistent across renders).
const AVATAR_PALETTES = ['a', 'b', 'c', 'd', 'e', 'f'] as const;
function avatarPaletteFor(sellerId: number | null): string {
  if (sellerId == null) return AVATAR_PALETTES[0];
  return AVATAR_PALETTES[sellerId % AVATAR_PALETTES.length] ?? AVATAR_PALETTES[0];
}

// ──────────────────────────────────────────────────────────────
// SLA pill — pílula fresco/atrasando/estourado/paga
// ──────────────────────────────────────────────────────────────
function SaleSlaPill({ row, compact = false }: { row: SaleRow; compact?: boolean }): ReactNode {
  const kind = row.sla_kind ?? 'fresh';
  const days = row.days_to_due;
  const label = slaLabel(kind, days);
  return (
    <span className={`vd-sla vd-sla-${kind}`} title={label}>
      <span className="vd-sla-ic">{SLA_ICON[kind] ?? '·'}</span>
      <span className="vd-sla-lbl">{compact ? slaShort(kind, days) : label}</span>
    </span>
  );
}

// ──────────────────────────────────────────────────────────────
// Pipeline dots — stepper FSM ●●●○ + label curta
// ──────────────────────────────────────────────────────────────
function PipelineDots({ row }: { row: SaleRow }): ReactNode {
  const step = row.pipeline_step ?? null;
  const total = row.pipeline_total ?? 5;
  if (step == null) {
    return <span className="vd-stp" style={{ opacity: 0.5 }}>—</span>;
  }
  const dots = Array.from({ length: total }, (_, i) => {
    let cls = 'vd-stp-dot';
    if (i < step) cls += ' done';
    else if (i === step) cls += ' current';
    return <span key={i} className={cls} />;
  });
  // label: pega 3-4 chars iniciais maiúsculas do stage_name ou stage_key
  const labelRaw =
    row.pipeline_label ?? (row.current_stage_key ? row.current_stage_key : '');
  const firstWord = labelRaw ? labelRaw.replace(/[_-]/g, ' ').split(' ')[0] ?? '' : '';
  const label = firstWord.slice(0, 4).toUpperCase();
  return (
    <span className="vd-stp" title={row.pipeline_label ?? row.current_stage_key ?? ''}>
      {dots}
      <span className="vd-stp-lbl">{label}</span>
    </span>
  );
}

// ──────────────────────────────────────────────────────────────
// Fiscal badges — NF-e / NFS-e por venda
// ──────────────────────────────────────────────────────────────
const FBADGE_CLASS: Record<string, { cls: string; ic: string; tip: string }> = {
  autorizada: { cls: 'ok', ic: '✓', tip: 'Autorizada SEFAZ' },
  pendente:   { cls: 'wait', ic: '⌛', tip: 'Transmitida · aguardando SEFAZ' },
  rejeitada:  { cls: 'bad', ic: 'Σ', tip: 'Rejeitada SEFAZ' },
  denegada:   { cls: 'bad', ic: '✕', tip: 'Denegada SEFAZ' },
  cancelada:  { cls: 'canc', ic: '⊘', tip: 'Cancelada' },
};

function FiscalBadgesCell({ row }: { row: SaleRow }): ReactNode {
  if (!row.fiscal_status) {
    return (
      <span className="vd-fc">
        <span className="vd-fb vd-fb-na">
          <span className="vd-fb-ic">—</span>
        </span>
      </span>
    );
  }
  const meta = FBADGE_CLASS[row.fiscal_status] ?? { cls: 'wait', ic: '⌛', tip: 'Pendente' };
  const kindLabel = row.fiscal_modelo === '65' ? 'NFC-e' : 'NF-e';
  return (
    <span className="vd-fc">
      <span className={`vd-fb vd-fb-${meta.cls}`}>
        <span className="vd-fb-ic">{meta.ic}</span>
        {kindLabel}
        <span className="vd-fb-tip">{meta.tip}</span>
      </span>
    </span>
  );
}

// ──────────────────────────────────────────────────────────────
// Sparkline — KPI hero (30d simulado por enquanto)
// ──────────────────────────────────────────────────────────────
function Sparkline({
  data,
  color = 'currentColor',
  fill = true,
  height = 32,
  width = 240,
}: {
  data: number[];
  color?: string;
  fill?: boolean;
  height?: number;
  width?: number;
}): ReactNode {
  if (!data || data.length < 2) return null;
  const pad = 2;
  const min = Math.min(...data);
  const max = Math.max(...data);
  const dx = (width - pad * 2) / (data.length - 1);
  const pts = data.map<[number, number]>((v, i) => [
    pad + i * dx,
    height - pad - ((v - min) / (max - min || 1)) * (height - pad * 2),
  ]);
  const first = pts[0];
  const last = pts[pts.length - 1];
  if (!first || !last) return null;
  const line = 'M' + pts.map((p) => p.join(',')).join(' L');
  const area = `${line} L${last[0]},${height} L${first[0]},${height} Z`;
  return (
    <svg
      viewBox={`0 0 ${width} ${height}`}
      preserveAspectRatio="none"
      style={{ width: '100%', height, display: 'block' }}
      aria-hidden="true"
    >
      {fill && <path d={area} fill={color} opacity="0.18" />}
      <path
        d={line}
        fill="none"
        stroke={color}
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <circle cx={last[0]} cy={last[1]} r="2.5" fill={color} />
    </svg>
  );
}

// ──────────────────────────────────────────────────────────────
// Cheat sheet overlay (?)
// ──────────────────────────────────────────────────────────────
function SellsCheatSheet({ onClose }: { onClose: () => void }): ReactNode {
  return (
    <div className="vd-cheat-bd" onClick={onClose}>
      <div className="vd-cheat" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Atalhos">
        <header className="vd-cheat-h">
          <span className="vd-cheat-ic">⌨</span>
          <h2>Atalhos · Lista de vendas</h2>
          <button className="vd-cheat-x" onClick={onClose} aria-label="Fechar">
            <X size={14} />
          </button>
        </header>
        <div className="vd-cheat-body">
          <section className="vd-cheat-grp">
            <h3>Navegar</h3>
            <ul>
              <li><kbd>J</kbd> <kbd>K</kbd> linha anterior/próxima</li>
              <li><kbd>Enter</kbd> abrir detalhes</li>
              <li><kbd>Esc</kbd> fechar drawer / palette</li>
            </ul>
          </section>
          <section className="vd-cheat-grp">
            <h3>Ações</h3>
            <ul>
              <li><kbd>N</kbd> nova venda</li>
              <li><kbd>B</kbd> favoritar linha em foco ★</li>
              <li><kbd>R</kbd> imprimir recibo</li>
              <li><kbd>F</kbd> faturar (emitir NF)</li>
              <li><kbd>X</kbd> marcar pra ação em lote</li>
              <li><kbd>E</kbd> editar venda</li>
            </ul>
          </section>
          <section className="vd-cheat-grp">
            <h3>⌘K palette</h3>
            <ul>
              <li><kbd>⌘K</kbd> abrir busca rápida</li>
              <li><kbd>/</kbd> ações</li>
              <li><kbd>#</kbd> ID da venda</li>
              <li><kbd>@</kbd> vendedor</li>
              <li><kbd>$</kbd> valor mínimo</li>
            </ul>
          </section>
          <section className="vd-cheat-grp">
            <h3>Sair</h3>
            <ul>
              <li><kbd>?</kbd> abre/fecha este painel</li>
              <li><kbd>Esc</kbd> fechar</li>
            </ul>
          </section>
        </div>
      </div>
    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// Saved views — Hoje / Pendentes / Atrasadas / Rejeitadas / Faturadas / Favoritas
// (mock simplificado — backend não implementa view filter ainda; client-side)
// ──────────────────────────────────────────────────────────────
type SavedViewId = 'hoje' | 'pendentes' | 'atrasadas' | 'rejeitadas' | 'faturadas' | 'todas';
interface SavedView {
  id: SavedViewId;
  label: string;
  filter: (r: SaleRow) => boolean;
}
const SAVED_VIEWS: SavedView[] = [
  { id: 'hoje',       label: 'Pendentes pgto.',  filter: (r) => r.payment_status !== 'paid' },
  { id: 'pendentes',  label: 'Pendentes',        filter: (r) => r.payment_status === 'due' || r.payment_status === 'partial' },
  { id: 'atrasadas',  label: 'Atrasadas',        filter: (r) => r.sla_kind === 'overdue' },
  { id: 'rejeitadas', label: 'NF-e rejeitadas',  filter: (r) => r.fiscal_status === 'rejeitada' },
  { id: 'faturadas',  label: 'Faturadas (mês)',  filter: (r) => r.fiscal_status === 'autorizada' },
  { id: 'todas',      label: 'Todas',            filter: () => true },
];

// ──────────────────────────────────────────────────────────────
// MAIN — SellsIndex
// ──────────────────────────────────────────────────────────────
export default function SellsIndex(props: SellsIndexPageProps): ReactNode {
  // Tier 0 multi-tenant: storage scoped per business_id (ver useBizStorage acima).
  const ls = useBizStorage();

  // FOCO segmented control (vista) — Caixa / Faturamento / Comissão (afeta 4º KPI).
  const [foco, setFoco] = useState<FocoKey>(() => {
    const v = ls.get('foco', 'caixa');
    return (['caixa', 'faturamento', 'comissao'] as const).includes(v as FocoKey)
      ? (v as FocoKey)
      : 'caixa';
  });
  useEffect(() => ls.set('foco', foco), [foco]);

  // Saved view dropdown (Hoje / Pendentes / Atrasadas / etc).
  // Default 'todas' — Larissa @ ROTA LIVRE biz=4 reportou 2026-05-21 abrir em
  // "Caixa" não bate com a operação dela (ADR 0105 sinal qualificado).
  const [savedViewId, setSavedViewId] = useState<SavedViewId>(() => {
    const v = ls.get('savedView', 'todas');
    return SAVED_VIEWS.find((s) => s.id === v)?.id ?? 'todas';
  });
  useEffect(() => ls.set('savedView', savedViewId), [savedViewId]);

  // 5-pill status filter.
  const [pillFilter, setPillFilter] = useState<PillKey>(() => {
    const v = ls.get('pill', 'todas');
    return (['todas', 'paga', 'pendente', 'faturada', 'cancelada'] as const).includes(v as PillKey)
      ? (v as PillKey)
      : 'todas';
  });
  useEffect(() => ls.set('pill', pillFilter), [pillFilter]);

  // Data fetch state.
  const [rows, setRows] = useState<SaleRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [meta, setMeta] = useState<ListMeta | null>(null);
  const [totals, setTotals] = useState<TotalsSummary | null>(null);
  const [openSaleId, setOpenSaleId] = useState<number | null>(null);

  // PR follow-up Cowork — filtros legacy preservados US-SELL-015/017/018/019/021.
  // viewMode (lista | grade-avancada), preset (Dia/Semana/Mês/Ano/Personalizado/all),
  // dateField (7 opções: emissão/atualização/nfe/faturamento/envio/competência/prometido),
  // groupBy (none/customer/payment_status/emission_month), sortKey/Dir.
  const [viewMode, setViewMode] = useState<SellsViewMode>(() => {
    const v = ls.get('viewMode', 'lista');
    return (['lista', 'grade-avancada'] as const).includes(v as SellsViewMode)
      ? (v as SellsViewMode)
      : 'lista';
  });
  useEffect(() => ls.set('viewMode', viewMode), [viewMode]);

  const [datePreset, setDatePreset] = useState<DateFilterPreset>(() => {
    const v = ls.get('datePreset', 'all');
    return (['day', 'week', 'month', 'year', 'custom', 'all'] as const).includes(
      v as DateFilterPreset
    )
      ? (v as DateFilterPreset)
      : 'all';
  });
  useEffect(() => ls.set('datePreset', datePreset), [datePreset]);

  const [dateFrom, setDateFrom] = useState<string>(() => {
    const stored = ls.get('datePreset', 'all') as DateFilterPreset;
    if (stored !== 'all' && stored !== 'custom') return computePresetRange(stored).dateFrom;
    return ls.get('dateFrom', '');
  });
  const [dateTo, setDateTo] = useState<string>(() => {
    const stored = ls.get('datePreset', 'all') as DateFilterPreset;
    if (stored !== 'all' && stored !== 'custom') return computePresetRange(stored).dateTo;
    return ls.get('dateTo', '');
  });
  useEffect(() => ls.set('dateFrom', dateFrom), [dateFrom]);
  useEffect(() => ls.set('dateTo', dateTo), [dateTo]);

  const [dateField, setDateField] = useState<
    'transaction_date' | 'updated_at' | 'nfe_issued_at' | 'invoiced_at'
    | 'invoice_sent_at' | 'competence_date' | 'due_date'
  >(() => {
    const v = ls.get('dateField', 'transaction_date');
    const allowed = [
      'transaction_date', 'updated_at', 'nfe_issued_at', 'invoiced_at',
      'invoice_sent_at', 'competence_date', 'due_date',
    ] as const;
    return (allowed as readonly string[]).includes(v) ? (v as typeof allowed[number]) : 'transaction_date';
  });
  useEffect(() => ls.set('dateField', dateField), [dateField]);

  const [groupBy, setGroupBy] = useState<GroupByField>(() => {
    const v = ls.get('groupBy', 'none');
    const allowed = ['none', 'customer_name', 'payment_status', 'emission_month'] as const;
    return (allowed as readonly string[]).includes(v) ? (v as GroupByField) : 'none';
  });
  useEffect(() => ls.set('groupBy', groupBy), [groupBy]);

  type SortKey = 'transaction_date' | 'invoice_no' | 'customer_name' | 'final_total' | 'payment_status';
  const [sortKey, setSortKey] = useState<SortKey>('transaction_date');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');
  const handleSort = useCallback(
    (k: SortKey) => {
      setSortKey((prev) => {
        if (k === prev) return prev;
        return k;
      });
      setSortDir((prev) => {
        if (k === sortKey) return prev === 'asc' ? 'desc' : 'asc';
        return k === 'transaction_date' || k === 'final_total' ? 'desc' : 'asc';
      });
    },
    [sortKey]
  );

  const handleDateFilterChange = useCallback(
    (next: {
      preset: DateFilterPreset;
      dateFrom: string;
      dateTo: string;
      dateField: typeof dateField;
    }) => {
      setDatePreset(next.preset);
      setDateFrom(next.dateFrom);
      setDateTo(next.dateTo);
      setDateField(next.dateField);
    },
    []
  );

  // Fix bug "vendas só aparecem até dia 14" (2026-05-18): se filter de data
  // está ativo (datePreset !== 'all' ou dateFrom/dateTo populado), mostra hint
  // visível acima das pills. Se dateTo < ontem, alerta como "Filtro antigo".
  const clearDateFilter = useCallback(() => {
    setDatePreset('all');
    setDateFrom('');
    setDateTo('');
    ls.set('datePreset', 'all');
    ls.set('dateFrom', '');
    ls.set('dateTo', '');
  }, []);

  const dateFilterActive = datePreset !== 'all' || dateFrom !== '' || dateTo !== '';
  const dateFilterStale = useMemo(() => {
    if (!dateTo) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const yesterday = new Date(today.getTime() - 86_400_000);
    const toDate = new Date(dateTo);
    return !isNaN(toDate.getTime()) && toDate < yesterday;
  }, [dateTo]);

  const dateFilterLabel = useMemo(() => {
    if (!dateFilterActive) return '';
    if (datePreset === 'day') return 'hoje';
    if (datePreset === 'week') return 'esta semana';
    if (datePreset === 'month') return 'este mês';
    if (datePreset === 'year') return 'este ano';
    if (dateFrom && dateTo) return `${dateFrom} → ${dateTo}`;
    if (dateTo) return `até ${dateTo}`;
    if (dateFrom) return `desde ${dateFrom}`;
    return '';
  }, [datePreset, dateFrom, dateTo, dateFilterActive]);

  // Toggle de visibilidade da barra (default fechado pra não poluir o Cowork visual).
  const [advancedOpen, setAdvancedOpen] = useState<boolean>(() => ls.get('advancedOpen', '0') === '1');
  useEffect(() => ls.set('advancedOpen', advancedOpen ? '1' : '0'), [advancedOpen]);

  // Selection + favorites + focus row (J/K navigation).
  const [selectedIds, setSelectedIds] = useState<Set<number>>(() => new Set());
  const [favSet, setFavSet] = useState<Set<number>>(() => {
    try {
      const v = window.localStorage.getItem(ls.key('favs'));
      return new Set(JSON.parse(v ?? '[]'));
    } catch (_) {
      return new Set();
    }
  });
  const [focusIdx, setFocusIdx] = useState<number>(-1);
  const rowsRef = useRef<Array<HTMLTableRowElement | null>>([]);

  // Search box (server-side via /sells-list-json q param).
  const [searchInput, setSearchInput] = useState('');
  const [searchDebounced, setSearchDebounced] = useState('');
  useEffect(() => {
    const t = setTimeout(() => setSearchDebounced(searchInput.trim()), 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  // Pagination + sort (preservados do legacy — controles ficam compactos no rodapé).
  const [page, setPage] = useState(1);
  const perPage = 50;
  // sortKey/sortDir agora vêm de state (declarados acima); permitem header clicáveis.

  // Refetch trigger (independente de filtros) — disparado por onSaleChanged do drawer.
  const [refetchToken, setRefetchToken] = useState(0);

  // US-SELL-042 — Larissa @ ROTA LIVRE biz=4 (ADR 0105) pediu 2026-05-21
  // "adicionar pagamentos como antigamente" (botão direto na linha sem abrir
  // drawer). Estado do modal QuickPayment compartilhado entre Lista e Grade.
  const [payDialog, setPayDialog] = useState<{ saleId: number; invoiceNo: string; dueAmount: number } | null>(null);

  // Onda Unificação PR2/6 (ADR 0178) — tabs Visão Operacional/Financeira/Produção.
  // Feature-flagged via URL `?tabs=1` — visível só pra Wagner em testes; default off
  // não impacta Larissa biz=4. PR3 conecta visão → visibleColumns; PR4 faz cutover.
  const tabsFlagOn = typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('tabs') === '1';
  const [visao, setVisao] = useState<SellsVisao>(() => {
    const v = ls.get('visao', 'operacional');
    return (['operacional', 'financeira', 'producao'] as const).includes(v as SellsVisao)
      ? (v as SellsVisao)
      : 'operacional';
  });
  useEffect(() => ls.set('visao', visao), [visao]);

  // UI overlays.
  const [cheatOpen, setCheatOpen] = useState(false);
  const [palOpen, setPalOpen] = useState(false);
  const [palQ, setPalQ] = useState('');
  const [palSel, setPalSel] = useState(0);
  const [viewsOpen, setViewsOpen] = useState(false);
  const [visoesOpen, setVisoesOpen] = useState(false);
  // US-SELL-COWORK-R2-IA — ⌘K palette ✦ "Perguntar à IA" abre drawer da 1ª venda
  // visível + painel IA. Esta flag sincroniza com SaleSheet via prop initialAiOpen.
  const [aiTriggered, setAiTriggered] = useState(false);

  // Fetch /sells-list-json sempre que filtros mudam.
  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    const params = new URLSearchParams();
    // Backend payment_status: mapeia pill UI → backend filter.
    if (pillFilter === 'paga') params.set('payment_status', 'paid');
    else if (pillFilter === 'pendente') params.set('payment_status', 'due');
    // Faturada/Cancelada filtra client-side (backend não tem invoice_status pill ainda).
    if (searchDebounced) params.set('q', searchDebounced);
    params.set('per_page', String(perPage));
    params.set('page', String(page));
    params.set('sort', sortKey);
    params.set('dir', sortDir);
    // PR follow-up — date_field + date_from/date_to do SellsDateFilter (US-SELL-018/021).
    params.set('date_field', dateField);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);

    fetch(`/sells-list-json?${params.toString()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then((json) => {
        if (cancelled) return;
        setRows(Array.isArray(json.data) ? json.data : []);
        setMeta(json.meta ?? null);
        setTotals(json.totals ?? null);
      })
      .catch(() => {
        if (cancelled) return;
        setRows([]);
        setMeta(null);
        setTotals(null);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [pillFilter, searchDebounced, page, perPage, sortKey, sortDir, dateField, dateFrom, dateTo, refetchToken]);

  // Reset page when filter changes.
  useEffect(
    () => setPage(1),
    [pillFilter, searchDebounced, sortKey, sortDir, dateField, dateFrom, dateTo]
  );

  // SavedView + pill filtering (client-side composto).
  const currentSavedView: SavedView =
    SAVED_VIEWS.find((s) => s.id === savedViewId) ?? SAVED_VIEWS[0]!;
  const filtered = useMemo<SaleRow[]>(() => {
    let out = rows.filter(currentSavedView.filter);
    if (pillFilter === 'faturada') out = out.filter((r) => r.fiscal_status === 'autorizada');
    else if (pillFilter === 'cancelada') out = out.filter((r) => r.fiscal_status === 'cancelada');
    return out;
  }, [rows, currentSavedView, pillFilter]);

  // Counts per pill (sobre rows server-loaded).
  const countByPill = useCallback(
    (p: PillKey) => {
      if (p === 'todas') return rows.length;
      return rows.filter((r) => classifyPill(r) === p).length;
    },
    [rows]
  );

  // SLA breakdown (counts) — alimenta o KPI A receber.
  const slaCounts = useMemo(() => {
    return rows.reduce(
      (acc, r) => {
        if (r.sla_kind === 'fresh') acc.fresh++;
        else if (r.sla_kind === 'warning') acc.warning++;
        else if (r.sla_kind === 'overdue') acc.overdue++;
        return acc;
      },
      { fresh: 0, warning: 0, overdue: 0 }
    );
  }, [rows]);

  // Ageing breakdown (0-30d / 31-60d / +60d) — alimenta a barra horizontal do KPI A receber.
  const ageingBreakdown = useMemo(() => {
    let ok = 0, w = 0, b = 0;
    rows.forEach((r) => {
      if (r.payment_status === 'paid' || r.days_to_due == null) return;
      const overdueDays = Math.max(0, -(r.days_to_due ?? 0));
      if (overdueDays === 0) ok += r.final_total;
      else if (overdueDays <= 30) w += r.final_total;
      else b += r.final_total;
    });
    return { ok, w, b, total: Math.max(1, ok + w + b) };
  }, [rows]);

  // KPI hero — Faturado hoje (deriva de transaction_date === hoje + paid).
  const todayIso = useMemo(() => new Date().toISOString().slice(0, 10), []);
  const kpiToday = useMemo(() => {
    const today = rows.filter((r) => r.transaction_date?.slice(0, 10) === todayIso);
    const total = today.reduce((s, r) => s + r.final_total, 0);
    const ticket = today.length ? total / today.length : 0;
    return { total, count: today.length, ticket };
  }, [rows, todayIso]);

  // A receber — soma de final_total - total_paid sobre rows não-pagas.
  const kpiAReceber = useMemo(
    () => rows.reduce((s, r) => s + (r.payment_status !== 'paid' ? r.final_total - r.total_paid : 0), 0),
    [rows]
  );

  // Notas fiscais — contagem por status fiscal.
  const fiscalCount = useMemo(() => {
    let ok = 0, wait = 0, bad = 0;
    rows.forEach((r) => {
      if (r.fiscal_status === 'autorizada') ok++;
      else if (r.fiscal_status === 'pendente') wait++;
      else if (r.fiscal_status === 'rejeitada' || r.fiscal_status === 'denegada') bad++;
    });
    return { ok, wait, bad, total: ok + wait + bad };
  }, [rows]);

  // US-SELL-COWORK-R5-POLISH — Sparkline real (30d) via Inertia::defer.
  // Fallback: enquanto coworkAggregates não chega, usa base ascendente + faturado hoje.
  const sparkData = useMemo<number[]>(() => {
    if (props.coworkAggregates?.sparkline?.length) {
      // Escala pra mil pra renderização compacta (Sparkline component aceita qualquer escala).
      return props.coworkAggregates.sparkline.map((v) => Math.max(0.1, v / 1000));
    }
    // Fallback enquanto deferred não carregou — base com últ. ponto = faturado hoje.
    const base = [
      3.2, 2.8, 4.1, 3.6, 4.8, 5.2, 3.9, 4.4, 5.8, 4.6,
      5.1, 6.3, 5.4, 4.9, 6.8, 7.2, 5.9, 6.4, 7.8, 6.2,
      7.5, 8.4, 6.9, 7.8, 9.1, 8.2, 7.6, 8.9, 9.4,
    ];
    return [...base, Math.max(0.1, kpiToday.total / 1000)];
  }, [kpiToday.total, props.coworkAggregates?.sparkline]);

  // US-SELL-COWORK-R5-POLISH — deltas reais (round int %) com fallback —.
  const deltaRevenue = props.coworkAggregates?.deltaRevenueVsYesterday ?? null;
  const deltaTicket = props.coworkAggregates?.deltaTicketVsLastWeek ?? null;
  const topSeller = props.coworkAggregates?.topSeller ?? null;

  // Selection helpers.
  const toggleSel = useCallback(
    (id: number) =>
      setSelectedIds((prev) => {
        const next = new Set(prev);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        return next;
      }),
    []
  );
  const toggleAll = useCallback(() => {
    if (selectedIds.size === filtered.length) setSelectedIds(new Set());
    else setSelectedIds(new Set(filtered.map((v) => v.id)));
  }, [selectedIds.size, filtered]);

  // Favorites helpers (persist localStorage).
  const toggleFav = useCallback((id: number) => {
    setFavSet((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      try {
        window.localStorage.setItem(ls.key('favs'), JSON.stringify([...next]));
      } catch (_) {
        /* ls indisponível */
      }
      return next;
    });
  }, []);

  // Keyboard handler — J/K nav, ?, N, B, R, F, E, X, ⌘K, Esc.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement | null;
      const inField =
        target &&
        (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) ||
          (target as HTMLElement).isContentEditable);

      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setPalOpen(true);
        return;
      }
      if (e.key === 'Escape') {
        if (cheatOpen) setCheatOpen(false);
        else if (palOpen) setPalOpen(false);
        else if (viewsOpen) setViewsOpen(false);
        else if (visoesOpen) setVisoesOpen(false);
        else if (openSaleId != null) setOpenSaleId(null);
        else if (focusIdx >= 0) setFocusIdx(-1);
        return;
      }
      if (inField) return;
      if (palOpen || cheatOpen || openSaleId != null) return;

      const total = filtered.length;
      const cur = focusIdx;

      if (e.key === 'j' || e.key === 'J' || e.key === 'ArrowDown') {
        e.preventDefault();
        const next = Math.min(total - 1, cur < 0 ? 0 : cur + 1);
        setFocusIdx(next);
        rowsRef.current[next]?.scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'k' || e.key === 'K' || e.key === 'ArrowUp') {
        e.preventDefault();
        const next = Math.max(0, cur < 0 ? 0 : cur - 1);
        setFocusIdx(next);
        rowsRef.current[next]?.scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter') {
        const row = cur >= 0 ? filtered[cur] : undefined;
        if (row) {
          e.preventDefault();
          setOpenSaleId(row.id);
        }
      } else if (e.key === 'n' || e.key === 'N') {
        e.preventDefault();
        window.location.href = '/sells/create';
      } else if (e.key === '?') {
        e.preventDefault();
        setCheatOpen(true);
      } else if (e.key === '/') {
        e.preventDefault();
        setPalOpen(true);
      } else {
        const row = cur >= 0 ? filtered[cur] : undefined;
        if (!row) return;
        if (e.key === 'b' || e.key === 'B') {
          e.preventDefault();
          toggleFav(row.id);
        } else if (e.key === 'x' || e.key === 'X') {
          e.preventDefault();
          toggleSel(row.id);
        } else if (
          e.key === 'e' ||
          e.key === 'E' ||
          e.key === 'r' ||
          e.key === 'R' ||
          e.key === 'f' ||
          e.key === 'F'
        ) {
          e.preventDefault();
          setOpenSaleId(row.id);
        }
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [filtered, focusIdx, cheatOpen, palOpen, viewsOpen, visoesOpen, openSaleId, toggleFav, toggleSel]);

  // Outside-click close pra dropdowns.
  const viewsRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!viewsOpen) return;
    const onClick = (e: MouseEvent) => {
      if (viewsRef.current && !viewsRef.current.contains(e.target as Node)) setViewsOpen(false);
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, [viewsOpen]);

  const visoesRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!visoesOpen) return;
    const onClick = (e: MouseEvent) => {
      if (visoesRef.current && !visoesRef.current.contains(e.target as Node)) setVisoesOpen(false);
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, [visoesOpen]);

  // Reset focus when filtered list size changes.
  useEffect(() => {
    if (focusIdx >= filtered.length) setFocusIdx(filtered.length ? filtered.length - 1 : -1);
  }, [filtered.length, focusIdx]);

  return (
    <div className="sells-cowork">
      <div className="os-page vendas-page vendas-aplus" data-vista={foco}>
        {/* HEADER linha 1: h1 + ⌘K + CTA primário */}
        <header className="os-head vd-head-clean">
          <div className="os-head-l">
            <h1>Vendas</h1>
            <p>Pedidos · faturamento · NF-e/NFS-e</p>
          </div>

          <button className="vd-cmdk" onClick={() => setPalOpen(true)} type="button">
            <Search size={12} />
            <span>Buscar venda, cliente, chave SEFAZ…</span>
            <kbd>⌘K</kbd>
          </button>

          <div className="os-head-r">
            {props.permissions.create && (
              <a className="os-btn primary" href="/sells/create">
                <Plus size={11} />
                Nova venda <kbd className="kbd-hint">N</kbd>
              </a>
            )}
          </div>
        </header>

        {/* TOOLBAR linha 2: Foco group + Visões btn / Imprimir + Visões ▾ */}
        <div className="vd-toolbar">
          <div className="vd-toolbar-l">
            <div className="vd-vista" role="group" aria-label="Foco">
              <span className="vd-vista-lbl">Foco</span>
              <button className={foco === 'caixa' ? 'on' : ''} onClick={() => setFoco('caixa')} type="button">
                Caixa
              </button>
              <button
                className={foco === 'faturamento' ? 'on' : ''}
                onClick={() => setFoco('faturamento')}
                type="button"
              >
                Faturamento
              </button>
              <button
                className={foco === 'comissao' ? 'on' : ''}
                onClick={() => setFoco('comissao')}
                type="button"
              >
                Comissão
              </button>
            </div>

            <span className="vd-toolbar-sep" />

            <div className="vd-views" ref={viewsRef}>
              <button className="vd-views-btn" onClick={() => setViewsOpen((v) => !v)} type="button">
                {currentSavedView.label}
                <ChevronDown size={11} style={{ marginLeft: 4 }} />
              </button>
              {viewsOpen && (
                <div className="vd-views-menu vd-tree">
                  {favSet.size > 0 && (
                    <>
                      <div
                        className={`vd-tree-row l0 vd-tree-fav ${savedViewId === ('favoritas' as SavedViewId) ? 'active' : ''}`}
                        onClick={() => {
                          // tratamos "favoritas" via override de filtro inline mais abaixo.
                          setSavedViewId('todas');
                          setViewsOpen(false);
                        }}
                      >
                        <span className="vd-tree-arr empty" />
                        <span className="vd-tree-lbl">
                          ★ Favoritas <small>(pessoais · atalho B)</small>
                        </span>
                        <span className="ct">{favSet.size}</span>
                      </div>
                      <div className="vd-views-sep" />
                    </>
                  )}
                  {SAVED_VIEWS.map((sv) => {
                    const ct = rows.filter(sv.filter).length;
                    const isActive = savedViewId === sv.id;
                    return (
                      <div
                        key={sv.id}
                        className={`vd-tree-row l0 ${isActive ? 'active' : ''}`}
                        onClick={() => {
                          setSavedViewId(sv.id);
                          setViewsOpen(false);
                        }}
                      >
                        <span className="vd-tree-arr empty" />
                        <span className="vd-tree-lbl">{sv.label}</span>
                        <span className="ct">{ct}</span>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          </div>

          <div className="vd-toolbar-r">
            <button
              className="vd-toolbar-act"
              type="button"
              title="Imprimir resumo do caixa de hoje (vendas, formas de pagamento, total)"
              onClick={() => {
                // US-SELL-COWORK-R5-POLISH — abre relatório de caixa de hoje em nova aba.
                // Endpoint existente UltimatePOS: /home (cash register summary) ou /sells?print=today.
                // Estratégia: filtra rows do dia + dispara window.print() do próprio Index
                // pra capturar o que está visível agora. Stack canônica imprime sells/{id}/print
                // mas pra "caixa do dia" inteiro o usuário faz print da listagem filtrada.
                window.print();
              }}
            >
              <Printer size={11} />
              <span>Imprimir caixa</span>
            </button>
            <div className="vd-visoes" ref={visoesRef}>
              <button
                className="vd-toolbar-act vd-visoes-btn"
                onClick={() => setVisoesOpen((v) => !v)}
                type="button"
                title="Outras visões deste módulo"
              >
                <Folder size={11} />
                <span>Visões</span>
                <ChevronDown size={11} style={{ marginLeft: 2 }} />
              </button>
              {visoesOpen && (
                <div className="vd-visoes-menu">
                  <div className="vd-visoes-grp">Visões deste módulo</div>
                  <div className="vd-visoes-item active">
                    <span className="vd-visoes-dot" />Lista de vendas
                    <span className="vd-visoes-here">aqui</span>
                  </div>
                  {[
                    { id: 'quotations', label: 'Orçamentos', href: '/sells/quotations' },
                    { id: 'drafts',     label: 'Rascunhos',  href: '/sells/drafts' },
                    { id: 'subs',       label: 'Assinaturas',href: '/sells/subscriptions' },
                  ].map((s) => (
                    <a key={s.id} className="vd-visoes-item" href={s.href}>
                      <span className="vd-visoes-dot" />
                      {s.label}
                    </a>
                  ))}
                  <div className="vd-visoes-sep" />
                  <a className="vd-visoes-item primary" href="/sells/create?pos=1">
                    <span className="vd-visoes-dot pdv" />
                    Abrir PDV balcão
                    <kbd>F2</kbd>
                  </a>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* KPIs row — 4 cards (Faturado hero · Ticket médio · A receber · 4º vista-dependente) */}
        <div className="os-kpis vd-kpis">
          {/* Hero: Faturado hoje + sparkline (delta real US-SELL-COWORK-R5) */}
          <div className="os-kpi vd-kpi-hero">
            <span className="os-kpi-label">Faturado hoje</span>
            <span className="os-kpi-value">{fmtShort(kpiToday.total)}</span>
            <span
              className={
                'os-kpi-sub ' +
                (deltaRevenue == null ? '' : deltaRevenue >= 0 ? 'vd-delta-up' : 'vd-delta-dn')
              }
              title={
                deltaRevenue == null
                  ? 'Sem dado de ontem pra comparar'
                  : `Variação vs ontem (${deltaRevenue >= 0 ? '+' : ''}${deltaRevenue}%)`
              }
            >
              {deltaRevenue == null
                ? `— · ${kpiToday.count} venda${kpiToday.count === 1 ? '' : 's'}`
                : `${deltaRevenue >= 0 ? '↑ +' : '↓ '}${deltaRevenue}% vs ontem · ${kpiToday.count} venda${kpiToday.count === 1 ? '' : 's'}`}
            </span>
            <div className="vd-spark">
              <Sparkline data={sparkData} color="oklch(0.72 0.10 155)" />
            </div>
          </div>

          {/* Ticket médio (delta WoW real US-SELL-COWORK-R5) */}
          <div className="os-kpi">
            <span className="os-kpi-label">Ticket médio</span>
            <span className="os-kpi-value">{fmtShort(kpiToday.ticket)}</span>
            <span
              className={
                'os-kpi-sub ' +
                (deltaTicket == null ? '' : deltaTicket >= 0 ? 'vd-delta-up' : 'vd-delta-dn')
              }
              title={
                deltaTicket == null
                  ? 'Sem ticket médio da semana passada pra comparar'
                  : `Ticket médio: variação semana atual vs semana passada (${deltaTicket >= 0 ? '+' : ''}${deltaTicket}%)`
              }
            >
              {deltaTicket == null
                ? '— vs semana passada'
                : `${deltaTicket >= 0 ? '↑ +' : '↓ '}${deltaTicket}% vs semana passada`}
            </span>
          </div>

          {/* A receber */}
          <div className="os-kpi">
            <span className="os-kpi-label">A receber</span>
            <span className="os-kpi-value">{fmtShort(kpiAReceber)}</span>
            <span className="os-kpi-sub vd-sla-counts">
              {slaCounts.overdue > 0 && (
                <span className="vd-sla-mini overdue">
                  <span className="ic">✕</span>
                  {`${slaCounts.overdue} estourado${slaCounts.overdue > 1 ? 's' : ''}`}
                </span>
              )}
              {slaCounts.warning > 0 && (
                <span className="vd-sla-mini warning">
                  <span className="ic">▲</span>
                  {`${slaCounts.warning} atrasando`}
                </span>
              )}
              {slaCounts.fresh > 0 && (
                <span className="vd-sla-mini fresh">
                  <span className="ic">●</span>
                  {`${slaCounts.fresh} fresco${slaCounts.fresh > 1 ? 's' : ''}`}
                </span>
              )}
            </span>
            <div className="vd-ageing">
              <div className="vd-ag-bar ok">
                <div style={{ width: (ageingBreakdown.ok / ageingBreakdown.total) * 100 + '%' }} />
              </div>
              <div className="vd-ag-bar warn">
                <div style={{ width: (ageingBreakdown.w / ageingBreakdown.total) * 100 + '%' }} />
              </div>
              <div className="vd-ag-bar bad">
                <div style={{ width: (ageingBreakdown.b / ageingBreakdown.total) * 100 + '%' }} />
              </div>
              <div className="vd-ag-lbls">
                <span>0–30d</span>
                <span>31–60d</span>
                <span>+60d</span>
              </div>
            </div>
          </div>

          {/* 4º card — varia por foco */}
          {foco === 'caixa' && (
            <div className="os-kpi">
              <span className="os-kpi-label">Pagos hoje</span>
              <span className="os-kpi-value">
                {fmtShort(
                  rows
                    .filter((r) => r.payment_status === 'paid' && r.transaction_date?.slice(0, 10) === todayIso)
                    .reduce((s, r) => s + r.total_paid, 0)
                )}
              </span>
              <span className="os-kpi-sub">
                {rows.filter((r) => r.payment_status === 'paid' && r.transaction_date?.slice(0, 10) === todayIso).length}{' '}
                pago{rows.filter((r) => r.payment_status === 'paid' && r.transaction_date?.slice(0, 10) === todayIso).length === 1 ? '' : 's'} hoje
              </span>
            </div>
          )}
          {foco === 'faturamento' && (
            <div className="os-kpi">
              <span className="os-kpi-label">Notas fiscais</span>
              <span className="os-kpi-value">
                {fiscalCount.ok}
                <small>/{fiscalCount.total || rows.length}</small>
              </span>
              <span className="os-kpi-sub">
                autorizadas · {fiscalCount.wait} processando · {fiscalCount.bad} rejeitadas
              </span>
              <div className="vd-fiscal-bar">
                <div style={{ flex: Math.max(1, fiscalCount.ok), background: 'var(--vd-ok)' } as CSSProperties} />
                <div
                  style={{ flex: Math.max(0, fiscalCount.wait), background: 'var(--vd-warn)' } as CSSProperties}
                />
                <div
                  style={{ flex: Math.max(0, fiscalCount.bad), background: 'var(--vd-bad)' } as CSSProperties}
                />
              </div>
            </div>
          )}
          {foco === 'comissao' && (
            <div className="os-kpi" title={topSeller ? `Soma de vendas final_total do mês corrente` : ''}>
              <span className="os-kpi-label">Top vendedor (mês)</span>
              <span className="os-kpi-value">{topSeller ? topSeller.name : '—'}</span>
              <span className="os-kpi-sub">
                {topSeller
                  ? `${fmtShort(topSeller.total)} no mês`
                  : props.coworkAggregates
                    ? 'sem commission_agent atribuído este mês'
                    : 'carregando…'}
              </span>
            </div>
          )}
        </div>

        {/* Fix bug 2026-05-18: hint visível pra filter de data ativo (proteção
            contra localStorage stale que escondia vendas dos últimos dias). */}
        {dateFilterActive && (
          <div className={'vd-date-filter-hint' + (dateFilterStale ? ' stale' : '')} role="status">
            <span className="vd-date-filter-hint-ic">{dateFilterStale ? '⚠' : '📅'}</span>
            <span className="vd-date-filter-hint-tx">
              {dateFilterStale ? <b>Filtro antigo escondendo vendas novas:</b> : <b>Filtro de data ativo:</b>}
              {' '}
              <span className="vd-date-filter-hint-range">{dateFilterLabel}</span>
              {dateFilterStale && <small> · vendas após {dateTo} ficam ocultas</small>}
            </span>
            <button
              type="button"
              className="vd-date-filter-hint-clear"
              onClick={clearDateFilter}
              title="Limpar filtro de data (mostra todas as vendas)"
            >
              Limpar filtro
            </button>
          </div>
        )}

        {/* 5 status pills + toggle "Filtros avançados ▾" (PR follow-up) */}
        <div className="vd-tabs-row">
          <div className="os-tabs vd-tabs-grow">
            {(['todas', 'paga', 'pendente', 'faturada', 'cancelada'] as const).map((s) => (
              <button
                key={s}
                type="button"
                className={'os-tab' + (pillFilter === s ? ' active' : '')}
                onClick={() => setPillFilter(s)}
              >
                {STATUS_LABEL[s] ?? s}
                <span className="os-tab-n">{countByPill(s)}</span>
              </button>
            ))}
          </div>
          <div className="vd-tabs-actions">
            {tabsFlagOn && (
              <SellsTabsVisao visao={visao} onChange={setVisao} />
            )}
            <SellsToggleViewMode viewMode={viewMode} onChange={setViewMode} />
            <button
              type="button"
              className={'vd-filters-toggle' + (advancedOpen ? ' on' : '')}
              onClick={() => setAdvancedOpen((v) => !v)}
              aria-expanded={advancedOpen}
              title="Filtros avançados (período, data, agrupamento)"
            >
              <SlidersHorizontal size={12} />
              <span>Filtros avançados</span>
              <ChevronDown
                size={11}
                style={{
                  transition: 'transform .12s',
                  transform: advancedOpen ? 'rotate(180deg)' : 'none',
                }}
              />
            </button>
          </div>
        </div>

        {/* Barra colapsável Filtros avançados (US-SELL-018/019/021 preservados) */}
        {advancedOpen && (
          <div className="vd-filters-bar">
            <SellsDateFilter
              preset={datePreset}
              dateFrom={dateFrom}
              dateTo={dateTo}
              dateField={dateField}
              onChange={handleDateFilterChange}
            />
            {viewMode === 'grade-avancada' && (
              <SellsGroupByDropdown groupBy={groupBy} onChange={setGroupBy} />
            )}
          </div>
        )}

        {/* TABLE — Cowork (lista) OU Grade Avançada (toggle) */}
        {viewMode === 'grade-avancada' ? (
          <div className="vd-grade-wrap">
            <SellsGradeAvancada
              rows={rows}
              loading={loading}
              totals={totals as SellsTotals | null}
              selectedIds={selectedIds}
              onToggleSelect={toggleSel}
              onToggleSelectAll={toggleAll}
              onClearSelection={() => setSelectedIds(new Set())}
              onRowClick={(id: number) => setOpenSaleId(id)}
              openSaleId={openSaleId}
              totalFiltered={meta?.total ?? rows.length}
              sortKey={sortKey}
              sortDir={sortDir}
              onSort={handleSort}
              groupBy={groupBy}
              onGroupByChange={setGroupBy}
              onPayClick={(saleId, invoiceNo, dueAmount) => setPayDialog({ saleId, invoiceNo, dueAmount })}
            />
          </div>
        ) : (
        <div className="os-table-wrap">
          <table className="os-table vendas-table vd-aplus-table">
            <thead>
              <tr>
                <th style={{ width: 24, padding: '0 0 0 12px' }}>
                  <input
                    type="checkbox"
                    checked={filtered.length > 0 && selectedIds.size === filtered.length}
                    onChange={toggleAll}
                    aria-label="Selecionar todas"
                  />
                </th>
                <th style={{ width: 82 }}>Venda</th>
                <th style={{ width: 80 }}>Data</th>
                <th>Cliente</th>
                <th style={{ width: 168 }}>Atendido por</th>
                <th style={{ width: 128 }}>Pipeline</th>
                <th style={{ width: 148 }}>Fiscal</th>
                <th style={{ width: 128 }}>Pagamento</th>
                <th style={{ width: 110 }}>Total</th>
                <th style={{ width: 88 }}>Status</th>
                {/* US-SELL-COWORK-COMMISSION — coluna Comissão (gap PR #1043).
                    Só renderiza se setting business.sales_cmsn_agnt ≠ 'disable'. */}
                {props.coworkCommissionEnabled && (
                  <th style={{ width: 120 }}>Comissão</th>
                )}
              </tr>
            </thead>
            <tbody>
              {loading &&
                Array.from({ length: 6 }).map((_, i) => (
                  <tr key={`sk${i}`} className="vd-sk-row">
                    {/* US-SELL-COWORK-COMMISSION — colSpan dinâmico (10 base + 1 se Comissão habilitada). */}
                    <td colSpan={props.coworkCommissionEnabled ? 11 : 10}>
                      <div className="vd-sk-bar" style={{ animationDelay: `${i * 60}ms` }} />
                    </td>
                  </tr>
                ))}
              {!loading &&
                filtered.map((v, ri) => {
                  const sel = selectedIds.has(v.id);
                  const isFocused = ri === focusIdx;
                  const isFav = favSet.has(v.id);
                  const isUrgent = v.sla_kind === 'overdue';
                  const pill = classifyPill(v);
                  const pillStyle: Record<PillKey, { bg: string; fg: string; label: string }> = {
                    todas: { bg: 'var(--vd-neutral-soft)', fg: 'var(--vd-neutral)', label: '—' },
                    paga: { bg: 'var(--vd-ok-soft)', fg: 'var(--vd-ok)', label: 'Paga' },
                    pendente: { bg: 'var(--vd-warn-soft)', fg: 'var(--vd-warn)', label: 'Pendente' },
                    faturada: { bg: 'var(--accent-soft)', fg: 'var(--accent)', label: 'Faturada' },
                    cancelada: { bg: 'var(--bg-2)', fg: 'var(--text-mute)', label: 'Cancelada' },
                  };
                  const ps = pillStyle[pill] ?? pillStyle.todas;
                  return (
                    <tr
                      key={v.id}
                      ref={(el) => {
                        rowsRef.current[ri] = el;
                      }}
                      className={
                        'os-row' +
                        (isUrgent ? ' urgent' : '') +
                        (sel ? ' selected' : '') +
                        (isFocused ? ' row-focused' : '')
                      }
                      onClick={() => {
                        setFocusIdx(ri);
                        setOpenSaleId(v.id);
                      }}
                    >
                      <td className="vd-chk" onClick={(e) => e.stopPropagation()}>
                        <input
                          type="checkbox"
                          checked={sel}
                          onChange={() => toggleSel(v.id)}
                          aria-label={`Selecionar venda ${v.invoice_no}`}
                        />
                      </td>
                      <td className="vd-id">
                        {isFav && (
                          <span className="vd-fav" title="Favorita (B)">
                            ★
                          </span>
                        )}
                        #{v.invoice_no}
                      </td>
                      <td className="vd-date">
                        <div>{fmtDateDM(v.display_date ?? v.transaction_date)}</div>
                        <div className="vd-time">{fmtTime(v.display_date ?? v.transaction_date)}</div>
                      </td>
                      <td className="vd-client">
                        <div className="vd-client-name">{v.customer_name ?? '—'}</div>
                        {v.items_summary && <div className="vd-notes">{v.items_summary}</div>}
                      </td>
                      <td className="vd-seller-cell">
                        {v.seller_abbr ? (
                          <>
                            <span className={`vd-av vd-av-${avatarPaletteFor(v.seller_id)}`}>
                              {v.seller_abbr}
                            </span>
                            <span className="vd-seller-info">
                              <b>{(v.seller_name ?? '').split(' ')[0]}</b>
                              <small>{v.seller_origin}</small>
                            </span>
                          </>
                        ) : (
                          <span style={{ opacity: 0.5 }}>—</span>
                        )}
                      </td>
                      <td>
                        <PipelineDots row={v} />
                      </td>
                      <td>
                        <FiscalBadgesCell row={v} />
                      </td>
                      <td className="vd-pay">
                        <div className="vd-pay-top">
                          <span>{v.payment_method_label ?? '—'}</span>
                          {v.installments > 1 && <span className="vd-inst">{v.installments}×</span>}
                        </div>
                        <div className="vd-pay-sla">
                          <SaleSlaPill row={v} compact />
                        </div>
                      </td>
                      <td className="vd-total">{fmt(v.final_total)}</td>
                      <td>
                        <span
                          className="os-stage"
                          style={{
                            background: ps.bg,
                            color: ps.fg,
                          }}
                        >
                          {ps.label}
                        </span>
                        <div className="vd-row-actions" onClick={(e) => e.stopPropagation()}>
                          {/* US-SELL-042 — botão "Pagar" inline (sem abrir drawer) pra
                              vendas com saldo devedor. Equivalente ao "Add Payment" do
                              UltimatePOS Blade legacy que Larissa biz=4 sentiu falta. */}
                          {v.payment_status !== 'paid' && (
                            <button
                              className="vd-row-act"
                              title="Registrar pagamento"
                              type="button"
                              onClick={() => setPayDialog({
                                saleId: v.id,
                                invoiceNo: v.invoice_no,
                                dueAmount: Math.max(0, v.final_total - v.total_paid),
                              })}
                            >
                              <DollarSign size={11} />
                            </button>
                          )}
                          {v.fiscal_status === 'autorizada' && (
                            <button className="vd-row-act" title="Baixar DANFE PDF" type="button">
                              <Archive size={11} />
                            </button>
                          )}
                          {v.fiscal_status === 'autorizada' && (
                            <button className="vd-row-act" title="Baixar XML" type="button">
                              <FileText size={11} />
                            </button>
                          )}
                          <button className="vd-row-act" title="Imprimir recibo (R)" type="button">
                            <Printer size={11} />
                          </button>
                        </div>
                      </td>
                      {/* US-SELL-COWORK-COMMISSION — célula Comissão (gap PR #1043).
                          Truncate 12 chars + tooltip nome completo; "—" quando sem comissionado. */}
                      {props.coworkCommissionEnabled && (
                        <td className="vd-commission">
                          {v.commission_agent_name ? (
                            <span
                              className="vd-commission-name"
                              title={v.commission_agent_name}
                              style={{
                                display: 'inline-block',
                                maxWidth: 108,
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                                verticalAlign: 'middle',
                              }}
                            >
                              {v.commission_agent_name.length > 12
                                ? v.commission_agent_name.slice(0, 12) + '…'
                                : v.commission_agent_name}
                            </span>
                          ) : (
                            <span style={{ opacity: 0.5 }}>—</span>
                          )}
                        </td>
                      )}
                    </tr>
                  );
                })}
              {!loading && filtered.length === 0 && (
                <tr>
                  {/* US-SELL-COWORK-COMMISSION — colSpan dinâmico (10 base + 1 se Comissão habilitada). */}
                  <td colSpan={props.coworkCommissionEnabled ? 11 : 10} className="os-empty">
                    {savedViewId === 'atrasadas' && (
                      <>
                        <b>Tudo dentro do prazo ✓</b>
                        <br />
                        <small>Nenhuma venda atrasada. Bom trabalho.</small>
                      </>
                    )}
                    {savedViewId === 'rejeitadas' && (
                      <>
                        <b>Zero rejeições da SEFAZ ✓</b>
                        <br />
                        <small>Todos os documentos fiscais autorizados.</small>
                      </>
                    )}
                    {!['atrasadas', 'rejeitadas'].includes(savedViewId) && (
                      <>
                        Nenhuma venda encontrada. Use <kbd>N</kbd> pra criar ou <kbd>⌘K</kbd> pra buscar.
                      </>
                    )}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        )}

        {/* Pagination compacta (preserva contrato US-SELL-008) */}
        {meta && meta.last_page > 1 && (
          <div className="vd-pagi">
            <span>
              {meta.from ?? 0}–{meta.to ?? 0} de {meta.total}
            </span>
            <button
              type="button"
              className="vd-pagi-btn"
              disabled={meta.current_page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
            >
              ← Anterior
            </button>
            <span>
              {meta.current_page} / {meta.last_page}
            </span>
            <button
              type="button"
              className="vd-pagi-btn"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
            >
              Próxima →
            </button>
          </div>
        )}

        {/* BULK action bar */}
        {selectedIds.size > 0 && (
          <div className="vd-bulk on">
            <span className="vd-bulk-ct">{selectedIds.size} selecionada{selectedIds.size > 1 ? 's' : ''}</span>
            <button className="vd-bulk-btn primary" type="button">
              <Folder size={11} />
              Emitir NF-e em lote
            </button>
            <button className="vd-bulk-btn" type="button">
              <CheckCircle2 size={11} />
              Marcar como pagas
            </button>
            <button className="vd-bulk-btn" type="button">
              <Archive size={11} />
              Exportar XML/PDF
            </button>
            <button
              className="vd-bulk-close"
              onClick={() => setSelectedIds(new Set())}
              aria-label="Limpar seleção"
              type="button"
            >
              ✕
            </button>
          </div>
        )}

        {/* CHEAT-SHEET (?) */}
        {cheatOpen && <SellsCheatSheet onClose={() => setCheatOpen(false)} />}

        {/* ⌘K palette — versão simplificada (backend search + recent + actions) */}
        {palOpen && (
          <div className="vd-pal-bd on" onClick={() => setPalOpen(false)}>
            <div className="vd-pal" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Busca">
              <div className="vd-pal-in">
                <Search size={16} />
                <input
                  autoFocus
                  placeholder="Buscar venda, cliente, chave SEFAZ, ações…"
                  value={palQ}
                  onChange={(e) => {
                    setPalQ(e.target.value);
                    setPalSel(0);
                  }}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' && palQ.trim()) {
                      setSearchInput(palQ.trim());
                      setPalOpen(false);
                    }
                    if (e.key === 'Escape') setPalOpen(false);
                  }}
                />
                <kbd>esc</kbd>
              </div>
              <div className="vd-pal-list">
                <div className="vd-pal-grp">Buscar</div>
                <div
                  className={`vd-pal-it ${palSel === 0 ? 'sel' : ''}`}
                  onClick={() => {
                    if (palQ.trim()) {
                      setSearchInput(palQ.trim());
                      setPalOpen(false);
                    }
                  }}
                >
                  <span className="vd-pal-ic">
                    <Search size={14} />
                  </span>
                  <span className="vd-pal-tx">
                    <b>Buscar em "{palQ || '…'}"</b>
                    <small>Cliente · ID · chave SEFAZ (44 dígitos)</small>
                  </span>
                  <kbd>↵</kbd>
                </div>
                {palQ.trim() && filtered.length > 0 && (
                  <>
                    <div className="vd-pal-grp">✦ Inteligência</div>
                    <button
                      type="button"
                      className="vd-pal-ai-cta"
                      onClick={() => {
                        const firstId = filtered[0]?.id;
                        if (firstId != null) {
                          setAiTriggered(true);
                          setPalOpen(false);
                          setPalQ('');
                          setOpenSaleId(firstId);
                        }
                      }}
                      title="Abrir a 1ª venda visível + painel IA"
                    >
                      <span className="vd-pal-ai-ic">✦</span>
                      <span className="vd-pal-ai-tx">
                        <b>Perguntar à IA</b>
                        <small>
                          sobre "<i>{palQ}</i>" — abre a 1ª venda visível com painel ✦ ativo
                        </small>
                      </span>
                      <kbd>↵</kbd>
                    </button>
                  </>
                )}
                <div className="vd-pal-grp">Ações</div>
                <a className="vd-pal-it" href="/sells/create">
                  <span className="vd-pal-ic">
                    <Plus size={14} />
                  </span>
                  <span className="vd-pal-tx">
                    <b>Nova venda</b>
                    <small>Drawer de cadastro completo</small>
                  </span>
                  <kbd>N</kbd>
                </a>
                <a className="vd-pal-it" href="/sells/quotations">
                  <span className="vd-pal-ic">
                    <Folder size={14} />
                  </span>
                  <span className="vd-pal-tx">
                    <b>Ir pra Orçamentos</b>
                    <small>Lista de orçamentos abertos</small>
                  </span>
                </a>
              </div>
              <div className="vd-pal-ft">
                <span>
                  <kbd>↵</kbd> abrir
                </span>
                <span className="vd-pal-prefix">
                  <kbd>#</kbd> ID · <kbd>@</kbd> vendedor · <kbd>$</kbd> valor · <kbd>/</kbd> ações
                </span>
                <span>
                  <kbd>esc</kbd> fechar
                </span>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* DRAWER detalhe + painel ✦ IA (Cowork Onda 2 R2 IA) */}
      <SaleSheet
        saleId={openSaleId}
        open={openSaleId != null}
        onOpenChange={(open) => {
          if (!open) {
            setOpenSaleId(null);
            setAiTriggered(false);
          }
        }}
        onSaleChanged={() => setRefetchToken((t) => t + 1)}
        initialAiOpen={aiTriggered}
      />

      {/* US-SELL-042 — modal "Registrar pagamento" inline (atalho rápido sem
          abrir o drawer). Lista e Grade Avançada disparam via setPayDialog. */}
      <QuickPaymentDialog
        saleId={payDialog?.saleId ?? null}
        invoiceNo={payDialog?.invoiceNo ?? ''}
        dueAmount={payDialog?.dueAmount ?? 0}
        open={payDialog !== null}
        onClose={() => setPayDialog(null)}
        onSuccess={() => setRefetchToken((t) => t + 1)}
      />
    </div>
  );
}

SellsIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
