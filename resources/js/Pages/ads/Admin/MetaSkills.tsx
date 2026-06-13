// @ads
//   tela: /ads/admin/meta-skills
//   adrs: ARQ-0007 — regras SOFT de governança (Wagner Cognitive Control)

import React, { useState, type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { router, useForm } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Switch } from '@/Components/ui/switch'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Label } from '@/Components/ui/label'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'
import { Plus, Play, X, CheckCircle2 } from 'lucide-react'

interface Rule {
  id: number
  rule_key: string
  name: string
  description: string
  category: string
  condition: any
  condition_human: string
  action: { type: string; params?: any }
  enabled: boolean
  version: number
  triggered_count: number
  last_triggered_at: string | null
  created_by: string
}

interface CategoryGroup {
  category: string
  rules: Rule[]
}

interface Props {
  rules_by_category: CategoryGroup[]
  kpis: { total: number; enabled: number; triggered: number; categories: number }
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)

const categoryLabel: Record<string, string> = {
  promotion:  'Promoção (skill → ALLOW_BRAIN_A)',
  archival:   'Arquivamento (não usado)',
  escalation: 'Escalação (humano)',
  retry:      'Retry inteligente',
  budget:     'Orçamento e custo',
  review:     'Revisão automática',
}

const MetaSkills: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ rules_by_category, kpis }) => {
  const toggle = (id: number, enabled: boolean) => {
    router.post(`/ads/admin/meta-skills/${id}/toggle`, { enabled }, { preserveScroll: true })
  }

  const [showEditor, setShowEditor] = useState(false)

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="brain"
        title="ADS — Meta-skills"
        description="Regras SOFT de governança. Diferentes do Policy Engine (HARD firewall imutável): meta-skills são configuráveis aqui mesmo, com versão e contagem de triggers. Rege como skills evoluem."
        action={
          <Button onClick={() => setShowEditor(!showEditor)}>
            {showEditor ? <><X className="w-4 h-4 mr-1" /> Cancelar</> : <><Plus className="w-4 h-4 mr-1" /> Nova Meta-skill</>}
          </Button>
        }
      />

      {showEditor && <MetaSkillEditor onClose={() => setShowEditor(false)} />}

      <KpiGrid cols={4}>
        <KpiCard icon="brain"      tone="info"    label="Meta-skills totais" value={num(kpis.total)}      description="Cadastradas no sistema" />
        <KpiCard icon="check"      tone="success" label="Ativas"             value={num(kpis.enabled)}    description="Avaliadas em runtime" />
        <KpiCard icon="zap"        tone="warning" label="Triggers totais"    value={num(kpis.triggered)}  description="Acumulado histórico" />
        <KpiCard icon="layers"     tone="default" label="Categorias"         value={num(kpis.categories)} description="Agrupamento UI" />
      </KpiGrid>

      <Card className="border-zinc-200 bg-zinc-50/50">
        <CardContent className="py-4 text-sm text-muted-foreground">
          <strong className="text-foreground">Como funciona:</strong> regras são DSL JSON
          (<code className="bg-background px-1 py-0.5 rounded text-xs">condition</code> + <code className="bg-background px-1 py-0.5 rounded text-xs">action</code>).
          Avaliadas pelo <code className="bg-background px-1 py-0.5 rounded text-xs">GovernanceRulesService</code> contra contexto de cada decision/pattern.
          Mudança de regra cria nova versão (auditável). Wagner pode desativar via switch sem deletar.
        </CardContent>
      </Card>

      {rules_by_category.length === 0 ? (
        <Card><CardContent className="p-0"><EmptyState icon="brain" title="Sem meta-skills" description="A migração inicial cria 4 regras core. Reseed se necessário." /></CardContent></Card>
      ) : (
        rules_by_category.map(group => (
          <Card key={group.category}>
            <CardHeader>
              <CardTitle className="text-base">
                {categoryLabel[group.category] ?? group.category}
                <Badge variant="outline" className="ml-2">{group.rules.length}</Badge>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <ul className="divide-y divide-border">
                {group.rules.map(rule => (
                  <li key={rule.id} className="py-3 first:pt-0 last:pb-0">
                    <div className="flex items-start justify-between gap-4">
                      <div className="flex-1 min-w-0 space-y-2">
                        <div className="flex items-center gap-2 flex-wrap">
                          <h4 className="font-medium">{rule.name}</h4>
                          <code className="text-xs bg-zinc-100 px-1.5 py-0.5 rounded">{rule.rule_key}</code>
                          <Badge variant="outline">v{rule.version}</Badge>
                          {rule.triggered_count > 0 && (
                            <Badge className="bg-blue-600 text-white">{rule.triggered_count}× disparou</Badge>
                          )}
                        </div>
                        <p className="text-sm text-muted-foreground">{rule.description}</p>

                        <div className="text-xs space-y-1.5">
                          <div>
                            <span className="font-medium text-muted-foreground">Condição:</span>{' '}
                            <code className="bg-zinc-50 px-1.5 py-0.5 rounded">{rule.condition_human}</code>
                          </div>
                          <div>
                            <span className="font-medium text-muted-foreground">Ação:</span>{' '}
                            <code className="bg-zinc-50 px-1.5 py-0.5 rounded">{rule.action.type}</code>
                            {rule.action.params && (
                              <span className="text-muted-foreground ml-2">
                                params: {JSON.stringify(rule.action.params)}
                              </span>
                            )}
                          </div>
                        </div>
                      </div>

                      <div className="shrink-0 flex flex-col items-end gap-2">
                        <Switch
                          checked={rule.enabled}
                          onCheckedChange={(checked) => toggle(rule.id, checked)}
                          aria-label="Ativar/desativar regra"
                        />
                        <span className="text-xs text-muted-foreground">
                          {rule.enabled ? 'Ativa' : 'Pausada'}
                        </span>
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
        ))
      )}
    </div>
  )
}

// ─── Meta-skill Editor ───
function MetaSkillEditor({ onClose }: { onClose: () => void }) {
  const form = useForm<{
    rule_key: string
    name: string
    description: string
    category: string
    condition: any
    action: any
    enabled: boolean
  }>({
    rule_key: '',
    name: '',
    description: '',
    category: 'promotion',
    condition: { op: 'AND', conds: [{ field: 'wilson_lower_bound', op: '>=', value: 0.8 }] },
    action: { type: 'create_pending_decision', params: {} },
    enabled: false,
  })

  const [validating, setValidating] = useState(false)
  const [validation, setValidation] = useState<any>(null)

  const conditions = form.data.condition?.conds ?? []

  const updateCond = (idx: number, key: string, value: any) => {
    const next = [...conditions]
    next[idx] = { ...next[idx], [key]: value }
    form.setData('condition', { ...form.data.condition, conds: next })
  }

  const addCond = () => {
    const next = [...conditions, { field: 'total_count', op: '>=', value: 10 }]
    form.setData('condition', { ...form.data.condition, conds: next })
  }

  const removeCond = (idx: number) => {
    const next = conditions.filter((_: any, i: number) => i !== idx)
    form.setData('condition', { ...form.data.condition, conds: next })
  }

  const setOp = (op: string) => {
    form.setData('condition', { ...form.data.condition, op })
  }

  const validateRule = async () => {
    setValidating(true)
    setValidation(null)
    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
      const r = await fetch('/ads/admin/meta-skills/validate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ condition: form.data.condition }),
      })
      setValidation(await r.json())
    } catch (e: any) { setValidation({ ok: false, error: e.message }) }
    finally { setValidating(false) }
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    form.post('/ads/admin/meta-skills', { onSuccess: onClose })
  }

  return (
    <Card className="border-blue-300">
      <CardHeader><CardTitle className="text-base">Nova Meta-skill</CardTitle></CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-3">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <Label htmlFor="rule_key">Rule key (snake_case único)</Label>
              <Input id="rule_key" value={form.data.rule_key} onChange={e => form.setData('rule_key', e.target.value)}
                placeholder="ex: archive_old_skills" required />
              {form.errors.rule_key && <p className="text-xs text-destructive mt-1">{form.errors.rule_key}</p>}
            </div>
            <div>
              <Label htmlFor="category">Categoria</Label>
              <select
                id="category"
                className="w-full border rounded px-2 py-1.5 text-sm"
                value={form.data.category}
                onChange={e => form.setData('category', e.target.value)}
              >
                <option value="promotion">Promoção</option>
                <option value="archival">Arquivamento</option>
                <option value="escalation">Escalação</option>
                <option value="retry">Retry</option>
                <option value="budget">Budget</option>
                <option value="review">Review</option>
              </select>
            </div>
          </div>

          <div>
            <Label htmlFor="name">Nome legível</Label>
            <Input id="name" value={form.data.name} onChange={e => form.setData('name', e.target.value)}
              placeholder="ex: Arquivar skills sem uso há 60 dias" required />
          </div>

          <div>
            <Label htmlFor="description">Descrição (PT-BR)</Label>
            <Textarea id="description" rows={2} value={form.data.description} onChange={e => form.setData('description', e.target.value)}
              placeholder="O que essa regra faz e por quê" required />
          </div>

          {/* Condition builder */}
          <div className="border rounded-lg p-3 bg-muted/30 space-y-2">
            <div className="flex items-center gap-2 text-sm">
              <strong>Condição:</strong> avalia em
              <select value={form.data.condition.op} onChange={e => setOp(e.target.value)}
                className="border rounded px-2 py-0.5 text-xs">
                <option value="AND">TODOS verdadeiros (AND)</option>
                <option value="OR">QUALQUER verdadeira (OR)</option>
              </select>
            </div>
            {conditions.map((c: any, i: number) => (
              <div key={i} className="flex items-center gap-2 text-sm">
                <select value={c.field} onChange={e => updateCond(i, 'field', e.target.value)}
                  className="border rounded px-2 py-1 text-xs">
                  <option value="wilson_lower_bound">wilson_lower_bound</option>
                  <option value="success_count">success_count</option>
                  <option value="total_count">total_count</option>
                  <option value="success_rate">success_rate</option>
                  <option value="is_hardcoded">is_hardcoded</option>
                  <option value="days_since_last_outcome">days_since_last_outcome</option>
                  <option value="review_score">review_score</option>
                  <option value="review_confidence">review_confidence</option>
                  <option value="attempts">attempts</option>
                </select>
                <select value={c.op} onChange={e => updateCond(i, 'op', e.target.value)}
                  className="border rounded px-2 py-1 text-xs font-mono">
                  <option value="==">==</option>
                  <option value="!=">!=</option>
                  <option value="<">{'<'}</option>
                  <option value="<=">{'<='}</option>
                  <option value=">">{'>'}</option>
                  <option value=">=">{'>='}</option>
                </select>
                <Input className="w-32 text-xs" value={String(c.value)}
                  onChange={e => {
                    const v: any = e.target.value
                    const parsed = v === 'true' ? true : v === 'false' ? false : isNaN(Number(v)) ? v : Number(v)
                    updateCond(i, 'value', parsed)
                  }} />
                {conditions.length > 1 && (
                  <Button type="button" size="sm" variant="ghost" onClick={() => removeCond(i)}>
                    <X className="w-3 h-3" />
                  </Button>
                )}
              </div>
            ))}
            <Button type="button" size="sm" variant="outline" onClick={addCond}>
              <Plus className="w-3 h-3 mr-1" /> + condição
            </Button>
          </div>

          {/* Action — JSON cru por enquanto */}
          <div>
            <Label htmlFor="action">Ação (JSON)</Label>
            <Textarea id="action" rows={2} className="font-mono text-xs"
              value={JSON.stringify(form.data.action, null, 2)}
              onChange={e => {
                try { form.setData('action', JSON.parse(e.target.value)) } catch {}
              }} />
            <p className="text-xs text-muted-foreground mt-1">
              Tipos: create_pending_decision, tag_pattern, set_destination, schedule_retry
            </p>
          </div>

          <div className="flex items-center gap-2 pt-2 border-t">
            <Button type="button" variant="outline" onClick={validateRule} disabled={validating}>
              <Play className="w-3 h-3 mr-1" /> {validating ? 'Validando…' : 'Validar contra dados reais'}
            </Button>
            <Button type="submit" disabled={form.processing}>
              <CheckCircle2 className="w-3 h-3 mr-1" />
              {form.processing ? 'Salvando…' : 'Salvar como draft'}
            </Button>
            <p className="text-xs text-muted-foreground">
              Salva com enabled=false. Você ativa depois via switch.
            </p>
          </div>

          {validation && (
            <div className={`text-xs rounded p-2 border ${validation.ok ? 'bg-success-soft border-success/20' : 'bg-destructive-soft border-destructive/20'}`}>
              {validation.ok ? (
                <>
                  <strong>✓ Condição válida:</strong> {validation.samples_matched}/{validation.samples_total} patterns matchariam.
                  {validation.sample_matches && validation.sample_matches.length > 0 && (
                    <ul className="mt-1 ml-4 list-disc">
                      {validation.sample_matches.map((s: string, i: number) => <li key={i}>{s}</li>)}
                    </ul>
                  )}
                </>
              ) : (
                <><strong>✗ Erro:</strong> {validation.error}</>
              )}
            </div>
          )}
        </form>
      </CardContent>
    </Card>
  )
}

MetaSkills.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Meta-skills" breadcrumbItems={[{ label: 'ADS' }, { label: 'Meta-skills' }]}>
    {page}
  </AppShellV2>
)

export default MetaSkills
