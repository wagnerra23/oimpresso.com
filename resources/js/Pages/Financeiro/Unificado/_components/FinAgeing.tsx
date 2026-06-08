// FinAgeing — Cowork KB-9.75 Financeiro Onda 10 (canon 100%)
// (barra horizontal segmentada com aging dos títulos A receber).
//
// Refs:
//  - prototipo-ui-patch/vendas-financeiro-completo/financeiro-app.jsx FinAgeing (linha 331)
//
// Pure compute. Recebe lançamentos kind=receivable não-pagos, classifica por
// dias até vencimento (delta = due - hoje):
//   - delta < 0   → late (atraso, vermelho)
//   - delta ≤ 30  → 0-30d (verde)
//   - delta ≤ 60  → 31-60d (amber)
//   - delta > 60  → 61d+ (roxo)
//
// Renderiza barra única horizontal com 4 segments coloridos proporcionais ao
// valor de cada bucket. Esconde quando total=0 (sem A receber).

import { useMemo } from 'react';

interface LancamentoLite {
  id: number;
  kind?: 'receivable' | 'payable';
  status?: string;
  liquidacao?: string | null;
  vencimento: string;
  valor: number;
}

function daysFromToday(isoDate: string): number {
  if (!isoDate) return 0;
  const d = new Date(isoDate + 'T00:00:00');
  const now = new Date();
  now.setHours(0, 0, 0, 0);
  return Math.round((d.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
}

function brl(n: number): string {
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export function FinAgeing({ lancamentos }: { lancamentos: LancamentoLite[] }) {
  const k = useMemo(() => {
    const open = lancamentos.filter(
      (l) => l.kind === 'receivable' && l.status !== 'recebido' && !l.liquidacao,
    );
    const total = open.reduce((s, l) => s + (l.valor || 0), 0);
    const buckets = { d30: 0, d60: 0, d90: 0, late: 0 };
    for (const l of open) {
      const delta = daysFromToday(l.vencimento);
      if (delta < 0) buckets.late += l.valor || 0;
      else if (delta <= 30) buckets.d30 += l.valor || 0;
      else if (delta <= 60) buckets.d60 += l.valor || 0;
      else buckets.d90 += l.valor || 0;
    }
    const pct = (v: number) => (total > 0 ? Math.round((v / total) * 100) : 0);
    return {
      total,
      ...buckets,
      pd30: pct(buckets.d30),
      pd60: pct(buckets.d60),
      pd90: pct(buckets.d90),
      plate: pct(buckets.late),
    };
  }, [lancamentos]);

  if (k.total === 0) return null;

  return (
    <div className="fin-ageing">
      <div className="fin-ageing-l">
        <small>A receber · ageing</small>
        <b className="mono">{brl(k.total)}</b>
      </div>
      <div className="fin-ageing-bar">
        {k.plate > 0 && (
          <div className="seg s4" style={{ flex: k.plate }} title={`Atraso: ${brl(k.late)}`}>
            {k.plate}% atraso
          </div>
        )}
        {k.pd30 > 0 && (
          <div className="seg s1" style={{ flex: k.pd30 }} title={`Vence em 0-30d: ${brl(k.d30)}`}>
            {k.pd30}% 0-30d
          </div>
        )}
        {k.pd60 > 0 && (
          <div className="seg s2" style={{ flex: k.pd60 }} title={`Vence em 31-60d: ${brl(k.d60)}`}>
            {k.pd60}% 31-60d
          </div>
        )}
        {k.pd90 > 0 && (
          <div className="seg s3" style={{ flex: k.pd90 }} title={`Vence em 61d+: ${brl(k.d90)}`}>
            {k.pd90}% 61d+
          </div>
        )}
      </div>
    </div>
  );
}

export default FinAgeing;
