---
page: /contacts/{id}/edit
component: resources/js/Pages/Cliente/Edit.tsx
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
  derived_screens: [Edit]
  divergence_from_blueprint: "none"
---

# Page Charter — /contacts/{id}/edit (DRAFT)

> Backend canon: `ContactController::edit($id)` linha 768. Family visual idêntica a Create.

## Mission

Form de edição de cliente existente, pré-preenchido. Mesmo layout de Create + breadcrumb pra detalhe + submit PUT.

## Goals

- Pré-preenche todos os campos com dados existentes
- Submit via Inertia PUT `/contacts/{id}` (rota legacy aceita)
- Display opening_balance ajustado (já descontado pagamento, vindo de TransactionUtil::getTotalAmountPaid)
- Mesmo conjunto de seções de Create

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
