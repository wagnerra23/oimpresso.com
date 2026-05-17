# Review Round 1 — ProjectMgmt/Board/DetailSheet.tsx

**Componente:** `DetailSheet` (sheet slide-in pro Kanban) · **Stories:** PMG-004/005/006/007 (ADR 0100)
**Reviewer:** W31 bulk · **Data:** 2026-05-17 · **Modo:** análise estática

## Resumo

Sheet com 5 tabs state-driven (Descrição/Comments/Activity/Subtasks/Watchers). `fetch` lazy on open com `AbortController` cleanup. PMG-005 (comment + @mentions via `MentionInput`), PMG-006 (watch toggle), PMG-007 (subtasks add/toggle). Footer com link pro board + dica `tasks-update {task_id}` via MCP.

## Pontos fortes

- `AbortController` no fetch (race-condition safe ao trocar task)
- Tabs sem dep lib (state-driven, custom tabs com counter badges) — leve
- Optimistic update em add comment / add subtask / toggle subtask
- 3 erros tipados (403/404/422) com mensagens PT-BR
- `MentionInput` componente reutilizado (PMG-005 SoC)
- Form submit via Enter (sem Shift+Enter — bom hint UX)

## Riscos / gaps (top 5)

1. **R1 — `STATUS_BADGE` duplicado local** (linhas 139-147) — já existe em `@/Components/board/badges` (importado mas Status só, não as classes). Refator: exportar `STATUS_BADGE` do badges shared module pra evitar drift visual.
2. **R2 — `handleToggleWatch` faz 2 fetch sequenciais** (POST + GET refetch detail). Race: usuário toggle 2x rápido → ordem indeterminada. Adicionar guard `togglingWatch` similar ao `togglingSubtaskId`.
3. **R3 — Catch silencioso em `handleToggleSubtaskStatus`** (linha 314 `catch {}`). Falha de rede deixa UI mentindo (optimistic já aplicado). Adicionar setSubtaskError + revert.
4. **R4 — `MentionInput` SEM ARIA live region** pra screen reader anunciar sugestões @mention. Accessibility gap (skill `accessibility-review` deveria pegar).
5. **R5 — Subtask `display_id` sem link clicável** pra abrir sub-sheet recursivo (ou pelo menos navegar). Power-users esperam.

## Veredito round 1

Componente sofisticado (4 stories em 1 file), bem segmentado por tab. **Pendências:** STATUS_BADGE shared (R1), watch race (R2), catch silencioso (R3).

**Status:** APROVA com pendências P2 (R2/R3 podem causar bugs em uso real).
