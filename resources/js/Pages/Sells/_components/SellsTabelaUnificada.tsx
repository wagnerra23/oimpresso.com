// SellsTabelaUnificada — tabela base que unifica Lista (KB-9.75 Cowork) e Grade
// Avançada num só componente, controlada por `visibleColumns: ColumnId[]`.
//
// ADR 0178 (memory/decisions/0178-sells-unified-tabs-visao-supersede-0136.md)
// Onda Unificação PR3/6 — extract puro deste PR. Não é montado em Index.tsx
// neste PR (Index continua usando markup inline da Lista + SellsGradeAvancada).
// PR4 faz Index renderizar este componente quando `?unificada=1` + connect a
// `visao` → conjunto de colunas pré-set; PR5 (cutover) torna default; PR6
// (cleanup) deleta SellsGradeAvancada legacy.
//
// Helpers visuais (PipelineDots, FiscalBadgesCell, SaleSlaPill, classifyPill,
// avatarPaletteFor) importados de `Index.tsx` (re-exportados neste mesmo PR
// adicionando `export`). Evita duplicação de ~70 LOC.

import type { ReactNode } from 'react';
import { Archive, DollarSign, FileText, Printer } from 'lucide-react';
import {
  PipelineDots,
  FiscalBadgesCell,
  SaleSlaPill,
  classifyPill,
  avatarPaletteFor,
} from '../Index';
import QuickPaymentPopover from './QuickPaymentPopover';
import VdSource, { type VdSourceKind } from './VdSource';

// ──────────────────────────────────────────────────────────────
// TIPOS — sub-conjunto do SaleRow do Index.tsx (mantido independente
// pra evitar circular imports; sincronizar quando shape mudar)
// ──────────────────────────────────────────────────────────────

type SlaKind = 'fresh' | 'warning' | 'overdue' | 'paid';
type PaymentStatus = 'paid' | 'due' | 'partial' | string;
type FiscalStatus = 'pendente' | 'autorizada' | 'rejeitada' | 'denegada' | 'cancelada' | null;
type PillKey = 'todas' | 'paga' | 'pendente' | 'faturada' | 'cancelada';

export interface SaleRow {
  id: number;
  transaction_date: string;
  display_date: string | null;
  invoice_no: string;
  final_total: number;
  total_paid: number;
  payment_status: PaymentStatus;
  customer_name: string | null;
  customer_secondary: string | null;
  location_name: string | null;
  is_overdue: boolean;
  fiscal_status: FiscalStatus;
  fiscal_modelo: '55' | '65' | null;
  current_stage_key: string | null;
  is_grouped_invoice: boolean;
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
  commission_agent_id?: number | null;
  commission_agent_name?: string | null;
  // Integração Vendas × Oficina (Onda 3 · ADR 0192) — backend devolve no payload
  // /sells-list-json desde Onda 2. source default 'balcao' retroativo.
  source?: VdSourceKind | string;
  source_label?: string;
  os_ref?: string | null;
}

// Identificadores canônicos das colunas. Ordem desta enumeração = ordem default
// quando `visibleColumns` lista todas. PR4 vai expor 3 presets por `visao`.
export type ColumnId =
  | 'check'
  | 'invoice'
  | 'date'
  | 'client'
  | 'seller'
  | 'source'
  | 'pipeline'
  | 'fiscal'
  | 'payment'
  | 'location'
  | 'paid'
  | 'due'
  | 'total'
  | 'status'
  | 'commission';

// 3 presets canônicos (ADR 0178) — exportados pra PR4 importar.
// Onda 3 (ADR 0192) — coluna 'source' entre 'seller' e 'pipeline' nas visões
// Operacional/Produção (cross-source signal). Financeira NÃO ganha — foco é
// $$/comissão, não origem (decisão pragmática: 10 cols já no limite 1280px).
export const COLUMNS_OPERACIONAL: ColumnId[] = [
  'check', 'invoice', 'date', 'client', 'seller', 'source', 'pipeline', 'fiscal', 'payment', 'total', 'status',
];
export const COLUMNS_FINANCEIRA: ColumnId[] = [
  'check', 'invoice', 'date', 'client', 'total', 'paid', 'due', 'payment', 'status', 'commission',
];
export const COLUMNS_PRODUCAO: ColumnId[] = [
  'check', 'invoice', 'date', 'client', 'location', 'source', 'pipeline', 'payment', 'total', 'status',
];

