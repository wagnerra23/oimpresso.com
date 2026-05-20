// @memcofre
//   tela: /financeiro/relatorios
//   module: Financeiro
//   status: implementada
//   stories: US-FIN-014
//   rules: R-FIN-001
//   adrs: ui/0002, arq/0005
//   tests: Modules/Financeiro/Tests/Feature/RelatoriosTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
// Onda 14 — PageHeader removido (canon os-page-h direto)

type TabId = 'fluxo' | 'resumo';

interface Filters {
  data_de: string;
  data_ate: string;
}

interface FluxoSemana {
  semana_inicio: string;
  semana_label: string;
  projetado_receber: number;
  projetado_pagar: number;
  realizado_receber: number;
  realizado_pagar: number;
}

interface Fluxo {
  semanas: FluxoSemana[];
  totais: {
    projetado_receber: number;
    projetado_pagar: number;
    realizado_receber: number;
    realizado_pagar: number;
    saldo_projetado: number;
    saldo_realizado: number;
  };
}

interface Resumo {
  periodo: { de: string; ate: string };
  a_receber: { valor: number; qtd: number; vencidos_qtd: number; vencidos_valor: number };
  a_pagar:   { valor: number; qtd: number; vencidos_qtd: number; vencidos_valor: number };
  recebido_periodo: { valor: number; qtd: number };
  pago_periodo:     { valor: number; qtd: number };
  saldo_aberto: number;
  saldo_periodo: number;
}

interface Props {
  filters: Filters;
  fluxo: Fluxo;
  resumo: Resumo;
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

function FinanceiroRelatorios({ filters, fluxo, resumo }: Props) {
  const [tab, setTab] = useState<TabId>('fluxo');
  const [dataDe, setDataDe] = useState(filters.data_de);
  const [dataAte, setDataAte] = useState(filters.data_ate);

  const aplicarFiltro = (patch: Partial<Filters> = {}) => {
    router.get(
      '/financeiro/relatorios',
      { data_de: dataDe, data_ate: dataAte, ...patch },
      { preserveScroll: true, preserveState: true, replace: true },
    );
  };

  const csvHref = useMemo(() => {
    const qs = new URLSearchParams({
      data_de: filters.data_de,
      data_ate: filters.data_ate,
      tipo: tab,
    });
    return `/financeiro/relatorios/export-csv?${qs.toString()}`;
  }, [filters.data_de, filters.data_ate, tab]);

  return (
    <div className="fin-curadoria vendas-aplus">
      {/* Onda 14 (2026-05-19) — header canon paridade Unificado */}
      <header className="os-page-h fin-page-h">
        <div className="os-page-h-l fin-page-h-l">
          <h1>Relatórios <span className="fin-hero-title-sub">· Financeiro</span></h1>
          <p>DRE gerencial, fluxo de caixa projetado vs realizado e resumo do período</p>
        </div>
        <div className="os-page-h-r fin-page-h-r">
          <a href={csvHref} target="_blank" rel="noopener noreferrer" className="os-btn ghost">
            Exportar CSV
          </a>
        </div>
      </header>

      {/* Filtros de período */}
      <Card className="mt-6 mb-4">
        <CardContent className="pt-6 flex flex-col md:flex-row gap-3 md:items-end">
          <div className="flex-1 min-w-[160px]">
            <label className="text-xs font-medium text-muted-foreground block mb-1">De</label>
            <Input type="date" value={dataDe} onChange={(e) => setDataDe(e.target.value)} />
          </div>
          <div className="flex-1 min-w-[160px]">
            <label className="text-xs font-medium text-muted-foreground block mb-1">Até</label>
            <Input type="date" value={dataAte} onChange={(e) => setDataAte(e.target.value)} />
          </div>
          <div className="flex gap-2">
            <Button size="sm" onClick={() => aplicarFiltro()}>
              Aplicar
            </Button>
            <Button
              size="sm"
              variant="ghost"
              onClick={() => {
                const hoje = new Date();
                const ini = new Date(hoje.getFullYear(), hoje.getMonth() - 3, 1);
                const fim = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
                const iso = (d: Date) => d.toISOString().slice(0, 10);
                setDataDe(iso(ini));
                setDataAte(iso(fim));
                aplicarFiltro({ data_de: iso(ini), data_ate: iso(fim) });
              }}
            >
              Últimos 4 meses
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Tabs — Onda DRE-reaplicacao 2026-05-20: tab DRE removida, virou tela dedicada `/financeiro/dre`.
          PR D wave reaplicação canon (Q8b aprovado Wagner). Banner avisa usuário. */}
      <div
        style={{
          background: 'oklch(0.96 0.04 70)',
          border: '1px solid oklch(0.85 0.10 70)',
          borderRadius: 8,
          padding: '12px 16px',
          marginBottom: 16,
          fontSize: 13,
          color: 'oklch(0.40 0.13 70)',
        }}
      >
        ⓘ DRE gerencial agora é tela dedicada com hierarquia clássica + comparativo mês anterior +
        % RL + export PDF/Excel.{' '}
        <Link href="/financeiro/dre" className="font-semibold underline">
          Abrir DRE →
        </Link>
      </div>

      <div className="border-b border-border flex gap-1 flex-wrap mb-4">
        <TabBtn active={tab === 'fluxo'}  onClick={() => setTab('fluxo')}>Fluxo de Caixa</TabBtn>
        <TabBtn active={tab === 'resumo'} onClick={() => setTab('resumo')}>Resumo</TabBtn>
      </div>

      {tab === 'fluxo'  && <FluxoPanel fluxo={fluxo} />}
      {tab === 'resumo' && <ResumoPanel resumo={resumo} />}
    </div>
  );
}

