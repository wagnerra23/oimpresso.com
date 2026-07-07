// @auditoria
//   tela: /auditoria
//   adrs: 0079 Art. 9 (Auditoria activity_log), 0127 (Modules/Auditoria UI + undo)
//   runbook: memory/requisitos/Auditoria/SPEC.md US-AUDIT-007/010
//   charter: ./Index.charter.md

import React, { useEffect, useState, type ReactNode } from 'react'
import { Deferred, Link, router } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Input } from '@/Components/ui/input'
import { Skeleton } from '@/Components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select'
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
  causer_kind?: string | null
  event?: string | null
  subject_type?: string | null
}

interface Paginator<T> {
  data: T[]
  current_page?: number
  last_page?: number
  total?: number
  links?: Array<{ url: string | null; label: string; active: boolean }>
}

interface Props {
  activities?: Activity[] | Paginator<Activity> | null
  filters?: Filters
}

// Sentinel pro Radix Select (value="" é proibido pelo Radix).
const ALL = '__all__'

// Filtros REAIS aceitos pelo backend (AuditEntryService::ALLOWED_FILTERS +
// FilterAuditEntriesRequest): causer_kind, event, subject_type. NÃO inventar
// chaves — normalizeFilters() faz array_intersect_key e descarta o resto.
const CAUSER_KIND_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'user', label: 'Usuário' },
  { value: 'ia', label: 'IA' },
  { value: 'system', label: 'Sistema' },
]

const EVENT_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'created', label: 'Criado' },
  { value: 'updated', label: 'Atualizado' },
  { value: 'deleted', label: 'Excluído' },
  { value: 'restored', label: 'Restaurado' },
  { value: 'reverted', label: 'Revertido' },
]

function normalizeActivities(input: Props['activities']): Activity[] {
  if (!input) return []
  if (Array.isArray(input)) return input
  if (typeof input === 'object' && 'data' in input && Array.isArray(input.data)) {
    return input.data
  }
  return []
}

function paginatorMeta(
  input: Props['activities'],
): { total: number; current_page: number; last_page: number; links: Paginator<Activity>['links'] } | null {
  if (!input || Array.isArray(input)) return null
  return {
    total: input.total ?? input.data.length,
    current_page: input.current_page ?? 1,
    last_page: input.last_page ?? 1,
    links: input.links,
  }
}

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline'

function logNameVariant(logName: string | null): BadgeVariant {
  if (!logName) return 'outline'
  if (logName.startsWith('sales')) return 'default'
  if (logName.startsWith('financeiro')) return 'default'
  if (logName.startsWith('whatsapp')) return 'secondary'
  if (logName.startsWith('crm')) return 'secondary'
  if (logName.includes('mfg') || logName.startsWith('manufacturing')) return 'secondary'
  return 'outline'
}

function TableSkeleton(): React.ReactElement {
  return (
    <div className="p-4 space-y-3" aria-busy="true" aria-label="Carregando atividades">
      {Array.from({ length: 8 }).map((_, i) => (
        <div key={i} className="flex items-center gap-4">
          <Skeleton className="h-4 w-32" />
          <Skeleton className="h-5 w-20 rounded-full" />
          <Skeleton className="h-4 flex-1" />
          <Skeleton className="h-4 w-24" />
          <Skeleton className="h-4 w-16" />
        </div>
      ))}
    </div>
  )
}

function ActivitiesTable({ activities }: { activities: Props['activities'] }): React.ReactElement {
  const rows = normalizeActivities(activities)
  const meta = paginatorMeta(activities)

  if (rows.length === 0) {
    return (
      <div className="p-6">
        <EmptyState
          icon="ShieldCheck"
          title="Nenhuma atividade encontrada"
          description="Quando usuários do sistema executarem ações, elas aparecem aqui."
        />
      </div>
    )
  }

  return (
    <>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 border-b border-border">
            <tr className="text-left">
              <th className="px-4 py-2 font-semibold text-muted-foreground">Quando</th>
              <th className="px-4 py-2 font-semibold text-muted-foreground">Log</th>
              <th className="px-4 py-2 font-semibold text-muted-foreground">Descrição</th>
              <th className="px-4 py-2 font-semibold text-muted-foreground">Entidade</th>
              <th className="px-4 py-2 font-semibold text-muted-foreground">Por</th>
              <th className="px-4 py-2 font-semibold text-muted-foreground text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((a) => (
              <tr key={a.id} className="border-b border-border last:border-0 hover:bg-muted/40 transition-colors">
                <td className="px-4 py-2 text-xs text-muted-foreground whitespace-nowrap">
                  {new Date(a.created_at).toLocaleString('pt-BR')}
                </td>
                <td className="px-4 py-2">
                  <Badge variant={logNameVariant(a.log_name)}>{a.log_name ?? '—'}</Badge>
                </td>
                <td className="px-4 py-2 text-foreground">{a.description}</td>
                <td className="px-4 py-2 text-xs text-muted-foreground font-mono">
                  {a.subject_type ? `${a.subject_type.split('\\').pop() || a.subject_type} #${a.subject_id ?? '?'}` : '—'}
                </td>
                <td className="px-4 py-2 text-xs text-muted-foreground">
                  {a.causer_id ? `User #${a.causer_id}` : 'sistema'}
                </td>
                <td className="px-4 py-2 text-right">
                  <Link
                    href={`/auditoria/${a.id}`}
                    className="text-primary hover:underline text-xs font-medium"
                  >
                    Ver →
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between gap-4 border-t border-border px-4 py-3 text-xs text-muted-foreground">
          <span className="tabular-nums">
            Página {meta.current_page} de {meta.last_page} · {meta.total} registros
          </span>
          <div className="flex flex-wrap gap-1">
            {(meta.links ?? []).map((l, i) => (
              <button
                key={i}
                type="button"
                disabled={!l.url}
                onClick={() => l.url && router.visit(l.url, {
                  // D-14: partial reload — só re-busca o que muda ao paginar
                  preserveScroll: true, preserveState: true, only: ['activities', 'filters'],
                })}
                className={
                  'min-w-7 rounded-md border px-2 py-1 text-xs transition-colors disabled:opacity-40 disabled:cursor-not-allowed ' +
                  (l.active
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-border bg-background text-muted-foreground hover:bg-muted hover:text-foreground')
                }
                dangerouslySetInnerHTML={{ __html: l.label }}
              />
            ))}
          </div>
        </div>
      )}
    </>
  )
}

