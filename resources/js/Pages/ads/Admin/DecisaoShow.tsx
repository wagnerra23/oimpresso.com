import React from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link, router } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { ArrowLeft, CheckCircle2, XCircle } from 'lucide-react'

interface Instruction {
  title?: string
  summary?: string
  files_to_touch?: string[]
  risk_identified?: string
  rollback_plan?: string
  test_strategy?: string
  claude_code_instruction?: string
  confidence_in_instruction?: number
  raw?: string
  [k: string]: unknown
}

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
  model_used: string | null
  outcome: string
  tokens_used: number | null
  execution_ms: number | null
  files_affected: string[]
  event_metadata: Record<string, unknown>
  instruction: Instruction | null
  created_at: string
  resolved_at: string | null
}

interface Props { decision: Decision }

const DecisaoShow: React.FC<Props> & { layout?: (p: React.ReactNode) => React.ReactNode } = ({ decision }) => {
  const d = decision
  const i = d.instruction
  const isPending = d.outcome === 'cancelled' && (d.destination === 'pending_wagner' || d.destination === 'brain_b')

  const approve = () => router.post(`/ads/admin/decisoes/${d.id}/approve`)
  const reject = () => {
    const reason = window.prompt('Motivo da rejeição:') ?? ''
    router.post(`/ads/admin/decisoes/${d.id}/reject`, { reason })
  }

  return (
    <div className="p-6 max-w-5xl mx-auto space-y-4">
      <Link href="/ads/admin/decisoes" className="text-sm text-zinc-500 hover:text-zinc-900 inline-flex items-center gap-1">
        <ArrowLeft className="w-4 h-4" /> Voltar para inbox
      </Link>

      <Card>
        <CardHeader>
          <div className="flex items-start justify-between gap-4">
            <div>
              <CardTitle>#{d.id} — {i?.title || d.event_type}</CardTitle>
              <div className="flex items-center gap-2 mt-2 flex-wrap">
                <Badge variant="outline">{d.domain}</Badge>
                <Badge>{d.event_type}</Badge>
                <Badge variant="outline">risk {d.risk_score.toFixed(2)}</Badge>
                <Badge variant="outline">conf {d.confidence_score.toFixed(2)}</Badge>
                {d.policy_applied && <Badge>{d.policy_applied}</Badge>}
                <Badge>HiTL-{d.hitl_level}</Badge>
              </div>
            </div>
            {isPending && (
              <div className="flex gap-2">
                <Button onClick={approve}><CheckCircle2 className="w-4 h-4 mr-1" /> Aprovar</Button>
                <Button variant="outline" onClick={reject}><XCircle className="w-4 h-4 mr-1" /> Rejeitar</Button>
              </div>
            )}
          </div>
        </CardHeader>
      </Card>

      {i && (
        <Card>
          <CardHeader><CardTitle>Instrução do Brain B</CardTitle></CardHeader>
          <CardContent className="space-y-4 text-sm">
            {i.summary && <Section title="Resumo" body={i.summary} />}
            {i.risk_identified && <Section title="Risco identificado" body={i.risk_identified} />}
            {i.rollback_plan && <Section title="Plano de rollback" body={i.rollback_plan} />}
            {i.test_strategy && <Section title="Estratégia de teste" body={i.test_strategy} />}
            {i.files_to_touch && i.files_to_touch.length > 0 && (
              <div>
                <div className="text-xs uppercase text-zinc-500 font-semibold mb-1">Arquivos</div>
                <ul className="font-mono text-xs space-y-1">
                  {i.files_to_touch.map(f => <li key={f}>{f}</li>)}
                </ul>
              </div>
            )}
            {i.claude_code_instruction && (
              <div>
                <div className="text-xs uppercase text-zinc-500 font-semibold mb-1">Instrução para Claude Code</div>
                <pre className="bg-zinc-50 p-3 rounded text-xs whitespace-pre-wrap">{i.claude_code_instruction}</pre>
              </div>
            )}
            {typeof i.confidence_in_instruction === 'number' && (
              <div className="text-xs text-zinc-500">Confiança do Brain B na instrução: <strong>{(i.confidence_in_instruction * 100).toFixed(0)}%</strong></div>
            )}
            {i.raw && <pre className="bg-zinc-50 p-3 rounded text-xs whitespace-pre-wrap">{i.raw}</pre>}
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader><CardTitle>Metadados</CardTitle></CardHeader>
        <CardContent className="text-sm space-y-2">
          <KV k="Fonte do evento" v={d.event_source} />
          <KV k="Modelo usado"   v={d.model_used || '—'} />
          <KV k="Brain executor" v={d.brain_used} />
          <KV k="Tokens"         v={d.tokens_used ? String(d.tokens_used) : '—'} />
          <KV k="Latência (ms)"  v={d.execution_ms ? String(d.execution_ms) : '—'} />
          <KV k="Outcome"        v={d.outcome} />
          <KV k="Criado"         v={new Date(d.created_at).toLocaleString('pt-BR')} />
          <KV k="Resolvido"      v={d.resolved_at ? new Date(d.resolved_at).toLocaleString('pt-BR') : '—'} />
          {d.files_affected.length > 0 && (
            <div>
              <div className="text-xs uppercase text-zinc-500 font-semibold mt-3 mb-1">Arquivos afetados</div>
              <ul className="font-mono text-xs space-y-1">
                {d.files_affected.map(f => <li key={f}>{f}</li>)}
              </ul>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

function Section({ title, body }: { title: string; body: string }) {
  return (
    <div>
      <div className="text-xs uppercase text-zinc-500 font-semibold mb-1">{title}</div>
      <p className="text-sm text-zinc-700">{body}</p>
    </div>
  )
}

function KV({ k, v }: { k: string; v: string }) {
  return (
    <div className="flex">
      <div className="w-40 text-xs text-zinc-500">{k}</div>
      <div className="text-sm">{v}</div>
    </div>
  )
}

DecisaoShow.layout = (page: React.ReactNode) => (
  <AppShellV2
    title="ADS — Decisão"
    breadcrumbItems={[{ label: 'ADS' }, { label: 'Decisões', href: '/ads/admin/decisoes' }, { label: 'Detalhe' }]}
  >
    {page}
  </AppShellV2>
)

export default DecisaoShow
