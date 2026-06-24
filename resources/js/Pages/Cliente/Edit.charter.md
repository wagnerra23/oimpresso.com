---
page: /contacts/{id}/edit
component: resources/js/Pages/Cliente/Edit.tsx
owner: wagner
status: live
last_validated: "2026-06-24"
parent_module: Cliente
related_adrs: [110, 107, 93, 94, 104, 149, 235]
tier: A
charter_version: 2
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/clientes-page.jsx"
  blueprint_screenshot_approval: "Wagner 2026-05-29 — PR-A Onda F (espelho do Create via ClienteForm)"
  derived_screens: [Edit]
  divergence_from_blueprint: "none"
---

# Page Charter — /contacts/{id}/edit (LIVE)

> **Status:** live — reconciliado de draft em 2026-06-24: Wagner confirmou biz=4 (ROTA LIVRE) em React em produção (flag `MWART_CLIENTE_EDIT` ON; fallback Blade no `ContactController`). Backend canon: `ContactController::edit($id)` linha 768. Family visual idêntica a Create.

## Mission

Form de edição de cliente existente, pré-preenchido. Mesmo layout de Create + breadcrumb pra detalhe + submit PUT.

## Goals

- Pré-preenche todos os campos com dados existentes
- Submit via Inertia PUT `/contacts/{id}` (rota legacy aceita)
- Display opening_balance ajustado (já descontado pagamento, vindo de TransactionUtil::getTotalAmountPaid)
- Mesmo corpo do Create via `_form/ClienteForm` compartilhado (DS v4 Onda F: Segmented, FormSection, InputGroup, FieldError) + rail de contexto

## Non-Goals

- ❌ Mudar tipo contact->customer durante edição (risco data integrity)
- ❌ Histórico de mudanças inline (vai pra activity tab no Show)
- ❌ Bulk edit (rota legacy /contacts/duplicates)

## UX Targets

- p95 first-paint < 800ms
- Submit < 1500ms p95

## Automation Anti-hooks

- ❌ Não dispara recálculo de credit_limit em background
- ❌ Não envia notification "cadastro alterado" ao cliente

## Refs

- Backend: `ContactController::edit()`
- Pattern reuse: ADR 0149
