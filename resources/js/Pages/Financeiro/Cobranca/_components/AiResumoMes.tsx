// AiResumoMes.tsx — AI panel ✦ Resumir mês (canon KB-9.75 Vendas/Index PR #1064)
import { useEffect } from 'react';
import { X } from 'lucide-react';
import { Btn } from './atoms';
import { brl, cn, copiar, DRIVERS, type Cobranca, type CobrancaKpis, type GatewayKey } from '../_lib/cobranca-shared';

interface Props {
  kpis: CobrancaKpis;
  cobs: Cobranca[];
  onClose: () => void;
}

export default function AiResumoMes({ kpis, cobs, onClose }: Props) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  const totalAberto = kpis.aberto.qtd + kpis.vencido.qtd;
  const inadimplencia = totalAberto > 0 ? ((kpis.vencido.qtd / totalAberto) * 100).toFixed(1) : '0';
  const porGateway = cobs.reduce<Record<string, number>>((acc, c) => {
    if (!c.gateway) return acc;
    acc[c.gateway] = (acc[c.gateway] || 0) + c.valor;
    return acc;
  }, {});
  const totalPorGw = Object.values(porGateway).reduce((s, v) => s + v, 0);
  const sortedGws = Object.entries(porGateway).sort((a, b) => b[1] - a[1]);
  const topGw = sortedGws[0];
  const mandatosCount = cobs.filter(c => c.tipo === 'pix_recv').length;

  // B6 "botões honestos" (2026-05-31): monta texto plain pra "Copiar resumo"
  // (formato WhatsApp/email) a partir dos mesmos dados que o painel renderiza.
  const resumoTexto = (): string => {
    const linhas: string[] = [`*Resumo Cobrança · ${monthLabel()}*`, ''];
    linhas.push(`• ${kpis.pago_mes.qtd} cobranças liquidadas — ${brl(kpis.pago_mes.valor)}`);
    if (kpis.aberto.qtd > 0) linhas.push(`• Em aberto: ${brl(kpis.aberto.valor)} (${kpis.aberto.qtd} títulos)`);
    if (kpis.vencido.qtd > 0) linhas.push(`• ⚠ Vencidas: ${kpis.vencido.qtd} — ${brl(kpis.vencido.valor)} · inadimplência ${inadimplencia}%`);
    if (sortedGws.length > 0) {
      linhas.push('', 'Por gateway:');
      sortedGws.forEach(([gw, vl]) => {
        const d = DRIVERS[gw as GatewayKey];
        if (d) linhas.push(`  - ${d.nome}: ${brl(vl)}`);
      });
    }
    if (mandatosCount > 0) linhas.push('', `• PIX Automático ativo em ${mandatosCount} mandato(s)`);
    if (kpis.mrr_pago > 0) linhas.push(`• MRR SaaS cobrado: ${brl(kpis.mrr_pago)}`);
    return linhas.join('\n');
  };

  return (
    <div className="fixed inset-0 z-40 flex justify-end" onClick={onClose} role="dialog" aria-modal="true" aria-label="Resumo IA do mês">
      <div className="absolute inset-0 bg-stone-900/30" />
      <div className="relative w-[480px] bg-white h-full shadow-xl border-l border-stone-200 flex flex-col" onClick={e => e.stopPropagation()}>
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <span style={{ fontSize: 20, color: 'oklch(0.50 0.18 295)' }}>✦</span>
          <div className="flex-1">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">IA · Resumo executivo</div>
            <div className="text-[15px] font-semibold mt-0.5">Cobrança · {monthLabel()}</div>
          </div>
          <button onClick={onClose} className="w-7 h-7 rounded hover:bg-stone-100 inline-grid place-items-center text-stone-500" aria-label="Fechar (Esc)">
            <X className="h-3.5 w-3.5" />
          </button>
        </div>

        <div className="flex-1 overflow-auto px-5 py-4 space-y-4 text-[12.5px]">
          <section>
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1.5">Panorâmica</div>
            <p className="text-stone-700 leading-relaxed">
              {kpis.pago_mes.qtd} cobranças liquidadas no mês totalizando <strong>{brl(kpis.pago_mes.valor)}</strong>.
              {kpis.aberto.qtd > 0 && <> Em aberto: <strong>{brl(kpis.aberto.valor)}</strong> ({kpis.aberto.qtd} títulos).</>}
            </p>
          </section>

          {kpis.vencido.qtd > 0 && (
            <section className="bg-destructive-soft border border-destructive/20 rounded-md p-3">
              <div className="text-[10px] uppercase tracking-widest text-destructive-fg font-medium mb-1.5">⚠ Atenção</div>
              <p className="text-destructive leading-relaxed">
                {kpis.vencido.qtd} cobranças vencidas — <strong>{brl(kpis.vencido.valor)}</strong>. Taxa de inadimplência: {inadimplencia}%.
              </p>
            </section>
          )}

          {sortedGws.length > 0 && (
            <section>
              <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1.5">Distribuição por gateway</div>
              <div className="space-y-1.5">
                {sortedGws.map(([gw, vl]) => {
                  const d = DRIVERS[gw as GatewayKey];
                  if (!d) return null;
                  const pct = totalPorGw > 0 ? ((vl / totalPorGw) * 100).toFixed(0) : '0';
                  return (
                    <div key={gw} className="flex items-center gap-2">
                      <span className={cn('w-4 h-4 rounded-sm grid place-items-center text-white text-[8.5px] font-bold', d.dot)}>{d.sigla}</span>
                      <span className="flex-1 text-stone-700">{d.nome}</span>
                      <span className="tabular-nums text-stone-900 font-medium">{brl(vl)}</span>
                      <span className="text-[10.5px] text-stone-400 tabular-nums w-8 text-right">{pct}%</span>
                    </div>
                  );
                })}
              </div>
            </section>
          )}

          <section>
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium mb-1.5">Insights</div>
            <ul className="space-y-1.5 text-stone-700">
              {topGw && DRIVERS[topGw[0] as GatewayKey] && (
                <li>• <strong>{DRIVERS[topGw[0] as GatewayKey].nome}</strong> concentra a maior parte ({brl(topGw[1])})</li>
              )}
              {mandatosCount > 0 && <li>• PIX Automático BCB ativo em {mandatosCount} mandato(s) recorrente(s)</li>}
              {kpis.pago_mes.qtd > 0 && (
                <li>• Tique médio liquidação: {brl(kpis.pago_mes.valor / Math.max(kpis.pago_mes.qtd, 1))}</li>
              )}
              {kpis.mrr_pago > 0 && <li>• MRR de assinaturas SaaS cobradas: <strong>{brl(kpis.mrr_pago)}</strong></li>}
            </ul>
          </section>
        </div>

        <div className="border-t border-stone-200 p-3 bg-stone-50/60 flex items-center gap-2">
          <span className="text-[10.5px] text-stone-500">IA · gerado agora</span>
          <div className="flex-1" />
          <Btn variant="outline" size="sm" onClick={() => copiar(resumoTexto(), 'Resumo copiado')}>Copiar resumo</Btn>
        </div>
      </div>
    </div>
  );
}

function monthLabel(): string {
  return new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(new Date());
}
