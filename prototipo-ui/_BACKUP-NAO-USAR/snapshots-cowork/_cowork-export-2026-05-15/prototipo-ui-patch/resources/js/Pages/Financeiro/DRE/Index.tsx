// @memcofre
//   tela: /financeiro/dre
//   module: Financeiro
//   status: em-implementacao
//   stories: US-FIN-016 (dre-mensal), US-FIN-017 (dre-comparativo)
//   rules: R-FIN-001 (multi-tenant), R-FIN-010 (simples-nacional-sem-csll)
//   adrs: ui/0114 (cockpit-v2), arq/0007 (dre-hierarquico)
//   tests: Modules/Financeiro/Tests/Feature/DREControllerTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { Card } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import PageHeader from '@/Components/shared/PageHeader';

type LineType = 'h' | 'i' | 'subtotal';

interface DRELine {
  type: LineType;
  label: string;
  valor: number;
  valor_anterior: number;
  indent: number;
  highlight: boolean;
}

interface Props {
  periodo: string;            // "Maio 2026"
  periodo_anterior: string;   // "Abril 2026"
  receita_liquida: number;
  resultado_operacional: number;
  margem_pct: number;
  meta_margem_pct: number;
  delta_pp: number;
  granularidade: 'mes' | 'trimestre' | 'ano' | '12m';
  lines: DRELine[];
  top_categorias_receita: { label: string; valor: number; pct: number }[];
}

const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);
const brlNoSign = (v: number) => brl(v).replace('R$', '').trim();

const PERIODOS: { id: 'mes' | 'trimestre' | 'ano' | '12m'; label: string }[] = [
  { id: 'mes', label: 'Mês' },
  { id: 'trimestre', label: 'Trimestre' },
  { id: 'ano', label: 'Ano' },
  { id: '12m', label: '12m' },
];

