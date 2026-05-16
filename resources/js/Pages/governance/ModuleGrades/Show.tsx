// @governance
//   tela: /governance/module-grades/{module}
//   adrs: 0153 module-grade-v1 rubrica oficial
//   runbook: memory/requisitos/Governance/RUNBOOK-module-grades.md

import React, { useState, useMemo } from 'react'
import { Head, Link } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/Components/ui/dialog'
import PageHeader from '@/Components/shared/PageHeader'

interface BreakdownItem {
  key?: string
  desc: string
  score: number
  max: number
  evidence: string
  /** ADR 0154 v2 — true quando sub-item é N/A justificado (não conta como gap) */
  na_justified?: boolean
  /** Razão textual do N/A — exibida em italic abaixo do desc */
  na_reason?: string
}

interface Dimension {
  weight: number
  score: number
  max: number
  breakdown: BreakdownItem[]
  /** ADR 0154 v2 — true quando TODOS sub-itens da dimensão são na_justified */
  na_justified?: boolean
  /** Razão consolidada da dimensão N/A (resumo) */
  na_reason?: string
}

interface Gap {
  dimension: string
  key: string
  desc: string
  evidence: string
  lost: number
  max: number
  priority: 'P0' | 'P1' | 'P2' | 'P3'
}

interface EvolveTask {
  title: string
  module: string
  priority: string
  estimate: string
  gap_ref: string
  rationale: string
}

interface Grade {
  module: string
  score: number
  bucket: string
  color: string
  dimensions: {
    multi_tenant: Dimension
    pest_coverage: Dimension
    documentation: Dimension
    architecture: Dimension
    client_real: Dimension
  }
  gaps: Gap[]
  evolve_tasks: EvolveTask[]
  evaluated_at: string
}

interface Props {
  grade: Grade
}

const DIM_LABELS: Record<keyof Grade['dimensions'], string> = {
  multi_tenant: 'D1 — Multi-tenant Tier 0',
  pest_coverage: 'D2 — Pest cobertura',
  documentation: 'D3 — Documentação canônica',
  architecture: 'D4 — Maturidade arquitetura',
  client_real: 'D5 — Cliente real',
}

const BUCKET_STYLES: Record<string, string> = {
  Excelente: 'bg-emerald-100 text-emerald-800 border-emerald-300',
  Bom: 'bg-sky-100 text-sky-800 border-sky-300',
  Médio: 'bg-amber-100 text-amber-800 border-amber-300',
  Crítico: 'bg-orange-100 text-orange-800 border-orange-300',
  Embrião: 'bg-red-100 text-red-800 border-red-300',
}

const PRIORITY_STYLES: Record<string, string> = {
  P0: 'bg-red-100 text-red-800 border-red-300',
  P1: 'bg-orange-100 text-orange-800 border-orange-300',
  P2: 'bg-amber-100 text-amber-800 border-amber-300',
  P3: 'bg-zinc-100 text-zinc-700 border-zinc-300',
}

function scoreColorClass(score: number): string {
  if (score >= 80) return 'text-emerald-700'
  if (score >= 60) return 'text-sky-700'
  if (score >= 40) return 'text-amber-700'
  if (score >= 20) return 'text-orange-700'
  return 'text-red-700'
}

function dimColorClass(score: number, max: number, naJustified?: boolean): string {
  // ADR 0154 v2 — dimensão N/A justificada sempre verde-esmeralda
  if (naJustified) return 'text-emerald-600'
  const ratio = max === 0 ? 0 : score / max
  if (ratio >= 0.8) return 'text-emerald-600'
  if (ratio >= 0.5) return 'text-sky-600'
  if (ratio >= 0.3) return 'text-amber-600'
  return 'text-red-600'
}

/** ADR 0154 v2 — detecta se dimensão é N/A justificada
 *  (flag explícita ou TODOS sub-itens marcados na_justified) */
function isDimensionNaJustified(dim: Dimension): boolean {
  if (dim.na_justified === true) return true
  if (dim.breakdown.length === 0) return false
  return dim.breakdown.every((item) => item.na_justified === true)
}

