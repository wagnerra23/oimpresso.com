// SaleAuditTrail — Cowork KB-9.75 Sells Onda 3 R3 Curadoria + Onda 3.5
// (histórico de edições + emissões fiscais + transições FSM REAIS).
//
// Refs:
//  - prototipo-ui/prototipos/sells-index/vendas-curation.jsx (canonical source)
//  - sale_stage_history table (ADR 0143 FSM Pipeline live biz=1)
//  - GET /sells/{sale}/audit → SellAuditController (Onda 3.5)
//
// Modos suportados:
//  1) DETERMINÍSTICO (Onda 3 — default): prop `venda: SaleAuditInput` → render
//     entries derivadas no frontend (create/payment/fiscal/reject).
//  2) REAL FSM (Onda 3.5 — opt-in): prop `realApiUrl` → fetch
//     /sells/{id}/audit e render entries reais de sale_stage_history. Em caso
//     de erro/loading, faz fallback pro modo determinístico (preserva UX).

import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Plus, Pencil, FileText, AlertTriangle, CheckCircle2, GitBranch } from 'lucide-react';

interface AuditEntry {
  when: string;
  who: string;
  kind: 'create' | 'edit' | 'fiscal' | 'reject' | 'payment' | 'fsm';
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

// Payload retornado por SellAuditController (Onda 3.5).
interface FsmHistoryEntry {
  id: number;
  when: string | null;
  from_stage: string | null;
  to_stage: string;
  action: string;
  action_key: string | null;
  user_name: string;
}

interface FsmAuditResponse {
  venda_id: number;
  invoice_no: string;
  count: number;
  history: FsmHistoryEntry[];
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

// Onda 3.5 — converte payload real de sale_stage_history pra AuditEntry.
function fsmHistoryToEntries(history: FsmHistoryEntry[]): AuditEntry[] {
  return history.map((h) => {
    const transitionDesc = h.from_stage
      ? `${h.action} · ${h.from_stage} → ${h.to_stage}`
      : `${h.action} · → ${h.to_stage}`;
    return {
      when: formatDateTime(h.when ?? null),
      who: h.user_name || 'sistema',
      kind: 'fsm' as const,
      desc: transitionDesc,
    };
  });
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
  fsm: <GitBranch size={11} />,
};

const KIND_LABEL: Record<AuditEntry['kind'], string> = {
  create: 'criou',
  edit: 'editou',
  fiscal: 'fiscal',
  reject: 'erro',
  payment: 'pagou',
  fsm: 'pipeline',
};

interface Props {
  venda: SaleAuditInput;
  /**
   * Onda 3.5 — se setado, faz fetch GET nessa URL pra buscar entries reais
   * de sale_stage_history (Controller SellAuditController). Em erro/timeout
   * cai automaticamente no fallback determinístico (preserva UX).
   * Ex: `/sells/123/audit`
   */
  realApiUrl?: string;
}

export default function SaleAuditTrail({ venda, realApiUrl }: Props): ReactNode {
  const fallbackEntries = useMemo(() => buildEntries(venda), [venda]);
  const [realEntries, setRealEntries] = useState<AuditEntry[] | null>(null);
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<boolean>(false);

  useEffect(() => {
    if (!realApiUrl) return;
    let cancelled = false;
    setLoading(true);
    setError(false);
    fetch(realApiUrl, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json() as Promise<FsmAuditResponse>;
      })
      .then((json) => {
        if (cancelled) return;
        const entries = fsmHistoryToEntries(json.history || []);
        setRealEntries(entries);
      })
      .catch(() => {
        if (cancelled) return;
        setError(true);
        setRealEntries(null);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [realApiUrl]);

  // Estratégia de fallback (Onda 3.5):
  //  - sem realApiUrl → sempre determinístico (modo Onda 3 original)
  //  - com realApiUrl + dados reais OK → real
  //  - com realApiUrl + erro → determinístico (UX preservada)
  //  - com realApiUrl + carregando → skeleton
  const usingReal = !!realApiUrl && realEntries !== null && !error;
  const entries = usingReal ? (realEntries as AuditEntry[]) : fallbackEntries;

  if (loading && realApiUrl) {
    return (
      <div className="vd-audit">
        <div className="vd-audit-h">
          <h4>Histórico</h4>
          <small>carregando auditoria FSM…</small>
        </div>
        <ul className="vd-audit-list">
          <li className="vd-audit-row vd-audit-skeleton">
            <span className="vd-audit-ic">⋯</span>
            <div className="vd-audit-body">
              <header><b>—</b><time>—</time></header>
              <p>carregando…</p>
            </div>
          </li>
        </ul>
      </div>
    );
  }

  if (entries.length === 0) return null;
  return (
    <div className="vd-audit">
      <div className="vd-audit-h">
        <h4>Histórico</h4>
        <small>
          {entries.length} entrada{entries.length === 1 ? '' : 's'} · auditoria
          {usingReal ? ' · FSM real' : ''}
        </small>
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

export type { SaleAuditInput, FsmHistoryEntry, FsmAuditResponse };