export default function DREIndex({
  periodo,
  periodo_anterior,
  receita_liquida,
  resultado_operacional,
  margem_pct,
  meta_margem_pct,
  delta_pp,
  granularidade,
  lines,
  top_categorias_receita,
}: Props) {
  const setGran = (g: 'mes' | 'trimestre' | 'ano' | '12m') => {
    router.get(route('financeiro.dre.index'), { granularidade: g }, { preserveState: true, preserveScroll: true });
  };

  const exportar = (formato: 'pdf' | 'excel') => {
    window.open(route('financeiro.dre.export', { formato, granularidade }), '_blank');
  };

  return (
    <AppShellV2>
      <PageHeader title="DRE / Relatórios" subtitle={`Demonstração de Resultado · ${periodo}`} />

      <Card className="mx-6 mt-4 overflow-hidden">
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <div className="min-w-0">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium whitespace-nowrap">Demonstração de Resultado</div>
            <div className="text-[16px] font-semibold mt-0.5 whitespace-nowrap">{periodo}</div>
          </div>
          <div className="ml-auto flex items-center gap-2 shrink-0">
            <div className="inline-flex bg-stone-100/80 rounded-md p-0.5 border border-stone-200">
              {PERIODOS.map((p) => (
                <button
                  key={p.id}
                  onClick={() => setGran(p.id)}
                  className={`h-7 px-3 rounded text-[12.5px] ${
                    p.id === granularidade ? 'bg-white shadow-sm font-medium' : 'text-stone-600'
                  }`}
                >
                  {p.label}
                </button>
              ))}
            </div>
            <Button variant="outline" size="sm" onClick={() => exportar('pdf')}>Exportar PDF</Button>
            <Button variant="outline" size="sm" onClick={() => exportar('excel')}>Excel</Button>
          </div>
        </div>

        <table className="w-full text-[12.5px] tabular-nums">
          <thead>
            <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
              <th className="pl-6 pr-2 py-2 text-left font-medium">Conta</th>
              <th className="px-2 py-2 text-right font-medium w-[140px]">{periodo}</th>
              <th className="px-2 py-2 text-right font-medium w-[100px]">% RL</th>
              <th className="px-2 py-2 text-right font-medium w-[140px]">{periodo_anterior}</th>
              <th className="px-2 py-2 text-right font-medium w-[80px]">Δ</th>
              <th className="pl-2 pr-6 py-2 w-[160px]"></th>
            </tr>
          </thead>
          <tbody>
            {lines.map((l, i) => {
              const pct = receita_liquida > 0 ? (l.valor / receita_liquida) * 100 : 0;
              const delta = l.valor_anterior !== 0 ? ((l.valor - l.valor_anterior) / Math.abs(l.valor_anterior)) * 100 : 0;
              const positive = l.valor >= 0;

              if (l.type === 'h') {
                return (
                  <tr key={i} className="border-b border-stone-100">
                    <td className="pl-6 pr-2 py-2 font-medium text-stone-900">{l.label}</td>
                    <td className="px-2 py-2 text-right font-semibold">{brlNoSign(l.valor)}</td>
                    <td className="px-2 py-2 text-right text-stone-500">{pct.toFixed(1)}%</td>
                    <td className="px-2 py-2 text-right text-stone-500">{brlNoSign(l.valor_anterior)}</td>
                    <td
                      className={`px-2 py-2 text-right ${
                        delta > 0 ? 'text-emerald-700' : delta < 0 ? 'text-rose-700' : 'text-stone-400'
                      }`}
                    >
                      {delta > 0 ? '+' : ''}{delta.toFixed(0)}%
                    </td>
                    <td className="pl-2 pr-6"></td>
                  </tr>
                );
              }
              if (l.type === 'i') {
                return (
                  <tr key={i} className="border-b border-stone-100 hover:bg-stone-50/60">
                    <td
                      className="pl-6 pr-2 py-1.5 text-stone-600"
                      style={{ paddingLeft: 24 + l.indent * 16 }}
                    >
                      {l.label}
                    </td>
                    <td className="px-2 py-1.5 text-right text-stone-700">{brlNoSign(l.valor)}</td>
                    <td className="px-2 py-1.5 text-right text-stone-400">{pct.toFixed(1)}%</td>
                    <td className="px-2 py-1.5 text-right text-stone-400">{brlNoSign(l.valor_anterior)}</td>
                    <td
                      className={`px-2 py-1.5 text-right text-[11.5px] ${
                        delta > 0 ? 'text-emerald-600' : delta < 0 ? 'text-rose-600' : 'text-stone-400'
                      }`}
                    >
                      {delta > 0 ? '+' : ''}{delta.toFixed(0)}%
                    </td>
                    <td className="pl-2 pr-6 py-1.5">
                      <div className="h-1 bg-stone-100 rounded-full overflow-hidden">
                        <div
                          className={`h-full ${positive ? 'bg-emerald-400' : 'bg-rose-400'}`}
                          style={{ width: `${Math.min(100, Math.abs(pct) * 3)}%` }}
                        />
                      </div>
                    </td>
                  </tr>
                );
              }
              return (
                <tr
                  key={i}
                  className={`border-y-2 border-stone-200 ${
                    l.highlight ? 'bg-stone-900 text-white' : 'bg-stone-50'
                  }`}
                >
                  <td className={`pl-6 pr-2 py-2.5 font-semibold ${l.highlight ? 'text-white' : ''}`}>{l.label}</td>
                  <td
                    className={`px-2 py-2.5 text-right font-bold text-[14px] ${
                      l.highlight ? 'text-white' : positive ? 'text-emerald-700' : 'text-rose-700'
                    }`}
                  >
                    {brlNoSign(l.valor)}
                  </td>
                  <td className={`px-2 py-2.5 text-right font-medium ${l.highlight ? 'text-stone-300' : 'text-stone-600'}`}>
                    {pct.toFixed(1)}%
                  </td>
                  <td className={`px-2 py-2.5 text-right ${l.highlight ? 'text-stone-400' : 'text-stone-600'}`}>
                    {brlNoSign(l.valor_anterior)}
                  </td>
                  <td
                    className={`px-2 py-2.5 text-right font-semibold ${
                      delta > 0 ? 'text-emerald-400' : delta < 0 ? 'text-rose-400' : ''
                    }`}
                  >
                    {delta > 0 ? '+' : ''}{delta.toFixed(0)}%
                  </td>
                  <td className="pl-2 pr-6"></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </Card>

      <div className="px-6 mt-4 mb-4 grid grid-cols-2 gap-4">
        <Card className="p-5">
          <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Margem operacional</div>
          <div className="mt-1 text-[28px] font-semibold tracking-tight tabular-nums">{margem_pct.toFixed(1)}%</div>
          <div className="mt-2 text-[11.5px] text-stone-500">
            vs <span className="tabular-nums">{(margem_pct - delta_pp).toFixed(1)}%</span> em {periodo_anterior.toLowerCase()} ·{' '}
            <span className={delta_pp >= 0 ? 'text-emerald-700 font-medium' : 'text-rose-700 font-medium'}>
              {delta_pp >= 0 ? '+' : ''}{delta_pp.toFixed(1)}pp
            </span>
          </div>
          <div className="mt-4 h-2 bg-stone-100 rounded-full overflow-hidden">
            <div className="h-full bg-stone-900" style={{ width: `${Math.min(100, margem_pct)}%` }} />
          </div>
          <div className="mt-1.5 flex justify-between text-[10.5px] text-stone-400">
            <span>0%</span>
            <span>meta {meta_margem_pct}%</span>
            <span>100%</span>
          </div>
        </Card>

        <Card className="p-5">
          <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">
            Top categorias receita · {periodo.toLowerCase()}
          </div>
          <div className="mt-3 space-y-2.5">
            {top_categorias_receita.map((c) => (
              <div key={c.label}>
                <div className="flex items-baseline justify-between text-[12.5px]">
                  <span className="text-stone-700">{c.label}</span>
                  <span className="tabular-nums font-medium">
                    {brl(c.valor)} <span className="text-stone-400">· {c.pct}%</span>
                  </span>
                </div>
                <div className="mt-1 h-1 bg-stone-100 rounded-full overflow-hidden">
                  <div className="h-full bg-emerald-500" style={{ width: `${c.pct}%` }} />
                </div>
              </div>
            ))}
          </div>
        </Card>
      </div>
    </AppShellV2>
  );
}
