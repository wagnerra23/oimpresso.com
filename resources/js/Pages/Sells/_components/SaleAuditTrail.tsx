// SaleAuditTrail — Cowork KB-9.75 Sells Onda 3 R3 Curadoria
// (histórico de edições + emissões fiscais + transições FSM).
//
// Refs:
//  - prototipo-ui/prototipos/sells-index/vendas-curation.jsx (canonical source)
//  - sale_stage_history table (ADR 0143 FSM Pipeline live biz=1)
//  - SellController::sheetData (futuro: retornará audit_trail real)
//
// Esta Onda: render frontend determinístico baseado no SaleDetail (cria/edita/
// emite). Onda 3.5 plugará em sale_stage_history + transaction audit log real.

import { useMemo, type ReactNode } from 'react';
import { Plus, Pencil, FileText, AlertTriangle, CheckCircle2 } from 'lucide-react';

interface AuditEntry {
  when: string;
  who: string;
  kind: 'create' | 'edit' | 'fiscal' | 'reject' | 'payment';
  desc: string;
  diff?: { from: number; to: number; pct: number };
}

interface SaleAuditInput {
  id: number;
  invoice_no: string;
  transaction_date: string;
  created_by_name?: string | null;
  lines: Array<{ product_name: string | null; quantity: number; unit_price: number }>;
  payments: Array<{ amount: number; method: string; paid_on: string }>;
  fiscal_status?: 'autorizada' | 'pendente' | 'rejeitada' | 'denegada' | 'cancelada' | null;
  fiscal_modelo?: '55' | '65' | null;
  fiscal_numero?: string | null;
  fiscal_serie?: string | null;
  fiscal_emitted_at?: string | null;
  fiscal_fail_reason?: string | null;
  current_stage_label?: string | null;
}

function buildEntries(venda: SaleAuditInput): AuditEntry[] {
  const entries: AuditEntry[] = [];
  const who = venda.created_by_name ?? '—';
  const itemsCount = venda.lines.length;

  // 1) Criação
  entries.push({
    when: formatDateTime(venda.transaction_date),
    who,
    kind: 'create',
    desc: `Venda registrada · ${itemsCount} ite${itemsCount > 1 ? 'ns' : 'm'}`,
  });

  // 2) Pagamentos registrados (cada um vira entrada)
  venda.payments.forEach((p) => {
    entries.push({
      when: formatDateTime(p.paid_on),
      who: 'sistema',
      kind: 'payment',
      desc: `Pagamento R$ ${p.amount.toLocaleString('pt-BR', { minimumFractionDigits: 2 })} via ${p.method}`,
    });
  });

  // 3) Emissão fiscal (se aplicável)
  if (venda.fiscal_status === 'autorizada' && venda.fiscal_numero) {
    const kind = venda.fiscal_modelo === '65' ? 'NFC-e' : 'NF-e';
    entries.push({
      when: formatDateTime(venda.fiscal_emitted_at ?? venda.transaction_date),
      who: 'sistema',
      kind: 'fiscal',
      desc: `${kind} ${venda.fiscal_numero}/${venda.fiscal_serie ?? '1'} autorizada SEFAZ`,
    });
  } else if (venda.fiscal_status === 'rejeitada') {
    entries.push({
      when: formatDateTime(venda.fiscal_emitted_at ?? venda.transaction_date),
      who: 'sistema',
      kind: 'reject',
      desc: `NF-e rejeitada${venda.fiscal_fail_reason ? `: ${venda.fiscal_fail_reason}` : ''}`,
    });
  }

  // 4) Stage atual FSM (se aplicável)
  if (venda.current_stage_label) {
    entries.push({
      when: formatDateTime(venda.transaction_date),
      who: 'pipeline',
      kind: 'fiscal',
      desc: `Estágio atual: ${venda.current_stage_label}`,
    });
  }

  // Ordena cronologicamente (oldest first).
  return entries.sort((a, b) => a.when.localeCompare(b.when));
}

function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}

const KIND_ICON: Record<AuditEntry['kind'], ReactNode> = {
  create: <Plus size={11} />,
  edit: <Pencil size={11} />,
  fiscal: <FileText size={11} />,
  reject: <AlertTriangle size={11} />,
  payment: <CheckCircle2 size={11} />,
};

const KIND_LABEL: Record<AuditEntry['kind'], string> = {
  create: 'criou',
  edit: 'editou',
  fiscal: 'fiscal',
  reject: 'erro',
  payment: 'pagou',
};

interface Props {
  venda: SaleAuditInput;
}

export default function SaleAuditTrail({ venda }: Props): ReactNode {
  const entries = useMemo(() => buildEntries(venda), [venda]);
  if (entries.length === 0) return null;
  return (
    <div className="vd-audit">
      <div className="vd-audit-h">
        <h4>Histórico</h4>
        <small>{entries.length} entrada{entries.length === 1 ? '' : 's'} · auditoria</small>
      </div>
      <ul className="vd-audit-list">
        {entries.map((e, i) => (
          <li key={i} className={`vd-audit-row vd-audit-${e.kind}`}>
            <span className="vd-audit-ic">{KIND_ICON[e.kind]}</span>
            <div className="vd-audit-body">
              <header>
                <b>{e.who}</b>
                <span className="vd-audit-action">{KIND_LABEL[e.kind]}</span>
                <time>{e.when}</time>
              </header>
              <p>{e.desc}</p>
              {e.diff && (
                <div className="vd-audit-diff">
                  <span className="diff-from">R$ {e.diff.from.toFixed(2)}</span>
                  <span className="diff-arr">→</span>
                  <span className="diff-to">R$ {e.diff.to.toFixed(2)}</span>
                  <span className={`diff-pct ${e.diff.pct < 0 ? 'neg' : 'pos'}`}>
                    {e.diff.pct > 0 ? '+' : ''}
                    {e.diff.pct.toFixed(1)}%
                  </span>
                </div>
              )}
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}

export type { SaleAuditInput };
