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
import { MessageSquare, TrendingUp, TrendingDown, Minus, Plus, Sparkles, Brain, Clock, Zap, Settings, Download } from 'lucide-react'
import FabJana from './components/FabJana'
import { JanaAreaHeader } from './components/JanaAreaHeader'
import JanaCockpit, { type JanaCockpitProps } from './_components/JanaCockpit'
import JanaConfigDrawer from './_components/JanaConfigDrawer'
import JanaMetaDrawer from './_components/JanaMetaDrawer'
import { Inline } from '@/Components/layout'
import { useJanaConfig } from './_components/useJanaConfig'
// Tipos e formatadores da seção Metas moram em `_components/metaFormat.ts` desde
// a onda de aproximação da âncora (2026-08-17): o drawer novo precisa dos mesmos,
// e arquivo de componente não pode exportar não-componente (`react-refresh`).
import {
  FAROL_CLASSES,
  farolDaMeta,
  formatValue,
  progressoDaMeta,
  rotuloPeriodo,
  type Apuracao,
  type Meta,
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

// Onda de fidelidade (2026-08-07): o farol NÃO é calculado aqui.
//
// O `Index.charter.md` proibia isto em dois lugares — §Goals ("frontend só
// consome") e §Anti-hooks ("⛔ Cálculo de farol no frontend") — e a regra vivia
// no frontend mesmo assim. A fonte autoritativa é `ApuracaoService::farol()`,
// que chega pronto no payload; `farolDaMeta` (em `metaFormat.ts`) só lê.

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
// O `<Link href="/ia/metas/{id}">Ver detalhe</Link>` que morava aqui SAIU — era
// ele que tirava o usuário do Painel (R5 do `Index-visual-comparison.md`, o
// maior buraco da tela). O destino não se perdeu: virou "Abrir a meta" no rodapé
// do drawer, junto com a série de 12 janelas e a origem do número.
function MetaCard({ meta, onOpen }: { meta: Meta; onOpen: (meta: Meta) => void }) {
  const farol        = farolDaMeta(meta)
  const realizado    = meta.ultima_apuracao?.valor_realizado ?? null
  const alvo         = meta.periodo_atual?.valor_alvo ?? null
  const progresso    = progressoDaMeta(meta)
  const periodo      = rotuloPeriodo(meta.periodo_atual)

  return (
    <button
      type="button"
      onClick={() => onOpen(meta)}
      aria-label={`Abrir a meta ${meta.nome}`}
      className="rounded-lg text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
      <Card className="relative h-full overflow-hidden transition-colors hover:border-primary/40">
        {/* Farol lateral */}
        <div className={`absolute left-0 top-0 h-full w-1 ${FAROL_CLASSES[farol]}`} aria-hidden="true" />

        <CardHeader className="pb-2 pl-5">
          <div className="flex items-start justify-between gap-2">
            <CardTitle className="text-base">{meta.nome}</CardTitle>
            {/* Período no card — `jm-meta-p` da âncora. Ele responde "esse número
                é de quando?", que antes só existia dentro da tela de detalhe. */}
            <span className="shrink-0 text-xs text-muted-foreground">
              {periodo ?? meta.unidade}
            </span>
          </div>
        </CardHeader>

        <CardContent className="pl-5 space-y-3">
          {realizado !== null ? (
            <Inline gap={1} align="baseline">
              <span className="text-2xl font-semibold tabular-nums">
                {formatValue(realizado, meta.unidade)}
              </span>
              {alvo !== null && (
                <small className="text-xs text-muted-foreground">de {formatValue(alvo, meta.unidade)}</small>
              )}
            </Inline>
          ) : (
            <div data-contract="painel-meta-apurando" className="text-sm text-muted-foreground">Aguardando apuração…</div>
          )}

          {/* Barra de progresso — `jm-meta-track` da âncora. Trava em 100% na
              LARGURA (barra que estoura o trilho vira ruído visual), mas o
              rótulo abaixo mostra o percentual REAL: uma meta em 132% precisa
              aparecer como 132%, não como "cheia". */}
          {progresso !== null && (
            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
              <div
                className={`h-full rounded-full ${FAROL_CLASSES[farol]}`}
                style={{ width: `${Math.min(Math.max(progresso, 0), 100)}%` }}
              />
            </div>
          )}

          {progresso !== null && (
            <div className="text-xs text-muted-foreground">
              <span className="font-medium text-foreground">{progresso.toFixed(0)}%</span> do alvo
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
  // Meta aberta no drawer. Guarda o OBJETO, não o id: o payload já veio inteiro
  // no first render, então reabrir não custa consulta nenhuma.
  const [metaAberta, setMetaAberta] = useState<Meta | null>(null)

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

      {/* Aviso de viewport — `jm-nota-mob` da âncora. O Painel foi desenhado pro
          monitor de 1280px da ROTA LIVRE (charter §UX targets); no celular os
          cards ficam apertados e a Conversa é o caminho melhor. Dizer isso é
          mais honesto que deixar o usuário descobrir rolando.
          `sm:hidden` = some a partir de 640px. */}
      <div className="mx-6 mt-6 rounded-lg border border-border bg-muted/40 px-3.5 py-2.5 text-xs leading-relaxed text-muted-foreground sm:hidden">
        O painel foi desenhado pro escritório (1280px). No celular, a Conversa dá conta — os cards
        abaixo ficam apertados.
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
            {/* "Nova meta" — `jm-btn ghost` no cabeçalho da seção, na âncora.
                Aponta pra `/ia/metas/create`, que EXISTE de verdade
                (`Route::resource('/metas', MetasController)` com a view
                `copiloto::metas.create`) — este botão entrega, não promete. A
                tela de destino ainda é Blade; migrá-la é outro trabalho. */}
            <Link href="/ia/metas/create">
              <Button variant="outline" className="gap-2">
                <Plus className="h-4 w-4" />
                Nova meta
              </Button>
            </Link>
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
              <MetaCard key={meta.id} meta={meta} onOpen={setMetaAberta} />
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
      <JanaMetaDrawer meta={metaAberta} onClose={() => setMetaAberta(null)} />

      <FabJana contextRoute="/ia/dashboard" />
    </>
  )
}

Dashboard.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Jana — Dashboard" breadcrumbItems={[{ label: 'Jana' }, { label: 'Dashboard' }]}>
    {page}
  </AppShellV2>
)
