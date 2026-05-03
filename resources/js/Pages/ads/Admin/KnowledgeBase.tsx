// @ads
//   tela: /ads/admin/kb
//   adrs: KB Obsidian-style com backlinks (Movement A consolidação)

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link, router } from '@inertiajs/react'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'
import { Search, Link2, BookOpen, Tag } from 'lucide-react'

interface Doc {
  id: number
  slug: string
  title: string
  type: string
  module: string | null
  status: string | null
  updated_at: string
  links_count: number
}

interface FilterOption { value: string; count: number }

interface Props {
  documents: Doc[]
  filters: {
    q: string
    type: string
    module: string
    available_types: FilterOption[]
    available_modules: FilterOption[]
  }
  kpis: { total_docs: number; total_links: number; orphan_count: number; most_linked: string | null }
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)

const typeColor: Record<string, string> = {
  adr:        'bg-blue-100 text-blue-700 border-blue-300',
  session:    'bg-zinc-100 text-zinc-700 border-zinc-300',
  spec:       'bg-purple-100 text-purple-700 border-purple-300',
  runbook:    'bg-amber-100 text-amber-700 border-amber-300',
  comparativo: 'bg-emerald-100 text-emerald-700 border-emerald-300',
  reference:  'bg-zinc-100 text-zinc-700 border-zinc-300',
  changelog:  'bg-orange-100 text-orange-700 border-orange-300',
}

const KnowledgeBase: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ documents, filters, kpis }) => {
  const [q, setQ] = React.useState(filters.q)

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    router.get('/ads/admin/kb', { q, type: filters.type, module: filters.module }, { preserveState: false })
  }

  const setFilter = (key: 'type' | 'module', value: string) => {
    const params: any = { q: filters.q, type: filters.type, module: filters.module }
    params[key] = params[key] === value ? '' : value
    router.get('/ads/admin/kb', params, { preserveState: false })
  }

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="book-open"
        title="ADS — Knowledge Base"
        description="Hub central de ADRs/sessions/specs/runbooks. Backlinks ao estilo Obsidian: clique numa ADR pra ver onde está sendo usada (Projects, Skills, Decisions, Meta-skills)."
      />

      <KpiGrid cols={4}>
        <KpiCard icon="book-open"   tone="info"    label="Documentos"          value={num(kpis.total_docs)}    description="ADRs + sessions + refs" />
        <KpiCard icon="link"        tone="success" label="Backlinks ativos"    value={num(kpis.total_links)}   description="Vínculos com entidades ADS" />
        <KpiCard icon="alert-circle" tone="warning" label="Docs órfãs"         value={num(kpis.orphan_count)}  description="Sem nenhum backlink" />
        <KpiCard icon="zap"         tone="default" label="Doc mais linkada"    value={kpis.most_linked ? '⭐' : '—'} description={kpis.most_linked ?? 'Nenhuma'} />
      </KpiGrid>

      {/* Search + filtros */}
      <Card>
        <CardContent className="py-4 space-y-3">
          <form onSubmit={submit} className="flex gap-2">
            <Input
              value={q}
              onChange={e => setQ(e.target.value)}
              placeholder="Buscar em título, slug, conteúdo… (ex: 'modulo Laravel', 'NFSe', 'ADR 0024')"
              className="flex-1"
            />
            <Button type="submit"><Search className="w-4 h-4 mr-1" /> Buscar</Button>
          </form>

          <div className="flex items-center gap-2 flex-wrap text-xs">
            <span className="text-muted-foreground">Tipos:</span>
            {filters.available_types.map(t => (
              <button key={t.value} type="button" onClick={() => setFilter('type', t.value)}
                className={`px-2 py-0.5 rounded border ${filters.type === t.value ? 'bg-primary text-white border-primary' : 'bg-zinc-50 border-zinc-300 hover:bg-zinc-100'}`}>
                {t.value} ({t.count})
              </button>
            ))}
          </div>

          <div className="flex items-center gap-2 flex-wrap text-xs">
            <span className="text-muted-foreground">Módulos:</span>
            {filters.available_modules.slice(0, 12).map(m => (
              <button key={m.value} type="button" onClick={() => setFilter('module', m.value)}
                className={`px-2 py-0.5 rounded border ${filters.module === m.value ? 'bg-primary text-white border-primary' : 'bg-zinc-50 border-zinc-300 hover:bg-zinc-100'}`}>
                {m.value} ({m.count})
              </button>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Resultados */}
      <Card>
        <CardContent className="p-0">
          {documents.length === 0 ? (
            <EmptyState icon="book-open" title="Nenhum documento" description="Tente outra busca ou remover filtros." />
          ) : (
            <ul className="divide-y divide-border">
              {documents.map(d => (
                <li key={d.id} className="px-4 py-3 hover:bg-muted/30">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex-1 min-w-0 space-y-1">
                      <div className="flex items-center gap-2 flex-wrap">
                        <Link href={`/ads/admin/kb/${encodeURIComponent(d.slug)}`} className="font-medium hover:underline">
                          {d.title}
                        </Link>
                        <Badge className={`border ${typeColor[d.type] ?? 'bg-zinc-100 text-zinc-700 border-zinc-300'}`}>
                          {d.type}
                        </Badge>
                        {d.module && (
                          <Badge variant="outline" className="text-xs">
                            <Tag className="w-2.5 h-2.5 inline mr-0.5" /> {d.module}
                          </Badge>
                        )}
                        {d.links_count > 0 && (
                          <Badge variant="outline" className="text-xs bg-blue-50 text-blue-700 border-blue-200">
                            <Link2 className="w-2.5 h-2.5 inline mr-0.5" /> {d.links_count} backlink{d.links_count > 1 ? 's' : ''}
                          </Badge>
                        )}
                      </div>
                      <div className="text-xs text-muted-foreground">
                        <code>{d.slug}</code> · atualizado {new Date(d.updated_at).toLocaleDateString('pt-BR')}
                      </div>
                    </div>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>

      <Card className="bg-muted/30">
        <CardContent className="py-3 text-xs text-muted-foreground">
          <strong className="text-foreground">Como funciona:</strong> docs vêm de <code className="bg-background px-1 rounded">mcp_memory_documents</code> sincronizado
          via webhook GitHub a partir de <code>memory/*</code>. Backlinks são vínculos polimórficos em <code>mcp_decision_links</code>:
          quando ProjectDecomposerAgent gera um Project, automaticamente vincula as ADRs consultadas. Manualmente: pode usar
          DecisionLinksService::link() em qualquer ponto do código pra registrar referências.
        </CardContent>
      </Card>
    </div>
  )
}

KnowledgeBase.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Knowledge Base" breadcrumbItems={[{ label: 'ADS' }, { label: 'Knowledge Base' }]}>
    {page}
  </AppShellV2>
)

export default KnowledgeBase
