// @ads
//   tela: /ads/admin/projects
//   adrs: Project Decomposer + Governance Layer (Wagner modelo)

import React, { useState, type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link, router, useForm } from '@inertiajs/react'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Label } from '@/Components/ui/label'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'
import { Plus, X } from 'lucide-react'

interface Project {
  id: number
  codigo: string
  nome: string
  objetivo_macro: string
  status: string
  decision: string
  viability_score: number | null
  custo_estimado_brl: number | null
  prazo_estimado_dias: number | null
  parts_total: number
  parts_done: number
  progress_pct: number
  created_at: string
}

interface Props {
  projects: Project[]
  kpis: { total: number; active: number; draft: number; completed: number }
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)
const brl = (v: number | null) => v === null ? '—' : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

const statusColor: Record<string, string> = {
  draft:     'bg-zinc-100 text-zinc-700',
  active:    'bg-blue-100 text-blue-700',
  paused:    'bg-amber-100 text-amber-700',
  completed: 'bg-emerald-100 text-emerald-700',
  killed:    'bg-red-100 text-red-700',
}

const Projects: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ projects, kpis }) => {
  const [showCreate, setShowCreate] = useState(false)
  const form = useForm({ nome: '', objetivo_macro: '' })

  const create = (e: React.FormEvent) => {
    e.preventDefault()
    form.post('/ads/admin/projects', {
      onSuccess: () => setShowCreate(false),
      onFinish: () => form.reset(),
    })
  }

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="folder-kanban"
        title="ADS — Projects"
        description="Unidade estratégica que agrupa decisões + ADRs + decomposição. Cada Project é decomposto pelo Project Decomposer Agent em Parts executáveis com viability score."
        action={
          <Button onClick={() => setShowCreate(!showCreate)}>
            {showCreate ? <><X className="w-4 h-4 mr-1" /> Cancelar</> : <><Plus className="w-4 h-4 mr-1" /> Novo Project</>}
          </Button>
        }
      />

      <KpiGrid cols={4}>
        <KpiCard icon="folder-kanban" tone="info"    label="Total Projects" value={num(kpis.total)}    description="Cadastrados" />
        <KpiCard icon="zap"           tone="success" label="Ativos"         value={num(kpis.active)}   description="Em execução" />
        <KpiCard icon="edit-3"        tone="default" label="Draft"          value={num(kpis.draft)}    description="Aguardando decompose" />
        <KpiCard icon="check-circle-2" tone="success" label="Concluídos"    value={num(kpis.completed)} description="100% parts done" />
      </KpiGrid>

      {showCreate && (
        <Card>
          <CardContent className="py-4">
            <form onSubmit={create} className="space-y-3">
              <div>
                <Label htmlFor="nome">Nome do Project</Label>
                <Input
                  id="nome"
                  value={form.data.nome}
                  onChange={e => form.setData('nome', e.target.value)}
                  placeholder="Ex: Criar Módulo Compras Externas"
                  required
                />
                {form.errors.nome && <p className="text-xs text-destructive mt-1">{form.errors.nome}</p>}
              </div>
              <div>
                <Label htmlFor="objetivo">Objetivo macro</Label>
                <Textarea
                  id="objetivo"
                  value={form.data.objetivo_macro}
                  onChange={e => form.setData('objetivo_macro', e.target.value)}
                  rows={4}
                  placeholder="Descreva o que o project precisa entregar. Ex: 'Adicionar módulo Modules/Compras que permite registrar compras externas (não relacionadas a fornecedores cadastrados), com rateio em centros de custo e integração com Financeiro'"
                  required
                />
                {form.errors.objetivo_macro && <p className="text-xs text-destructive mt-1">{form.errors.objetivo_macro}</p>}
              </div>
              <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>
                  {form.processing ? 'Criando…' : 'Criar Project (status=draft)'}
                </Button>
                <p className="text-xs text-muted-foreground self-center">
                  Após criar, clique em <strong>Decompor</strong> no detalhe pra rodar o Project Decomposer Agent.
                </p>
              </div>
            </form>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardContent className="p-0">
          {projects.length === 0 ? (
            <EmptyState
              icon="folder-kanban"
              title="Nenhum project ainda"
              description="Cria seu primeiro project clicando em 'Novo Project' acima. Exemplo: 'Criar Módulo Compras Externas'."
            />
          ) : (
            <ul className="divide-y divide-border">
              {projects.map(p => (
                <li key={p.id} className="px-4 py-4 hover:bg-muted/30">
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex-1 min-w-0 space-y-2">
                      <div className="flex items-center gap-2 flex-wrap">
                        <code className="text-xs bg-zinc-100 px-1.5 py-0.5 rounded font-mono">{p.codigo}</code>
                        <Link href={`/ads/admin/projects/${p.id}`} className="font-medium hover:underline">
                          {p.nome}
                        </Link>
                        <Badge className={statusColor[p.status] ?? ''}>{p.status}</Badge>
                        {p.viability_score !== null && (
                          <Badge variant="outline">Viab {p.viability_score}%</Badge>
                        )}
                      </div>
                      <p className="text-sm text-muted-foreground line-clamp-2">{p.objetivo_macro}</p>
                      <div className="flex items-center gap-3 text-xs text-muted-foreground">
                        <span>Parts {p.parts_done}/{p.parts_total}</span>
                        {p.parts_total > 0 && (
                          <div className="w-32 h-1.5 bg-muted rounded overflow-hidden">
                            <div className="h-full bg-primary" style={{ width: `${p.progress_pct}%` }} />
                          </div>
                        )}
                        {p.custo_estimado_brl && <span>· {brl(p.custo_estimado_brl)}</span>}
                        {p.prazo_estimado_dias && <span>· {p.prazo_estimado_dias} dias</span>}
                      </div>
                    </div>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

Projects.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Projects" breadcrumbItems={[{ label: 'ADS' }, { label: 'Projects' }]}>
    {page}
  </AppShellV2>
)

export default Projects