// ──────────────────────────────────────────────────────────────
// HELPERS — formatação BRL/data/hora (duplicados localmente; helpers maiores
// vêm via re-export do Index.tsx)
// ──────────────────────────────────────────────────────────────
const fmt = (n: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(n);

const fmtDateDM = (iso: string | null) => {
  if (!iso) return '—';
  const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
  return m ? `${m[3]}/${m[2]}` : iso.slice(0, 5);
};

const fmtTime = (iso: string | null) => {
  if (!iso) return '';
  const m = iso.match(/(\d{2}):(\d{2})/);
  return m ? `${m[1]}:${m[2]}` : '';
};

const PILL_STYLE: Record<PillKey, { bg: string; fg: string; label: string }> = {
  todas: { bg: 'var(--vd-neutral-soft)', fg: 'var(--vd-neutral)', label: '—' },
  paga: { bg: 'var(--vd-ok-soft)', fg: 'var(--vd-ok)', label: 'Paga' },
  pendente: { bg: 'var(--vd-warn-soft)', fg: 'var(--vd-warn)', label: 'Pendente' },
  faturada: { bg: 'var(--accent-soft)', fg: 'var(--accent)', label: 'Faturada' },
  cancelada: { bg: 'var(--bg-2)', fg: 'var(--text-mute)', label: 'Cancelada' },
};

// ──────────────────────────────────────────────────────────────
// COLUMN DEFS — header + cell por ColumnId
// ──────────────────────────────────────────────────────────────
const COL_HEADERS: Record<ColumnId, { label: string; width?: number; style?: React.CSSProperties; className?: string }> = {
  check:      { label: '', width: 24, style: { padding: '0 0 0 12px' } },
  invoice:    { label: 'Venda', width: 82 },
  date:       { label: 'Data', width: 80 },
  client:     { label: 'Cliente' },
  seller:     { label: 'Atendido por', width: 168 },
  source:     { label: 'Origem', width: 138, className: 'vd-col-source' },
  pipeline:   { label: 'Pipeline', width: 128 },
  fiscal:     { label: 'Fiscal', width: 148 },
  payment:    { label: 'Pagamento', width: 128 },
  location:   { label: 'Localização', width: 120 },
  paid:       { label: 'Pago', width: 100 },
  due:        { label: 'A receber', width: 110 },
  total:      { label: 'Total', width: 110 },
  status:     { label: 'Status', width: 88 },
  commission: { label: 'Comissão', width: 120 },
};

// ──────────────────────────────────────────────────────────────
// PROPS
// ──────────────────────────────────────────────────────────────
interface SellsTabelaUnificadaProps {
  rows: SaleRow[];
  loading: boolean;
  visibleColumns: ColumnId[];
  selectedIds: Set<number>;
  favSet: Set<number>;
  focusIdx: number;
  filteredCount: number;
  rowsRef: React.MutableRefObject<Array<HTMLTableRowElement | null>>;
  onToggleSel: (id: number) => void;
  onToggleAll: () => void;
  onRowClick: (id: number, ri: number) => void;
  onPaySuccess: () => void;
  /**
   * Onda 3 (ADR 0192) — callback quando user clica em `↗ #OS-NNNN` na pill VdSource.
   * Default: navega pra `/repair/producao-oficina?os=OS-NNNN`. Onda 5 (Repair drawer)
   * intercepta pra abrir o drawer da OS direto.
   */
  onPickOs?: (osRef: string) => void;
}

// ──────────────────────────────────────────────────────────────
// COMPONENT
// ──────────────────────────────────────────────────────────────
export default function SellsTabelaUnificada({
  rows,
  loading,
  visibleColumns,
  selectedIds,
  favSet,
  focusIdx,
  filteredCount,
  rowsRef,
  onToggleSel,
  onToggleAll,
  onRowClick,
  onPaySuccess,
  onPickOs,
}: SellsTabelaUnificadaProps): ReactNode {
  const colSpan = visibleColumns.length;

  // Default handler: navega pra /repair/producao-oficina com ?os=OS-NNNN.
  // Onda 5 (Worker B) intercepta no Repair pra abrir drawer da OS direto.
  const handlePickOs = (osRef: string): void => {
    if (onPickOs) {
      onPickOs(osRef);
      return;
    }
    if (typeof window !== 'undefined') {
      window.location.href = `/repair/producao-oficina?os=${encodeURIComponent(osRef)}`;
    }
  };

  return (
    <div className="os-table-wrap">
      <table className="os-table vendas-table vd-aplus-table">
        <thead>
          <tr>
            {visibleColumns.map((id) => {
              const h = COL_HEADERS[id];
              if (id === 'check') {
                return (
                  <th key={id} style={h.style ?? { width: h.width }}>
                    <input
                      type="checkbox"
                      checked={filteredCount > 0 && selectedIds.size === filteredCount}
                      onChange={onToggleAll}
                      aria-label="Selecionar todas"
                    />
                  </th>
                );
              }
              return (
                <th
                  key={id}
                  className={h.className}
                  style={h.width ? { width: h.width } : undefined}
                >
                  {h.label}
                </th>
              );
            })}
          </tr>
        </thead>
        <tbody>
          {loading &&
            Array.from({ length: 6 }).map((_, i) => (
              <tr key={`sk${i}`} className="vd-sk-row">
                <td colSpan={colSpan}>
                  <div className="vd-sk-bar" style={{ animationDelay: `${i * 60}ms` }} />
                </td>
              </tr>
            ))}
          {!loading &&
            rows.map((v, ri) => {
              const sel = selectedIds.has(v.id);
              const isFocused = ri === focusIdx;
              const isFav = favSet.has(v.id);
              const isUrgent = v.sla_kind === 'overdue';
              const pill = classifyPill(v);
              const ps = PILL_STYLE[pill] ?? PILL_STYLE.todas;
              const due = Math.max(0, v.final_total - v.total_paid);
              // Onda 3 (ADR 0192) — stripe sutil border-left azul quando origem=oficina
              const isFromOficina = v.source === 'oficina';
              return (
                <tr
                  key={v.id}
                  ref={(el) => { rowsRef.current[ri] = el; }}
                  className={
                    'os-row' +
                    (isUrgent ? ' urgent' : '') +
                    (sel ? ' selected' : '') +
                    (isFocused ? ' row-focused' : '') +
                    (isFromOficina ? ' vd-row-oficina' : '')
                  }
                  data-source={v.source ?? 'balcao'}
                  onClick={() => onRowClick(v.id, ri)}
                >
                  {visibleColumns.map((id) => renderCell(id, v, { sel, isFav, ps, due, onToggleSel, onPaySuccess, onPickOs: handlePickOs }))}
                </tr>
              );
            })}
        </tbody>
      </table>
    </div>
  );
}

// ──────────────────────────────────────────────────────────────
// CELL RENDERERS — switch por ColumnId
// ──────────────────────────────────────────────────────────────
interface CellCtx {
  sel: boolean;
  isFav: boolean;
  ps: { bg: string; fg: string; label: string };
  due: number;
  onToggleSel: (id: number) => void;
  onPaySuccess: () => void;
  onPickOs: (osRef: string) => void;
}

// Renderiza célula por ColumnId. Helpers maiores (PipelineDots etc) vêm via
// re-export do Index.tsx; pequenos (formatBRL, fmtDateDM) ficam locais.
function renderCell(id: ColumnId, v: SaleRow, ctx: CellCtx): ReactNode {
  switch (id) {
    case 'check':
      return (
        <td key={id} className="vd-chk" onClick={(e) => e.stopPropagation()}>
          <input
            type="checkbox"
            checked={ctx.sel}
            onChange={() => ctx.onToggleSel(v.id)}
            aria-label={`Selecionar venda ${v.invoice_no}`}
          />
        </td>
      );
    case 'invoice':
      return (
        <td key={id} className="vd-id">
          {ctx.isFav && <span className="vd-fav" title="Favorita (B)">★</span>}
          #{v.invoice_no}
        </td>
      );
    case 'date':
      return (
        <td key={id} className="vd-date">
          <div>{fmtDateDM(v.display_date ?? v.transaction_date)}</div>
          <div className="vd-time">{fmtTime(v.display_date ?? v.transaction_date)}</div>
        </td>
      );
    case 'client':
      return (
        <td key={id} className="vd-client">
          <div className="vd-client-name">{v.customer_name ?? '—'}</div>
          {v.items_summary && <div className="vd-notes">{v.items_summary}</div>}
        </td>
      );
    case 'seller':
      return (
        <td key={id} className="vd-seller-cell">
          {v.seller_abbr ? (
            <>
              <span className={`vd-av vd-av-${avatarPaletteFor(v.seller_id)}`}>{v.seller_abbr}</span>
              <span className="vd-seller-info">
                <b>{(v.seller_name ?? '').split(' ')[0]}</b>
                <small>{v.seller_origin}</small>
              </span>
            </>
          ) : (
            <span style={{ opacity: 0.5 }}>—</span>
          )}
        </td>
      );
    case 'source':
      // Onda 3 (ADR 0192) — pill colorida Balcão/Oficina/Online + link ↗ #OS-NNNN clicável.
      // Defesa: se backend não devolveu source (rota legacy), default 'balcao' (zero crash).
      return (
        <td key={id} className="vd-col-source" onClick={(e) => e.stopPropagation()}>
          <VdSource
            source={v.source ?? 'balcao'}
            sourceLabel={v.source_label ?? 'Balcão'}
            osRef={v.os_ref ?? null}
            onPickOs={ctx.onPickOs}
          />
        </td>
      );
    case 'pipeline':
      return <td key={id}><PipelineDots row={v as never} /></td>;
    case 'fiscal':
      return <td key={id}><FiscalBadgesCell row={v as never} /></td>;
    case 'payment':
      return (
        <td key={id} className="vd-pay">
          <div className="vd-pay-top">
            <span>{v.payment_method_label ?? '—'}</span>
            {v.installments > 1 && <span className="vd-inst">{v.installments}×</span>}
          </div>
          <div className="vd-pay-sla"><SaleSlaPill row={v as never} compact /></div>
        </td>
      );
    case 'location':
      return <td key={id} className="vd-loc">{v.location_name ?? '—'}</td>;
    case 'paid':
      return <td key={id} className="vd-paid" style={{ textAlign: 'right' }}>{fmt(v.total_paid)}</td>;
    case 'due':
      return <td key={id} className="vd-due" style={{ textAlign: 'right' }}>{fmt(ctx.due)}</td>;
    case 'total':
      return <td key={id} className="vd-total">{fmt(v.final_total)}</td>;
    case 'status':
      return (
        <td key={id}>
          <span className="os-stage" style={{ background: ctx.ps.bg, color: ctx.ps.fg }}>{ctx.ps.label}</span>
          <div className="vd-row-actions" onClick={(e) => e.stopPropagation()}>
            {v.payment_status !== 'paid' && (
              <QuickPaymentPopover
                saleId={v.id}
                invoiceNo={v.invoice_no}
                dueAmount={ctx.due}
                onSuccess={ctx.onPaySuccess}
                trigger={
                  <button className="vd-row-act" title="Registrar pagamento" type="button">
                    <DollarSign size={11} />
                  </button>
                }
              />
            )}
            {v.fiscal_status === 'autorizada' && (
              <button className="vd-row-act" title="Baixar DANFE PDF" type="button"><Archive size={11} /></button>
            )}
            {v.fiscal_status === 'autorizada' && (
              <button className="vd-row-act" title="Baixar XML" type="button"><FileText size={11} /></button>
            )}
            <button className="vd-row-act" title="Imprimir recibo (R)" type="button"><Printer size={11} /></button>
          </div>
        </td>
      );
    case 'commission':
      return (
        <td key={id} className="vd-commission">
          {v.commission_agent_name ? (
            <span
              className="vd-commission-name"
              title={v.commission_agent_name}
              style={{ display: 'inline-block', maxWidth: 108, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', verticalAlign: 'middle' }}
            >
              {v.commission_agent_name.length > 12 ? v.commission_agent_name.slice(0, 12) + '…' : v.commission_agent_name}
            </span>
          ) : (
            <span style={{ opacity: 0.5 }}>—</span>
          )}
        </td>
      );
  }
}
