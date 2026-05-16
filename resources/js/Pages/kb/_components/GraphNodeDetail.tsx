/**
 * GraphNodeDetail — side panel com detalhe do node selecionado
 * ============================================================
 *
 * Aberto ao clicar num node no canvas. Mostra:
 * - Header: tipo + título + status + slug
 * - Meta: módulo, tags, last_verified_at, edges_count
 * - Lista de edges (in/out) agrupadas por tipo — cada uma clicável (navega no canvas)
 * - Botões: "Focar aqui" (re-layout dagre com este node como root), "Abrir leitor" (vai pra /kb?slug=X)
 *
 * Persona-target Wagner-governança ONDA 5: detalhe quick-glance, sem editor inline.
 * Edição vive em /kb/composer (ONDA 3).
 *
 * Agent E (ONDA 5) — 2026-05-15
 */

import * as React from 'react';
import { router } from '@inertiajs/react';
import { ExternalLink, Compass, X, FileText, GitBranch } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { NODE_COLORS, EDGE_STYLES } from '../_lib/graphLayout';
import type { KbGraphNode, KbGraphEdge, KbEdgeType } from '../_lib/graphTypes';

interface Props {
  node: KbGraphNode;
  allNodes: KbGraphNode[];
  edgesIn: KbGraphEdge[];   // edges onde target = node.id
  edgesOut: KbGraphEdge[];  // edges onde source = node.id
  onFocus: (nodeId: string) => void;   // double-click equivalent
  onNavigate: (nodeId: string) => void; // hop pra outro node sem mudar focus
  onClose: () => void;
}