function ModuleGradesShow({ grade }: Props): React.ReactElement {
  const [evolveOpen, setEvolveOpen] = useState(false)
  const [copied, setCopied] = useState(false)

  const evolveMarkdown = useMemo(() => generateEvolveMarkdown(grade), [grade])

  // ADR 0154 v2 — conta dimensões N/A justificadas (mostrado no header)
  const naJustifiedCount = useMemo(() => {
    return (Object.keys(DIM_LABELS) as (keyof Grade['dimensions'])[])
      .filter((key) => isDimensionNaJustified(grade.dimensions[key])).length
  }, [grade])
  const totalDims = Object.keys(DIM_LABELS).length

  function handleCopyMarkdown(): void {
    if (typeof navigator === 'undefined' || !navigator.clipboard) return
    navigator.clipboard.writeText(evolveMarkdown).then(() => {
      setCopied(true)
      setTimeout(() => setCopied(false), 2500)
    })
  }

  return (
    <>
      <Head title={`${grade.module} — Module Grade`} />
      <PageHeader
        title={`Modules/${grade.module}`}
        subtitle={`Avaliação rubrica module-grade-v1 · ${new Date(grade.evaluated_at).toLocaleString('pt-BR')}`}
        breadcrumbs={[
          { label: 'Governança', href: '/governance' },
          { label: 'Module Grades', href: '/governance/module-grades' },
          { label: grade.module },
        ]}
        actions={
          <Button
            onClick={() => setEvolveOpen(true)}
            disabled={grade.evolve_tasks.length === 0}
            size="lg"
            className="bg-emerald-600 hover:bg-emerald-700 text-white"
          >
            Evoluir ({grade.evolve_tasks.length} tasks sugeridas)
          </Button>
        }
      />

      {/* Header de nota */}
      <Card className="mb-4">
        <CardContent className="py-6 flex items-center justify-between flex-wrap gap-4">
          <div className="flex items-center gap-4">
            <div className={`text-5xl font-bold ${scoreColorClass(grade.score)}`}>{grade.score}<span className="text-2xl text-zinc-400">/100</span></div>
            <Badge className={`${BUCKET_STYLES[grade.bucket] ?? ''} text-base px-3 py-1`}>{grade.bucket}</Badge>
            {/* ADR 0154 v2 — indica dimensões N/A justificadas no header */}
            {naJustifiedCount > 0 && (
              <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300 text-sm px-3 py-1">
                {naJustifiedCount} de {totalDims} dimensões com N/A justificado
              </Badge>
            )}
          </div>
          <Link href="/governance/module-grades" className="text-sm text-sky-700 hover:underline">
            ← Voltar à lista
          </Link>
        </CardContent>
      </Card>

      {/* 5 cards dimensões */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
        {(Object.keys(DIM_LABELS) as (keyof Grade['dimensions'])[]).map((key) => {
          const dim = grade.dimensions[key]
          const dimNa = isDimensionNaJustified(dim)
          return (
            <Card key={key} className={dimNa ? 'border-emerald-200 bg-emerald-50/30' : ''}>
              <CardHeader className="pb-2">
                <CardTitle className="text-sm flex items-center justify-between gap-2">
                  <span>{DIM_LABELS[key]}</span>
                  <div className="flex items-center gap-2">
                    {/* ADR 0154 v2 — badge "N/A justificado" verde no card de dimensão */}
                    {dimNa && (
                      <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300 text-[10px] px-1.5 py-0">
                        N/A justificado
                      </Badge>
                    )}
                    <span className={`text-base font-bold ${dimColorClass(dim.score, dim.max, dimNa)}`}>
                      {dimNa ? 'N/A' : `${dim.score}/${dim.max}`}
                    </span>
                  </div>
                </CardTitle>
              </CardHeader>
              <CardContent className="pt-0">
                {/* Razão consolidada da dimensão N/A (se houver) */}
                {dimNa && dim.na_reason && (
                  <p className="text-xs text-emerald-700 italic mb-2">{dim.na_reason}</p>
                )}
                <ul className="space-y-2 text-xs">
                  {dim.breakdown.map((item, i) => (
                    <li
                      key={i}
                      className={`border-l-2 pl-2 ${item.na_justified ? 'border-emerald-300' : 'border-zinc-200'}`}
                    >
                      <div className="flex items-baseline gap-2">
                        {item.na_justified ? (
                          <span className="font-mono text-emerald-600" aria-label="N/A justificado">
                            ✓ N/A
                          </span>
                        ) : (
                          <span className={`font-mono ${dimColorClass(item.score, item.max)}`}>
                            [{item.score}/{item.max}]
                          </span>
                        )}
                        <span className="font-medium text-zinc-700">{item.key}</span>
                      </div>
                      <p className="text-zinc-600 mt-0.5">{item.desc}</p>
                      {/* Razão N/A em italic (substitui evidence padrão quando N/A) */}
                      {item.na_justified && item.na_reason ? (
                        <p className="text-emerald-700 italic">{item.na_reason}</p>
                      ) : (
                        <p className="text-zinc-400 italic">{item.evidence}</p>
                      )}
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )
        })}
      </div>

      {/* Top gaps */}
      {grade.gaps.length > 0 && (
        <Card className="mb-4">
          <CardHeader>
            <CardTitle className="text-base">Top gaps ordenados (perda absoluta de pontos)</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="space-y-2">
              {grade.gaps.slice(0, 10).map((g, i) => (
                <li key={i} className="flex items-start gap-3 text-sm">
                  <span className="font-bold text-red-600 w-12 text-right">-{g.lost}</span>
                  <Badge className={PRIORITY_STYLES[g.priority] ?? ''}>{g.priority}</Badge>
                  <code className="text-xs text-zinc-500">{g.key}</code>
                  <span className="text-zinc-700">{g.desc}</span>
                  <span className="text-xs text-zinc-400 ml-auto">{g.evidence}</span>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      )}

      {/* Modal Evoluir */}
      <Dialog open={evolveOpen} onOpenChange={setEvolveOpen}>
        <DialogContent className="max-w-3xl max-h-[80vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Evoluir Modules/{grade.module}</DialogTitle>
            <DialogDescription>
              Batch de {grade.evolve_tasks.length} tasks sugeridas pra fechar os top gaps. Copie o markdown e cole no Claude Code pra criar via{' '}
              <code className="text-xs">tasks-create</code> MCP.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-3">
            {grade.evolve_tasks.map((task, i) => (
              <Card key={i}>
                <CardContent className="py-3 space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-zinc-500 font-mono">#{i + 1}</span>
                    <Badge className={PRIORITY_STYLES[task.priority] ?? ''}>{task.priority}</Badge>
                    <span className="text-xs text-zinc-500">estimativa {task.estimate}</span>
                    <code className="text-xs text-zinc-400 ml-auto">{task.gap_ref}</code>
                  </div>
                  <h4 className="font-semibold text-sm">{task.title}</h4>
                  <p className="text-xs text-zinc-600 italic">{task.rationale}</p>
                </CardContent>
              </Card>
            ))}
          </div>

          <details className="mt-2">
            <summary className="text-xs text-zinc-500 cursor-pointer hover:text-zinc-700">Ver markdown gerado</summary>
            <pre className="mt-2 p-3 bg-zinc-50 border border-zinc-200 rounded text-xs overflow-x-auto whitespace-pre-wrap">
              {evolveMarkdown}
            </pre>
          </details>

          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setEvolveOpen(false)}>
              Fechar
            </Button>
            <Button onClick={handleCopyMarkdown} className="bg-emerald-600 hover:bg-emerald-700 text-white">
              {copied ? '✓ Copiado!' : 'Copiar Markdown'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <p className="text-xs text-zinc-500 mt-4">
        Rubrica oficial: <code>module-grade-v1</code> ·{' '}
        <Link href="/copiloto/admin/memoria?slug=0153-module-grade-rubrica-v1" className="underline">
          ADR 0153
        </Link>{' '}
        · CLI equivalente: <code className="text-xs">php artisan module:grade {grade.module} --detail --evolve</code>
      </p>
    </>
  )
}

function generateEvolveMarkdown(grade: Grade): string {
  const lines: string[] = []
  lines.push(`# Evoluir Modules/${grade.module}`)
  lines.push('')
  lines.push(`Nota atual: **${grade.score}/100** · Bucket: **${grade.bucket}**`)
  lines.push(`Avaliado: ${grade.evaluated_at}`)
  lines.push('')
  lines.push(`## Tasks sugeridas (${grade.evolve_tasks.length})`)
  lines.push('')
  grade.evolve_tasks.forEach((task, i) => {
    lines.push(`### ${i + 1}. [${task.priority}] ${task.title}`)
    lines.push('')
    lines.push(`- **Módulo:** ${task.module}`)
    lines.push(`- **Estimativa:** ${task.estimate}`)
    lines.push(`- **Gap ref:** ${task.gap_ref}`)
    lines.push(`- **Racional:** ${task.rationale}`)
    lines.push('')
  })
  lines.push('---')
  lines.push('')
  lines.push(`Cole esse bloco no Claude Code e peça pra criar as tasks via \`tasks-create\` MCP.`)
  lines.push(`Refs: ADR 0153 module-grade-v1, RUNBOOK-module-grades.md`)
  return lines.join('\n')
}

ModuleGradesShow.layout = (page: React.ReactNode): React.ReactElement => <AppShellV2>{page}</AppShellV2>

export default ModuleGradesShow
