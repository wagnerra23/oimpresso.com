// FinPartyHistory — Cowork KB-9.75 Financeiro Onda 6 R2 IA
// (stats da contraparte: média, on-time%, categoria top, recorrência).
//
// Refs:
//  - prototipo-ui/financeiro-ai.jsx — finAiPartyHistory + FinAiPartyContext
//
// Pure compute do array `lancamentos` (sem backend, sem IA real).
// Determinístico. Multi-tenant Tier 0 safe (lancamentos já vem business_id-scoped).

import { useMemo } from 'react';

interface LancamentoLite {
  id: number;
  contraparte: string;
  categoria?: string;
  valor: number;
  vencimento?: string | Date | null;
  liquidacao?: string | Date | null;
  status?: string;
  kind?: 'receivable' | 'payable';
}

export interface FinPartyStats {
  count: number;
  total: number;
  avg: number;
  paidCount: number;
  overdueCount: number;
  onTimePct: number | null;
  topCategory?: string;
  isNew: boolean;
  isRecurrent: boolean;
  recent: LancamentoLite[];
}

function asDate(v: string | Date | null | undefined): Date | null {
  if (!v) return null;
  if (v instanceof Date) return v;
  const d = new Date(v);
  return isNaN(d.getTime()) ? null : d;
}

export function finPartyHistory(
  partyName: string,
  currentRowId: number | null,
  all: LancamentoLite[],
): FinPartyStats {
  const list = all.filter((r) => r.contraparte === partyName);
  const mine = currentRowId != null ? list.filter((r) => r.id !== currentRowId) : list;

  if (mine.length === 0) {
    return {
      count: 0,
      total: 0,
      avg: 0,
      paidCount: 0,
      overdueCount: 0,
      onTimePct: null,
      isNew: true,
      isRecurrent: false,
      recent: [],
    };
  }

  const total = mine.reduce((s, r) => s + (r.valor || 0), 0);
  const avg = total / mine.length;
  const paid = mine.filter((r) => !!r.liquidacao);
  const overdue = mine.filter((r) => r.status === 'atrasado');
  const onTime = paid.filter((r) => {
    const p = asDate(r.liquidacao);
    const d = asDate(r.vencimento);
    return p && d && p.getTime() <= d.getTime();
  }).length;

  const onTimePct = paid.length ? Math.round((onTime / paid.length) * 100) : null;

  // Categoria mais comum
  const catCount: Record<string, number> = {};
  mine.forEach((r) => {
    const c = r.categoria || '—';
    catCount[c] = (catCount[c] || 0) + 1;
  });
  const topCategory = Object.entries(catCount).sort((a, b) => b[1] - a[1])[0]?.[0];

  // 5 transações mais recentes (por vencimento desc)
  const recent = [...mine]
    .sort((a, b) => {
      const da = asDate(a.liquidacao || a.vencimento)?.getTime() ?? 0;
      const db = asDate(b.liquidacao || b.vencimento)?.getTime() ?? 0;
      return db - da;
    })
    .slice(0, 5);

  return {
    count: mine.length,
    total,
    avg,
    paidCount: paid.length,
    overdueCount: overdue.length,
    onTimePct,
    topCategory,
    isNew: mine.length === 1,
    isRecurrent: mine.length >= 3,
    recent,
  };
}

function brl(n: number): string {
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function brlShort(n: number): string {
  if (n >= 1000) {
    return 'R$ ' + (n / 1000).toFixed(1).replace('.', ',') + 'k';
  }
  return brl(n);
}

interface FinPartyHistoryProps {
  currentRow: { id: number; contraparte: string };
  all: LancamentoLite[];
}

export function FinPartyHistory({ currentRow, all }: FinPartyHistoryProps) {
  const stats = useMemo(
    () => finPartyHistory(currentRow.contraparte, currentRow.id, all),
    [currentRow.contraparte, currentRow.id, all],
  );

  if (stats.isNew) {
    return (
      <div className="fin-party-history fin-party-new">
        <div className="fin-party-h">
          <span className="fin-party-ic">✦</span>
          <h4>Contraparte nova</h4>
        </div>
        <p>Primeira transação com <b>{currentRow.contraparte}</b>. Sem histórico pra comparar.</p>
      </div>
    );
  }

  const recCls = stats.isRecurrent ? 'recurrent' : 'occasional';

  return (
    <div className={`fin-party-history fin-party-${recCls}`}>
      <div className="fin-party-h">
        <span className="fin-party-ic">✦</span>
        <h4>{currentRow.contraparte}</h4>
        <small>{stats.count} lançamento{stats.count > 1 ? 's' : ''} históricos</small>
      </div>

      <div className="fin-party-stats">
        <div className="fin-party-stat">
          <small>Média</small>
          <b>{brlShort(stats.avg)}</b>
        </div>
        <div className="fin-party-stat">
          <small>Total</small>
          <b>{brlShort(stats.total)}</b>
        </div>
        {stats.onTimePct != null && (
          <div className="fin-party-stat">
            <small>No prazo</small>
            <b className={stats.onTimePct >= 80 ? 'pos' : stats.onTimePct >= 50 ? 'mid' : 'neg'}>
              {stats.onTimePct}%
            </b>
          </div>
        )}
        {stats.overdueCount > 0 && (
          <div className="fin-party-stat">
            <small>Atrasados</small>
            <b className="neg">{stats.overdueCount}</b>
          </div>
        )}
      </div>

      {stats.topCategory && (
        <p className="fin-party-top-cat">
          <small>Categoria recorrente:</small> <b>{stats.topCategory}</b>
        </p>
      )}

      {stats.recent.length > 0 && (
        <div className="fin-party-recent">
          <small>5 mais recentes</small>
          <ul>
            {stats.recent.map((r) => (
              <li key={r.id}>
                <span className="fin-party-recent-cat">{r.categoria || '—'}</span>
                <span className="fin-party-recent-amt">{brlShort(r.valor)}</span>
                {r.liquidacao
                  ? <span className="fin-party-recent-tag paid">✓</span>
                  : r.status === 'atrasado'
                    ? <span className="fin-party-recent-tag overdue">✕</span>
                    : <span className="fin-party-recent-tag open">○</span>}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

export default FinPartyHistory;
