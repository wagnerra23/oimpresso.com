// FinMonthDigest — Cowork KB-9.75 Financeiro Onda 6 R2 IA
// (4-card snapshot do mês — "Eliana 5min sexta" digest executivo).
//
// Refs:
//  - prototipo-ui/financeiro-ai.jsx — FinAiMonthDigest
//
// Pure compute do array `lancamentos` + `kpis` que já vêm no payload. Sem backend.
// Pode ser exibido como section colapsada acima da tabela OU como tab no header.

import { useMemo, useState } from 'react';

interface LancamentoLite {
  id: number;
  contraparte: string;
  valor: number;
  vencimento?: string | Date | null;
  liquidacao?: string | Date | null;
  status?: string;
  kind?: 'receivable' | 'payable';
}

interface KpiSnapshot {
  saldo_previsto: number;
  recebido: { valor: number; qtd: number };
  a_receber: { valor: number; qtd: number };
  pago: { valor: number; qtd: number };
  a_pagar: { valor: number; qtd: number };
}

export interface MonthDigest {
  cashIn: { value: number; count: number };
  cashOut: { value: number; count: number };
  net: number;
  late: { value: number; count: number };
  topPartyIn: { name: string; value: number } | null;
  topPartyOut: { name: string; value: number } | null;
  conferidoCount: number;
  totalCount: number;
  conferidoPct: number;
}

export function buildMonthDigest(
  lancamentos: LancamentoLite[],
  kpis: KpiSnapshot,
  conferidoSet: Set<string>,
): MonthDigest {
  const recebidos = lancamentos.filter((l) => l.status === 'recebido' || !!l.liquidacao && l.kind === 'receivable');
  const pagos = lancamentos.filter((l) => l.status === 'pago' || (!!l.liquidacao && l.kind === 'payable'));
  const atrasados = lancamentos.filter((l) => l.status === 'atrasado');

  const cashIn = {
    value: kpis.recebido?.valor || recebidos.reduce((s, r) => s + r.valor, 0),
    count: kpis.recebido?.qtd || recebidos.length,
  };
  const cashOut = {
    value: kpis.pago?.valor || pagos.reduce((s, r) => s + r.valor, 0),
    count: kpis.pago?.qtd || pagos.length,
  };
  const late = {
    value: atrasados.reduce((s, r) => s + r.valor, 0),
    count: atrasados.length,
  };

  // Top contraparte recebimento e pagamento
  const partyIn: Record<string, number> = {};
  recebidos.forEach((r) => {
    partyIn[r.contraparte] = (partyIn[r.contraparte] || 0) + r.valor;
  });
  const partyOut: Record<string, number> = {};
  pagos.forEach((r) => {
    partyOut[r.contraparte] = (partyOut[r.contraparte] || 0) + r.valor;
  });

  const topPartyIn = Object.entries(partyIn).sort((a, b) => b[1] - a[1])[0];
  const topPartyOut = Object.entries(partyOut).sort((a, b) => b[1] - a[1])[0];

  const conferidoCount = lancamentos.filter((l) => conferidoSet.has(String(l.id))).length;
  const totalCount = lancamentos.length;
  const conferidoPct = totalCount > 0 ? Math.round((conferidoCount / totalCount) * 100) : 0;

  return {
    cashIn,
    cashOut,
    net: cashIn.value - cashOut.value,
    late,
    topPartyIn: topPartyIn ? { name: topPartyIn[0], value: topPartyIn[1] } : null,
    topPartyOut: topPartyOut ? { name: topPartyOut[0], value: topPartyOut[1] } : null,
    conferidoCount,
    totalCount,
    conferidoPct,
  };
}

function brl(n: number): string {
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function brlShort(n: number): string {
  if (Math.abs(n) >= 1000) {
    return 'R$ ' + (n / 1000).toFixed(1).replace('.', ',') + 'k';
  }
  return brl(n);
}

interface FinMonthDigestProps {
  lancamentos: LancamentoLite[];
  kpis: KpiSnapshot;
  conferidoSet: Set<string>;
  periodLabel: string;
  defaultOpen?: boolean;
}

export function FinMonthDigest({ lancamentos, kpis, conferidoSet, periodLabel, defaultOpen = false }: FinMonthDigestProps) {
  const [open, setOpen] = useState(defaultOpen);
  const digest = useMemo(() => buildMonthDigest(lancamentos, kpis, conferidoSet), [lancamentos, kpis, conferidoSet]);

  const netCls = digest.net >= 0 ? 'pos' : 'neg';

  return (
    <div className={`fin-digest ${open ? 'open' : 'closed'}`}>
      <button type="button" className="fin-digest-toggle" onClick={() => setOpen((v) => !v)} aria-expanded={open}>
        <span className="fin-digest-ic">✦</span>
        <b>Resumo do mês · {periodLabel}</b>
        <small>{digest.totalCount} lançamentos · {digest.conferidoPct}% conferido</small>
        <span className="fin-digest-chev">{open ? '▾' : '▸'}</span>
      </button>

      {open && (
        <div className="fin-digest-body">
          <div className="fin-digest-cards">
            <div className="fin-digest-card in">
              <small>Recebido</small>
              <b>{brlShort(digest.cashIn.value)}</b>
              <i>{digest.cashIn.count} baixas</i>
            </div>
            <div className="fin-digest-card out">
              <small>Pago</small>
              <b>{brlShort(digest.cashOut.value)}</b>
              <i>{digest.cashOut.count} baixas</i>
            </div>
            <div className={`fin-digest-card net ${netCls}`}>
              <small>Saldo do mês</small>
              <b>{digest.net >= 0 ? '+' : ''}{brlShort(digest.net)}</b>
              <i>{digest.net >= 0 ? 'positivo' : 'negativo'}</i>
            </div>
            <div className="fin-digest-card late">
              <small>Atrasados</small>
              <b>{brlShort(digest.late.value)}</b>
              <i>{digest.late.count} título{digest.late.count !== 1 ? 's' : ''}</i>
            </div>
          </div>

          {(digest.topPartyIn || digest.topPartyOut) && (
            <div className="fin-digest-tops">
              {digest.topPartyIn && (
                <div className="fin-digest-top in">
                  <small>↑ Top recebimento</small>
                  <span><b>{digest.topPartyIn.name}</b> · {brlShort(digest.topPartyIn.value)}</span>
                </div>
              )}
              {digest.topPartyOut && (
                <div className="fin-digest-top out">
                  <small>↓ Top pagamento</small>
                  <span><b>{digest.topPartyOut.name}</b> · {brlShort(digest.topPartyOut.value)}</span>
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export default FinMonthDigest;
