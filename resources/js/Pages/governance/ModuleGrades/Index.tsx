// @governance
//   tela: /governance/module-grades
//   adrs: 0153 module-grade-v1 rubrica oficial, 0155 v3 9 dimensões
//   runbook: memory/requisitos/Governance/RUNBOOK-module-grades.md

import React, { useState, useMemo } from 'react'
import { Head, Link, Deferred } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import { Shield, TriangleAlert, Info } from 'lucide-react'

interface GradeRow {
  module: string
  score: number
  /** ADR 0155 v3 — raw /118 (opcional p/ compat retroativa) */
  score_v3_raw?: number
  bucket: 'Excelente' | 'Bom' | 'Médio' | 'Crítico' | 'Embrião'
  color: string
  dimensions: {
    multi_tenant: string
    pest_coverage: string
    documentation: string
    architecture: string
    client_real: string
    // ADR 0155 v3 — 4 novas dimensões compactas (opcionais p/ compat retroativa)
    performance?: string
    lgpd?: string
    security?: string
    observability?: string
  }
}

interface Kpis {
  average: number
  total: number
  by_bucket: Record<string, number>
}

// ── Aba "Catálogo & Sinais-vivos" (grade Catálogo/IDP 2026-07-21) ──
interface ScreenSignal {
  matched: boolean
  ns: string
  via?: string
  telas?: number
  charter_pct?: number
  casos_pct?: number
  stale?: boolean
  nota_media?: number | null
  backend_only?: boolean
}
interface GraphSignal {
  depends_on: number
  dependents: number
  provides_api: number
  owns_tables: number
  governed_by_adr: number
  components: number
  dangling_edges: number
  connected: boolean
}
interface Maturity {
  passed: number
  applicable: number
  ratio: number
  level: 'ouro' | 'prata' | 'bronze'
}
interface ServiceRow {
  id: string
  grade: number | null
  trust: string | null
  owner: string | null
  scope: string | null
  purpose: string | null
  screens: ScreenSignal | null
  graph: GraphSignal | null
  briefing: { present: boolean; last_commit: string | null } | null
  maturity: Maturity | null
  depends_on: string[]
  dependents: string[]
}
interface CatalogPayload {
  available: boolean
  generated_from?: {
    grades?: { baseline?: string; rubric?: string }
    vital_signs?: { generated_at?: string }
  }
  stats?: {
    services: number
    with_grade: number
    unmatched_screen_dirs: string[]
    orphan_screen_ns: string[]
    maturity_levels: { ouro: number; prata: number; bronze: number }
  }
  services: ServiceRow[]
}

interface Props {
  grades?: GradeRow[]
  kpis?: Kpis
  catalog?: CatalogPayload
}

type View = 'notas' | 'catalogo'

const BUCKETS = ['Excelente', 'Bom', 'Médio', 'Crítico', 'Embrião'] as const
type Bucket = (typeof BUCKETS)[number]

const BUCKET_STYLES: Record<Bucket, string> = {
  Excelente: 'bg-emerald-100 text-emerald-800 border-emerald-300',
  Bom: 'bg-sky-100 text-sky-800 border-sky-300',
  Médio: 'bg-amber-100 text-amber-800 border-amber-300',
  Crítico: 'bg-orange-100 text-orange-800 border-orange-300',
  Embrião: 'bg-red-100 text-red-800 border-red-300',
}

function bucketBadgeClass(bucket: string): string {
  return BUCKET_STYLES[bucket as Bucket] ?? 'bg-zinc-100 text-zinc-700 border-zinc-300'
}

function scoreColorClass(score: number): string {
  if (score >= 80) return 'text-emerald-700'
  if (score >= 60) return 'text-sky-700'
  if (score >= 40) return 'text-amber-700'
  if (score >= 20) return 'text-orange-700'
  return 'text-red-700'
}

