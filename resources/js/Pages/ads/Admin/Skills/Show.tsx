// @ads
//   tela: /ads/admin/skills/{slug}
//   adrs: 0076 (V0 read-only — frontmatter + body markdown render)

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link } from '@inertiajs/react'
import ReactMarkdown from 'react-markdown'
import remarkGfm from 'remark-gfm'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import PageHeader from '@/Components/shared/PageHeader'
import { ArrowLeft, ExternalLink } from 'lucide-react'

interface Skill {
  slug: string
  frontmatter: Record<string, any>
  body: string
  git_path: string
}

interface Props {
  skill: Skill
}

const Show: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ skill }) => {
  const fm = skill.frontmatter || {}
  const githubUrl = `https://github.com/wagnerra23/oimpresso.com/blob/main/${skill.git_path}`

  return (
    <div className="mx-auto max-w-5xl p-6 space-y-4">
      <div className="flex items-center justify-between">
        <Link href="/ads/admin/skills" className="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline">
          <ArrowLeft className="w-4 h-4" /> Voltar pra lista
        </Link>
        <a href={githubUrl} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
          Ver no GitHub <ExternalLink className="w-3.5 h-3.5" />
        </a>
      </div>

      <PageHeader
        icon="zap"
        title={fm.name || skill.slug}
        description={fm.description || ''}
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
            prose-a:text-blue-600 dark:prose-a:text-blue-400 prose-a:no-underline hover:prose-a:underline
            prose-table:text-xs prose-th:text-xs prose-td:py-1 prose-td:px-2">
            <ReactMarkdown remarkPlugins={[remarkGfm]}>{skill.body}</ReactMarkdown>
          </article>
        </CardContent>
      </Card>

      <p className="text-xs text-muted-foreground">
        V0 read-only. CYCLE-02 entrega edição inline, versionamento DB, history, rationale 4 campos e drift detection.
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
