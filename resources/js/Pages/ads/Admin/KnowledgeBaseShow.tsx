// @ads
//   tela: /ads/admin/kb/{slug}
//   adrs: KB Obsidian-style — backlinks view

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import PageHeader from '@/Components/shared/PageHeader'
import EmptyState from '@/Components/shared/EmptyState'
import { ArrowLeft, Link2, FolderKanban, Zap, Brain, Inbox, ExternalLink } from 'lucide-react'

interface Doc {
  id: number
  slug: string
  title: string
  type: string
  module: string | null
  status: string | null
  content_md: string
  git_path: string | null
  updated_at: string
}

interface BacklinkItem {
  id: number
  relation: string
  created_at: string
  label: string
  url: string
}

interface Props {
  document: Doc
  backlinks: Record<string, BacklinkItem[]>  // {project: [...], skill: [...], ...}
}

const targetIcon: Record<string, ReactNode> = {
  project:   <FolderKanban className="w-4 h-4" />,
  skill:     <Zap className="w-4 h-4" />,
  decision:  <Inbox className="w-4 h-4" />,
  metaskill: <Brain className="w-4 h-4" />,
}

const targetLabel: Record<string, string> = {
  project:   'Projects',
  skill:     'Skills',
  decision:  'Decisions',
  metaskill: 'Meta-skills',
}

const KnowledgeBaseShow: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ document: d, backlinks }) => {
  const totalBacklinks = Object.values(backlinks).reduce((acc, list) => acc + list.length, 0)

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <Link href="/ads/admin/kb" className="text-sm text-muted-foreground hover:text-foreground inline-flex items-center gap-1">
        <ArrowLeft className="w-4 h-4" /> Voltar para KB
      </Link>

      <PageHeader
        icon="book-open"
        title={d.title}
        description={
          <span>
            <code className="text-xs bg-zinc-100 px-1.5 py-0.5 rounded">{d.slug}</code>
            {' · '}
            <Badge variant="outline">{d.type}</Badge>
            {d.module && <> · <Badge variant="outline">{d.module}</Badge></>}
            {d.git_path && <> · <code className="text-xs">{d.git_path}</code></>}
          </span> as any
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Conteúdo principal — Markdown raw (V1; V2 render markdown) */}
        <div className="lg:col-span-2">
          <Card>
            <CardHeader><CardTitle className="text-base">Conteúdo</CardTitle></CardHeader>
            <CardContent>
              <pre className="text-xs whitespace-pre-wrap font-mono bg-zinc-50 p-4 rounded overflow-x-auto max-h-[60vh] overflow-y-auto">
                {d.content_md || '(sem conteúdo indexado — ver no git)'}
              </pre>
            </CardContent>
          </Card>
        </div>

        {/* Sidebar — backlinks (Obsidian style) */}
        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <Link2 className="w-4 h-4" />
                Backlinks
                <Badge variant="outline" className="ml-auto">{totalBacklinks}</Badge>
              </CardTitle>
            </CardHeader>
            <CardContent>
              {totalBacklinks === 0 ? (
                <p className="text-sm text-muted-foreground">
                  Nenhuma entidade do ADS referencia esta ADR ainda.
                </p>
              ) : (
                <div className="space-y-3">
                  {Object.entries(backlinks).map(([type, items]) => (
                    <div key={type}>
                      <div className="flex items-center gap-2 text-xs uppercase font-semibold text-muted-foreground mb-1">
                        {targetIcon[type] ?? <Link2 className="w-3 h-3" />}
                        {targetLabel[type] ?? type} ({items.length})
                      </div>
                      <ul className="space-y-1">
                        {items.map(item => (
                          <li key={`${type}-${item.id}`} className="text-sm">
                            <Link
                              href={item.url}
                              className="text-blue-600 hover:underline flex items-center gap-1"
                            >
                              {item.label}
                              <ExternalLink className="w-3 h-3 opacity-50" />
                            </Link>
                            {item.relation !== 'referenced' && (
                              <Badge variant="outline" className="text-xs ml-1">{item.relation}</Badge>
                            )}
                          </li>
                        ))}
                      </ul>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          <Card className="bg-muted/30">
            <CardContent className="py-3 text-xs text-muted-foreground space-y-1">
              <p><strong className="text-foreground">Como criar backlinks:</strong></p>
              <p>1. Manualmente via <code>DecisionLinksService::link()</code></p>
              <p>2. Automático: ProjectDecomposerAgent extrai do JSON <code>regras_consultadas</code></p>
              <p>3. Texto livre "ADR 0024" é parseado por regex</p>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}

KnowledgeBaseShow.layout = (page: ReactNode) => (
  <AppShellV2
    title="ADS — KB"
    breadcrumbItems={[
      { label: 'ADS' },
      { label: 'KB', href: '/ads/admin/kb' },
      { label: 'Doc' },
    ]}
  >
    {page}
  </AppShellV2>
)

export default KnowledgeBaseShow
