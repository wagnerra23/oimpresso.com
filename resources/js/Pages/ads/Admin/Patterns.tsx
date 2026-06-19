// @ads
//   tela: /ads/admin/patterns
//   adrs: ARQ-0007 (Learning Loop), T15 PatternLearning + Wilson Score Interval

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'
import { Lightbulb, AlertTriangle, Lock } from 'lucide-react'

interface Pattern {
  id: number
  domain: string
  event_type: string
  description: string
  success_count: number
  total_count: number
  success_rate: number
  wilson_lower_bound: number
  is_promotion_ready: boolean
  is_hardcoded: boolean
  updated_at: string | null
}

interface Candidate {
  domain: string
  event_type: string
  success_count: number
  total_count: number
  success_rate: number
  wilson_lower_bound: number
  recommendation: string
}

interface Drift {
  pattern_id: number
  domain: string
  event_type: string
  rate_historic: number
  rate_recent: number
  sample_recent: number
  recommendation: string
}

interface Props {
  patterns: Pattern[]
  candidates: Candidate[]
  drifts: Drift[]
  kpis: {
    total_patterns: number
    candidates: number
    drifts: number
    hardcoded: number
  }
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)

function lbColor(lb: number): string {
  if (lb >= 0.80) return 'bg-emerald-600 text-white'
  if (lb >= 0.60) return 'bg-blue-600 text-white'
  if (lb >= 0.40) return 'bg-amber-600 text-white'
  return 'bg-zinc-300 text-zinc-700'
}

const Patterns: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ patterns, candidates, drifts, kpis }) => {
  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="zap"
        title="ADS — Padrões aprendidos"
        description="Tabela mcp_decision_patterns com Wilson Score Interval. Evita promoção por ruído de poucas amostras (3/3 ≠ confiável)."
      />

      <KpiGrid cols={4}>
        <KpiCard icon="layers"          tone="info"    label="Padrões totais"       value={num(kpis.total_patterns)} description="Pares (domínio×tipo)"/>
        <KpiCard icon="lightbulb"       tone="success" label="Candidatos a promoção" value={num(kpis.candidates)}     description="Wilson LB ≥ 0.80"/>
        <KpiCard icon="alert-triangle"  tone="danger"  label="Drifts detectados"    value={num(kpis.drifts)}        description="Taxa caiu >25pp"/>
        <KpiCard icon="lock"            tone="default" label="Já hardcoded"          value={num(kpis.hardcoded)}     description="Promovidos pra Policy"/>
      </KpiGrid>

      {/* Candidatos a promoção */}
      {candidates.length > 0 && (
        <Card className="border-success/30 bg-success-soft">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Lightbulb className="w-5 h-5 text-success-fg" />
              Padrões prontos pra promoção
              <Badge className="ml-auto bg-emerald-600">{candidates.length}</Badge>
            </CardTitle>
            <p className="text-sm text-muted-foreground mt-1">
              Estes padrões têm Wilson Score Interval LB ≥ 0.80 com ≥10 amostras.
              Considerar mover pra <code>ALLOW_BRAIN_A</code> em <code>PolicyEngine.php</code> (PR git).
            </p>
          </CardHeader>
          <CardContent>
            <ul className="space-y-3">
              {candidates.map((c, i) => (
                <li key={i} className="text-sm space-y-1">
                  <div className="flex items-center gap-2 flex-wrap">
                    <Badge variant="outline">{c.domain}</Badge>
                    <code className="text-xs">{c.event_type}</code>
                    <span className="text-muted-foreground">{c.success_count}/{c.total_count} sucessos</span>
                    <span className={`px-2 py-0.5 rounded font-mono text-xs ${lbColor(c.wilson_lower_bound)}`}>
                      LB {c.wilson_lower_bound.toFixed(3)}
                    </span>
                  </div>
                  <p className="text-xs text-muted-foreground pl-2 border-l-2 border-success/30">
                    {c.recommendation}
                  </p>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      )}

      {/* Drifts detectados */}
      {drifts.length > 0 && (
        <Card className="border-destructive/30 bg-destructive/5">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <AlertTriangle className="w-5 h-5 text-destructive" />
              Drifts detectados
              <Badge className="ml-auto" variant="destructive">{drifts.length}</Badge>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="space-y-2">
              {drifts.map((d, i) => (
                <li key={i} className="text-sm">
                  <div className="font-medium">{d.domain} · {d.event_type}</div>
                  <div className="text-xs text-muted-foreground">
                    Taxa histórica {(d.rate_historic * 100).toFixed(1)}% → recente {(d.rate_recent * 100).toFixed(1)}% ({d.sample_recent} amostras)
                  </div>
                  <p className="text-xs text-muted-foreground mt-1">{d.recommendation}</p>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      )}

      {/* Tabela de padrões */}
      <Card>
        <CardContent className="p-0">
          {patterns.length === 0 ? (
            <EmptyState
              icon="zap"
              title="Sem padrões aprendidos ainda"
              description={`Padrões aparecem quando decisões com outcome != cancelled forem registradas.
                Rode 'php artisan ads:learn-patterns' diariamente (já agendado 02:00).`}
            />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                  <tr>
                    <th className="px-4 py-3 text-left">Domínio</th>
                    <th className="px-4 py-3 text-left">Tipo evento</th>
                    <th className="px-4 py-3 text-center">Amostras</th>
                    <th className="px-4 py-3 text-center">Taxa naïve</th>
                    <th className="px-4 py-3 text-center">Wilson LB (95%)</th>
                    <th className="px-4 py-3 text-center">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {patterns.map(p => (
                    <tr key={p.id} className="hover:bg-muted/30">
                      <td className="px-4 py-2 font-medium">{p.domain}</td>
                      <td className="px-4 py-2"><code className="text-xs">{p.event_type}</code></td>
                      <td className="px-4 py-2 text-center tabular-nums">
                        {p.success_count}/{p.total_count}
                      </td>
                      <td className="px-4 py-2 text-center tabular-nums text-muted-foreground">
                        {(p.success_rate * 100).toFixed(1)}%
                      </td>
                      <td className="px-4 py-2 text-center">
                        <span className={`px-2 py-0.5 rounded text-xs font-mono ${lbColor(p.wilson_lower_bound)}`}>
                          {p.wilson_lower_bound.toFixed(3)}
                        </span>
                      </td>
                      <td className="px-4 py-2 text-center">
                        {p.is_hardcoded ? (
                          <Badge className="bg-zinc-700 text-white"><Lock className="w-3 h-3 mr-1 inline" /> Hardcoded</Badge>
                        ) : p.is_promotion_ready ? (
                          <Badge className="bg-emerald-600 text-white">Pronto</Badge>
                        ) : (
                          <Badge variant="outline">Aprendendo</Badge>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

Patterns.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Padrões aprendidos" breadcrumbItems={[{ label: 'ADS' }, { label: 'Padrões' }]}>
    {page}
  </AppShellV2>
)

export default Patterns
