# Review estática — `resources/js/Pages/kb/Graph.tsx`

**Round:** W31-R1 · **Data:** 2026-05-17 · **Modo:** análise estática (sem execução navegador)
**Charter:** `Graph.charter.md` (presente)
**RUNBOOK MWART:** não confirmado (verificar `memory/requisitos/KB/RUNBOOK-graph.md`)

## Resumo

Tela coração do KB Unificado ONDA 5 (ADR 0149) — visualização grafo Reactflow 11.11.4 (`reactflow@^11.11.4` já instalada). Tri-pane: filters | canvas | detail. Layout client-side (`graphLayout.ts`: concentric/force-radial/dagre-tb). Fallback mock data quando Controller backend (Agent A) ainda não publicado. KPIs no header (`total_nodes`, `total_edges`, `outdated_count`, `last_bridge_at`).

## Pontos fortes

- Decisão de lib documentada inline (lines 8-33) com 5 alternativas rejeitadas e critério decisivo ("já instalada + precedent existente")
- Fallback mock + badge "modo mock" no header (line 232) — UX correta enquanto backend pendente
- Keyboard shortcuts (`/` foca busca, `Esc` limpa focus) com guard `isTyping` correto
- Memoização agressiva (`useMemo` em `filtered`, `rfNodes`, `rfEdges`, `selectedNode`, `edgesForSelected`, `focusNodeLabel`) — performance defendida
- Empty state com botão "Limpar todos os filtros" — boa UX
- AppShellV2 + breadcrumb canônico

## Riscos / gaps

1. **R-A (P1) — Dagre layout falta dep.** TODO[CL] linha 35: `@dagrejs/dagre@^1.1.4` ainda não instalada; modo `dagre-tb` cai em `concentric` silenciosamente. Double-click muda layout pra `dagre-tb` (line 180) sem aviso UI ao usuário.
2. **R-B (P1) — Backend KbGraphController não existe.** F2 BACKEND BASELINE (ADR 0104) não cumprida; tela em F3 com mock — viola MWART canônico até backend pousar. Confirmar PR Agent A.
3. **R-C (P2) — `Inertia::defer` não confirmável.** Como Controller não existe, regra Tier 0 defer pra `nodes`/`edges`/`kpis` (≤700 nodes / ~3000 edges payload) ainda não verificada. Risco first-render >800ms sem defer.
4. **R-D (P2) — Cross-tenant Pest pendente.** Comentário linha 50 admite tests biz=1 + biz=99 pendentes Agent C — viola ADR 0101/0093 até cobertura existir.
5. **R-E (P3) — `EmptyState.onReset` não restaura `depth`/`layoutMode`** (linha 288) — pode deixar usuário travado em layout dagre vazio.

## Score parcial (estática)

| Eixo | Nota |
|---|---|
| Charter presente | OK |
| MWART F2 cumprido | NOK (backend pendente) |
| Inertia::defer | INCONCLUSIVO |
| Multi-tenant Tier 0 | INCONCLUSIVO (depende Controller) |
| A11y (keyboard) | OK parcial (foco `/` + `Esc`) |
| PT-BR | OK |

**Recomendação Round 2:** validar com backend KbGraphController em main + screenshot gate visual ADR 0114.
