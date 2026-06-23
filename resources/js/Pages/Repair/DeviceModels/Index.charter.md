---
page: /repair/device-models
component: resources/js/Pages/Repair/DeviceModels/Index.tsx
owner: wagner
status: draft
last_validated: "2026-05-17"
parent_module: Repair
parent_capterra: memory/requisitos/Repair/CAPTERRA-FICHA.md
related_adrs: [93, 101, 104, 121]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/os-page.jsx"
  blueprint_screenshot_approval: "n/a (catálogo administrativo, sem screenshot Cowork)"
  derived_screens: [Index]
  divergence_from_blueprint: "tabela simples — DeviceModel é catálogo compartilhado de Repair, não OS"
---

# Page Charter — /repair/device-models

> **Status:** draft (Blade T1 Migration C — Wave 2026-05-17). Coexistência com Blade legacy via flag `MWART_REPAIR_DEVICE_MODELS_INDEX`.

## Mission

Listar catálogo de modelos de aparelhos atendidos por oficina (compartilhado entre verticais Repair). Permitir filtro por marca/categoria e ações criar/editar.

## Goals — Features (faz)

- Header PT-BR com botão "Novo modelo" → `/repair/device-models/create`
- KpiGrid com totais: modelos cadastrados / marcas ativas / categorias
- DataTable: marca · modelo · categoria · checklist · ações
- Filter chips (brand_id + device_id) — server-side via Controller
- `localStorage` `oimpresso.repair.device_models.index.*` pra preservar filtros
- Multi-tenant: `business_id` global scope (Tier 0 IRREVOGÁVEL — ADR 0093)

## Non-Goals — Features (NÃO faz)

- ❌ Edição inline (vai pra `/repair/device-models/{id}/edit`)
- ❌ Bulk import CSV (não escopado nessa migração)
- ❌ Drag-and-drop reorder (catálogo plano sem hierarquia)
- ❌ Soft delete UI (destroy hard, segue legacy Blade)

## UX Targets

- p95 first-paint < 800ms (KPIs + payload via `Inertia::defer`)
- 0 erros console
- Cabe 1280px sem scroll horizontal (cliente ROTA LIVRE — embora não use Repair)

## UX Anti-patterns

- ❌ Modal pra criar/editar (rota dedicada existe — Blade legacy usa modal mas Inertia branch usa página)
- ❌ Tooltip explicando "o que é catálogo" (audiência técnica)

## Automation Hooks

- `DeviceModelController::index()` com filtros brand_id/device_id
- Multi-tenant scoping via `business_id`

## Automation Anti-hooks

- ❌ NÃO dispara email/SMS
- ❌ NÃO chama LLM
- ❌ NÃO acessa modelo de outro `business_id` (ADR 0093)
- ❌ NÃO toca FSM Repair (DeviceModel é catálogo, não OS — ADR 0143)

## Métricas vivas (Pest)

- `DeviceModelsInertiaSmokeTest::it_renders_inertia_index_when_flag_on()`
- `DeviceModelsInertiaSmokeTest::it_isolates_by_business_id_cross_tenant()`
- `DeviceModelsInertiaSmokeTest::it_keeps_blade_when_flag_off()`

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-17 | Agent Blade T1-C | Charter inicial junto com Create/Edit MWART |
