// @memcofre
//   tela: /financeiro/fluxo-caixa
//   module: Financeiro
//   status: em-implementacao
//   stories: US-FIN-014 (fluxo-caixa-projetado)
//   rules: R-FIN-001 (multi-tenant), R-FIN-008 (limite-minimo-caixa)
//   adrs: ui/0114 (cockpit-v2)
//   tests: Modules/Financeiro/Tests/Feature/FluxoControllerTest
//
// Origem: prototipo Cowork "Fluxo de Caixa" (Financeiro.html § Fluxo), aprovado por [W] 2026-05-09.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useMemo } from 'react';
import { Card } from '@/Components/ui/card';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';

interface Dia {
  data: string;            // ISO yyyy-mm-dd
  data_label: string;      // "14 mai"
  is_today: boolean;
  is_past: boolean;
  entradas: number;
  saidas: number;
  liquido: number;
  saldo_acumulado: number;
  eventos: { id: number; kind: 'receivable' | 'payable'; descricao: string; contraparte: string; categoria: string; valor: number }[];
}

interface Props {
  saldo_hoje: number;
  saldo_30d: number;
  pior_dia: { saldo: number; data_label: string };
  margem_minima: number;
  conta: string;
  dias: Dia[];
}

const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);
const brlNoSign = (v: number) => brl(Math.abs(v)).replace('R$', '').trim();

export default function FluxoIndex({ saldo_hoje, saldo_30d, pior_dia, margem_minima, conta, dias }: Props) {
  const { minSaldo, maxSaldo, minNet, maxNet } = useMemo(() => {
    const saldos = dias.map((d) => d.saldo_acumulado);
    const nets = dias.map((d) => Math.abs(d.liquido) || 1);
    return {
      minSaldo: Math.min(...saldos, 0),
      maxSaldo: Math.max(...saldos),
      minNet: Math.min(...nets),
      maxNet: Math.max(...nets),
    };
  }, [dias]);

  const range = maxSaldo - minSaldo || 1;
  const limitTopPct = (1 - (margem_minima - minSaldo) / range) * 100;

  const proxEventos = dias.filter((d) => !d.is_past && d.eventos.length > 0).slice(0, 7);

  return (
    <AppShellV2>
      <PageHeader title="Fluxo de caixa" subtitle="Projeção 35 dias · saldo, entradas e saídas dia-a-dia" />

      <KpiGrid columns={4}>
        <KpiCard label="Saldo hoje" value={brl(saldo_hoje)} caption={conta} dark />
        <KpiCard
          label="Projeção 30 dias"
          value={brl(saldo_30d)}
          caption={`${saldo_30d >= saldo_hoje ? 'alta' : 'queda'} de ${brl(Math.abs(saldo_30d - saldo_hoje))} vs hoje`}
          tone={saldo_30d >= saldo_hoje ? 'emerald' : 'rose'}
        />
        <KpiCard label="Pior dia previsto" value={brl(pior_dia.saldo)} caption={pior_dia.data_label} tone="amber" />
        <KpiCard label="Margem mínima" value={brl(margem_minima)} caption="limite definido" />
      </KpiGrid>

      <Card className="mx-6 mt-4 p-5">
        <div className="flex items-center justify-between mb-3">
          <div>
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Saldo projetado · próximos 35 dias</div>
            <div className="text-[14px] font-semibold mt-0.5">linha laranja = limite mínimo · barras = saldo acumulado</div>
          </div>
          <div className="flex items-center gap-3 text-[11.5px] text-stone-500">
            <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-stone-900" /> saldo</span>
            <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-emerald-500" /> entrada</span>
            <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-sm bg-rose-500" /> saída</span>
          </div>
        </div>

        <div className="relative h-[220px] border-b border-stone-200 mt-2">
          <div
            className="absolute left-0 right-0 border-t border-dashed border-amber-400"
            style={{ top: `${limitTopPct}%` }}
          >
            <span className="absolute -top-4 right-0 text-[10px] text-amber-700 font-medium bg-white px-1">
              {brl(margem_minima)} mínimo
            </span>
          </div>
          <div className="absolute inset-0 flex items-end gap-px">
            {dias.map((d) => {
              const h = ((d.saldo_acumulado - minSaldo) / range) * 100;
              const baixo = d.saldo_acumulado < margem_minima;
              return (
                <div key={d.data} className="flex-1 h-full flex flex-col justify-end relative group">
                  <div
                    className={`w-full ${
                      d.is_past ? 'bg-stone-300' : d.is_today ? 'bg-stone-900' : 'bg-stone-700'
                    } ${baixo ? '!bg-amber-500' : ''}`}
                    style={{ height: `${h}%` }}
                  />
                  {d.eventos.length > 0 && (
                    <div className="hidden group-hover:block absolute -top-14 left-1/2 -translate-x-1/2 z-10 bg-stone-900 text-white text-[10.5px] rounded px-2 py-1 whitespace-nowrap tabular-nums">
                      {d.data_label} · {brl(d.saldo_acumulado)}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>

        <div className="flex gap-px mt-1.5 text-[9.5px] text-stone-500 tabular-nums">
          {dias.map((d, i) => (
            <div key={d.data} className={`flex-1 text-center ${d.is_today ? 'font-bold text-stone-900' : ''}`}>
              {i % 5 === 0 || d.is_today ? d.data_label : ''}
            </div>
          ))}
        </div>
      </Card>

      <Card className="mx-6 mt-4 mb-4 overflow-hidden">
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <div className="min-w-0">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium whitespace-nowrap">Próximos eventos</div>
            <div className="text-[14px] font-semibold mt-0.5 whitespace-nowrap">7 dias adiante</div>
          </div>
          <div className="ml-auto text-[11.5px] text-stone-500 whitespace-nowrap shrink-0">
            {proxEventos.reduce((s, d) => s + d.eventos.length, 0)} lançamentos
          </div>
        </div>
        <table className="w-full text-[12.5px] tabular-nums">
          <tbody>
            {proxEventos.flatMap((d) =>
              d.eventos.map((ev, j) => (
                <tr key={ev.id} className={`border-b border-stone-100 hover:bg-stone-50/60 ${j === 0 ? 'border-t-2 border-t-stone-100' : ''}`}>
                  <td className="pl-6 pr-3 py-2 w-[110px] text-stone-700">{j === 0 ? d.data_label : ''}</td>
                  <td className="px-2 py-2">
                    <span
                      className={`inline-grid place-items-center rounded ${
                        ev.kind === 'receivable' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                      }`}
                      style={{ width: 22, height: 22 }}
                    >
                      {ev.kind === 'receivable' ? '↓' : '↑'}
                    </span>
                  </td>
                  <td className="px-2 py-2 font-medium text-stone-900 truncate">{ev.descricao}</td>
                  <td className="px-2 py-2 text-stone-600">{ev.contraparte}</td>
                  <td className="px-2 py-2 text-stone-500">{ev.categoria}</td>
                  <td className="pr-6 py-2 text-right font-medium">
                    <span className={ev.kind === 'receivable' ? 'text-emerald-700' : 'text-stone-900'}>
                      {ev.kind === 'receivable' ? '+' : '−'} {brlNoSign(ev.valor)}
                    </span>
                  </td>
                  <td className="pr-6 py-2 text-right text-stone-700 font-medium w-[120px]">
                    {j === d.eventos.length - 1 ? brl(d.saldo_acumulado) : ''}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </Card>
    </AppShellV2>
  );
}
