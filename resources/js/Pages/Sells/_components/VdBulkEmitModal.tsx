// VdBulkEmitModal — Faturar em lote NF-e/NFS-e (KB-9.75 Cowork bundle 2026-05-26 P0 gap #4).
// Refs: memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md gap #4
//
// Mock backend: setTimeout 600-1200ms por item, 85% ok / 15% bad aleatório.
// Progress tricolor: pending (cinza) → running (azul) → ok (verde) ou bad (vermelho).
//
// **UI stub** — wire backend real pelo loop /sells/{id}/emit-nfe quando disponível.

import { useEffect, useState } from 'react';
import { X, Check, AlertCircle, Loader2, Circle } from 'lucide-react';
import { toast } from 'sonner';

export interface BulkEmitItem {
  id: number;
  invoice_no: string;
  customer_name: string | null;
  kind: 'nfe' | 'nfse';
}

type ItemStatus = 'pending' | 'running' | 'ok' | 'bad';
interface ItemState extends BulkEmitItem {
  status: ItemStatus;
  motivo?: string;
  protocolo?: string;
}

interface Props {
  open: boolean;
  items: BulkEmitItem[];
  onClose: () => void;
  onCompleted?: (okCount: number, badCount: number) => void;
}

export default function VdBulkEmitModal({ open, items, onClose, onCompleted }: Props) {
  const [states, setStates] = useState<ItemState[]>([]);
  const [running, setRunning] = useState(false);
  const [finished, setFinished] = useState(false);

  useEffect(() => {
    if (open) {
      setStates(items.map((it) => ({ ...it, status: 'pending' })));
      setRunning(false);
      setFinished(false);
    }
  }, [open, items]);

  const okCount = states.filter((s) => s.status === 'ok').length;
  const badCount = states.filter((s) => s.status === 'bad').length;
  const runCount = states.filter((s) => s.status === 'running').length;
  const total = states.length;
  const done = okCount + badCount;
  const pctOk = total ? (okCount / total) * 100 : 0;
  const pctBad = total ? (badCount / total) * 100 : 0;

  const startBulk = async () => {
    setRunning(true);
    setFinished(false);
    for (let i = 0; i < states.length; i++) {
      // Mark running
      setStates((prev) => prev.map((s, idx) => (idx === i ? { ...s, status: 'running' } : s)));
      // Mock SEFAZ/Prefeitura delay
      const delay = 600 + Math.random() * 600;
      await new Promise((resolve) => setTimeout(resolve, delay));
      // Mock result 85% ok
      const ok = Math.random() < 0.85;
      const finalStatus: ItemStatus = ok ? 'ok' : 'bad';
      const proto = ok
        ? `35260100000${Math.floor(Math.random() * 1_000_000_000).toString().padStart(9, '0')}`
        : undefined;
      const motivo = !ok ? 'Erro fiscal: revise CFOP/NCM/CST' : undefined;
      setStates((prev) =>
        prev.map((s, idx) =>
          idx === i ? { ...s, status: finalStatus, protocolo: proto, motivo } : s,
        ),
      );
      if (ok && proto) {
        window.dispatchEvent(
          new CustomEvent(`oimpresso:venda-emitted-${states[i].kind}`, {
            detail: { saleId: states[i].id, protocolo: proto },
          }),
        );
      }
    }
    setRunning(false);
    setFinished(true);
    const finalOk = states.filter((s) => s.status === 'ok' || (s.status as ItemStatus) === 'ok')
      .length;
    const finalBad = states.length - finalOk;
    toast.success(`Lote concluído · ${finalOk} ok / ${finalBad} falhas`);
    onCompleted?.(finalOk, finalBad);
  };

  const close = () => {
    if (running) {
      if (!confirm('Lote em execução. Fechar mesmo assim?')) return;
    }
    onClose();
  };

  if (!open) return null;

  return (
    <div
      className="vd-emit-bd"
      role="dialog"
      aria-modal="true"
      aria-labelledby="vd-bulk-title"
      onClick={(e) => {
        if (e.target === e.currentTarget) close();
      }}
    >
      <div className="vd-emit-modal vd-bulk-emit-modal">
        <header className="vd-emit-h">
          <div className="vd-emit-h-l">
            <h2 id="vd-bulk-title">
              Faturar em lote · {total} venda{total === 1 ? '' : 's'}
            </h2>
            <small>
              {finished
                ? `Concluído · ${okCount} ok · ${badCount} falhas`
                : running
                  ? `Em execução · ${done}/${total}`
                  : 'Pronto pra iniciar'}
            </small>
          </div>
          <button
            type="button"
            className="vd-emit-close"
            onClick={close}
            aria-label="Fechar"
          >
            <X size={18} />
          </button>
        </header>

        <div className="vd-emit-body">
          <div className="vd-bulk-emit-list">
            {states.map((s) => (
              <div key={s.id} className={`vd-bulk-row ${s.status}`}>
                <span className="vd-bulk-row-icon">
                  {s.status === 'pending' && <Circle size={14} />}
                  {s.status === 'running' && <Loader2 size={14} className="vd-emit-spin" />}
                  {s.status === 'ok' && <Check size={14} />}
                  {s.status === 'bad' && <AlertCircle size={14} />}
                </span>
                <div className="vd-bulk-row-main">
                  <b>
                    #{s.invoice_no} · {s.customer_name ?? 'Cliente'} ·{' '}
                    <span style={{ textTransform: 'uppercase', fontSize: 10 }}>
                      {s.kind}
                    </span>
                  </b>
                  {s.status === 'ok' && s.protocolo && (
                    <small>Protocolo {s.protocolo}</small>
                  )}
                  {s.status === 'bad' && s.motivo && <small>{s.motivo}</small>}
                </div>
              </div>
            ))}
          </div>

          {total > 0 && (
            <>
              <div className="vd-bulk-progress-bar">
                <div className="vd-bulk-progress-fill ok" style={{ width: `${pctOk}%` }} />
                <div className="vd-bulk-progress-fill bad" style={{ width: `${pctBad}%` }} />
              </div>
              <div className="vd-bulk-progress-summary">
                <span><span className="dot ok" /> {okCount} ok</span>
                <span><span className="dot bad" /> {badCount} falhas</span>
                <span><span className="dot run" /> {runCount} rodando</span>
              </div>
            </>
          )}
        </div>

        <footer className="vd-emit-f">
          <div className="vd-emit-f-spacer" />
          {!running && !finished && (
            <button
              type="button"
              className="vd-emit-btn primary"
              onClick={startBulk}
              disabled={total === 0}
            >
              Iniciar lote ({total})
            </button>
          )}
          {running && (
            <button type="button" className="vd-emit-btn" disabled>
              <Loader2 size={14} className="vd-emit-spin" /> Em execução…
            </button>
          )}
          {finished && (
            <button type="button" className="vd-emit-btn" onClick={close}>
              Fechar
            </button>
          )}
        </footer>
      </div>
    </div>
  );
}
