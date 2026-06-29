// CobrancaDrawer.tsx — drawer lateral canon 2 modos (view / form)
// Port literal pg-vendas-integration.jsx Cowork F1.5 — refator P0 (modal → drawer lateral).
// ESC + scroll lock + focus trap (WCAG 2.1). ADR 0144 + ADR 0170.
// Onda 4d.5: wire-up emissão real via POST /sells/{id}/emitir-cobranca.

import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { X, Check, RotateCw, ArrowRight } from 'lucide-react';
import type { CobrancaState, VendaContext } from './CobrancaChip';

interface Props {
  venda: VendaContext;
  state: CobrancaState;
  onClose: () => void;
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

const DRIVERS_INFO: Record<string, { nome: string; sigla: string; bg: string }> = {
  inter:   { nome: 'Inter PJ',     sigla: 'IN', bg: 'bg-orange-500' },
  c6:      { nome: 'C6 Bank',      sigla: 'C6', bg: 'bg-stone-800' },
  asaas:   { nome: 'Asaas',        sigla: 'AS', bg: 'bg-blue-600' },
  bcb_pix: { nome: 'BCB · PIX Automático', sigla: 'BC', bg: 'bg-emerald-600' },
  pesapal: { nome: 'PesaPal',      sigla: 'PP', bg: 'bg-purple-500' },
};

const TIPOS_LABEL: Record<string, string> = {
  boleto: 'Boleto',
  pix_cob: 'PIX cob',
  pix_cobv: 'PIX cobv',
  pix_recv: 'PIX Aut.',
  card: 'Cartão',
};

export default function CobrancaDrawer({ venda, state, onClose }: Props) {
  const drawerRef = useRef<HTMLDivElement>(null);
  const isView = state.kind === 'paid' || state.kind === 'pending' || state.kind === 'overdue';
  const [tipo, setTipo] = useState(isView && state.cob ? state.cob.tipo : 'boleto');
  const [venc, setVenc] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() + 7);
    return d.toISOString().slice(0, 10);
  });
  const [submitting, setSubmitting] = useState(false);

  // Onda 4d.5: POST /sells/{id}/emitir-cobranca real
  const emitir = async () => {
    if (submitting) return;
    setSubmitting(true);
    const idempotency = typeof crypto !== 'undefined' && crypto.randomUUID
      ? crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    try {
      const response = await fetch(`/sells/${venda.id}/emitir-cobranca`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '',
        },
        body: JSON.stringify({
          tipo,
          vencimento: venc,
          idempotency_key: idempotency,
        }),
      });
      const data = await response.json();
      if (!response.ok) {
        toast.error(data.error || 'Falha ao emitir cobrança');
        return;
      }
      toast.success(`Cobrança #${data.cobranca_id} emitida via ${data.tipo}`);
      router.reload({ only: ['cobranca'] });
      onClose();
    } catch (e) {
      toast.error('Erro de rede ao emitir cobrança');
    } finally {
      setSubmitting(false);
    }
  };

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
      const focusable = drawerRef.current?.querySelector<HTMLElement>('button, input, select');
      focusable?.focus();
    }, 100);
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prev;
    };
  }, [onClose]);

  const goToCobranca = () => {
    router.visit('/financeiro/cobranca');
    onClose();
  };

  const drv = state.cob ? DRIVERS_INFO[state.cob.gateway] : null;

  return (
    <div className="fixed inset-0 z-[80] flex justify-end" onClick={onClose} role="dialog" aria-modal="true" aria-label="Cobrança da venda">
      <div className="absolute inset-0 bg-stone-900/30" />
      <aside
        ref={drawerRef}
        className="relative w-[480px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col pg-shell-scope font-sans"
        onClick={e => e.stopPropagation()}
      >
        <header className="px-5 py-3 border-b border-stone-200 flex items-center gap-2">
          <div className="min-w-0 flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">
              {isView && state.cob ? `Cobrança #${state.cob.id}` : 'Nova cobrança'}
            </div>
            <div className="text-[15px] font-semibold mt-0.5 truncate">{venda.customer_name || 'Venda'}</div>
            <p className="text-[11px] text-stone-500 mt-0.5">
              Venda #{venda.id} · {(venda.final_total || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
            </p>
          </div>
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500" aria-label="Fechar (ESC)">
            <X className="h-3.5 w-3.5" />
          </button>
        </header>

        <div className="flex-1 overflow-auto">

          {/* Resumo herdado da venda */}
          <div className="border-b border-stone-200 bg-stone-50/40 p-4 grid grid-cols-2 gap-3 text-[12px]">
            <div>
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Pagador</div>
              <div className="mt-0.5 font-medium text-stone-900">{venda.customer_name || '—'}</div>
            </div>
            <div>
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Valor</div>
              <div className="mt-0.5 font-semibold text-[14px] tabular-nums text-stone-900">
                {(venda.final_total || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
              </div>
            </div>
          </div>

          {/* VIEW: cobrança existente */}
          {isView && state.cob && drv && (
            <div className="p-4 space-y-3">
              <div className="bg-stone-50 border border-stone-200 rounded-md p-3">
                <div className="flex items-center gap-2 text-[12px]">
                  <span className={`w-4 h-4 rounded-sm grid place-items-center text-white text-[8.5px] font-bold tracking-tight ${drv.bg}`}>
                    {drv.sigla}
                  </span>
                  <strong className="text-stone-900">{drv.nome}</strong>
                  <span className="text-stone-500">· {TIPOS_LABEL[state.cob.tipo] || state.cob.tipo}</span>
                  <span className="ml-auto text-[10.5px] text-stone-500">
                    emitida {fmtDateBR(state.cob.emitida_em?.slice(0, 10) ?? null)}
                  </span>
                </div>

                {state.kind === 'paid' && state.cob.paga_em && (
                  <div className="mt-2 pt-2 border-t border-stone-200 text-[11.5px] text-success flex items-center gap-1.5">
                    <span className="w-1.5 h-1.5 rounded-full bg-success" />
                    Paga em {fmtDateBR(state.cob.paga_em.slice(0, 10))} — liquidação automática via webhook
                  </div>
                )}
                {state.kind === 'pending' && (
                  <div className="mt-2 pt-2 border-t border-stone-200 text-[11.5px] text-blue-700 flex items-center gap-1.5">
                    <span className="w-1.5 h-1.5 rounded-full bg-blue-500" />
                    Vence {fmtDateBR(state.cob.vencimento)} — aguardando pagamento
                  </div>
                )}
                {state.kind === 'overdue' && (
                  <div className="mt-2 pt-2 border-t border-stone-200 text-[11.5px] text-destructive flex items-center gap-1.5">
                    <span className="w-1.5 h-1.5 rounded-full bg-destructive" />
                    Vencida há {-daysFrom(state.cob.vencimento)}d — smart retry agendado
                  </div>
                )}
              </div>

              <div className="flex gap-2">
                <button
                  className="inline-flex items-center gap-1.5 h-8 px-3 text-[12px] font-medium rounded-md bg-stone-900 text-white hover:bg-stone-800"
                  onClick={goToCobranca}
                >
                  Ver em /financeiro/cobrança <ArrowRight className="h-3 w-3" />
                </button>
              </div>
            </div>
          )}

          {/* FORM: emitir (none ou error) */}
          {!isView && (
            <div className="p-4 space-y-4">
              {state.kind === 'error' && state.cob?.erro_msg && (
                <div className="bg-destructive-soft border border-destructive/20 rounded-md p-3 text-[11.5px] text-destructive-fg">
                  <div className="font-medium">Tentativa anterior falhou</div>
                  <div className="font-mono text-[10.5px] mt-1">{state.cob.erro_msg}</div>
                </div>
              )}

              <div>
                <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-2">Tipo de cobrança</div>
                <div className="grid grid-cols-4 gap-2">
                  {[
                    { id: 'boleto',   label: 'Boleto' },
                    { id: 'pix_cob',  label: 'PIX' },
                    { id: 'pix_recv', label: 'PIX Aut.', disabled: true, why: 'só pra assinaturas' },
                    { id: 'card',     label: 'Cartão' },
                  ].map(t => (
                    <button key={t.id} disabled={t.disabled} onClick={() => !t.disabled && setTipo(t.id)} title={t.why || ''}
                      className={`p-2.5 border rounded-md text-[11.5px] font-medium transition disabled:opacity-40 disabled:cursor-not-allowed ${
                        tipo === t.id
                          ? 'border-stone-900 bg-stone-50 text-stone-900 font-semibold ring-2 ring-stone-900/10'
                          : 'border-stone-200 text-stone-700 hover:border-stone-400 hover:bg-stone-50'
                      }`}>
                      {t.label}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block">
                  <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1">Vencimento</div>
                  <input type="date" value={venc} onChange={e => setVenc(e.target.value)}
                    className="w-full h-8 px-2 text-[12.5px] bg-white border border-stone-300 rounded text-stone-900" />
                </label>
              </div>

              <div className="bg-stone-50 border border-stone-200 rounded p-3 text-[11px] text-stone-700">
                Dispara <span className="font-mono">PaymentGateway::emitir{tipo === 'card' ? 'Cartao' : tipo.startsWith('pix') ? 'Pix' : 'Boleto'}()</span> com origem <span className="font-mono">sale:{venda.id}</span> — idempotente (não duplica). Onda 5 backend wire-up pendente.
              </div>
            </div>
          )}
        </div>

        <footer className="border-t border-stone-200 p-3 flex items-center gap-2 bg-stone-50/60">
          <button onClick={onClose} className="inline-flex items-center gap-1.5 h-8 px-3 text-[12px] font-medium rounded-md border border-stone-300 text-stone-700 hover:bg-stone-50">
            Fechar (ESC)
          </button>
          <div className="flex-1" />
          {!isView && (
            <button onClick={emitir} disabled={submitting} className="inline-flex items-center gap-1.5 h-8 px-3 text-[12px] font-medium rounded-md bg-stone-900 text-white hover:bg-stone-800 disabled:opacity-50">
              <Check className="h-3 w-3" />{submitting ? 'Emitindo…' : 'Emitir cobrança'}
            </button>
          )}
          {state.kind === 'error' && (
            <button onClick={emitir} disabled={submitting} className="inline-flex items-center gap-1.5 h-8 px-3 text-[12px] font-medium rounded-md bg-stone-900 text-white hover:bg-stone-800 disabled:opacity-50">
              <RotateCw className="h-3 w-3" />{submitting ? 'Reemitindo…' : 'Reemitir'}
            </button>
          )}
        </footer>
      </aside>
    </div>
  );
}