export default function GraphNodeDetail({
  node, allNodes, edgesIn, edgesOut, onFocus, onNavigate, onClose,
}: Props) {
  const colors = NODE_COLORS[node.type] ?? NODE_COLORS.reference;
  const nodeById = React.useMemo(() => {
    const m = new Map<string, KbGraphNode>();
    for (const n of allNodes) m.set(n.id, n);
    return m;
  }, [allNodes]);

  // Group edges by type
  const grouped = React.useMemo(() => {
    const out: Record<string, { dir: 'in' | 'out'; edge: KbGraphEdge; other: KbGraphNode | undefined }[]> = {};
    for (const e of edgesIn) {
      (out[e.edge_type] ??= []).push({ dir: 'in', edge: e, other: nodeById.get(e.source) });
    }
    for (const e of edgesOut) {
      (out[e.edge_type] ??= []).push({ dir: 'out', edge: e, other: nodeById.get(e.target) });
    }
    return out;
  }, [edgesIn, edgesOut, nodeById]);

  const openInReader = () => {
    // Navega pro leitor markdown na Index. Slug do node tem precedência sobre id.
    router.visit(`/kb?slug=${encodeURIComponent(node.data.slug)}`, { preserveState: false });
  };

  return (
    <aside
      data-slot="graph-node-detail"
      className="w-[320px] shrink-0 border-l border-border bg-surface flex flex-col"
      role="region"
      aria-label={`Detalhe do nó ${node.data.label}`}
    >
      {/* Header */}
      <div
        className="px-3 py-2 border-b border-border flex items-start justify-between gap-2"
        style={{ background: colors.bg, color: colors.stroke }}
      >
        <div className="min-w-0">
          <div className="text-[10px] uppercase tracking-wide font-medium opacity-75">
            {colors.label}
            {node.data.status && node.data.status !== 'ok' && (
              <span className="ml-1.5 px-1 py-px rounded-sm bg-background/30">
                {node.data.status}
              </span>
            )}
          </div>
          <div className="text-sm font-semibold leading-tight">{node.data.label}</div>
          <div className="text-[11px] opacity-75 truncate font-mono mt-0.5">{node.data.slug}</div>
        </div>
        <Button
          variant="ghost"
          size="sm"
          className="h-7 w-7 p-0 shrink-0"
          onClick={onClose}
          aria-label="Fechar detalhe"
          style={{ color: colors.stroke }}
        >
          <X className="w-4 h-4" />
        </Button>
      </div>

      {/* Actions */}
      <div className="px-3 py-2 border-b border-border flex gap-1.5">
        <Button
          size="sm"
          variant="default"
          className="flex-1 h-7 text-xs"
          onClick={() => onFocus(node.id)}
          title="Re-layout em modo hierárquico com este nó como raiz"
        >
          <Compass className="w-3.5 h-3.5 mr-1" />
          Focar aqui
        </Button>
        <Button
          size="sm"
          variant="outline"
          className="flex-1 h-7 text-xs"
          onClick={openInReader}
          title="Abrir leitor markdown na /kb"
        >
          <FileText className="w-3.5 h-3.5 mr-1" />
          Abrir leitor
        </Button>
      </div>

      {/* Scroll body */}
      <div className="overflow-y-auto flex-1">
        {/* Meta */}
        <section className="px-3 py-2 border-b border-border space-y-1.5 text-xs">
          {node.data.module && (
            <div className="flex items-center justify-between">
              <span className="text-muted-foreground">Módulo</span>
              <Badge variant="secondary" className="text-[10px]">{node.data.module}</Badge>
            </div>
          )}
          {node.data.tags && node.data.tags.length > 0 && (
            <div className="space-y-0.5">
              <span className="text-muted-foreground">Tags</span>
              <div className="flex flex-wrap gap-1">
                {node.data.tags.map(t => (
                  <span key={t} className="px-1.5 py-px rounded-sm bg-accent/10 text-[10px]">
                    {t}
                  </span>
                ))}
              </div>
            </div>
          )}
          {node.data.last_verified_at && (
            <div className="flex items-center justify-between">
              <span className="text-muted-foreground">Última verificação</span>
              <span className="font-mono text-[10px]">
                {new Date(node.data.last_verified_at).toLocaleDateString('pt-BR')}
              </span>
            </div>
          )}
          {node.data.updated_at && (
            <div className="flex items-center justify-between">
              <span className="text-muted-foreground">Atualizado</span>
              <span className="font-mono text-[10px]">
                {new Date(node.data.updated_at).toLocaleDateString('pt-BR')}
              </span>
            </div>
          )}
          {typeof node.data.edges_count === 'number' && (
            <div className="flex items-center justify-between">
              <span className="text-muted-foreground">Conexões</span>
              <span className="text-foreground font-medium">{node.data.edges_count}</span>
            </div>
          )}
        </section>

        {/* Edges agrupadas por tipo */}
        <section className="px-3 py-2 space-y-3">
          <h3 className="text-[10px] uppercase tracking-wide text-muted-foreground font-medium flex items-center gap-1">
            <GitBranch className="w-3 h-3" />
            Conexões ({edgesIn.length + edgesOut.length})
          </h3>

          {Object.keys(grouped).length === 0 && (
            <p className="text-xs text-muted-foreground italic">
              Sem conexões — nó isolado.
            </p>
          )}

          {Object.entries(grouped).map(([edgeType, items]) => {
            const style = EDGE_STYLES[edgeType as KbEdgeType] ?? EDGE_STYLES['cross-link'];
            return (
              <div key={edgeType} className="space-y-1">
                <div className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                  <svg width={16} height={6} aria-hidden>
                    <line
                      x1={0} y1={3} x2={16} y2={3}
                      stroke={style.stroke}
                      strokeWidth={1.5}
                      strokeDasharray={style.strokeDasharray}
                      opacity={style.opacity}
                    />
                  </svg>
                  <span className="font-medium">{style.label}</span>
                  <span>({items.length})</span>
                </div>
                <ul className="space-y-0.5">
                  {items.map(({ dir, edge, other }) => {
                    if (!other) return null;
                    const otherColors = NODE_COLORS[other.type] ?? NODE_COLORS.reference;
                    return (
                      <li key={edge.id}>
                        <button
                          type="button"
                          onClick={() => onNavigate(other.id)}
                          className="w-full text-left text-xs px-1.5 py-1 rounded-sm hover:bg-accent/10 flex items-center gap-1.5 group"
                          title={dir === 'in' ? `${other.data.label} → ${node.data.label}` : `${node.data.label} → ${other.data.label}`}
                        >
                          <span
                            aria-hidden
                            className="inline-block w-1.5 h-1.5 rounded-full shrink-0"
                            style={{ background: otherColors.stroke }}
                          />
                          <span className="text-[10px] text-muted-foreground shrink-0">
                            {dir === 'in' ? '←' : '→'}
                          </span>
                          <span className="truncate flex-1">{other.data.label}</span>
                          <ExternalLink className="w-3 h-3 opacity-0 group-hover:opacity-50 shrink-0" />
                        </button>
                      </li>
                    );
                  })}
                </ul>
              </div>
            );
          })}
        </section>
      </div>
    </aside>
  );
}
