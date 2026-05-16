// @memcofre
//   tela: /copiloto/dashboard
//   stories: US-COPI-010, US-COPI-011, US-COPI-012
//   rules: R-COPI-002, R-COPI-FAROL-001
//   adrs: 0026, 0031, 0035, 0036
//   tests: tests/Feature/Modules/Copiloto/MemoriaContratoTest
//   status: implementada
//   module: Copiloto

import React from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link } from '@inertiajs/react'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { MessageSquare, TrendingUp, TrendingDown, Minus, ExternalLink, Sparkles, Brain, Clock, Zap } from 'lucide-react'
import FabJana from './components/FabJana'

interface Apuracao {
  data_ref: string
  valor_realizado: number
}

interface Periodo {
  data_ini: string
  data_fim: string
  valor_alvo: number
  trajetoria: string
}

interface Meta {
  id: number
  slug: string
  nome: string
  unidade: string
  tipo_agregacao: string
  periodo_atual: Periodo | null
  ultima_apuracao: Apuracao | null
  apuracoes_recentes: Apuracao[]
}

interface Props {
  metas: Meta[]
}

function calcularFarol(
  meta: Meta
): 'verde' | 'amarelo' | 'vermelho' | 'cinza' {
  const periodo = meta.periodo_atual
  const ultima  = meta.ultima_apuracao

  if (! periodo || ! ultima) return 'cinza'

  const hoje          = new Date()
  const ini           = new Date(periodo.data_ini)
  const fim           = new Date(periodo.data_fim)
  const totalMs       = fim.getTime() - ini.getTime()
  const decorridoMs   = hoje.getTime() - ini.getTime()
  const progresso     = Math.min(1, Math.max(0, decorridoMs / totalMs))
  const projetado     = periodo.valor_alvo * progresso
  const realizado     = ultima.valor_realizado

  if (projetado <= 0) return 'cinza'

  const desvioPct = ((realizado - projetado) / projetado) * 100

  if (desvioPct >= -5) return 'verde'
  if (desvioPct >= -15) return 'amarelo'
  return 'vermelho'
}

const FAROL_CLASSES: Record<string, string> = {
  verde:    'bg-emerald-500',
  amarelo:  'bg-amber-400',
  vermelho: 'bg-rose-500',
  cinza:    'bg-muted-foreground/30',
}

function formatValue(value: number, unidade: string) {
  if (unidade === 'R$') {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(value)
  }
  if (unidade === '%') return `${value.toFixed(1)}%`
  return new Intl.NumberFormat('pt-BR').format(value)
}

function Sparkline({ dados }: { dados: Apuracao[] }) {
  if (dados.length < 2) {
    return <div className="h-8 text-xs text-muted-foreground flex items-center">Sem histórico</div>
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
      {tendencia === 'up'   && <TrendingUp   className="h-4 w-4 text-emerald-500" />}
      {tendencia === 'down' && <TrendingDown  className="h-4 w-4 text-rose-500" />}
      {tendencia === 'flat' && <Minus         className="h-4 w-4 text-muted-foreground" />}
    </div>
  )
}

