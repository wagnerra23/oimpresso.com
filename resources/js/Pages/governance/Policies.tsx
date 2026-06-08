// @governance
//   tela: /governance/policies
//   adrs: 0079 Art. 8 (Policy Gating), 0086 (Fase 5 MVP)

import React, { useState, type ReactNode } from 'react'
import { router } from '@inertiajs/react'
import { toast } from 'sonner'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent } from '@/Components/ui/card'
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
  enabled: boolean
  version: number
  triggered_count: number
  created_by: string | null
  updated_at: string
}

interface Group {
  category: string
  rules: Rule[]
}

interface Props {
  rules_by_category: Group[]
  kpis: {
    total: number
    enabled: number
    triggered: number
    categories: number
  }
}

const Policies: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ rules_by_category, kpis }) => {
  // estado otimista por rule id: { [id]: enabled } sobrepõe o valor vindo das props
  const [overrides, setOverrides] = useState<Record<number, boolean>>({})
  const [pendingId, setPendingId] = useState<number | null>(null)

  const isEnabled = (rule: Rule) => overrides[rule.id] ?? rule.enabled

  const toggle = (id: number, current: boolean) => {
    const next = !current
    setPendingId(id)
    setOverrides((prev) => ({ ...prev, [id]: next })) // otimista: reflete antes da resposta

    router.post(`/governance/policies/${id}/toggle`, { enabled: next }, {
      preserveScroll: true,
      preserveState: true,
      onError: () => {
        setOverrides((prev) => ({ ...prev, [id]: current })) // rollback
        toast.error('Falha ao alterar a policy. Revertido.')
      },
      onFinish: () => setPendingId(null),
    })
  }

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="settings"
        title="Policies (Governança)"
        description="mcp_governance_rules — runtime gates enforçados pelo ActionGate (Constituição Art. 8). Toggle ativa/desativa rule. Edição inline + create vão pra próxima iteração."
      />

      <KpiGrid cols={4}>
        <KpiCard icon="layers" tone="info"     label="Rules total"     value={kpis.total.toString()} />
        <KpiCard icon="check"  tone="success"  label="Ativas"           value={kpis.enabled.toString()} />
        <KpiCard icon="zap"    tone="info"     label="Triggered total" value={kpis.triggered.toString()} description="Soma de hits desde criação" />
        <KpiCard icon="folder" tone="info"     label="Categorias"      value={kpis.categories.toString()} />
      </KpiGrid>

      {rules_by_category.length === 0 ? (
        <EmptyState icon="info" title="Sem rules ainda" description="Quando o decision flow ADS criar rules, elas aparecem aqui pra Wagner habilitar/desabilitar." />
      ) : (
        rules_by_category.map((group) => (
          <Card key={group.category}>
            <CardContent className="p-4">
              <h3 className="text-lg font-semibold mb-3 capitalize">{group.category}</h3>
              <ul className="space-y-2">
                {group.rules.map((rule) => (
                  <li key={rule.id} className="flex items-start gap-3 text-sm border-b border-border pb-2 last:border-0">
                    <Switch
                      checked={isEnabled(rule)}
                      disabled={pendingId === rule.id}
                      onCheckedChange={() => toggle(rule.id, isEnabled(rule))}
                      aria-label={isEnabled(rule) ? 'Desativar policy' : 'Ativar policy'}
                      className="mt-0.5 shrink-0"
                    />

                    <div className="flex-1">
                      <div className="font-mono text-xs text-muted-foreground">{rule.rule_key}</div>
                      <div className="font-medium">{rule.name}</div>
                      <div className="text-xs text-muted-foreground mt-1">{rule.description}</div>
                    </div>

                    <div className="flex flex-col items-end gap-1 shrink-0">
                      <Badge variant="outline" className="text-xs">v{rule.version}</Badge>
                      <span className="text-xs text-muted-foreground">{rule.triggered_count} hits</span>
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

Policies.layout = (page: ReactNode) => <AppShellV2 children={page} />

export default Policies
