---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Repair/Status/Index
file: resources/js/Pages/Repair/Status/Index.tsx
charter_present: true
charter_file: Index.charter.md
runbook_present: false
runbook_notes: sem RUNBOOK-status-index.md (RUNBOOK.md genérico apenas)
append_only: true
---

# Review estática — `Repair/Status/Index.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader+EmptyState ✓
- CRUD simples: lista statuses (color, sort_order, is_completed_status)
- Sem useForm/Deferred/useState — pure render
- `statuses: StatusRow[]` EAGER (dataset pequeno OK)
- CRUD via Blade modal "edit/create existentes — 1:1 paridade visual" — DEBT MWART

## Riscos Tier 0

1. **MWART/M2 — SEM RUNBOOK específico**: hook deveria bloquear.
2. **CORES/M3 — `color: string` é hex DB**: render usa cor direto do DB sem mapeamento semantic. Se DB tem `#FF0000`, vai bater literal — anti-pattern catalogado em Admin charter.
3. **MWART/M3 — CRUD híbrido Blade**: viola ADR 0104 caminho único. Documentar override.
4. **A11Y/L4 — Color chip sem aria-label**: usuário daltônico não distingue.
5. **MULTI-TENANT/L4 — `business_id` scope implícito**: validar.

## Top 5 recomendações

1. P0 — Criar `RUNBOOK-status-index.md` ou `/mwart-override` per-tela ADR `historical`.
2. P1 — Migrar create/edit Blade → Inertia (US-REPAIR-STATUS-MIGRATE).
3. P1 — Color chip com `aria-label="${name} (cor ${color})"`.
4. P2 — Sanitizar `color` (regex `^#[0-9A-Fa-f]{6}$` no controller).
5. P3 — Charter drift round 2.
