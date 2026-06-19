// @ads
//   tela: /ads/admin/tools
//   adrs: T12 (Tool framework Anthropic-compatible) + Boost adapter

import React, { useState, type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Textarea } from '@/Components/ui/textarea'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import { Wrench, Eye, Edit3, BarChart3, Play, AlertTriangle, CheckCircle2, XCircle } from 'lucide-react'

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

interface Execution {
  id: number
  tool_name: string
  is_read_only: boolean
  ok: boolean
  error: string | null
  duration_ms: number | null
  triggered_by: string
  created_at: string
}

interface Props {
  tools_by_category: ToolGroup[]
  recent_executions: Execution[]
  kpis: { total: number; read_only: number; write: number; categories: number; executions_7d: number }
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)

const categoryIcon: Record<string, ReactNode> = {
  'leitura':                  <Eye className="w-5 h-5" />,
  'leitura (Laravel Boost)':  <Eye className="w-5 h-5" />,
  'escrita':                  <Edit3 className="w-5 h-5" />,
  'execução':                 <Play className="w-5 h-5" />,
  'análise':                  <BarChart3 className="w-5 h-5" />,
}

const Tools: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ tools_by_category, recent_executions, kpis }) => {
  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="wrench"
        title="ADS — Tools"
        description="Ferramentas que agentes podem invocar. Read-only chamável direto. Tools de escrita exigem aprovação Wagner. Audit log de toda invocação."
      />

      <KpiGrid cols={5}>
        <KpiCard icon="wrench"   tone="info"    label="Tools registradas"   value={num(kpis.total)}      description="Disponíveis" />
        <KpiCard icon="eye"      tone="success" label="Read-only"           value={num(kpis.read_only)}  description="Sem aprovação" />
        <KpiCard icon="edit-3"   tone="warning" label="Escrita"             value={num(kpis.write)}      description="Exigem Wagner" />
        <KpiCard icon="layers"   tone="default" label="Categorias"          value={num(kpis.categories)} description="UI groups" />
        <KpiCard icon="activity" tone="default" label="Execuções 7d"        value={num(kpis.executions_7d)} description="audit log" />
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
              {group.tools.map(tool => <ToolCard key={tool.name} tool={tool} />)}
            </div>
          </CardContent>
        </Card>
      ))}

      {recent_executions.length > 0 && (
        <Card>
          <CardHeader><CardTitle className="text-base">Execuções recentes (audit)</CardTitle></CardHeader>
          <CardContent className="p-0">
            <ul className="divide-y divide-border">
              {recent_executions.map(e => (
                <li key={e.id} className="px-4 py-2 text-sm flex items-center gap-3">
                  {e.ok
                    ? <CheckCircle2 className="w-4 h-4 text-success-fg" />
                    : <XCircle className="w-4 h-4 text-destructive" />}
                  <code className="text-xs">{e.tool_name}</code>
                  {e.is_read_only && <Badge variant="outline" className="text-xs">read</Badge>}
                  <span className="text-xs text-muted-foreground">por {e.triggered_by}</span>
                  {e.duration_ms !== null && (
                    <span className="text-xs text-muted-foreground">· {e.duration_ms}ms</span>
                  )}
                  <span className="text-xs text-muted-foreground ml-auto">
                    {new Date(e.created_at).toLocaleString('pt-BR')}
                  </span>
                  {e.error && <span className="text-xs text-destructive truncate max-w-md">· {e.error}</span>}
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      )}

      <Card className="border-zinc-200 bg-zinc-50/50">
        <CardContent className="py-4 text-sm text-muted-foreground">
          <strong className="text-foreground">Boost (preferência Wagner):</strong> as tools nas categorias
          "leitura (Laravel Boost)" são wrappers das ferramentas oficiais do <code className="bg-background px-1 py-0.5 rounded text-xs">laravel/boost</code> ^2.4.
          As de escrita (write_file, run_test, git_commit_wip) são customizadas com whitelist paranoica de paths.
        </CardContent>
      </Card>
    </div>
  )
}

function ToolCard({ tool }: { tool: ToolItem }) {
  const [showRun, setShowRun] = useState(false)
  const [inputJson, setInputJson] = useState('{}')
  const [running, setRunning] = useState(false)
  const [result, setResult] = useState<any>(null)

  const run = async () => {
    if (! tool.is_read_only) {
      if (! confirm(`⚠️ "${tool.name}" é tool de ESCRITA. Confirmar execução?`)) return
    }
    let parsed: any
    try { parsed = JSON.parse(inputJson || '{}') }
    catch { setResult({ ok: false, error: 'JSON inválido no input' }); return }

    setRunning(true)
    setResult(null)
    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
      const r = await fetch(`/ads/admin/tools/${tool.name}/execute`, {
        method: 'POST',
        headers: {
          'Content-Type':  'application/json',
          'X-CSRF-TOKEN':  csrf,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ input: parsed }),
      })
      const data = await r.json()
      setResult(data)
    } catch (e: any) {
      setResult({ ok: false, error: e.message })
    } finally {
      setRunning(false)
    }
  }

  return (
    <Card className="border-zinc-200">
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

        <div className="flex items-center gap-2">
          <Button size="sm" variant="outline" onClick={() => setShowRun(!showRun)}>
            <Play className="w-3 h-3 mr-1" />
            {showRun ? 'Esconder' : 'Try it'}
          </Button>
          <details className="text-xs">
            <summary className="cursor-pointer text-muted-foreground hover:text-foreground">
              Schema
            </summary>
            <pre className="mt-2 p-2 bg-muted/30 rounded overflow-x-auto text-[10px]">{JSON.stringify(tool.input_schema, null, 2)}</pre>
          </details>
        </div>

        {showRun && (
          <div className="space-y-2 pt-2 border-t">
            <div>
              <label className="text-xs font-medium text-muted-foreground">Input JSON</label>
              <Textarea
                value={inputJson}
                onChange={e => setInputJson(e.target.value)}
                rows={4}
                className="font-mono text-xs"
                placeholder='{"path": "Modules/X/Tests/Unit"}'
              />
            </div>
            <div className="flex items-center gap-2">
              <Button size="sm" onClick={run} disabled={running}>
                {running ? 'Executando…' : (
                  <><Play className="w-3 h-3 mr-1" /> Executar</>
                )}
              </Button>
              {! tool.is_read_only && (
                <span className="text-xs text-warning-fg inline-flex items-center gap-1">
                  <AlertTriangle className="w-3 h-3" /> Tool de escrita — confirmação será solicitada
                </span>
              )}
            </div>
            {result && (
              <div className={`text-xs rounded p-2 ${result.ok ? 'bg-success-soft border border-success/20' : 'bg-destructive-soft border border-destructive/20'}`}>
                <div className="flex items-center gap-1 font-medium mb-1">
                  {result.ok
                    ? <><CheckCircle2 className="w-3 h-3 text-success-fg" /> OK</>
                    : <><XCircle className="w-3 h-3 text-destructive" /> Erro</>}
                </div>
                <pre className="whitespace-pre-wrap break-all text-[10px]">{JSON.stringify(result, null, 2).slice(0, 2000)}</pre>
              </div>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  )
}

Tools.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Tools" breadcrumbItems={[{ label: 'ADS' }, { label: 'Tools' }]}>
    {page}
  </AppShellV2>
)

export default Tools
