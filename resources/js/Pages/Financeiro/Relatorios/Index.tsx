// @memcofre
//   tela: /financeiro/relatorios
//   module: Financeiro
//   status: implementada
//   stories: US-FIN-014
//   rules: R-FIN-001
//   adrs: ui/0002, arq/0005
//   tests: Modules/Financeiro/Tests/Feature/RelatoriosTest

import AppShell from '@/Layouts/AppShell';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';

type TabId = 'dre' | 'fluxo' | 'resumo';

interface Filters {
  data_de: string;
  data_ate: string;
}

interface DreMes {
  mes: string;
  receita: number;
  despesa: number;
  resultado: number;
}

interface DespesaCat {
  categoria: string;
  cor: string;
  total: number;
}

interface Dre {
  meses: DreMes[];
  despesas_por_cat: DespesaCat[];
  totais: { receita: number; despesa: number; resultado: number };
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
  dre: Dre;
  fluxo: Fluxo;
  resumo: Resumo;
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

const fmtMes = (yyyymm: string) => {
  const parts = yyyymm.split('-');
  const y = parts[0] ?? '';
  const m = parts[1] ?? '01';
  const meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
  const i = Math.max(0, Math.min(11, parseInt(m, 10) - 1));
  return `${meses[i]}/${y.slice(2)}`;
};

function FinanceiroRelatorios({ filters, dre, fluxo, resumo }: Props) {
  const [tab, setTab] = useState<TabId>('dre');
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
    <>
      <Head title="Financeiro — Relatórios" />

      <PageHeader
        icon="bar-chart-3"
        title="Relatórios"
        description="DRE gerencial, fluxo de caixa projetado vs realizado e resumo do período"
        action={
          <a href={csvHref} target="_blank" rel="noopener noreferrer">
            <Button size="sm" variant="outline">
              Exportar CSV
            </Button>
          </a>
        }
      />

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

      {/* Tabs */}
      <div className="border-b border-border flex gap-1 flex-wrap mb-4">
        <TabBtn active={tab === 'dre'}    onClick={() => setTab('dre')}>DRE Gerencial</TabBtn>
        <TabBtn active={tab === 'fluxo'}  onClick={() => setTab('fluxo')}>Fluxo de Caixa</TabBtn>
        <TabBtn active={tab === 'resumo'} onClick={() => setTab('resumo')}>Resumo</TabBtn>
      </div>

      {tab === 'dre'    && <DrePanel dre={dre} />}
      {tab === 'fluxo'  && <FluxoPanel fluxo={fluxo} />}
      {tab === 'resumo' && <ResumoPanel resumo={resumo} />}
    </>
  );
}

// ──────────────────── DRE ────────────────────

