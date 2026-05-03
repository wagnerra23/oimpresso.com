// @ads
//   tela: /ads/admin/confidence
//   adrs: ARQ-0005 (Confidence Engine), ARQ-0008 (HiTL progressivo)

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'

interface Score {
  domain: string
  event_type: string
  score: number
  sample_size: number
  hitl_level: number
  last_outcome: string | null
  consecutive_approvals: number
  consecutive_failures: number
  updated_at: string
}

interface Props {
  scores: Score[]
  kpis: {
    total_pares: number
    autonomos_brain_a: number
    media_score: number
    sample_total: number
  }
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)

function scoreColor(score: number): string {
  if (score >= 0.85) return 'bg-emerald-600 text-white'
  if (score >= 0.70) return 'bg-blue-600 text-white'
  if (score >= 0.50) return 'bg-amber-600 text-white'
  return 'bg-zinc-300 text-zinc-700'
}

function hitlBadge(level: number): { label: string; color: string } {
  return [
    { label: 'HiTL-0 Autônomo',     color: 'bg-emerald-100 text-emerald-700 border-emerald-300' },
    { label: 'HiTL-1 Notificação',  color: 'bg-blue-100 text-blue-700 border-blue-300' },
    { label: 'HiTL-2 Revisão',      color: 'bg-amber-100 text-amber-700 border-amber-300' },
    { label: 'HiTL-3 Humano decide', color: 'bg-red-100 text-red-700 border-red-300' },
  ][level] ?? { label: `HiTL-${level}`, color: '' }
}

const Confidence: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ scores, kpis }) => {
  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="trending-up"
        title="ADS — Confidence Engine"
        description="Confiança do sistema por (domínio × tipo de evento). Sobe com acertos, cai com modificações/rejeições. Quando supera 0.70 e Policy permite, Brain A executa autônomo."
      />

      <KpiGrid cols={4}>
        <KpiCard
          icon="layers"
          tone="info"
          label="Pares (domínio × tipo)"
          value={num(kpis.total_pares)}
          description="Combinações conhecidas"
        />
        <KpiCard
          icon="zap"
          tone="success"
          label="Autônomos (HiTL-0)"
          value={num(kpis.autonomos_brain_a)}
          description="Brain A executa sozinho"
        />
        <KpiCard
          icon="bar-chart-3"
          tone="default"
          label="Score médio"
          value={kpis.media_score.toFixed(3)}
          description="Inicial 0.500"
        />
        <KpiCard
          icon="activity"
          tone="default"
          label="Total de execuções"
          value={num(kpis.sample_total)}
          description="Janela 20 últimas/par"
        />
      </KpiGrid>

      <Card>
        <CardContent className="p-0">
          {scores.length === 0 ? (
            <EmptyState
              icon="trending-up"
              title="Ainda não há scores"
              description="Quando você aprovar/rejeitar decisões, o ConfidenceEngine começa a registrar. Cada decisão move o score em ±0.05."
            />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-4 py-3 text-left">Domínio</th>
                    <th className="px-4 py-3 text-left">Tipo de evento</th>
                    <th className="px-4 py-3 text-center">Score</th>
                    <th className="px-4 py-3 text-center">HiTL</th>
                    <th className="px-4 py-3 text-center">Amostras</th>
                    <th className="px-4 py-3 text-center">Aprovações cons.</th>
                    <th className="px-4 py-3 text-left">Último outcome</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {scores.map(s => {
                    const hitl = hitlBadge(s.hitl_level)
                    return (
                      <tr key={`${s.domain}-${s.event_type}`} className="hover:bg-muted/30">
                        <td className="px-4 py-2 font-medium">{s.domain}</td>
                        <td className="px-4 py-2"><code className="text-xs">{s.event_type}</code></td>
                        <td className="px-4 py-2 text-center">
                          <span className={`px-2 py-0.5 rounded-md text-xs font-mono tabular-nums ${scoreColor(s.score)}`}>
                            {s.score.toFixed(3)}
                          </span>
                        </td>
                        <td className="px-4 py-2 text-center">
                          <Badge variant="outline" className={hitl.color}>{hitl.label}</Badge>
                        </td>
                        <td className="px-4 py-2 text-center text-muted-foreground tabular-nums">{s.sample_size}</td>
                        <td className="px-4 py-2 text-center tabular-nums">
                          {s.consecutive_approvals > 0 && (
                            <span className="text-emerald-600">+{s.consecutive_approvals}</span>
                          )}
                          {s.consecutive_failures > 0 && (
                            <span className="text-destructive">-{s.consecutive_failures}</span>
                          )}
                          {s.consecutive_approvals === 0 && s.consecutive_failures === 0 && (
                            <span className="text-muted-foreground">—</span>
                          )}
                        </td>
                        <td className="px-4 py-2 text-xs text-muted-foreground">{s.last_outcome ?? '—'}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

Confidence.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Confidence Engine" breadcrumbItems={[{ label: 'ADS' }, { label: 'Confidence' }]}>
    {page}
  </AppShellV2>
)

export default Confidence
