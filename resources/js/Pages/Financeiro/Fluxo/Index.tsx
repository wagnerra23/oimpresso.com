// @memcofre
//   tela: /financeiro/fluxo
//   module: Financeiro
//   status: em-implementacao
//   stories: US-FIN-014 (fluxo-caixa-projetado), US-FIN-014c (fluxo-realizado)
//   rules: R-FIN-001 (multi-tenant), R-FIN-008 (limite-minimo-caixa)
//   adrs: ui/0114 (cockpit-v2), 0093 (multi-tenant Tier 0)
//   tests: Modules/Financeiro/Tests/Feature/FluxoControllerTest
//
// Origem: prototipo Cowork "Fluxo de Caixa" aprovado [W] 2026-05-09.
// Decisões Q1-Q4 aprovadas [W] 2026-05-14 (memory/requisitos/Financeiro/fluxo-visual-comparison.md).
// Fase 3 deprecação legacy 2026-05-21: tab Realizado absorve Cash Flow legacy
//  (`/account/cash-flow` → 301 → `/financeiro/fluxo?tab=realizado` via PR #1283).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Card } from '@/Components/ui/card';
import { router } from '@inertiajs/react';
import { useMemo, type ReactNode } from 'react';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';

interface Dia {
  data: string;
  data_label: string;
  is_today: boolean;
  is_past: boolean;
  entradas: number;
  saidas: number;
  liquido: number;
  saldo_acumulado: number;
  eventos: {
    id: number;
    kind: 'receivable' | 'payable';
    descricao: string;
    contraparte: string;
    categoria: string;
    valor: number;
  }[];
}

interface MesRealizado {
  mes: string;
  mes_label: string;
  ano: number;
  entradas: number;
  saidas: number;
  saldo: number;
  qtd_baixas: number;
  is_current: boolean;
}

interface Realizado {
  meta: {
    meses_janela: number;
    primeiro_mes: string;
    ultimo_mes: string;
    business_id: number;
  };
  totais: {
    entradas: number;
    saidas: number;
    saldo: number;
    qtd_baixas: number;
  };
  meses: MesRealizado[];
}

type TabAtiva = 'projetado' | 'realizado';

