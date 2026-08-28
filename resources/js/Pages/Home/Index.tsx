// @memcofre tela=/dashboard-legacy module=Dashboard
// Visão geral — Rewrite Cockpit V2 (US-DASH-004).
// Âncora de design: prototipo-ui/cowork/dash-legacy-page.jsx (rota `dash-legacy`, atalho
// "Visão geral" em data.jsx:19). Substitui o F6 Soft wrapper de 2026-05-22, que o [W]
// declarou tentativa descartada em 2026-08-27.
//
// Layout por PRIMITIVOS (ADR 0253): Stack/Inline/Grid, nunca `flex`/`grid` solto.
//
// Gráficos (US-DASH-002) e abas de grade + drawer (US-DASH-005) JÁ ESTÃO nesta tela — o
// comentário anterior dizia o contrário e apodreceu no dia em que as ondas entraram.
//
// Fora desta onda, por motivo declarado:
//   · "Papel simulado" / "Simular falha" — instrumentos do protótipo. Em produção quem
//     decide é a permissão real e a resposta real do endpoint.
//   · widgets pluggable de outros módulos (US-DASH-003) — o ponto de extensão é o método
//     `dashboard_widget()` em `Modules\<X>\Http\Controllers\DataController`, e ele tem
//     ZERO produtores nos 32 DataControllers (medido 2026-08-28). Era o único motivo
//     restante pra abrir o Blade legado, e não tinha conteúdo — por isso o Blade saiu.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Icon } from '@/Components/Icon';
import { Grid, Inline, Stack } from '@/Components/layout';
import Chart from '@/Components/shared/Chart';
import EmptyState from '@/Components/shared/EmptyState';
import KpiCard from '@/Components/shared/KpiCard';
import KpiGrid from '@/Components/shared/KpiGrid';
import { PageHeader } from '@/Components/PageHeader';
import { PeriodBar, type Period } from '@/Components/shared/PeriodBar';
import type { PaginatorShape } from '@/Components/shared/DataTable';
import GradesPainel, { type Aba, type LinhaDaGrade } from './_components/GradesPainel';
import { Deferred, router } from '@inertiajs/react';
import { ReactNode } from 'react';

interface Totals {
  total_sell: number;
  net: number;
  invoice_due: number;
  total_expense: number;
  total_purchase: number;
  purchase_due: number;
  total_sell_return: number;
  total_purchase_return: number;
}

interface Props {
  user_name: string;
  is_admin: boolean;
  can_dashboard_data: boolean;
  all_locations: Record<number, string>;
  totals: Totals | null;
  /** variação % vs o período anterior de mesma duração; null = sem base de comparação */
  deltas: Partial<Record<'net' | 'total_sell' | 'invoice_due' | 'total_expense', number | null>> | null;
  period: Period;
  /** deferido — 2 agregações sobre o FY; não segura o first paint */
  charts?: { dia: Array<{ label: string; value: number }>; mes: Array<{ label: string; value: number }> };
  /** Abas de grade que ESTE usuário pode ver — já filtradas por permissão e setting. */
  abas: Aba[];
  /** Aba aberta, resolvida no servidor contra as permitidas. `null` = nenhuma permitida. */
  aba: string | null;
  /** Linhas da aba aberta. Prop DEFERIDA: chega no segundo round-trip. */
  grade: PaginatorShape<LinhaDaGrade> | null;
  endpoints: {
    totals: string;
    stock_alert: string;
    purchase_dues: string;
    sales_dues: string;
  };
}

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const brlCurto = (v: number) => {
  const abs = Math.abs(v);
  if (abs >= 1000) {
    return `${v < 0 ? '−' : ''}R$ ${(abs / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}k`;
  }
  return brl(v);
};

/** % com sinal explícito, pro caso em que subir é ruim e a cor do DS mentiria. */
const sinal = (v: number | null | undefined) => (v === null || v === undefined ? '' : `${v >= 0 ? '+' : ''}${v}% vs anterior · `);

const CARTAO = 'rounded-lg border border-border bg-card p-5 shadow-sm';
const ROTULO = 'text-[10px] font-semibold uppercase tracking-wider text-muted-foreground';

/** KPI em destaque — o número que responde "como foi o período". */
function KpiHero({
  label,
  value,
  delta,
  description,
}: {
  label: string;
  value: number;
  delta?: number | null;
  description?: string;
}) {
  return (
    <Stack gap={1} className={`${CARTAO} ring-1 ring-primary/15`}>
      <span className={ROTULO}>{label}</span>
      <Inline gap={2} align="baseline" wrap>
        <span className="font-mono text-3xl font-semibold tracking-tight text-foreground">{brl(value)}</span>
        {delta != null && (
          <span className={`font-mono text-[12px] font-semibold ${delta >= 0 ? 'text-success' : 'text-destructive'}`}>
            {delta >= 0 ? '+' : ''}
            {delta}% vs anterior
          </span>
        )}
      </Inline>
      {description && <span className="text-[12px] text-muted-foreground">{description}</span>}
    </Stack>
  );
}

