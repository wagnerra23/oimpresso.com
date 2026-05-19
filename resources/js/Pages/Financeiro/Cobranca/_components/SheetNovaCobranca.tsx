// SheetNovaCobranca.tsx — wizard 4 steps (Tipo → Pagador → Valores → Revisar)
// Onda 4d.5: wire-up emissão real via POST /financeiro/cobranca/emitir.
import { useState, useMemo, useEffect, type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import {
  X, ChevronRight, ChevronLeft, Plus, Check, Receipt, QrCode, Zap, CreditCard,
} from 'lucide-react';
import { Btn, Field, GatewayTipoChip } from './atoms';
import {
  brl, cn, fmtDate, DRIVERS,
  type CobrancaTipo, type Account, type GatewayKey,
} from '../_lib/cobranca-shared';

interface Props {
  accounts: Account[];
  onClose: () => void;
}

const TIPOS_DISPONIVEIS: Array<{ id: CobrancaTipo; label: string; desc: string; icon: ReactNode; highlight?: boolean }> = [
  { id: 'boleto',   label: 'Boleto',         desc: 'Inter · C6 · Asaas',         icon: <Receipt className="h-4 w-4" /> },
  { id: 'pix_cob',  label: 'PIX',            desc: 'Inter · Asaas · imediato',    icon: <QrCode className="h-4 w-4" /> },
  { id: 'pix_recv', label: 'PIX Automático', desc: 'BCB · mandato recorrente',    icon: <Zap className="h-4 w-4" />, highlight: true },
  { id: 'card',     label: 'Cartão',         desc: 'Asaas · 3DS · 1-12x',         icon: <CreditCard className="h-4 w-4" /> },
];

const STEPS = ['Tipo', 'Pagador', 'Valores', 'Revisar'];

export default function SheetNovaCobranca({ accounts, onClose }: Props) {
  const [step, setStep] = useState(1);
  const [tipo, setTipo] = useState<CobrancaTipo | null>(null);
  const [contato, setContato] = useState('');
  const [valor, setValor] = useState('');
  const [vencimento, setVencimento] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() + 7);
    return d.toISOString().slice(0, 10);
  });
  const [account, setAccount] = useState(accounts[0]?.id || 0);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const driversParaTipo = useMemo<GatewayKey[]>(() => {
    if (!tipo) return [];
    return Object.values(DRIVERS).filter(d => !d.deprecated && d.tipos.includes(tipo)).map(d => d.key);
  }, [tipo]);

  const driverPrincipal = driversParaTipo[0];

  const canNext = step === 1 ? !!tipo : step === 2 ? !!contato : step === 3 ? !!valor && !!vencimento : true;
  const isLast = step === STEPS.length;

  /**
   * Onda 4d.5 — Submete POST /financeiro/cobranca/emitir.
   * Idempotência via crypto.randomUUID() (cliente garante unicidade per-submit).
   */
  const emitir = async () => {
    if (!tipo || submitting) return;
    setSubmitting(true);
    const valorCentavos = Math.round(parseFloat(valor.replace(',', '.')) * 100);
    const payload = {
      tipo,
      valor_centavos: valorCentavos,
      vencimento,
      account_id: account,
      payer_name: contato || null,
      idempotency_key: typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`,
    };

    router.post('/financeiro/cobranca/emitir', payload, {
      preserveScroll: true,
      preserveState: false,
      onSuccess: () => {
        toast.success('Cobrança emitida com sucesso');
        onClose();
      },
      onError: (errors) => {
        const msg = Object.values(errors)[0] || 'Falha ao emitir cobrança';
        toast.error(typeof msg === 'string' ? msg : 'Falha ao emitir cobrança');
      },
      onFinish: () => setSubmitting(false),
    });
  };

  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose} role="dialog" aria-modal="true" aria-label="Nova cobrança">
      <div className="absolute inset-0 bg-stone-900/30" />
      <div className="relative w-[640px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>

        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <div className="flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Nova cobrança</div>
            <div className="text-[15px] font-semibold mt-0.5">passo {step} de {STEPS.length}</div>
          </div>
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500" aria-label="Fechar (Esc)">
            <X className="h-3.5 w-3.5" />
          </button>
        </div>

        <div className="px-5 py-3 border-b border-stone-200 bg-stone-50/40 flex items-center gap-2 text-[11px]">
          {STEPS.map((s, i) => (
            <div key={i} className="flex items-center gap-1.5">
              <div className={cn(
                'flex items-center gap-1.5',
                step === i + 1 ? 'text-stone-900 font-semibold' : step > i + 1 ? 'text-emerald-700' : 'text-stone-400',
              )}>
                <span className={cn(
                  'w-5 h-5 rounded-full grid place-items-center text-[10px] font-bold',
                  step === i + 1 ? 'bg-stone-900 text-white' :
                  step > i + 1 ? 'bg-emerald-100 text-emerald-700' :
                  'bg-stone-200 text-stone-500',
                )}>
                  {step > i + 1 ? <Check className="h-2.5 w-2.5" /> : i + 1}
                </span>
                {s}
              </div>
              {i < STEPS.length - 1 && <ChevronRight className="h-3 w-3 text-stone-300" />}
            </div>
          ))}
        </div>

        <div className="flex-1 overflow-auto p-5">
          {step === 1 && (
            <div className="space-y-3">
              <div className="text-[11px] text-stone-500 mb-2">Escolha o tipo de cobrança.</div>
              <div className="grid grid-cols-2 gap-2.5">
                {TIPOS_DISPONIVEIS.map(t => (
                  <button key={t.id} onClick={() => setTipo(t.id)} className={cn(
                    'text-left rounded-md border p-3 transition',
                    tipo === t.id ? 'border-stone-900 ring-2 ring-stone-900/10 bg-stone-50' : 'border-stone-200 hover:border-stone-400 hover:bg-stone-50',
                    t.highlight && tipo !== t.id && 'border-violet-200 bg-violet-50/40',
                  )}>
                    <div className="flex items-center gap-2">
                      <span className="text-stone-700">{t.icon}</span>
                      <div className="text-[13px] font-semibold">{t.label}</div>
                      {t.highlight && <span className="text-[9px] uppercase tracking-widest font-medium text-violet-700 ml-auto">novo</span>}
                    </div>
                    <div className="text-[11px] text-stone-500 mt-1.5">{t.desc}</div>
                  </button>
                ))}
              </div>
            </div>
          )}

          {step === 2 && (
            <div className="space-y-3">
              <Field label="Pagador (contato)">
                <input value={contato} onChange={e => setContato(e.target.value)} placeholder="busca por nome ou CPF/CNPJ..." autoFocus
                  className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] focus:outline-none focus:border-stone-500" />
              </Field>
              <div className="text-[11px] text-stone-500">Sem integração de contatos ainda — digite o nome livre. Onda 5 ligará no Customer/Contact.</div>
            </div>
          )}

          {step === 3 && (
            <div className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <Field label="Valor (R$)">
                  <input value={valor} onChange={e => setValor(e.target.value)} placeholder="0,00"
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px] font-mono tabular-nums" />
                </Field>
                <Field label="Vencimento">
                  <input type="date" value={vencimento} onChange={e => setVencimento(e.target.value)}
                    className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]" />
                </Field>
              </div>
              <Field label="Conta destino">
                <select value={account} onChange={e => setAccount(parseInt(e.target.value, 10))} className="w-full h-8 bg-white border border-stone-300 rounded px-2 text-[12.5px]">
                  {accounts.filter(a => a.driver).map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
                  {accounts.filter(a => a.driver).length === 0 && <option value="">— sem conta com gateway ativo —</option>}
                </select>
              </Field>
              {tipo === 'pix_recv' && (
                <div className="bg-violet-50 border border-violet-200 rounded p-3 text-[10.5px] text-violet-900">
                  Mandato PIX Automático precisa ser autorizado pelo pagador no app do banco antes da 1ª cobrança.
                </div>
              )}
            </div>
          )}

          {step === 4 && (
            <div className="space-y-3">
              <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500">Revisar</div>
              <div className="border border-stone-200 rounded-md divide-y divide-stone-100 text-[12.5px]">
                <Row k="Tipo" v={tipo && driverPrincipal ? <GatewayTipoChip gateway={driverPrincipal} tipo={tipo} /> : '—'} />
                <Row k="Pagador" v={contato || '—'} />
                <Row k="Valor" v={<span className="font-semibold tabular-nums">{valor ? brl(parseFloat(valor.replace(',', '.'))) : '—'}</span>} />
                <Row k="Vencimento" v={fmtDate(vencimento)} />
                <Row k="Conta destino" v={accounts.find(a => a.id === account)?.name || '—'} />
                <Row k="Driver" v={driverPrincipal ? DRIVERS[driverPrincipal].nome : 'auto'} />
              </div>
              <div className="bg-stone-50 border border-stone-200 rounded p-3 text-[11px] text-stone-700">
                Ao confirmar, dispara <span className="font-mono">PaymentGateway::emitir{
                  tipo === 'pix_recv' ? 'PixAutomatico' : tipo === 'card' ? 'Cartao' : tipo?.startsWith('pix') ? 'Pix' : 'Boleto'
                }()</span>. Idempotency key gerada automaticamente. (Onda 5 backend wire-up pendente.)
              </div>
            </div>
          )}
        </div>

        <div className="border-t border-stone-200 p-3 flex items-center gap-2 bg-stone-50/60">
          {step > 1 && <Btn variant="outline" onClick={() => setStep(s => s - 1)}><ChevronLeft className="h-3 w-3" />Voltar</Btn>}
          <div className="flex-1" />
          <Btn variant="outline" onClick={onClose}>Cancelar</Btn>
          {!isLast && (
            <Btn variant="primary" onClick={() => canNext && setStep(s => s + 1)} disabled={!canNext}>
              Avançar<ChevronRight className="h-3 w-3" />
            </Btn>
          )}
          {isLast && (
            <Btn variant="primary" onClick={emitir} disabled={submitting}>
              <Plus className="h-3 w-3" />{submitting ? 'Emitindo…' : 'Emitir cobrança'}
            </Btn>
          )}
        </div>
      </div>
    </div>
  );
}

function Row({ k, v }: { k: string; v: ReactNode }) {
  return (
    <div className="px-3 py-2 flex items-center gap-3">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium w-[120px]">{k}</div>
      <div className="flex-1 text-stone-800">{v}</div>
    </div>
  );
}