function ModuleGradesIndex({ grades, kpis, catalog }: Props): React.ReactElement {
  const [view, setView] = useState<View>('notas')
  const [filterBucket, setFilterBucket] = useState<Bucket | 'Todos'>('Todos')
  const [search, setSearch] = useState('')

  const filteredGrades = useMemo(() => {
    if (!grades) return []
    return grades.filter((g) => {
      const matchBucket = filterBucket === 'Todos' || g.bucket === filterBucket
      const matchSearch = !search || g.module.toLowerCase().includes(search.toLowerCase())
      return matchBucket && matchSearch
    })
  }, [grades, filterBucket, search])

  return (
    <>
      <Head title="Module Grades — Governance" />
      <PageHeader
        title="Module Grades"
        subtitle="Rubrica oficial module-grade-v3 (ADR 0155) — nota 0-100 normalizada de 9 dimensões (raw /118)"
        breadcrumbs={[
          { label: 'Governança', href: '/governance' },
          { label: 'Module Grades' },
        ]}
      />

      {/* Toggle de visão — Notas (default) | Catálogo & Sinais (grade Catálogo/IDP 2026-07-21) */}
      <div className="mb-4 inline-block rounded-lg border border-border bg-card p-0.5" role="group" aria-label="Visão">
        <ViewTab label="Notas" active={view === 'notas'} onClick={() => setView('notas')} />
        <ViewTab label="Catálogo & Sinais" active={view === 'catalogo'} onClick={() => setView('catalogo')} />
      </div>

      {view === 'catalogo' ? (
        <Deferred data="catalog" fallback={<TableSkeleton />}>
          <CatalogSignalsView catalog={catalog} />
        </Deferred>
      ) : (
      <>
      {/* KPIs agregados */}
      <Deferred data="kpis" fallback={<KpiSkeletonBar />}>
        {kpis ? <KpiBar kpis={kpis} /> : null}
      </Deferred>

      {/* Filtros */}
      <Card className="mb-4">
        <CardContent className="py-3 flex flex-wrap gap-2 items-center">
          <div className="flex flex-wrap gap-2">
            <FilterChip label="Todos" active={filterBucket === 'Todos'} onClick={() => setFilterBucket('Todos')} count={grades?.length} />
            {BUCKETS.map((b) => (
              <FilterChip
                key={b}
                label={b}
                active={filterBucket === b}
                onClick={() => setFilterBucket(b)}
                count={kpis?.by_bucket?.[b] ?? 0}
                badgeClass={bucketBadgeClass(b)}
              />
            ))}
          </div>
          <input
            type="search"
            placeholder="Buscar módulo…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="ml-auto px-3 py-1.5 rounded-md border border-zinc-300 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500"
          />
        </CardContent>
      </Card>

      {/* Tabela — wrapper overflow-x-auto pra acomodar 13 colunas em laptops 1366px */}
      <Card>
        <CardContent className="p-0">
          <Deferred data="grades" fallback={<TableSkeleton />}>
            <div className="overflow-x-auto">
            <table className="w-full text-sm min-w-[1100px]">
              <thead className="bg-zinc-50 border-b border-zinc-200">
                <tr className="text-left">
                  <th className="px-4 py-3 font-semibold">Módulo</th>
                  <th className="px-4 py-3 font-semibold text-right">Nota</th>
                  <th className="px-4 py-3 font-semibold">Bucket</th>
                  <th className="px-2 py-3 font-semibold text-center">D1 MT</th>
                  <th className="px-2 py-3 font-semibold text-center">D2 Pest</th>
                  <th className="px-2 py-3 font-semibold text-center">D3 Doc</th>
                  <th className="px-2 py-3 font-semibold text-center">D4 Arq</th>
                  <th className="px-2 py-3 font-semibold text-center">D5 Cli</th>
                  {/* ADR 0155 v3 — 4 colunas novas (compactas — só score/max) */}
                  <th className="px-2 py-3 font-semibold text-center text-purple-700" title="D6 Performance — ADR 0155 v3">D6 Perf</th>
                  <th className="px-2 py-3 font-semibold text-center text-pink-700" title="D7 LGPD — ADR 0155 v3">D7 LGPD</th>
                  <th className="px-2 py-3 font-semibold text-center text-indigo-700" title="D8 Security — ADR 0155 v3">D8 Sec</th>
                  <th className="px-2 py-3 font-semibold text-center text-cyan-700" title="D9 Observability — ADR 0155 v3">D9 Obs</th>
                  <th className="px-4 py-3 font-semibold text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {filteredGrades.length === 0 ? (
                  <tr>
                    <td colSpan={13} className="px-4 py-8 text-center text-zinc-500">
                      Nenhum módulo combina com o filtro.
                    </td>
                  </tr>
                ) : (
                  filteredGrades.map((g) => (
                    <tr key={g.module} className="border-b border-zinc-100 hover:bg-sky-50 cursor-pointer">
                      <td className="px-4 py-2">
                        <Link href={`/governance/module-grades/${g.module}`} className="font-medium text-zinc-900 hover:text-sky-700">
                          {g.module}
                        </Link>
                      </td>
                      <td className={`px-4 py-2 text-right font-bold ${scoreColorClass(g.score)}`}>{g.score}</td>
                      <td className="px-4 py-2">
                        <Badge className={bucketBadgeClass(g.bucket)}>{g.bucket}</Badge>
                      </td>
                      <td className="px-2 py-2 text-center font-mono text-xs">{g.dimensions.multi_tenant}</td>
                      <td className="px-2 py-2 text-center font-mono text-xs">{g.dimensions.pest_coverage}</td>
                      <td className="px-2 py-2 text-center font-mono text-xs">{g.dimensions.documentation}</td>
                      <td className="px-2 py-2 text-center font-mono text-xs">{g.dimensions.architecture}</td>
                      <td className="px-2 py-2 text-center font-mono text-xs">{g.dimensions.client_real}</td>
                      {/* ADR 0155 v3 — render compacto (—) quando dimensão ausente p/ compat */}
                      <td className="px-2 py-2 text-center font-mono text-xs text-purple-700">{g.dimensions.performance ?? '—'}</td>
                      <td className="px-2 py-2 text-center font-mono text-xs text-pink-700">{g.dimensions.lgpd ?? '—'}</td>
                      <td className="px-2 py-2 text-center font-mono text-xs text-indigo-700">{g.dimensions.security ?? '—'}</td>
                      <td className="px-2 py-2 text-center font-mono text-xs text-cyan-700">{g.dimensions.observability ?? '—'}</td>
                      <td className="px-4 py-2 text-right">
                        <Link href={`/governance/module-grades/${g.module}`}>
                          <Button variant="ghost" size="sm">
                            Ver →
                          </Button>
                        </Link>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
            </div>
          </Deferred>
        </CardContent>
      </Card>

      {/* Gate CI anti-regressão — info pro time MCP (Felipe/Maiara/Eliana/Luiz) entender por que PR fica bloqueado */}
      <Card className="mt-4 border-sky-200 bg-sky-50/50">
        <CardContent className="py-3">
          <div className="flex items-start gap-3">
            <Shield className="w-4 h-4 text-sky-700 mt-0.5 flex-shrink-0" aria-hidden />
            <div className="text-xs text-zinc-700 space-y-1.5">
              <p className="font-semibold text-sky-900">Gate CI anti-regressão ativo</p>
              <p className="text-zinc-600">
                Toda PR roda <code className="text-[11px]">module-grade --all --json</code> e compara com o baseline.
                Se a nota de qualquer módulo <strong>cair</strong>, o merge é bloqueado.
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
        <Link href="/copiloto/admin/memoria?slug=0153-module-grade-rubrica-v1" className="underline">ADR 0153</Link> ·{' '}
        <Link href="/copiloto/admin/memoria?slug=0154-module-grade-rubrica-v2-na-justificado" className="underline">ADR 0154 v2</Link> ·{' '}
        <Link href="/copiloto/admin/memoria?slug=0155-module-grade-rubrica-v3-9-dimensoes" className="underline">ADR 0155 v3</Link>{' '}
        · 9 dimensões × pesos canônicos (D1 25, D2 17, D3 12, D4 17, D5 12, D6 10, D7 10, D8 8, D9 7) = 118 raw → /100 normalizado
      </p>
      </>
      )}
    </>
  )
}

function FilterChip({
  label,
  active,
  onClick,
  count,
  badgeClass,
}: {
  label: string
  active: boolean
  onClick: () => void
  count?: number
  badgeClass?: string
}): React.ReactElement {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`px-3 py-1 rounded-full text-xs font-medium border transition ${
        active ? 'bg-zinc-900 text-white border-zinc-900' : 'bg-white text-zinc-700 border-zinc-300 hover:border-zinc-500'
      }`}
    >
      {label}
      {count !== undefined && (
        <span className={`ml-1.5 inline-block px-1.5 py-0.5 rounded text-[10px] ${active ? 'bg-white/20' : badgeClass ?? 'bg-zinc-100'}`}>
          {count}
        </span>
      )}
    </button>
  )
}

