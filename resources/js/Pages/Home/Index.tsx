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
import { PageHeader } from '@/Components/PageHeader';
import { PeriodBar, type Period } from '@/Components/shared/PeriodBar';
import type { PaginatorShape } from '@/Components/shared/DataTable';
import GradesPainel, { type Aba, type LinhaDaGrade } from './_components/GradesPainel';
import { hrefDaAba, type Filtros } from './_components/abaHref';
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
  /**
   * Atalhos do painel de Pendências: as abas que têm algo esperando, já filtradas
   * por permissão e sem as de total zero. Prop DEFERIDA — são 5 contagens, e o alvo
   * de first-paint <= 800ms do charter não paga por atalho.
   */
  pendencias?: Array<{ aba: string; label: string; total: number }>;
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

const CARTAO = 'rounded-lg border border-border bg-card p-[14px] shadow-sm';
const ROTULO = 'text-[10.5px] font-semibold uppercase tracking-[0.06em] text-muted-foreground';

/**
 * KPI hero — o "Líquido no período".
 *
 * ── POR QUE ESTE COMPONENTE USA TOKEN PRÓPRIO (medido 2026-09-03) ────────────
 * A âncora (`prototipo-ui/cowork/dash-legacy-page.jsx:251`) renderiza o hero como
 * `<KpiCard hero … spark={SERIE_30.slice(-12)} />` — variante do DS, com fundo
 * `--kpi-feature-bg` e sparkline. O `KpiCard` DESTE repo não tem `hero` nem
 * `spark`, então a tela reimplementou o card aqui — e, ao reimplementar, herdou
 * `bg-card` (o card genérico) em vez do token do hero. Medido nos dois renders,
 * mesmo viewport 1280:
 *
 *     âncora    fundo oklch(.238 .02 264) · padding 16 · 1 svg (sparkline)
 *     produção  fundo oklch(.30  .008 240) · padding 20 · 0 svg
 *
 * O efeito era inverter a figura-fundo: o fundo da página é `L .26`, então o hero
 * da âncora AFUNDA (`.238`) e destaca, enquanto o de produção ELEVAVA (`.30`)
 * igual aos outros três — o número mais importante da tela não se distinguia.
 *
 * ⚠️ Os tokens `--kpi-feature-*` JÁ EXISTIAM em produção (ADR 0310, gerados em
 * `resources/css/tokens/_generated-cockpit-dark.css`), escopados em `.cockpit`.
 * Nada de token novo aqui — só passar a USAR o que a fundação já entrega. Foi a
 * medição no `:root` (onde eles não vivem) que fez parecer ausência.
 *
 * O `spark` chega por `Inertia::defer` junto com `charts`; até resolver, o espaço
 * é RESERVADO com a mesma altura, para o card não pular no first paint.
 */
