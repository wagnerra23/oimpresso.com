---
page: /sells/subscriptions
component: resources/js/Pages/Sells/Subscriptions.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
status_detail: wave1-draft
last_validated: "2026-05-15"
parent_module: Sells
related_adrs: [104, 110, 149, 93]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/vendas-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG pendente verificar Wagner (ADR 0149)"
  derived_screens: [Subscriptions]
  divergence_from_blueprint: "Cols extras (intervalo + próxima fatura + toggle start/stop por linha)."
---

# Page Charter — /sells/subscriptions

> **Status:** wave1-draft. Migração MWART Wave 1 W1-A (2026-05-15).
> Reusa pattern Cockpit V2 ADR 0110 via ADR 0149.

---

## Mission

Listar vendas recorrentes (status=final + is_recurring=1) — cobranças mensais/anuais. Toggle start/stop por linha. Mostra próxima fatura calculada. Substitui `sale_pos.subscriptions.blade.php` legacy.

---

## Goals — Features (faz)

- AppShellV2 + PageHeader
- 3 KPIs: total / ativas / pausadas
- Search inline por nº / cliente
- Tabela 7 cols (data início + nº cobrança + cliente + intervalo + próxima fatura + status + ações)
- Status badge inline (Ativa emerald / Pausada amber)
- Toggle start/stop por linha (POST `/sells/recurring-toggle/{id}`)
- Customers dropdown deferred
- Atalho `N` (nova venda), `Esc` (voltar)
- Multi-tenant Tier 0 backend (`is_recurring=1` + scope business_id)
- Permission gate: `sell.view` OR `direct_sell.access` (view); `sell.update` (toggle)

---

## Non-Goals — Features (NÃO faz)

- ❌ Editar config recorrência inline (vai pra /sells/{id}/edit)
- ❌ Ver histórico faturas geradas inline (drawer futuro)
- ❌ Modules/RecurringBilling integração avançada (esperar US-RB)
- ❌ Filtros avançados (vencendo em 7d, parados há 30d)
- ❌ Bulk start/stop

---

## UX Targets

- p95 first-paint < 1000ms
- Toggle ação → response < 800ms
- 0 erros JS console
- 1280px sem scroll horizontal

---

## UX Anti-patterns

- ❌ Toggle sem feedback visual (spinner/disabled)
- ❌ DataTables jQuery
- ❌ Cor crua

---

## Endpoints alimentadores

| Método | Rota | Retorna |
|---|---|---|
| GET | `/sells/subscriptions` (X-Inertia) | Inertia render Sells/Subscriptions |
| GET | `/sells/subscriptions` (sem X-Inertia) | Blade `sale_pos.subscriptions` |
| GET | `/sells/subscriptions?ajax=1` | DataTables JSON |
| GET | `/sells/recurring-toggle/{id}` | toggle recur_stopped_on (legacy GET) |

---

## Tests anti-regressão

- [tests/Feature/Sells/Wave1SubscriptionsBaselineTest.php](../../../../tests/Feature/Sells/Wave1SubscriptionsBaselineTest.php) — 7 estruturais
- [tests/Feature/Sells/Wave1SubscriptionsInertiaTest.php](../../../../tests/Feature/Sells/Wave1SubscriptionsInertiaTest.php) — Inertia + cross-tenant + toggle

---

## Cutover plan

- Smoke biz=1: venda recurring → /sells/subscriptions → toggle stop → toggle start
- Canary 7d
- Remover Blade após 30d

---

## Refs

- [ADR 0149](../../../../memory/decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- [RUNBOOK-subscriptions.md](../../../../memory/requisitos/Sells/RUNBOOK-subscriptions.md)
- Parent visual: `resources/js/Pages/Sells/Index.charter.md`
