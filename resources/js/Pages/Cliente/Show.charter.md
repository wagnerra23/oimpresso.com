---
page: /contacts/{id}
component: resources/js/Pages/Cliente/Show.tsx
owner: wagner
status: draft
last_validated: 2026-05-15
parent_module: Cliente
related_adrs: [0110, 0107, 0093, 0094, 0104, 0149]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/clientes/"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Show]
  divergence_from_blueprint: "none"
---

# Page Charter — /contacts/{id} (DRAFT)

> Backend canon: `ContactController::show($id)` linha 713. Pattern reuse blueprint Cowork Index — header pattern + KPI cards idênticos.

## Mission

Página completa de detalhe do cliente com header rico + 4 stats financeiros + histórico de transações + sidebar contato. Inertia::defer em stats e transactions.

## Goals

- Header com avatar quadrado 56px, nome, doc, tipo (badge), botão Editar
- 4 stat cards: total_invoice, invoice_due, total_purchase, opening_balance
- Layout 2-col 6xl: sidebar contato + histórico transações
- `Inertia::defer` em stats + transactions (queries caras)
- Link "Extrato completo" pra Ledger
- Multi-tenant: `App\Contact::where('business_id', ...)` global scope

## Non-Goals

- ❌ Edição inline (botão "Editar" leva pra /contacts/{id}/edit)
- ❌ Delete inline (rota legacy /contacts/duplicates)
- ❌ Histórico de mensagens WhatsApp (vai pra Modules/Whatsapp)
- ❌ Activity log completo (rota tab dedicada, scope futuro)

## UX Targets

- p95 first-paint header < 600ms
- p95 stats defer < 800ms
- p95 transactions defer < 1500ms (limit 20 últimas)

## Automation Anti-hooks

- ❌ Não dispara emails ao abrir
- ❌ Não emite log de "viewed" (privacidade)
- ❌ Não acessa Contact de outro `business_id`

## Refs

- Backend: `ContactController::show()`
- Pattern reuse: ADR 0149
