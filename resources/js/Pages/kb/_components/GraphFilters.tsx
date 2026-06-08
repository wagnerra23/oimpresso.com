/**
 * GraphFilters — sidebar de filtros do grafo
 * ===========================================
 *
 * Filtros suportados:
 * - Busca por texto (title/excerpt/tags)
 * - Tipos de nó visíveis (toggles)
 * - Tipos de aresta visíveis (toggles)
 * - Depth do focus (slider 1-3) — só faz sentido se focusNodeId != null
 * - Layout mode (radio: concentric / force-radial / dagre-tb)
 * - Botão "Limpar focus" se focusNodeId != null
 *
 * Layout: coluna esquerda, ~240px largura, scroll vertical.
 *
 * Agent E (ONDA 5) — 2026-05-15
 */

import * as React from 'react';
import { Search, Eye, EyeOff, Compass, X } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';
import { NODE_COLORS, EDGE_STYLES } from '../_lib/graphLayout';
import {
  ALL_NODE_TYPES, ALL_EDGE_TYPES,
  NODE_TYPES_GOVERNANCE, NODE_TYPES_ERP,
  type GraphFilterState, type KbNodeType, type KbEdgeType, type GraphLayoutMode,
} from '../_lib/graphTypes';

interface Props {
  filters: GraphFilterState;
  onChange: (next: Partial<GraphFilterState>) => void;
  focusNodeLabel?: string | null;
  totalNodes: number;
  visibleNodes: number;
}

const LAYOUT_OPTIONS: { value: GraphLayoutMode; label: string; hint: string }[] = [
  { value: 'force-radial', label: 'Força radial', hint: 'Bom pra visão geral; centra no focus' },
  { value: 'dagre-tb',     label: 'Hierárquico ↓', hint: 'Bom pra árvore supersedes (focus em ADR)' },
  { value: 'concentric',   label: 'Concêntrico',   hint: 'Anéis por tipo; layout determinístico' },
];

