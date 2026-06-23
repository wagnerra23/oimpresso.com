---
page: /repair/job-sheet/create
component: resources/js/Pages/Repair/JobSheet/Create.tsx
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Repair
related_adrs: [104, 93]
tier: A
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/os-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Create]
  divergence_from_blueprint: "wizard step-by-step substitui NewOsModal compacto do blueprint"
---

# Page Charter — /repair/job-sheet/create

## Mission

Criar nova OS preservando ergonomia do Blade legado (cliente + aparelho + defeitos + checklist) com fluxo "Salvar e adicionar peças" / "Salvar e upload docs" mantido.

## Goals

- Form completo (mesmos campos do Blade `create.blade.php`)
- Combobox cliente (com fallback walk_in_customer)
- Submit types: save · save_and_add_parts · save_and_upload_docs

## Non-Goals

- ❌ Editar OS existente
- ❌ FSM pipeline iniciação (OS nasce legacy)

## UX Targets

- p95 < 500ms first-paint (props vêm via defer)
- Cabe 1280px

## UX Anti-patterns

- ❌ Modal stacking

## Automation Hooks

- `JobSheetController::store` preservado
- Permission `job_sheet.create`

## Automation Anti-hooks

- ❌ NÃO cria OS de outro biz
- ❌ NÃO chama LLM

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | Agent W3-E | Inicial |
