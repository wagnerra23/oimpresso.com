// @ads
//   tela: /ads/admin/projects/{id}
//   adrs: Project Decomposer + Viability Score (Wagner modelo)

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link, router } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'
import { ArrowLeft, Zap, ArrowRight, Wallet } from 'lucide-react'

interface Part {
  id: number
  ordem: number
  codigo: string
  nome: string
  objetivo: string
  dependencias: number[]
  arquivos_estimados: string[]
  status: string
  viability_score: number | null
  risco: number | null
  estimativa_horas: number | null
  valor_estimado_brl: number | null
}

interface MetricaSucesso {
  nome: string
  alvo?: string
  atual?: string
  deadline_dias?: number
}

interface Project {
  id: number
  codigo: string
  nome: string
  objetivo_macro: string
  metricas_sucesso: MetricaSucesso[]
  constraints: any
  status: string
  decision: string
  viability_score: number | null
  custo_estimado_brl: number | null
  prazo_estimado_dias: number | null
  owner: string
  created_at: string
}

interface DecisionLink {
  id: number
  event_type: string
  domain: string
  destination: string
  outcome: string
  review_score: number | null
}

interface Props {
  project: Project
  parts: Part[]
  decisions: DecisionLink[]
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)
const brl = (v: number | null) => v === null ? '—' : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)

function viabilityColor(score: number | null): string {
  if (score === null) return 'bg-zinc-100 text-zinc-700'
  if (score >= 80) return 'bg-emerald-100 text-emerald-700 border-emerald-300'
  if (score >= 60) return 'bg-blue-100 text-blue-700 border-blue-300'
  if (score >= 40) return 'bg-amber-100 text-amber-700 border-amber-300'
  return 'bg-red-100 text-red-700 border-red-300'
}

function statusBadge(status: string): { label: string; color: string } {
  return ({
    pending:     { label: 'Pendente',     color: 'bg-zinc-100 text-zinc-700' },
    planning:    { label: 'Planejando',   color: 'bg-blue-100 text-blue-700' },
    in_progress: { label: 'Em execução',  color: 'bg-amber-100 text-amber-700' },
    done:        { label: 'Concluído',    color: 'bg-emerald-100 text-emerald-700' },
    blocked:     { label: 'Bloqueado',    color: 'bg-red-100 text-red-700' },
    cancelled:   { label: 'Cancelado',    color: 'bg-zinc-100 text-zinc-500' },
  } as Record<string, any>)[status] ?? { label: status, color: '' }
}

