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
import { ArrowLeft, CheckCircle2, XCircle, Archive, ShieldAlert, Workflow } from 'lucide-react'

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

interface ChainSkill {
  id: number
  description: string
  success_count: number
  total_count: number
  success_rate: number
  is_hardcoded: boolean
}

interface ChainMetaSkill {
  id: number
  rule_key: string
  name: string
  category: string
  triggered_count: number
}

interface ChainParent {
  id: number
  event_type: string
  domain: string
  destination: string
  outcome: string
}

interface ChainChild {
  id: number
  event_type: string
  domain: string
  destination: string
  outcome: string
  review_score: number | null
}

interface Chain {
  parent: ChainParent | null
  children: ChainChild[]
  skill: ChainSkill | null
  meta_skills: ChainMetaSkill[]
  review_breakdown: any
}

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
  model_used: string | null
  outcome: string
  tokens_used: number | null
  execution_ms: number | null
  files_affected: string[]
  event_metadata: Record<string, unknown>
  instruction: Instruction | null
  created_at: string
  resolved_at: string | null
  review_score: number | null
  review_confidence: number | null
  attempts: number
}

interface Props { decision: Decision; chain: Chain }

const DecisaoShow: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ decision, chain }) => {
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

      {/* Drill-down chain — decisão → skill → meta-skills → policy */}
      <DrillDownChain decision={d} chain={chain} />

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

function DrillDownChain({ decision: d, chain }: { decision: Decision; chain: Chain }) {
  const hasParent   = !!chain.parent
  const hasChildren = chain.children.length > 0
  const hasSkill    = !!chain.skill
  const hasMeta     = chain.meta_skills.length > 0

  if (!hasParent && !hasChildren && !hasSkill && !hasMeta) return null

  return (
    <Card className="border-blue-500/20 bg-blue-500/5">
      <CardHeader>
        <CardTitle className="text-base">Cadeia de raciocínio</CardTitle>
        <p className="text-sm text-muted-foreground">
          Como o sistema decidiu: contexto pai/filhas, skill aprendida e meta-skills aplicáveis.
        </p>
      </CardHeader>
      <CardContent className="space-y-4">

        {/* Parent (se for subtarefa) */}
        {hasParent && chain.parent && (
          <ChainBlock title="Decisão pai" icon="↑">
            <Link
              href={`/ads/admin/decisoes/${chain.parent.id}`}
              className="text-sm font-medium text-primary hover:underline"
            >
              #{String(chain.parent.id).padStart(4, '0')} — {chain.parent.event_type}
            </Link>
            <div className="text-xs text-muted-foreground mt-1">
              {chain.parent.domain} · destino: {chain.parent.destination} · outcome: {chain.parent.outcome}
            </div>
          </ChainBlock>
        )}

        {/* Children (subtarefas) */}
        {hasChildren && (
          <ChainBlock title={`Subtarefas geradas (${chain.children.length})`} icon="↓">
            <ul className="space-y-1">
              {chain.children.map(c => (
                <li key={c.id} className="text-sm flex items-center gap-2 flex-wrap">
                  <Link href={`/ads/admin/decisoes/${c.id}`} className="text-primary hover:underline font-mono text-xs">
                    #{String(c.id).padStart(4, '0')}
                  </Link>
                  <code className="text-xs">{c.event_type}</code>
                  <Badge variant="outline" className="text-xs">{c.destination}</Badge>
                  <Badge variant="outline" className="text-xs">{c.outcome}</Badge>
                  {c.review_score !== null && (
                    <Badge className={c.review_score >= 70 ? 'bg-emerald-600 text-white' : 'bg-amber-600 text-white'}>
                      review {c.review_score}/100
                    </Badge>
                  )}
                </li>
              ))}
            </ul>
          </ChainBlock>
        )}

        {/* Skill aprendida */}
        {hasSkill && chain.skill && (
          <ChainBlock title="Skill aprendido (mcp_decision_patterns)" icon="⚡">
            <div className="text-sm">
              <Link href="/ads/admin/skills" className="text-primary hover:underline font-medium">
                {chain.skill.description}
              </Link>
              <div className="text-xs text-muted-foreground mt-1 flex items-center gap-2 flex-wrap">
                <span>{chain.skill.success_count}/{chain.skill.total_count} sucessos</span>
                <span>·</span>
                <span>taxa {(chain.skill.success_rate * 100).toFixed(1)}%</span>
                {chain.skill.is_hardcoded && <Badge className="bg-zinc-700 text-white text-xs">Hardcoded</Badge>}
              </div>
            </div>
          </ChainBlock>
        )}

        {/* Meta-skills aplicáveis */}
        {hasMeta && (
          <ChainBlock title={`Meta-skills aplicáveis (${chain.meta_skills.length})`} icon={<Workflow className="h-3 w-3" />}>
            <ul className="space-y-1.5">
              {chain.meta_skills.map(m => (
                <li key={m.id} className="text-sm">
                  <Link href="/ads/admin/meta-skills" className="text-primary hover:underline font-medium">
                    {m.name}
                  </Link>
                  <div className="text-xs text-muted-foreground">
                    <code className="text-xs bg-zinc-100 px-1 py-0.5 rounded">{m.rule_key}</code>
                    <span className="ml-2">categoria: {m.category}</span>
                    {m.triggered_count > 0 && <span className="ml-2 text-blue-600">{m.triggered_count}× disparou</span>}
                  </div>
                </li>
              ))}
            </ul>
          </ChainBlock>
        )}

        {/* Review breakdown se disponível */}
        {chain.review_breakdown && d.review_score !== null && (
          <ChainBlock title="Avaliação do ReviewerAgent (G-Eval)" icon="⭐">
            <div className="text-sm space-y-2">
              <div className="flex items-center gap-2 flex-wrap">
                <Badge className={d.review_score >= 70 ? 'bg-emerald-600 text-white' : 'bg-amber-600 text-white'}>
                  Score {d.review_score}/100
                </Badge>
                {d.review_confidence !== null && (
                  <Badge variant="outline">conf {(d.review_confidence * 100).toFixed(0)}%</Badge>
                )}
                {d.attempts > 0 && <Badge variant="outline">tentativa {d.attempts}/3</Badge>}
              </div>

              {chain.review_breakdown.correctness !== undefined && (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-1 text-xs">
                  <ScoreBar label="Correctness" value={chain.review_breakdown.correctness} />
                  <ScoreBar label="Safety"      value={chain.review_breakdown.safety} />
                  <ScoreBar label="Quality"     value={chain.review_breakdown.quality} />
                  <ScoreBar label="Cost"        value={chain.review_breakdown.cost_efficiency} />
                </div>
              )}

              {Array.isArray(chain.review_breakdown.issues) && chain.review_breakdown.issues.length > 0 && (
                <div>
                  <div className="text-xs font-medium text-destructive-fg">Issues identificadas:</div>
                  <ul className="text-xs space-y-0.5 mt-1">
                    {chain.review_breakdown.issues.slice(0, 5).map((iss: string, i: number) => (
                      <li key={i} className="text-muted-foreground">• {iss}</li>
                    ))}
                  </ul>
                </div>
              )}

              {Array.isArray(chain.review_breakdown.strengths) && chain.review_breakdown.strengths.length > 0 && (
                <div>
                  <div className="text-xs font-medium text-success-fg">Pontos positivos:</div>
                  <ul className="text-xs space-y-0.5 mt-1">
                    {chain.review_breakdown.strengths.slice(0, 5).map((s: string, i: number) => (
                      <li key={i} className="text-muted-foreground">• {s}</li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          </ChainBlock>
        )}
      </CardContent>
    </Card>
  )
}

function ChainBlock({ title, icon, children }: { title: string; icon: React.ReactNode; children: React.ReactNode }) {
  return (
    <div className="border-l-2 border-blue-300 pl-3 py-1">
      <div className="text-xs uppercase font-semibold text-blue-700 mb-1.5 flex items-center gap-1">
        <span>{icon}</span>
        <span>{title}</span>
      </div>
      {children}
    </div>
  )
}

function ScoreBar({ label, value }: { label: string; value: number }) {
  const color = value >= 70 ? 'bg-emerald-500' : value >= 50 ? 'bg-amber-500' : 'bg-red-500'
  return (
    <div>
      <div className="flex justify-between text-xs">
        <span className="text-muted-foreground">{label}</span>
        <span className="font-mono">{value}</span>
      </div>
      <div className="h-1.5 bg-muted rounded overflow-hidden mt-0.5">
        <div className={`h-full ${color}`} style={{ width: `${value}%` }} />
      </div>
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
