// @governance
//   tela: /governance/module-grades/{module}
//   adrs: 0153 module-grade-v1 rubrica oficial, 0154 v2 N/A justificado, 0155 v3 9 dimensões
//   runbook: memory/requisitos/Governance/RUNBOOK-module-grades.md

import React, { useState, useMemo } from 'react'
import { Head, Link, Deferred } from '@inertiajs/react'
import ReactMarkdown from 'react-markdown'
import remarkGfm from 'remark-gfm'
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
import { ScrollArea } from '@/Components/ui/scroll-area'
import PageHeader from '@/Components/shared/PageHeader'
import { FileText, Shield } from 'lucide-react'

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
  /** Score normalizado /100 (compat v1/v2 + ADR 0155 v3) */
  score: number
  /** ADR 0155 v3 — score raw /118 (soma de pesos das 9 dimensões); opcional p/ compat v1/v2 */
  score_v3_raw?: number
  bucket: string
  color: string
  dimensions: {
    multi_tenant: Dimension
    pest_coverage: Dimension
    documentation: Dimension
    architecture: Dimension
    client_real: Dimension
    /** ADR 0155 v3 — 4 dimensões novas (opcionais p/ compat retroativa) */
    performance?: Dimension
    lgpd?: Dimension
    security?: Dimension
    observability?: Dimension
  }
  gaps: Gap[]
  evolve_tasks: EvolveTask[]
  evaluated_at: string
}

interface HistoryPoint {
  score: number
  bucket: string
  snapshot_at: string
}

/** Charter Goal 9 (2026-05-17) — entry do dossier markdown do módulo. */
interface DossierDoc {
  slug: string
  label: string
  filename: string
  content_md: string
  size_chars: number
  modified_at: string | null
}

interface Props {
  grade: Grade
  /** ADR 0155 v3 — últimos 7 snapshots de mcp_module_grades_history (deferred) */
  history?: HistoryPoint[]
  /** Charter Goal 9 — dossier docs lidos de memory/requisitos/<name>/ (deferred). */
  dossier?: DossierDoc[]
}

/** Sparkline 7d Tailwind-only — 7 barras verticais com altura ~ score/100. */
function Sparkline7d({ history }: { history: HistoryPoint[] }): React.ReactElement {
  if (history.length === 0) {
    return (
      <div className="text-[11px] text-zinc-400 italic">
        Sem histórico ainda — primeiro snapshot cron 06:05 BRT.
      </div>
    )
  }

  const last = history[history.length - 1]
  const first = history[0]
  const delta = last.score - first.score
  const deltaLabel =
    history.length > 1
      ? delta > 0
        ? `+${delta}`
        : delta < 0
        ? `${delta}`
        : '±0'
      : '—'
  const deltaClass =
    delta > 0 ? 'text-emerald-600' : delta < 0 ? 'text-red-600' : 'text-zinc-500'

  return (
    <div
      className="flex items-end gap-0.5 h-10"
      role="img"
      aria-label={`Sparkline 7 dias — variação ${deltaLabel} pontos · último ${last.score}/100`}
      title={history
        .map((p) => `${new Date(p.snapshot_at).toLocaleDateString('pt-BR')}: ${p.score}/100`)
        .join('\n')}
    >
      {history.map((point, i) => {
        const heightPct = Math.max(4, Math.min(100, point.score))
        const barColor =
          point.score >= 80
            ? 'bg-emerald-500'
            : point.score >= 60
            ? 'bg-sky-500'
            : point.score >= 40
            ? 'bg-amber-500'
            : point.score >= 20
            ? 'bg-orange-500'
            : 'bg-red-500'
        return (
          <div
            key={i}
            className={`w-2 ${barColor} rounded-sm`}
            style={{ height: `${heightPct}%` }}
          />
        )
      })}
      <div className="ml-1 text-[10px] leading-tight">
        <div className={`font-semibold ${deltaClass}`}>{deltaLabel}</div>
        <div className="text-zinc-400 uppercase tracking-wide">7d</div>
      </div>
    </div>
  )
}

const DIM_LABELS: Record<keyof Grade['dimensions'], string> = {
  multi_tenant: 'D1 — Multi-tenant Tier 0',
  pest_coverage: 'D2 — Pest cobertura',
  documentation: 'D3 — Documentação canônica',
  architecture: 'D4 — Maturidade arquitetura',
  client_real: 'D5 — Cliente real',
  // ADR 0155 v3 — dimensões novas
  performance: 'D6 — Performance',
  lgpd: 'D7 — LGPD',
  security: 'D8 — Security',
  observability: 'D9 — Observability',
}

