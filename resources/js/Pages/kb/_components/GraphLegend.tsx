/**
 * GraphLegend — legenda fixa pro grafo
 * =====================================
 *
 * Componente passivo que mostra:
 * - Cores por tipo de nó (article, ADR, session, charter, runbook, briefing, spec, dado ERP)
 * - Padrões de aresta (supersedes dashed, ai-related dashed translúcido, etc)
 *
 * Layout compacto pra encaixar no topo do canvas sem invadir muito espaço (Larissa 1280px).
 *
 * Agent E (ONDA 5) — 2026-05-15
 */

import * as React from 'react';
import { NODE_COLORS, EDGE_STYLES } from '../_lib/graphLayout';
import type { KbNodeType, KbEdgeType } from '../_lib/graphTypes';

interface Props {
  visibleNodeTypes?: Set<KbNodeType>;
  visibleEdgeTypes?: Set<KbEdgeType>;
}

// Tipos de nó canon mais relevantes pra Wagner-governança (ONDA 1-5).
// Larissa-operacional ONDA 6+ vai adicionar article/external_file no topo.
const NODE_ORDER: KbNodeType[] = [
  'adr', 'session', 'charter', 'runbook', 'briefing', 'spec',
];

const EDGE_ORDER: KbEdgeType[] = [
  'supersedes', 'charter-of', 'cross-link', 'related-by-tag', 'ai-related',
];

export default function GraphLegend({ visibleNodeTypes, visibleEdgeTypes }: Props) {
  return (
    <div
      data-slot="graph-legend"
      className="flex flex-wrap items-center gap-x-4 gap-y-2 px-3 py-2 border border-border rounded-sm bg-surface text-xs"
      role="region"
      aria-label="Legenda do grafo"
    >
      {/* Tipos de nó */}
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-muted-foreground font-medium">Nós:</span>
        {NODE_ORDER.map(type => {
          const colors = NODE_COLORS[type];
          const dimmed = visibleNodeTypes && !visibleNodeTypes.has(type);
          return (
            <span
              key={type}
              className="inline-flex items-center gap-1.5"
              style={{ opacity: dimmed ? 0.35 : 1 }}
            >
              <span
                aria-hidden
                className="inline-block w-3 h-3 rounded-sm"
                style={{ background: colors.bg, border: `1.5px solid ${colors.stroke}` }}
              />
              <span className="text-foreground">{colors.label}</span>
            </span>
          );
        })}
      </div>

      <div className="h-4 w-px bg-border" aria-hidden />

      {/* Tipos de aresta */}
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-muted-foreground font-medium">Arestas:</span>
        {EDGE_ORDER.map(type => {
          const style = EDGE_STYLES[type];
          const dimmed = visibleEdgeTypes && !visibleEdgeTypes.has(type);
          return (
            <span
              key={type}
              className="inline-flex items-center gap-1.5"
              style={{ opacity: dimmed ? 0.35 : 1 }}
            >
              <svg
                width={20}
                height={6}
                aria-hidden
                style={{ display: 'inline-block' }}
              >
                <line
                  x1={0}
                  y1={3}
                  x2={20}
                  y2={3}
                  stroke={style.stroke}
                  strokeWidth={1.5}
                  strokeDasharray={style.strokeDasharray}
                  opacity={style.opacity}
                />
              </svg>
              <span className="text-foreground">{style.label}</span>
            </span>
          );
        })}
      </div>
    </div>
  );
}