function MetaCard({ meta }: { meta: Meta }) {
  const farol        = calcularFarol(meta)
  const realizado    = meta.ultima_apuracao?.valor_realizado ?? null
  const alvo         = meta.periodo_atual?.valor_alvo ?? null
  const progresso    = alvo && realizado !== null ? Math.min(100, (realizado / alvo) * 100) : null

  return (
    <Card className="relative overflow-hidden">
      {/* Farol lateral */}
      <div className={`absolute left-0 top-0 h-full w-1 ${FAROL_CLASSES[farol]}`} aria-hidden="true" />

      <CardHeader className="pb-2 pl-5">
        <div className="flex items-start justify-between gap-2">
          <CardTitle className="text-base">{meta.nome}</CardTitle>
          <Badge variant="outline" className="shrink-0">{meta.unidade}</Badge>
        </div>
      </CardHeader>

      <CardContent className="pl-5 space-y-3">
        {realizado !== null ? (
          <div className="text-2xl font-semibold tabular-nums">
            {formatValue(realizado, meta.unidade)}
          </div>
        ) : (
          <div className="text-sm text-muted-foreground">Aguardando apuração…</div>
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

        <Link
          href={`/jana/metas/${meta.id}`}
          className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
        >
          Ver detalhe <ExternalLink className="h-3 w-3" />
        </Link>
      </CardContent>
    </Card>
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
      tone: 'text-violet-500',
    },
    {
      icon: Clock,
      label: 'Última conversa',
      value: '—',
      hint: 'aguardando contexto',
      tone: 'text-sky-500',
    },
    {
      icon: Zap,
      label: 'Brain B hoje',
      value: '0/50',
      hint: 'orçamento diário',
      tone: 'text-amber-500',
    },
  ]

  return (
    <div className="grid gap-3 sm:grid-cols-3">
      {kpis.map(({ icon: Icon, label, value, hint, tone }) => (
        <Card key={label} className="border-muted/60">
          <CardContent className="flex items-center gap-3 p-4">
            <div className={`rounded-full bg-muted/40 p-2 ${tone}`}>
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
    <Card className="border-violet-500/20 bg-gradient-to-br from-violet-500/5 via-transparent to-fuchsia-500/5">
      <CardHeader className="pb-2">
        <div className="flex items-center gap-2">
          <Sparkles className="h-4 w-4 text-violet-500" />
          <CardTitle className="text-sm font-medium text-violet-700 dark:text-violet-300">
            Próxima ação sugerida
          </CardTitle>
        </div>
      </CardHeader>
      <CardContent className="pt-1">
        <p className="text-sm text-muted-foreground">
          Quando houver sinal claro nas metas, a Jana vai sugerir aqui o próximo passo prático — sem você precisar perguntar.
        </p>
        <div className="mt-3">
          <Link href="/jana">
            <Button size="sm" variant="outline" className="gap-2">
              <MessageSquare className="h-4 w-4" />
              Conversar agora
            </Button>
          </Link>
        </div>
      </CardContent>
    </Card>
  )
}

export default function Dashboard({ metas }: Props) {
  return (
    <>
      <div className="space-y-6 p-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              <Badge
                className="border-0 bg-gradient-to-r from-violet-600 via-fuchsia-500 to-pink-500 text-white shadow-sm"
                aria-label="Versão Jana V2"
              >
                <Sparkles className="mr-1 h-3 w-3" aria-hidden="true" />
                JANA V2
              </Badge>
              <span className="text-xs text-muted-foreground">Copiloto operacional</span>
            </div>
            <div>
              <h1 className="text-2xl font-semibold tracking-tight">Dashboard de Metas</h1>
              <p className="text-sm text-muted-foreground">
                {metas.length} {metas.length === 1 ? 'meta ativa' : 'metas ativas'} — visão consolidada do business
              </p>
            </div>
          </div>

          <Link href="/jana">
            <Button className="gap-2">
              <MessageSquare className="h-4 w-4" />
              Conversar com a Jana
            </Button>
          </Link>
        </div>

        <JanaKpiStrip />

        <ProximaAcaoCard />

        {metas.length === 0 ? (
          <Card className="border-dashed">
            <CardContent className="flex flex-col items-center justify-center gap-4 py-16 text-center">
              <div className="rounded-full bg-violet-500/10 p-4">
                <Sparkles className="h-10 w-10 text-violet-500" aria-hidden="true" />
              </div>
              <div className="space-y-1">
                <p className="text-base font-medium">Nada por aqui ainda</p>
                <p className="max-w-sm text-sm text-muted-foreground">
                  Pergunte algo à Jana — ela aprende o que importa pro seu business e cria metas com base no que conversamos.
                </p>
              </div>
              <Link href="/jana">
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
              <MetaCard key={meta.id} meta={meta} />
            ))}
          </div>
        )}
      </div>

      <FabJana contextRoute="/jana/dashboard" />
    </>
  )
}

Dashboard.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Copiloto — Dashboard" breadcrumbItems={[{ label: 'Copiloto' }, { label: 'Dashboard' }]}>
    {page}
  </AppShellV2>
)