/** Painel de gráfico — título à esquerda, janela à direita, como no protótipo. */
function PainelGrafico({ titulo, meta, children }: { titulo: string; meta: string; children: React.ReactNode }) {
  return (
    <Stack gap={3} asChild>
      <section aria-label={titulo} className={CARTAO}>
        <Inline gap={3} align="baseline" justify="between">
          <h2 className="text-[13.5px] font-semibold text-foreground">{titulo}</h2>
          <span className="font-mono text-[10.5px] text-muted-foreground">{meta}</span>
        </Inline>
        {children}
      </section>
    </Stack>
  );
}

/** Contrapartidas — os 4 números do outro lado do caixa, sem gastar 4 cards. */
function HomeIndex({
  user_name,
  is_admin,
  can_dashboard_data,
  all_locations,
  totals,
  deltas,
  period,
  charts,
  abas,
  aba,
  grade,
}: Props) {
  const lojas = Object.entries(all_locations);
  const mostraLoja = is_admin && lojas.length > 1;

  // O que a troca de aba e a paginação precisam preservar. Tudo em query string —
  // o charter proíbe session pra estado de filtro.
  const parametros = new URLSearchParams(window.location.search);
  const filtrosDaTela = {
    from: period.from,
    to: period.to,
    location_id: parametros.get('location_id'),
  };

  const trocaLoja = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const id = e.target.value;
    router.visit(window.location.pathname, {
      data: {
        ...(id ? { location_id: id } : {}),
        from: period.from,
        to: period.to,
        // A loja filtra as grades também — sem carregar a aba junto, a tabela
        // seguiria mostrando a loja anterior enquanto os KPI já teriam mudado.
        ...(aba ? { aba } : {}),
      },
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ['totals', 'period', 'grade'],
    });
  };

  return (
    <Stack gap={5} className="mx-auto max-w-7xl p-6">
      <div data-contract="cabecalho">
      <PageHeader
        leading={
          <span className="mr-2 inline-flex translate-y-[1px] align-middle text-muted-foreground">
            <Icon name="layout-dashboard" size={18} strokeWidth={1.8} />
          </span>
        }
        title="Visão geral"
        subtitle={
          totals ? (
            <>
              <strong>{brlCurto(totals.total_sell)}</strong> vendas ·{' '}
              <strong>{brlCurto(totals.invoice_due)}</strong> a receber ·{' '}
              <strong>{brlCurto(totals.total_expense)}</strong> despesas
            </>
          ) : undefined
        }
        actions={
          <span className="text-[12.5px] text-muted-foreground">
            Bem-vindo{user_name ? `, ${user_name}` : ''}
          </span>
        }
      />
      </div>

      <Inline gap={4} align="end" justify="between" wrap>
        <PeriodBar period={period} />
        {mostraLoja && (
          <Stack gap={2} asChild>
            <label>
              <span className={ROTULO}>Loja</span>
              <select
                id="dashboard_location"
                onChange={trocaLoja}
                defaultValue=""
                className="h-9 rounded-lg border border-border bg-card px-3 text-[13px] text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
              >
                <option value="">Todas as lojas</option>
                {lojas.map(([id, nome]) => (
                  <option key={id} value={id}>
                    {nome}
                  </option>
                ))}
              </select>
            </label>
          </Stack>
        )}
      </Inline>

      {can_dashboard_data && totals ? (
        <Stack gap={4}>
          <KpiGrid cols={4} data-contract="kpis">
            <KpiHero
              label="Líquido no período"
              value={totals.net}
              delta={deltas?.net}
              description="Vendas − A receber − Despesas"
            />
            <KpiCard
              label="Vendas"
              tone="success"
              value={brl(totals.total_sell)}
              icon="trending-up"
              description="incluindo impostos"
              delta={deltas?.total_sell != null ? { value: deltas.total_sell, label: '% vs anterior' } : undefined}
            />
            <KpiCard
              label="A receber"
              value={brl(totals.invoice_due)}
              icon="hourglass"
              tone="warning"
              description={`${sinal(deltas?.invoice_due)}líquido de descontos de razão`}
            />
            <KpiCard
              label="Despesas"
              tone="info"
              value={brl(totals.total_expense)}
              icon="receipt"
              description={`${sinal(deltas?.total_expense)}lançadas no período`}
            />
          </KpiGrid>
          <Contrapartidas totals={totals} />

          <Deferred
            data='charts'
            fallback={
              <Grid cols={1} gap={4} className="lg:grid-cols-2">
                <div className={`${CARTAO} h-[190px] animate-pulse`} />
                <div className={`${CARTAO} h-[190px] animate-pulse`} />
              </Grid>
            }
          >
            <GraficosVendas charts={charts} />
          </Deferred>

          {/* Ordem do protótipo: contrapartidas → gráficos → abas de grade. */}
          <GradesPainel abas={abas} aba={aba} grade={grade} filtros={filtrosDaTela} />
        </Stack>
      ) : (
        <EmptyState
          icon="lock"
          title="Você não tem acesso aos dados do painel"
          description="A permissão dashboard.data não está atribuída ao seu papel. A tela abre sem erro — nenhum indicador é carregado."
        />
      )}
    </Stack>
  );
}

