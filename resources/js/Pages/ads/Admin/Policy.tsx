// @ads
//   tela: /ads/admin/policy
//   adrs: ARQ-0006 (Policy Engine firewall imutável)

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import PageHeader from '@/Components/shared/PageHeader'
import { ShieldX, AlertTriangle, Brain, CheckCheck, Lock } from 'lucide-react'

interface RuleItem { event_type: string; label: string }
interface Category {
  category: string
  description: string
  count: number
  items: RuleItem[]
}
interface Props { rules: Category[] }

const categoryConfig: Record<string, { icon: ReactNode; color: string; title: string }> = {
  BLOCK_ALWAYS: {
    icon: <ShieldX className="w-5 h-5" />,
    color: 'border-destructive/30 bg-destructive/5',
    title: 'Bloqueado sempre',
  },
  REQUIRE_HUMAN_REVIEW: {
    icon: <AlertTriangle className="w-5 h-5" />,
    color: 'border-amber-500/30 bg-amber-500/5',
    title: 'Exige humano',
  },
  REQUIRE_BRAIN_B: {
    icon: <Brain className="w-5 h-5" />,
    color: 'border-blue-500/30 bg-blue-500/5',
    title: 'Exige Brain B (Claude API)',
  },
  ALLOW_BRAIN_A: {
    icon: <CheckCheck className="w-5 h-5" />,
    color: 'border-emerald-500/30 bg-emerald-500/5',
    title: 'Brain A pode autônomo',
  },
}

const Policy: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ rules }) => {
  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="shield-check"
        title="ADS — Policy Engine"
        description="Regras imutáveis do firewall. Read-only — mudança só via PR no git, aprovado por Wagner (ARQ-0006)."
      />

      <Card className="border-warning/30 bg-warning-soft">
        <CardContent className="py-4 flex items-start gap-3">
          <Lock className="w-5 h-5 text-warning-fg shrink-0 mt-0.5" />
          <div className="text-sm">
            <p className="font-medium">Firewall imutável</p>
            <p className="text-muted-foreground">
              Estas regras estão hardcoded em <code className="text-xs bg-background px-1 py-0.5 rounded">Modules/ADS/Services/PolicyEngine.php</code>.
              Nenhuma LLM pode lê-las, sugerir alterações ou contorná-las. Para modificar uma regra:
              abrir PR no GitHub com justificativa, Wagner aprova, merge faz a mudança.
            </p>
          </div>
        </CardContent>
      </Card>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {rules.map(category => {
          const cfg = categoryConfig[category.category] ?? categoryConfig.REQUIRE_BRAIN_B
          return (
            <Card key={category.category} className={`border ${cfg.color}`}>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  {cfg.icon}
                  {cfg.title}
                  <Badge variant="outline" className="ml-auto">{category.count}</Badge>
                </CardTitle>
                <CardDescription className="text-xs">
                  <code className="bg-background px-1 py-0.5 rounded text-[10px]">{category.category}</code>
                </CardDescription>
                <p className="text-sm text-muted-foreground mt-2">{category.description}</p>
              </CardHeader>
              <CardContent>
                <ul className="space-y-1.5">
                  {category.items.map(item => (
                    <li key={item.event_type} className="text-sm flex items-start gap-2 py-1">
                      <span className="text-muted-foreground">•</span>
                      <div className="min-w-0 flex-1">
                        <div className="font-medium">{item.label}</div>
                        <code className="text-[10px] text-muted-foreground">{item.event_type}</code>
                      </div>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )
        })}
      </div>
    </div>
  )
}

Policy.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Policy Engine" breadcrumbItems={[{ label: 'ADS' }, { label: 'Policy Engine' }]}>
    {page}
  </AppShellV2>
)

export default Policy
