// @ads
//   tela: /ads/admin/decisoes
//   module: ADS
//   adrs: UI-0006 (padrão operacional), UI-0008 (Cockpit), ARQ-0008 (HiTL 4 níveis)
//   status: implementada
//   permissao: superadmin (V1) → ads.decisoes.review (V2)

import React, { useEffect, useRef, useState, type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link, router } from '@inertiajs/react'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import StatusBadge from '@/Components/shared/StatusBadge'
import EmptyState from '@/Components/shared/EmptyState'
import { CheckCircle2, XCircle, ShieldAlert, Clock, Archive, ExternalLink } from 'lucide-react'

interface Decision {
  id: number
  parent_decision_id: number | null
  event_type: string
  event_source: string
  domain: string
  risk_score: number
  confidence_score: number
  policy_applied: string | null
  destination: string
  hitl_level: number
  brain_used: string
  outcome: string
  instruction_short: string | null
  created_at: string
  resolved_at: string | null

  one_line: string
  why_badge: string
  status_label: string
  actionable: boolean
  action_hint: string
  risk_label: string
}

interface Props {
  tab: 'pendentes' | 'em_andamento' | 'subtarefas' | 'historico'
  decisions: Decision[]
  kpis: {
    pendentes: number
    em_andamento: number
    concluidas_7d: number
    rejeitadas_7d: number
    subtarefas: number
  }
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)

