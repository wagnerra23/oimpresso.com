---
page: /repair/device-models/{id}/edit
component: resources/js/Pages/Repair/DeviceModels/Edit.tsx
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-05-17"
parent_module: Repair
related_adrs: [93, 104]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "n/a (form CRUD simples)"
  blueprint_screenshot_approval: "n/a"
  derived_screens: [Edit]
  divergence_from_blueprint: "página dedicada substitui modal Blade legacy"
---

# Page Charter — /repair/device-models/{id}/edit

## Mission

Editar modelo existente do catálogo Repair. Preserva campos do Blade legado e respeita isolamento `business_id`.

## Goals

- Form pré-populado com dados atuais do DeviceModel
- Mesmos 4 campos do Create
- Submit PUT `/repair/device-models/{id}`
- Cancelar volta pra `/repair/device-models`
- 404 se modelo pertence a outro `business_id` (Controller faz `findOrFail` scopado)

## Non-Goals

- ❌ Histórico de mudanças (Spatie ActivityLog já registra — ver Show futuramente)
- ❌ Soft delete daqui (rota destroy separada)

## UX Targets

- p95 first-paint < 500ms
- Cabe 1280px

## UX Anti-patterns

- ❌ Confirmação dupla
- ❌ Modal stacking

## Automation Hooks

- `DeviceModelController::update()` preservado
- Permission `superadmin` OU `repair_module` subscription

## Automation Anti-hooks

- ❌ NÃO atualiza modelo de outro `business_id` (ADR 0093 — `findOrFail` scopado)
- ❌ NÃO dispara LLM/email/SMS

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-17 | Agent Blade T1-C | Charter inicial — Blade T1 Migration C |
