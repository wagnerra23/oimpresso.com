// @ads
//   tela: /ads/admin/skills-review
//   adrs: 0076 (Fase 4) — approval queue obrigatório pré-publish

import React, { useState, type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link, router, usePage } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Textarea } from '@/Components/ui/textarea'
import PageHeader from '@/Components/shared/PageHeader'
import EmptyState from '@/Components/shared/EmptyState'
import { Check, X, ExternalLink } from 'lucide-react'

interface Draft {
  id: number
  skill_slug: string
  skill_name: string
  version: number
  origin: 'ui' | 'git_drift' | 'git_seed'
  rationale_problem: string
  rationale_hypothesis: string
  created_at: string | null
  test_runs_count: number
  test_runs_pass: number
}

interface Props {
  drafts: Draft[]
}

const Review: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ drafts }) => {
  const flash = (usePage().props as any)?.flash?.status
  const [comments, setComments] = useState<Record<number, string>>({})

  const setComment = (id: number, val: string) => setComments(prev => ({ ...prev, [id]: val }))

  const approve = (versionId: number) => {
    router.post(`/ads/admin/skills/versions/${versionId}/approve`, {
      comment: comments[versionId] || '',
    }, { preserveScroll: true })
  }

  const reject = (versionId: number) => {
    if (!comments[versionId] || comments[versionId].length < 5) {
      alert('Comentário obrigatório (≥5 chars) pra rejeitar.')
      return
    }
    router.post(`/ads/admin/skills/versions/${versionId}/reject`, {
      comment: comments[versionId],
    }, { preserveScroll: true })
  }

  return (
    <div className="mx-auto max-w-5xl p-6 space-y-4">
      <PageHeader
        icon="check"
        title="Approval queue"
        description="Drafts aguardando aprovação. Approve = vira published + label production move. Reject = arquivado (precisa comment ≥5 chars)."
      />

      {flash && (
        <div className="rounded-md border border-success/20 bg-success-soft px-4 py-2 text-sm text-success-fg">
          {flash}
        </div>
      )}

      {drafts.length === 0 ? (
        <EmptyState
          icon="check"
          title="Nenhum draft pra revisar"
          description="Drafts aparecem aqui quando alguém edita uma skill via UI e clica 'Salvar como draft'."
        />
      ) : (
        <div className="space-y-3">
          {drafts.map(d => (
            <Card key={d.id}>
              <CardHeader>
                <CardTitle className="text-sm flex items-center justify-between">
                  <span>
                    <Link href={`/ads/admin/skills/${d.skill_slug}`} className="text-primary hover:underline">
                      {d.skill_name}
                    </Link>
                    <span className="text-muted-foreground"> · v{d.version}</span>
                  </span>
                  <span className="flex items-center gap-2">
                    <Badge variant="outline">{d.origin}</Badge>
                    <Badge variant="secondary">draft</Badge>
                  </span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3 text-sm">
                <div>
                  <div className="text-xs font-medium text-muted-foreground">Problema observado:</div>
                  <p className="leading-relaxed">{d.rationale_problem || <em className="text-muted-foreground">(vazio)</em>}</p>
                </div>
                <div>
                  <div className="text-xs font-medium text-muted-foreground">Hipótese:</div>
                  <p className="leading-relaxed">{d.rationale_hypothesis || <em className="text-muted-foreground">(vazio)</em>}</p>
                </div>

                <div className="flex items-center gap-3 text-xs text-muted-foreground">
                  <span>Criada {d.created_at}</span>
                  {d.test_runs_count > 0 ? (
                    <span>Test runs: {d.test_runs_pass}/{d.test_runs_count} passed</span>
                  ) : (
                    <span className="text-warning-fg">⚠️ Sem test runs anexados</span>
                  )}
                  <Link href={`/ads/admin/skills/${d.skill_slug}/test`} className="inline-flex items-center gap-1 text-primary hover:underline">
                    Testar primeiro <ExternalLink className="w-3 h-3" />
                  </Link>
                </div>

                <Textarea
                  rows={2}
                  placeholder="Comentário (obrigatório pra rejeitar, opcional pra aprovar)"
                  value={comments[d.id] || ''}
                  onChange={e => setComment(d.id, e.target.value)}
                />

                <div className="flex items-center justify-end gap-2">
                  <Button size="sm" variant="outline" onClick={() => reject(d.id)}>
                    <X className="w-4 h-4 mr-1" /> Rejeitar
                  </Button>
                  <Button size="sm" onClick={() => approve(d.id)}>
                    <Check className="w-4 h-4 mr-1" /> Aprovar
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}

Review.layout = (page: ReactNode) => (
  <AppShellV2 title="Approval Queue" breadcrumbItems={[{ label: 'ADS' }, { label: 'Skills', href: '/ads/admin/skills' }, { label: 'Review' }]}>
    {page}
  </AppShellV2>
)

export default Review
