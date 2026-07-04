---
page: /products/mass-edit
component: resources/js/Pages/Produto/BulkEdit.tsx
related_us: [US-PROD-023]
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Produto
related_adrs: [104, 149, 93, 107]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/produtos-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [BulkEdit]
  divergence_from_blueprint: "datatable multi-row edit — pattern distinto de Index Cockpit. ADR 0149 §'Casos que NÃO se qualificam — bulk-edit datatable (interação multi-row distinta de Index)'. Mantém família AppShellV2 + tokens + header pattern; diverge no conteúdo central edit-in-place"
---

# Page Charter — /products/mass-edit (DRAFT)

## Mission

Editar atributos comuns (Category/Sub/Brand/Tax/Locations + preços variations) em N produtos selecionados simultaneamente. Tabela densa edit-in-place + aviso destrutivo claro.

## Goals

- AppShellV2 + PageHeader "Edição em massa · {N} produtos"
- Banner aviso destrutivo "Estas alterações afetam {N} produtos simultaneamente"
- Tabela densa edit-in-place: 1 linha por produto + colunas editáveis
- Colunas: Categoria (select) · Sub-categoria · Brand · Tax · Locations (multi) · Variations prices (sub-rows)
- Botão "Atualizar {N} produtos" sticky topo (primary destructive)
- Multi-tenant: `business_id` scope nas queries
- Submit POST `/products/mass-update`

## Non-Goals

- ❌ Adicionar produto novo inline
- ❌ Deletar produto inline
- ❌ Editar SKU/name/type (sensíveis demais pra bulk)
- ❌ Variation builder novo inline

## UX Targets

- p95 < 1.5s (N produtos × variations)
- Larissa hesita antes confirmar — banner aviso visível
- Confirmação modal "Confirma alterar {N} produtos?" antes submit

## Anti-patterns

- ❌ `withoutGlobalScopes` sem comentário SUPERADMIN
- ❌ Submit sem confirmação destrutiva
- ❌ Edit-in-place sem feedback visual (linha dirty)

## Pest GUARD

```php
it('Page Inertia existe em Pages/Produto/BulkEdit.tsx')
it('Page declara matriz products × atributos')
it('Page tem banner aviso destrutivo')
it('Controller cross-tenant não inclui produtos biz=99')
```

## Refs

- RUNBOOK: `memory/requisitos/Inventory/RUNBOOK-produto-bulk-edit.md`
- Visual comparison: `memory/requisitos/Inventory/produto-bulk-edit-visual-comparison.md`
- ADR 0149

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Charter criado em Wave 2 B4 Produto. |
