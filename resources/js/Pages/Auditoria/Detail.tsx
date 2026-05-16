// @auditoria
//   tela: /auditoria/{id}
//   adrs: 0079 Art. 9 Auditoria, 0127 Modules/Auditoria UI

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

interface Props {
  activity?: Activity | null
}

function AuditoriaDetail({ activity }: Props): React.ReactElement {
  if (!activity) {
    return (
      <>
        <PageHeader
          title="Atividade não encontrada"
          breadcrumbs={[{ label: 'Auditoria', href: '/auditoria' }, { label: 'Não encontrada' }]}
        />
        <Card>
          <CardContent className="py-6">
            <EmptyState
              icon="AlertCircle"
              title="Atividade não encontrada"
              description="Esta atividade pode não existir ou pertencer a outro tenant."
            />
            <div className="text-center mt-4">
              <Link href="/auditoria" className="text-sky-700 hover:underline">← Voltar à lista</Link>
            </div>
          </CardContent>
        </Card>
      </>
    )
  }

  return (
    <>
      <PageHeader
        title={`Atividade #${activity.id}`}
        subtitle={activity.description}
        breadcrumbs={[
          { label: 'Auditoria', href: '/auditoria' },
          { label: `#${activity.id}` },
        ]}
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
            <dt className="text-zinc-500">Quando</dt>
            <dd>{new Date(activity.created_at).toLocaleString('pt-BR')}</dd>

            <dt className="text-zinc-500">Entidade</dt>
            <dd className="font-mono text-xs">{activity.subject_type ? `${activity.subject_type} #${activity.subject_id ?? '?'}` : '—'}</dd>

            <dt className="text-zinc-500">Quem causou</dt>
            <dd>{activity.causer_id ? `User #${activity.causer_id} (${activity.causer_type?.split('\\').pop() ?? 'User'})` : 'sistema'}</dd>

            <dt className="text-zinc-500">Business ID</dt>
            <dd>{activity.business_id ?? '—'}</dd>
          </dl>
        </CardContent>
      </Card>

      {activity.properties && Object.keys(activity.properties).length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Propriedades (diff)</CardTitle>
          </CardHeader>
          <CardContent>
            <pre className="text-xs bg-zinc-50 border border-zinc-200 rounded p-3 overflow-x-auto">
              {JSON.stringify(activity.properties, null, 2)}
            </pre>
          </CardContent>
        </Card>
      )}

      <div className="mt-4">
        <Link href="/auditoria" className="text-sky-700 hover:underline text-sm">← Voltar à lista</Link>
      </div>

      <p className="text-xs text-zinc-500 mt-4">
        Constituição v2 Art. 9 · activity_log append-only · RevertService US-AUDIT-008 pendente
      </p>
    </>
  )
}

AuditoriaDetail.layout = (page: ReactNode): React.ReactElement => <AppShellV2>{page}</AppShellV2>

export default AuditoriaDetail
