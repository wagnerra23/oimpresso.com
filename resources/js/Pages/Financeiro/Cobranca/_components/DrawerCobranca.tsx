// DrawerCobranca.tsx — drawer detalhe condicional por tipo (boleto/pix/pix_recv/card) + Timeline
import { useEffect, useRef } from 'react';
import {
  X, Copy, ShieldCheck, AlertCircle,
} from 'lucide-react';
import { Btn, StatusBadge, GatewayTipoChip, OrigemChip } from './atoms';
import {
  brl, cn, copiar, fmtDate, fmtDateRel, piiMask, DRIVERS, ORIGENS,
  type Cobranca, type Account,
} from '../_lib/cobranca-shared';

interface Props {
  cob: Cobranca;
  accounts: Account[];
  today: string;
  onClose: () => void;
}

export default function DrawerCobranca({ cob, accounts, today, onClose }: Props) {
  const drawerRef = useRef<HTMLDivElement>(null);
  const drv = DRIVERS[cob.gateway];
  const acct = accounts.find(a => a.id === cob.account_id);

  // WCAG 2.1: ESC + scroll lock + focus trap
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
      const focusable = drawerRef.current?.querySelector<HTMLElement>('button, input, select, [tabindex="0"]');
      focusable?.focus();
    }, 100);
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prev;
    };
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-30 flex justify-end" onClick={onClose} role="dialog" aria-modal="true" aria-label={`Cobrança ${cob.id}`}>
      <div className="absolute inset-0 bg-stone-900/20" />
      <div ref={drawerRef} className="relative w-[520px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>

        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-2">
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2 text-[10px] uppercase tracking-widest text-stone-500 font-medium">
              <span>Cobrança #{cob.id}</span>
              <GatewayTipoChip gateway={cob.gateway} tipo={cob.tipo} />
            </div>
            <div className="text-[15px] font-semibold mt-1 truncate">{cob.contato}</div>
            <div className="text-[11px] text-stone-500 font-mono mt-0.5">{piiMask(cob.contato_doc)}</div>
          </div>
          <StatusBadge status={cob.status} />
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500" aria-label="Fechar (Esc)">
            <X className="h-3.5 w-3.5" />
          </button>
        </div>

        <div className="flex-1 overflow-auto">

          {cob.origem_type && (
            <div className="px-5 py-2.5 border-b border-stone-100 bg-stone-50/40 flex items-center gap-2">
              <div className="text-[10px] uppercase tracking-widest font-medium text-stone-500">Origem</div>
              <OrigemChip tipo={cob.origem_type} label={cob.origem_label || ORIGENS[cob.origem_type]?.label} />
              {cob.origem_id && <span className="text-[11px] text-stone-500 font-mono ml-auto">#{cob.origem_id}</span>}
            </div>
          )}

          <div className="px-5 py-4 grid grid-cols-2 gap-x-5 gap-y-3 text-[12.5px]">
            <Cell label="Vencimento">
              {fmtDate(cob.vencimento)} <span className="text-[10.5px] text-stone-400">· {fmtDateRel(cob.vencimento, today)}</span>
            </Cell>
            <Cell label="Valor">
              <span className="font-semibold tabular-nums">{brl(cob.valor)}</span>
            </Cell>
            <Cell label="Conta destino" colSpan>
              <div className="font-medium inline-flex items-center gap-2">
                <span className={cn('w-2 h-2 rounded-sm', drv?.dot)} />
                {acct?.name || '—'}
              </div>
              {acct?.banco && (
                <div className="text-[10.5px] text-stone-400 mt-0.5">
                  {acct.banco}{acct.agencia ? ` · Ag ${acct.agencia} · Cc ${acct.conta}` : acct.conta ? ` · ${acct.conta}` : ''}
                </div>
              )}
            </Cell>
            <Cell label="Gateway" colSpan>
              {drv?.nome}
            </Cell>
          </div>

          {cob.tipo === 'boleto' && <SectionBoleto cob={cob} />}
          {(cob.tipo === 'pix_cob' || cob.tipo === 'pix_cobv') && <SectionPix cob={cob} />}
          {cob.tipo === 'pix_recv' && <SectionPixRecv cob={cob} today={today} />}
          {cob.tipo === 'card' && <SectionCard cob={cob} />}

          {cob.status === 'erro' && cob.erro_msg && (
            <div className="px-5 py-3 border-t border-stone-200">
              <div className="text-[10px] uppercase tracking-widest text-rose-700 font-medium mb-2 flex items-center gap-1.5">
                <AlertCircle className="h-3 w-3" />Erro do gateway
              </div>
              <div className="bg-rose-50 border border-rose-200 rounded px-3 py-2 text-[11.5px] text-rose-900 font-mono">
                {cob.erro_msg}
              </div>
              {/* B6 "botões honestos" (2026-05-31): "Tentar reemitir" REMOVIDO —
                  não há rota de reemissão (POST /cobranca/emitir é pra nova
                  cobrança, não retry de uma falha existente). */}
            </div>
          )}

          <div className="px-5 py-4 border-t border-stone-200">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-2 font-medium">Linha do tempo</div>
            <Timeline cob={cob} />
          </div>
        </div>

        {/* B6 "botões honestos" (2026-05-31): footer só mostra ação real AGORA.
            "Copiar BR Code" (PIX) → clipboard via copiar(). REMOVIDOS por falta
            de rota backend: "Baixar PDF" + "Link 2ª via" (sem endpoint boleto-pdf
            no CobrancaController), "Estornar" (sem rota refund) e "Cancelar"
            (POST /boletos/{id}/cancelar é pro model legacy BoletoRemessa, não pra
            PaymentGateway\Cobranca desta tela). Reentram quando o endpoint existir. */}
        {cob.tipo?.startsWith('pix') && cob.tipo !== 'pix_recv' && cob.pix_emv && (
          <div className="border-t border-stone-200 p-3 flex items-center gap-2 bg-white">
            <Btn variant="outline" onClick={() => copiar(cob.pix_emv, 'BR Code copiado')}>
              <Copy className="h-3 w-3" />Copiar BR Code
            </Btn>
          </div>
        )}
      </div>
    </div>
  );
}