function KpiHero({
  label,
  value,
  delta,
  description,
  spark,
}: {
  label: string;
  value: number;
  delta?: number | null;
  description?: string;
  spark?: Array<{ label: string; value: number }>;
}) {
  return (
    <Stack
      gap={1}
      className="rounded-lg border p-4 shadow-sm"
      style={{
        background: 'var(--kpi-feature-bg)',
        borderColor: 'var(--kpi-feature-line)',
      }}
    >
      <span className={ROTULO} style={{ color: 'var(--kpi-feature-fg-2)' }}>
        {label}
      </span>
      <Inline gap={2} align="baseline" wrap>
        <span
          className="text-[28px] font-bold tracking-tight"
          style={{ color: 'var(--kpi-feature-fg)' }}
        >
          {brl(value)}
        </span>
        {delta != null && (
          <Inline gap={1} align="baseline">
            {/* Seta + numero: so o NUMERO carrega a cor do sinal, como na ancora. */}
            <span className={`text-[11.5px] font-semibold ${delta >= 0 ? 'text-success' : 'text-destructive'}`}>
              {delta >= 0 ? '↗' : '↘'} {delta >= 0 ? '+' : ''}
              {delta}
            </span>
            {/* O rotulo e acessorio: peso 400 e tom secundario do proprio hero. Antes ele
                vinha DENTRO do span colorido e gritava junto com o numero. */}
            <span className="text-[11.5px] font-normal" style={{ color: 'var(--kpi-feature-fg-2)' }}>
              % vs anterior
            </span>
          </Inline>
        )}
      </Inline>
      {description && (
        <span className="text-[12px]" style={{ color: 'var(--kpi-feature-fg-2)' }}>
          {description}
        </span>
      )}
      {/*
        Sparkline: a âncora usa os últimos 12 pontos da série de 30 dias.

        `aria-hidden` de propósito — este SVG é DECORATIVO e redundante: o valor do
        período e o delta já estão em TEXTO logo acima, no mesmo card. Sem isto o
        gráfico entraria como conteúdo sem alternativa textual, que é exatamente o
        defeito que `UC-DASH-18` existe pra impedir (o `SerieAcessivel` cobre os 2
        gráficos do painel, onde o dado NÃO está escrito em lugar nenhum).
      */}
      <div className="mt-auto h-[44px]" aria-hidden="true">
        {spark && spark.length > 0 ? (
          <Chart type="area" data={spark.slice(-12)} height={44} formatValue={brlCurto} />
        ) : null}
      </div>
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
  pendencias,
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
    <Stack className="gap-[10px] px-[14px] pb-5">
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
              <strong className="text-warning">{brlCurto(totals.invoice_due)}</strong> a receber ·{' '}
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
        <Stack className="gap-[10px]">
          <Grid fit="sm" gap={2} data-contract="kpis">
            <KpiHero
              label="Líquido no período"
              value={totals.net}
              delta={deltas?.net}
              spark={charts?.dia}
            />
            <KpiCard
              label="Vendas"
              tone="success"
              value={brl(totals.total_sell)}
              description="incluindo impostos"
              delta={deltas?.total_sell != null ? { value: deltas.total_sell, label: '% vs anterior' } : undefined}
            />
            <KpiCard
              label="A receber"
              value={brl(totals.invoice_due)}
              tone="warning"
              description={`${sinal(deltas?.invoice_due)}líquido de descontos de razão`}
            />
            <KpiCard
              label="Despesas"
              tone="info"
              value={brl(totals.total_expense)}
              description={`${sinal(deltas?.total_expense)}lançadas no período`}
            />
          </Grid>
          {/*
            Contrapartidas à esquerda, Pendências à direita — a linha de 2 colunas da
            âncora (`minmax(0, 1.4fr)` / `minmax(240px, 1fr)`, `dash-legacy-page.jsx`
            linha 257). As proporções e o piso de 240px são os dela, não escolha daqui.
          */}
          <Grid className="grid-cols-[minmax(0,1.4fr)_minmax(240px,1fr)] gap-[10px]">
            <Contrapartidas totals={totals} />
            <Deferred
              data="pendencias"
              fallback={<div className={`${CARTAO} h-[150px] animate-pulse`} />}
            >
              <PendenciasPainel pendencias={pendencias} filtros={filtrosDaTela} />
            </Deferred>
          </Grid>

          <Deferred
            data='charts'
            fallback={
              <Grid className="grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] gap-[10px]">
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
        <Grid fit="xs" className="gap-x-[14px] gap-y-3">
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
 * Pendências — atalho pras abas que têm algo esperando agora.
 *
 * ⚠️ O que este painel NÃO copia da âncora, e por quê: lá (`dash-legacy-page.jsx`,
 * const `PENDENCIAS`) cada linha tem texto próprio e um selo de severidade —
 * "1 título de venda vencido" + `payment/overdue`. Esses rótulos descrevem um
 * predicado MAIS ESTRITO do que o que a aba consulta: `titulosVencendo` traz tudo
 * que vence em até 7 dias, vencido ou não. Carimbar "vencido" num conjunto que
 * inclui o que ainda vai vencer é rotular errado — o usuário clica e não encontra
 * o que o selo prometeu. Severidade honesta seria um SEGUNDO predicado: decisão de
 * [W], não wiring.
 *
 * Então a linha mostra o rótulo CANÔNICO da aba — o mesmo do catálogo do serviço e
 * o mesmo que a aba exibe — e o total que a aba vai mostrar. O número vem do mesmo
 * `linhas()` que serve a grade, então clicar nunca contradiz o que estava escrito.
 */
function PendenciasPainel({ pendencias, filtros }: { pendencias: Props['pendencias']; filtros: Filtros }) {
  return (
    <Stack gap={3} asChild>
      <section aria-label="Pendências" data-contract="pendencias" className={CARTAO}>
        <Inline gap={3} align="baseline" justify="between">
          <h2 className="text-[13.5px] font-semibold text-foreground">Pendências</h2>
          {/* Não é "mesmo período" como nas Contrapartidas: nenhuma das 5 consultas
              é recortada pela PeriodBar — elas olham o estado de agora. */}
          <span className="font-mono text-[10.5px] text-muted-foreground">agora</span>
        </Inline>
        {pendencias && pendencias.length > 0 ? (
          <Stack gap={0} asChild>
            <ul>
              {pendencias.map(({ aba, label, total }) => (
                <li key={aba} className="border-b border-border last:border-b-0">
                  {/* Link, não botão: a aba mora na query string, então o atalho tem
                      de ser um endereço de verdade — com voltar, abrir em nova aba e
                      o mesmo construtor de URL que a barra de abas usa. */}
                  <a
                    href={hrefDaAba(filtros, aba)}
                    className="flex items-center justify-between gap-3 py-[7px] text-[12.5px] text-foreground hover:text-primary"
                  >
                    <span className="min-w-0">{label}</span>
                    <span className="font-mono font-semibold tabular-nums">{total}</span>
                  </a>
                </li>
              ))}
            </ul>
          </Stack>
        ) : (
          <span className="text-[12.5px] text-muted-foreground">Nada pendente.</span>
        )}
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
 * Usa `brl` (moeda por extenso), não `brlCurto`: a forma abreviada é
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
    <Grid className="grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] gap-[10px]" data-contract="graficos">
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
