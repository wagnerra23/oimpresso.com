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
  BarChart3,
  Calendar,
  CheckCircle2,
  ChevronDown,
  DollarSign,
  FileText,
  Folder,
  Plus,
  Printer,
  Search,
  SlidersHorizontal,
} from 'lucide-react';
import SaleSheet from './_components/SaleSheet';
import QuickPaymentPopover from './_components/QuickPaymentPopover';
import VdBulkEmitModal, { type BulkEmitItem } from './_components/VdBulkEmitModal';
import { toast } from 'sonner';
// PR follow-up Cowork — filtros legacy reintegrados via barra colapsável "Filtros avançados ▾".
// Refs: Index.charter.md v2 Goals · feedback-design-literal-copy §How to apply #5.
import SellsDateFilter, {
  computePresetRange,
  type DateFilterPreset,
} from './_components/SellsDateFilter';
import SellsTabsVisao, { type SellsVisao } from './_components/SellsTabsVisao';
import SellsTabelaUnificada, {
  COLUMNS_OPERACIONAL,
  COLUMNS_FINANCEIRA,
  COLUMNS_PRODUCAO,
  type ColumnId,
  type SaleRow as UnifiedSaleRow,
} from './_components/SellsTabelaUnificada';
import SellsCheatSheet, { SELLS_INDEX_SHORTCUTS } from './_components/SellsCheatSheet';
// Insights Jana movido pra /ia/dashboard (Jana V2 canon). Antes era tab embutida aqui via
// `viewMode === 'insights'` + SellsInsightsView. Ver Modules/Jana/Http/Controllers/DashboardController
// + resources/js/Pages/Jana/components/JanaCockpitV2.tsx.

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
  // Integração Vendas × Oficina (Onda 3+4 · ADR 0192) — payload `/sells-list-json`
  // devolve desde Onda 2 commit e98649989. source default 'balcao' retroativo
  // (migration default · zero breaking change com vendas legacy).
  source?: 'balcao' | 'oficina' | 'online' | string;
  source_label?: string;
  os_ref?: string | null;
  /** ADR 0251 — placa do veículo (venda direta de oficina). null = sem veículo. */
  vehicle_plate?: string | null;
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
  // PR #1666 — 5º KPI PIX hoje (paridade prototipo Cowork).
  pixHojeTotal?: number;
  faturadoHojeTotal?: number;
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
export function classifyPill(r: SaleRow): PillKey {
  if (r.fiscal_status === 'cancelada') return 'cancelada';
  if (r.fiscal_status === 'autorizada') return 'faturada';
  if (r.payment_status === 'paid') return 'paga';
  return 'pendente';
}

// avatar palette index by seller_id (consistent across renders).
const AVATAR_PALETTES = ['a', 'b', 'c', 'd', 'e', 'f'] as const;
export function avatarPaletteFor(sellerId: number | null): string {
  if (sellerId == null) return AVATAR_PALETTES[0];
  return AVATAR_PALETTES[sellerId % AVATAR_PALETTES.length] ?? AVATAR_PALETTES[0];
}

