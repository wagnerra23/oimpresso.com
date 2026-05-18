// FinAuditTrail — Cowork KB-9.75 Financeiro Onda 5 R1 Curadoria
// (histórico determinístico de eventos derivado do row).
//
// Refs:
//  - prototipo-ui/financeiro-curation.jsx — finAuditTrail + FinAuditTrail
//  - SaleAuditTrail.tsx pattern (canon Sells Onda 3)
//
// Determinístico: SEM persistência. Deriva eventos do row (created/categorize/edit/concil/alert)
// usando seed simples (charCode do último char do id) pra estabilidade visual.
// Onda futura plugará em backend `audit_log` ou Spatie Activitylog real.

import { useMemo } from 'react';

export type FinAuditKind = 'create' | 'categorize' | 'edit' | 'concil' | 'alert';

export interface FinAuditEntry {
  when: string;
  who: string;
  kind: FinAuditKind;
  desc: string;
  diff?: { from: number; to: number; pct: number };
}

interface LancamentoLike {
  id: string | number;
  descricao?: string;
  contraparte?: string;
  categoria?: string;
  conta_bancaria?: string;
  canal?: string;
  valor?: number;
  status?: string;
  kind?: 'receivable' | 'payable';
  paid_at?: string | Date | null;
  due?: string | Date | null;
  vencimento?: string | Date | null;
}

function asDate(v: string | Date | null | undefined): Date | null {
  if (!v) return null;
  if (v instanceof Date) return v;
  const d = new Date(v);
  return isNaN(d.getTime()) ? null : d;
}

function fmtDate(d: Date | null): string {
  if (!d) return '—';
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function brl(n: number): string {
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export function finAuditTrail(row: LancamentoLike): FinAuditEntry[] {
  const id = String(row.id);
  const seed = id.charCodeAt(id.length - 1) % 4;
  const entries: FinAuditEntry[] = [];

  const due = asDate(row.due ?? row.vencimento ?? null);
  const paid = asDate(row.paid_at ?? null);
  const isReceivable = row.kind === 'receivable';
  const valor = row.valor || 0;
  const desc = (row.descricao || '').slice(0, 48);
  const descTrunc = (row.descricao || '').length > 48 ? '…' : '';

  // 1) Criação — sempre
  entries.push({
    when: `${fmtDate(due)} ${isReceivable ? 'emitido' : 'recebido'}`,
    who: isReceivable ? (row.contraparte || 'Vendas') : (row.contraparte || 'Fornecedor · NF'),
    kind: 'create',
    desc: `Lançamento #${id} · ${desc}${descTrunc}`,
  });

  // 2) Categorização — sempre se houver categoria
  if (row.categoria) {
    entries.push({
      when: `${fmtDate(due)} ${('0' + (((due?.getHours() ?? 0) + 1) % 24)).slice(-2)}:${('0' + (due?.getMinutes() ?? 0)).slice(-2)}`,
      who: 'Eliana Financeiro',
      kind: 'categorize',
      desc: `Classificado em "${row.categoria}"`,
    });
  }

  // 3) Edição de valor — só se seed >= 2 (determinístico ~50% das linhas)
  if (seed >= 2 && valor > 0) {
    const oldAmount = Math.round(valor * 0.94 * 100) / 100;
    const diff = valor - oldAmount;
    entries.push({
      when: `${fmtDate(due)} ajuste`,
      who: 'Eliana Financeiro',
      kind: 'edit',
      desc: `Valor revisado · ${brl(oldAmount)} → ${brl(valor)}`,
      diff: { from: oldAmount, to: valor, pct: (diff / oldAmount) * 100 },
    });
  }

  // 4) Conciliação — só se pago
  if (paid) {
    entries.push({
      when: fmtDate(paid),
      who: 'Banco (Inter)',
      kind: 'concil',
      desc: `Conciliado com extrato · ${row.canal || '—'}`,
    });
  }

  // 5) Alerta atrasado
  if (row.status === 'atrasado' || row.status === 'overdue') {
    entries.push({
      when: 'agora',
      who: 'sistema',
      kind: 'alert',
      desc: '⚠ Vencimento ultrapassou — em atraso',
    });
  }

  return entries;
}

const KIND_LABEL: Record<FinAuditKind, string> = {
  create: 'criou',
  categorize: 'categorizou',
  edit: 'editou',
  concil: 'conciliou',
  alert: 'alerta',
};

const KIND_IC: Record<FinAuditKind, string> = {
  create: '+',
  categorize: '▦',
  edit: '✎',
  concil: '≣',
  alert: '⚠',
};

interface FinAuditTrailProps {
  row: LancamentoLike;
}

export function FinAuditTrail({ row }: FinAuditTrailProps) {
  const entries = useMemo(() => finAuditTrail(row), [row.id, row.paid_at, row.status, row.valor, row.categoria]);

  return (
    <div className="fin-audit">
      <div className="fin-audit-h">
        <h4>Histórico</h4>
        <small>{entries.length} eventos · auditoria contábil</small>
      </div>
      <ul className="fin-audit-list">
        {entries.map((e, i) => (
          <li key={i} className={`fin-audit-row fin-audit-${e.kind}`}>
            <span className="fin-audit-ic">{KIND_IC[e.kind] || '·'}</span>
            <div className="fin-audit-body">
              <header>
                <b>{e.who}</b>
                <span className="fin-audit-action">{KIND_LABEL[e.kind] || ''}</span>
                <time>{e.when}</time>
              </header>
              <p>{e.desc}</p>
              {e.diff && (
                <div className="fin-audit-diff">
                  <span className="diff-from">{brl(e.diff.from)}</span>
                  <span className="diff-arr">→</span>
                  <span className="diff-to">{brl(e.diff.to)}</span>
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

export default FinAuditTrail;
