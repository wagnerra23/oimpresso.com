// @governance
//   tela: /governance/dashboard  (a raiz /governance redireciona 302 pra /ia desde 2026-05-22)
//   adrs: 0079 (Constituição Art. 8+9), 0086 (Fase 5 MVP), 0275 (scorecard SDD),
//         0366 (fronteira dos 4 módulos — a seção "Governança MCP" veio do Jana)
//   runbook: memory/requisitos/Governance/RUNBOOK-dashboard.md

import React, { useState, type ReactNode } from 'react'
import { Deferred, Link, router } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'
import SubNav from '@/Components/shared/SubNav'
import { Grid, Inline } from '@/Components/layout'
import GovernancaSubNav from '@/Pages/governance/_shared/GovernancaSubNav'
import {
  ClipboardList,
  BarChart3,
  Bot,
  AlertTriangle,
  History,
  KeyRound,
  Construction,
  Users,
  Shield,
  Ruler,
  BookOpen,
} from 'lucide-react'

interface Adr {
  slug: string
  title: string
  updated_at: string
}

interface AuditEntry {
  user_id: number
  endpoint: string
  tool_or_resource: string | null
  status: string
  created_at: string
}

interface Narrative {
  severity: 'info' | 'warning' | 'critical'
  narrative: string
  generated_at: string
}

interface HealthKpis {
  failed_jobs_24h: number | null
  custo_ia_brl_24h: number | null
  last_narrative: { severity: 'info' | 'warning' | 'critical'; message: string; generated_at: string } | null
}

interface SddPayload {
  snapshot_date: string
  composta: number | null
  composta_k: number
  delta: number | null
  vivas: number
  metrics_total: number
  alerts: string[]
}

/* ── Seção "Governança MCP" (ADR 0366 §D-B — absorvida de Jana/Admin/Governanca) ──
   O contrato é o retorno de `Modules\Jana\Services\GovernancaService::painel()`.
   O Service NÃO se moveu: a ADR 0366 §D-C item 4 (mover as `Mcp*`) não está
   autorizada, e o destino declarado delas é o Forja. Muda só o consumidor. */

type McpPreset = 'hoje' | 'ontem' | '7d' | '30d' | 'mes_anterior' | 'custom'
type McpAba = 'consumo' | 'acesso' | 'usuarios'
type McpChartMode = 'calls' | 'custo'

interface McpFilters {
  preset: McpPreset
  de: string | null
  ate: string | null
}

interface McpPayload {
  kpis: {
    total_calls: number
    usuarios_ativos: number
    custo_total: number
    tokens_total: number
    latency_avg_ms: number
  }
  por_status: Array<{ status: string; calls: number; pct: number }>
  latency: { p50: number; p95: number; p99: number; max: number }
  top_tools: Array<{ tool: string; calls: number; custo_brl: number }>
  top_users: Array<{ user_id: number; nome: string; calls: number; custo_brl: number }>
  denied_por_codigo: Array<{ error_code: string; calls: number }>
  serie_diaria: Array<{ data: string; calls: number; custo_brl: number; denied: number }>
  periodo: { inicio: string; fim: string; label: string }
}

interface Props {
  sdd?: SddPayload | null
  kpis: {
    pending_adrs: number
    active_policies: number
    skill_approvals: number
    actors_registered: number
    audit_highlights: number
    compliance_pct: number
  }
  pending_adrs: Adr[]
  audit_highlights: AuditEntry[]
  actiongate_mode: 'off' | 'warn' | 'strict'
  next_review_at: string
  health_kpis: HealthKpis
  narratives: Narrative[]
  /** `jana.mcp.usage.all` — mesmo gate da tela original. false ⇒ prop `mcp` nem existe. */
  mcp_enabled: boolean
  /** Estado do filtro de período (query string `mcp_*`) — eager, zero I/O. */
  mcp_filters: McpFilters
  /** Deferred. `null` = `mcp_audit_log` ausente (degradação graciosa). */
  mcp?: McpPayload | null
}

function complianceColor(pct: number): string {
  if (pct >= 90) return 'success'
  if (pct >= 70) return 'info'
  if (pct >= 50) return 'warning'
  return 'danger'
}

function modeBadge(mode: 'off' | 'warn' | 'strict'): { label: string; color: string } {
  return {
    off:    { label: 'Desligado',  color: 'bg-zinc-200 text-zinc-700 border-zinc-300' },
    warn:   { label: 'Modo aviso',  color: 'bg-amber-100 text-amber-700 border-amber-300' },
    strict: { label: 'Modo estrito', color: 'bg-emerald-100 text-emerald-700 border-emerald-300' },
  }[mode] ?? { label: mode, color: '' }
}

