---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Repair/JobSheet/Show
file: resources/js/Pages/Repair/JobSheet/Show.tsx
charter_present: true
charter_file: Show.charter.md
runbook_present: true
runbook_file: memory/requisitos/Repair/RUNBOOK-jobsheet-show.md
append_only: true
---

# Review estática — `Repair/JobSheet/Show.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader+EmptyState shared ✓ · charter+RUNBOOK ✓
- `Deferred` importado ✓ — uso ativo positivo
- `FsmActionsResponse` com `current_stage` + `actions[]` + `in_pipeline` — coerente ADR 0143
- `useCallback`/`useEffect` ✓ · `toast` (sonner) ✓
- Importa muitos lucide icons (10+) — bundle size?

## Riscos Tier 0

1. **FSM/M2 — FSM endpoints REPAIR próprios (wrapper)**: validar wrapper passa `business_id` + RBAC + `FsmAuthorizationFlag::mark()` ANTES do save (proibições § "UPDATE direto em current_stage_id").
2. **TOAST/L3 — `toast()` chama sem context module-scope**: se mais de 1 toast simultâneo pode confundir.
3. **CHARTER/L4 — Drift check round 2** (FSM panel evolui rápido).
4. **PII/L4 — Payload mostra `defects`/`security_pwd_revealed?`**: validar masking na visualização.
5. **A11Y/L4 — Botões FSM action sem `aria-busy` durante `Loader2`**.

## Top 5 recomendações

1. P0 — Pest GUARD FSM: wrapper REPAIR aciona `ExecuteStageActionService` (não UPDATE direto).
2. P0 — Validar masking `security_pwd` no Show + audit log se revelado.
3. P1 — `aria-busy` + `disabled` nos botões durante processing.
4. P2 — Lazy-load icons raros (tree-shake já faz, mas confirmar bundle).
5. P3 — Charter drift round 2.