function KpiBar({ kpis }: { kpis: Kpis }): React.ReactElement {
  return (
    <KpiGrid className="mb-4">
      <KpiCard label="Média projeto" value={kpis.average.toFixed(1)} suffix="pts" tone={kpis.average >= 60 ? 'success' : kpis.average >= 40 ? 'warning' : 'danger'} />
      <KpiCard label="Total módulos" value={String(kpis.total)} />
      {BUCKETS.map((b) => (
        <KpiCard
          key={b}
          label={b}
          value={String(kpis.by_bucket?.[b] ?? 0)}
          suffix="módulos"
          tone={b === 'Excelente' || b === 'Bom' ? 'success' : b === 'Médio' ? 'warning' : 'danger'}
        />
      ))}
    </KpiGrid>
  )
}

function KpiSkeletonBar(): React.ReactElement {
  return (
    <div className="grid grid-cols-2 md:grid-cols-7 gap-3 mb-4">
      {Array.from({ length: 7 }).map((_, i) => (
        <div key={i} className="h-20 rounded-lg bg-zinc-100 animate-pulse" />
      ))}
    </div>
  )
}

function TableSkeleton(): React.ReactElement {
  return (
    <div className="p-4 space-y-2">
      {Array.from({ length: 8 }).map((_, i) => (
        <div key={i} className="h-8 rounded bg-zinc-100 animate-pulse" />
      ))}
    </div>
  )
}