function failedJobsTone(n: number | null): 'default' | 'success' | 'warning' | 'danger' {
  if (n === null) return 'default'
  if (n === 0) return 'success'
  if (n > 100) return 'danger'
  return 'warning'
}

function custoIaTone(n: number | null): 'default' | 'success' | 'warning' | 'info' {
  if (n === null) return 'default'
  if (n > 5) return 'warning'
  if (n > 0) return 'info'
  return 'success'
}

function severityTone(s: 'info' | 'warning' | 'critical' | undefined): 'default' | 'warning' | 'danger' | 'info' {
  if (s === 'critical') return 'danger'
  if (s === 'warning') return 'warning'
  if (s === 'info') return 'info'
  return 'default'
}

function severityBadgeClass(s: 'info' | 'warning' | 'critical'): string {
  return {
    critical: 'bg-rose-50 text-rose-700 border-rose-200',
    warning: 'bg-amber-50 text-amber-700 border-amber-200',
    info: 'bg-blue-50 text-blue-700 border-blue-200',
  }[s]
}

function truncate(text: string, max: number): string {
  return text.length > max ? text.slice(0, max - 1) + '…' : text
}

/* ─────────────────────────────────────────────────────────────────────────────
   Seção Governança MCP — ADR 0366 §D-C item 1
   ─────────────────────────────────────────────────────────────────────────────
   Portada de `Jana/Admin/Governanca/Index.tsx`. Duas diferenças deliberadas
   em relação ao original, ambas por regra do repo:

   1) Cor: o original usava palette cru (`bg-emerald-500`, `stroke-amber-400`).
      Em `Pages/**` isso é `ds/no-raw-palette-color` e conta como regressão no
      `lint:baseline:check` — aqui vai token semântico (success/warning/
      destructive/primary), que ainda ganha dark-mode de graça.
   2) Navegação: o original trocava de seção por rota (`?secao=`) com
      `PageHeaderTabs`. Aqui a barra de topo já é a do MÓDULO
      (`GovernancaSubNav`); um segundo tablist de rota competiria com ela. O
      switch é in-page controlado ⇒ `<SubNav variant="segmented">` — que é
      exatamente a regra de decisão do `ds/no-inline-tablist`.

   Não portados (não são perda de dado, e ficam registrados no charter):
   - atalho `/` focando o seletor: fazia sentido numa tela dedicada; num painel
     de 5 seções sequestraria a tecla para uma delas;
   - `localStorage[...governanca.preset]`: o original só ESCREVIA, nunca lia —
     era código morto. A fonte de verdade do período agora é a query string.  */

const MCP_PRESET_LABEL: Record<McpPreset, string> = {
  hoje: 'Hoje',
  ontem: 'Ontem',
  '7d': 'Últimos 7 dias',
  '30d': 'Últimos 30 dias',
  mes_anterior: 'Mês anterior',
  custom: 'Customizado',
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v ?? 0)

function formatDataCurta(iso: string): string {
  const d = new Date(iso + 'T00:00:00')
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
}

/** Barra de proporção por status — tokens semânticos (nunca palette cru). */
function statusBarClass(status: string): string {
  switch (status) {
    case 'ok':
      return 'bg-success h-2'
    case 'denied':
      return 'bg-warning h-2'
    case 'quota_exceeded':
      return 'bg-warning/60 h-2'
    default:
      return 'bg-destructive h-2'
  }
}

