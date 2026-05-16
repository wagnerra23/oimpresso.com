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

interface Props {
  grades?: GradeRow[]
  kpis?: Kpis
}

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

function ModuleGradesIndex({ grades, kpis }: Props): React.ReactElement {
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

      {/* Tabela */}
      <Card>
        <CardContent className="p-0">
          <Deferred data="grades" fallback={<TableSkeleton />}>
            <table className="w-full text-sm">
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
          </Deferred>
        </CardContent>
      </Card>

      <p className="text-xs text-zinc-500 mt-4">
        Rubrica oficial: <code>module-grade-v3</code> ·{' '}
        <Link href="/copiloto/admin/memoria?slug=0153-module-grade-rubrica-v1" className="underline">ADR 0153</Link> ·{' '}
        <Link href="/copiloto/admin/memoria?slug=0154-module-grade-rubrica-v2-na-justificado" className="underline">ADR 0154 v2</Link> ·{' '}
        <Link href="/copiloto/admin/memoria?slug=0155-module-grade-rubrica-v3-9-dimensoes" className="underline">ADR 0155 v3</Link>{' '}
        · pesos 30/20/15/20/15 + (D6 Perf 6 / D7 LGPD 6 / D8 Sec 4 / D9 Obs 2) = /118 raw → /100 normalizado
      </p>
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

ModuleGradesIndex.layout = (page: React.ReactNode): React.ReactElement => <AppShellV2>{page}</AppShellV2>

export default ModuleGradesIndex