interface Props {
  saldo_hoje: number;
  saldo_30d: number;
  pior_dia: { saldo: number; data_label: string };
  margem_minima: number;
  conta: string;
  dias: Dia[];
  tab?: TabAtiva;
  realizado?: Realizado | null;
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

const brlNoSign = (v: number) => brl(Math.abs(v)).replace('R$', '').trim();

function TabSwitcher({ tab }: { tab: TabAtiva }) {
  // Fase 3 — pill segmented control consistente com Cobranca/Index.tsx pattern.
  // router.visit preserva scroll + replace na URL (?tab=X) pra deep-link funcionar.
  const trocaTab = (alvo: TabAtiva) => {
    if (alvo === tab) return;
    router.visit(`/financeiro/fluxo?tab=${alvo}`, {
      preserveScroll: true,
      replace: true,
    });
  };

  const items: { id: TabAtiva; label: string; hint: string }[] = [
    { id: 'projetado', label: 'Projetado', hint: 'próx 35 dias' },
    { id: 'realizado', label: 'Realizado', hint: 'últ 12 meses' },
  ];

  return (
    <div className="px-6 pt-3 pb-1">
      <div className="inline-flex bg-stone-100/80 rounded-md p-0.5 border border-stone-200">
        {items.map((it) => (
          <button
            key={it.id}
            type="button"
            onClick={() => trocaTab(it.id)}
            className={
              'h-8 px-4 rounded text-[12.5px] flex items-center gap-2 transition tabular-nums ' +
              (tab === it.id
                ? 'bg-white shadow-sm font-medium text-stone-900'
                : 'text-stone-600 hover:text-stone-800')
            }
            aria-pressed={tab === it.id}
          >
            <span>{it.label}</span>
            <span
              className={
                'text-[10px] uppercase tracking-wider ' +
                (tab === it.id ? 'text-stone-500' : 'text-stone-400')
              }
            >
              {it.hint}
            </span>
          </button>
        ))}
      </div>
    </div>
  );
}

function ProjetadoView({
  saldo_hoje,
  saldo_30d,
  pior_dia,
  margem_minima,
  conta,
  dias,
}: Pick<Props, 'saldo_hoje' | 'saldo_30d' | 'pior_dia' | 'margem_minima' | 'conta' | 'dias'>) {
  const { minSaldo, maxSaldo } = useMemo(() => {
    const saldos = dias.map((d) => d.saldo_acumulado);
    return {
      minSaldo: Math.min(...saldos, 0),
      maxSaldo: Math.max(...saldos),
    };
  }, [dias]);

  const range = maxSaldo - minSaldo || 1;
  const limitTopPct = (1 - (margem_minima - minSaldo) / range) * 100;

  const proxEventos = dias.filter((d) => !d.is_past && d.eventos.length > 0).slice(0, 7);

  return (
    <>
      {/* Onda 18 (2026-05-19) #47 — Fallback friendly quando biz sem ContaBancaria. */}
      {conta === 'Sem conta cadastrada' && (
        <div style={{
          background: 'oklch(0.96 0.04 70)',
          border: '1px solid oklch(0.85 0.10 70)',
          borderRadius: 8,
          padding: '12px 16px',
          marginBottom: 16,
          fontSize: 13,
          color: 'oklch(0.40 0.13 70)',
        }}>
          ⓘ Nenhuma conta bancária cadastrada — Fluxo de caixa precisa de saldo inicial pra projetar.{' '}
          <a href="/financeiro/contas-bancarias" style={{ textDecoration: 'underline', fontWeight: 600 }}>
            Cadastrar conta agora →
          </a>
        </div>
      )}

      {/* Onda 15 (2026-05-19) — KPI grid canon fin-stats (Saldo hoje = hero dark warm) */}
      <div className="fin-stats">
        <div className="fin-stat fin-stat-hero">
          <small>SALDO HOJE</small>
          <b>{brl(saldo_hoje)}</b>
          <span className="fin-stat-hint">{conta}</span>
        </div>
        <div className="fin-stat">
          <small>PROJEÇÃO 30 DIAS</small>
          <b className={saldo_30d >= saldo_hoje ? 'fin-num-pos' : 'fin-num-neg'}>{brl(saldo_30d)}</b>
          <span className="fin-stat-hint">
            {saldo_30d >= saldo_hoje ? 'alta' : 'queda'} de {brl(Math.abs(saldo_30d - saldo_hoje))} vs hoje
          </span>
        </div>
        <div className="fin-stat">
          <small>PIOR DIA PREVISTO</small>
          <b>{brl(pior_dia.saldo)}</b>
          <span className="fin-stat-hint">{pior_dia.data_label}</span>
        </div>
        <div className="fin-stat">
          <small>MARGEM MÍNIMA</small>
          <b>{brl(margem_minima)}</b>
          <span className="fin-stat-hint">limite definido</span>
        </div>
      </div>

      <Card className="mx-6 mt-4 p-5">
        <div className="flex items-center justify-between mb-3">
          <div>
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">Saldo projetado · próximos 35 dias</div>
            <div className="text-[14px] font-semibold mt-0.5">linha laranja = limite mínimo · barras = saldo acumulado</div>
          </div>
          <div className="flex items-center gap-3 text-[11.5px] text-stone-500">
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-stone-900" /> saldo
            </span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-emerald-500" /> entrada
            </span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-rose-500" /> saída
            </span>
          </div>
        </div>

        <div className="relative h-[220px] border-b border-stone-200 mt-2">
          <div className="absolute left-0 right-0 border-t border-dashed border-amber-400" style={{ top: `${limitTopPct}%` }}>
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
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium whitespace-nowrap">
              Próximos eventos
            </div>
            <div className="text-[14px] font-semibold mt-0.5 whitespace-nowrap">7 dias adiante</div>
          </div>
          <div className="ml-auto text-[11.5px] text-stone-500 whitespace-nowrap shrink-0">
            {proxEventos.reduce((s, d) => s + d.eventos.length, 0)} lançamentos
          </div>
        </div>
        {proxEventos.length === 0 ? (
          <div className="px-6 py-8 text-center text-[12.5px] text-stone-500">
            Nenhum evento programado nos próximos 7 dias.
          </div>
        ) : (
          <table className="w-full text-[12.5px] tabular-nums">
            <tbody>
              {proxEventos.flatMap((d) =>
                d.eventos.map((ev, j) => (
                  <tr
                    key={ev.id}
                    className={`border-b border-stone-100 hover:bg-stone-50/60 ${
                      j === 0 ? 'border-t-2 border-t-stone-100' : ''
                    }`}
                  >
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
        )}
      </Card>
    </>
  );
}

function RealizadoView({ realizado }: { realizado: Realizado | null | undefined }) {
  // Hook ANTES do early return (Rules of Hooks). meses default [] pra evitar
  // spread em undefined; Math.max([], 1) = 1 (safe).
  const meses = realizado?.meses ?? [];
  const maxAbs = useMemo(
    () => Math.max(...meses.map((m) => Math.max(m.entradas, m.saidas)), 1),
    [meses],
  );

  // Defensiva: tab=realizado deve sempre trazer payload, mas se Inertia partial
  // reload pular, renderiza skeleton-like empty state.
  if (!realizado) {
    return (
      <Card className="mx-6 mt-4 mb-4 p-8 text-center text-[13px] text-stone-500">
        Carregando movimentações realizadas…
      </Card>
    );
  }

  const { totais, meta } = realizado;

  return (
    <>
      {/* KPI grid Realizado — 4 cards: entradas/saidas/saldo do período + qtd baixas */}
      <div className="fin-stats">
        <div className="fin-stat fin-stat-hero">
          <small>SALDO {meta.meses_janela}M</small>
          <b className={totais.saldo >= 0 ? 'fin-num-pos' : 'fin-num-neg'}>{brl(totais.saldo)}</b>
          <span className="fin-stat-hint">{meses[0]?.mes_label} → {meses[meses.length - 1]?.mes_label}</span>
        </div>
        <div className="fin-stat">
          <small>ENTRADAS</small>
          <b className="fin-num-pos">{brl(totais.entradas)}</b>
          <span className="fin-stat-hint">recebimentos confirmados</span>
        </div>
        <div className="fin-stat">
          <small>SAÍDAS</small>
          <b className="fin-num-neg">{brl(totais.saidas)}</b>
          <span className="fin-stat-hint">pagamentos confirmados</span>
        </div>
        <div className="fin-stat">
          <small>BAIXAS REGISTRADAS</small>
          <b>{totais.qtd_baixas}</b>
          <span className="fin-stat-hint">no período</span>
        </div>
      </div>

      {/* Gráfico de barras gemelhas por mês (entradas vs saídas lado-a-lado) */}
      <Card className="mx-6 mt-4 p-5">
        <div className="flex items-center justify-between mb-3">
          <div>
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium">
              Movimentações realizadas · últimos {meta.meses_janela} meses
            </div>
            <div className="text-[14px] font-semibold mt-0.5">
              barra verde = entradas · barra rosa = saídas (escala R$)
            </div>
          </div>
          <div className="flex items-center gap-3 text-[11.5px] text-stone-500">
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-emerald-500" /> entradas
            </span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-rose-500" /> saídas
            </span>
          </div>
        </div>

        <div className="relative h-[200px] border-b border-stone-200 mt-2 flex items-end gap-1">
          {meses.map((m) => {
            const hEnt = (m.entradas / maxAbs) * 100;
            const hSai = (m.saidas / maxAbs) * 100;
            return (
              <div key={m.mes} className="flex-1 h-full flex items-end gap-0.5 relative group">
                <div
                  className={`flex-1 ${m.is_current ? 'bg-emerald-600' : 'bg-emerald-500'}`}
                  style={{ height: `${hEnt}%` }}
                  title={`${m.mes_label}: entradas ${brl(m.entradas)}`}
                />
                <div
                  className={`flex-1 ${m.is_current ? 'bg-rose-600' : 'bg-rose-500'}`}
                  style={{ height: `${hSai}%` }}
                  title={`${m.mes_label}: saídas ${brl(m.saidas)}`}
                />
                <div className="hidden group-hover:block absolute -top-16 left-1/2 -translate-x-1/2 z-10 bg-stone-900 text-white text-[10.5px] rounded px-2 py-1 whitespace-nowrap tabular-nums">
                  {m.mes_label} · saldo {brl(m.saldo)}
                </div>
              </div>
            );
          })}
        </div>

        <div className="flex gap-1 mt-1.5 text-[9.5px] text-stone-500 tabular-nums">
          {meses.map((m) => (
            <div
              key={m.mes}
              className={`flex-1 text-center ${m.is_current ? 'font-bold text-stone-900' : ''}`}
            >
              {m.mes_label}
            </div>
          ))}
        </div>
      </Card>

      {/* Tabela detalhada mês × entradas × saídas × saldo */}
      <Card className="mx-6 mt-4 mb-4 overflow-hidden">
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <div className="min-w-0">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium whitespace-nowrap">
              Detalhe por mês
            </div>
            <div className="text-[14px] font-semibold mt-0.5 whitespace-nowrap">
              {meses.length} {meses.length === 1 ? 'mês' : 'meses'}
            </div>
          </div>
          <div className="ml-auto text-[11.5px] text-stone-500 whitespace-nowrap shrink-0">
            {totais.qtd_baixas} {totais.qtd_baixas === 1 ? 'baixa' : 'baixas'} registradas
          </div>
        </div>

        {totais.qtd_baixas === 0 ? (
          <div className="px-6 py-8 text-center text-[12.5px] text-stone-500">
            Nenhuma baixa registrada nos últimos {meta.meses_janela} meses.
          </div>
        ) : (
          <table className="w-full text-[12.5px] tabular-nums">
            <thead>
              <tr className="border-b border-stone-200 text-[10.5px] uppercase tracking-widest text-stone-500">
                <th className="pl-6 pr-3 py-2 text-left font-medium">Mês</th>
                <th className="px-3 py-2 text-right font-medium">Entradas</th>
                <th className="px-3 py-2 text-right font-medium">Saídas</th>
                <th className="px-3 py-2 text-right font-medium">Saldo</th>
                <th className="pr-6 py-2 text-right font-medium">Baixas</th>
              </tr>
            </thead>
            <tbody>
              {meses.map((m) => (
                <tr
                  key={m.mes}
                  className={`border-b border-stone-100 hover:bg-stone-50/60 ${
                    m.is_current ? 'bg-stone-50/40' : ''
                  }`}
                >
                  <td className={`pl-6 pr-3 py-2 ${m.is_current ? 'font-semibold text-stone-900' : 'text-stone-700'}`}>
                    {m.mes_label}
                    {m.is_current && (
                      <span className="ml-2 text-[10px] uppercase tracking-wider text-stone-500">atual</span>
                    )}
                  </td>
                  <td className="px-3 py-2 text-right text-emerald-700 font-medium">
                    {m.entradas > 0 ? '+ ' + brlNoSign(m.entradas) : '—'}
                  </td>
                  <td className="px-3 py-2 text-right text-rose-700 font-medium">
                    {m.saidas > 0 ? '− ' + brlNoSign(m.saidas) : '—'}
                  </td>
                  <td
                    className={`px-3 py-2 text-right font-semibold ${
                      m.saldo > 0 ? 'text-emerald-700' : m.saldo < 0 ? 'text-rose-700' : 'text-stone-700'
                    }`}
                  >
                    {brl(m.saldo)}
                  </td>
                  <td className="pr-6 py-2 text-right text-stone-500">{m.qtd_baixas}</td>
                </tr>
              ))}
              <tr className="border-t-2 border-t-stone-300 bg-stone-50/60 font-semibold">
                <td className="pl-6 pr-3 py-2 text-stone-900">Total {meta.meses_janela}M</td>
                <td className="px-3 py-2 text-right text-emerald-800">+ {brlNoSign(totais.entradas)}</td>
                <td className="px-3 py-2 text-right text-rose-800">− {brlNoSign(totais.saidas)}</td>
                <td
                  className={`px-3 py-2 text-right ${
                    totais.saldo >= 0 ? 'text-emerald-800' : 'text-rose-800'
                  }`}
                >
                  {brl(totais.saldo)}
                </td>
                <td className="pr-6 py-2 text-right text-stone-700">{totais.qtd_baixas}</td>
              </tr>
            </tbody>
          </table>
        )}
      </Card>
    </>
  );
}

function FinanceiroFluxo(props: Props) {
  const tab: TabAtiva = props.tab ?? 'projetado';

  const headerSub =
    tab === 'realizado'
      ? `Realizado · últ ${props.realizado?.meta.meses_janela ?? 12} meses`
      : 'Projeção 35 dias';

  return (
    <div className="fin-curadoria vendas-aplus">
      {/* Onda 12.8 (2026-05-19) — header canon paridade Unificado */}
      <header className="os-page-h fin-page-h">
        <div className="os-page-h-l fin-page-h-l">
          <h1>
            Fluxo de caixa <span className="fin-hero-title-sub">· {headerSub}</span>
          </h1>
          <p>
            {tab === 'realizado'
              ? 'Entradas e saídas confirmadas, agrupadas por mês'
              : 'Saldo, entradas e saídas dia-a-dia'}
          </p>
        </div>
        <div className="os-page-h-r fin-page-h-r">
          {/* ADR 0180 Fase 5 propagação — ghost tabs Financeiro + primary `+ Novo título` */}
          <FinanceiroSubNav active="fluxo" hidePrimary />
        </div>
      </header>

      <TabSwitcher tab={tab} />

      {tab === 'projetado' ? (
        <ProjetadoView
          saldo_hoje={props.saldo_hoje}
          saldo_30d={props.saldo_30d}
          pior_dia={props.pior_dia}
          margem_minima={props.margem_minima}
          conta={props.conta}
          dias={props.dias}
        />
      ) : (
        <RealizadoView realizado={props.realizado ?? null} />
      )}
    </div>
  );
}

FinanceiroFluxo.layout = (page: ReactNode) => (
  <AppShellV2
    title="Financeiro — Fluxo de caixa"
    breadcrumbItems={[{ label: 'Financeiro', href: '/financeiro' }, { label: 'Fluxo de caixa' }]}
  >
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);

export default FinanceiroFluxo;
