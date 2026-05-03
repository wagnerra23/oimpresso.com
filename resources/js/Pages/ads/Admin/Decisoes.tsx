import React from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link, router } from '@inertiajs/react'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { CheckCircle2, XCircle, Hourglass, ShieldAlert, Clock, Brain, Info } from 'lucide-react'

interface Decision {
  id: number
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
  tab: 'pendentes' | 'em_andamento' | 'historico'
  decisions: Decision[]
  kpis: {
    pendentes: number
    em_andamento: number
    concluidas_7d: number
    rejeitadas_7d: number
  }
}

const statusColor: Record<string, string> = {
  blocked:        'bg-red-100 text-red-700 border-red-300',
  pending_wagner: 'bg-amber-100 text-amber-800 border-amber-300',
  brain_b:        'bg-blue-100 text-blue-700 border-blue-300',
  brain_a:        'bg-emerald-100 text-emerald-700 border-emerald-300',
  queued:         'bg-zinc-100 text-zinc-700 border-zinc-300',
}

const riskColor: Record<string, string> = {
  Baixo:    'bg-emerald-50 text-emerald-700 border-emerald-200',
  Médio:    'bg-yellow-50 text-yellow-700 border-yellow-200',
  Alto:     'bg-orange-50 text-orange-700 border-orange-200',
  Crítico:  'bg-red-50 text-red-700 border-red-200',
}

const Decisoes: React.FC<Props> & { layout?: (p: React.ReactNode) => React.ReactNode } = ({ tab, decisions, kpis }) => {
  return (
    <div className="p-6 space-y-6 max-w-6xl mx-auto">

      {/* Cabeçalho explicativo */}
      <div className="bg-zinc-50 border border-zinc-200 rounded-lg p-4 text-sm text-zinc-700">
        <div className="flex gap-2">
          <Info className="w-5 h-5 text-zinc-500 shrink-0 mt-0.5" />
          <div className="space-y-1">
            <p><strong>O que você vê aqui:</strong> decisões automatizadas que o sistema (ADS) detectou e roteou para Brain A (autônomo), Brain B (Claude API) ou para você.</p>
            <p><strong>O que precisa fazer:</strong> revisar itens com botão <em>Aprovar/Rejeitar</em> visível. Itens bloqueados pelo firewall e já executados são apenas para auditoria.</p>
          </div>
        </div>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <KpiCard icon={<Hourglass className="text-amber-600" />} label="Aguardando você" value={kpis.pendentes} hint="Precisam da sua decisão" />
        <KpiCard icon={<Brain className="text-blue-600" />}      label="Brain B processando" value={kpis.em_andamento} hint="Claude API gerando instruções" />
        <KpiCard icon={<CheckCircle2 className="text-emerald-600" />} label="Concluídas (7d)" value={kpis.concluidas_7d} hint="Aprovadas + executadas" />
        <KpiCard icon={<XCircle className="text-red-600" />}     label="Rejeitadas (7d)" value={kpis.rejeitadas_7d} hint="Você rejeitou — IA aprende" />
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b">
        <TabLink current={tab} value="pendentes" label="Aguardando ação" />
        <TabLink current={tab} value="em_andamento" label="Em andamento" />
        <TabLink current={tab} value="historico" label="Histórico" />
      </div>

      {/* Lista */}
      {decisions.length === 0 ? (
        <Card><CardContent className="py-12 text-center text-zinc-500">Nenhuma decisão nesta aba.</CardContent></Card>
      ) : (
        <div className="space-y-3">
          {decisions.map(d => <DecisionCard key={d.id} d={d} tab={tab} />)}
        </div>
      )}
    </div>
  )
}