export default function GraphFilters({
  filters, onChange, focusNodeLabel, totalNodes, visibleNodes,
}: Props) {
  const toggleNodeType = (type: KbNodeType) => {
    const next = new Set(filters.visibleNodeTypes);
    if (next.has(type)) next.delete(type);
    else next.add(type);
    onChange({ visibleNodeTypes: next });
  };

  const toggleEdgeType = (type: KbEdgeType) => {
    const next = new Set(filters.visibleEdgeTypes);
    if (next.has(type)) next.delete(type);
    else next.add(type);
    onChange({ visibleEdgeTypes: next });
  };

  const showAllNodes = () => onChange({ visibleNodeTypes: new Set(ALL_NODE_TYPES) });
  const showOnlyGovernance = () => onChange({ visibleNodeTypes: new Set(NODE_TYPES_GOVERNANCE) });
  const showOnlyErp = () => onChange({ visibleNodeTypes: new Set(NODE_TYPES_ERP) });

  return (
    <aside
      data-slot="graph-filters"
      className="w-[240px] shrink-0 border-r border-border bg-surface flex flex-col"
      role="region"
      aria-label="Filtros do grafo"
    >
      <div className="overflow-y-auto p-3 space-y-4 flex-1">
        {/* Contador */}
        <div className="text-xs text-muted-foreground">
          Mostrando <strong className="text-foreground">{visibleNodes}</strong> de {totalNodes} nós
        </div>

        {/* Busca */}
        <div className="space-y-1.5">
          <Label htmlFor="graph-search" className="text-xs font-medium">Buscar</Label>
          <div className="relative">
            <Search className="absolute left-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground" />
            <Input
              id="graph-search"
              type="search"
              placeholder="Título, tag, slug…"
              value={filters.query}
              onChange={(e) => onChange({ query: e.target.value })}
              className="h-8 pl-7 text-xs"
            />
          </div>
        </div>

        {/* Focus mode */}
        {filters.focusNodeId && (
          <div className="rounded-sm border border-border bg-background p-2 space-y-2">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <div className="text-[10px] uppercase tracking-wide text-muted-foreground flex items-center gap-1">
                  <Compass className="w-3 h-3" /> Foco
                </div>
                <div className="text-xs font-medium truncate" title={focusNodeLabel ?? filters.focusNodeId}>
                  {focusNodeLabel ?? filters.focusNodeId}
                </div>
              </div>
              <Button
                variant="ghost"
                size="sm"
                className="h-6 w-6 p-0 shrink-0"
                onClick={() => onChange({ focusNodeId: null })}
                aria-label="Remover foco"
              >
                <X className="w-3.5 h-3.5" />
              </Button>
            </div>
            <div>
              <Label htmlFor="depth-slider" className="text-[11px] flex items-center justify-between">
                <span>Profundidade</span>
                <span className="text-muted-foreground">{filters.depth} hop{filters.depth > 1 ? 's' : ''}</span>
              </Label>
              <input
                id="depth-slider"
                type="range"
                min={1}
                max={3}
                step={1}
                value={filters.depth}
                onChange={(e) => onChange({ depth: Number(e.target.value) })}
                className="w-full accent-primary"
              />
            </div>
          </div>
        )}

        {/* Layout mode */}
        <fieldset className="space-y-1.5">
          <legend className="text-xs font-medium">Layout</legend>
          <div className="space-y-1">
            {LAYOUT_OPTIONS.map(opt => (
              <label
                key={opt.value}
                className="flex items-center gap-2 text-xs cursor-pointer hover:text-foreground"
                title={opt.hint}
              >
                <input
                  type="radio"
                  name="layoutMode"
                  value={opt.value}
                  checked={filters.layoutMode === opt.value}
                  onChange={() => onChange({ layoutMode: opt.value })}
                  className="accent-primary"
                />
                <span>{opt.label}</span>
              </label>
            ))}
          </div>
        </fieldset>

        {/* Tipos de nó */}
        <fieldset className="space-y-1.5">
          <legend className="text-xs font-medium flex items-center justify-between w-full">
            <span>Tipos de nó</span>
            <button
              type="button"
              onClick={showAllNodes}
              className="text-[10px] text-muted-foreground hover:text-foreground underline"
              title="Mostrar todos os tipos"
            >
              todos
            </button>
          </legend>
          <div className="flex gap-1 mb-1">
            <button
              type="button"
              onClick={showOnlyGovernance}
              className="flex-1 text-[10px] px-1.5 py-0.5 rounded-sm border border-border hover:bg-accent/10"
              title="Governança apenas (ADR, session, charter, runbook, briefing, spec)"
            >
              Governança
            </button>
            <button
              type="button"
              onClick={showOnlyErp}
              className="flex-1 text-[10px] px-1.5 py-0.5 rounded-sm border border-border hover:bg-accent/10"
              title="ERP apenas (OS, cliente, produto, NFe, equipamento)"
            >
              ERP
            </button>
          </div>
          <div className="space-y-0.5">
            {ALL_NODE_TYPES.map(type => {
              const colors = NODE_COLORS[type];
              const visible = filters.visibleNodeTypes.has(type);
              return (
                <label
                  key={type}
                  className="flex items-center gap-2 text-xs cursor-pointer hover:text-foreground py-0.5"
                >
                  <input
                    type="checkbox"
                    checked={visible}
                    onChange={() => toggleNodeType(type)}
                    className="accent-primary"
                    aria-label={`Toggle ${colors.label}`}
                  />
                  <span
                    aria-hidden
                    className="inline-block w-2.5 h-2.5 rounded-sm shrink-0"
                    style={{ background: colors.bg, border: `1.5px solid ${colors.stroke}` }}
                  />
                  <span className={visible ? '' : 'text-muted-foreground line-through'}>
                    {colors.label}
                  </span>
                </label>
              );
            })}
          </div>
        </fieldset>

        {/* Tipos de aresta */}
        <fieldset className="space-y-1.5">
          <legend className="text-xs font-medium flex items-center justify-between w-full">
            <span>Tipos de aresta</span>
            <button
              type="button"
              onClick={() => onChange({ visibleEdgeTypes: new Set(ALL_EDGE_TYPES) })}
              className="text-[10px] text-muted-foreground hover:text-foreground underline"
            >
              todos
            </button>
          </legend>
          <div className="space-y-0.5">
            {ALL_EDGE_TYPES.map(type => {
              const style = EDGE_STYLES[type];
              const visible = filters.visibleEdgeTypes.has(type);
              return (
                <label
                  key={type}
                  className="flex items-center gap-2 text-xs cursor-pointer hover:text-foreground py-0.5"
                >
                  <input
                    type="checkbox"
                    checked={visible}
                    onChange={() => toggleEdgeType(type)}
                    className="accent-primary"
                    aria-label={`Toggle ${style.label}`}
                  />
                  <svg width={16} height={6} aria-hidden>
                    <line
                      x1={0}
                      y1={3}
                      x2={16}
                      y2={3}
                      stroke={style.stroke}
                      strokeWidth={1.5}
                      strokeDasharray={style.strokeDasharray}
                      opacity={style.opacity}
                    />
                  </svg>
                  <span className={visible ? '' : 'text-muted-foreground line-through'}>
                    {style.label}
                  </span>
                </label>
              );
            })}
          </div>
        </fieldset>
      </div>
    </aside>
  );
}
