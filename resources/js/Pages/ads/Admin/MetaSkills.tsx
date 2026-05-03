// @ads
//   tela: /ads/admin/meta-skills
//   adrs: ARQ-0007 — regras SOFT de governança (Wagner Cognitive Control)

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { router } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Switch } from '@/Components/ui/switch'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import EmptyState from '@/Components/shared/EmptyState'

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

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="brain"
        title="ADS — Meta-skills"
        description="Regras SOFT de governança. Diferentes do Policy Engine (HARD firewall imutável): meta-skills são configuráveis aqui mesmo, com versão e contagem de triggers. Rege como skills evoluem."
      />

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

MetaSkills.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Meta-skills" breadcrumbItems={[{ label: 'ADS' }, { label: 'Meta-skills' }]}>
    {page}
  </AppShellV2>
)

export default MetaSkills
