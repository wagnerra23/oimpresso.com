import React from 'react'
import AppShell from '@/Layouts/AppShell'
import { Head, Link } from '@inertiajs/react'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { MessageSquare, TrendingUp, TrendingDown, Minus, ExternalLink } from 'lucide-react'
import FabCopiloto from './components/FabCopiloto'

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
          <div className="text-2xl font-bold tabular-nums">
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
          href={`/copiloto/metas/${meta.id}`}
          className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
        >
          Ver detalhe <ExternalLink className="h-3 w-3" />
        </Link>
      </CardContent>
    </Card>
  )
}

export default function Dashboard({ metas }: Props) {
  return (
    <>
      <Head title="Copiloto — Dashboard" />

      <div className="space-y-6 p-6">
        <div className="flex items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold">Dashboard de Metas</h1>
            <p className="text-sm text-muted-foreground">
              {metas.length} {metas.length === 1 ? 'meta ativa' : 'metas ativas'}
            </p>
          </div>

          <Link href="/copiloto">
            <Button className="gap-2">
              <MessageSquare className="h-4 w-4" />
              Conversar com Copiloto
            </Button>
          </Link>
        </div>

        {metas.length === 0 ? (
          <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
            <MessageSquare className="h-12 w-12 text-muted-foreground/50" />
            <p className="text-muted-foreground">Nenhuma meta ativa. Converse com o Copiloto para criar a primeira.</p>
            <Link href="/copiloto">
              <Button>Iniciar conversa</Button>
            </Link>
          </div>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {metas.map(meta => (
              <MetaCard key={meta.id} meta={meta} />
            ))}
          </div>
        )}
      </div>

      <FabCopiloto contextRoute="/copiloto/dashboard" />
    </>
  )
}

Dashboard.layout = (page: React.ReactNode) => <AppShell>{page}</AppShell>
