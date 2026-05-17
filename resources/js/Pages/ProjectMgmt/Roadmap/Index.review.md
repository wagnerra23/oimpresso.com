# Review Round 1 — ProjectMgmt/Roadmap/Index.tsx

**Tela:** `/project-mgmt/roadmap` · **Stories:** US-TR-203 · **Charter:** ❌ ausente
**Reviewer:** W31 bulk · **Data:** 2026-05-17 · **Modo:** análise estática

## Resumo

Roadmap em colunas por quarter (horizontal scroll) com `EpicCard` — border-l colorida custom, status badge, progress bar (done/total), owner, ativas. 4 KPIs. Read-only (edit via MCP `epics-update`).

## Pontos fortes

- **Visual orientado** — quarter columns + epic cards com cor custom border-l 4px (`e.color ?? '#3b82f6'`)
- Progress bar inline `bg-emerald-500` com width %
- Status tipado (`'planning' | 'active' | 'done' | 'cancelled'`) + STATUS_BADGE local
- Empty state na ausência de epics + dica `epics-create` via MCP
- KPIs com tones consistentes (success/info/default)

## Riscos / gaps (top 5)

1. **R1 — Charter ausente** ([ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)). Bloqueador F3.
2. **R2 — Read-only sem inline edit** — usuário precisa abrir MCP pra mudar `target_quarter`. Em modo IA-pair OK, mas Larissa/cliente não tem MCP. Roadmap fica decorativo.
3. **R3 — `STATUS_BADGE` duplicado novamente** (idem DetailSheet R1). Padrão repetido 3+ telas. Refator urgente: shared `@/Components/board/badges` precisa exportar `STATUS_BADGE_EPIC` distinto.
4. **R4 — Cor `bg-emerald-500` da progress bar hardcoded** — não usa token semântico (e.g. `bg-success` se existisse). Quando dark mode tem variante, fica off.
5. **R5 — Sem drag-drop** entre quarters (esperado em roadmaps modernos — Notion, Linear, Productboard). Story PMG futura.

## Veredito round 1

Tela enxuta (147 LOC) e visualmente clara, mas **read-only é gap funcional importante**. **Pendências:** charter (R1), inline edit (R2), STATUS_BADGE shared (R3).

**Status:** APROVA com pendências P2 (R2 é feature gap importante, não bug).
