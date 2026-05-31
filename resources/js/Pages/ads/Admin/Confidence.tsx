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
import {
  ShieldCheck,
  BellRing,
  Eye,
  UserCheck,
  TrendingUp,
  TrendingDown,
  Minus,
  type LucideIcon,
} from 'lucide-react'

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

/**
 * Faixa de confiança por score — tokens semânticos (nunca cor crua: ADR UI-0013).
 * Cor NUNCA é o único sinal: cada faixa carrega `Icon` + `label` textual (daltonismo).
 * Faixa "atende" (≥0.70) usa o primary roxo canon (ADR 0190) — azul é proibido.
 */
function scoreTier(score: number): { label: string; Icon: LucideIcon; badge: string } {
  if (score >= 0.85) return { label: 'Alta',  Icon: ShieldCheck, badge: 'bg-success text-success-foreground' }
  if (score >= 0.70) return { label: 'Atende', Icon: ShieldCheck, badge: 'bg-primary text-primary-foreground' }
  if (score >= 0.50) return { label: 'Baixa', Icon: Eye,         badge: 'bg-warning text-warning-foreground' }
  return { label: 'Inicial', Icon: Minus, badge: 'bg-muted text-muted-foreground' }
}

/**
 * Nível HiTL — Badge DS (variant real) + ícone lucide + label.
 * variant existe em badge.tsx: default(roxo)|secondary|destructive|outline.
 * NÃO há variant success/warning → faixas verde/âmbar usam tokens semânticos
 * (`bg-success`/`bg-warning`) por className, mantendo zero cor crua.
 */
function hitlBadge(level: number): {
  label: string
  Icon: LucideIcon
  variant: 'default' | 'destructive' | 'outline'
  className: string
} {
  return [
    { label: 'HiTL-0 Autônomo',      Icon: ShieldCheck, variant: 'outline' as const, className: 'border-success/40 bg-success/10 text-success-foreground' },
    { label: 'HiTL-1 Notificação',   Icon: BellRing,    variant: 'default' as const, className: '' },
    { label: 'HiTL-2 Revisão',       Icon: Eye,         variant: 'outline' as const, className: 'border-warning/40 bg-warning/10 text-warning-foreground' },
    { label: 'HiTL-3 Humano decide', Icon: UserCheck,   variant: 'destructive' as const, className: '' },
  ][level] ?? { label: `HiTL-${level}`, Icon: Minus, variant: 'outline' as const, className: '' }
}

/**
 * Streak de aprovações/falhas consecutivas. Seta (ícone) + sinal +/− além da cor
 * semântica (`text-success-foreground`/`text-destructive`) — não depende só de cor.
 */
function ConsecutiveCell({ approvals, failures }: { approvals: number; failures: number }) {
  if (approvals > 0) {
    return (
      <span className="inline-flex items-center gap-0.5 text-success-foreground">
        <TrendingUp className="size-3" aria-hidden="true" />+{approvals}
      </span>
    )
  }
  if (failures > 0) {
    return (
      <span className="inline-flex items-center gap-0.5 text-destructive">
        <TrendingDown className="size-3" aria-hidden="true" />-{failures}
      </span>
    )
  }
  return <span className="text-muted-foreground">—</span>
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
            <>
              {/* Desktop ≥md: tabela com a11y (caption + scope) */}
              <div className="hidden md:block overflow-x-auto">
                <table className="w-full text-sm">
                  <caption className="sr-only">
                    Confiança do sistema por domínio e tipo de evento, com faixa de score, nível HiTL,
                    amostras, aprovações consecutivas e último outcome.
                  </caption>
                  <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                    <tr>
                      <th scope="col" className="px-4 py-3 text-left">Domínio</th>
                      <th scope="col" className="px-4 py-3 text-left">Tipo de evento</th>
                      <th scope="col" className="px-4 py-3 text-center">Score</th>
                      <th scope="col" className="px-4 py-3 text-center">HiTL</th>
                      <th scope="col" className="px-4 py-3 text-center">Amostras</th>
                      <th scope="col" className="px-4 py-3 text-center">Aprovações cons.</th>
                      <th scope="col" className="px-4 py-3 text-left">Último outcome</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {scores.map(s => {
                      const hitl = hitlBadge(s.hitl_level)
                      const tier = scoreTier(s.score)
                      return (
                        <tr key={`${s.domain}-${s.event_type}`} className="hover:bg-muted/30">
                          <th scope="row" className="px-4 py-2 text-left font-medium">{s.domain}</th>
                          <td className="px-4 py-2"><code className="text-xs">{s.event_type}</code></td>
                          <td className="px-4 py-2 text-center">
                            <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-mono tabular-nums ${tier.badge}`}>
                              <tier.Icon className="size-3" aria-hidden="true" />
                              {s.score.toFixed(3)}
                              <span className="font-sans font-medium">{tier.label}</span>
                            </span>
                          </td>
                          <td className="px-4 py-2 text-center">
                            <Badge variant={hitl.variant} className={`gap-1 ${hitl.className}`}>
                              <hitl.Icon className="size-3" aria-hidden="true" />
                              {hitl.label}
                            </Badge>
                          </td>
                          <td className="px-4 py-2 text-center text-muted-foreground tabular-nums">{s.sample_size}</td>
                          <td className="px-4 py-2 text-center tabular-nums">
                            <ConsecutiveCell approvals={s.consecutive_approvals} failures={s.consecutive_failures} />
                          </td>
                          <td className="px-4 py-2 text-xs text-muted-foreground">{s.last_outcome ?? '—'}</td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>

              {/* Mobile <md: card-stack (sem overflow horizontal cego) */}
              <ul className="md:hidden divide-y divide-border">
                {scores.map(s => {
                  const hitl = hitlBadge(s.hitl_level)
                  const tier = scoreTier(s.score)
                  return (
                    <li key={`${s.domain}-${s.event_type}`} className="p-4 space-y-2">
                      <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                          <div className="font-medium truncate">{s.domain}</div>
                          <code className="text-xs text-muted-foreground break-all">{s.event_type}</code>
                        </div>
                        <span className={`inline-flex shrink-0 items-center gap-1 px-2 py-0.5 rounded-md text-xs font-mono tabular-nums ${tier.badge}`}>
                          <tier.Icon className="size-3" aria-hidden="true" />
                          {s.score.toFixed(3)}
                          <span className="font-sans font-medium">{tier.label}</span>
                        </span>
                      </div>
                      <div className="flex items-center gap-2 flex-wrap">
                        <Badge variant={hitl.variant} className={`gap-1 ${hitl.className}`}>
                          <hitl.Icon className="size-3" aria-hidden="true" />
                          {hitl.label}
                        </Badge>
                        <span className="text-xs text-muted-foreground tabular-nums">{s.sample_size} amostras</span>
                        <span className="text-xs tabular-nums">
                          <ConsecutiveCell approvals={s.consecutive_approvals} failures={s.consecutive_failures} />
                        </span>
                      </div>
                      <div className="text-xs text-muted-foreground">
                        Último outcome: {s.last_outcome ?? '—'}
                      </div>
                    </li>
                  )
                })}
              </ul>
            </>
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
