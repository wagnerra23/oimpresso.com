// @governance
//   tela: /governance/audit
//   adrs: 0079 Art. 9 (Auditoria), 0084 (trigger MySQL append-only)

import React, { type ReactNode } from 'react'
import { router } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'

interface Entry {
  id: number
  user_id: number | null
  business_id: number | null
  endpoint: string
  tool_or_resource: string | null
  status: string
  duration_ms: number | null
  created_at: string
}

interface Actor {
  slug: string
  display_name: string
}

interface Props {
  entries: Entry[]
  kpis: {
    total: number
    errors: number
    unique_users: number
  }
  filters: {
    period: string
    actor: string | null
    endpoint: string | null
    status: string | null
  }
  available_endpoints: string[]
  available_actors: Actor[]
}

// Radix Select não aceita SelectItem com value="" — sentinela pro "Todos"
const ALL = '__all__'

function statusColor(status: string): string {
  if (status === 'ok') return 'bg-emerald-100 text-emerald-700 border-emerald-300'
  return 'bg-red-100 text-red-700 border-red-300'
}

const Audit: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({
  entries,
  kpis,
  filters,
  available_endpoints,
  available_actors,
}) => {
  const updateFilter = (key: string, value: string) => {
    router.get('/governance/audit', { ...filters, [key]: value || undefined }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    })
  }

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="search"
        title="Audit Log"
        description="mcp_audit_log forense (Constituição Art. 9). Append-only enforced via trigger MySQL (ADR 0084). Read-only — modificação é incidente P0."
      />

      <KpiGrid cols={3}>
        <KpiCard icon="layers"        tone="info"     label="Entries no período" value={kpis.total.toString()} />
        <KpiCard icon="alert-triangle" tone={kpis.errors > 0 ? 'warning' : 'success'} label="Errors" value={kpis.errors.toString()} />
        <KpiCard icon="users"         tone="info"     label="Users distintos"   value={kpis.unique_users.toString()} />
      </KpiGrid>

      {/* Filtros */}
      <Card>
        <CardContent className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
              <label className="block text-xs font-medium text-zinc-500 mb-1">Período</label>
              <Select value={filters.period} onValueChange={(v) => updateFilter('period', v)}>
                <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="1h">Última hora</SelectItem>
                  <SelectItem value="24h">Últimas 24h</SelectItem>
                  <SelectItem value="7d">Últimos 7d</SelectItem>
                  <SelectItem value="30d">Últimos 30d</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div>
              <label className="block text-xs font-medium text-zinc-500 mb-1">Actor</label>
              <Select
                value={filters.actor || ALL}
                onValueChange={(v) => updateFilter('actor', v === ALL ? '' : v)}
              >
                <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL}>Todos</SelectItem>
                  {available_actors.map((a) => (
                    <SelectItem key={a.slug} value={a.slug}>{a.display_name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div>
              <label className="block text-xs font-medium text-zinc-500 mb-1">Endpoint</label>
              <Select
                value={filters.endpoint || ALL}
                onValueChange={(v) => updateFilter('endpoint', v === ALL ? '' : v)}
              >
                <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL}>Todos</SelectItem>
                  {available_endpoints.map((ep) => (
                    <SelectItem key={ep} value={ep}>{ep}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div>
              <label className="block text-xs font-medium text-zinc-500 mb-1">Status</label>
              <Select
                value={filters.status || ALL}
                onValueChange={(v) => updateFilter('status', v === ALL ? '' : v)}
              >
                <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL}>Todos</SelectItem>
                  <SelectItem value="ok">OK</SelectItem>
                  <SelectItem value="error">Error</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Entries */}
      <Card>
        <CardContent className="p-0">
          {entries.length === 0 ? (
            <div className="p-6">
              <EmptyState icon="info" title="Sem entries" description="Sem registros no período selecionado com os filtros aplicados." />
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
                  <tr>
                    <th className="px-3 py-2 text-left font-medium">Quando</th>
                    <th className="px-3 py-2 text-left font-medium">User</th>
                    <th className="px-3 py-2 text-left font-medium">Endpoint</th>
                    <th className="px-3 py-2 text-left font-medium">Tool/Resource</th>
                    <th className="px-3 py-2 text-left font-medium">Status</th>
                    <th className="px-3 py-2 text-right font-medium">Duração</th>
                  </tr>
                </thead>
                <tbody>
                  {entries.map((e) => (
                    <tr key={e.id} className="border-b border-zinc-100 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-900">
                      <td className="px-3 py-2 text-xs text-zinc-500 font-mono">
                        {new Date(e.created_at).toLocaleString('pt-BR')}
                      </td>
                      <td className="px-3 py-2">{e.user_id ? `#${e.user_id}` : '—'}</td>
                      <td className="px-3 py-2 font-mono text-xs">{e.endpoint}</td>
                      <td className="px-3 py-2 text-xs text-zinc-500">{e.tool_or_resource || '—'}</td>
                      <td className="px-3 py-2">
                        <Badge variant="outline" className={statusColor(e.status)}>{e.status}</Badge>
                      </td>
                      <td className="px-3 py-2 text-right text-xs text-zinc-500">
                        {e.duration_ms ? `${e.duration_ms}ms` : '—'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      <p className="text-xs text-zinc-500 text-center">
        Limit 200 entries por query. Períodos longos podem truncar — refine filtros pra ver mais.
      </p>
    </div>
  )
}

Audit.layout = (page: ReactNode) => <AppShellV2 children={page} />

export default Audit
