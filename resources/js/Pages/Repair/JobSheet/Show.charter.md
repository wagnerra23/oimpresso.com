---
page: /repair/job-sheet/{id}
component: resources/js/Pages/Repair/JobSheet/Show.tsx
owner: wagner
status: draft
last_validated: 2026-05-15
parent_module: Repair
related_adrs: [0104, 0143, 0093, 0149]
tier: A
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/os/"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Show]
  divergence_from_blueprint: "FSM panel é elemento adicional não presente no blueprint (módulo FSM canônico ADR 0143)"
---

# Page Charter — /repair/job-sheet/{id}

## Mission

Detalhe completo de OS com FSM panel pra executar transições canônicas (ADR 0143 quando pipeline iniciado) OU mostrar empty state "Iniciar pipeline FSM" quando legacy.

## Goals — Features

- Header: OS#, status (badge legacy ou FSM stage), botões Edit/AddParts/Print
- Detalhes: cliente, aparelho (brand/device/model/serial), defects, condition
- Checklist read
- Parts usadas (peças cobradas)
- Anexos (foto + docs via `HasArquivos`)
- Timeline activities (activity_log)
- **FSM Panel** lateral: actions disponíveis ou "Iniciar pipeline"

## Non-Goals

- ❌ Edit inline (vai pra /edit)
- ❌ UPDATE direto `current_stage_id` (proibido — FSM service)
- ❌ Delete inline (vai pra confirm dialog)
- ❌ Print PDF inline (rota separada)

## UX Targets

- FSM panel renderiza em <500ms (chama `/api/repair/job-sheets/{id}/fsm-actions` async)
- Activities defer (lista pode ser grande)
- Anexos defer
- 1280px ok

## UX Anti-patterns

- ❌ Modal redundante pra ação non-critical FSM
- ❌ Toast spam

## Automation Hooks

- FSM execute via POST `/repair/job-sheets/{id}/fsm-action` → `ExecuteStageActionService` (ADR 0143)
- `business_id` scope
- Permission `job_sheet.view_all` OU `view_assigned`

## Automation Anti-hooks

- ❌ NÃO faz UPDATE direto em `current_stage_id` (trait `GuardsFsmTransitions` bloqueia)
- ❌ NÃO acessa OS de outro biz (Tier 0)
- ❌ NÃO chama LLM no render

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | Agent W3-E | Charter inicial Wave 3 B6 Repair |
