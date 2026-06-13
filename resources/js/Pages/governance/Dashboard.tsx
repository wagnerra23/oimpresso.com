// @governance
//   tela: /governance
//   adrs: 0079 (Constituição Art. 8+9), 0086 (Fase 5 MVP)

import React, { type ReactNode } from 'react'
import { Deferred, Link } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'
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

const Dashboard: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({
  sdd,
  kpis,
  pending_adrs,
  audit_highlights,
  actiongate_mode,
  next_review_at,
  health_kpis,
  narratives,
}) => {
  const mode = modeBadge(actiongate_mode)

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
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
                      <Badge variant="outline" className={isError ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'}>
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
