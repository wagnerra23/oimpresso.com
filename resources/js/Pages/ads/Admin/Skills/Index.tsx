// @ads
//   tela: /ads/admin/skills
//   adrs: 0076 (DB primary, mas V0 lê filesystem direto — MVP read-only)
//   nota: substitui o alias semântico antigo que apontava pra PatternsController

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link } from '@inertiajs/react'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Input } from '@/Components/ui/input'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'
import { Zap, BookOpen, FileText, CheckCircle } from 'lucide-react'
import { Button } from '@/Components/ui/button'

interface Skill {
  slug: string
  name: string
  description: string
  module: string | null
  git_path: string
  body_chars: number
  source: 'db' | 'filesystem'
}

interface Props {
  skills: Skill[]
  kpis: { total: number; with_module: number; avg_body: number }
}

const sourceLabel: Record<Skill['source'], string> = {
  db: 'DB',
  filesystem: 'Filesystem (fallback)',
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)

const Skills: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ skills, kpis }) => {
  const [filter, setFilter] = React.useState('')

  const filtered = React.useMemo(() => {
    const q = filter.trim().toLowerCase()
    if (!q) return skills
    return skills.filter(s =>
      s.slug.toLowerCase().includes(q) ||
      s.name.toLowerCase().includes(q) ||
      s.description.toLowerCase().includes(q)
    )
  }, [skills, filter])

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="zap"
        title="Skills"
        description="Skills do Claude Code disponíveis no projeto. ADR 0076 — DB primary com fallback filesystem se import inicial não rodou."
        action={
          <div className="flex items-center gap-2">
            <Link href="/ads/admin/skills-review">
              <Button size="sm" variant="outline">
                <CheckCircle className="w-4 h-4 mr-1" /> Approval queue
              </Button>
            </Link>
            {skills.length > 0 && (
              <Badge variant={skills[0].source === 'db' ? 'default' : 'outline'}>
                {sourceLabel[skills[0].source]}
              </Badge>
            )}
          </div>
        }
      />

      <KpiGrid cols={3}>
        <KpiCard label="Skills disponíveis" value={num(kpis.total)} icon={<Zap className="w-4 h-4" />} />
        <KpiCard label="Com módulo" value={num(kpis.with_module)} icon={<BookOpen className="w-4 h-4" />} />
        <KpiCard label="Tamanho médio (chars)" value={num(kpis.avg_body)} icon={<FileText className="w-4 h-4" />} />
      </KpiGrid>

      <div className="flex items-center gap-2">
        <Input
          type="text"
          placeholder="Buscar por slug, nome ou descrição..."
          value={filter}
          onChange={e => setFilter(e.target.value)}
          className="max-w-md"
        />
        {filter && (
          <span className="text-xs text-muted-foreground">
            {filtered.length} de {skills.length} skill{skills.length === 1 ? '' : 's'}
          </span>
        )}
      </div>

      {filtered.length === 0 ? (
        <EmptyState
          icon="zap"
          title={filter ? 'Nenhuma skill bate com o filtro' : 'Nenhuma skill encontrada'}
          description={filter ? 'Tente outro termo de busca.' : 'Adicione skills em .claude/skills/<slug>/SKILL.md no repositório.'}
        />
      ) : (
        <Card>
          <CardContent className="p-0">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 text-xs uppercase">
                <tr>
                  <th className="text-left px-4 py-2 w-[20%]">Slug</th>
                  <th className="text-left px-4 py-2 w-[20%]">Nome</th>
                  <th className="text-left px-4 py-2">Descrição</th>
                  <th className="text-left px-4 py-2 w-[10%]">Módulo</th>
                  <th className="text-right px-4 py-2 w-[10%]">Tamanho</th>
                </tr>
              </thead>
              <tbody>
                {filtered.map(s => (
                  <tr key={s.slug} className="border-t hover:bg-muted/30">
                    <td className="px-4 py-2 font-mono text-xs">
                      <Link
                        href={`/ads/admin/skills/${s.slug}`}
                        className="text-primary hover:underline"
                      >
                        {s.slug}
                      </Link>
                    </td>
                    <td className="px-4 py-2">{s.name}</td>
                    <td className="px-4 py-2 text-muted-foreground line-clamp-2">{s.description}</td>
                    <td className="px-4 py-2">
                      {s.module ? <Badge variant="secondary">{s.module}</Badge> : <span className="text-muted-foreground text-xs">—</span>}
                    </td>
                    <td className="px-4 py-2 text-right text-xs text-muted-foreground tabular-nums">
                      {num(s.body_chars)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>
      )}

      <p className="text-xs text-muted-foreground">
        Esta é a versão V0 (read-only, lê filesystem direto). CYCLE-02 entrega edição inline,
        versionamento DB, history, rationale 4 campos e drift detection.
        Ver <a href="https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0076-skills-db-primary-git-destino-drift-alert.md" className="underline" target="_blank" rel="noreferrer">ADR 0076</a>.
      </p>
    </div>
  )
}

Skills.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Skills" breadcrumbItems={[{ label: 'ADS' }, { label: 'Skills' }]}>
    {page}
  </AppShellV2>
)

export default Skills
