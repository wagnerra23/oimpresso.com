---
page: /sells/quotations
component: resources/js/Pages/Sells/Quotations.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
status_detail: wave1-draft
last_validated: "2026-05-15"
parent_module: Sells
related_adrs: [104, 110, 143, 149, 93]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/vendas-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG pendente verificar Wagner (ADR 0149)"
  derived_screens: [Quotations]
  divergence_from_blueprint: "Pattern Drafts (sub_status='quotation'). Ações específicas cotação: Enviar, Editar, Converter futuramente."
---

# Page Charter — /sells/quotations

> **Status:** wave1-draft. Migração MWART Wave 1 W1-A (2026-05-15).
> Reusa pattern lista Cockpit V2 ADR 0110 via ADR 0149.

---

## Mission

Listar cotações (status=draft + sub_status=quotation) — propostas formais enviadas pro cliente. Larissa converte em venda quando aprovado. Substitui `sale_pos.quotations.blade.php` legacy.

---

## Goals — Features (faz)

- AppShellV2 + PageHeader
- 1 KPI: total cotações ativas
- Search inline por nº / cliente
- Tabela 6 cols (data + nº cotação + cliente + local + itens + ações)
- Endpoint AJAX DataTables legacy preservado (`/sells/quotations?is_quotation=1`)
- Actions específicas: Editar + Enviar (PDF cotação no print blade)
- Customers dropdown deferred
- Atalho `N` (nova cotação), `Esc` (voltar)
- Multi-tenant Tier 0 backend
- Permission gate: `quotation.view_all` OR `quotation.view_own`

---

## Non-Goals — Features (NÃO faz)

- ❌ Converter cotação em venda direto (futuro: chamada FSM `quote_accepted`)
- ❌ Enviar cotação via WhatsApp/email direto (módulo Whatsapp separado)
- ❌ Filtros avançados (validade, valor) — backlog
- ❌ Bulk operations
- ❌ KPIs múltiplos (válidas/vencidas/convertidas) — esperar FSM stage_key

---

## UX Targets

- p95 first-paint < 1000ms
- 0 erros JS console
- 1280px sem scroll horizontal

---

## Endpoints alimentadores

| Método | Rota | Retorna |
|---|---|---|
| GET | `/sells/quotations` (X-Inertia) | Inertia render Sells/Quotations |
| GET | `/sells/quotations` (sem X-Inertia) | Blade `sale_pos.quotations` |
| GET | `/sells/quotations?is_quotation=1` (AJAX) | DataTables JSON |

---

## Tests anti-regressão

- [tests/Feature/Sells/Wave1QuotationsBaselineTest.php](../../../../tests/Feature/Sells/Wave1QuotationsBaselineTest.php) — 6 estruturais
- [tests/Feature/Sells/Wave1QuotationsInertiaTest.php](../../../../tests/Feature/Sells/Wave1QuotationsInertiaTest.php) — Inertia + cross-tenant

---

## Cutover plan

- Smoke biz=1: criar cotação → /sells/quotations → Editar → Enviar PDF
- Canary 7d
- Remover Blade após 30d

---

## Refs

- [ADR 0149](../../../../memory/decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- [RUNBOOK-quotations.md](../../../../memory/requisitos/Sells/RUNBOOK-quotations.md)
- Parent visual: `resources/js/Pages/Sells/Index.charter.md` (sibling: Drafts.charter.md)
