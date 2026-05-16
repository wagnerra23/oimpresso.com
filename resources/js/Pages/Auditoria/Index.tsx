// @auditoria
//   tela: /auditoria
//   adrs: 0079 Art. 9 (Auditoria activity_log), 0127 (Modules/Auditoria UI + undo)
//   runbook: memory/requisitos/Auditoria/SPEC.md US-AUDIT-007/010

import React, { type ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import PageHeader from '@/Components/shared/PageHeader'
import EmptyState from '@/Components/shared/EmptyState'

interface Activity {
  id: number
  log_name: string | null
  description: string
  subject_type: string | null
  subject_id: number | null
  causer_type: string | null
  causer_id: number | null
  properties: Record<string, unknown> | null
  business_id: number | null
  created_at: string
}

interface Filters {
  period?: string
  log_name?: string | null
  subject_type?: string | null
  causer_id?: number | null
}

interface Paginator<T> {
  data: T[]
  current_page?: number
  last_page?: number
  total?: number
}

interface Props {
  activities?: Activity[] | Paginator<Activity> | null
  filters?: Filters
}

function normalizeActivities(input: Props['activities']): Activity[] {
  if (!input) return []
  if (Array.isArray(input)) return input
  if (typeof input === 'object' && 'data' in input && Array.isArray(input.data)) {
    return input.data
  }
  return []
}

function logNameColor(logName: string | null): string {
  if (!logName) return 'bg-zinc-100 text-zinc-700 border-zinc-300'
  if (logName.startsWith('sales')) return 'bg-emerald-100 text-emerald-700 border-emerald-300'
  if (logName.startsWith('financeiro')) return 'bg-sky-100 text-sky-700 border-sky-300'
  if (logName.startsWith('whatsapp')) return 'bg-green-100 text-green-700 border-green-300'
  if (logName.startsWith('crm')) return 'bg-amber-100 text-amber-700 border-amber-300'
  if (logName.includes('mfg') || logName.startsWith('manufacturing')) return 'bg-indigo-100 text-indigo-700 border-indigo-300'
  return 'bg-zinc-100 text-zinc-700 border-zinc-300'
}

function AuditoriaIndex({ activities, filters = {} }: Props): React.ReactElement {
  const rows = normalizeActivities(activities)
  const totalAttr = activities && !Array.isArray(activities) && 'total' in activities ? activities.total : rows.length

  return (
    <>
      <PageHeader
        title="Auditoria"
        subtitle="Log de atividades do sistema — Constituição Art. 9 (activity_log)"
        breadcrumbs={[{ label: 'Auditoria' }]}
      />

      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm flex items-center justify-between">
            <span>Atividades recentes ({totalAttr})</span>
            {filters.period && (
              <Badge className="bg-zinc-100 text-zinc-700 border-zinc-300">{filters.period}</Badge>
            )}
          </CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          {rows.length === 0 ? (
            <div className="p-6">
              <EmptyState
                icon="ShieldCheck"
                title="Nenhuma atividade encontrada"
                description="Quando usuários do sistema executarem ações, elas aparecem aqui."
              />
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead className="bg-zinc-50 border-b border-zinc-200">
                <tr className="text-left">
                  <th className="px-4 py-2 font-semibold">Quando</th>
                  <th className="px-4 py-2 font-semibold">Log</th>
                  <th className="px-4 py-2 font-semibold">Descrição</th>
                  <th className="px-4 py-2 font-semibold">Entidade</th>
                  <th className="px-4 py-2 font-semibold">Por</th>
                  <th className="px-4 py-2 font-semibold text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((a) => (
                  <tr key={a.id} className="border-b border-zinc-100 hover:bg-sky-50">
                    <td className="px-4 py-2 text-xs text-zinc-500 whitespace-nowrap">
                      {new Date(a.created_at).toLocaleString('pt-BR')}
                    </td>
                    <td className="px-4 py-2">
                      <Badge className={logNameColor(a.log_name)}>{a.log_name ?? '—'}</Badge>
                    </td>
                    <td className="px-4 py-2 text-zinc-700">{a.description}</td>
                    <td className="px-4 py-2 text-xs text-zinc-500 font-mono">
                      {a.subject_type ? `${a.subject_type.split('\\').pop()} #${a.subject_id ?? '?'}` : '—'}
                    </td>
                    <td className="px-4 py-2 text-xs text-zinc-500">
                      {a.causer_id ? `User #${a.causer_id}` : 'sistema'}
                    </td>
                    <td className="px-4 py-2 text-right">
                      <Link
                        href={`/auditoria/${a.id}`}
                        className="text-sky-700 hover:underline text-xs"
                      >
                        Ver →
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </CardContent>
      </Card>

      <p className="text-xs text-zinc-500 mt-4">
        Constituição v2 Art. 9 · activity_log append-only (trigger MySQL ADR 0084) · RevertService US-AUDIT-008 (pendente)
      </p>
    </>
  )
}

AuditoriaIndex.layout = (page: ReactNode): React.ReactElement => <AppShellV2>{page}</AppShellV2>

export default AuditoriaIndex
