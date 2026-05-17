---
review_round: 1
review_type: static-analysis
reviewer: W31 bulk-screen-review-r1 agent
review_at: 2026-05-17
page: Repair/DeviceModels/Index
file: resources/js/Pages/Repair/DeviceModels/Index.tsx
charter_present: false
charter_file: null
runbook_present: false
runbook_notes: sem RUNBOOK-devicemodels-index.md em memory/requisitos/Repair/
append_only: true
---

# Review estática — `Repair/DeviceModels/Index.tsx` (Round 1)

> Append-only. Próximos rounds adicionam blocos abaixo (NUNCA editar/remover este).

## Sinais técnicos

- AppShellV2 ✓ · PageHeader+EmptyState shared ✓
- Render simples: lista models (Device+Brand+Checklist) — sem paginação
- Sem `useForm`, sem `Deferred`, sem `localStorage`
- CRUD via routes `/repair/device-models/create|edit` (Blade modal segundo comment)
- `models: ModelRow[]` chega EAGER (mas é small dataset esperado)

## Riscos Tier 0

1. **MWART/M2 — SEM charter + SEM RUNBOOK**: Edit em `.tsx` sem `RUNBOOK-devicemodels-index.md` deveria ter sido BLOQUEADO pelo hook `block-mwart-violation.ps1` (ADR 0104 + `.claude/rules/pages.md`). Verificar se foi override.
2. **SCAL/L3 — `models` eager sem paginação**: se biz tiver >500 modelos catálogo, lista vai pesar. Falta limite/pagination ou virtualização.
3. **DEBT/M3 — Híbrido Blade**: criar/editar via rotas Blade — viola caminho único MWART F3 (ADR 0104). Documentar em ADR per-tela `historical` se intencional.
4. **A11Y/L4 — `<table>` sem `<caption>`/`scope`**: thead com `<th>` mas sem `scope="col"`.
5. **MULTI-TENANT/L3 — Sem indicação de `business_id` scope**: assumir controller faz, mas validar com Grep.

## Top 5 recomendações

1. P0 — Criar `Repair/DeviceModels/Index.charter.md` OR justificar ausência (CRUD trivial não precisa? Decidir norma).
2. P0 — Criar `memory/requisitos/Repair/RUNBOOK-devicemodels-index.md` ou `/mwart-override`.
3. P1 — Migrar create/edit Blade → Inertia (completar MWART) — abrir US-REPAIR-DM-001.
4. P2 — Paginação ou `Deferred` se >100 modelos.
5. P3 — A11Y `scope="col"` + `<caption>` no `<table>`.