/** Série diária de chamadas MCP (SVG inline, sem lib). `mode` troca calls ⇄ custo. */
function McpCallsChart({
  dados,
  mode,
}: {
  dados: McpPayload['serie_diaria']
  mode: McpChartMode
}) {
  const w = 800
  const h = 220
  const pad = { top: 16, right: 16, bottom: 28, left: 56 }
  const innerW = w - pad.left - pad.right
  const innerH = h - pad.top - pad.bottom

  const n = dados.length
  if (n === 0) {
    return (
      <EmptyState
        icon="bar-chart-2"
        title="Sem dados no período"
        description="Nenhuma chamada ao MCP server registrada no intervalo selecionado."
        variant="search"
        className="py-8"
      />
    )
  }

  const valorPrincipal = (d: McpPayload['serie_diaria'][number]) =>
    mode === 'custo' ? d.custo_brl : d.calls
  const max = Math.max(1, ...dados.map(valorPrincipal))

  const xAt = (i: number) => pad.left + (n === 1 ? innerW / 2 : (i / (n - 1)) * innerW)
  const yAt = (v: number) => pad.top + innerH - (v / max) * innerH

  const linePts = dados.map((d, i) => `${xAt(i)},${yAt(valorPrincipal(d))}`).join(' ')
  const deniedPts =
    mode === 'calls' ? dados.map((d, i) => `${xAt(i)},${yAt(d.denied)}`).join(' ') : null
  const areaPts = [
    `${xAt(0)},${pad.top + innerH}`,
    ...dados.map((d, i) => `${xAt(i)},${yAt(valorPrincipal(d))}`),
    `${xAt(n - 1)},${pad.top + innerH}`,
  ].join(' ')

  const ticks = 4
  const yTicks = Array.from({ length: ticks + 1 }, (_, i) => Math.round((max * i) / ticks))
  const stepX = Math.max(1, Math.ceil(n / 8))
  const xLabels = dados.map((d, i) => ({ d, i })).filter(({ i }) => i % stepX === 0 || i === n - 1)

  return (
    <div className="w-full overflow-x-auto">
      <svg
        viewBox={`0 0 ${w} ${h}`}
        className="w-full h-auto text-primary"
        role="img"
        aria-label={mode === 'custo' ? 'Custo do MCP por dia' : 'Chamadas ao MCP por dia'}
      >
        {yTicks.map((t, i) => (
          <g key={`y-${i}`}>
            <line
              x1={pad.left}
              x2={pad.left + innerW}
              y1={yAt(t)}
              y2={yAt(t)}
              className="stroke-border"
              strokeDasharray="2 4"
            />
            <text
              x={pad.left - 6}
              y={yAt(t)}
              textAnchor="end"
              dominantBaseline="middle"
              className="fill-muted-foreground text-[10px]"
            >
              {mode === 'custo' ? `R$${t.toFixed(0)}` : num(t)}
            </text>
          </g>
        ))}

        <polygon points={areaPts} className="fill-primary/15" />
        <polyline
          points={linePts}
          fill="none"
          className="stroke-primary"
          strokeWidth={2}
          strokeLinecap="round"
          strokeLinejoin="round"
        />
        {deniedPts && (
          <polyline
            points={deniedPts}
            fill="none"
            className="stroke-warning"
            strokeWidth={1.5}
            strokeDasharray="4 2"
            strokeLinecap="round"
          />
        )}

        {xLabels.map(({ d, i }) => (
          <text
            key={`x-${i}`}
            x={xAt(i)}
            y={h - 8}
            textAnchor="middle"
            className="fill-muted-foreground text-[10px]"
          >
            {formatDataCurta(d.data)}
          </text>
        ))}
      </svg>
      <Inline gap={4} className="text-xs text-muted-foreground mt-2 ml-14">
        <Inline className="gap-1.5">
          <span className="inline-block w-3 h-0.5 bg-primary" />
          {mode === 'custo' ? 'custo (R$)' : 'chamadas'}
        </Inline>
        {mode === 'calls' && (
          <Inline className="gap-1.5">
            <span className="inline-block w-3 h-0.5 bg-warning" /> negadas
          </Inline>
        )}
      </Inline>
    </div>
  )
}

