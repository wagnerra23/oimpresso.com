---
page: /repair/job-sheet/add-parts/{id}
component: resources/js/Pages/Repair/JobSheet/AddParts.tsx
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Repair
related_adrs: [104, 93]
tier: A
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/os/"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [AddParts]
  divergence_from_blueprint: "form add-peças não existe no blueprint OS (assumido fluxo POS) — reuso parcial header+stats apenas"
---

# Page Charter — /repair/job-sheet/add-parts/{id}

## Mission

Adicionar peças (variations) à OS pra cobrar do cliente. Form com lista editável (variation + qty).

## Goals

- Tabela peças (add/remove rows)
- Variation lookup (select async)
- Submit POST → `saveParts`

## Non-Goals

- ❌ Sem FSM (action não-transitiva)
- ❌ Sem edit OS

## UX Targets

- Add row sem recarregar página
- 1280px ok

## UX Anti-patterns

- ❌ Tabela sem header sticky em muitas peças

## Automation Hooks

- POST `/repair/job-sheet/save-parts/{id}` legacy preservado
- Permission `job_sheet.create` OR `edit`

## Automation Anti-hooks

- ❌ NÃO consome estoque ao salvar parts (consumo via FSM action)
- ❌ NÃO chama LLM

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | Agent W3-E | Inicial |
