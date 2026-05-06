// @governance
//   tela: /governance/drift
//   adrs: 0079 Art. 7 (Module Charter), 0086 (Fase 5 MVP)

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
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
        <CardContent className="p-4">
          <h3 className="text-lg font-semibold mb-3">Drift detectado em runtime</h3>
          {report.length === 0 ? (
            <EmptyState icon="check-circle" title="Sem drift" description="Todos controllers declarados em seus SCOPE.md." />
          ) : (
            <div className="space-y-3">
              {report.map((item) => (
                <div key={item.module} className="border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950 rounded-md p-3">
                  <div className="flex items-center justify-between mb-2">
                    <h4 className="font-mono font-semibold">Modules/{item.module}</h4>
                    <Badge variant="outline" className="bg-amber-100 text-amber-700">
                      {item.undeclared_count} de {item.total_actual} controllers
                    </Badge>
                  </div>
                  <ul className="text-sm space-y-1 ml-4">
                    {item.undeclared.map((ctrl) => (
                      <li key={ctrl} className="font-mono text-amber-800 dark:text-amber-300">
                        → {ctrl}
                      </li>
                    ))}
                  </ul>
                  <div className="text-xs text-zinc-600 dark:text-zinc-400 mt-2">
                    Adicione em <code className="font-mono">SCOPE.md.contains[]</code> OU mova pro módulo correto OU declare em <code className="font-mono">drift_alerts[]</code>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Módulos sem SCOPE.md */}
      {modules_without_scope.length > 0 && (
        <Card>
          <CardContent className="p-4">
            <h3 className="text-lg font-semibold mb-3 text-amber-700">
              Módulos sem SCOPE.md ({modules_without_scope.length})
            </h3>
            <div className="flex flex-wrap gap-2">
              {modules_without_scope.map((m) => (
                <Badge key={m} variant="outline" className="bg-zinc-100 text-zinc-700 font-mono">
                  {m}
                </Badge>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Alertas persistidos (cron) */}
      <Card>
        <CardContent className="p-4">
          <h3 className="text-lg font-semibold mb-3">
            Histórico (mcp_alertas — últimos 30d)
          </h3>
          {persisted_alerts.length === 0 ? (
            <EmptyState
              icon="info"
              title="Sem alertas persistidos"
              description="Drift detection cron job vai persistir aqui. Não está rodando ainda — Fase 3.5 pendente."
            />
          ) : (
            <ul className="space-y-2">
              {persisted_alerts.map((alert) => (
                <li key={alert.id} className="flex items-start gap-3 text-sm border-b border-zinc-100 dark:border-zinc-800 pb-2 last:border-0">
                  <Badge variant="outline" className="bg-amber-100 text-amber-700">
                    {alert.severity}
                  </Badge>
                  <div className="flex-1">
                    <div className="font-mono text-xs text-zinc-500">{alert.module || '—'}</div>
                    <div>{alert.detail}</div>
                  </div>
                  <span className="text-xs text-zinc-500 shrink-0">
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
