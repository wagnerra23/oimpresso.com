// CobrancaChip.tsx — chip footer drawer Sells com 5 estados (paid/pending/overdue/error/none)
// Port literal pg-vendas-integration.jsx Cowork F1.5 (score 93/100) aprovado [W] 2026-05-19.
// ADR 0144 + ADR 0170 PaymentGateway.

import { useState } from 'react';
import { Plus } from 'lucide-react';
import CobrancaDrawer from './CobrancaDrawer';

export type CobrancaKind = 'paid' | 'pending' | 'overdue' | 'error' | 'none';

export interface CobrancaState {
  kind: CobrancaKind;
  cob?: {
    id: number;
    valor: number;
    tipo: string;
    gateway: string;
    vencimento: string | null;
    paga_em: string | null;
    emitida_em: string | null;
    erro_msg: string | null;
  };
}

export interface VendaContext {
  id: number;
  invoice_no: string;
  customer_name: string | null;
  final_total: number;
}

interface Props {
  venda: VendaContext;
  state: CobrancaState;
}

const fmtDateBR = (iso: string | null) => {
  if (!iso) return '';
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y.slice(2)}`;
};

const daysFrom = (iso: string | null, today = new Date().toISOString().slice(0, 10)) => {
  if (!iso) return 0;
  const [yt, mt, dt] = today.split('-').map(Number);
  const [y, m, d] = iso.split('-').map(Number);
  return Math.round((new Date(y, m - 1, d).getTime() - new Date(yt, mt - 1, dt).getTime()) / 86400000);
};

export default function CobrancaChip({ venda, state }: Props) {
  const [drawerOpen, setDrawerOpen] = useState(false);

  return (
    <>
      {state.kind === 'none' && (
        <button
          className="vd-cob-chip vd-cob-chip-pending"
          onClick={() => setDrawerOpen(true)}
          title="Emitir boleto, PIX ou cartão direto desta venda · ADR 0144 PaymentGateway"
          aria-label="Emitir cobrança"
        >
          <Plus className="h-3 w-3" />Emitir cobrança
        </button>
      )}

      {state.kind === 'paid' && state.cob && (
        <button
          className="vd-cob-chip vd-cob-chip-paid"
          onClick={() => setDrawerOpen(true)}
          title={`Cobrança #${state.cob.id} paga em ${fmtDateBR(state.cob.paga_em?.slice(0, 10) ?? null)} via ${state.cob.gateway}`}
        >
          <span className="vd-cob-dot vd-cob-dot-paid" />
          Cobrança #{state.cob.id} paga
        </button>
      )}

      {state.kind === 'pending' && state.cob && (
        <button
          className="vd-cob-chip vd-cob-chip-pending"
          onClick={() => setDrawerOpen(true)}
          title={`Cobrança #${state.cob.id} emitida · vence ${fmtDateBR(state.cob.vencimento)} via ${state.cob.gateway}`}
        >
          <span className="vd-cob-dot vd-cob-dot-pending" />
          Cobrança #{state.cob.id} · vence {fmtDateBR(state.cob.vencimento)}
        </button>
      )}

      {state.kind === 'overdue' && state.cob && (
        <button
          className="vd-cob-chip vd-cob-chip-overdue"
          onClick={() => setDrawerOpen(true)}
          title={`Cobrança #${state.cob.id} vencida há ${-daysFrom(state.cob.vencimento)}d`}
        >
          <span className="vd-cob-dot vd-cob-dot-overdue" />
          Cobrança #{state.cob.id} vencida · {-daysFrom(state.cob.vencimento)}d
        </button>
      )}

      {state.kind === 'error' && state.cob && (
        <button
          className="vd-cob-chip vd-cob-chip-error"
          onClick={() => setDrawerOpen(true)}
          title={state.cob.erro_msg || 'Erro de emissão'}
        >
          <span className="vd-cob-dot vd-cob-dot-error" />
          Cobrança erro · {(state.cob.erro_msg || 'falha').slice(0, 32)}{(state.cob.erro_msg?.length || 0) > 32 ? '…' : ''}
        </button>
      )}

      {drawerOpen && (
        <CobrancaDrawer venda={venda} state={state} onClose={() => setDrawerOpen(false)} />
      )}
    </>
  );
}