/** ADR 0155 v3 — dimensões que são "NOVO v3" (ganham badge no header do card) */
const V3_NEW_DIMS = new Set<keyof Grade['dimensions']>([
  'performance',
  'lgpd',
  'security',
  'observability',
])

/** ADR 0155 v3 — cor de accent por dimensão (border-left + badge tone) */
const DIM_ACCENT: Partial<Record<keyof Grade['dimensions'], string>> = {
  performance: 'border-l-4 border-l-purple-400',
  lgpd: 'border-l-4 border-l-pink-400',
  security: 'border-l-4 border-l-indigo-400',
  observability: 'border-l-4 border-l-cyan-400',
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

function ModuleGradesShow({ grade, history, dossier }: Props): React.ReactElement {
  const [evolveOpen, setEvolveOpen] = useState(false)
  const [copied, setCopied] = useState(false)
  // Charter Goal 9 — slug do doc dossier aberto no Dialog (null = fechado)
  const [openedDossierSlug, setOpenedDossierSlug] = useState<string | null>(null)
  const [dossierCopied, setDossierCopied] = useState(false)

  const openedDossierDoc = useMemo<DossierDoc | null>(() => {
    if (!openedDossierSlug || !dossier) return null
    return dossier.find((d) => d.slug === openedDossierSlug) ?? null
  }, [openedDossierSlug, dossier])

  function handleCopyDossierMarkdown(): void {
    if (typeof navigator === 'undefined' || !navigator.clipboard || !openedDossierDoc) return
    navigator.clipboard.writeText(openedDossierDoc.content_md).then(() => {
      setDossierCopied(true)
      setTimeout(() => setDossierCopied(false), 2500)
    })
  }

  const evolveMarkdown = useMemo(() => generateEvolveMarkdown(grade), [grade])

  // ADR 0155 v3 — só lista as dimensões realmente presentes (compat retroativa v1/v2 — sem D6-D9)
  const presentDims = useMemo<(keyof Grade['dimensions'])[]>(() => {
    return (Object.keys(DIM_LABELS) as (keyof Grade['dimensions'])[])
      .filter((key) => grade.dimensions[key] !== undefined)
  }, [grade])

  // ADR 0154 v2 — conta dimensões N/A justificadas (mostrado no header)
  const naJustifiedCount = useMemo(() => {
    return presentDims.filter((key) => isDimensionNaJustified(grade.dimensions[key] as Dimension)).length
  }, [grade, presentDims])
  const totalDims = presentDims.length

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
          <div className="flex items-center gap-4 flex-wrap">
            <div className={`text-5xl font-bold ${scoreColorClass(grade.score)}`}>
              {grade.score}
              <span className="text-2xl text-zinc-400">/100</span>
            </div>
            {/* ADR 0155 v3 — score raw /118 em fonte pequena ao lado do normalizado */}
            {typeof grade.score_v3_raw === 'number' && (
              <div className="text-xs text-zinc-500 leading-tight">
                <div className="font-mono">{grade.score_v3_raw}<span className="text-zinc-400">/118</span></div>
                <div className="text-[10px] uppercase tracking-wide text-zinc-400">raw v3</div>
              </div>
            )}
            <Badge className={`${BUCKET_STYLES[grade.bucket] ?? ''} text-base px-3 py-1`}>{grade.bucket}</Badge>
            {/* ADR 0154 v2 — indica dimensões N/A justificadas no header */}
            {naJustifiedCount > 0 && (
              <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300 text-sm px-3 py-1">
                {naJustifiedCount} de {totalDims} dimensões com N/A justificado
              </Badge>
            )}
            {/* ADR 0155 v3 — sparkline 7d real consumindo `history` deferred prop */}
            <div className="px-3 py-2 rounded-md border border-zinc-200 bg-white text-xs text-zinc-600">
              <div className="text-[10px] uppercase tracking-wide text-zinc-400 mb-1">Evolução 7d</div>
              <Deferred
                data="history"
                fallback={
                  <div className="flex items-end gap-0.5 h-10" aria-busy="true">
                    {Array.from({ length: 7 }).map((_, i) => (
                      <div
                        key={i}
                        className="w-2 bg-zinc-200 rounded-sm animate-pulse"
                        style={{ height: '40%' }}
                      />
                    ))}
                  </div>
                }
              >
                <Sparkline7d history={history ?? []} />
              </Deferred>
            </div>
          </div>
          <Link href="/governance/module-grades" className="text-sm text-sky-700 hover:underline">
            ← Voltar à lista
          </Link>
        </CardContent>
      </Card>

      {/* Charter Goal 9 — Dossier do módulo (markdown canônico de memory/requisitos/<name>/).
          Inertia::defer pula closure se partial reload não pedir. Card discreto entre Header
          e Grid de dimensões — Wagner enxerga narrativa qualitativa (Capterra, BRIEFING,
          DEPRECATION-PLAN) lado-a-lado com nota técnica. Resolve fragmentação detectada
          sessão 2026-05-17. */}
      <Deferred data="dossier" fallback={null}>
        {dossier && dossier.length > 0 ? (
          <Card className="mb-4">
            <CardHeader className="pb-2">
              <CardTitle className="text-base flex items-center gap-2">
                <FileText className="w-4 h-4 text-sky-700" />
                Dossier do módulo
                <Badge className="bg-sky-100 text-sky-800 border-sky-300 text-[10px] px-1.5 py-0">
                  {dossier.length} doc{dossier.length === 1 ? '' : 's'}
                </Badge>
              </CardTitle>
            </CardHeader>
            <CardContent className="pt-0">
              <p className="text-xs text-zinc-500 mb-3">
                Narrativa qualitativa canônica em <code className="text-[11px]">memory/requisitos/{grade.module}/</code> —
                complementa a rubrica técnica D1-D9 acima.
              </p>
              <div className="flex flex-wrap gap-2">
                {dossier.map((doc) => (
                  <button
                    key={doc.slug}
                    type="button"
                    onClick={() => setOpenedDossierSlug(doc.slug)}
                    className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border border-zinc-200 bg-white hover:bg-sky-50 hover:border-sky-300 text-xs text-zinc-700 transition-colors"
                    title={`${doc.size_chars.toLocaleString('pt-BR')} caracteres · modificado ${doc.modified_at ?? '—'}`}
                  >
                    <FileText className="w-3 h-3 text-sky-600" />
                    <span className="font-medium">{doc.label}</span>
                    <span className="text-[10px] text-zinc-400 font-mono">
                      {Math.round(doc.size_chars / 1024)}KB
                    </span>
                  </button>
                ))}
              </div>
            </CardContent>
          </Card>
        ) : null}
      </Deferred>

      {/* Cards de dimensões — responsivo: 1 col mobile / 2 col tablet / 3 col desktop */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
        {presentDims.map((key) => {
          const dim = grade.dimensions[key] as Dimension
          const dimNa = isDimensionNaJustified(dim)
          const isV3New = V3_NEW_DIMS.has(key)
          const accent = DIM_ACCENT[key] ?? ''
          return (
            <Card key={key} className={`${dimNa ? 'border-emerald-200 bg-emerald-50/30' : ''} ${accent}`}>
              <CardHeader className="pb-2">
                <CardTitle className="text-sm flex items-center justify-between gap-2">
                  <span className="flex items-center gap-2 flex-wrap">
                    {DIM_LABELS[key]}
                    {/* ADR 0155 v3 — badge "NOVO v3" pra dimensões D6-D9 */}
                    {isV3New && (
                      <Badge className="bg-violet-100 text-violet-800 border-violet-300 text-[10px] px-1.5 py-0 font-semibold">
                        NOVO v3
                      </Badge>
                    )}
                  </span>
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
                  <p className="text-xs text-success italic mb-2">{dim.na_reason}</p>
                )}
                <ul className="space-y-2 text-xs">
                  {dim.breakdown.map((item, i) => (
                    <li
                      key={i}
                      className={`border-l-2 pl-2 ${item.na_justified ? 'border-emerald-300' : 'border-zinc-200'}`}
                    >
                      <div className="flex items-baseline gap-2">
                        {item.na_justified ? (
                          <span className="font-mono text-success" aria-label="N/A justificado">
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
                        <p className="text-success italic">{item.na_reason}</p>
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
                  <span className="font-bold text-destructive w-12 text-right">-{g.lost}</span>
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

      {/* Charter Goal 9 — Dialog dossier markdown renderer. ReactMarkdown + remarkGfm
          mesma stack do KB Index. ScrollArea pra docs longos (DEPRECATION-PLAN ~470
          linhas). Botão Copiar pra Wagner colar no Claude pra contexto. */}
      <Dialog
        open={openedDossierSlug !== null}
        onOpenChange={(open) => !open && setOpenedDossierSlug(null)}
      >
        <DialogContent className="max-w-4xl max-h-[85vh] flex flex-col">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <FileText className="w-4 h-4 text-sky-700" />
              {openedDossierDoc?.label ?? 'Dossier'}
            </DialogTitle>
            <DialogDescription className="text-xs">
              {openedDossierDoc && (
                <>
                  <code className="text-[11px]">memory/requisitos/{grade.module}/{openedDossierDoc.filename}</code>
                  {' · '}
                  {openedDossierDoc.size_chars.toLocaleString('pt-BR')} caracteres
                  {openedDossierDoc.modified_at ? ` · modificado ${openedDossierDoc.modified_at}` : ''}
                </>
              )}
            </DialogDescription>
          </DialogHeader>

          <ScrollArea className="flex-1 -mx-6 px-6 border-y border-zinc-100">
            <article className="prose prose-sm max-w-none py-4
              prose-headings:text-zinc-900 prose-headings:font-semibold
              prose-h1:text-2xl prose-h2:text-xl prose-h3:text-base prose-h4:text-sm
              prose-p:text-sm prose-p:leading-relaxed
              prose-code:text-xs prose-code:bg-zinc-100 prose-code:px-1 prose-code:py-0.5 prose-code:rounded
              prose-pre:bg-zinc-50 prose-pre:border prose-pre:border-zinc-200 prose-pre:text-xs
              prose-a:text-sky-700 prose-a:no-underline hover:prose-a:underline
              prose-table:text-xs prose-th:text-xs prose-td:py-1 prose-td:px-2
              prose-blockquote:border-l-4 prose-blockquote:border-sky-500 prose-blockquote:pl-4 prose-blockquote:italic prose-blockquote:text-zinc-600
              prose-hr:my-6 prose-hr:border-zinc-200
              prose-strong:text-zinc-900
              prose-li:my-0.5">
              <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                components={{
                  a: ({ href, children, ...rest }) => {
                    const isExternal = href && /^(https?:|mailto:)/.test(href)
                    return isExternal
                      ? <a href={href} target="_blank" rel="noopener noreferrer" {...rest}>{children}</a>
                      : <a href={href} {...rest}>{children}</a>
                  },
                }}
              >
                {openedDossierDoc?.content_md ?? ''}
              </ReactMarkdown>
            </article>
          </ScrollArea>

          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setOpenedDossierSlug(null)}>
              Fechar
            </Button>
            <Button onClick={handleCopyDossierMarkdown} className="bg-sky-600 hover:bg-sky-700 text-white">
              {dossierCopied ? '✓ Copiado!' : 'Copiar Markdown'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Gate CI anti-regressão — info pro time MCP entender comportamento do merge */}
      <Card className="mt-4 border-sky-200 bg-sky-50/50">
        <CardContent className="py-3">
          <div className="flex items-start gap-3">
            <Shield className="w-4 h-4 text-sky-700 mt-0.5 flex-shrink-0" aria-hidden />
            <div className="text-xs text-zinc-700 space-y-1.5">
              <p className="font-semibold text-sky-900">Gate CI anti-regressão ativo</p>
              <p className="text-zinc-600">
                Se a nota deste módulo <strong>cair</strong> em uma PR, o merge é bloqueado automaticamente.
                Override: aplicar a label <code className="text-[11px] px-1 py-0.5 bg-amber-100 text-amber-900 rounded">module-grades-allowed-regression</code> na PR.
              </p>
              <p className="text-zinc-600">
                <a href="https://github.com/wagnerra23/oimpresso.com/blob/main/.github/workflows/module-grades-gate.yml" target="_blank" rel="noreferrer" className="text-sky-700 hover:underline">Workflow GitHub Actions</a>{' '}
                ·{' '}
                <a href="https://github.com/wagnerra23/oimpresso.com/blob/main/governance/module-grades-baseline.json" target="_blank" rel="noreferrer" className="text-sky-700 hover:underline">Baseline JSON</a>{' '}
                ·{' '}
                <a href="https://github.com/wagnerra23/oimpresso.com/blob/main/memory/requisitos/Governance/RUNBOOK-module-grades.md" target="_blank" rel="noreferrer" className="text-sky-700 hover:underline">RUNBOOK módulo</a>{' '}
                ·{' '}
                <Link href="/copiloto/admin/memoria?slug=0155-module-grade-rubrica-v3-9-dimensoes" className="text-sky-700 hover:underline">ADR 0155</Link>
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      <p className="text-xs text-zinc-500 mt-4">
        Rubrica oficial: <code>module-grade-v3</code> ·{' '}
        <Link href="/copiloto/admin/memoria?slug=0153-module-grade-rubrica-v1" className="underline">
          ADR 0153
        </Link>{' '}
        ·{' '}
        <Link href="/copiloto/admin/memoria?slug=0154-module-grade-rubrica-v2-na-justificado" className="underline">
          ADR 0154 v2
        </Link>{' '}
        ·{' '}
        <Link href="/copiloto/admin/memoria?slug=0155-module-grade-rubrica-v3-9-dimensoes" className="underline">
          ADR 0155 v3
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
