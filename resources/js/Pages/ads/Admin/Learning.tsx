// @ads
//   tela: /ads/admin/learning
//   adrs: ARQ-0007 (Learning Loop) — pipeline visual

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Deferred, Link } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Skeleton } from '@/Components/ui/skeleton'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import { Icon } from '@/Components/Icon'
import { ArrowRight } from 'lucide-react'

interface Stage {
  key: string
  name: string
  description: string
  count: number
  filter_url: string | null
  icon: string
  color: string
}

interface ThroughputPoint {
  hora: string
  total: number
  sucessos: number
  rejeitadas: number
}

interface Kpis {
  janela_horas: number
  eventos_24h: number
  taxa_review: number
  taxa_pattern: number
  pendencia_humana: number
}

interface Props {
  // vêm via Inertia::defer (LearningController) — undefined no first render
  stages?: Stage[]
  throughput?: ThroughputPoint[]
  kpis?: Kpis
}

const KPIS_FALLBACK: Kpis = {
  janela_horas: 0, eventos_24h: 0, taxa_review: 0, taxa_pattern: 0, pendencia_humana: 0,
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)

// Colapsa as ~9 cores que o backend manda em `stage.color` em 4 tons semânticos
// do DS (tokens) — sem 9 cores Tailwind cruas. neutral/progress/success/attention.
const colorMap: Record<string, string> = {
  zinc:    'bg-muted text-muted-foreground border-border',
  blue:    'bg-primary/10 text-primary border-primary/30',
  indigo:  'bg-primary/10 text-primary border-primary/30',
  purple:  'bg-primary/10 text-primary border-primary/30',
  amber:   'bg-warning/10 text-warning-foreground border-warning/30',
  orange:  'bg-warning/10 text-warning-foreground border-warning/30',
  yellow:  'bg-warning/10 text-warning-foreground border-warning/30',
  emerald: 'bg-success/10 text-success-foreground border-success/30',
  green:   'bg-success/10 text-success-foreground border-success/30',
}

