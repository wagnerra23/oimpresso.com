// @memcofre
//   tela: /copiloto/dashboard
//   stories: US-COPI-010, US-COPI-011, US-COPI-012
//   rules: R-COPI-002, R-COPI-FAROL-001
//   adrs: 0026, 0031, 0035, 0036
//   tests: tests/Feature/Modules/Copiloto/MemoriaContratoTest
//   status: implementada
//   module: Copiloto

import React, { useState } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link } from '@inertiajs/react'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { MessageSquare, TrendingUp, TrendingDown, Minus, Sparkles, Brain, Clock, Zap, Settings, Download, Target } from 'lucide-react'
import FabJana from './components/FabJana'
import { JanaAreaHeader } from './components/JanaAreaHeader'
import JanaCockpit, { type JanaCockpitProps } from './_components/JanaCockpit'
import JanaConfigDrawer from './_components/JanaConfigDrawer'
import JanaMetaDrawer from './_components/JanaMetaDrawer'
import { useJanaConfig } from './_components/useJanaConfig'
// Tipos e formatadores com DOIS consumidores (Index + JanaMetaDrawer) moram em
// `_components/metaFormat.ts` desde 2026-08-17: arquivo de componente não exporta
// não-componente (`react-refresh`, a mesma regressão que separou o `useJanaConfig`).
// `periodoLabel`/`mesAno`/`Sparkline` FICARAM aqui — são do card, e o drawer
// recebe o período já formatado como prop.
import {
  FAROL_CLASSES,
  farolDaMeta,
  formatValue,
  type Apuracao,
  type Meta,
  type Periodo,
} from './_components/metaFormat'

interface Props {
  metas: Meta[]
  /** Jana V2 cockpit payload (movido de /sells Onda 2026-05-26). */
  sellKpis: JanaCockpitProps['sellKpis']
  insightsAggregates: JanaCockpitProps['insightsAggregates']
  coworkAggregates?: JanaCockpitProps['coworkAggregates']
  janaContext: {
    businessId: number | null
    businessName: string
    userName: string | null
  }
}

// Onda de fidelidade (2026-08-07): o farol NÃO é calculado aqui. O charter
// proibia em dois lugares — §Goals ("frontend só consome") e §Anti-hooks ("⛔
// Cálculo de farol no frontend") — e a regra vivia no frontend mesmo assim. A
// fonte autoritativa é `ApuracaoService::farol()`, e `farolDaMeta` (em
// `metaFormat.ts`) só lê o campo que chega no payload.

// Rótulo do período no card de meta (âncora: `jm-meta-p` no header do
// `JmMetaCard` — `grep -n "jm-meta-p" prototipo-ui/cowork/jana-merge.css`).
//
// ⚠️ NÃO usar `new Date('2026-05-01')`: a string date-only é parseada como UTC
// meia-noite pelo JS, e em BRT (UTC-3) isso volta 30/abr — o card mostraria o mês
// ERRADO em todo período que começa no dia 1º. Fatiar os 10 primeiros chars evita
// o fuso inteiro, e funciona tanto pra 'YYYY-MM-DD' quanto pro ISO completo que o
// cast `date` do Eloquent pode emitir.
//
// O protótipo mostra sempre "mai/2026" porque os períodos dele são mensais por
// construção. Aqui o período é dado real e pode cruzar meses (`MetaPeriodo` tem
// data_ini/data_fim livres), então um período trimestral vira "mai–jul/2026" em
// vez de mentir com um mês só.
const MESES_PT = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez']

function mesAno(iso: string | null | undefined): { mes: string; ano: string } | null {
  if (!iso) return null
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso)
  // `ano`/`mm` saem como `string | undefined` sob `noUncheckedIndexedAccess`;
  // o guard explícito é o que faz o tipo fechar — e de quebra cobre o caso real
  // de `data_fim` ausente, que existe no payload (`periodo_atual` é nullable).
  const ano = m?.[1]
  const mm = m?.[2]
  if (!ano || !mm) return null
  const mes = MESES_PT[Number(mm) - 1]
  if (!mes) return null
  return { mes, ano }
}

function periodoLabel(periodo: Periodo): string | null {
  const ini = mesAno(periodo.data_ini)
  const fim = mesAno(periodo.data_fim)
  if (!ini) return null
  if (!fim || (ini.mes === fim.mes && ini.ano === fim.ano)) return `${ini.mes}/${ini.ano}`
  if (ini.ano === fim.ano) return `${ini.mes}–${fim.mes}/${fim.ano}`
  return `${ini.mes}/${ini.ano}–${fim.mes}/${fim.ano}`
}