const ProjectShow: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ project: p, parts, decisions }) => {
  const decompose = () => {
    if (!confirm(`Decompor "${p.nome}"? Vai chamar Claude Sonnet (~30s, ~5k tokens).`)) return
    router.post(`/ads/admin/projects/${p.id}/decompose`)
  }

  const hasDecomposition = parts.length > 0

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <Link href="/ads/admin/projects" className="text-sm text-muted-foreground hover:text-foreground inline-flex items-center gap-1">
        <ArrowLeft className="w-4 h-4" /> Voltar
      </Link>

      <PageHeader
        icon="folder-kanban"
        title={`${p.codigo} — ${p.nome}`}
        description={p.objetivo_macro}
        action={!hasDecomposition && p.status === 'draft' && (
          <Button onClick={decompose}>
            <Zap className="w-4 h-4 mr-1" /> Decompor com IA
          </Button>
        )}
      />

      {/* KPIs estratégicos */}
      <KpiGrid cols={4}>
        <KpiCard
          icon="bar-chart-3"
          tone={p.viability_score !== null && p.viability_score >= 70 ? 'success' : p.viability_score !== null && p.viability_score >= 40 ? 'warning' : 'danger'}
          label="Viability score"
          value={p.viability_score !== null ? `${p.viability_score}%` : '—'}
          description="Probabilidade de proceder"
        />
        <KpiCard
          icon="dollar-sign"
          tone="default"
          label="Custo estimado"
          value={brl(p.custo_estimado_brl)}
          description="Soma das parts"
        />
        <KpiCard
          icon="clock"
          tone="info"
          label="Prazo estimado"
          value={p.prazo_estimado_dias ? `${p.prazo_estimado_dias} dias` : '—'}
          description="Soma sequencial"
        />
        <KpiCard
          icon="layers"
          tone="default"
          label="Parts"
          value={num(parts.length)}
          description={`${parts.filter(pt => pt.status === 'done').length} concluídas`}
        />
      </KpiGrid>

      {/* Decomposição visual */}
      {!hasDecomposition ? (
        <Card>
          <CardContent className="p-0">
            <EmptyState
              icon="zap"
              title="Project ainda não decomposto"
              description="Clique em 'Decompor com IA' acima. Project Decomposer Agent (Sonnet) vai gerar 5-8 parts com viability score, dependências e estimativas. Custo: ~$0.05."
            />
          </CardContent>
        </Card>
      ) : (
        <>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Parts (decomposição estratégica)</CardTitle>
              <p className="text-sm text-muted-foreground">
                Ordem sequencial. Cada part agrupa decisões executáveis. Dependências indicadas.
              </p>
            </CardHeader>
            <CardContent>
              <ol className="space-y-3">
                {parts.map(part => {
                  const sb = statusBadge(part.status)
                  return (
                    <li key={part.id} className="border rounded-lg p-3 space-y-2">
                      <div className="flex items-start justify-between gap-3">
                        <div className="flex-1 min-w-0 space-y-1">
                          <div className="flex items-center gap-2 flex-wrap">
                            <code className="text-xs bg-zinc-100 px-1.5 py-0.5 rounded font-mono">{part.ordem}. {part.codigo}</code>
                            <span className="font-medium">{part.nome}</span>
                            <Badge className={sb.color}>{sb.label}</Badge>
                          </div>
                          <p className="text-sm text-muted-foreground">{part.objetivo}</p>
                        </div>
                        <div className="shrink-0 flex flex-col items-end gap-1 text-xs">
                          <Badge className={`border ${viabilityColor(part.viability_score)}`}>
                            Viab {part.viability_score ?? '—'}%
                          </Badge>
                          {part.risco !== null && (
                            <Badge variant="outline">Risco {part.risco}%</Badge>
                          )}
                        </div>
                      </div>

                      <div className="flex items-center gap-3 text-xs text-muted-foreground flex-wrap">
                        {part.estimativa_horas && <span>⏱ {part.estimativa_horas}h</span>}
                        {part.valor_estimado_brl && <span className="inline-flex items-center"><Wallet className="h-3 w-3 mr-1" /> {brl(part.valor_estimado_brl)}</span>}
                        {part.dependencias.length > 0 && (
                          <span>↳ depende de: {part.dependencias.join(', ')}</span>
                        )}
                      </div>

                      {part.arquivos_estimados.length > 0 && (
                        <details className="text-xs">
                          <summary className="cursor-pointer text-muted-foreground hover:text-foreground">
                            {part.arquivos_estimados.length} arquivo(s) estimado(s)
                          </summary>
                          <ul className="mt-1 space-y-0.5 font-mono">
                            {part.arquivos_estimados.map(a => (
                              <li key={a} className="text-muted-foreground">• {a}</li>
                            ))}
                          </ul>
                        </details>
                      )}
                    </li>
                  )
                })}
              </ol>
            </CardContent>
          </Card>

          {/* Métricas de sucesso */}
          {p.metricas_sucesso.length > 0 && (
            <Card>
              <CardHeader><CardTitle className="text-base">Métricas de sucesso</CardTitle></CardHeader>
              <CardContent>
                <ul className="space-y-2">
                  {p.metricas_sucesso.map((m, i) => (
                    <li key={i} className="text-sm">
                      <strong>{m.nome}</strong>
                      {m.alvo && <span className="text-muted-foreground"> · alvo: {m.alvo}</span>}
                      {m.deadline_dias && <span className="text-muted-foreground"> · {m.deadline_dias} dias</span>}
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}

          {/* Decisions linkadas */}
          {decisions.length > 0 && (
            <Card>
              <CardHeader><CardTitle className="text-base">Decisões geradas neste project ({decisions.length})</CardTitle></CardHeader>
              <CardContent>
                <ul className="space-y-1.5">
                  {decisions.map(d => (
                    <li key={d.id} className="text-sm flex items-center gap-2 flex-wrap">
                      <Link href={`/ads/admin/decisoes/${d.id}`} className="font-mono text-xs text-primary hover:underline">
                        #{String(d.id).padStart(4, '0')}
                      </Link>
                      <code className="text-xs">{d.event_type}</code>
                      <Badge variant="outline">{d.destination}</Badge>
                      <Badge variant="outline">{d.outcome}</Badge>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}
        </>
      )}
    </div>
  )
}

ProjectShow.layout = (page: ReactNode) => (
  <AppShellV2
    title="ADS — Project"
    breadcrumbItems={[{ label: 'ADS' }, { label: 'Projects', href: '/ads/admin/projects' }, { label: 'Detalhe' }]}
  >
    {page}
  </AppShellV2>
)

export default ProjectShow