const Learning: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ stages, throughput, kpis }) => {
  const k = kpis ?? KPIS_FALLBACK
  const stageList = stages ?? []
  const throughputList = throughput ?? []

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-6">
      <PageHeader
        icon="repeat"
        title="ADS — Learning Pipeline"
        description="Visualização do loop completo: evento → classificação → roteamento → execução → review → pattern → promoção. Cada estágio é clicável."
      />

      <Deferred
        data={['stages', 'throughput', 'kpis']}
        fallback={(
          <div className="space-y-6">
            <Skeleton className="h-24 w-full" />
            <Skeleton className="h-64 w-full" />
          </div>
        )}
      >
      <KpiGrid cols={4}>
        <KpiCard
          icon="activity"
          tone="info"
          label={`Eventos (${k.janela_horas}h)`}
          value={num(k.eventos_24h)}
          description="Janela de análise"
        />
        <KpiCard
          icon="user-check"
          tone="warning"
          label="Aguardando humano"
          value={num(k.pendencia_humana)}
          description="HiTL-2/3 abertos"
        />
        <KpiCard
          icon="star"
          tone="default"
          label="% Reviewed"
          value={`${k.taxa_review.toFixed(1)}%`}
          description="ReviewerAgent rodou"
        />
        <KpiCard
          icon="zap"
          tone="success"
          label="% Pattern registrado"
          value={`${k.taxa_pattern.toFixed(1)}%`}
          description="Wilson Score atualizado"
        />
      </KpiGrid>

      {/* Pipeline visual — vertical no mobile, horizontal flow no desktop */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Loop do ADS — últimas 24h</CardTitle>
          <p className="text-sm text-muted-foreground">
            Cada estágio mostra quantas decisões passaram por ele. Setas indicam fluxo. Clique pra filtrar.
          </p>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            {stageList.map((stage, idx) => {
              const colorClass = colorMap[stage.color] ?? colorMap.zinc
              const isClickable = !!stage.filter_url

              const card = (
                <div
                  key={stage.key}
                  className={`border rounded-lg p-4 ${colorClass} ${isClickable ? 'hover:shadow-md cursor-pointer transition-shadow' : ''}`}
                >
                  <div className="flex items-center gap-2 mb-2">
                    <Icon name={stage.icon} size={20} />
                    <span className="text-xs font-mono opacity-60">[{idx + 1}]</span>
                  </div>
                  <div className="text-2xl font-bold tabular-nums">{num(stage.count)}</div>
                  <div className="text-sm font-medium mt-1">{stage.name}</div>
                  <div className="text-xs opacity-70 mt-1 leading-tight">{stage.description}</div>
                </div>
              )
              return isClickable && stage.filter_url
                ? <Link key={stage.key} href={stage.filter_url}>{card}</Link>
                : card
            })}
          </div>
        </CardContent>
      </Card>

      {/* Throughput por hora */}
      {throughputList.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Throughput por hora (últimas 24h)</CardTitle>
          </CardHeader>
          <CardContent>
            <ThroughputChart data={throughputList} />
          </CardContent>
        </Card>
      )}
      </Deferred>

      {/* Diagrama do loop com setas (legend) */}
      <Card className="bg-muted/30">
        <CardHeader>
          <CardTitle className="text-base">Como o loop fecha</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="text-sm text-muted-foreground space-y-2">
            <div className="flex items-center gap-2 flex-wrap">
              <Badge variant="outline">Capturado</Badge>
              <ArrowRight className="w-4 h-4" />
              <Badge variant="outline">Classificado</Badge>
              <ArrowRight className="w-4 h-4" />
              <Badge variant="outline">Roteado</Badge>
              <ArrowRight className="w-4 h-4" />
              <Badge variant="outline">Executado</Badge>
              <ArrowRight className="w-4 h-4" />
              <Badge variant="outline">Reviewed</Badge>
              <ArrowRight className="w-4 h-4" />
              <Badge variant="outline">Pattern</Badge>
              <ArrowRight className="w-4 h-4" />
              <Badge variant="outline">Promoted</Badge>
            </div>
            <p className="mt-3">
              <strong>Loop fecha quando:</strong> Wilson Score Lower Bound ≥ 0.80 com ≥10 amostras
              → cria task pendente Wagner com proposta de mover event_type pra <code>ALLOW_BRAIN_A</code>
              em <code>PolicyEngine.php</code> (PR git).
            </p>
            <p>
              <strong>Drift detection:</strong> taxa recente caiu &gt;25pp da histórica → alerta Wagner
              em <Link href="/ads/admin/skills" className="underline">Skills</Link>.
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

function ThroughputChart({ data }: { data: ThroughputPoint[] }) {
  const max = Math.max(1, ...data.map(d => d.total))
  return (
    <div className="space-y-1">
      {data.map(d => {
        const w = (d.total / max) * 100
        const sw = (d.sucessos / max) * 100
        const rw = (d.rejeitadas / max) * 100
        return (
          <div key={d.hora} className="flex items-center gap-2 text-xs">
            <div className="w-32 font-mono text-muted-foreground tabular-nums">{d.hora.slice(11)}</div>
            <div className="flex-1 h-5 relative bg-muted/30 rounded overflow-hidden" role="img" aria-label={`${d.sucessos} sucessos, ${d.rejeitadas} rejeitadas de ${d.total}`}>
              <div className="absolute h-full bg-success" style={{ width: `${sw}%` }} title={`${d.sucessos} sucessos`} />
              <div className="absolute h-full bg-destructive" style={{ left: `${sw}%`, width: `${rw}%` }} title={`${d.rejeitadas} rejeitadas`} />
              <div className="absolute h-full bg-muted-foreground/40" style={{ left: `${sw + rw}%`, width: `${w - sw - rw}%` }} title="pendentes/outros" />
            </div>
            <div className="w-12 text-right tabular-nums font-medium">{d.total}</div>
          </div>
        )
      })}
    </div>
  )
}

Learning.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Learning Pipeline" breadcrumbItems={[{ label: 'ADS' }, { label: 'Learning Pipeline' }]}>
    {page}
  </AppShellV2>
)

export default Learning
