// @governance
//   tela: /governance/drift
//   adrs: 0079 Art. 7 (Module Charter), 0086 (Fase 5 MVP)

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'

interface ReportItem {
  module: string
  undeclared: string[]
  undeclared_count: number
  total_actual: number
}

interface PersistedAlert {
  id: number
  category: string
  severity: string
  module: string | null
  detail: string
  created_at: string
}

interface Props {
  kpis: {
    total_drift: number
    modules_with_drift: number
    modules_without_scope: number
    modules_total: number
  }
  report: ReportItem[]
  modules_without_scope: string[]
  persisted_alerts: PersistedAlert[]
}

const GH_BLOB = 'https://github.com/wagnerra23/oimpresso.com/blob/main'

// Severidade -> variant de Badge do DS (cor via token, nunca hex/amber cru).
function severityVariant(severity: string): 'destructive' | 'secondary' | 'outline' {
  const s = severity.toLowerCase()
  if (s === 'critical' || s === 'high' || s === 'error') return 'destructive'
  if (s === 'warning' || s === 'medium') return 'secondary'
  return 'outline'
}

const DriftAlerts: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({
  kpis,
  report,
  modules_without_scope,
  persisted_alerts,
}) => {
  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="alert-triangle"
        title="Drift Alerts"
        description="Constituição Art. 7 — controllers fora do SCOPE.md.contains[] declarado. Mesma lógica do bin/check-scope.php em runtime. Drift detection cron (Enforcement #5) também persiste em mcp_alertas."
      />

      <KpiGrid cols={4}>
        <KpiCard
          icon="alert-triangle"
          tone={kpis.total_drift > 0 ? 'warning' : 'success'}
          label="Controllers em drift"
          value={kpis.total_drift.toString()}
        />
        <KpiCard
          icon="folder"
          tone={kpis.modules_with_drift > 0 ? 'warning' : 'success'}
          label="Módulos com drift"
          value={kpis.modules_with_drift.toString()}
          description={`de ${kpis.modules_total} total`}
        />
        <KpiCard
          icon="file-x"
          tone={kpis.modules_without_scope > 0 ? 'warning' : 'success'}
          label="Sem SCOPE.md"
          value={kpis.modules_without_scope.toString()}
          description="Fase 3.4 pendente"
        />
        <KpiCard
          icon="check-circle"
          tone="info"
          label="Total módulos"
          value={kpis.modules_total.toString()}
        />
      </KpiGrid>

      {/* Drift detected runtime */}
      <Card>
        <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
          <CardTitle className="text-lg">Drift detectado em runtime</CardTitle>
          {report.length > 0 && (
            <Badge variant="destructive">{report.length} módulos</Badge>
          )}
        </CardHeader>
        <CardContent>
          {report.length === 0 ? (
            <EmptyState icon="check-circle" title="Sem drift" description="Todos controllers declarados em seus SCOPE.md." />
          ) : (
            <div className="space-y-3">
              {report.map((item) => (
                <Alert key={item.module} variant="destructive">
                  <AlertTitle className="flex items-center justify-between gap-2">
                    <span className="font-mono">Modules/{item.module}</span>
                    <Badge variant="destructive">
                      {item.undeclared_count} de {item.total_actual} controllers
                    </Badge>
                  </AlertTitle>
                  <AlertDescription>
                    <ul className="text-sm space-y-1 ml-4 list-none">
                      {item.undeclared.map((ctrl) => (
                        <li key={ctrl} className="font-mono">
                          → {ctrl}
                        </li>
                      ))}
                    </ul>
                    <p className="text-xs text-muted-foreground mt-1">
                      Adicione em <code className="font-mono">SCOPE.md.contains[]</code> OU mova pro módulo correto OU declare em <code className="font-mono">drift_alerts[]</code>
                    </p>
                    <div className="flex flex-wrap gap-2 mt-2">
                      <Button asChild variant="outline" size="sm">
                        <a
                          href={`${GH_BLOB}/Modules/${item.module}/SCOPE.md`}
                          target="_blank"
                          rel="noreferrer"
                        >
                          Abrir SCOPE.md
                        </a>
                      </Button>
                      <Button asChild variant="ghost" size="sm">
                        <a
                          href={`${GH_BLOB}/Modules/${item.module}/Http/Controllers`}
                          target="_blank"
                          rel="noreferrer"
                        >
                          Ver controllers
                        </a>
                      </Button>
                    </div>
                  </AlertDescription>
                </Alert>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Módulos sem SCOPE.md */}
      {modules_without_scope.length > 0 && (
        <Card>
          <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
            <CardTitle className="text-lg">Módulos sem SCOPE.md</CardTitle>
            <Badge variant="secondary">{modules_without_scope.length}</Badge>
          </CardHeader>
          <CardContent>
            <div className="flex flex-wrap gap-2">
              {modules_without_scope.map((m) => (
                <Button key={m} asChild variant="outline" size="sm">
                  <a
                    href={`${GH_BLOB}/Modules/${m}`}
                    target="_blank"
                    rel="noreferrer"
                    className="font-mono"
                  >
                    {m}
                  </a>
                </Button>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Alertas persistidos (cron) */}
      <Card>
        <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
          <CardTitle className="text-lg">Histórico (mcp_alertas — últimos 30d)</CardTitle>
          {persisted_alerts.length > 0 && (
            <Badge variant="outline">{persisted_alerts.length}</Badge>
          )}
        </CardHeader>
        <CardContent>
          {persisted_alerts.length === 0 ? (
            <EmptyState
              icon="info"
              title="Sem alertas persistidos"
              description="Drift detection cron job vai persistir aqui. Não está rodando ainda — Fase 3.5 pendente."
            />
          ) : (
            <ul className="space-y-2">
              {persisted_alerts.map((alert) => (
                <li key={alert.id} className="flex items-start gap-3 text-sm border-b border-border pb-2 last:border-0">
                  <Badge variant={severityVariant(alert.severity)}>
                    {alert.severity}
                  </Badge>
                  <div className="flex-1">
                    <div className="font-mono text-xs text-muted-foreground">{alert.module || '—'}</div>
                    <div>{alert.detail}</div>
                  </div>
                  <span className="text-xs text-muted-foreground shrink-0">
                    {new Date(alert.created_at).toLocaleDateString('pt-BR')}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

DriftAlerts.layout = (page: ReactNode) => <AppShellV2 children={page} />

export default DriftAlerts
