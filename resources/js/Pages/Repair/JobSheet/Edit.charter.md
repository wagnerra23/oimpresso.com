---
page: /repair/job-sheet/{id}/edit
component: resources/js/Pages/Repair/JobSheet/Edit.tsx
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Repair
related_adrs: [104, 143, 93]
tier: A
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/os-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Edit]
  divergence_from_blueprint: "form de edição completo (blueprint mostra apenas drawer-detail)"
---

# Page Charter — /repair/job-sheet/{id}/edit

## Mission

Editar dados de OS preservando coexistência FSM/legacy: campos principais (cliente, aparelho, defeitos, status legacy, checklist) editáveis. Transições FSM via Show panel (não aqui).

## Goals

- Form completo com todos os campos do Blade preservados
- Tabs UX: Cliente · Aparelho · Defeitos · Checklist · Anexos
- Submit PUT `/repair/job-sheet/{id}`
- Validação client + server-side
- Cancel → Show

## Non-Goals

- ❌ Editar `current_stage_id` (FSM via Show panel)
- ❌ AddParts inline (rota separada)
- ❌ Upload docs inline (rota separada)

## UX Targets

- Auto-save preview (M2)
- Validação inline antes de submit
- 1280px ok

## UX Anti-patterns

- ❌ Tabs sem indicação de erro
- ❌ Submit sem confirmação se mudou status

## Automation Hooks

- `JobSheetController::update` preservado (legacy path)
- `business_id` scope
- Permission `job_sheet.edit`

## Automation Anti-hooks

- ❌ NÃO UPDATE direto current_stage_id
- ❌ NÃO chama LLM

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | Agent W3-E | Inicial |
