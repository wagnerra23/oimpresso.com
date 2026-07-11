---
page: /repair/repair/{id}
component: resources/js/Pages/Repair/Show.tsx
related_prototype: n/a (herda PT-03 Detalhe; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Repair
related_adrs: [104, 143, 93]
tier: A
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/os-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Show]
  divergence_from_blueprint: "venda-de-reparo (Transaction sub_type=repair) — adiciona sell_lines+payments+warranty vs blueprint que mostra OS-tipo"
---

# Page Charter — /repair/repair/{id}

## Mission

Detalhe da VENDA-de-reparo (Transaction sub_type='repair'). Mostra invoice/sell-lines/payments/warranty/activities. FSM Sells panel opcional via flag.

## Goals

- Header invoice_no + status + valor
- Cliente / Aparelho (repair_model_id)
- Sell lines table (peças/serviços faturados)
- Pagamentos
- Warranty info
- Activities log defer
- FSM panel Sells (opcional via flag `mwart.repair_show_fsm_panel.enabled`)

## Non-Goals

- ❌ Edit inline (vai pra /edit)
- ❌ JobSheet detail (vai pra /job-sheet/{id})
- ❌ Cancel sell inline

## UX Targets

- Activities defer
- 1280px ok
- p95 < 1000ms

## UX Anti-patterns

- ❌ Confusão com JobSheet (deixar header explícito "Venda de Reparo")

## Automation Hooks

- `business_id` scope
- Permission `repair.view`

## Automation Anti-hooks

- ❌ NÃO emite NFe direto
- ❌ NÃO chama LLM
- ❌ NÃO transita FSM sem ExecuteStageActionService

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | Agent W3-E | Inicial |
