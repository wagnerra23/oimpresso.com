// @auditoria
//   tela: /auditoria/{id}
//   adrs: 0079 Art. 9 Auditoria, 0127 Modules/Auditoria UI
//   gap-fix board 2026-05-30 (58→≥70): PageHeader canon (sem subtitle/breadcrumbs
//   inexistentes), tokens (zero sky/zinc cru), diff key/value formatado, links navegáveis.

import React, { type ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import PageHeader from '@/Components/shared/PageHeader'
import EmptyState from '@/Components/shared/EmptyState'
import { ArrowLeft } from 'lucide-react'

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

interface Props {
  activity?: Activity | null
}

// Diff Spatie activitylog: properties = { old?: {...}, attributes?: {...} }.
// Renderiza Campo · Antes · Depois quando houver old+attributes; senão KV simples.
function PropertiesDiff({ properties }: { properties: Record<string, unknown> }) {
  const old = (properties.old ?? null) as Record<string, unknown> | null
  const attrs = (properties.attributes ?? null) as Record<string, unknown> | null

  if (attrs && typeof attrs === 'object') {
    const keys = Array.from(
      new Set([...Object.keys(attrs), ...(old ? Object.keys(old) : [])]),
    )
    return (
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <caption className="sr-only">Diferenças de propriedades da atividade</caption>
          <thead className="bg-muted/50">
            <tr className="text-left">
              <th scope="col" className="px-3 py-2 font-medium text-muted-foreground">Campo</th>
              <th scope="col" className="px-3 py-2 font-medium text-muted-foreground">Antes</th>
              <th scope="col" className="px-3 py-2 font-medium text-muted-foreground">Depois</th>
            </tr>
          </thead>
          <tbody>
            {keys.map((k) => (
              <tr key={k} className="border-t border-border">
                <td className="px-3 py-2 font-mono text-xs text-foreground">{k}</td>
                <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                  {old && k in old ? String(old[k]) : '—'}
                </td>
                <td className="px-3 py-2 font-mono text-xs text-foreground">
                  {k in attrs ? String(attrs[k]) : '—'}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )
  }

  // Sem shape old/attributes → KV simples (sem <pre> JSON cru)
  return (
    <dl className="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
      {Object.entries(properties).map(([k, v]) => (
        <div key={k} className="flex flex-col gap-0.5">
          <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{k}</dt>
          <dd className="break-all font-mono text-xs text-foreground">
            {typeof v === 'object' ? JSON.stringify(v) : String(v)}
          </dd>
        </div>
      ))}
    </dl>
  )
}

function AuditoriaDetail({ activity }: Props): React.ReactElement {
  if (!activity) {
    return (
      <>
        <PageHeader icon="history" title="Atividade não encontrada" />
        <Card>
          <CardContent className="py-6">
            <EmptyState
              icon="AlertCircle"
              title="Atividade não encontrada"
              description="Esta atividade pode não existir ou pertencer a outro tenant."
              action={
                <Button asChild variant="outline">
                  <Link href="/auditoria">
                    <ArrowLeft className="size-4" aria-hidden /> Voltar à lista
                  </Link>
                </Button>
              }
            />
          </CardContent>
        </Card>
      </>
    )
  }

  const subjectEntity = activity.subject_type
    ? `${activity.subject_type.split('\\').pop() || activity.subject_type} #${activity.subject_id ?? '?'}`
    : null

  return (
    <>
      <PageHeader
        icon="history"
        title={`Atividade #${activity.id}`}
        description={activity.description}
        action={
          <Button asChild variant="outline">
            <Link href="/auditoria">
              <ArrowLeft className="size-4" aria-hidden /> Voltar à lista
            </Link>
          </Button>
        }
      />

      <Card className="mb-4">
        <CardHeader>
          <CardTitle className="text-base flex items-center justify-between">
            <span>Metadata</span>
            <Badge>{activity.log_name ?? '—'}</Badge>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="grid grid-cols-2 gap-3 text-sm">
            <dt className="text-muted-foreground">Quando</dt>
            <dd className="text-foreground">{new Date(activity.created_at).toLocaleString('pt-BR')}</dd>

            <dt className="text-muted-foreground">Entidade</dt>
            <dd className="font-mono text-xs">
              {subjectEntity && activity.subject_type ? (
                <Link
                  href={`/auditoria?subject_type=${encodeURIComponent(activity.subject_type)}`}
                  className="text-primary hover:underline"
                >
                  {subjectEntity}
                </Link>
              ) : (
                '—'
              )}
            </dd>

            <dt className="text-muted-foreground">Quem causou</dt>
            <dd className="text-foreground">
              {activity.causer_id
                ? `User #${activity.causer_id} (${activity.causer_type?.split('\\').pop() ?? 'User'})`
                : 'sistema'}
            </dd>

            <dt className="text-muted-foreground">Business ID</dt>
            <dd className="text-foreground">{activity.business_id ?? '—'}</dd>
          </dl>
        </CardContent>
      </Card>

      {activity.properties && Object.keys(activity.properties).length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Propriedades (diff)</CardTitle>
          </CardHeader>
          <CardContent>
            <PropertiesDiff properties={activity.properties} />
          </CardContent>
        </Card>
      )}

      <p className="text-xs text-muted-foreground mt-4">
        Constituição v2 Art. 9 · activity_log append-only · RevertService US-AUDIT-008 pendente
      </p>
    </>
  )
}

AuditoriaDetail.layout = (page: ReactNode): React.ReactElement => <AppShellV2>{page}</AppShellV2>

export default AuditoriaDetail
