// @ads
//   tela: /ads/admin/decisoes/{id}
//   adrs: ARQ-0008 (HiTL), ARQ-0009 (Decision Memory)

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Link, router } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import PageHeader from '@/Components/shared/PageHeader'
import StatusBadge from '@/Components/shared/StatusBadge'
import { ArrowLeft, CheckCircle2, XCircle, Archive, ShieldAlert } from 'lucide-react'

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

const DecisaoShow: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ decision }) => {
  const d = decision
  const i = d.instruction
  const isPending = d.outcome === 'cancelled' && (d.destination === 'pending_wagner' || d.destination === 'brain_b')
  const isBlocked = d.destination === 'blocked'

  const approve = () => router.post(`/ads/admin/decisoes/${d.id}/approve`)
  const reject = () => {
    const reason = window.prompt('Motivo da rejeição:') ?? ''
    router.post(`/ads/admin/decisoes/${d.id}/reject`, { reason })
  }
  const dismiss = () => router.post(`/ads/admin/decisoes/${d.id}/dismiss`)

  return (
    <div className="mx-auto max-w-5xl p-6 space-y-4">
      {/* Voltar */}
      <Link href="/ads/admin/decisoes" className="text-sm text-muted-foreground hover:text-foreground inline-flex items-center gap-1">
        <ArrowLeft className="w-4 h-4" /> Voltar para inbox
      </Link>

      {/* PageHeader canônico */}
      <PageHeader
        icon="brain"
        title={i?.title || `Decisão #${String(d.id).padStart(4, '0')}`}
        description={`${d.event_type} · ${d.domain} · ${new Date(d.created_at).toLocaleString('pt-BR')}`}
        action={
          isPending && !isBlocked ? (
            <div className="flex gap-2">
              <Button onClick={approve}><CheckCircle2 className="w-4 h-4 mr-1" /> Aprovar</Button>
              <Button variant="outline" onClick={reject}><XCircle className="w-4 h-4 mr-1" /> Rejeitar</Button>
            </div>
          ) : isBlocked ? (
            <Button variant="outline" onClick={dismiss}>
              <Archive className="w-4 h-4 mr-1" /> Dispensar
            </Button>
          ) : null
        }
      />

      {/* Status e badges */}
      <Card>
        <CardContent className="py-4">
          <div className="flex items-center gap-2 flex-wrap">
            <StatusBadge kind="ads_destination" value={d.destination} />
            <Badge variant="outline">HiTL-{d.hitl_level}</Badge>
            <Badge variant="outline" className="font-mono text-xs">risk {d.risk_score.toFixed(2)}</Badge>
            <Badge variant="outline" className="font-mono text-xs">conf {d.confidence_score.toFixed(2)}</Badge>
            {d.policy_applied && (
              <Badge variant="outline" className="gap-1">
                <ShieldAlert className="w-3 h-3" /> {d.policy_applied}
              </Badge>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Instrução do Brain B (se houver) */}
      {i && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center justify-between">
              <span>Instrução do Brain B</span>
              {typeof i.confidence_in_instruction === 'number' && (
                <Badge variant="outline" className="font-mono">
                  Confiança: {(i.confidence_in_instruction * 100).toFixed(0)}%
                </Badge>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4 text-sm">
            {i.summary         && <Section title="Resumo"               body={i.summary} />}
            {i.risk_identified && <Section title="Risco identificado"   body={i.risk_identified} />}
            {i.rollback_plan   && <Section title="Plano de rollback"    body={i.rollback_plan} />}
            {i.test_strategy   && <Section title="Estratégia de teste"  body={i.test_strategy} />}

            {Array.isArray(i.files_to_touch) && i.files_to_touch.length > 0 && (
              <div>
                <div className="text-xs uppercase text-muted-foreground font-semibold mb-1">Arquivos</div>
                <ul className="font-mono text-xs space-y-1 bg-muted/30 p-3 rounded">
                  {i.files_to_touch.map(f => <li key={f}>{f}</li>)}
                </ul>
              </div>
            )}

            {i.claude_code_instruction && (
              <div>
                <div className="text-xs uppercase text-muted-foreground font-semibold mb-1">Instrução para Claude Code</div>
                <pre className="bg-muted/30 p-3 rounded text-xs whitespace-pre-wrap border">{i.claude_code_instruction}</pre>
              </div>
            )}

            {i.raw && (
              <details className="group">
                <summary className="cursor-pointer text-xs text-muted-foreground hover:text-foreground">Ver JSON cru</summary>
                <pre className="bg-muted/30 p-3 rounded text-xs whitespace-pre-wrap border mt-2">{i.raw}</pre>
              </details>
            )}
          </CardContent>
        </Card>
      )}

      {/* Metadados técnicos */}
      <Card>
        <CardHeader><CardTitle className="text-base">Detalhes técnicos</CardTitle></CardHeader>
        <CardContent className="text-sm grid grid-cols-1 md:grid-cols-2 gap-2">
          <KV k="ID"             v={`#${String(d.id).padStart(4, '0')}`} />
          <KV k="Event type"     v={d.event_type} mono />
          <KV k="Fonte do evento" v={d.event_source} />
          <KV k="Domínio"        v={d.domain} />
          <KV k="Modelo usado"   v={d.model_used || '—'} mono />
          <KV k="Brain executor" v={d.brain_used} />
          <KV k="Tokens"         v={d.tokens_used ? d.tokens_used.toLocaleString('pt-BR') : '—'} />
          <KV k="Latência (ms)"  v={d.execution_ms ? `${d.execution_ms.toLocaleString('pt-BR')} ms` : '—'} />
          <KV k="Outcome"        v={d.outcome} />
          <KV k="Criado"         v={new Date(d.created_at).toLocaleString('pt-BR')} />
          <KV k="Resolvido"      v={d.resolved_at ? new Date(d.resolved_at).toLocaleString('pt-BR') : '—'} />

          {d.files_affected.length > 0 && (
            <div className="md:col-span-2">
              <div className="text-xs uppercase text-muted-foreground font-semibold mt-3 mb-1">Arquivos afetados</div>
              <ul className="font-mono text-xs space-y-1 bg-muted/30 p-3 rounded">
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
      <div className="text-xs uppercase text-muted-foreground font-semibold mb-1">{title}</div>
      <p className="text-sm">{body}</p>
    </div>
  )
}

function KV({ k, v, mono = false }: { k: string; v: string; mono?: boolean }) {
  return (
    <div className="flex border-b border-border/50 py-1.5">
      <div className="w-40 text-xs text-muted-foreground">{k}</div>
      <div className={`text-sm flex-1 ${mono ? 'font-mono text-xs' : ''}`}>{v}</div>
    </div>
  )
}

DecisaoShow.layout = (page: ReactNode) => (
  <AppShellV2
    title="ADS — Decisão"
    breadcrumbItems={[
      { label: 'ADS' },
      { label: 'Decisões', href: '/ads/admin/decisoes' },
      { label: 'Detalhe' },
    ]}
  >
    {page}
  </AppShellV2>
)

export default DecisaoShow