/*
 * Contrapartidas e GraficosVendas ficam DEPOIS do componente principal de propósito.
 * O gate `contrato-de-tela` lê a ordem das âncoras `data-contract` na ORDEM DO FONTE, e
 * com as auxiliares no topo a ordem textual (contrapartidas→graficos→cabecalho→kpis) não
 * batia com a de render (cabecalho→kpis→contrapartidas→graficos). `function` é hoisted,
 * então mover não muda comportamento — muda só o que a máquina consegue verificar.
 */

function Contrapartidas({ totals }: { totals: Totals }) {
  const itens: Array<[string, number, string]> = [
    ['Compras', totals.total_purchase, 'incluindo impostos'],
    ['A pagar', totals.purchase_due, 'líquido de descontos'],
    ['Devolução de venda', totals.total_sell_return, 'no período'],
    ['Devolução de compra', totals.total_purchase_return, 'devido ao fornecedor'],
  ];

  return (
    <Stack gap={4} asChild>
      <section aria-label="Contrapartidas" data-contract="contrapartidas" className={CARTAO}>
        <Inline gap={3} align="baseline" justify="between">
          <h2 className="text-[13.5px] font-semibold text-foreground">Contrapartidas</h2>
          <span className="font-mono text-[10.5px] text-muted-foreground">mesmo período</span>
        </Inline>
        <Grid cols={2} gap={4} className="lg:grid-cols-4">
          {itens.map(([label, valor, sub]) => (
            <Stack gap={1} key={label} className="min-w-0">
              <span className="text-[9.5px] font-semibold uppercase tracking-wider text-muted-foreground">
                {label}
              </span>
              <span className="font-mono text-[15.5px] font-semibold tabular-nums text-foreground">
                {brlCurto(valor)}
              </span>
              <span className="text-[11.5px] text-muted-foreground">{sub}</span>
            </Stack>
          ))}
        </Grid>
      </section>
    </Stack>
  );
}

/**
 * Os 2 gráficos da Visão geral. Lê `charts` DEPOIS do first paint (Deferred).
 * O <Chart> é o do DS portado 1:1 — SVG puro, sem lib.
 */
/**
 * SerieAcessivel — a MESMA série do gráfico, em texto, só pra leitor de tela.
 *
 * O `<Chart>` desenha em SVG: quem não enxerga o desenho não recebe número
 * nenhum. O `aria-label` do `PainelGrafico` anuncia o TÍTULO ("Vendas por dia")
 * e para aí — diz que existe um gráfico, não o que ele mostra.
 *
 * Tabela (e não um parágrafo) porque a série é tabular: leitor de tela navega
 * célula a célula e anuncia o cabeçalho de cada linha. O `<caption>` carrega a
 * leitura de relance — total e pico — pra quem não quer percorrer 30 linhas.
 *
 * Usa `brl` (moeda por extenso), não `brlCurto`: "R$ 12,3 mil" é abreviação
 * pensada pro olho num eixo estreito; em áudio o valor cheio é mais claro.
 */
function SerieAcessivel({
  titulo,
  dados,
}: {
  titulo: string;
  dados: Array<{ label: string; value: number }>;
}) {
  const primeiro = dados[0];
  if (!primeiro) return null; // série vazia: nada a anunciar (e satisfaz o strict)

  const total = dados.reduce((soma, p) => soma + p.value, 0);
  const pico = dados.reduce((maior, p) => (p.value > maior.value ? p : maior), primeiro);

  return (
    <table className="sr-only">
      <caption>
        {titulo}: total de {brl(total)} no período; maior valor em {pico.label}, {brl(pico.value)}.
      </caption>
      <thead>
        <tr>
          <th scope="col">Período</th>
          <th scope="col">Valor</th>
        </tr>
      </thead>
      <tbody>
        {dados.map((p) => (
          <tr key={p.label}>
            <th scope="row">{p.label}</th>
            <td>{brl(p.value)}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function GraficosVendas({ charts }: { charts: Props['charts'] }) {
  if (!charts) return null;

  return (
    <Grid cols={1} gap={4} className="lg:grid-cols-2" data-contract="graficos">
      <PainelGrafico titulo="Vendas por dia" meta="últimos 30 dias">
        <Chart type="area" data={charts.dia} height={132} formatValue={brlCurto} />
        <SerieAcessivel titulo="Vendas por dia, últimos 30 dias" dados={charts.dia} />
      </PainelGrafico>
      <PainelGrafico titulo="Vendas por mês" meta="ano fiscal">
        <Chart type="bar" data={charts.mes} height={132} highlightLast formatValue={brlCurto} />
        <SerieAcessivel titulo="Vendas por mês, ano fiscal" dados={charts.mes} />
      </PainelGrafico>
    </Grid>
  );
}


HomeIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Visão geral" breadcrumbItems={[{ label: 'Visão geral' }]}>
    {page}
  </AppShellV2>
);

export default HomeIndex;