// ── Aba "Catálogo & Sinais-vivos" ──────────────────────────────────────────────

function ViewTab({ label, active, onClick }: { label: string; active: boolean; onClick: () => void }): React.ReactElement {
  return (
    <button
      type="button"
      aria-pressed={active}
      onClick={onClick}
      className={`px-3 py-1.5 rounded-md text-sm font-medium transition ${
        active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'
      }`}
    >
      {label}
    </button>
  )
}

const MATURITY_LABEL: Record<Maturity['level'], string> = { ouro: 'Ouro', prata: 'Prata', bronze: 'Bronze' }

function CatalogSignalsView({ catalog }: { catalog?: CatalogPayload }): React.ReactElement {
  if (!catalog || !catalog.available) {
    return (
      <Card>
        <CardContent className="py-8 text-center text-sm text-muted-foreground">
          Catálogo indisponível — o artefato <code>memory/governance/service-scorecard.json</code> ainda não foi gerado.
          Rode <code>node scripts/governance/service-scorecard.mjs --write</code> (ou aguarde o nightly MV).
        </CardContent>
      </Card>
    )
  }

  const { stats, services, generated_from: gen } = catalog

  return (
    <>
      {/* Stats header + proveniência (AGREGADOR advisory) */}
      {stats && (
        <Card className="mb-4 border-border bg-muted/50">
          <CardContent className="py-3 text-xs text-muted-foreground space-y-1.5">
            <div className="space-x-3">
              <span><strong className="text-foreground">{stats.services}</strong> serviços</span>
              <span><strong className="text-foreground">{stats.with_grade}</strong> com nota</span>
              <span>Maturidade: {MATURITY_LABEL.ouro} {stats.maturity_levels.ouro} · {MATURITY_LABEL.prata} {stats.maturity_levels.prata} · {MATURITY_LABEL.bronze} {stats.maturity_levels.bronze}</span>
            </div>
            {stats.unmatched_screen_dirs.length > 0 && (
              <p className="text-destructive"><TriangleAlert className="inline h-3.5 w-3.5 mr-1 -mt-0.5" />Telas sem linha em vital-signs (gap): {stats.unmatched_screen_dirs.join(', ')}</p>
            )}
            {stats.orphan_screen_ns.length > 0 && (
              <p><Info className="inline h-3.5 w-3.5 mr-1 -mt-0.5" />Namespaces de tela órfãos (core-app, sem serviço no catálogo): {stats.orphan_screen_ns.join(', ')}</p>
            )}
            <p>
              AGREGADOR advisory (não recalcula nota — module-grade é o dono). Fontes: catalog.json + module-grades-baseline
              {gen?.grades?.baseline ? ` (${gen.grades.baseline})` : ''} + vital-signs
              {gen?.vital_signs?.generated_at ? ` (${gen.vital_signs.generated_at})` : ''}.
            </p>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <table className="w-full text-sm min-w-[1000px]">
              <thead className="bg-muted border-b border-border">
                <tr className="text-left">
                  <th className="px-4 py-3 font-semibold">Serviço</th>
                  <th className="px-3 py-3 font-semibold text-right">Nota</th>
                  <th className="px-3 py-3 font-semibold">Telas</th>
                  <th className="px-3 py-3 font-semibold text-center" title="depende-de / usado-por · API providas · tabelas próprias">Grafo</th>
                  <th className="px-3 py-3 font-semibold">Depende de</th>
                  <th className="px-3 py-3 font-semibold">BRIEFING</th>
                  <th className="px-3 py-3 font-semibold text-center">Maturidade</th>
                </tr>
              </thead>
              <tbody>
                {services.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-muted-foreground">Nenhum serviço no catálogo.</td>
                  </tr>
                ) : (
                  services.map((s) => <CatalogRow key={s.id} s={s} />)
                )}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </>
  )
}

function CatalogRow({ s }: { s: ServiceRow }): React.ReactElement {
  const sc = s.screens
  const telas = sc?.matched
    ? `${sc.telas ?? '—'}${sc.charter_pct != null ? ` · ${sc.charter_pct}% chrt` : ''}`
    : sc?.backend_only
      ? 'backend'
      : '—'
  const g = s.graph
  return (
    <tr className="border-b border-border hover:bg-accent">
      <td className="px-4 py-2">
        <Link href={`/governance/module-grades/${s.id}`} className="font-medium text-foreground hover:text-primary">{s.id}</Link>
        {s.trust ? <span className="ml-2 text-[10px] text-muted-foreground">{s.trust}</span> : null}
      </td>
      <td className={`px-3 py-2 text-right font-bold ${s.grade != null ? scoreColorClass(s.grade) : 'text-muted-foreground'}`}>
        {s.grade != null ? s.grade : '—'}
      </td>
      <td className="px-3 py-2">
        <span className={sc?.matched && sc.stale ? 'text-destructive font-medium' : 'text-foreground'}>
          {telas}{sc?.matched && sc.stale ? ' · stale' : ''}
        </span>
      </td>
      <td className="px-3 py-2 text-center font-mono text-xs text-muted-foreground" title={`depende de ${g?.depends_on ?? 0} · dependentes ${g?.dependents ?? 0} · API ${g?.provides_api ?? 0} · tabelas ${g?.owns_tables ?? 0}`}>
        dep {g?.depends_on ?? 0} · usado {g?.dependents ?? 0} · {g?.provides_api ?? 0}api · {g?.owns_tables ?? 0}t
      </td>
      <td className="px-3 py-2 text-xs text-muted-foreground max-w-[220px] truncate" title={s.depends_on.join(', ') || '—'}>
        {s.depends_on.length ? s.depends_on.join(', ') : '—'}
      </td>
      <td className="px-3 py-2 text-xs text-muted-foreground">{s.briefing?.present ? (s.briefing.last_commit ?? 'sim') : '—'}</td>
      <td className="px-3 py-2 text-center">
        {s.maturity ? (
          <Badge className="bg-muted text-muted-foreground border-border" title={`${s.maturity.passed}/${s.maturity.applicable} checks de catálogo (presença/conexão)`}>
            {MATURITY_LABEL[s.maturity.level]} {s.maturity.passed}/{s.maturity.applicable}
          </Badge>
        ) : '—'}
      </td>
    </tr>
  )
}

ModuleGradesIndex.layout = (page: React.ReactNode): React.ReactElement => <AppShellV2>{page}</AppShellV2>

export default ModuleGradesIndex
