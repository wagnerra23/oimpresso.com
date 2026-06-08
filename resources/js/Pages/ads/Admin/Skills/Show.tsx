// @ads
//   tela: /ads/admin/skills/{slug}
//   adrs: 0076 (Fase 2) — adiciona timeline de versions + botão Editar

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link, router } from '@inertiajs/react'
import ReactMarkdown from 'react-markdown'
import remarkGfm from 'remark-gfm'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import PageHeader from '@/Components/shared/PageHeader'
import { ArrowLeft, ExternalLink, Pencil, History, Play, Upload, GitBranch } from 'lucide-react'

interface Skill {
  slug: string
  frontmatter: Record<string, any>
  body: string
  git_path: string
  source: 'db' | 'filesystem'
}

interface Version {
  id: number
  version: number
  origin: 'ui' | 'git_drift' | 'git_seed'
  status: 'draft' | 'review' | 'published' | 'drift_pending' | 'archived'
  created_at: string | null
  is_current: boolean
}

interface Props {
  skill: Skill
  versions: Version[]
  editable: boolean
}

const originLabel: Record<Version['origin'], string> = {
  ui: 'UI',
  git_drift: 'Git drift',
  git_seed: 'Git seed',
}

const statusVariant: Record<Version['status'], 'default' | 'secondary' | 'outline' | 'destructive'> = {
  published: 'default',
  draft: 'secondary',
  review: 'outline',
  drift_pending: 'destructive',
  archived: 'outline',
}