const Decisoes: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ tab, decisions, kpis }) => {
  const setTab = (value: string) => router.get('/ads/admin/decisoes', { tab: value }, { preserveState: false })

  // Auto-refresh: polling 10s nas abas operacionais (não no histórico)
  const [autoRefresh, setAutoRefresh] = useState(true)
  const lastRefresh = useRef<number>(Date.now())

  useEffect(() => {
    if (!autoRefresh) return
    if (tab === 'historico') return // histórico não muda em tempo real

    const id = setInterval(() => {
      // Só refresh se o usuário NÃO está mexendo (sem foco em input)
      if (document.hasFocus() && document.activeElement?.tagName !== 'INPUT') {
        router.reload({ only: ['decisions', 'kpis'], preserveScroll: true, preserveState: true })
        lastRefresh.current = Date.now()
      }
    }, 10000)
    return () => clearInterval(id)
  }, [autoRefresh, tab])

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">

      {/* 1. Cabeçalho operacional canônico (UI-0006) */}
      <PageHeader
        icon="brain"
        title="ADS — Decisões"
        description="Decisões automatizadas detectadas e roteadas pelo Adaptive Decision System. Aprove, rejeite ou dispense para auditoria."
        action={tab !== 'historico' && (
          <button
            type="button"
            onClick={() => setAutoRefresh(!autoRefresh)}
            className={`text-xs px-3 py-1.5 rounded-md border transition-colors flex items-center gap-1.5 ${
              autoRefresh
                ? 'bg-success-soft text-success-fg border-success/30'
                : 'bg-muted text-muted-foreground border-border'
            }`}
            title={autoRefresh ? 'Auto-refresh a cada 10s' : 'Clique para reativar auto-refresh'}
          >
            <span className={`w-2 h-2 rounded-full ${autoRefresh ? 'bg-success animate-pulse' : 'bg-muted-foreground'}`} />
            {autoRefresh ? 'Live (10s)' : 'Pausado'}
          </button>
        )}
      />

      {/* 2. KPIs como filtros (clicáveis, selected = aba ativa) */}
      <KpiGrid cols={5}>
        <KpiCard
          icon="hourglass"
          tone="warning"
          label="Aguardando você"
          value={num(kpis.pendentes)}
          description="Precisam de decisão"
          onClick={() => setTab('pendentes')}
          selected={tab === 'pendentes'}
        />
        <KpiCard
          icon="brain"
          tone="info"
          label="Brain B processando"
          value={num(kpis.em_andamento)}
          description="Claude API gerando"
          onClick={() => setTab('em_andamento')}
          selected={tab === 'em_andamento'}
        />
        <KpiCard
          icon="git-branch"
          tone="default"
          label="Subtarefas"
          value={num(kpis.subtarefas)}
          description="Geradas pelo Planner"
          onClick={() => setTab('subtarefas')}
          selected={tab === 'subtarefas'}
        />
        <KpiCard
          icon="check-circle-2"
          tone="success"
          label="Concluídas 7d"
          value={num(kpis.concluidas_7d)}
          description="Aprovadas + executadas"
          onClick={() => setTab('historico')}
          selected={tab === 'historico'}
        />
        <KpiCard
          icon="x-circle"
          tone="danger"
          label="Rejeitadas 7d"
          value={num(kpis.rejeitadas_7d)}
          description="IA aprende"
        />
      </KpiGrid>

      {/* 3. Lista canônica — Card + lista de decisões OU EmptyState */}
      <Card>
        <CardContent className="p-0">
          {decisions.length === 0 ? (
            <EmptyState
              icon="inbox"
              title={emptyTitle(tab)}
              description={emptyDescription(tab)}
            />
          ) : (
            <ul className="divide-y divide-border">
              {decisions.map(d => <DecisionRow key={d.id} d={d} tab={tab} />)}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

function DecisionRow({ d, tab }: { d: Decision; tab: string }) {
  return (
    <li className="px-4 py-4 hover:bg-muted/30 transition-colors">
      <div className="flex items-start justify-between gap-4">
        {/* Lado esquerdo — conteúdo principal estilo "Ordem" */}
        <div className="flex-1 min-w-0 space-y-2">
          {/* Linha 1: ID + título + link de detalhe */}
          <div className="flex items-center gap-2 flex-wrap">
            <span className="font-mono text-xs text-muted-foreground tabular-nums">
              #{String(d.id).padStart(4, '0')}
            </span>
            {d.parent_decision_id && (
              <Link
                href={`/ads/admin/decisoes/${d.parent_decision_id}`}
                className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                title="Esta é subtarefa — clique pra ver decisão pai"
              >
                ↳ subtarefa de #{String(d.parent_decision_id).padStart(4, '0')}
              </Link>
            )}
            <Link
              href={`/ads/admin/decisoes/${d.id}`}
              className="font-medium text-foreground hover:underline inline-flex items-center gap-1"
            >
              {d.one_line}
              <ExternalLink className="w-3 h-3 opacity-50" />
            </Link>
          </div>

          {/* Linha 2: badges canônicos */}
          <div className="flex items-center gap-2 flex-wrap text-xs">
            <StatusBadge kind="ads_destination" value={d.destination} />
            <StatusBadge kind="ads_risco" value={d.risk_label} />
            {d.policy_applied && (
              <Badge variant="outline" className="font-medium gap-1">
                <ShieldAlert className="w-3 h-3" />
                {d.why_badge}
              </Badge>
            )}
          </div>

          {/* Linha 3: hint de ação + meta */}
          {d.action_hint && (
            <p className="text-sm text-muted-foreground">{d.action_hint}</p>
          )}

          {/* Instrução do Brain B (resumida) */}
          {d.instruction_short && (
            <div className="text-sm rounded-md bg-blue-500/5 border border-blue-500/20 p-3">
              <strong className="text-foreground">Brain B sugere:</strong>{' '}
              <span className="text-muted-foreground">{d.instruction_short}</span>
            </div>
          )}

          {/* Linha 4: meta info (timestamp · origem · módulo) */}
          <div className="flex items-center gap-3 text-xs text-muted-foreground">
            <span className="inline-flex items-center gap-1">
              <Clock className="w-3 h-3" />
              {new Date(d.created_at).toLocaleString('pt-BR')}
            </span>
            <span>·</span>
            <span>Origem: <strong className="text-foreground/70">{d.event_source}</strong></span>
            <span>·</span>
            <span>Módulo: <strong className="text-foreground/70">{d.domain}</strong></span>
          </div>
        </div>

        {/* Lado direito — ações contextuais */}
        <div className="shrink-0">
          {tab === 'pendentes' && d.actionable && <ActionButtons decisionId={d.id} />}
          {tab === 'pendentes' && !d.actionable && <DismissButton decisionId={d.id} />}
        </div>
      </div>
    </li>
  )
}

function emptyTitle(tab: string): string {
  return {
    pendentes: 'Nenhuma decisão aguardando',
    em_andamento: 'Nenhuma decisão em processamento',
    subtarefas: 'Nenhuma subtarefa ativa',
    historico: 'Histórico vazio',
  }[tab] ?? 'Sem decisões'
}

function emptyDescription(tab: string): string {
  return {
    pendentes: 'Quando o Brain A detectar eventos que precisam da sua atenção, eles aparecerão aqui.',
    em_andamento: 'Quando o Brain B começar a gerar instruções via Claude API, elas aparecerão aqui em até 5 minutos.',
    subtarefas: 'Quando o PlannerAgent decompor decisões complexas, as subtarefas aparecem aqui com link pra decisão pai.',
    historico: 'Decisões concluídas, rejeitadas ou dispensadas aparecerão aqui.',
  }[tab] ?? ''
}

function ActionButtons({ decisionId }: { decisionId: number }) {
  const approve = () => router.post(`/ads/admin/decisoes/${decisionId}/approve`, {}, { preserveScroll: true })
  const reject = () => {
    const reason = window.prompt('Motivo da rejeição (opcional, ajuda o ConfidenceEngine a aprender):') ?? ''
    router.post(`/ads/admin/decisoes/${decisionId}/reject`, { reason }, { preserveScroll: true })
  }
  return (
    <div className="flex gap-2">
      <Button size="sm" onClick={approve}>
        <CheckCircle2 className="w-4 h-4 mr-1" /> Aprovar
      </Button>
      <Button size="sm" variant="outline" onClick={reject}>
        <XCircle className="w-4 h-4 mr-1" /> Rejeitar
      </Button>
    </div>
  )
}

function DismissButton({ decisionId }: { decisionId: number }) {
  const dismiss = () => router.post(`/ads/admin/decisoes/${decisionId}/dismiss`, {}, { preserveScroll: true })
  return (
    <Button size="sm" variant="outline" onClick={dismiss} title="Move para Histórico — sem ação executada">
      <Archive className="w-4 h-4 mr-1" /> Dispensar
    </Button>
  )
}

Decisoes.layout = (page: ReactNode) => (
  <AppShellV2
    title="ADS — Decisões"
    breadcrumbItems={[{ label: 'ADS' }, { label: 'Decisões' }]}
  >
    {page}
  </AppShellV2>
)

export default Decisoes