// ──────────────────────────────────────────────────────────────
// SLA pill — pílula fresco/atrasando/estourado/paga
// ──────────────────────────────────────────────────────────────
export function SaleSlaPill({ row, compact = false }: { row: SaleRow; compact?: boolean }): ReactNode {
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
export function PipelineDots({ row }: { row: SaleRow }): ReactNode {
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

export function FiscalBadgesCell({ row }: { row: SaleRow }): ReactNode {
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
// Cheat sheet overlay (?) — extraído pra _components/SellsCheatSheet.tsx
// (gap P3 #12 KB-9.75 Cowork bundle 2026-05-26). Lista canônica de atalhos
// em SELLS_INDEX_SHORTCUTS no mesmo módulo.
// ──────────────────────────────────────────────────────────────

// ──────────────────────────────────────────────────────────────
// Saved views — Hoje / Pendentes / Atrasadas / Rejeitadas / Faturadas / Favoritas
// (mock simplificado — backend não implementa view filter ainda; client-side)
// ──────────────────────────────────────────────────────────────
type SavedViewId = 'hoje' | 'pendentes' | 'aguardando-faturamento' | 'atrasadas' | 'rejeitadas' | 'faturadas' | 'todas';
interface SavedView {
  id: SavedViewId;
  label: string;
  filter: (r: SaleRow) => boolean;
}
// KB-9.75 P1 gap #7: "Aguardando faturamento" — pedidos confirmados sem NF emitida.
// Glossário BR (gap #6): faturar = emitir NF + título no contas a receber.
// Critério: payment_status !== 'paid' AND fiscal_status NULL — pedido pronto mas
// sem NF ainda. Permite a Larissa filtrar a fila de "preciso emitir NF" rapidamente.
const SAVED_VIEWS: SavedView[] = [
  { id: 'hoje',                    label: 'Pendentes pgto.',         filter: (r) => r.payment_status !== 'paid' },
  { id: 'pendentes',               label: 'Pendentes',               filter: (r) => r.payment_status === 'due' || r.payment_status === 'partial' },
  { id: 'aguardando-faturamento',  label: 'Aguardando faturamento',  filter: (r) => r.payment_status !== 'paid' && (r.fiscal_status === null || r.fiscal_status === undefined) },
  { id: 'atrasadas',               label: 'Atrasadas',               filter: (r) => r.sla_kind === 'overdue' },
  { id: 'rejeitadas',              label: 'NF-e rejeitadas',         filter: (r) => r.fiscal_status === 'rejeitada' },
  { id: 'faturadas',               label: 'Faturadas (mês)',         filter: (r) => r.fiscal_status === 'autorizada' },
  { id: 'todas',                   label: 'Todas',                   filter: () => true },
];

// ──────────────────────────────────────────────────────────────
// MAIN — SellsIndex
// ──────────────────────────────────────────────────────────────
export default function SellsIndex(props: SellsIndexPageProps): ReactNode {
  // (authUserName + businessName + businessIdShared removidos junto com o render
  // Insights Jana — usavam-se só pra header tenant. Veja /ia/dashboard via Jana V2.)
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

  // Onda 4 (ADR 0192) — saved tree branch "Por origem ▾" no dropdown Visões.
  // Filhos Balcão/Oficina/Online filtram client-side; persiste em
  // localStorage Tier 0 per-business (Larissa biz=4 ≠ Wagner biz=1).
  // Vazio = sem filtro de origem (semântica neutra). Felipe (mecânico) abre
  // direto em 'oficina' via setting backend `user.profile_default === 'mecanico'`
  // — UI-only, sem ACL hard (ADR 0192 decisão Wagner).
  type VisaoOrigemKind = '' | 'balcao' | 'oficina' | 'online';
  const [visaoOrigem, setVisaoOrigem] = useState<VisaoOrigemKind>(() => {
    const v = ls.get('visao_origem', '');
    return (['', 'balcao', 'oficina', 'online'] as const).includes(v as VisaoOrigemKind)
      ? (v as VisaoOrigemKind)
      : '';
  });
  useEffect(() => ls.set('visao_origem', visaoOrigem), [visaoOrigem]);
  // Branch tree expand/collapse state (não persiste — UX volátil).
  const [origemExpanded, setOrigemExpanded] = useState<boolean>(false);

  // Tab bar [Dashboard | Insights Jana] removida — Insights migrado pra /ia/dashboard
  // (Jana V2 canon). viewMode state + localStorage `view_mode` removidos. Sells/Index
  // volta a ser single-view (Dashboard) sem mode-switching.

  // Data fetch state.
  const [rows, setRows] = useState<SaleRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [meta, setMeta] = useState<ListMeta | null>(null);
  const [totals, setTotals] = useState<TotalsSummary | null>(null);
  const [openSaleId, setOpenSaleId] = useState<number | null>(null);

  // PR follow-up Cowork — filtros legacy preservados US-SELL-018/021.
  // preset (Dia/Semana/Mês/Ano/Personalizado/all),
  // dateField (7 opções: emissão/atualização/nfe/faturamento/envio/competência/prometido),
  // sortKey/Dir.
  // viewMode + groupBy + Grade Avançada REMOVIDOS 2026-05-21 (cleanup pós-Onda Unificação,
  // ADR 0178). Conceito de "Lista vs Grade Avançada" eliminado — tabs Visão atendem.

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

  // US-SELL-042 / Onda Unificação PR5 — Larissa @ ROTA LIVRE biz=4 (ADR 0105)
  // pediu 2026-05-21 "adicionar pagamentos como antigamente" (botão direto na
  // linha sem abrir drawer). Agora via QuickPaymentPopover anchored em cada
  // row (state local ao componente — sem lift-up).

  // Onda Unificação PR6/6 (ADR 0178) — cutover: tabs Visão sempre ON +
  // SellsTabelaUnificada sempre renderizada (Lista inline aposentada via
  // viewMode legado preservado pra fallback "Grade Avançada" durante 30d).
  // Migration silenciosa localStorage `viewMode → visao` (PR1 #1311 mantém
  // chaves Tier 0 per-business `oimpresso.sells.b<bizId>.*`).
  const [visao, setVisao] = useState<SellsVisao>(() => {
    // Migration: se já tem chave visao salva, usa. Senão, deriva do viewMode
    // legado: 'lista' → 'operacional' (default), 'grade-avancada' → 'financeira'
    // (heurística — quem usava Grade buscava Pago/A receber/totalizador).
    const stored = ls.get('visao', '');
    if (stored && (['operacional', 'financeira', 'producao'] as const).includes(stored as SellsVisao)) {
      return stored as SellsVisao;
    }
    const legacyViewMode = ls.get('viewMode', 'lista');
    if (legacyViewMode === 'grade-avancada') return 'financeira';
    return 'operacional';
  });
  useEffect(() => ls.set('visao', visao), [visao]);

  // Connect visão → visibleColumns pra SellsTabelaUnificada (ADR 0178). Filtra
  // 'commission' do preset quando setting business.sales_cmsn_agnt = 'disable'.
  const visibleColumns = useMemo<ColumnId[]>(() => {
    const preset =
      visao === 'financeira' ? COLUMNS_FINANCEIRA :
      visao === 'producao' ? COLUMNS_PRODUCAO :
      COLUMNS_OPERACIONAL;
    return props.coworkCommissionEnabled
      ? preset
      : preset.filter((c) => c !== 'commission');
  }, [visao, props.coworkCommissionEnabled]);

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

  // KB-9.75 P0 #4 bulk emit modal state — wire-up BulkActionBar onClick
  // (bug-fix smoke real 2026-05-26 — PR #1644 entregou componente mas wire-up
  // incompleto deixou 3 botões decorativos sem handler).
  const [openBulk, setOpenBulk] = useState(false);
  const [bulkKind, setBulkKind] = useState<'nfe' | 'nfse'>('nfe');

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
    // Onda 4 (ADR 0192) — saved tree "Por origem" filtra cliente-side por source.
    if (visaoOrigem) out = out.filter((r) => (r.source ?? 'balcao') === visaoOrigem);
    return out;
  }, [rows, currentSavedView, pillFilter, visaoOrigem]);

  // Onda 4 (ADR 0192) — contadores por source dos rows server-loaded.
  // Alimenta os filhos do branch "Por origem" no dropdown saved tree e o
  // breakdown line do KPI hero quando foco='faturamento'.
  const sourceCounts = useMemo(() => {
    const acc = { balcao: 0, oficina: 0, online: 0 };
    rows.forEach((r) => {
      const k = (r.source ?? 'balcao') as 'balcao' | 'oficina' | 'online';
      if (k === 'oficina' || k === 'online' || k === 'balcao') acc[k]++;
    });
    return acc;
  }, [rows]);

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

  // Onda 4 (ADR 0192) — breakdown por source do KPI hero "Faturado hoje".
  // Soma final_total dos rows do dia agrupado por source. Renderiza apenas
  // quando foco='faturamento' E há pelo menos 1 row de oficina OU online
  // (paridade Cowork: condicional pra não poluir tela em biz só-balcão).
  const kpiTodayBySource = useMemo(() => {
    const acc = { balcao: 0, oficina: 0, online: 0 };
    rows.forEach((r) => {
      if (r.transaction_date?.slice(0, 10) !== todayIso) return;
      const k = (r.source ?? 'balcao') as 'balcao' | 'oficina' | 'online';
      if (k === 'oficina' || k === 'online' || k === 'balcao') acc[k] += r.final_total;
    });
    return acc;
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

  // KB-9.75 P0 #4 — mapeia selecionadas → BulkEmitItem pro VdBulkEmitModal.
  // Lê rows (state local feed via /sells-list-json) + filtra pelos IDs selecionados.
  const buildBulkItems = useCallback((): BulkEmitItem[] => {
    return rows
      .filter((r) => selectedIds.has(r.id))
      .map((r) => ({
        id: r.id,
        invoice_no: r.invoice_no,
        customer_name: r.customer_name,
        kind: bulkKind,
      }));
  }, [rows, selectedIds, bulkKind]);

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

  // Onda 4 (ADR 0192) — listener cross-módulo `oimpresso:open-venda`.
  // Worker B (Onda 5) Repair drawer card "Esta OS gerou venda #V-NNNN" dispara
  // `window.dispatchEvent(new CustomEvent('oimpresso:open-venda', { detail: { venda_id } }))`
  // quando user clica "Abrir #V-NNNN". Aqui Sells/Index escuta + abre o drawer
  // SaleSheet da venda derivada. Sem fetch redundante — backend já filtra
  // tenancy via /sells/{id}/sheet-data (Tier 0 ADR 0093).
  useEffect(() => {
    if (typeof window === 'undefined') return;
    const onOpenVenda = (e: Event) => {
      const detail = (e as CustomEvent<{ venda_id?: number; vendaId?: number; id?: number }>).detail;
      // Tolera 3 nomes de campo pro contrato eventual ficar resiliente.
      const vendaId = detail?.venda_id ?? detail?.vendaId ?? detail?.id ?? null;
      if (vendaId != null && Number.isFinite(vendaId)) {
        setOpenSaleId(Number(vendaId));
      }
    };
    window.addEventListener('oimpresso:open-venda', onOpenVenda as EventListener);
    return () => window.removeEventListener('oimpresso:open-venda', onOpenVenda as EventListener);
  }, []);

  // Onda 6 (ADR 0192) — deep-link `?open=ID` cross-página.
  // Sells/Caixa/Index navega `router.visit('/sells?open=ID')` quando user clica
  // link `↗ #OS-NNNN` na seção "Por origem". Pattern padrão deep-link Inertia.
  useEffect(() => {
    if (typeof window === 'undefined') return;
    const params = new URLSearchParams(window.location.search);
    const openParam = params.get('open');
    if (openParam) {
      const id = Number(openParam);
      if (Number.isFinite(id) && id > 0) {
        setOpenSaleId(id);
      }
    }
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
        {/* HEADER linha 1: h1 + CTA primário (busca ⌘K movida pra barra de
            tabs ao lado de Filtros avançados — Wagner 2026-05-21). */}
        <header className="os-head vd-head-clean">
          <div className="os-head-l">
            <h1>Vendas</h1>
            {/* PR 1666 — header subtitle métrica live (paridade prototipo Cowork).
                Substitui string estática por agregados do payload atual.
                Fallback elegante quando rows ainda não carregaram. */}
            <p>
              {rows.length > 0 ? (
                <>
                  <strong>{rows.length}</strong> vendas
                  {' · '}
                  <strong>{fmtShort(rows.reduce((acc, r) => acc + (Number(r.final_total) || 0), 0))}</strong> faturado
                  {(() => {
                    const overdue = rows.filter((r) => r.sla_kind === 'overdue').length;
                    return overdue > 0 ? (
                      <>
                        {' · '}
                        <strong className="vd-delta-dn">{overdue}</strong> estouradas
                      </>
                    ) : null;
                  })()}
                </>
              ) : (
                'Pedidos · faturamento · NF-e/NFS-e'
              )}
            </p>
            {/* Tab bar Dashboard | Insights Jana removida — Insights V2 migrado pra
                /ia/dashboard (canon Jana). */}
          </div>

          <div className="os-head-r">
            {props.permissions.create && (
              <a className="os-btn primary" href="/sells/create">
                <Plus size={11} />
                Nova venda <kbd className="kbd-hint">N</kbd>
              </a>
            )}
          </div>
        </header>

        {/* Render do Dashboard (Insights Jana migrado pra /ia/dashboard). */}
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
                  {/* Onda 4 (ADR 0192) — branch expansível "Por origem ▾" com
                      filhos Balcão/Oficina/Online + contadores derivados de
                      sourceCounts. Filtro client-side via visaoOrigem state +
                      localStorage Tier 0 per-business `oimpresso.sells.b*.visao_origem`.
                      Só renderiza filhos se há pelo menos 1 row da origem. */}
                  <div className="vd-views-sep" />
                  <div
                    className={`vd-tree-row l0 ${visaoOrigem ? 'active' : ''}`}
                    onClick={(e) => {
                      // Click no label: limpa filtro (toggle off) ou expande pra ver filhos.
                      if (visaoOrigem) {
                        setVisaoOrigem('');
                      } else {
                        setOrigemExpanded((v) => !v);
                      }
                      e.stopPropagation();
                    }}
                  >
                    <span
                      className={`vd-tree-arr ${origemExpanded ? 'open' : ''}`}
                      onClick={(e) => {
                        e.stopPropagation();
                        setOrigemExpanded((v) => !v);
                      }}
                    >
                      ›
                    </span>
                    <span className="vd-tree-lbl">Por origem</span>
                    <span className="ct">{rows.length}</span>
                  </div>
                  {origemExpanded &&
                    (['balcao', 'oficina', 'online'] as const)
                      .filter((k) => sourceCounts[k] > 0)
                      .map((k) => {
                        const label = k === 'balcao' ? 'Balcão' : k === 'oficina' ? 'Oficina' : 'Online';
                        const isActiveChild = visaoOrigem === k;
                        return (
                          <div
                            key={k}
                            className={`vd-tree-row l1 ${isActiveChild ? 'active' : ''}`}
                            onClick={() => {
                              setVisaoOrigem(k);
                              setViewsOpen(false);
                            }}
                          >
                            <span className="vd-tree-arr empty" />
                            <span className="vd-tree-lbl">
                              <span className={`vd-src-dot vd-src-${k}`} style={{ display: 'inline-block', marginRight: 6 }} />
                              {label}
                            </span>
                            <span className="ct">{sourceCounts[k]}</span>
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
                    { id: 'caixa',      label: 'Caixa do dia', href: '/vendas/caixa' },
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
            <span className="os-kpi-label">
              Faturado hoje
              {/* Onda 4 (ADR 0192) — tag "· todas origens" só aparece quando
                  Foco=Faturamento (paridade Cowork). Sinaliza que o breakdown
                  abaixo distribui o total por source (Balcão/Oficina/Online). */}
              {foco === 'faturamento' && (kpiTodayBySource.oficina > 0 || kpiTodayBySource.online > 0) && (
                <small className="vd-kpi-tag"> · todas origens</small>
              )}
            </span>
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
            {/* Onda 4 (ADR 0192) — breakdown por source quando Foco=Faturamento
                E há pelo menos 1 venda oficina OU online hoje (evita poluir
                tela em biz só-balcão · paridade Cowork vendas-page.jsx L817-838). */}
            {foco === 'faturamento' && (kpiTodayBySource.oficina > 0 || kpiTodayBySource.online > 0) && (
              <div className="vd-kpi-breakdown">
                {kpiTodayBySource.balcao > 0 && (
                  <div className="vd-kpi-b balcao">
                    <small>● Balcão</small>
                    <b>{fmtShort(kpiTodayBySource.balcao)}</b>
                  </div>
                )}
                {kpiTodayBySource.oficina > 0 && (
                  <div className="vd-kpi-b oficina">
                    <small>● Oficina</small>
                    <b>{fmtShort(kpiTodayBySource.oficina)}</b>
                  </div>
                )}
                {kpiTodayBySource.online > 0 && (
                  <div className="vd-kpi-b online">
                    <small>● Online</small>
                    <b>{fmtShort(kpiTodayBySource.online)}</b>
                  </div>
                )}
              </div>
            )}
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

          {/* PR 1666 — 5º KPI PIX hoje (paridade prototipo Cowork).
              Sempre visível independente do foco — mostra share de PIX
              no faturamento do dia. Critical pra Larissa biz=4 (vestuário PIX-first). */}
          <div className="os-kpi" title="PIX hoje · share % do faturamento do dia">
            <span className="os-kpi-label">PIX hoje</span>
            <span className="os-kpi-value">
              {fmtShort(props.coworkAggregates?.pixHojeTotal ?? 0)}
            </span>
            <span className="os-kpi-sub">
              {(() => {
                const pix = props.coworkAggregates?.pixHojeTotal ?? 0;
                const fat = props.coworkAggregates?.faturadoHojeTotal ?? 0;
                if (!props.coworkAggregates) return 'carregando…';
                if (fat <= 0) return 'sem faturamento hoje';
                const pct = Math.round((pix / fat) * 100);
                return `${pct}% do faturamento — imediato`;
              })()}
            </span>
          </div>
        </div>

        {/* Fix bug 2026-05-18: hint visível pra filter de data ativo (proteção
            contra localStorage stale que escondia vendas dos últimos dias). */}
        {dateFilterActive && (
          <div className={'vd-date-filter-hint' + (dateFilterStale ? ' stale' : '')} role="status">
            <span className="vd-date-filter-hint-ic">{dateFilterStale ? '⚠' : <Calendar size={12} />}</span>
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
            {/* ADR 0178 — tabs Visão sempre visíveis (Operacional/Financeira/Produção). */}
            <SellsTabsVisao visao={visao} onChange={setVisao} />
            {/* Busca ⌘K movida do header pra cá 2026-05-21 (Wagner) —
                fica próxima dos filtros, contextualmente coerente. */}
            <button className="vd-cmdk" onClick={() => setPalOpen(true)} type="button">
              <Search size={12} />
              <span>Buscar venda, cliente, chave SEFAZ…</span>
              <kbd>⌘K</kbd>
            </button>
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
          </div>
        )}

        {/* TABLE — SellsTabelaUnificada com visibleColumns derivado da tab Visão
            (ADR 0178). Grade Avançada + toggle Lista/Grade Avançada deletados
            2026-05-21 (cleanup pós-Onda Unificação — Wagner aprovou delete).
            Onda 3+4 (ADR 0192): coluna Origem entra no preset Operacional/Produção
            + onPickOs navega pra Repair quando user clica ↗ #OS-NNNN. */}
        <div className="os-table-wrap">
          <SellsTabelaUnificada
            rows={filtered as UnifiedSaleRow[]}
            loading={loading}
            visibleColumns={visibleColumns}
            selectedIds={selectedIds}
            favSet={favSet}
            focusIdx={focusIdx}
            filteredCount={filtered.length}
            rowsRef={rowsRef}
            onToggleSel={toggleSel}
            onToggleAll={toggleAll}
            onRowClick={(id, ri) => { setFocusIdx(ri); setOpenSaleId(id); }}
            onPaySuccess={() => setRefetchToken((t) => t + 1)}
            onPickOs={(osRef) => {
              // Cross-módulo Onda 3 + extensão ServiceOrderObserver 2026-05-25:
              // Roteia por prefix do os_ref pra evitar levar SO-NNNN pra Repair
              // (que só conhece JobSheet · resultaria em kanban vazio).
              //   OS-{id}  → Modules/Repair/JobSheet         → /repair/producao-oficina
              //   SO-{id}  → Modules/OficinaAuto/ServiceOrder → /oficina-auto/ordens-servico/board (ADR 0265)
              const isOficinaAuto = osRef.startsWith('SO-');
              const targetPath = isOficinaAuto
                ? '/oficina-auto/ordens-servico/board'
                : '/repair/producao-oficina';
              window.location.href = `${targetPath}?os=${encodeURIComponent(osRef)}`;
            }}
          />
        </div>

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
            <button
              className="vd-bulk-btn primary"
              type="button"
              onClick={() => {
                setBulkKind('nfe');
                setOpenBulk(true);
              }}
            >
              <Folder size={11} />
              Emitir NF-e em lote
            </button>
            <button
              className="vd-bulk-btn"
              type="button"
              onClick={() => toast.info('Marcar como pagas em lote · Em breve V2')}
            >
              <CheckCircle2 size={11} />
              Marcar como pagas
            </button>
            <button
              className="vd-bulk-btn"
              type="button"
              onClick={() => toast.info('Exportar XML/PDF em lote · Em breve V2')}
            >
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
        <SellsCheatSheet
          open={cheatOpen}
          onClose={() => setCheatOpen(false)}
          shortcuts={SELLS_INDEX_SHORTCUTS}
          title="Atalhos · Lista de vendas"
          footerLeft="Atalhos persistem em toda sub-rota de Vendas (Lista · Caixa · Devoluções · Comissões · Relatórios)."
        />

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

      {/* KB-9.75 P0 #4 — Faturar em lote NF-e/NFS-e (wire-up bug-fix 2026-05-26).
          VdBulkEmitModal monta só quando openBulk=true; items derivados de selectedIds. */}
      <VdBulkEmitModal
        open={openBulk}
        items={buildBulkItems()}
        onClose={() => setOpenBulk(false)}
        onCompleted={(okCount, badCount) => {
          if (okCount > 0) setRefetchToken((t) => t + 1);
          if (badCount === 0) setSelectedIds(new Set());
        }}
      />

      {/* QuickPaymentPopover agora vive ANCORADO em cada row (state local). O
          render global do antigo QuickPaymentDialog foi removido — popover é
          mais ergonômico (preserva contexto da linha; Esc/click-outside close
          via Radix primitive). Dialog mantido @deprecated em _components/. */}
    </div>
  );
}

SellsIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
