---
page: /sells/drafts
component: resources/js/Pages/Sells/Drafts.tsx
owner: wagner
status: wave1-draft
last_validated: 2026-05-15
parent_module: Sells
related_adrs: [0104, 0110, 0149, 0093]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/vendas-cockpit/"
  blueprint_screenshot_approval: "SYNC_LOG pendente verificar Wagner (ADR 0149)"
  derived_screens: [Drafts]
  divergence_from_blueprint: "Lista simplificada (sem KPI múltiplos, sem filter pills) — só rascunhos pendentes. Reusa endpoint AJAX DataTables legacy."
---

# Page Charter — /sells/drafts

> **Status:** wave1-draft. Migração MWART Wave 1 W1-A (2026-05-15).
> Reusa pattern lista Cockpit V2 ADR 0110 via ADR 0149.

---

## Mission

Listar rascunhos de venda (status=draft, sub_status=NULL) — Larissa retoma pra finalizar depois. Substitui `sale_pos.draft.blade.php` legacy AdminLTE.

---

## Goals — Features (faz)

- AppShellV2 + PageHeader inline
- 1 KPI: total rascunhos pendentes
- Search inline por nº / cliente
- Tabela 6 cols (data + nº + cliente + local + itens + ações)
- Endpoint AJAX DataTables legacy preservado (`/sells/drafts` GET ajax) — fetch JSON
- Action principal: "Continuar" → `/sells/{id}/edit`
- Customers dropdown deferred (pré-carga pra autocomplete futuro)
- Atalho `N` (nova venda), `Esc` (voltar)
- Multi-tenant Tier 0 backend (ADR 0093)
- Permission gate: `draft.view_all` OR `draft.view_own`

---

## Non-Goals — Features (NÃO faz)

- ❌ Filtros avançados (date range, customer, location) — backlog
- ❌ Paginação SSR (front-side fetch via DataTables JSON; pagina cliente se >100)
- ❌ Drawer SaleSheet (futuro — drawer detalhe + ações)
- ❌ Bulk delete rascunhos antigos (backlog)
- ❌ Export PDF/CSV

---

## UX Targets

- p95 first-paint < 1000ms
- 0 erros JS console
- 1280px sem scroll horizontal
- Filtro busca instantâneo (cliente-side filter)

---

## UX Anti-patterns

- ❌ DataTables jQuery render (canon = React table simples)
- ❌ Cor crua
- ❌ AppShell sem V2

---

## Endpoints alimentadores

| Método | Rota | Retorna |
|---|---|---|
| GET | `/sells/drafts` (X-Inertia) | Inertia render Sells/Drafts |
| GET | `/sells/drafts` (sem X-Inertia) | Blade `sale_pos.draft` |
| GET | `/sells/drafts` (Accept JSON / AJAX) | DataTables JSON `{data: [...]}` (alimenta tabela cliente) |

---

## Tests anti-regressão

- [tests/Feature/Sells/Wave1DraftsBaselineTest.php](../../../../tests/Feature/Sells/Wave1DraftsBaselineTest.php) — 6 estruturais
- [tests/Feature/Sells/Wave1DraftsInertiaTest.php](../../../../tests/Feature/Sells/Wave1DraftsInertiaTest.php) — Inertia + cross-tenant

---

## Cutover plan

- Smoke biz=1: criar 1 rascunho → /sells/drafts → Continuar → finalizar
- Canary 7d
- Remover Blade após 30d

---

## Refs

- [ADR 0149](../../../../memory/decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- [RUNBOOK-drafts.md](../../../../memory/requisitos/Sells/RUNBOOK-drafts.md)
- Parent visual: `resources/js/Pages/Sells/Index.charter.md`