// ──────────────────── DRE: removida 2026-05-20 (PR D wave reaplicação canon) ────────────────────
// DRE agora vive em /financeiro/dre como tela dedicada com hierarquia clássica.
// Ver: memory/requisitos/Financeiro/dre-visual-comparison.md (status approved).

// ──────────────────── Fluxo ────────────────────

function FluxoPanel({ fluxo }: { fluxo: Fluxo }) {
  const max = Math.max(
    1,
    ...fluxo.semanas.map((s) =>
      Math.max(s.projetado_receber, s.projetado_pagar, s.realizado_receber, s.realizado_pagar),
    ),
  );

  return (
    <>
      {/* Onda 15 — canon fin-stats */}
      <div className="fin-stats mb-6">
        <div className="fin-stat">
          <small>PROJETADO A RECEBER</small>
          <b className="fin-num-pos">{brl(fluxo.totais.projetado_receber)}</b>
          <span className="fin-stat-hint">Títulos em aberto no período</span>
        </div>
        <div className="fin-stat">
          <small>PROJETADO A PAGAR</small>
          <b className="fin-num-neg">{brl(fluxo.totais.projetado_pagar)}</b>
          <span className="fin-stat-hint">Títulos em aberto no período</span>
        </div>
        <div className="fin-stat">
          <small>REALIZADO (RECEBIDO)</small>
          <b className="fin-num-pos">{brl(fluxo.totais.realizado_receber)}</b>
          <span className="fin-stat-hint">Baixas de receber</span>
        </div>
        <div className="fin-stat">
          <small>REALIZADO (PAGO)</small>
          <b>{brl(fluxo.totais.realizado_pagar)}</b>
          <span className="fin-stat-hint">Baixas de pagar</span>
        </div>
      </div>

      <div className="fin-stats mb-6" style={{ gridTemplateColumns: 'repeat(2, 1fr)' }}>
        <div className="fin-stat">
          <small>SALDO PROJETADO</small>
          <b className={fluxo.totais.saldo_projetado >= 0 ? 'fin-num-pos' : 'fin-num-neg'}>{brl(fluxo.totais.saldo_projetado)}</b>
          <span className="fin-stat-hint">Receber − Pagar (em aberto)</span>
        </div>
        <div className="fin-stat">
          <small>SALDO REALIZADO</small>
          <b className={fluxo.totais.saldo_realizado >= 0 ? 'fin-num-pos' : 'fin-num-neg'}>{brl(fluxo.totais.saldo_realizado)}</b>
          <span className="fin-stat-hint">Recebido − Pago (baixas)</span>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Fluxo de caixa por semana</CardTitle>
          <CardDescription>
            Verde = entrada · Vermelho = saída · Linha clara = projetado · Linha cheia = realizado
          </CardDescription>
        </CardHeader>
        <CardContent>
          {fluxo.semanas.length === 0 ? (
            <EmptyMsg text="Sem dados no período selecionado." />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-2 px-2 font-medium">Semana</th>
                    <th className="text-right py-2 px-2 font-medium">Proj. ↓</th>
                    <th className="text-right py-2 px-2 font-medium">Real. ↓</th>
                    <th className="text-right py-2 px-2 font-medium">Proj. ↑</th>
                    <th className="text-right py-2 px-2 font-medium">Real. ↑</th>
                    <th className="py-2 px-2 font-medium w-[40%]">Visualização</th>
                  </tr>
                </thead>
                <tbody>
                  {fluxo.semanas.map((s) => (
                    <tr key={s.semana_inicio} className="border-b hover:bg-muted/40">
                      <td className="py-2 px-2 font-mono text-xs">{s.semana_label}</td>
                      <td className="text-right py-2 px-2 font-mono text-emerald-700/70 dark:text-emerald-300/70">
                        {brl(s.projetado_receber)}
                      </td>
                      <td className="text-right py-2 px-2 font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
                        {brl(s.realizado_receber)}
                      </td>
                      <td className="text-right py-2 px-2 font-mono text-rose-700/70 dark:text-rose-300/70">
                        {brl(s.projetado_pagar)}
                      </td>
                      <td className="text-right py-2 px-2 font-mono text-rose-600 dark:text-rose-400 font-semibold">
                        {brl(s.realizado_pagar)}
                      </td>
                      <td className="py-2 px-2">
                        <FluxoBars semana={s} max={max} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </>
  );
}

// ──────────────────── Resumo ────────────────────

function ResumoPanel({ resumo }: { resumo: Resumo }) {
  return (
    <>
      {/* Onda 15 — canon fin-stats */}
      <div className="fin-stats mb-6">
        <div className="fin-stat">
          <small>A RECEBER (ABERTO)</small>
          <b className="fin-num-pos">{brl(resumo.a_receber.valor)}</b>
          <span className="fin-stat-hint">
            {resumo.a_receber.vencidos_qtd > 0
              ? `${resumo.a_receber.qtd} títulos · ${resumo.a_receber.vencidos_qtd} vencidos`
              : `${resumo.a_receber.qtd} títulos`}
          </span>
        </div>
        <div className="fin-stat">
          <small>A PAGAR (ABERTO)</small>
          <b className={resumo.a_pagar.vencidos_qtd > 0 ? 'fin-num-neg' : ''}>{brl(resumo.a_pagar.valor)}</b>
          <span className="fin-stat-hint">
            {resumo.a_pagar.vencidos_qtd > 0
              ? `${resumo.a_pagar.qtd} títulos · ${resumo.a_pagar.vencidos_qtd} vencidos`
              : `${resumo.a_pagar.qtd} títulos`}
          </span>
        </div>
        <div className="fin-stat">
          <small>RECEBIDO NO PERÍODO</small>
          <b className="fin-num-pos">{brl(resumo.recebido_periodo.valor)}</b>
          <span className="fin-stat-hint">{resumo.recebido_periodo.qtd} baixas</span>
        </div>
        <div className="fin-stat">
          <small>PAGO NO PERÍODO</small>
          <b>{brl(resumo.pago_periodo.valor)}</b>
          <span className="fin-stat-hint">{resumo.pago_periodo.qtd} baixas</span>
        </div>
      </div>

      <div className="fin-stats mb-6" style={{ gridTemplateColumns: 'repeat(2, 1fr)' }}>
        <div className="fin-stat">
          <small>SALDO EM ABERTO</small>
          <b className={resumo.saldo_aberto >= 0 ? 'fin-num-pos' : 'fin-num-neg'}>{brl(resumo.saldo_aberto)}</b>
          <span className="fin-stat-hint">A receber − A pagar</span>
        </div>
        <div className="fin-stat">
          <small>SALDO DO PERÍODO</small>
          <b className={resumo.saldo_periodo >= 0 ? 'fin-num-pos' : 'fin-num-neg'}>{brl(resumo.saldo_periodo)}</b>
          <span className="fin-stat-hint">Recebido − Pago</span>
        </div>
      </div>

      {(resumo.a_receber.vencidos_qtd > 0 || resumo.a_pagar.vencidos_qtd > 0) && (
        <Card className="border-amber-300 dark:border-amber-700">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              Atenção: títulos vencidos
              <Badge variant="destructive">
                {resumo.a_receber.vencidos_qtd + resumo.a_pagar.vencidos_qtd}
              </Badge>
            </CardTitle>
            <CardDescription>Posição de hoje (independente do filtro de período)</CardDescription>
          </CardHeader>
          <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-1">
              <div className="text-xs text-muted-foreground">Recebíveis vencidos</div>
              <div className="text-2xl font-mono">{brl(resumo.a_receber.vencidos_valor)}</div>
              <Link
                href="/financeiro?tipo=receber&status=aberto"
                className="text-xs text-primary hover:underline"
                preserveScroll
                preserveState
              >
                {resumo.a_receber.vencidos_qtd} título(s) · ver no dashboard →
              </Link>
            </div>
            <div className="space-y-1">
              <div className="text-xs text-muted-foreground">Pagáveis vencidos</div>
              <div className="text-2xl font-mono">{brl(resumo.a_pagar.vencidos_valor)}</div>
              <Link
                href="/financeiro?tipo=pagar&status=aberto"
                className="text-xs text-primary hover:underline"
                preserveScroll
                preserveState
              >
                {resumo.a_pagar.vencidos_qtd} título(s) · ver no dashboard →
              </Link>
            </div>
          </CardContent>
        </Card>
      )}
    </>
  );
}

// ──────────────────── helpers UI ────────────────────

function TabBtn({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`px-3 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${
        active
          ? 'border-primary text-primary'
          : 'border-transparent text-muted-foreground hover:text-foreground'
      }`}
    >
      {children}
    </button>
  );
}

function FluxoBars({ semana, max }: { semana: FluxoSemana; max: number }) {
  const cell = (v: number, color: string) => (
    <div className="h-2 bg-muted rounded-full overflow-hidden">
      <div className={`h-full ${color} rounded-full`} style={{ width: `${Math.min(100, (v / max) * 100)}%` }} />
    </div>
  );

  return (
    <div className="space-y-1">
      {cell(semana.projetado_receber, 'bg-emerald-300 dark:bg-emerald-700')}
      {cell(semana.realizado_receber, 'bg-emerald-500 dark:bg-emerald-400')}
      {cell(semana.projetado_pagar,   'bg-rose-300 dark:bg-rose-700')}
      {cell(semana.realizado_pagar,   'bg-rose-500 dark:bg-rose-400')}
    </div>
  );
}

function EmptyMsg({ text }: { text: string }) {
  return (
    <div className="text-center py-12 text-muted-foreground text-sm">
      {text}
    </div>
  );
}

FinanceiroRelatorios.layout = (page: ReactNode) => (
  <AppShellV2 title="Financeiro — Relatórios" breadcrumbItems={[{ label: 'Financeiro', href: '/financeiro' }, { label: 'Relatórios' }]}>
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);

export default FinanceiroRelatorios;
