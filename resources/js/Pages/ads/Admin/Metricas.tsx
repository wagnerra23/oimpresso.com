// @ads
//   tela: /ads/admin/metricas
//   adrs: ARQ-0007 (Learning Loop), ARQ-0009 (Decision Memory)

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Deferred } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Skeleton } from '@/Components/ui/skeleton'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'

interface Distribuicao {
  brain_a: number
  brain_b: number
  blocked: number
  pending: number
}
interface Kpis {
  total: number
  last30d: number
  taxa_autonomia: number
  taxa_humano: number
  taxa_firewall: number
  modificadas: number
  rejeitadas: number
  sucessos: number
  custo_total_usd: number
  tokens_total: number
}

interface Props {
  // vêm via Inertia::defer (MetricasController) — undefined no first render
  kpis?: Kpis
  distribuicao?: Distribuicao
  por_dominio?: Array<{ domain: string; count: number }>
  por_event_type?: Array<{ event_type: string; count: number }>
}

const KPIS_FALLBACK: Kpis = {
  total: 0, last30d: 0, taxa_autonomia: 0, taxa_humano: 0, taxa_firewall: 0,
  modificadas: 0, rejeitadas: 0, sucessos: 0, custo_total_usd: 0, tokens_total: 0,
}

const DISTRIBUICAO_FALLBACK: Distribuicao = { brain_a: 0, brain_b: 0, blocked: 0, pending: 0 }

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)
const pct = (v: number) => `${v.toFixed(1)}%`
const usd = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 4 }).format(v)

function StackedBar({ d, total }: { d: Distribuicao; total: number }) {
  if (total === 0) return null
  const segs = [
    { key: 'brain_a',  label: 'Brain A (autônomo)',   value: d.brain_a, color: 'bg-emerald-500' },
    { key: 'brain_b',  label: 'Brain B (Claude)',     value: d.brain_b, color: 'bg-blue-500' },
    { key: 'blocked',  label: 'Bloqueado (firewall)', value: d.blocked, color: 'bg-red-500' },
    { key: 'pending',  label: 'Aguardando humano',    value: d.pending, color: 'bg-amber-500' },
  ]
  return (
    <div className="space-y-3">
      <div className="flex h-8 w-full overflow-hidden rounded-md border">
        {segs.map(s => {
          const w = total > 0 ? (s.value / total) * 100 : 0
          if (w === 0) return null
          return (
            <div
              key={s.key}
              className={`${s.color} flex items-center justify-center text-xs font-medium text-white`}
              style={{ width: `${w}%` }}
              title={`${s.label}: ${s.value} (${w.toFixed(1)}%)`}
            >
              {w >= 8 && pct(w)}
            </div>
          )
        })}
      </div>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
        {segs.map(s => (
          <div key={s.key} className="flex items-center gap-2">
            <div className={`w-3 h-3 rounded ${s.color}`} />
            <span className="text-muted-foreground">{s.label}</span>
            <span className="ml-auto tabular-nums font-medium">{s.value}</span>
          </div>
        ))}
      </div>
    </div>
  )
}

function TopList({ title, rows, keyField }: { title: string; rows: Array<Record<string, any>>; keyField: string }) {
  const max = Math.max(1, ...rows.map(r => r.count))
  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{title}</CardTitle></CardHeader>
      <CardContent>
        {rows.length === 0 ? (
          <p className="text-sm text-muted-foreground">Sem dados ainda.</p>
        ) : (
          <ul className="space-y-2">
            {rows.map(r => (
              <li key={r[keyField]} className="space-y-1">
                <div className="flex items-center justify-between text-sm">
                  <span className="font-mono text-xs">{r[keyField]}</span>
                  <span className="tabular-nums font-medium">{r.count}</span>
                </div>
                <div className="h-1.5 bg-muted rounded overflow-hidden">
                  <div className="h-full bg-primary" style={{ width: `${(r.count / max) * 100}%` }} />
                </div>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}

const Metricas: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ kpis, distribuicao, por_dominio, por_event_type }) => {
  const k = kpis ?? KPIS_FALLBACK
  const dist = distribuicao ?? DISTRIBUICAO_FALLBACK
  const dominios = por_dominio ?? []
  const eventTypes = por_event_type ?? []

  // Só mostra empty-state quando kpis JÁ resolveu (não no first render deferido,
  // senão o <Deferred> abaixo nunca dispara o fetch).
  if (kpis !== undefined && k.total === 0) {
    return (
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <PageHeader
          icon="bar-chart-3"
          title="ADS — Métricas"
          description="Adoção, distribuição por agente e custo do Adaptive Decision System."
        />
        <Card>
          <CardContent className="p-0">
            <EmptyState
              icon="bar-chart-3"
              title="Sem decisões ainda"
              description="Métricas aparecem assim que o Brain A daemon começar a roteer eventos. Verifique se o systemd ads-brain-a está rodando no CT 100."
            />
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="bar-chart-3"
        title="ADS — Métricas"
        description="Adoção, distribuição por agente e custo do Adaptive Decision System."
      />

      <Deferred
        data={['kpis', 'distribuicao', 'por_dominio', 'por_event_type']}
        fallback={(
          <div className="space-y-4">
            <Skeleton className="h-24 w-full" />
            <Skeleton className="h-32 w-full" />
            <Skeleton className="h-64 w-full" />
          </div>
        )}
      >
      <KpiGrid cols={4}>
        <KpiCard
          icon="activity"
          tone="info"
          label="Total de decisões"
          value={num(k.total)}
          description={`${num(k.last30d)} nos últimos 30 dias`}
        />
        <KpiCard
          icon="zap"
          tone="success"
          label="Taxa de autonomia"
          value={pct(k.taxa_autonomia)}
          description="% executado por Brain A sem humano"
        />
        <KpiCard
          icon="user-check"
          tone="warning"
          label="Taxa que exige humano"
          value={pct(k.taxa_humano)}
          description="% que escala pra você"
        />
        <KpiCard
          icon="shield-check"
          tone="danger"
          label="Bloqueios firewall"
          value={pct(k.taxa_firewall)}
          description="% bloqueado por Policy"
        />
      </KpiGrid>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Distribuição por destino</CardTitle>
        </CardHeader>
        <CardContent>
          <StackedBar d={dist} total={k.total} />
        </CardContent>
      </Card>

      <KpiGrid cols={4}>
        <KpiCard
          icon="check-circle-2"
          tone="success"
          label="Aprovadas + executadas"
          value={num(k.sucessos)}
          description="outcome=success"
        />
        <KpiCard
          icon="edit-3"
          tone="warning"
          label="Aprovadas com modificação"
          value={num(k.modificadas)}
          description="Wagner ajustou (peso 3× p/ aprendizado)"
        />
        <KpiCard
          icon="x-circle"
          tone="danger"
          label="Rejeitadas"
          value={num(k.rejeitadas)}
          description="Wagner rejeitou — IA aprende"
        />
        <KpiCard
          icon="dollar-sign"
          tone="default"
          label="Custo Brain B"
          value={usd(k.custo_total_usd)}
          description={`${num(k.tokens_total)} tokens`}
        />
      </KpiGrid>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <TopList title="Top 10 — Domínios mais ativos" rows={dominios} keyField="domain" />
        <TopList title="Top 10 — Tipos de evento mais frequentes" rows={eventTypes} keyField="event_type" />
      </div>
      </Deferred>
    </div>
  )
}

Metricas.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Métricas" breadcrumbItems={[{ label: 'ADS' }, { label: 'Métricas' }]}>
    {page}
  </AppShellV2>
)

export default Metricas
