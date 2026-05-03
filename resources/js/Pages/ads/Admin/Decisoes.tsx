import React from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link, router, useForm } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { CheckCircle2, XCircle, Hourglass, ShieldAlert, Clock, Brain } from 'lucide-react'

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

function destinationBadge(d: Decision) {
  const map: Record<string, { color: string; label: string }> = {
    blocked:        { color: 'bg-red-100 text-red-700 border-red-300',         label: '🛡️ Bloqueado' },
    pending_wagner: { color: 'bg-amber-100 text-amber-800 border-amber-300',   label: '⏳ Pendente' },
    brain_b:        { color: 'bg-blue-100 text-blue-700 border-blue-300',      label: '🧠 Brain B' },
    brain_a:        { color: 'bg-emerald-100 text-emerald-700 border-emerald-300', label: '⚡ Brain A' },
    queued:         { color: 'bg-zinc-100 text-zinc-700 border-zinc-300',      label: '📋 Fila' },
  }
  const cfg = map[d.destination] ?? { color: '', label: d.destination }
  return <Badge className={`border ${cfg.color}`}>{cfg.label}</Badge>
}

function riskBadge(score: number) {
  const zone =
    score < 0.20 ? { color: 'bg-emerald-100 text-emerald-700', label: 'verde' } :
    score < 0.40 ? { color: 'bg-yellow-100 text-yellow-700',   label: 'amarela' } :
    score < 0.70 ? { color: 'bg-orange-100 text-orange-700',   label: 'laranja' } :
                   { color: 'bg-red-100 text-red-700',         label: 'vermelha' }
  return <Badge className={zone.color}>{score.toFixed(2)} {zone.label}</Badge>
}

const Decisoes: React.FC<Props> & { layout?: (p: React.ReactNode) => React.ReactNode } = ({ tab, decisions, kpis }) => {
  return (
    <div className="p-6 space-y-6">
      {/* KPIs */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <KpiCard icon={<Hourglass className="text-amber-600" />} label="Pendentes" value={kpis.pendentes} />
        <KpiCard icon={<Brain className="text-blue-600" />} label="Em andamento" value={kpis.em_andamento} />
        <KpiCard icon={<CheckCircle2 className="text-emerald-600" />} label="Concluídas (7d)" value={kpis.concluidas_7d} />
        <KpiCard icon={<XCircle className="text-red-600" />} label="Rejeitadas (7d)" value={kpis.rejeitadas_7d} />
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b">
        <TabLink current={tab} value="pendentes" label="Pendentes" />
        <TabLink current={tab} value="em_andamento" label="Em andamento" />
        <TabLink current={tab} value="historico" label="Histórico" />
      </div>

      {/* Lista */}
      {decisions.length === 0 ? (
        <Card><CardContent className="py-12 text-center text-zinc-500">Nenhuma decision nesta aba.</CardContent></Card>
      ) : (
        <div className="space-y-3">
          {decisions.map(d => (
            <Card key={d.id} className="hover:shadow-md transition-shadow">
              <CardContent className="py-4">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <Link
                        href={`/ads/admin/decisoes/${d.id}`}
                        className="font-mono text-sm text-zinc-500"
                      >#{d.id}</Link>
                      <span className="font-semibold">{d.event_type}</span>
                      <Badge variant="outline">{d.domain}</Badge>
                      {destinationBadge(d)}
                      {riskBadge(d.risk_score)}
                      <Badge variant="outline">conf {d.confidence_score.toFixed(2)}</Badge>
                      {d.hitl_level === 0 && <Badge className="bg-zinc-100 text-zinc-600">HiTL-0 autônomo</Badge>}
                      {d.hitl_level === 3 && <Badge className="bg-red-100 text-red-700">HiTL-3 humano</Badge>}
                    </div>
                    {d.instruction_short && (
                      <p className="text-sm text-zinc-600 mt-2 line-clamp-2">{d.instruction_short}</p>
                    )}
                    <div className="flex items-center gap-3 mt-2 text-xs text-zinc-500">
                      <span><Clock className="inline w-3 h-3 mr-1" />{new Date(d.created_at).toLocaleString('pt-BR')}</span>
                      {d.policy_applied && <span><ShieldAlert className="inline w-3 h-3 mr-1" />{d.policy_applied}</span>}
                      <span>fonte: {d.event_source}</span>
                    </div>
                  </div>

                  {tab === 'pendentes' && (
                    <ActionButtons decisionId={d.id} />
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}

function KpiCard({ icon, label, value }: { icon: React.ReactNode; label: string; value: number }) {
  return (
    <Card>
      <CardContent className="py-4 flex items-center gap-3">
        <div className="p-2 bg-zinc-50 rounded-lg">{icon}</div>
        <div>
          <div className="text-xs text-zinc-500">{label}</div>
          <div className="text-2xl font-bold">{value}</div>
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
    const reason = window.prompt('Motivo da rejeição (opcional, ajuda o ConfidenceEngine):') ?? ''
    router.post(`/ads/admin/decisoes/${decisionId}/reject`, { reason }, { preserveScroll: true })
  }
  return (
    <div className="flex gap-2">
      <Button size="sm" variant="outline" onClick={approve}>
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