function Cell({ label, children, colSpan }: { label: string; children: React.ReactNode; colSpan?: boolean }) {
  return (
    <div className={colSpan ? 'col-span-2' : ''}>
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">{label}</div>
      <div className="font-medium mt-0.5">{children}</div>
    </div>
  );
}

function SectionBoleto({ cob }: { cob: Cobranca }) {
  return (
    <div className="px-5 py-4 border-t border-stone-200 space-y-3 text-[12.5px]">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Boleto</div>
      {cob.nosso_numero && (
        <div>
          <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1 font-medium">Nosso número</div>
          <div className="font-mono">{cob.nosso_numero}</div>
        </div>
      )}
      {cob.linha_digitavel && (
        <div>
          <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1 font-medium">Linha digitável</div>
          <div className="flex items-center gap-2 bg-stone-50 border border-stone-200 rounded px-2.5 py-2">
            <div className="font-mono text-[11.5px] flex-1 break-all">{cob.linha_digitavel}</div>
            <Btn variant="outline" size="xs" aria-label="Copiar linha digitável" onClick={() => copiar(cob.linha_digitavel, 'Linha digitável copiada')}><Copy className="h-3 w-3" /></Btn>
          </div>
        </div>
      )}
      {cob.codigo_barras && (
        <div>
          <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1 font-medium">Código de barras</div>
          <div className="font-mono text-[10.5px] text-stone-500 break-all">{cob.codigo_barras}</div>
        </div>
      )}
    </div>
  );
}

function SectionPix({ cob }: { cob: Cobranca }) {
  return (
    <div className="px-5 py-4 border-t border-stone-200 space-y-3 text-[12.5px]">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">
        PIX {cob.tipo === 'pix_cob' ? 'cob (imediata)' : 'cobv (com vencimento)'}
      </div>
      <div className="flex gap-3">
        <div className="w-[140px] h-[140px] bg-white border border-stone-300 rounded-md p-2 grid place-items-center shrink-0">
          <FakeQR />
        </div>
        <div className="flex-1 min-w-0">
          <div className="text-[10px] uppercase tracking-widest text-stone-500 mb-1 font-medium">BR Code copia-e-cola</div>
          {cob.pix_emv ? (
            <div className="bg-stone-50 border border-stone-200 rounded px-2.5 py-2">
              <div className="font-mono text-[10.5px] text-stone-700 break-all">{cob.pix_emv}</div>
            </div>
          ) : (
            <div className="text-[11px] text-stone-400 italic">BR Code não disponível</div>
          )}
          {cob.pix_emv && (
            <Btn variant="outline" size="xs" className="mt-2" onClick={() => copiar(cob.pix_emv, 'BR Code copiado')}><Copy className="h-3 w-3" />Copiar BR Code</Btn>
          )}
        </div>
      </div>
    </div>
  );
}

function SectionPixRecv({ cob, today }: { cob: Cobranca; today: string }) {
  return (
    <div className="px-5 py-4 border-t border-stone-200 space-y-3 text-[12.5px]">
      <div className="flex items-center gap-2">
        <div className="text-[10px] uppercase tracking-widest text-violet-700 font-medium">PIX Automático · mandato BCB</div>
        {cob.status === 'emitida' && (
          <span className="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded border bg-emerald-50 text-emerald-700 border-emerald-200">
            <span className="w-1 h-1 rounded-full bg-emerald-500" />mandato ativo
          </span>
        )}
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Cell label="Ciclo">{cob.mandato_ciclo || 'mensal'}</Cell>
        <Cell label="Mandato desde">{fmtDate(cob.mandato_inicio || cob.emitida_em?.slice(0, 10))}</Cell>
        <Cell label="Próxima cobrança">{fmtDate(cob.mandato_proximo || cob.vencimento)}</Cell>
        <Cell label="Próximo evento">{fmtDateRel(cob.mandato_proximo || cob.vencimento, today)}</Cell>
      </div>
      <div className="bg-violet-50 border border-violet-200 rounded p-2.5 text-[11px] text-violet-900">
        <div className="flex gap-2">
          <ShieldCheck className="h-3.5 w-3.5 mt-0.5 shrink-0" />
          <div>Resolução BCB 380/2024 · pagador pode cancelar mandato a qualquer momento via app do banco · gera evento <span className="font-mono">CobrancaCancelada</span>.</div>
        </div>
      </div>
    </div>
  );
}