const Show: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ skill, versions, editable }) => {
  const fm = skill.frontmatter || {}
  const githubUrl = `https://github.com/wagnerra23/oimpresso.com/blob/main/${skill.git_path}`

  return (
    <div className="mx-auto max-w-5xl p-6 space-y-4">
      <div className="flex items-center justify-between">
        <Link href="/ads/admin/skills" className="inline-flex items-center gap-1 text-sm text-primary hover:underline">
          <ArrowLeft className="w-4 h-4" /> Voltar pra lista
        </Link>
        <div className="flex items-center gap-2">
          {editable && (
            <>
              <Link href={`/ads/admin/skills/${skill.slug}/test`}>
                <Button size="sm" variant="outline">
                  <Play className="w-4 h-4 mr-1" /> Testar
                </Button>
              </Link>
              <Link href={`/ads/admin/skills/${skill.slug}/edit`}>
                <Button size="sm">
                  <Pencil className="w-4 h-4 mr-1" /> Editar
                </Button>
              </Link>
            </>
          )}
          <a href={githubUrl} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
            Ver no GitHub <ExternalLink className="w-3.5 h-3.5" />
          </a>
        </div>
      </div>

      <PageHeader
        icon="zap"
        title={fm.name || skill.slug}
        description={fm.description || ''}
        action={<Badge variant={skill.source === 'db' ? 'default' : 'outline'}>{skill.source === 'db' ? 'DB' : 'Filesystem'}</Badge>}
      />

      <Card>
        <CardHeader>
          <CardTitle className="text-sm">Frontmatter</CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="grid grid-cols-1 sm:grid-cols-[140px_1fr] gap-2 text-sm">
            <dt className="font-medium text-muted-foreground">slug</dt>
            <dd className="font-mono">{skill.slug}</dd>

            {fm.name && (
              <>
                <dt className="font-medium text-muted-foreground">name</dt>
                <dd>{fm.name}</dd>
              </>
            )}

            {fm.description && (
              <>
                <dt className="font-medium text-muted-foreground">description</dt>
                <dd className="text-sm leading-relaxed">{fm.description}</dd>
              </>
            )}

            {fm.module && (
              <>
                <dt className="font-medium text-muted-foreground">module</dt>
                <dd><Badge variant="secondary">{fm.module}</Badge></dd>
              </>
            )}

            {Array.isArray(fm.tags) && fm.tags.length > 0 && (
              <>
                <dt className="font-medium text-muted-foreground">tags</dt>
                <dd className="flex flex-wrap gap-1">
                  {fm.tags.map((t: string) => <Badge key={t} variant="outline">{t}</Badge>)}
                </dd>
              </>
            )}

            <dt className="font-medium text-muted-foreground">arquivo</dt>
            <dd className="font-mono text-xs text-muted-foreground">{skill.git_path}</dd>
          </dl>
        </CardContent>
      </Card>

      {versions.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-sm flex items-center gap-2">
              <History className="w-4 h-4" /> Histórico de versões ({versions.length})
            </CardTitle>
          </CardHeader>
          <CardContent>
            <table className="w-full text-sm">
              <thead className="text-xs uppercase text-muted-foreground">
                <tr>
                  <th className="text-left py-1 pr-3 w-16">v</th>
                  <th className="text-left py-1 pr-3">Origem</th>
                  <th className="text-left py-1 pr-3">Status</th>
                  <th className="text-left py-1 pr-3">Criada em</th>
                  <th className="text-left py-1">Ações</th>
                </tr>
              </thead>
              <tbody>
                {versions.map(v => (
                  <tr key={v.id} className="border-t">
                    <td className="py-1.5 pr-3 font-mono text-xs">v{v.version}</td>
                    <td className="py-1.5 pr-3"><Badge variant="outline" className="text-xs">{originLabel[v.origin]}</Badge></td>
                    <td className="py-1.5 pr-3"><Badge variant={statusVariant[v.status]} className="text-xs">{v.status}</Badge></td>
                    <td className="py-1.5 pr-3 text-xs text-muted-foreground">{v.created_at || '—'}</td>
                    <td className="py-1.5 space-x-1">
                      {v.is_current && <Badge>production</Badge>}
                      {!v.is_current && v.status === 'published' && (
                        <Button
                          size="sm"
                          variant="outline"
                          className="h-6 text-xs"
                          onClick={() => {
                            if (!confirm(`Mover label production pra v${v.version}? (rollback ou switch)`)) return
                            router.post(`/ads/admin/skills/${skill.slug}/move-label`, {
                              label: 'production',
                              version_id: v.id,
                              reason: `Manual switch via UI`,
                            }, { preserveScroll: true })
                          }}
                        >
                          <GitBranch className="w-3 h-3 mr-1" /> Promover production
                        </Button>
                      )}
                      {v.status === 'published' && (
                        <Button
                          size="sm"
                          variant="outline"
                          className="h-6 text-xs"
                          onClick={() => {
                            if (!confirm(`Publicar v${v.version} no git? Vai criar PR.`)) return
                            router.post(`/ads/admin/skills/versions/${v.id}/publish`, {}, { preserveScroll: true })
                          }}
                        >
                          <Upload className="w-3 h-3 mr-1" /> Publish to git
                        </Button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="text-sm">Conteúdo (markdown)</CardTitle>
        </CardHeader>
        <CardContent>
          <article className="prose prose-sm dark:prose-invert max-w-none
            prose-headings:scroll-mt-4 prose-headings:font-semibold
            prose-h1:text-2xl prose-h1:border-b prose-h1:pb-2 prose-h1:mb-4
            prose-h2:text-xl prose-h2:mt-8 prose-h2:mb-3
            prose-h3:text-base prose-h3:mt-6 prose-h3:mb-2
            prose-pre:bg-zinc-900 prose-pre:text-zinc-100 prose-pre:rounded-md prose-pre:p-4 prose-pre:text-xs
            prose-code:before:content-none prose-code:after:content-none
            prose-code:bg-muted prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded prose-code:text-xs prose-code:font-mono prose-code:font-normal
            prose-a:text-primary prose-a:no-underline hover:prose-a:underline
            prose-table:text-xs prose-th:text-xs prose-td:py-1 prose-td:px-2">
            <ReactMarkdown remarkPlugins={[remarkGfm]}>{skill.body}</ReactMarkdown>
          </article>
        </CardContent>
      </Card>

      <p className="text-xs text-muted-foreground">
        Edição cria nova version status=draft. Approval queue (Fase 4) move label production pra version aprovada.
      </p>
    </div>
  )
}

Show.layout = (page: ReactNode) => (
  <AppShellV2 title="Skill" breadcrumbItems={[{ label: 'ADS' }, { label: 'Skills', href: '/ads/admin/skills' }, { label: 'Detalhe' }]}>
    {page}
  </AppShellV2>
)

export default Show
