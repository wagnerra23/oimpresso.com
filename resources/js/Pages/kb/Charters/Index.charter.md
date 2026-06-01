---
page: /kb/charters
component: resources/js/Pages/kb/Charters/Index.tsx
owner: wagner
status: live
last_validated: 2026-06-01
parent_module: KB
related_adrs: [0243, 0101, 0149, 0061, 0093]
tier: B
---

# Page Charter — /kb/charters

## Mission

Porta de entrada do Charter Governance (ADR 0243): listar os charters (contratos de tela/módulo) governados no KB, **read-only**, reusando o tri-pane do `/kb` que Wagner já aprovou.

## Goals

- Lista master de charters (page + module) com filtro por módulo + busca client-side
- Preview do contrato (markdown do núcleo, vindo do git via `mcp_memory_documents`)
- KPIs: total de charters + módulos cobertos
- Atalhos `j/k/Enter/Esc//` (mesma convenção do `/kb`)
- Banner deixando explícito que o núcleo é imutável (vem do git)
- Link pro charter no GitHub

## Non-Goals

- ❌ Editar o núcleo na tela (vem do git — ADR 0061; só sugestão em F1)
- ❌ Aba Governança funcional (sugestão/aprovação) — F1 (US-CHTR-001..003), hoje botão `disabled` "em breve"
- ❌ Module Charter consolidado (meta/limite/backlog/changelog) — F2 (US-CHTR-010)
- ❌ `Inertia::defer` (rollback Wave L/W7 — eager; ≈30 charters é barato)

## UX Targets

- first-paint < 600ms (eager, ~30 charters)
- preview abrir < 300ms (fetch `/kb/{slug}/show`)
- tokens roxo v4 (sem cor crua — mira ≥ Leader no SCREEN-GRADE)

## Automation Anti-hooks

- ❌ Não acessa charter de outro `business_id` (scope `acessiveisPara` — ADR 0093)
- ❌ Não muta nada (read-only; sem PATCH/DELETE)
- ❌ Não chama LLM

## Refs

- [ADR 0243](../../../../memory/decisions/proposals/0243-charter-governance-kb.md) · [INTERFACE-CHARTER-KB.md](../../../../memory/requisitos/KB/INTERFACE-CHARTER-KB.md) · [SPEC](../../../../memory/requisitos/KB/SPEC-CHARTER-GOVERNANCE.md)
- Reusa padrão de `resources/js/Pages/kb/Index.tsx`
- Backend: `Modules/KB/Http/Controllers/KbCharterController.php`