function DrePanel({ dre }: { dre: Dre }) {
  const max = Math.max(
    1,
    ...dre.meses.map((m) => Math.max(m.receita, m.despesa, Math.abs(m.resultado))),
  );

  return (
    <>
      <KpiGrid cols={3} className="mb-6">
        <KpiCard icon="arrow-down-circle" tone="success" label="Receita (período)"
                 value={brl(dre.totais.receita)} description="Soma de títulos a receber" />
        <KpiCard icon="arrow-up-circle"   tone="warning" label="Despesa (período)"
                 value={brl(dre.totais.despesa)} description="Soma de títulos a pagar" />
        <KpiCard icon="trending-up"
                 tone={dre.totais.resultado >= 0 ? 'success' : 'danger'}
                 label="Resultado"
                 value={brl(dre.totais.resultado)}
                 description={dre.totais.resultado >= 0 ? 'Superávit' : 'Déficit'} />
      </KpiGrid>

      <Card className="mb-6">
        <CardHeader>
          <CardTitle>DRE comparativa — {dre.meses.length} {dre.meses.length === 1 ? 'mês' : 'meses'}</CardTitle>
          <CardDescription>Regime de competência (competencia_mes do título)</CardDescription>
        </CardHeader>
        <CardContent>
          {dre.meses.length === 0 ? (
            <EmptyMsg text="Sem dados no período selecionado." />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b">
                    <th className="text-left py-2 px-2 font-medium">Mês</th>
                    <th className="text-right py-2 px-2 font-medium">Receita</th>
                    <th className="text-right py-2 px-2 font-medium">Despesa</th>
                    <th className="text-right py-2 px-2 font-medium">Resultado</th>
                    <th className="py-2 px-2 font-medium w-[40%]">Comparativo</th>
                  </tr>
                </thead>
                <tbody>
                  {dre.meses.map((m) => (
                    <tr key={m.mes} className="border-b hover:bg-muted/40">
                      <td className="py-2 px-2 font-medium">{fmtMes(m.mes)}</td>
                      <td className="text-right py-2 px-2 font-mono text-emerald-600 dark:text-emerald-400">
                        {brl(m.receita)}
                      </td>
                      <td className="text-right py-2 px-2 font-mono text-amber-600 dark:text-amber-400">
                        {brl(m.despesa)}
                      </td>
                      <td className={`text-right py-2 px-2 font-mono font-semibold ${
                        m.resultado >= 0
                          ? 'text-emerald-600 dark:text-emerald-400'
                          : 'text-rose-600 dark:text-rose-400'
                      }`}>
                        {brl(m.resultado)}
                      </td>
                      <td className="py-2 px-2">
                        <DualBar receita={m.receita} despesa={m.despesa} max={max} />
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr className="border-t-2 font-semibold">
                    <td className="py-2 px-2">Total</td>
                    <td className="text-right py-2 px-2 font-mono">{brl(dre.totais.receita)}</td>
                    <td className="text-right py-2 px-2 font-mono">{brl(dre.totais.despesa)}</td>
                    <td className={`text-right py-2 px-2 font-mono ${
                      dre.totais.resultado >= 0
                        ? 'text-emerald-600 dark:text-emerald-400'
                        : 'text-rose-600 dark:text-rose-400'
                    }`}>
                      {brl(dre.totais.resultado)}
                    </td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Despesas por categoria</CardTitle>
          <CardDescription>Top 10 do período</CardDescription>
        </CardHeader>
        <CardContent>
          {dre.despesas_por_cat.length === 0 ? (
            <EmptyMsg text="Sem despesas no período." />
          ) : (
            <div className="space-y-2">
              {dre.despesas_por_cat.map((c, i) => {
                const pct = dre.totais.despesa > 0 ? (c.total / dre.totais.despesa) * 100 : 0;
                return (
                  <div key={i} className="flex items-center gap-3">
                    <div className="w-3 h-3 rounded-sm shrink-0" style={{ background: c.cor }} />
                    <div className="flex-1 min-w-0">
                      <div className="flex justify-between text-sm">
                        <span className="truncate">{c.categoria}</span>
                        <span className="font-mono ml-2 shrink-0">
                          {brl(c.total)} <span className="text-muted-foreground">({pct.toFixed(1)}%)</span>
                        </span>
                      </div>
                      <div className="w-full h-2 bg-muted rounded-full overflow-hidden mt-1">
                        <div
                          className="h-full rounded-full transition-all"
                          style={{ width: `${Math.min(100, pct)}%`, background: c.cor }}
                        />
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </CardContent>
      </Card>
    </>
  );
}

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
      <KpiGrid cols={4} className="mb-6">
        <KpiCard icon="calendar-clock" tone="info"    label="Projetado a receber"
                 value={brl(fluxo.totais.projetado_receber)} description="Títulos em aberto no período" />
        <KpiCard icon="calendar-clock" tone="warning" label="Projetado a pagar"
                 value={brl(fluxo.totais.projetado_pagar)} description="Títulos em aberto no período" />
        <KpiCard icon="check-circle-2" tone="success" label="Realizado (recebido)"
                 value={brl(fluxo.totais.realizado_receber)} description="Baixas de receber" />
        <KpiCard icon="check-circle-2" tone="default" label="Realizado (pago)"
                 value={brl(fluxo.totais.realizado_pagar)} description="Baixas de pagar" />
      </KpiGrid>

      <KpiGrid cols={2} className="mb-6">
        <KpiCard
          icon="trending-up"
          tone={fluxo.totais.saldo_projetado >= 0 ? 'success' : 'danger'}
          label="Saldo projetado"
          value={brl(fluxo.totais.saldo_projetado)}
          description="Receber − Pagar (em aberto)"
        />
        <KpiCard
          icon="trending-up"
          tone={fluxo.totais.saldo_realizado >= 0 ? 'success' : 'danger'}
          label="Saldo realizado"
          value={brl(fluxo.totais.saldo_realizado)}
          description="Recebido − Pago (baixas)"
        />
      </KpiGrid>

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
      <KpiGrid cols={4} className="mb-6">
        <KpiCard
          icon="arrow-down-circle"
          tone="success"
          label="A receber (aberto)"
          value={brl(resumo.a_receber.valor)}
          description={
            resumo.a_receber.vencidos_qtd > 0
              ? `${resumo.a_receber.qtd} títulos · ${resumo.a_receber.vencidos_qtd} vencidos`
              : `${resumo.a_receber.qtd} títulos`
          }
        />
        <KpiCard
          icon="arrow-up-circle"
          tone={resumo.a_pagar.vencidos_qtd > 0 ? 'warning' : 'default'}
          label="A pagar (aberto)"
          value={brl(resumo.a_pagar.valor)}
          description={
            resumo.a_pagar.vencidos_qtd > 0
              ? `${resumo.a_pagar.qtd} títulos · ${resumo.a_pagar.vencidos_qtd} vencidos`
              : `${resumo.a_pagar.qtd} títulos`
          }
        />
        <KpiCard
          icon="check-circle-2"
          tone="success"
          label="Recebido no período"
          value={brl(resumo.recebido_periodo.valor)}
          description={`${resumo.recebido_periodo.qtd} baixas`}
        />
        <KpiCard
          icon="check-circle-2"
          tone="info"
          label="Pago no período"
          value={brl(resumo.pago_periodo.valor)}
          description={`${resumo.pago_periodo.qtd} baixas`}
        />
      </KpiGrid>

      <KpiGrid cols={2} className="mb-6">
        <KpiCard
          icon="trending-up"
          tone={resumo.saldo_aberto >= 0 ? 'success' : 'danger'}
          label="Saldo em aberto"
          value={brl(resumo.saldo_aberto)}
          description="A receber − A pagar"
        />
        <KpiCard
          icon="trending-up"
          tone={resumo.saldo_periodo >= 0 ? 'success' : 'danger'}
          label="Saldo do período"
          value={brl(resumo.saldo_periodo)}
          description="Recebido − Pago"
        />
      </KpiGrid>

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

function DualBar({
  receita,
  despesa,
  max,
}: {
  receita: number;
  despesa: number;
  max: number;
}) {
  const wR = (receita / max) * 100;
  const wD = (despesa / max) * 100;
  return (
    <div className="space-y-1">
      <div className="h-2 bg-muted rounded-full overflow-hidden">
        <div
          className="h-full bg-emerald-500 dark:bg-emerald-400 rounded-full"
          style={{ width: `${Math.min(100, wR)}%` }}
        />
      </div>
      <div className="h-2 bg-muted rounded-full overflow-hidden">
        <div
          className="h-full bg-amber-500 dark:bg-amber-400 rounded-full"
          style={{ width: `${Math.min(100, wD)}%` }}
        />
      </div>
    </div>
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
  <AppShell breadcrumb={[{ label: 'Financeiro', href: '/financeiro' }, { label: 'Relatórios' }]}>
    {page}
  </AppShell>
);

export default FinanceiroRelatorios;