function Sparkline({ dados }: { dados: Apuracao[] }) {
  if (dados.length < 2) {
    return <div data-contract="painel-meta-sem-historico" className="h-8 text-xs text-muted-foreground flex items-center">Sem histórico</div>
  }

  const valores = dados.map(d => d.valor_realizado)
  const min = Math.min(...valores)
  const max = Math.max(...valores)
  const range = max - min || 1
  const w = 120
  const h = 32
  const pts = valores.map((v, i) => {
    const x = (i / (valores.length - 1)) * w
    const y = h - ((v - min) / range) * h
    return `${x},${y}`
  })

  const last = valores[valores.length - 1] ?? 0
  const prev = valores[valores.length - 2] ?? 0
  const tendencia = last > prev ? 'up' : last < prev ? 'down' : 'flat'

  return (
    <div className="flex items-center gap-2">
      <svg width={w} height={h} className="text-primary overflow-visible" aria-hidden="true">
        <polyline
          points={pts.join(' ')}
          fill="none"
          stroke="currentColor"
          strokeWidth={2}
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </svg>
      {tendencia === 'up'   && <TrendingUp   className="h-4 w-4 text-success" />}
      {tendencia === 'down' && <TrendingDown  className="h-4 w-4 text-destructive" />}
      {tendencia === 'flat' && <Minus         className="h-4 w-4 text-muted-foreground" />}
    </div>
  )
}

// O card inteiro abre o drawer, e por isso é um `<button>` nativo em vez de
// `div[role=button]`: Enter/Espaço, foco e leitura de tela vêm de graça (mesma
// escolha do `AnalysisCard` do JanaCockpit e do `KpiCard` canônico).
//
// O link "Ver detalhe" que morava no rodapé SAIU — era ele que tirava o usuário
// do Painel (R5 do `Index-visual-comparison.md`, ordem 1). O destino não se
// perdeu: virou "Abrir a meta" no rodapé do drawer, junto da série de 12 janelas
// e da origem do número.
function MetaCard({ meta, onOpen }: { meta: Meta; onOpen: (meta: Meta, periodo: string | null) => void }) {
  const farol        = farolDaMeta(meta)
  const realizado    = meta.ultima_apuracao?.valor_realizado ?? null
  const alvo         = meta.periodo_atual?.valor_alvo ?? null
  const progresso    = alvo && realizado !== null ? Math.min(100, (realizado / alvo) * 100) : null
  // `null` quando não há período OU quando a data não parseia — o card
  // simplesmente não mostra o rótulo, em vez de exibir "Invalid Date".
  const periodo      = meta.periodo_atual ? periodoLabel(meta.periodo_atual) : null

  return (
    // `<button>` por FORA do Card, e não `asChild`: o `Card` deste DS é um
    // `<div>` simples (`Components/ui/card.tsx:5`) e não implementa `asChild` —
    // passar a prop mandaria um atributo desconhecido pro DOM. O wrapper não
    // custa ao `layout:check`, que conta `className` com flex/grid, e este não
    // tem nenhum.
    <button
      type="button"
      onClick={() => onOpen(meta, periodo)}
      aria-label={`Abrir a meta ${meta.nome}`}
      className="w-full rounded-lg text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
    <Card className="relative h-full overflow-hidden transition-colors hover:border-primary/40">
      {/* Farol lateral */}
      <div className={`absolute left-0 top-0 h-full w-1 ${FAROL_CLASSES[farol]}`} aria-hidden="true" />

      <CardHeader className="pb-2 pl-5">
        {/* 3 filhos no MESMO flex, sem wrapper novo: o título leva `flex-1` e
            empurra período + unidade pro canto direito. Um `<div>` agrupando os
            dois últimos seria um flex/grid solto A MAIS neste arquivo, e o
            `layout:check` é ratchet POR ARQUIVO (baseline: 11) — a regra existe
            justamente pra impedir que cada ajuste vá somando container. */}
        <div className="flex items-start justify-between gap-2">
          <CardTitle className="flex-1 text-base">{meta.nome}</CardTitle>
          {periodo && (
            <span className="shrink-0 font-mono text-[10.5px] leading-5 text-muted-foreground">
              {periodo}
            </span>
          )}
          <Badge variant="outline" className="shrink-0">{meta.unidade}</Badge>
        </div>
      </CardHeader>

      <CardContent className="pl-5 space-y-3">
        {realizado !== null ? (
          <div className="text-2xl font-semibold tabular-nums">
            {formatValue(realizado, meta.unidade)}
          </div>
        ) : (
          <div data-contract="painel-meta-apurando" className="text-sm text-muted-foreground">Aguardando apuração…</div>
        )}

        {/* Barra de progresso — `jm-meta-track` da âncora. O número já existia
            abaixo; o que faltava era o comprimento, que é o que se lê de relance
            numa grade de N metas. `progresso` já vem travado em 100 pelo cálculo
            acima, então a largura não estoura o trilho. */}
        {progresso !== null && (
          <div className="h-1.5 overflow-hidden rounded-full bg-muted">
            <div
              className={`h-full rounded-full ${FAROL_CLASSES[farol]}`}
              style={{ width: `${progresso}%` }}
            />
          </div>
        )}

        {alvo !== null && (
          <div className="text-xs text-muted-foreground">
            Alvo: {formatValue(alvo, meta.unidade)}
            {progresso !== null && (
              <span className="ml-2 font-medium text-foreground">{progresso.toFixed(0)}%</span>
            )}
          </div>
        )}

        {meta.apuracoes_recentes.length > 0 && (
          <Sparkline dados={meta.apuracoes_recentes} />
        )}
      </CardContent>
    </Card>
    </button>
  )
}