function DecisionCard({ d, tab }: { d: Decision; tab: string }) {
  const statusBg = statusColor[d.destination] ?? 'bg-zinc-100 text-zinc-700 border-zinc-300'
  const riskBg = riskColor[d.risk_label] ?? 'bg-zinc-50 text-zinc-700 border-zinc-200'

  return (
    <Card className="hover:shadow-md transition-shadow">
      <CardContent className="py-4 space-y-3">
        {/* Linha 1 — resumo */}
        <div className="flex items-start justify-between gap-4">
          <div className="flex-1 min-w-0">
            <Link href={`/ads/admin/decisoes/${d.id}`} className="block">
              <h3 className="font-medium text-zinc-900 hover:underline">
                <span className="text-zinc-400 font-mono text-sm mr-2">#{d.id}</span>
                {d.one_line}
              </h3>
            </Link>
            <p className="text-sm text-zinc-600 mt-1">{d.action_hint}</p>
          </div>

          {/* Botões só se acionável */}
          {tab === 'pendentes' && d.actionable && (
            <ActionButtons decisionId={d.id} />
          )}
        </div>

        {/* Linha 2 — badges legíveis */}
        <div className="flex items-center gap-2 flex-wrap text-xs">
          <Badge className={`border ${statusBg}`} title="Estado da decisão">
            {d.status_label}
          </Badge>
          {d.policy_applied && (
            <Badge variant="outline" className="border-zinc-300" title="Regra do firewall (Policy Engine)">
              <ShieldAlert className="w-3 h-3 mr-1 inline" />
              {d.why_badge}
            </Badge>
          )}
          <Badge className={`border ${riskBg}`} title={`Score técnico: ${d.risk_score.toFixed(2)}`}>
            Risco {d.risk_label}
          </Badge>
        </div>

        {/* Instrução curta do Brain B (se houver) */}
        {d.instruction_short && (
          <div className="text-sm text-zinc-600 bg-zinc-50 rounded p-3 border border-zinc-200">
            <strong className="text-zinc-700">Brain B sugere:</strong> {d.instruction_short}
          </div>
        )}

        {/* Linha 3 — meta */}
        <div className="flex items-center gap-3 text-xs text-zinc-400">
          <span><Clock className="inline w-3 h-3 mr-1" />{new Date(d.created_at).toLocaleString('pt-BR')}</span>
          <span>Origem: {d.event_source}</span>
          <span>Módulo: {d.domain}</span>
        </div>
      </CardContent>
    </Card>
  )
}

function KpiCard({ icon, label, value, hint }: { icon: React.ReactNode; label: string; value: number; hint?: string }) {
  return (
    <Card>
      <CardContent className="py-4">
        <div className="flex items-center gap-3">
          <div className="p-2 bg-zinc-50 rounded-lg shrink-0">{icon}</div>
          <div className="min-w-0">
            <div className="text-xs text-zinc-500">{label}</div>
            <div className="text-2xl font-bold leading-tight">{value}</div>
            {hint && <div className="text-xs text-zinc-400 mt-0.5">{hint}</div>}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}

function TabLink({ current, value, label }: { current: string; value: string; label: string }) {
  const active = current === value
  return (
    <Link
      href={`/ads/admin/decisoes?tab=${value}`}
      className={`px-4 py-2 -mb-px border-b-2 text-sm font-medium ${
        active ? 'border-zinc-900 text-zinc-900' : 'border-transparent text-zinc-500 hover:text-zinc-700'
      }`}
    >{label}</Link>
  )
}

function ActionButtons({ decisionId }: { decisionId: number }) {
  const approve = () => router.post(`/ads/admin/decisoes/${decisionId}/approve`, {}, { preserveScroll: true })
  const reject = () => {
    const reason = window.prompt('Motivo da rejeição (opcional, ajuda o ConfidenceEngine a aprender):') ?? ''
    router.post(`/ads/admin/decisoes/${decisionId}/reject`, { reason }, { preserveScroll: true })
  }
  return (
    <div className="flex gap-2 shrink-0">
      <Button size="sm" onClick={approve}>
        <CheckCircle2 className="w-4 h-4 mr-1" /> Aprovar
      </Button>
      <Button size="sm" variant="outline" onClick={reject}>
        <XCircle className="w-4 h-4 mr-1" /> Rejeitar
      </Button>
    </div>
  )
}

Decisoes.layout = (page: React.ReactNode) => (
  <AppShellV2 title="ADS — Decisões" breadcrumbItems={[{ label: 'ADS' }, { label: 'Decisões' }]}>
    {page}
  </AppShellV2>
)

export default Decisoes
