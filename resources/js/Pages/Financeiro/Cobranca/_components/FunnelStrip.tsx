// FunnelStrip.tsx — funil 5 etapas + chip lateral mandato cancelado
import { brl, cn, type CobrancaFunil } from '../_lib/cobranca-shared';

export default function FunnelStrip({ funil }: { funil: CobrancaFunil }) {
  return (
    <div className="border border-stone-200 bg-white rounded-md overflow-hidden">
      <div className="px-3.5 py-1.5 text-[10px] uppercase tracking-widest font-medium text-stone-500 border-b border-stone-100 flex items-center justify-between">
        <span>Funil de cobrança · mês corrente</span>
        {funil.mandatos_cancelados > 0 && (
          <span className="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded border bg-rose-50 text-rose-700 border-rose-200">
            <span className="w-1 h-1 rounded-full bg-rose-500" />
            {funil.mandatos_cancelados} mandato(s) cancelado(s)
          </span>
        )}
      </div>
      <div className="flex">
        {[
          { l: 'Em aberto',        v: funil.aberto.qtd,         vv: funil.aberto.valor ? brl(funil.aberto.valor) : (funil.aberto.desc || '—'), active: true },
          { l: '→ Lembrete',       v: funil.lembrete.qtd,       vv: funil.lembrete.desc || '3d antes do vcto' },
          { l: '→ Cobrança ativa', v: funil.cobranca_ativa.qtd, vv: funil.cobranca_ativa.desc || '1-5d após vcto' },
          { l: '→ Vencidos +5d',   v: funil.vencido_5d.qtd,     vv: funil.vencido_5d.valor ? brl(funil.vencido_5d.valor) : (funil.vencido_5d.desc || '—'), alert: funil.vencido_5d.qtd > 0 },
          { l: '→ Protesto',       v: funil.protesto.qtd,       vv: funil.protesto.desc || '30d+' },
        ].map((s, i) => (
          <div key={i} className={cn(
            'flex-1 px-4 py-3 border-r border-stone-100 last:border-r-0',
            s.active && 'bg-blue-50/40',
            s.alert && 'bg-rose-50/40',
          )}>
            <div className={cn(
              'text-[10.5px] font-medium',
              s.alert ? 'text-rose-700' : s.active ? 'text-blue-700' : 'text-stone-500',
            )}>{s.l}</div>
            <div className="text-[18px] font-semibold tabular-nums tracking-tight mt-1">{s.v}</div>
            <div className={cn(
              'text-[10.5px] tabular-nums mt-0.5',
              s.alert ? 'text-rose-600' : 'text-stone-400',
            )}>{s.vv}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
