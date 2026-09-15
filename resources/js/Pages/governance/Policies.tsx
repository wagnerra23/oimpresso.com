// @governance
//   tela: /governance/policies
//   adrs: 0079 Art. 8 (Policy Gating), 0086 (Fase 5 MVP)

import React, { useMemo, useState, type ReactNode } from 'react'
import { router } from '@inertiajs/react'
import { toast } from 'sonner'
import { TriangleAlert } from 'lucide-react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import { Button } from '@/Components/ui/button'
import { Inline } from '@/Components/layout'
import { Card, CardContent } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Input } from '@/Components/ui/input'
import { Switch } from '@/Components/ui/switch'
import PageHeader from '@/Components/shared/PageHeader'
import GovernancaSubNav from '@/Pages/governance/_shared/GovernancaSubNav'
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
  const [search, setSearch] = useState('')

  const isEnabled = (rule: Rule) => overrides[rule.id] ?? rule.enabled

  // Busca local sobre o catálogo já carregado — não re-consulta o backend.
  // Anti-hook do charter ("NÃO esconder rules disabled"): sem termo digitado a lista
  // volta inteira, ativas e desligadas; o filtro é ação explícita do operador.
  const filteredGroups = useMemo(() => {
    const q = search.trim().toLowerCase()
    if (!q) return rules_by_category

    return rules_by_category
      .map((group) => ({
        ...group,
        rules: group.rules.filter((r) =>
          `${r.rule_key} ${r.name} ${r.description} ${group.category}`.toLowerCase().includes(q),
        ),
      }))
      .filter((group) => group.rules.length > 0)
  }, [rules_by_category, search])

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
      <GovernancaSubNav active="policies" />
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

      {/*
        Aviso de rastro ausente — o anti-hook do charter ("Toggle sem registrar histórico…
        sem isso, audit fica cego") descreve o estado ATUAL do vivo: `mcp_governance_rule_history`
        não tem migration (Fase 5+1, TODO em PoliciesController.php:19 e PolicyToggleService.php:17).
        Quando a tabela existir, este bloco sai junto.
      */}
      {rules_by_category.length > 0 && (
        <Alert>
          <TriangleAlert className="h-4 w-4 text-warning" />
          <AlertTitle>Alternar não deixa rastro</AlertTitle>
          <AlertDescription>
            A tabela de histórico de regras ainda não existe. Enquanto for assim, ligar ou desligar
            uma política muda o enforcement em runtime e a auditoria fica cega justamente para essa
            mudança — quem alterna precisa saber disso na hora, não depois.
          </AlertDescription>
        </Alert>
      )}

      {rules_by_category.length > 0 && (
        <Card>
          <CardContent className="py-3">
            <Inline gap={3} align="center" wrap>
              <Input
                type="search"
                placeholder="Buscar por chave, nome ou categoria…"
                aria-label="Buscar política"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="max-w-xs"
              />
              <span className="text-xs text-muted-foreground">
                Desligadas continuam na lista — são elas que você precisa achar para reativar.
              </span>
            </Inline>
          </CardContent>
        </Card>
      )}

      {rules_by_category.length === 0 ? (
        <EmptyState icon="info" title="Sem rules ainda" description="Quando o decision flow ADS criar rules, elas aparecem aqui pra Wagner habilitar/desabilitar." />
      ) : filteredGroups.length === 0 ? (
        <EmptyState
          icon="search-x"
          variant="search"
          title="Nenhuma política bate com essa busca"
          description="Limpe o campo para ver o catálogo inteiro — ativas primeiro, depois por categoria e chave."
          action={<Button size="sm" onClick={() => setSearch('')}>Limpar busca</Button>}
        />
      ) : (
        filteredGroups.map((group) => (
          <Card key={group.category}>
            <CardContent className="p-4">
              <Inline asChild gap={2} align="center">
                <h3 className="text-lg font-semibold mb-3 capitalize">
                  {group.category}
                  <span className="font-mono text-xs font-normal text-muted-foreground">
                    {group.rules.length}
                  </span>
                </h3>
              </Inline>
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
