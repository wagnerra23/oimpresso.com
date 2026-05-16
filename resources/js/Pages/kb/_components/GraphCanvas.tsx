/**
 * GraphCanvas — wrapper Reactflow tipado
 * =======================================
 *
 * Encapsula o componente Reactflow + Background + Controls + MiniMap configurados
 * pro KB. Recebe os nodes/edges já filtrados+roteados pela page mãe e devolve
 * callbacks tipados (onNodeClick, onNodeDoubleClick, onPaneClick).
 *
 * Decisão da lib (ver Graph.tsx topo): **Reactflow 11** já instalado. Render performance
 * via React 19 + lib decente até ~5k nodes. Layout client-side em `_lib/graphLayout.ts`.
 *
 * Acessibilidade WCAG: nodes têm `aria-label` derivado do title; tab navega entre eles
 * (Reactflow não traz keyboard nav nativo robusto — TODO[CL]: avaliar focus trap).
 *
 * Agent E (ONDA 5) — 2026-05-15
 */

import * as React from 'react';
import ReactFlow, {
  Background, BackgroundVariant,
  Controls, ControlButton,
  MiniMap, MarkerType,
  type Node as RFNode, type Edge as RFEdge,
  type ReactFlowProps,
} from 'reactflow';
import 'reactflow/dist/style.css';
import { Maximize2 } from 'lucide-react';
import { NODE_COLORS } from '../_lib/graphLayout';
import type { KbGraphNode } from '../_lib/graphTypes';

interface Props {
  rfNodes: RFNode[];
  rfEdges: RFEdge[];
  onNodeClick?: (nodeId: string) => void;
  onNodeDoubleClick?: (nodeId: string) => void;
  onPaneClick?: () => void;
  height?: number | string;
}

export default function GraphCanvas({
  rfNodes,
  rfEdges,
  onNodeClick,
  onNodeDoubleClick,
  onPaneClick,
  height = '100%',
}: Props) {
  const handleNodeClick: ReactFlowProps['onNodeClick'] = (_e, node) => {
    onNodeClick?.(node.id);
  };

  const handleNodeDoubleClick: ReactFlowProps['onNodeDoubleClick'] = (_e, node) => {
    onNodeDoubleClick?.(node.id);
  };

  const handlePaneClick: ReactFlowProps['onPaneClick'] = () => {
    onPaneClick?.();
  };

  // Pré-computa cor do mini-map por prefixo de id ou pelo tipo (kbNode.type) no data.
  const miniMapNodeColor = React.useCallback((node: RFNode) => {
    const kb = (node.data as { kbNode?: KbGraphNode })?.kbNode;
    if (kb) {
      const c = NODE_COLORS[kb.type];
      return c?.stroke ?? '#94a3b8';
    }
    // fallback por prefixo
    const prefix = node.id.split('-')[0];
    return NODE_COLORS[prefix]?.stroke ?? '#94a3b8';
  }, []);

  return (
    <div
      data-slot="graph-canvas"
      style={{ height }}
      className="w-full bg-background relative"
      role="application"
      aria-label="Canvas do grafo de conhecimento"
    >
      <ReactFlow
        nodes={rfNodes}
        edges={rfEdges}
        fitView
        fitViewOptions={{ padding: 0.2, duration: 250 }}
        minZoom={0.2}
        maxZoom={2.5}
        nodesDraggable
        nodesConnectable={false}
        elementsSelectable
        onNodeClick={handleNodeClick}
        onNodeDoubleClick={handleNodeDoubleClick}
        onPaneClick={handlePaneClick}
        defaultEdgeOptions={{
          markerEnd: { type: MarkerType.ArrowClosed, width: 14, height: 14 },
        }}
        proOptions={{ hideAttribution: true }}
      >
        <Background variant={BackgroundVariant.Dots} gap={20} size={1} color="var(--border)" />
        <Controls
          position="bottom-left"
          showInteractive={false}
          showFitView={true}
          showZoom={true}
        >
          {/* TODO[CL]: botão custom pra alternar fullscreen do canvas */}
          <ControlButton title="Fullscreen (TODO)">
            <Maximize2 className="w-3.5 h-3.5" />
          </ControlButton>
        </Controls>
        <MiniMap
          position="bottom-right"
          nodeColor={miniMapNodeColor}
          nodeStrokeWidth={2}
          maskColor="oklch(0.92 0.005 240 / 0.6)"
          style={{
            border: '1px solid var(--border)',
            borderRadius: 4,
            background: 'var(--surface)',
          }}
          pannable
          zoomable
        />
      </ReactFlow>
    </div>
  );
}
