---
page: /products/{id}/edit
component: resources/js/Pages/Produto/Edit.tsx
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Produto
related_adrs: [104, 149, 93, 107]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/produto-cockpit/"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [Edit]
  divergence_from_blueprint: "none — deriva direto de Create.tsx (mesma estrutura form full-width AppShellV2). ADR 0149 admite Edit deriva de Create da mesma entidade"
---

# Page Charter — /products/{id}/edit (DRAFT)

## Mission

Editar produto existente reusando estrutura Create.tsx 100% — diferença é `useForm` inicializado com `product` props recebidos do controller. Type select disabled (não muda após criar).

## Goals

- Mesma estrutura Create.tsx
- Header "Editar produto · {nome} · SKU mono small"
- Type select disabled
- Defaults preenchidos com produto atual
- "Salvar alterações" + "Cancelar"
- Multi-tenant: produto cross-tenant retorna 404

## Non-Goals

- ❌ Mudar `type` (Single/Variable/Combo) após criar
- ❌ Editar variations dinamicamente (Wave 3)
- ❌ Deletar produto inline

## UX Targets

- p95 < 800ms
- 1280px sem scroll horizontal
- Form preenchido em <300ms (já vem do controller)

## Anti-patterns

- ❌ NÃO mexer no método `update()` PHP (out of scope)
- ❌ NÃO recriar SKU server-side no client

## Pest GUARD

```php
it('Page Inertia existe em Pages/Produto/Edit.tsx')
it('Page importa AppShellV2')
it('Page declara interface ProdutoEditPageProps com product')
it('Page tem type select disabled')
it('Controller cross-tenant retorna 404')
```

## Refs

- RUNBOOK: `memory/requisitos/Inventory/RUNBOOK-produto-edit.md`
- Visual comparison: `memory/requisitos/Inventory/produto-edit-visual-comparison.md`
- ADR 0149

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Charter criado em Wave 2 B4 Produto. |
