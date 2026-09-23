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
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';

// FIN-2 (2026-09-23): forma do TelaFluxo / FinFluxoRealizado do protótipo
// (financeiro-telas-extras.jsx:49-220 · financeiro-relatorios.jsx). Medido na FIN-0a e de
// novo antes desta onda: os cartões já seguiam o tema (Card = bg-card); o que divergia era a
// faixa de KPIs (4 caixas `fin-stat`, valores 28/22/22/22px → 1 cartão dividido, 28px nos 4),
// a paleta fixa das legendas/tabelas e o cabeçalho. Só forma: nenhuma conta muda.
// Apelidos do protótipo → nome vigente: --text-2=--text-dim · --text-3=--text-mute ·
// --hairline=--border-2 (prototipo styles.css:6269-6274). `.fin-ink` sem token na produção:
// valores literais do protótipo (financeiro.css:777-778), com a variante do tema escuro.
const FIN_INK = 'bg-[oklch(0.24_0.012_80)] dark:bg-[oklch(0.32_0.014_80)] text-white';

interface KpiItem {
  rotulo: string;
  valor: ReactNode;
  tom?: string;
  dica: ReactNode;
  escuro?: boolean;
}

function KpiFaixa({ itens }: { itens: KpiItem[] }) {
  return (
    <div className="px-6 pt-4">
      <div className="bg-[var(--surface)] border border-[var(--border)] rounded-[11px] shadow-[var(--sh-1)] overflow-hidden flex flex-col sm:flex-row divide-y sm:divide-y-0 sm:divide-x divide-[var(--border)]">
        {itens.map((k) => (
          <div key={k.rotulo} className={`flex-1 px-5 py-4 ${k.escuro ? FIN_INK : ''}`}>
            <div
              className={`text-[length:var(--fs-1)] uppercase tracking-widest font-medium ${
                k.escuro ? 'text-[var(--text-mute)]' : 'text-[var(--text-dim)]'
              }`}
            >
              {k.rotulo}
            </div>
            <div
              className={`mt-1 text-[length:var(--fs-8)] leading-none font-semibold tracking-tight tabular-nums ${
                k.tom ?? (k.escuro ? '' : 'text-[var(--text)]')
              }`}
            >
              {k.valor}
            </div>
            <div
              className={`mt-2 text-[length:var(--fs-2)] ${
                k.escuro ? 'text-[var(--text-mute)]' : 'text-[var(--text-dim)]'
              }`}
            >
              {k.dica}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

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
    // D-14: partial reload — troca de tab só re-busca tab/realizado
    // (projeção 35d saldo_hoje/dias/etc são closures no controller, pulam no partial).
    router.visit(`/financeiro/fluxo?tab=${alvo}`, {
      preserveScroll: true,
      replace: true,
      only: ['tab', 'realizado'],
    });
  };

  const items: { id: TabAtiva; label: string; hint: string }[] = [
    { id: 'projetado', label: 'Projetado', hint: 'próx 35 dias' },
    { id: 'realizado', label: 'Realizado', hint: 'últ 12 meses' },
  ];

  return (
    <div className="px-6 pt-3 pb-1">
      <div className="inline-flex bg-[var(--bg-2)] rounded-md p-0.5 border border-[var(--border)]">
        {items.map((it) => (
          <button
            key={it.id}
            type="button"
            onClick={() => trocaTab(it.id)}
            className={
              'h-8 px-4 rounded text-[length:var(--fs-3)] flex items-center gap-2 transition tabular-nums ' +
              (tab === it.id
                ? 'bg-[var(--surface)] shadow-sm font-medium text-[var(--text)]'
                : 'text-[var(--text-dim)] hover:text-[var(--text)]')
            }
            aria-pressed={tab === it.id}
          >
            <span>{it.label}</span>
            <span
              className={
                'text-[length:var(--fs-1)] uppercase tracking-wider ' +
                (tab === it.id ? 'text-[var(--text-dim)]' : 'text-[var(--text-mute)]')
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

      {/* FIN-2 — faixa única de KPIs (TelaFluxo :95-117). Expressões de valor idênticas às de
          antes; "Pior dia" ganha o tom de alerta que o charter já pedia ("tone amber"). */}
      <KpiFaixa
        itens={[
          { rotulo: 'Saldo hoje', escuro: true, valor: brl(saldo_hoje), dica: conta },
          {
            rotulo: 'Projeção 30 dias',
            valor: brl(saldo_30d),
            tom: saldo_30d >= saldo_hoje ? 'text-[var(--pos)]' : 'text-[var(--neg)]',
            dica: (
              <>
                {saldo_30d >= saldo_hoje ? 'alta' : 'queda'} de {brl(Math.abs(saldo_30d - saldo_hoje))} vs hoje
              </>
            ),
          },
          {
            rotulo: 'Pior dia previsto',
            valor: brl(pior_dia.saldo),
            tom: 'text-[var(--warn)]',
            dica: pior_dia.data_label,
          },
          { rotulo: 'Margem mínima', valor: brl(margem_minima), dica: 'limite definido' },
        ]}
      />

      <Card className="mx-6 mt-4 p-5">
        <div className="flex items-center justify-between mb-3">
          <div>
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)] font-medium">Saldo projetado · próximos 35 dias</div>
            {/* Texto da produção mantido: as barras desenham o SALDO acumulado. O protótipo diz
                "movimento líquido do dia" e calcula `moveBar` sem desenhar — pedido de correção
                ao Design em CODE_NOTES.prompt-cowork-financeiro-2026-09-23.md (item 2). */}
            <div className="text-[length:var(--fs-4)] font-semibold mt-0.5">linha laranja = limite mínimo · barras = saldo acumulado</div>
          </div>
          <div className="flex items-center gap-3 text-[length:var(--fs-2)] text-[var(--text-dim)]">
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-[var(--accent)]" /> saldo
            </span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-[var(--pos)]" /> entrada
            </span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-[var(--neg)]" /> saída
            </span>
          </div>
        </div>

        <div className="relative h-[220px] border-b border-[var(--border)] mt-2">
          <div className="absolute left-0 right-0 border-t border-dashed border-[var(--warn)]" style={{ top: `${limitTopPct}%` }}>
            <span className="absolute -top-4 right-0 text-[length:var(--fs-1)] text-[var(--warn)] font-medium bg-[var(--surface)] px-1">
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
                      d.is_past ? 'bg-[var(--border)]' : d.is_today ? 'bg-[var(--accent)]' : 'bg-[var(--text-mute)]'
                    } ${baixo ? '!bg-[var(--warn)]' : ''}`}
                    style={{ height: `${h}%` }}
                  />
                  {d.eventos.length > 0 && (
                    <div className={`hidden group-hover:block absolute -top-14 left-1/2 -translate-x-1/2 z-10 ${FIN_INK} text-[length:var(--fs-1)] rounded px-2 py-1 whitespace-nowrap tabular-nums`}>
                      {d.data_label} · {brl(d.saldo_acumulado)}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>

        <div className="flex gap-px mt-1.5 text-[length:var(--fs-1)] text-[var(--text-dim)] tabular-nums">
          {dias.map((d, i) => (
            <div key={d.data} className={`flex-1 text-center ${d.is_today ? 'font-bold text-[var(--text)]' : ''}`}>
              {i % 5 === 0 || d.is_today ? d.data_label : ''}
            </div>
          ))}
        </div>
      </Card>

      <Card className="mx-6 mt-4 mb-4 overflow-hidden">
        <div className="px-5 py-3 border-b border-[var(--border)] flex items-center gap-3">
          <div className="min-w-0">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)] font-medium whitespace-nowrap">
              Próximos eventos
            </div>
            <div className="text-[length:var(--fs-4)] font-semibold mt-0.5 whitespace-nowrap">7 dias adiante</div>
          </div>
          <div className="ml-auto text-[length:var(--fs-2)] text-[var(--text-dim)] whitespace-nowrap shrink-0">
            {proxEventos.reduce((s, d) => s + d.eventos.length, 0)} lançamentos
          </div>
        </div>
        {proxEventos.length === 0 ? (
          <div className="px-6 py-8 text-center text-[length:var(--fs-3)] text-[var(--text-dim)]">
            Nenhum evento programado nos próximos 7 dias.
          </div>
        ) : (
          <table className="w-full text-[length:var(--fs-3)] tabular-nums">
            <tbody>
              {proxEventos.flatMap((d) =>
                d.eventos.map((ev, j) => (
                  <tr
                    key={ev.id}
                    className={`border-b border-[var(--border-2)] hover:bg-[var(--bg-2)] ${
                      j === 0 ? 'border-t-2 border-t-[var(--border-2)]' : ''
                    }`}
                  >
                    <td className="pl-6 pr-3 py-2 w-[110px] text-[var(--text)]">{j === 0 ? d.data_label : ''}</td>
                    <td className="px-2 py-2">
                      <span
                        className={`inline-grid place-items-center rounded ${
                          ev.kind === 'receivable' ? 'bg-success-soft text-success-fg' : 'bg-destructive-soft text-destructive-fg'
                        }`}
                        style={{ width: 22, height: 22 }}
                      >
                        {ev.kind === 'receivable' ? '↓' : '↑'}
                      </span>
                    </td>
                    <td className="px-2 py-2 font-medium text-[var(--text)] truncate">{ev.descricao}</td>
                    <td className="px-2 py-2 text-[var(--text-dim)]">{ev.contraparte}</td>
                    <td className="px-2 py-2 text-[var(--text-dim)]">{ev.categoria}</td>
                    <td className="pr-6 py-2 text-right font-medium">
                      <span className={ev.kind === 'receivable' ? 'text-[var(--pos)]' : 'text-[var(--text)]'}>
                        {ev.kind === 'receivable' ? '+' : '−'} {brlNoSign(ev.valor)}
                      </span>
                    </td>
                    <td className="pr-6 py-2 text-right text-[var(--text)] font-medium w-[120px]">
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
      <Card className="mx-6 mt-4 mb-4 p-8 text-center text-[length:var(--fs-3)] text-[var(--text-dim)]">
        Carregando movimentações realizadas…
      </Card>
    );
  }

  const { totais, meta } = realizado;

  return (
    <>
      {/* FIN-2 — faixa única de KPIs (FinFluxoRealizado do protótipo). Valores idênticos aos de antes. */}
      <KpiFaixa
        itens={[
          {
            rotulo: `Saldo ${meta.meses_janela}M`,
            escuro: true,
            valor: brl(totais.saldo),
            tom: totais.saldo >= 0 ? 'text-[var(--pos)]' : 'text-[var(--neg)]',
            dica: (
              <>
                {meses[0]?.mes_label} → {meses[meses.length - 1]?.mes_label}
              </>
            ),
          },
          { rotulo: 'Entradas', valor: brl(totais.entradas), tom: 'text-[var(--pos)]', dica: 'recebimentos confirmados' },
          { rotulo: 'Saídas', valor: brl(totais.saidas), tom: 'text-[var(--neg)]', dica: 'pagamentos confirmados' },
          { rotulo: 'Baixas registradas', valor: totais.qtd_baixas, dica: 'no período' },
        ]}
      />

      {/* Gráfico de barras gemelhas por mês (entradas vs saídas lado-a-lado) */}
      <Card className="mx-6 mt-4 p-5">
        <div className="flex items-center justify-between mb-3">
          <div>
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)] font-medium">
              Movimentações realizadas · últimos {meta.meses_janela} meses
            </div>
            <div className="text-[length:var(--fs-4)] font-semibold mt-0.5">
              barra verde = entradas · barra rosa = saídas (escala R$)
            </div>
          </div>
          <div className="flex items-center gap-3 text-[length:var(--fs-2)] text-[var(--text-dim)]">
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-[var(--pos)]" /> entradas
            </span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-[var(--neg)]" /> saídas
            </span>
          </div>
        </div>

        <div className="relative h-[200px] border-b border-[var(--border)] mt-2 flex items-end gap-1">
          {meses.map((m) => {
            const hEnt = (m.entradas / maxAbs) * 100;
            const hSai = (m.saidas / maxAbs) * 100;
            return (
              <div key={m.mes} className="flex-1 h-full flex items-end gap-0.5 relative group">
                <div
                  className={`flex-1 bg-[var(--pos)] ${m.is_current ? '' : 'opacity-80'}`}
                  style={{ height: `${hEnt}%` }}
                  title={`${m.mes_label}: entradas ${brl(m.entradas)}`}
                />
                <div
                  className={`flex-1 bg-[var(--neg)] ${m.is_current ? '' : 'opacity-80'}`}
                  style={{ height: `${hSai}%` }}
                  title={`${m.mes_label}: saídas ${brl(m.saidas)}`}
                />
                <div className={`hidden group-hover:block absolute -top-16 left-1/2 -translate-x-1/2 z-10 ${FIN_INK} text-[length:var(--fs-1)] rounded px-2 py-1 whitespace-nowrap tabular-nums`}>
                  {m.mes_label} · saldo {brl(m.saldo)}
                </div>
              </div>
            );
          })}
        </div>

        <div className="flex gap-1 mt-1.5 text-[length:var(--fs-1)] text-[var(--text-dim)] tabular-nums">
          {meses.map((m) => (
            <div
              key={m.mes}
              className={`flex-1 text-center ${m.is_current ? 'font-bold text-[var(--text)]' : ''}`}
            >
              {m.mes_label}
            </div>
          ))}
        </div>
      </Card>

      {/* Tabela detalhada mês × entradas × saídas × saldo */}
      <Card className="mx-6 mt-4 mb-4 overflow-hidden">
        <div className="px-5 py-3 border-b border-[var(--border)] flex items-center gap-3">
          <div className="min-w-0">
            <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)] font-medium whitespace-nowrap">
              Detalhe por mês
            </div>
            <div className="text-[length:var(--fs-4)] font-semibold mt-0.5 whitespace-nowrap">
              {meses.length} {meses.length === 1 ? 'mês' : 'meses'}
            </div>
          </div>
          <div className="ml-auto text-[length:var(--fs-2)] text-[var(--text-dim)] whitespace-nowrap shrink-0">
            {totais.qtd_baixas} {totais.qtd_baixas === 1 ? 'baixa' : 'baixas'} registradas
          </div>
        </div>

        {totais.qtd_baixas === 0 ? (
          <div className="px-6 py-8 text-center text-[length:var(--fs-3)] text-[var(--text-dim)]">
            Nenhuma baixa registrada nos últimos {meta.meses_janela} meses.
          </div>
        ) : (
          <table className="w-full text-[length:var(--fs-3)] tabular-nums">
            <thead>
              <tr className="border-b border-[var(--border)] bg-[var(--bg-2)] text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)]">
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
                  className={`border-b border-[var(--border-2)] hover:bg-[var(--bg-2)] ${
                    m.is_current ? 'bg-[var(--bg-2)]' : ''
                  }`}
                >
                  <td className={`pl-6 pr-3 py-2 ${m.is_current ? 'font-semibold text-[var(--text)]' : 'text-[var(--text)]'}`}>
                    {m.mes_label}
                    {m.is_current && (
                      <span className="ml-2 text-[length:var(--fs-1)] uppercase tracking-wider text-[var(--text-dim)]">atual</span>
                    )}
                  </td>
                  <td className="px-3 py-2 text-right text-[var(--pos)] font-medium">
                    {m.entradas > 0 ? '+ ' + brlNoSign(m.entradas) : '—'}
                  </td>
                  <td className="px-3 py-2 text-right text-[var(--neg)] font-medium">
                    {m.saidas > 0 ? '− ' + brlNoSign(m.saidas) : '—'}
                  </td>
                  <td
                    className={`px-3 py-2 text-right font-semibold ${
                      m.saldo > 0 ? 'text-[var(--pos)]' : m.saldo < 0 ? 'text-[var(--neg)]' : 'text-[var(--text)]'
                    }`}
                  >
                    {brl(m.saldo)}
                  </td>
                  <td className="pr-6 py-2 text-right text-[var(--text-dim)]">{m.qtd_baixas}</td>
                </tr>
              ))}
              <tr className="border-t-2 border-t-[var(--border)] bg-[var(--bg-2)] font-semibold">
                <td className="pl-6 pr-3 py-2 text-[var(--text)]">Total {meta.meses_janela}M</td>
                <td className="px-3 py-2 text-right text-[var(--pos)]">+ {brlNoSign(totais.entradas)}</td>
                <td className="px-3 py-2 text-right text-[var(--neg)]">− {brlNoSign(totais.saidas)}</td>
                <td
                  className={`px-3 py-2 text-right ${
                    totais.saldo >= 0 ? 'text-[var(--pos)]' : 'text-[var(--neg)]'
                  }`}
                >
                  {brl(totais.saldo)}
                </td>
                <td className="pr-6 py-2 text-right text-[var(--text)]">{totais.qtd_baixas}</td>
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
      {/* Wave 4 (2026-05-25): migrado pra <PageHeader> canon v3.8 */}
      {/* FIN-2: título "Financeiro · Fluxo de caixa" = protótipo (medido na FIN-0a) e igual ao
          padrão do DRE. A indicação da aba, que o charter exige no header ("Projeção 35 dias" vs
          "Realizado · últ 12 meses"), passa para o subtítulo. Primário "Novo título" = protótipo e
          DRE/Dashboard: só navega para /financeiro/unificado/novo — a tela segue read-only
          (Non-Goal do charter: "mutações via /financeiro/unificado"). */}
      <PageHeader
        title="Financeiro"
        suffix=" · Fluxo de caixa"
        subtitle={
          <>
            {headerSub} ·{' '}
            {tab === 'realizado'
              ? 'Entradas e saídas confirmadas, agrupadas por mês'
              : 'Saldo, entradas e saídas dia-a-dia'}
          </>
        }
      >
        <div className="flex-shrink-0 flex items-center gap-1.5 ml-auto">
          <FinanceiroSubNav active="fluxo" hidePrimary />
          <PageHeaderPrimary
            label="Novo título"
            onClick={() => router.visit('/financeiro/unificado/novo')}
          />
        </div>
      </PageHeader>

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
