---
page: /repair/device-models/create
component: resources/js/Pages/Repair/DeviceModels/Create.tsx
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
  derived_screens: [Create]
  divergence_from_blueprint: "página dedicada substitui modal Blade legacy"
---

# Page Charter — /repair/device-models/create

## Mission

Criar novo modelo de aparelho no catálogo Repair (compartilhado entre verticais). Preserva campos do Blade legado: nome · marca · categoria/dispositivo · checklist (pipe-separated).

## Goals

- Form completo: name · brand_id (select) · device_id (select) · repair_checklist (textarea pipe-separated)
- Submit POST `/repair/device-models` (rota resource Laravel)
- Cancelar volta pra `/repair/device-models`
- `business_id` injetado no Controller (não no payload — Tier 0 ADR 0093)

## Non-Goals

- ❌ Editar modelo existente (rota `/edit`)
- ❌ Upload imagem (não escopado no Blade legacy)
- ❌ Wizard multi-step (form é flat — 4 campos só)

## UX Targets

- p95 first-paint < 500ms
- Cabe 1280px

## UX Anti-patterns

- ❌ Auto-save (form trivial, não precisa)
- ❌ Confirmação dupla pra salvar
- ❌ Modal sobre modal (Blade usa modal — Inertia branch não)

## Automation Hooks

- `DeviceModelController::store()` preservado (mesma rota, mesma assinatura)
- Permission `superadmin` OU `repair_module` subscription

## Automation Anti-hooks

- ❌ NÃO cria modelo em outro `business_id`
- ❌ NÃO chama LLM
- ❌ NÃO dispara email/SMS

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-17 | Agent Blade T1-C | Charter inicial — Blade T1 Migration C |
