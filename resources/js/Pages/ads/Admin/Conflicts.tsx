// @ads
//   tela: /ads/admin/conflicts
//   adrs: ARQ-0010 (Governance — conflitos)

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'
import { AlertTriangle, FileWarning, TrendingDown, Users } from 'lucide-react'

interface FileLockConflict {
  file: string
  decision_a: { id: number; event_type: string; destination: string }
  decision_b: { id: number; event_type: string; destination: string }
  gap_minutes: number
  recommendation: string
}

interface DriftConflict {
  pattern_id: number
  domain: string
  event_type: string
  rate_historic: number
  rate_recent: number
  sample_recent: number
  recommendation: string
}

interface JudgmentConflict {
  decision_id: number
  event_type: string
  domain: string
  human_action: string
  ai_score: number
  ai_confidence: number
  ai_issues: string[]
  created_at: string
  recommendation: string
}

interface Props {
  file_lock_conflicts: FileLockConflict[]
  drift_conflicts:     DriftConflict[]
  judgment_conflicts:  JudgmentConflict[]
  kpis: { file_lock: number; drift: number; human_ai: number; total: number }
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)

const Conflicts: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({
  file_lock_conflicts, drift_conflicts, judgment_conflicts, kpis,
}) => {
  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="alert-triangle"
        title="ADS — Conflitos"
        description="Detector automático de 3 tipos de conflito. Sem essa tela, sistema quebra silenciosamente."
      />

      <KpiGrid cols={4}>
        <KpiCard icon="alert-triangle" tone="danger"  label="Total de conflitos"  value={num(kpis.total)}     description="7 dias" />
        <KpiCard icon="file-warning"   tone="warning" label="File lock"            value={num(kpis.file_lock)} description="Decisões concorrentes" />
        <KpiCard icon="trending-down"  tone="warning" label="Drift de padrão"      value={num(kpis.drift)}     description="Taxa caiu >25pp" />
        <KpiCard icon="users"          tone="info"    label="Humano × IA"          value={num(kpis.human_ai)}  description="Wagner aprovou, IA<50" />
      </KpiGrid>

      {kpis.total === 0 && (
        <Card>
          <CardContent className="p-0">
            <EmptyState
              icon="check-circle-2"
              title="Nenhum conflito detectado"
              description="Sistema operando de forma consistente. Esta tela é monitorada continuamente — assim que detector encontrar discrepância, você verá aqui."
            />
          </CardContent>
        </Card>
      )}

      {/* File lock conflicts */}
      {file_lock_conflicts.length > 0 && (
        <Card className="border-warning/30 bg-warning-soft">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileWarning className="w-5 h-5 text-warning-fg" />
              File lock — decisões concorrentes
              <Badge className="ml-auto bg-amber-600">{file_lock_conflicts.length}</Badge>
            </CardTitle>
            <p className="text-sm text-muted-foreground mt-1">
              Duas decisões tentaram modificar o mesmo arquivo dentro de 1 hora. Mutex evita execução simultânea, mas convém investigar duplicidade.
            </p>
          </CardHeader>
          <CardContent>
            <ul className="space-y-3">
              {file_lock_conflicts.map((c, i) => (
                <li key={i} className="text-sm space-y-1 border-l-2 border-amber-300 pl-3">
                  <div className="flex items-center gap-2 flex-wrap">
                    <code className="text-xs bg-zinc-100 px-1.5 py-0.5 rounded">{c.file}</code>
                    <Badge variant="outline">{c.gap_minutes}min entre eventos</Badge>
                  </div>
                  <div className="flex items-center gap-2 flex-wrap text-xs">
                    <Link href={`/ads/admin/decisoes/${c.decision_a.id}`} className="text-primary hover:underline">
                      #{c.decision_a.id} {c.decision_a.event_type} → {c.decision_a.destination}
                    </Link>
                    <span className="text-muted-foreground">⟷</span>
                    <Link href={`/ads/admin/decisoes/${c.decision_b.id}`} className="text-primary hover:underline">
                      #{c.decision_b.id} {c.decision_b.event_type} → {c.decision_b.destination}
                    </Link>
                  </div>
                  <p className="text-xs text-muted-foreground">{c.recommendation}</p>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      )}

      {/* Drift conflicts */}
      {drift_conflicts.length > 0 && (
        <Card className="border-destructive/30 bg-destructive/5">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <TrendingDown className="w-5 h-5 text-destructive" />
              Drift — padrão degradando
              <Badge className="ml-auto" variant="destructive">{drift_conflicts.length}</Badge>
            </CardTitle>
            <p className="text-sm text-muted-foreground mt-1">
              Taxa recente caiu mais de 25pp em relação à histórica. Pode indicar contexto mudou (Laravel update, schema novo, etc).
            </p>
          </CardHeader>
          <CardContent>
            <ul className="space-y-2">
              {drift_conflicts.map((d, i) => (
                <li key={i} className="text-sm border-l-2 border-destructive pl-3">
                  <div className="font-medium">{d.domain} · <code className="text-xs">{d.event_type}</code></div>
                  <div className="text-xs text-muted-foreground">
                    Histórico {(d.rate_historic * 100).toFixed(1)}% → recente {(d.rate_recent * 100).toFixed(1)}% ({d.sample_recent} amostras)
                  </div>
                  <p className="text-xs text-muted-foreground mt-1">{d.recommendation}</p>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      )}

      {/* Human × AI judgment */}
      {judgment_conflicts.length > 0 && (
        <Card className="border-blue-500/30 bg-blue-500/5">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Users className="w-5 h-5 text-blue-600" />
              Humano × IA — julgamentos divergentes
              <Badge className="ml-auto bg-blue-600">{judgment_conflicts.length}</Badge>
            </CardTitle>
            <p className="text-sm text-muted-foreground mt-1">
              Wagner aprovou mas ReviewerAgent deu nota baixa. Sinal valioso: ou Wagner viu algo que IA não percebe, ou prompt do Reviewer está calibrado errado.
            </p>
          </CardHeader>
          <CardContent>
            <ul className="space-y-3">
              {judgment_conflicts.map((c, i) => (
                <li key={i} className="text-sm space-y-1 border-l-2 border-blue-300 pl-3">
                  <div className="flex items-center gap-2 flex-wrap">
                    <Link href={`/ads/admin/decisoes/${c.decision_id}`} className="text-primary hover:underline font-medium">
                      #{c.decision_id} {c.event_type}
                    </Link>
                    <Badge variant="outline">{c.domain}</Badge>
                    <Badge className="bg-emerald-600 text-white text-xs">Humano: {c.human_action}</Badge>
                    <Badge className="bg-red-600 text-white text-xs">IA: {c.ai_score}/100</Badge>
                  </div>
                  {c.ai_issues.length > 0 && (
                    <div className="text-xs">
                      <span className="text-muted-foreground">IA apontou: </span>
                      {c.ai_issues.slice(0, 3).map((iss, j) => (
                        <span key={j} className="text-destructive-fg">{j > 0 && ', '}{iss}</span>
                      ))}
                    </div>
                  )}
                  <p className="text-xs text-muted-foreground">{c.recommendation}</p>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      )}
    </div>
  )
}

Conflicts.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Conflitos" breadcrumbItems={[{ label: 'ADS' }, { label: 'Conflitos' }]}>
    {page}
  </AppShellV2>
)

export default Conflicts
