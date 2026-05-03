// @ads
//   tela: /ads/admin/tools
//   adrs: T12 (Tool framework Anthropic-compatible)

import React, { type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import { Wrench, Eye, Edit3, BarChart3 } from 'lucide-react'

interface ToolItem {
  name: string
  description: string
  category: string
  is_read_only: boolean
  input_schema: any
}

interface ToolGroup {
  category: string
  tools: ToolItem[]
}

interface Props {
  tools_by_category: ToolGroup[]
  kpis: { total: number; read_only: number; categories: number }
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)

const categoryIcon: Record<string, ReactNode> = {
  'leitura':  <Eye className="w-5 h-5" />,
  'escrita':  <Edit3 className="w-5 h-5" />,
  'análise':  <BarChart3 className="w-5 h-5" />,
}

const Tools: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ tools_by_category, kpis }) => {
  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="wrench"
        title="ADS — Tools"
        description="Ferramentas que agentes (Brain A/B) podem invocar. Cada tool tem schema JSON Anthropic-compatible. Tools read-only podem ser chamadas autonomamente; tools de escrita exigem aprovação humana."
      />

      <KpiGrid cols={3}>
        <KpiCard icon="wrench"   tone="info"    label="Tools registradas"   value={num(kpis.total)}      description="Disponíveis para agentes" />
        <KpiCard icon="eye"      tone="success" label="Read-only"           value={num(kpis.read_only)}  description="Sem aprovação humana" />
        <KpiCard icon="layers"   tone="default" label="Categorias"          value={num(kpis.categories)} description="Agrupamento UI" />
      </KpiGrid>

      {tools_by_category.map(group => (
        <Card key={group.category}>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 capitalize">
              {categoryIcon[group.category] ?? <Wrench className="w-5 h-5" />}
              {group.category}
              <Badge variant="outline" className="ml-auto">{group.tools.length}</Badge>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {group.tools.map(tool => (
                <Card key={tool.name} className="border-zinc-200">
                  <CardContent className="py-4 space-y-3">
                    <div className="flex items-start gap-2 flex-wrap">
                      <code className="font-semibold text-sm bg-zinc-100 px-2 py-0.5 rounded">{tool.name}</code>
                      {tool.is_read_only ? (
                        <Badge className="bg-emerald-600 text-white">Read-only</Badge>
                      ) : (
                        <Badge className="bg-amber-600 text-white">Write (HiTL-2)</Badge>
                      )}
                    </div>
                    <p className="text-sm text-muted-foreground">{tool.description}</p>

                    <details className="text-xs">
                      <summary className="cursor-pointer text-muted-foreground hover:text-foreground">
                        Ver input schema
                      </summary>
                      <pre className="mt-2 p-2 bg-muted/30 rounded overflow-x-auto">{JSON.stringify(tool.input_schema, null, 2)}</pre>
                    </details>
                  </CardContent>
                </Card>
              ))}
            </div>
          </CardContent>
        </Card>
      ))}

      <Card className="border-zinc-200 bg-zinc-50/50">
        <CardContent className="py-4 text-sm text-muted-foreground">
          <strong className="text-foreground">Como agentes invocam tools:</strong>{' '}
          Brain B (Claude API) recebe schemas via{' '}
          <code className="bg-background px-1 py-0.5 rounded text-xs">ToolRegistry::schemasForLlm()</code>{' '}
          e usa Anthropic tool use. Tool read-only roda direto;
          tool de escrita gera decision pendente Wagner com instrução.
        </CardContent>
      </Card>
    </div>
  )
}

Tools.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Tools" breadcrumbItems={[{ label: 'ADS' }, { label: 'Tools' }]}>
    {page}
  </AppShellV2>
)

export default Tools