/** Miolo da seção — só renderiza com `mcp` já resolvido (dentro do `<Deferred>`). */
function McpPainel({ mcp }: { mcp: McpPayload }) {
  const [aba, setAba] = useState<McpAba>('consumo')
  const [chartMode, setChartMode] = useState<McpChartMode>('calls')

  const okCount = mcp.por_status.find((s) => s.status === 'ok')?.calls ?? 0
  const taxaSucesso = mcp.kpis.total_calls > 0 ? (okCount / mcp.kpis.total_calls) * 100 : 0

  return (
    <div className="space-y-4">
      <KpiGrid cols={4}>
        <KpiCard
          icon="activity"
          tone="info"
          label="Chamadas no período"
          value={num(mcp.kpis.total_calls)}
          description={`${num(mcp.kpis.usuarios_ativos)} usuários ativos · ${mcp.periodo.label}`}
        />
        <KpiCard
          icon="check-circle"
          tone={taxaSucesso >= 95 ? 'success' : taxaSucesso >= 80 ? 'default' : 'warning'}
          label="Taxa de sucesso"
          value={`${taxaSucesso.toFixed(1)}%`}
          description={`${num(okCount)} de ${num(mcp.kpis.total_calls)} sem erro`}
        />
        <KpiCard
          icon="zap"
          tone="default"
          label="Latência p95"
          value={`${num(mcp.latency.p95)} ms`}
          description={`p50 ${num(mcp.latency.p50)} · p99 ${num(mcp.latency.p99)} · máx ${num(mcp.latency.max)}`}
        />
        <KpiCard
          icon="dollar-sign"
          tone="success"
          label="Custo do MCP"
          value={brl(mcp.kpis.custo_total)}
          description={`${num(mcp.kpis.tokens_total)} tokens consumidos`}
        />
      </KpiGrid>

      <SubNav
        variant="segmented"
        ariaLabel="Seções da governança MCP"
        value={aba}
        onChange={(v) => setAba(v as McpAba)}
        items={[
          { value: 'consumo', label: 'Consumo', icon: 'bar-chart-2' },
          {
            value: 'acesso',
            label: 'Acesso / RBAC',
            icon: 'shield-check',
            badge: mcp.denied_por_codigo.length || undefined,
          },
          { value: 'usuarios', label: 'Usuários e tools', icon: 'users' },
        ]}
      />

      {aba === 'consumo' && (
        <Card>
          <CardContent className="p-4">
            <Inline gap={3} align="start" justify="between" wrap className="mb-3">
              <div>
                <h3 className="text-lg font-semibold">Chamadas por dia</h3>
                <p className="text-xs text-muted-foreground">
                  {mcp.serie_diaria.length} dias · linha cheia = total · linha tracejada = negadas
                </p>
              </div>
              <SubNav
                variant="segmented"
                ariaLabel="Métrica do gráfico"
                value={chartMode}
                onChange={(v) => setChartMode(v as McpChartMode)}
                items={[
                  { value: 'calls', label: 'Chamadas', icon: 'activity' },
                  { value: 'custo', label: 'Custo', icon: 'dollar-sign' },
                ]}
              />
            </Inline>
            <McpCallsChart dados={mcp.serie_diaria} mode={chartMode} />
          </CardContent>
        </Card>
      )}

      {aba === 'acesso' && (
        <Grid cols={1} gap={4} className="md:grid-cols-2">
          <Card>
            <CardContent className="p-4">
              <h3 className="text-lg font-semibold mb-1">Distribuição por resultado</h3>
              <p className="text-xs text-muted-foreground mb-3">
                {mcp.kpis.total_calls > 0
                  ? `${num(mcp.kpis.total_calls)} chamadas no período`
                  : 'Sem chamadas no período'}
              </p>
              {mcp.por_status.length === 0 ? (
                <EmptyState
                  icon="activity"
                  title="Sem dados de resultado"
                  description="Nenhuma chamada registrada no período."
                  className="py-6"
                />
              ) : (
                <div className="space-y-2">
                  {mcp.por_status.map((s) => (
                    <Inline key={s.status} gap={3}>
                      <Badge variant="outline" className="font-mono text-xs w-28 justify-center">
                        {s.status}
                      </Badge>
                      <div className="flex-1 bg-muted rounded h-2 overflow-hidden">
                        <div className={statusBarClass(s.status)} style={{ width: `${s.pct}%` }} />
                      </div>
                      <span className="text-xs font-mono w-16 text-right">{num(s.calls)}</span>
                      <span className="text-xs text-muted-foreground w-12 text-right">{s.pct}%</span>
                    </Inline>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-4">
              <h3 className="text-lg font-semibold mb-1">Negadas por código</h3>
              <p className="text-xs text-muted-foreground mb-3">
                Quem foi bloqueado e por quê — diagnóstico de RBAC
              </p>
              {mcp.denied_por_codigo.length === 0 ? (
                <EmptyState
                  icon="shield-check"
                  title="Nenhuma negativa no período"
                  description="Todas as chamadas passaram nas políticas de acesso."
                  variant="success"
                  className="py-6"
                />
              ) : (
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-xs text-muted-foreground">
                      <th className="text-left py-2 font-medium">Código</th>
                      <th className="text-right py-2 font-medium">Chamadas</th>
                    </tr>
                  </thead>
                  <tbody>
                    {mcp.denied_por_codigo.map((d) => (
                      <tr key={d.error_code} className="border-b last:border-0">
                        <td className="py-1.5 font-mono text-xs">{d.error_code}</td>
                        <td className="text-right py-1.5 font-mono">{num(d.calls)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </CardContent>
          </Card>
        </Grid>
      )}

      {aba === 'usuarios' && (
        <Grid cols={1} gap={4} className="md:grid-cols-2">
          <Card>
            <CardContent className="p-4">
              <h3 className="text-lg font-semibold mb-1">Tools e recursos mais usados</h3>
              <p className="text-xs text-muted-foreground mb-3">Top 10 do período</p>
              {mcp.top_tools.length === 0 ? (
                <EmptyState
                  icon="wrench"
                  title="Sem chamadas de tools"
                  description="Nenhuma tool ou recurso foi invocado no período."
                  className="py-6"
                />
              ) : (
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-xs text-muted-foreground">
                      <th className="text-left py-2 font-medium">Tool / recurso</th>
                      <th className="text-right py-2 font-medium">Chamadas</th>
                      <th className="text-right py-2 font-medium">Custo</th>
                    </tr>
                  </thead>
                  <tbody>
                    {mcp.top_tools.map((t) => (
                      <tr key={t.tool} className="border-b last:border-0 hover:bg-muted/40">
                        <td
                          className="py-1.5 font-mono text-xs truncate max-w-[200px]"
                          title={t.tool}
                        >
                          {t.tool}
                        </td>
                        <td className="text-right py-1.5 font-mono">{num(t.calls)}</td>
                        <td className="text-right py-1.5 font-mono text-xs">{brl(t.custo_brl)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-4">
              <h3 className="text-lg font-semibold mb-1">Quem mais consome</h3>
              <p className="text-xs text-muted-foreground mb-3">Top 10 do período</p>
              {mcp.top_users.length === 0 ? (
                <EmptyState
                  icon="users"
                  title="Sem dados de usuários"
                  description="Ninguém consumiu o MCP server no período."
                  className="py-6"
                />
              ) : (
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-xs text-muted-foreground">
                      <th className="text-left py-2 font-medium">Usuário</th>
                      <th className="text-right py-2 font-medium">Chamadas</th>
                      <th className="text-right py-2 font-medium">Custo</th>
                    </tr>
                  </thead>
                  <tbody>
                    {mcp.top_users.map((u) => (
                      <tr key={u.user_id} className="border-b last:border-0 hover:bg-muted/40">
                        <td className="py-1.5">{u.nome}</td>
                        <td className="text-right py-1.5 font-mono">{num(u.calls)}</td>
                        <td className="text-right py-1.5 font-mono text-xs">{brl(u.custo_brl)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </CardContent>
          </Card>
        </Grid>
      )}
    </div>
  )
}

/** Cabeçalho + filtro de período (eager) + `<Deferred>` em volta do miolo caro. */
function McpSection({ filters, mcp }: { filters: McpFilters; mcp?: McpPayload | null }) {
  const [de, setDe] = useState(filters.de ?? '')
  const [ate, setAte] = useState(filters.ate ?? '')
  const [carregando, setCarregando] = useState(false)

  const aplicar = (patch: Partial<McpFilters>) => {
    const proximo: McpFilters = { ...filters, ...patch }
    const data: Record<string, string> = { mcp_preset: proximo.preset }
    if (proximo.preset === 'custom') {
      if (proximo.de) data.mcp_de = proximo.de
      if (proximo.ate) data.mcp_ate = proximo.ate
    }

    // Partial reload: trocar o período NÃO re-roda os KPIs da Constituição nem o
    // scorecard SDD — só a seção MCP. `mcp` é deferred; pedi-la em `only` força
    // a resolução (é o mesmo mecanismo que o <Deferred> usa na 1ª pintura).
    router.get('/governance/dashboard', data, {
      only: ['mcp', 'mcp_filters'],
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onStart: () => setCarregando(true),
      onFinish: () => setCarregando(false),
    })
  }

  return (
    <>
      <Inline gap={3} align="end" justify="between" wrap className="mt-4">
        <div>
          <h2 className="text-sm font-semibold uppercase tracking-widest text-muted-foreground">
            Governança MCP
          </h2>
          <p className="text-xs text-muted-foreground mt-1">
            Consumo cross-team do MCP server — trilha append-only em{' '}
            <code className="font-mono">mcp_audit_log</code> (ADR 0053)
          </p>
        </div>

        <Inline gap={2} align="end" wrap>
          <div className="min-w-[180px]">
            <span className="text-xs font-medium text-muted-foreground block mb-1">Período</span>
            <Select
              value={filters.preset}
              onValueChange={(v) => aplicar({ preset: v as McpPreset, de: null, ate: null })}
            >
              <SelectTrigger aria-label="Período da seção Governança MCP">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {(Object.keys(MCP_PRESET_LABEL) as McpPreset[]).map((p) => (
                  <SelectItem key={p} value={p}>
                    {MCP_PRESET_LABEL[p]}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {filters.preset === 'custom' && (
            <Inline asChild gap={2} align="end">
              <form
                onSubmit={(e) => {
                  e.preventDefault()
                  aplicar({ preset: 'custom', de, ate })
                }}
              >
                <div>
                  <span className="text-xs font-medium text-muted-foreground block mb-1">De</span>
                  <Input
                    type="date"
                    value={de}
                    onChange={(e) => setDe(e.target.value)}
                    aria-label="Data inicial"
                    required
                  />
                </div>
                <div>
                  <span className="text-xs font-medium text-muted-foreground block mb-1">Até</span>
                  <Input
                    type="date"
                    value={ate}
                    onChange={(e) => setAte(e.target.value)}
                    aria-label="Data final"
                    required
                  />
                </div>
                <Button type="submit" size="sm">
                  Aplicar
                </Button>
              </form>
            </Inline>
          )}
        </Inline>
      </Inline>

      {carregando && (
        <p className="text-xs text-muted-foreground" role="status">
          Atualizando o período…
        </p>
      )}

      {/* ⚠️ O <Deferred> é obrigatório — sem ele, consumir `mcp` direto reabre o
          incidente de 2026-05-25 da tela original (TypeError undefined.find em prod). */}
      <Deferred
        data="mcp"
        fallback={<p className="text-sm text-muted-foreground">Carregando consumo do MCP…</p>}
      >
        {mcp ? (
          <McpPainel mcp={mcp} />
        ) : (
          <EmptyState
            icon="database"
            title="Trilha do MCP indisponível"
            description="A tabela mcp_audit_log não existe neste ambiente — a seção volta sozinha quando a migration do MCP server estiver aplicada."
            className="py-8"
          />
        )}
      </Deferred>
    </>
  )
}

const Dashboard: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({
  sdd,
  kpis,
  pending_adrs,
  audit_highlights,
  actiongate_mode,
  next_review_at,
  health_kpis,
  narratives,
  mcp_enabled,
  mcp_filters,
  mcp,
}) => {
  const mode = modeBadge(actiongate_mode)

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      {/* Strip de sub-navegação do módulo — fonte da lista é o DataController
          (`shell.menu`), NUNCA duplicada aqui. Silenciosa quando o usuário não
          tem `governance.dashboard.view` ou o módulo está desinstalado. */}
      <GovernancaSubNav active="dashboard" />

      <PageHeader
        icon="shield-check"
        title="Governança"
        description="Painel consolidado de governança do oimpresso. Constituição v1.1.0 — Art. 8 (Policy Gating) + Art. 9 (Auditoria) operacional. Wagner opera 5min/dia."
      >
        <Badge variant="outline" className={mode.color}>
          ActionGate: {mode.label}
        </Badge>
      </PageHeader>

      <h2 className="text-sm font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400 mt-2">
        Constituição
      </h2>

      <KpiGrid cols={6}>
        <KpiCard
          icon="file-text"
          tone="warning"
          label="ADRs pendentes"
          value={kpis.pending_adrs.toString()}
          description="Status proposto aguardando você"
          href="/copiloto/admin/memoria?type=adr&status=proposto"
        />
        <KpiCard
          icon="check-circle"
          tone="success"
          label="Policies ativas"
          value={kpis.active_policies.toString()}
          description="mcp_governance_rules.enabled=1"
        />
        <KpiCard
          icon="git-pull-request"
          tone="info"
          label="Skill approvals"
          value={kpis.skill_approvals.toString()}
          description="Pending de aprovação"
          href="/ads/admin/skills-review"
        />
        <KpiCard
          icon="users"
          tone="info"
          label="Actors registrados"
          value={kpis.actors_registered.toString()}
          description="Identity Mesh — humanos + IAs"
        />
        <KpiCard
          icon="alert-triangle"
          tone={kpis.audit_highlights > 0 ? 'warning' : 'success'}
          label="Audit highlights 24h"
          value={kpis.audit_highlights.toString()}
          description="Erros + ações L0/L1"
        />
        <KpiCard
          icon="award"
          tone={complianceColor(kpis.compliance_pct) as any}
          label="Compliance Constitution"
          value={`${kpis.compliance_pct}%`}
          description={`v1.1.0 — próx revisão ${next_review_at}`}
        />
      </KpiGrid>

      <h2 className="text-sm font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400 mt-4">
        SDD — Reestruturação (ADR 0275)
      </h2>

      <Deferred data="sdd" fallback={<p className="text-sm text-zinc-400">Carregando scorecard SDD…</p>}>
        {sdd ? (
          <KpiGrid cols={3}>
            <KpiCard
              icon="gauge"
              tone={sdd.delta !== null && sdd.delta < 0 ? 'danger' : 'info'}
              label={`Composta v1 (k=${sdd.composta_k})`}
              value={sdd.composta === null ? '—' : sdd.composta.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}
              description={
                sdd.delta === null
                  ? `snapshot ${sdd.snapshot_date} — sem Δ (1º snapshot ou composta nula)`
                  : `Δ vs ontem: ${sdd.delta > 0 ? '+' : ''}${sdd.delta.toLocaleString('pt-BR')} · ${sdd.snapshot_date}`
              }
            />
            <KpiCard
              icon="activity"
              tone="info"
              label="Métricas vivas"
              value={`${sdd.vivas}/${sdd.metrics_total}`}
              description="fontes medindo de verdade (status measured)"
            />
            <KpiCard
              icon="alert-triangle"
              tone={sdd.alerts.length > 0 ? 'danger' : 'success'}
              label="Alertas SDD"
              value={sdd.alerts.length.toString()}
              description={sdd.alerts.length > 0 ? truncate(sdd.alerts[0], 70) : 'nenhuma métrica armada regrediu'}
            />
          </KpiGrid>
        ) : (
          <p className="text-sm text-zinc-500">
            Sem snapshot SDD ainda — cron `governance:sdd-scorecard-snapshot` roda 07:10 BRT.
          </p>
        )}
      </Deferred>

      <h2 className="text-sm font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400 mt-4">
        Saúde do ecossistema
      </h2>

      <KpiGrid cols={3}>
        <KpiCard
          icon="activity"
          tone={failedJobsTone(health_kpis.failed_jobs_24h)}
          label="Failed jobs 24h"
          value={health_kpis.failed_jobs_24h === null ? '—' : health_kpis.failed_jobs_24h.toString()}
          description={health_kpis.failed_jobs_24h === null ? 'failed_jobs ausente' : 'queue Horizon'}
        />
        <KpiCard
          icon="dollar-sign"
          tone={custoIaTone(health_kpis.custo_ia_brl_24h)}
          label="Custo IA 24h"
          value={
            health_kpis.custo_ia_brl_24h === null
              ? '—'
              : `R$ ${health_kpis.custo_ia_brl_24h.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
          }
          description={health_kpis.custo_ia_brl_24h === null ? 'jana_mensagens ausente' : 'tokens × pricing canônico'}
        />
        <KpiCard
          icon="message-circle-warning"
          tone={severityTone(health_kpis.last_narrative?.severity)}
          label="Última narrativa"
          value={health_kpis.last_narrative ? health_kpis.last_narrative.severity : '—'}
          description={
            health_kpis.last_narrative
              ? truncate(health_kpis.last_narrative.message, 60)
              : 'Brain A narrador inativo'
          }
        />
      </KpiGrid>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* ADRs pendentes */}
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <ClipboardList className="h-4 w-4 text-amber-600" />
                ADRs aguardando você ({pending_adrs.length})
              </h3>
              <Link href="/copiloto/admin/memoria?type=adr" className="text-sm text-primary hover:underline">
                ver todos →
              </Link>
            </div>

            {pending_adrs.length === 0 ? (
              <EmptyState icon="check-circle" title="Sem ADRs pendentes" description="Tudo em dia." />
            ) : (
              <ul className="space-y-2">
                {pending_adrs.map((adr) => (
                  <li key={adr.slug} className="flex items-start gap-2 text-sm border-b border-zinc-100 dark:border-zinc-800 pb-2 last:border-0">
                    <Link
                      href={`/copiloto/admin/memoria/${adr.slug}`}
                      className="font-mono text-xs text-primary hover:underline shrink-0"
                    >
                      {adr.slug.split('-')[0]}
                    </Link>
                    <span className="flex-1">{adr.title}</span>
                    <span className="text-xs text-zinc-500 shrink-0">{new Date(adr.updated_at).toLocaleDateString('pt-BR')}</span>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>

        {/* Audit highlights */}
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <BarChart3 className="h-4 w-4 text-zinc-700 dark:text-zinc-300" />
                Audit Highlights 24h ({audit_highlights.length})
              </h3>
              <Link href="/governance/audit" className="text-sm text-primary hover:underline">
                drill-down →
              </Link>
            </div>

            {audit_highlights.length === 0 ? (
              <EmptyState icon="check-circle" title="Sem alertas" description="Nada anormal nas últimas 24h." />
            ) : (
              <ul className="space-y-2">
                {audit_highlights.slice(0, 10).map((entry, idx) => {
                  const isError = entry.status !== 'ok'
                  return (
                    <li key={idx} className="flex items-start gap-2 text-sm border-b border-zinc-100 dark:border-zinc-800 pb-2 last:border-0">
                      <Badge variant="outline" className={isError ? 'bg-destructive-soft text-destructive-fg border-destructive/20' : 'bg-success-soft text-success-fg border-success/20'}>
                        {entry.status}
                      </Badge>
                      <div className="flex-1">
                        <div className="font-mono text-xs">{entry.endpoint}</div>
                        {entry.tool_or_resource && (
                          <div className="text-xs text-zinc-500">{entry.tool_or_resource}</div>
                        )}
                      </div>
                      <span className="text-xs text-zinc-500 shrink-0">
                        {new Date(entry.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                      </span>
                    </li>
                  )
                })}
              </ul>
            )}
          </CardContent>
        </Card>

        {/* Narrativas Brain A 24h */}
        <Card>
          <CardContent className="p-4">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-lg font-semibold flex items-center gap-2">
                <Bot className="h-4 w-4 text-zinc-700 dark:text-zinc-300" />
                Narrativas Brain A 24h ({narratives.length})
              </h3>
              <Link href="/copiloto/admin/memoria?type=narrative" className="text-sm text-primary hover:underline">
                histórico →
              </Link>
            </div>

            {narratives.length === 0 ? (
              <EmptyState icon="message-circle" title="Sem narrativas" description="Brain A ainda não rodou nas últimas 24h." />
            ) : (
              <ul className="space-y-2">
                {narratives.map((n, idx) => (
                  <li key={idx} className="flex items-start gap-2 text-sm border-b border-zinc-100 dark:border-zinc-800 pb-2 last:border-0">
                    <Badge variant="outline" className={severityBadgeClass(n.severity)}>
                      {n.severity}
                    </Badge>
                    <div className="flex-1">{truncate(n.narrative, 80)}</div>
                    <span className="text-xs text-zinc-500 shrink-0">
                      {new Date(n.generated_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Governança MCP — ADR 0366 §D-C item 1 (fusão da tela Jana/Admin/Governanca).
          Some inteira (título incluso) sem `jana.mcp.usage.all`: o gate herdado da
          tela de origem vive no controller, e sem ele a prop `mcp` nem é enviada. */}
      {mcp_enabled && <McpSection filters={mcp_filters} mcp={mcp} />}

      {/* Quick actions */}
      <Card>
        <CardContent className="p-4">
          <h3 className="text-lg font-semibold mb-3">Atalhos de governança</h3>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
            <Link
              href="/governance/policies"
              className="px-4 py-3 bg-zinc-50 dark:bg-zinc-900 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 text-sm font-medium transition-colors"
            >
              ⚙️ Policies
            </Link>
            <Link
              href="/governance/audit"
              className="px-4 py-3 bg-zinc-50 dark:bg-zinc-900 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 text-sm font-medium transition-colors"
            >
              <BarChart3 className="h-3.5 w-3.5 mr-1 inline-block" /> Audit log
            </Link>
            <Link
              href="/governance/drift"
              className="px-4 py-3 bg-zinc-50 dark:bg-zinc-900 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 text-sm font-medium transition-colors"
            >
              <AlertTriangle className="h-3.5 w-3.5 mr-1 inline-block" /> Drift alerts
            </Link>
            <Link
              href="/copiloto/admin/memoria?type=adr&status=proposto"
              className="px-4 py-3 bg-zinc-50 dark:bg-zinc-900 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-md border border-zinc-200 dark:border-zinc-700 text-sm font-medium transition-colors"
            >
              <ClipboardList className="h-3.5 w-3.5 mr-1 inline-block" /> ADRs proposto
            </Link>
          </div>
        </CardContent>
      </Card>

      {/* Constitution links */}
      <Card>
        <CardContent className="p-4">
          <h3 className="text-lg font-semibold mb-3">Documentos canônicos</h3>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
            <a href="https://github.com/wagnerra23/oimpresso.com/blob/main/memory/governance/CONSTITUTION.md" target="_blank" rel="noreferrer" className="text-primary hover:underline"><History className="h-3.5 w-3.5 mr-1 inline-block" /> Constituição v1.1.0</a>
            <a href="https://github.com/wagnerra23/oimpresso.com/blob/main/memory/governance/TRUST-TIERS.md" target="_blank" rel="noreferrer" className="text-primary hover:underline"><KeyRound className="h-3.5 w-3.5 mr-1 inline-block" /> Trust Tiers</a>
            <a href="https://github.com/wagnerra23/oimpresso.com/blob/main/memory/governance/ARCHITECTURE.md" target="_blank" rel="noreferrer" className="text-primary hover:underline"><Construction className="h-3.5 w-3.5 mr-1 inline-block" /> Architecture</a>
            <a href="https://github.com/wagnerra23/oimpresso.com/blob/main/memory/governance/IDENTITY-MESH.md" target="_blank" rel="noreferrer" className="text-primary hover:underline"><Users className="h-3.5 w-3.5 mr-1 inline-block" /> Identity Mesh</a>
            <a href="https://github.com/wagnerra23/oimpresso.com/blob/main/memory/governance/ENFORCEMENT.md" target="_blank" rel="noreferrer" className="text-primary hover:underline"><Shield className="h-3.5 w-3.5 mr-1 inline-block" /> Enforcement (8 mecanismos)</a>
            <a href="https://github.com/wagnerra23/oimpresso.com/blob/main/memory/governance/MODULE-DRIFT-MIGRATION-PLAN.md" target="_blank" rel="noreferrer" className="text-primary hover:underline"><Ruler className="h-3.5 w-3.5 mr-1 inline-block" /> Drift Migration Plan</a>
            <a href="https://github.com/wagnerra23/oimpresso.com/blob/main/memory/governance/audit-2026-05-05-v1.1.md" target="_blank" rel="noreferrer" className="text-primary hover:underline"><ClipboardList className="h-3.5 w-3.5 mr-1 inline-block" /> Audit cascata v1.1</a>
            <Link href="/copiloto/admin/memoria" className="text-primary hover:underline"><BookOpen className="h-3.5 w-3.5 mr-1 inline-block" /> KB completo →</Link>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

Dashboard.layout = (page: ReactNode) => <AppShellV2 children={page} />

export default Dashboard