function AuditoriaIndex({ activities, filters = {} }: Props): React.ReactElement {
  const [causerKind, setCauserKind] = useState<string>(filters.causer_kind ?? ALL)
  const [event, setEvent] = useState<string>(filters.event ?? ALL)
  const [subjectType, setSubjectType] = useState<string>(filters.subject_type ?? '')

  const applyFilters = (next: { causer_kind?: string; event?: string; subject_type?: string }) => {
    const params: Record<string, string> = {}
    const ck = next.causer_kind ?? causerKind
    const ev = next.event ?? event
    const st = next.subject_type ?? subjectType

    if (ck && ck !== ALL) params.causer_kind = ck
    if (ev && ev !== ALL) params.event = ev
    if (st.trim()) params.subject_type = st.trim()

    router.get('/auditoria', params, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ['activities', 'filters'],
    })
  }

  // Debounce só pro input livre de subject_type (Selects aplicam no change).
  useEffect(() => {
    const current = filters.subject_type ?? ''
    if (subjectType === current) return
    const t = setTimeout(() => applyFilters({ subject_type: subjectType }), 400)
    return () => clearTimeout(t)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [subjectType])

  const hasActiveFilter = causerKind !== ALL || event !== ALL || subjectType.trim() !== ''

  const clearAll = () => {
    setCauserKind(ALL)
    setEvent(ALL)
    setSubjectType('')
    router.get('/auditoria', {}, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
      only: ['activities', 'filters'],
    })
  }

  return (
    <>
      <PageHeader
        title="Auditoria"
        icon="ShieldCheck"
        description="Log de atividades do sistema — Constituição Art. 9 (activity_log)"
      />

      <Card>
        <CardHeader className="pb-3">
          <CardTitle className="text-sm">Filtros</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex flex-wrap items-end gap-3">
            <div className="flex flex-col gap-1.5">
              <span className="text-xs font-medium text-muted-foreground">Origem</span>
              <Select
                value={causerKind}
                onValueChange={(v) => {
                  setCauserKind(v)
                  applyFilters({ causer_kind: v })
                }}
              >
                <SelectTrigger className="w-44">
                  <SelectValue placeholder="Qualquer origem" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL}>Qualquer origem</SelectItem>
                  {CAUSER_KIND_OPTIONS.map((o) => (
                    <SelectItem key={o.value} value={o.value}>
                      {o.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex flex-col gap-1.5">
              <span className="text-xs font-medium text-muted-foreground">Evento</span>
              <Select
                value={event}
                onValueChange={(v) => {
                  setEvent(v)
                  applyFilters({ event: v })
                }}
              >
                <SelectTrigger className="w-44">
                  <SelectValue placeholder="Qualquer evento" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL}>Qualquer evento</SelectItem>
                  {EVENT_OPTIONS.map((o) => (
                    <SelectItem key={o.value} value={o.value}>
                      {o.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex flex-col gap-1.5">
              <span className="text-xs font-medium text-muted-foreground">Entidade (subject_type)</span>
              <Input
                type="text"
                value={subjectType}
                onChange={(e) => setSubjectType(e.target.value)}
                placeholder="ex: Transaction"
                className="w-56"
                aria-label="Filtrar por tipo de entidade"
              />
            </div>

            {hasActiveFilter && (
              <button
                type="button"
                onClick={clearAll}
                className="h-9 rounded-md px-3 text-xs font-medium text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
              >
                Limpar filtros
              </button>
            )}
          </div>
        </CardContent>
      </Card>

      <Card className="mt-4">
        <CardHeader className="pb-2">
          <CardTitle className="text-sm flex items-center justify-between gap-2">
            <span>Atividades recentes</span>
            {hasActiveFilter && <Badge variant="secondary">Filtros ativos</Badge>}
          </CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          <Deferred data="activities" fallback={<TableSkeleton />}>
            <ActivitiesTable activities={activities} />
          </Deferred>
        </CardContent>
      </Card>

      <p className="text-xs text-muted-foreground mt-4">
        Constituição v2 Art. 9 · activity_log append-only (trigger MySQL ADR 0084) · RevertService US-AUDIT-008 (pendente)
      </p>
    </>
  )
}

AuditoriaIndex.layout = (page: ReactNode): React.ReactElement => (
  <AppShellV2 title="Auditoria" breadcrumbItems={[{ label: 'Administração' }, { label: 'Auditoria' }]}>
    {page}
  </AppShellV2>
)

export default AuditoriaIndex