function JanaKpiStrip() {
  // Placeholders — Brain B vai preencher via Inertia::defer no futuro
  // Mocks intencionais pra demo CYCLE-06 G3 (não consultam DB)
  const kpis = [
    {
      icon: Brain,
      label: 'Memória ativa',
      value: '—',
      hint: 'facts indexados',
      tone: 'text-primary',
    },
    {
      icon: Clock,
      label: 'Última conversa',
      value: '—',
      hint: 'aguardando contexto',
      tone: 'text-primary',
    },
    {
      icon: Zap,
      label: 'Brain B hoje',
      value: '0/50',
      hint: 'orçamento diário',
      tone: 'text-primary',
    },
  ]

  return (
    <div className="grid gap-3 sm:grid-cols-3">
      {kpis.map(({ icon: Icon, label, value, hint, tone }) => (
        <Card key={label} className="border-muted/60">
          <CardContent className="flex items-center gap-3 p-4">
            <div className={`rounded-full bg-primary/10 p-2 ${tone}`}>
              <Icon className="h-5 w-5" aria-hidden="true" />
            </div>
            <div className="min-w-0 flex-1">
              <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
              <div className="text-lg font-semibold tabular-nums">{value}</div>
              <div className="text-[11px] text-muted-foreground">{hint}</div>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

function ProximaAcaoCard() {
  // Mock pra demo — Brain B vai preencher próxima ação sugerida
  return (
    // Mesmo tratamento do brief no JanaCockpit: tint OPACO sobre a superfície de
    // card. O gradiente translúcido de 3 paradas compunha sobre o fundo da página
    // (dark: oklch 0.26, mais escuro que um card 0.30) e o bloco afundava.
    <Card className="border-primary/25 bg-[color:color-mix(in_oklch,var(--color-primary)_6%,var(--color-card))]">
      <CardHeader className="pb-2">
        <div className="flex items-center gap-2">
          <Sparkles className="h-4 w-4 text-primary" />
          <CardTitle className="text-sm font-medium text-primary">
            Próxima ação sugerida
          </CardTitle>
        </div>
      </CardHeader>
      <CardContent className="pt-1">
        <p className="text-sm text-muted-foreground">
          Quando houver sinal claro nas metas, a Jana vai sugerir aqui o próximo passo prático — sem você precisar perguntar.
        </p>
        <div className="mt-3">
          <Link href="/ia/conversa">
            <Button data-contract="painel-cta-conversar" size="sm" variant="outline" className="gap-2">
              <MessageSquare className="h-4 w-4" />
              Conversar agora
            </Button>
          </Link>
        </div>
      </CardContent>
    </Card>
  )
}

export default function Dashboard({ metas, sellKpis, insightsAggregates, coworkAggregates, janaContext }: Props) {
  // Config da tela. Mora AQUI, e não no drawer, porque tem dois consumidores:
  // o drawer escreve e o `JanaCockpit` lê pra decidir quais análises renderiza.
  const { config, alternarAnalise } = useJanaConfig()
  const [configAberto, setConfigAberto] = useState(false)
  // Meta aberta no drawer. Guarda o OBJETO + o período já formatado pelo card:
  // o payload veio inteiro no first render, então reabrir não custa consulta, e
  // o `periodoLabel` continua morando num lugar só.
  const [metaAberta, setMetaAberta] = useState<{ meta: Meta; periodo: string | null } | null>(null)

  return (
    <>
      {/* JanaAreaHeader — barra ÚNICA da área Jana (PageHeader canon).
          Onda de fusão 2026-08-07: identidade (business + biz), "Atualizado" e
          as ações Configurar/Exportar SUBIRAM do header próprio do JanaCockpit,
          que era a segunda barra da tela e foi removido. Gate F1.5 em
          memory/requisitos/Jana/Chat-header-tabs-visual-comparison.md */}
      <JanaAreaHeader
        active="dashboard"
        businessName={janaContext.businessName || undefined}
        businessId={janaContext.businessId ?? undefined}
        actions={
          <>
            {/* Até 2026-08-17 este botão era clicável, sem rota e sem `disabled`,
                anunciando Brain B como "em breve" — promessa que a tela não
                cumpria, e por isso o contrato manteve os dois botões FORA dele
                ("pinar uma promessa é congelá-la"). Agora abre o drawer.
                Âncora: `jana-merge.jsx` §JmConfigDrawer.
                (A frase antiga não é repetida aqui de propósito: o UC-10 asserta
                a ausência dela, e citá-la no comentário criaria o falso-positivo
                que o §5 2026-07-26 cataloga.) */}
            <Button
              variant="outline"
              size="sm"
              onClick={() => setConfigAberto(true)}
              aria-haspopup="dialog"
              aria-expanded={configAberto}
            >
              <Settings className="h-3.5 w-3.5" /> Configurar
            </Button>
            <Button variant="outline" size="sm" title="Exportar relatório (em breve)">
              <Download className="h-3.5 w-3.5" /> Exportar
            </Button>
          </>
        }
      />

      {/* Aviso de viewport — só abaixo de 768px (`md:hidden`, que é exatamente o
          breakpoint do `@media (max-width:768px)` da âncora em jana-merge.css).
          Âncora de SÍMBOLO: `grep -n "jm-nota-mob" prototipo-ui/cowork/jana-merge.css`.
          Copy literal do protótipo (`jana-merge.jsx`, símbolo `jm-nota-mob`).

          Por que existe: o charter fixa "1 viewport scroll desktop 1280px" como
          alvo de UX (§UX targets) — no celular os cards abaixo ficam apertados, e
          o honesto é DIZER isso em vez de deixar o usuário achar que quebrou.
          Tom `warning` (mesma família do protótipo, que usa `--warn`), via token —
          não escala crua. */}
      <div className="px-6 pt-6 md:hidden">
        <p className="rounded-[10px] border border-warning/30 bg-warning/8 px-3 py-2.5 text-[11.5px] leading-relaxed text-muted-foreground">
          O painel foi desenhado pro escritório (1280px). No celular, a Conversa dá conta — os cards
          abaixo ficam apertados.
        </p>
      </div>

      {/* JanaCockpit — conteúdo primário PT-04 (bifurcação do antigo JanaCockpitV2, US-COPI-146).
          Sem wrapper .sells-cowork: o cockpit agora usa shared KpiGrid/KpiCard + Card +
          tokens Tailwind (dark herda nativo), zero ilha CSS. Este comentário dizia que a
          tab Insights de /sells "segue no JanaCockpitV2" — era falso: aquela tab já não
          existe e o V2 tinha 0 imports; foi removido em 2026-08-10.
          shrink-0: .main-body é flex-column + overflow-y:auto (cockpit.css); sem ele o
          flex encolhia o wrapper e o conteúdo (mais alto) vazava sobre o bloco Metas. */}
      <div className="px-6 pt-6 shrink-0">
        <JanaCockpit
          sellKpis={sellKpis}
          insightsAggregates={insightsAggregates}
          coworkAggregates={coworkAggregates}
          userName={janaContext.userName ?? undefined}
          analisesVisiveis={config.analises}
        />
      </div>

      {/* Bloco secundário — Dashboard de Metas (continua acessível, mas não é
          mais a face primária da /ia/dashboard). shrink-0 pelo mesmo motivo do
          wrapper do cockpit acima (flex-column scroll container). */}
      <div className="space-y-6 p-6 shrink-0">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              {/* Badge sólido em `primary`. Era um gradiente de 3 paradas
                  (violet→fuchsia→pink) — paleta inventada, fora do sistema de
                  token e sem par no dark. Não vira "gradiente de token": some. */}
              <Badge aria-label="Versão Jana V2">
                <Sparkles className="mr-1 h-3 w-3" aria-hidden="true" />
                METAS
              </Badge>
              <span data-contract="painel-metas-header" className="text-xs text-muted-foreground">Acompanhamento contínuo</span>
            </div>
            <div>
              <h2 className="text-xl font-semibold tracking-tight">Metas ativas</h2>
              <p className="text-sm text-muted-foreground">
                {metas.length} {metas.length === 1 ? 'meta ativa' : 'metas ativas'} — visão consolidada do business
              </p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {/* "Nova meta" — a âncora põe este botão no cabeçalho da seção METAS
                (`jana-merge.jsx`, símbolo `JmMetasSecao`; re-localize com
                `grep -n "Nova meta" prototipo-ui/cowork/jana-merge.jsx`).

                ⚠️ `<a href>` NATIVO, nunca `<Link>` do Inertia — e isto não é
                estilo, é a diferença entre funcionar e não funcionar.
                `MetasController@create` retorna BLADE (`view('copiloto::metas.create')`,
                MetasController.php:25-28), não `Inertia::render`. Um `<Link>` pediria
                resposta Inertia, não receberia, e o clique viraria NO-OP SILENCIOSO —
                exatamente o defeito que fez [W] remover o ghost 'metas' do
                DataController em 2026-05-23 (o comentário lá ainda descreve o caso).
                Navegação full-page entrega a tela real. Quando o MetasController for
                migrado via MWART, isto vira `<Link>` no mesmo PR.

                O charter proíbe "prometer no botão o que a rota não entrega"
                (§Anti-hooks) — a rota entrega: `Modules/Jana/Resources/views/metas/create.blade.php`
                existe e o prefixo do grupo é `ia` (routes.php:51). */}
            <Button variant="outline" className="gap-2" asChild>
              <a href="/ia/metas/create">
                <Target className="h-4 w-4" />
                Nova meta
              </a>
            </Button>
            {/* Entry-point pro paywall Jana Pro (ADR 0140). Upsell discreto —
                a ação primária da Dashboard continua sendo "Conversar". */}
            <Link href="/ia/pro">
              <Button variant="outline" className="gap-2">
                <Sparkles className="h-4 w-4 text-primary" />
                Jana Pro
              </Button>
            </Link>
            <Link href="/ia/conversa">
              <Button variant="outline" className="gap-2">
                <MessageSquare className="h-4 w-4" />
                Conversar com a Jana
              </Button>
            </Link>
          </div>
        </div>

        <JanaKpiStrip />

        <ProximaAcaoCard />

        {metas.length === 0 ? (
          <Card className="border-dashed">
            <CardContent className="flex flex-col items-center justify-center gap-4 py-12 text-center">
              <div className="rounded-full bg-primary/10 p-4">
                <Sparkles className="h-10 w-10 text-primary" aria-hidden="true" />
              </div>
              <div className="space-y-1">
                <p data-contract="painel-metas-vazio" className="text-base font-medium">Nenhuma meta cadastrada ainda</p>
                <p className="max-w-sm text-sm text-muted-foreground">
                  Pergunte algo à Jana — ela aprende o que importa pro seu business e cria metas com base no que conversamos.
                </p>
              </div>
              <Link href="/ia/conversa">
                <Button className="gap-2">
                  <MessageSquare className="h-4 w-4" />
                  Pergunte algo a Jana
                </Button>
              </Link>
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {metas.map(meta => (
              <MetaCard
                key={meta.id}
                meta={meta}
                onOpen={(m, periodo) => setMetaAberta({ meta: m, periodo })}
              />
            ))}
          </div>
        )}
      </div>

      <JanaConfigDrawer
        open={configAberto}
        onClose={() => setConfigAberto(false)}
        config={config}
        onAlternarAnalise={alternarAnalise}
      />

      {/* A meta abre AQUI, não noutra tela — âncora §JmMetaDrawer. */}
      <JanaMetaDrawer
        meta={metaAberta?.meta ?? null}
        periodo={metaAberta?.periodo ?? null}
        onClose={() => setMetaAberta(null)}
      />

      <FabJana contextRoute="/ia/dashboard" />
    </>
  )
}

Dashboard.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Jana — Dashboard" breadcrumbItems={[{ label: 'Jana' }, { label: 'Dashboard' }]}>
    {page}
  </AppShellV2>
)
