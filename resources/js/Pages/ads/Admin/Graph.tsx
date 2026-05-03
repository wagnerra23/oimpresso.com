// @ads
//   tela: /ads/admin/graph
//   adrs: Cognitive Control Panel #3 — Knowledge Graph

import React, { useMemo, type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import ReactFlow, {
  Background, Controls, MiniMap, MarkerType,
  type Node as RFNode, type Edge as RFEdge,
} from 'reactflow'
import 'reactflow/dist/style.css'
import { Brain, Zap, Wrench, Shield, Database } from 'lucide-react'

interface GraphNode {
  id: string
  type: string
  data: any
  position: { x: number; y: number }
}

interface GraphEdge {
  id: string
  source: string
  target: string
  label?: string
  animated?: boolean
}

interface Props {
  nodes: GraphNode[]
  edges: GraphEdge[]
  kpis: { skills: number; metaskills: number; tools: number; memory_docs: number }
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)

/**
 * Layout determinístico simples (sem dagre).
 * Memory no centro, círculos concêntricos para os outros tipos.
 */
function layoutNodes(nodes: GraphNode[]): RFNode[] {
  const groups: Record<string, GraphNode[]> = {
    memory: [], skill: [], metaskill: [], tool: [], policy: [],
  }
  nodes.forEach(n => groups[n.type]?.push(n))

  const positions: RFNode[] = []
  const cx = 600
  const cy = 350

  // Memory: centro
  groups.memory.forEach((n, i) => {
    positions.push({
      id: n.id, type: 'default',
      position: { x: cx, y: cy },
      data: nodeData(n, '💾'),
      style: nodeStyle(n.type),
    })
  })

  // Skills: círculo interno (raio 200)
  const skillCount = groups.skill.length
  groups.skill.forEach((n, i) => {
    const angle = (i / skillCount) * 2 * Math.PI
    positions.push({
      id: n.id, type: 'default',
      position: { x: cx + Math.cos(angle) * 200, y: cy + Math.sin(angle) * 200 },
      data: nodeData(n, '⚡'),
      style: nodeStyle(n.type),
    })
  })

  // Meta-skills: à esquerda
  groups.metaskill.forEach((n, i) => {
    positions.push({
      id: n.id, type: 'default',
      position: { x: cx - 480, y: 100 + i * 110 },
      data: nodeData(n, '🧬'),
      style: nodeStyle(n.type),
    })
  })

  // Tools: à direita
  groups.tool.forEach((n, i) => {
    positions.push({
      id: n.id, type: 'default',
      position: { x: cx + 380, y: 100 + i * 90 },
      data: nodeData(n, '🔧'),
      style: nodeStyle(n.type),
    })
  })

  // Policy: rodapé
  groups.policy.forEach((n, i) => {
    positions.push({
      id: n.id, type: 'default',
      position: { x: 100 + i * 240, y: cy + 280 },
      data: nodeData(n, '🛡️'),
      style: nodeStyle(n.type),
    })
  })

  return positions
}

function nodeData(n: GraphNode, icon: string): any {
  const lines: string[] = []
  if (n.data.success_rate !== undefined) lines.push(`taxa ${(n.data.success_rate * 100).toFixed(0)}%`)
  if (n.data.total_count !== undefined)  lines.push(`${n.data.total_count} exec`)
  if (n.data.triggered_count !== undefined) lines.push(`${n.data.triggered_count}× trig`)
  if (n.data.count !== undefined)        lines.push(`${n.data.count} itens`)
  if (n.data.is_hardcoded)               lines.push('🔒 hardcoded')
  if (n.data.enabled === false)          lines.push('⏸ pausada')
  if (n.data.is_read_only)               lines.push('👁️ read-only')

  return {
    label: (
      <div className="text-center">
        <div className="text-xs">{icon}</div>
        <div className="font-semibold text-xs leading-tight">{n.data.label}</div>
        {lines.length > 0 && (
          <div className="text-[9px] text-zinc-500 mt-0.5">{lines.join(' · ')}</div>
        )}
      </div>
    ),
  }
}

function nodeStyle(type: string): any {
  const styles: Record<string, any> = {
    memory:    { background: '#3b82f6', color: 'white', border: '2px solid #1e40af', borderRadius: 8, padding: 10, width: 140 },
    skill:     { background: '#fef3c7', color: '#78350f', border: '1px solid #f59e0b', borderRadius: 8, padding: 8, width: 130 },
    metaskill: { background: '#ddd6fe', color: '#4c1d95', border: '1px solid #8b5cf6', borderRadius: 8, padding: 8, width: 160 },
    tool:      { background: '#d1fae5', color: '#064e3b', border: '1px solid #10b981', borderRadius: 8, padding: 8, width: 140 },
    policy:    { background: '#fee2e2', color: '#7f1d1d', border: '1px solid #ef4444', borderRadius: 8, padding: 8, width: 180 },
  }
  return styles[type] ?? {}
}

const Graph: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ nodes, edges, kpis }) => {
  const rfNodes = useMemo(() => layoutNodes(nodes), [nodes])
  const rfEdges = useMemo<RFEdge[]>(() => edges.map(e => ({
    id: e.id,
    source: e.source,
    target: e.target,
    label: e.label,
    animated: e.animated ?? false,
    markerEnd: { type: MarkerType.ArrowClosed },
    style: { strokeWidth: 1, stroke: '#94a3b8' },
    labelStyle: { fontSize: 10, fill: '#64748b' },
  })), [edges])

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="git-branch"
        title="ADS — Knowledge Graph"
        description="Visualização das relações entre Memory ↔ Skills ↔ Meta-skills ↔ Tools ↔ Policy. Arraste pra rearranjar. Use scroll/pinch para zoom."
      />

      <KpiGrid cols={4}>
        <KpiCard icon="database"   tone="info"    label="Docs em Memory"  value={num(kpis.memory_docs)} description="MCP server" />
        <KpiCard icon="zap"        tone="warning" label="Skills"          value={num(kpis.skills)}      description="Top 15 por uso" />
        <KpiCard icon="brain"      tone="default" label="Meta-skills"     value={num(kpis.metaskills)}  description="Regras de governança" />
        <KpiCard icon="wrench"     tone="success" label="Tools"           value={num(kpis.tools)}       description="Ferramentas registradas" />
      </KpiGrid>

      <Card>
        <CardHeader>
          <CardTitle className="text-base flex items-center justify-between">
            <span>Mapa de relações</span>
            <Legend />
          </CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          <div style={{ height: 700 }} className="border-t">
            <ReactFlow
              nodes={rfNodes}
              edges={rfEdges}
              fitView
              minZoom={0.3}
              maxZoom={2}
              nodesDraggable
              defaultEdgeOptions={{ markerEnd: { type: MarkerType.ArrowClosed } }}
            >
              <Background />
              <Controls />
              <MiniMap
                nodeColor={(node) => {
                  const colors: Record<string, string> = {
                    memory:    '#3b82f6',
                    skill:     '#f59e0b',
                    metaskill: '#8b5cf6',
                    tool:      '#10b981',
                    policy:    '#ef4444',
                  }
                  // Match pelo prefixo do id pra cor
                  const prefix = (node.id.split('-')[0]) as string
                  return colors[prefix] ?? '#94a3b8'
                }}
              />
            </ReactFlow>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

function Legend() {
  const items = [
    { color: '#3b82f6', label: 'Memory', icon: <Database className="w-3 h-3" /> },
    { color: '#f59e0b', label: 'Skills', icon: <Zap className="w-3 h-3" /> },
    { color: '#8b5cf6', label: 'Meta-skills', icon: <Brain className="w-3 h-3" /> },
    { color: '#10b981', label: 'Tools', icon: <Wrench className="w-3 h-3" /> },
    { color: '#ef4444', label: 'Policy', icon: <Shield className="w-3 h-3" /> },
  ]
  return (
    <div className="flex items-center gap-3 text-xs">
      {items.map(i => (
        <div key={i.label} className="flex items-center gap-1">
          <div className="w-3 h-3 rounded" style={{ background: i.color }} />
          <span className="text-muted-foreground">{i.label}</span>
        </div>
      ))}
    </div>
  )
}

Graph.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Knowledge Graph" breadcrumbItems={[{ label: 'ADS' }, { label: 'Knowledge Graph' }]}>
    {page}
  </AppShellV2>
)

export default Graph