function SectionCard({ cob }: { cob: Cobranca }) {
  return (
    <div className="px-5 py-4 border-t border-stone-200 space-y-3 text-[12.5px]">
      <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Cartão de crédito</div>
      <div className="bg-stone-50 border border-stone-200 rounded p-3 flex items-center gap-3">
        <div className="w-10 h-7 rounded bg-stone-900 grid place-items-center text-white text-[10px] font-bold uppercase">
          {cob.card_brand || 'visa'}
        </div>
        <div className="flex-1">
          <div className="font-mono">•••• •••• •••• {cob.card_last4 || '****'}</div>
          <div className="text-[10.5px] text-stone-400 mt-0.5">tokenizado no provedor · sem PAN local</div>
        </div>
        {cob.card_3ds && (
          <span className="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded border bg-emerald-50 text-emerald-700 border-emerald-200">
            <ShieldCheck className="h-2.5 w-2.5" />3DS autenticado
          </span>
        )}
      </div>
    </div>
  );
}

function FakeQR() {
  const cells: React.ReactElement[] = [];
  for (let i = 0; i < 21 * 21; i++) {
    const x = i % 21;
    const y = Math.floor(i / 21);
    const isFinder = (x < 7 && y < 7) || (x > 13 && y < 7) || (x < 7 && y > 13);
    const finderOn = isFinder && (
      (x === 0 || x === 6 || y === 0 || y === 6) ||
      (x >= 2 && x <= 4 && y >= 2 && y <= 4)
    );
    const seed = (x * 31 + y * 17 + 7) % 11;
    const on = finderOn || (!isFinder && seed < 4);
    cells.push(<div key={i} className={cn('w-[5px] h-[5px]', on ? 'bg-stone-900' : 'bg-transparent')} />);
  }
  return <div className="grid" style={{ gridTemplateColumns: 'repeat(21, 5px)', gap: 0 }}>{cells}</div>;
}

function Timeline({ cob }: { cob: Cobranca }) {
  const evs: Array<{ ts: string | null; label: string; actor: string; meta?: string; severity?: 'rose' }> = [
    cob.emitida_em ? { ts: cob.emitida_em, label: 'Cobrança emitida', actor: `gateway ${cob.gateway} · ${cob.tipo}` } : null,
    cob.status === 'paga' && cob.paga_em
      ? { ts: cob.paga_em, label: 'Pagamento confirmado', actor: 'webhook · liquidação automática' }
      : null,
    cob.status === 'vencida'
      ? { ts: cob.vencimento, label: 'Venceu sem pagamento', actor: 'evento CobrancaVencida · smart retry agendado' }
      : null,
    cob.status === 'cancelada' && cob.cancelada_em
      ? { ts: cob.cancelada_em, label: 'Cobrança cancelada', actor: 'cancelada manualmente', meta: cob.cancelamento_motivo ?? undefined }
      : null,
    cob.status === 'erro' && cob.erro_msg
      ? { ts: cob.emitida_em, label: 'Erro do gateway', actor: cob.erro_msg, severity: 'rose' as const }
      : null,
  ].filter((e): e is NonNullable<typeof e> => e !== null);

  return (
    <div className="space-y-2.5">
      {evs.map((e, i) => (
        <div key={i} className="flex gap-3 text-[12px]">
          <div className="w-[64px] text-stone-500 tabular-nums whitespace-nowrap pt-0.5 text-[10.5px]">
            {fmtDate(e.ts?.slice(0, 10))} {e.ts?.slice(11, 16)}
          </div>
          <div className="w-2 mt-1.5 relative">
            <div className={cn('absolute inset-0 w-1.5 h-1.5 rounded-full top-0', e.severity === 'rose' ? 'bg-rose-500' : 'bg-stone-900')} />
            {i < evs.length - 1 && <div className="absolute left-[3px] top-2 w-px h-7 bg-stone-200" />}
          </div>
          <div className="flex-1 min-w-0">
            <div className={cn('font-medium', e.severity === 'rose' ? 'text-rose-700' : 'text-stone-900')}>{e.label}</div>
            <div className="text-stone-500 text-[11px] mt-0.5">
              {e.actor}{e.meta && <span className="ml-1 text-stone-400">· {e.meta}</span>}
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}
