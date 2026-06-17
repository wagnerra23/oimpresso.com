---
page: /products/create
component: resources/js/Pages/Produto/Create.tsx
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Produto
related_adrs: [104, 149, 93, 107]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/produto-cockpit/"
  blueprint_screenshot_approval: "SYNC_LOG (pendente — Wave 2 B4 Produto 2026-05-15)"
  derived_screens: [Create]
  divergence_from_blueprint: "form full-width AppShellV2 (não Cockpit 3-col) — Create é form não-list; preserva tokens + header pattern + design system; não usa drawer pattern"
---

# Page Charter — /products/create (DRAFT)

> **Status:** draft criado em Wave 2 B4 Produto 2026-05-15 (Agent W2-C paralelo). Deriva de Index/produto-cockpit blueprint via ADR 0149.

## Mission

Cadastrar produto novo (single/variable/combo) com validação cliente-side TypeScript + server-side Laravel. Preserva pipeline UPOS legacy (geração SKU server-side, sync product_locations, Media upload). 8 campos sempre visíveis + ~22 colapsáveis em `<details>` "Avançado".

## Goals — Features (faz)

- AppShellV2 + PageHeader "Novo produto" + ações "Cancelar"+"Salvar"
- 5 seções Card: Identificação · Preço & Imposto · Estoque · Localizações · Avançado
- Campos sempre visíveis (8): name · sku · type · unit · category · brand · tax · alert_quantity
- Campos avançados colapsáveis: barcode_type · sub_category · sub_units · weight · product_description · enable_sr_no · expiry (se enabled) · racks (se enabled) · custom_fields 1-20
- Defaults: type='single' · enable_stock=true · tax_type='exclusive'
- Suporte duplicate via `?d=N` (preenche form com produto+`(copy)`)
- TypeScript estrito sem `any`
- Multi-tenant: dropdowns via business_id scope
- Cores semânticas tokens OKLCH Cowork

## Non-Goals (NÃO faz)

> ⚠️ Anti-alucinação. Wagner aprova.

- ❌ Variation builder dinâmico inline (variable type — Wave 3)
- ❌ Combo composition picker inline (combo type — Wave 3)
- ❌ Multi-image gallery upload (apenas 1 image — paridade legacy)
- ❌ NÃO gera SKU client-side (server-side em `store()`)
- ❌ NÃO modifica método `store()` PHP (out of scope)
- ❌ NÃO valida SKU duplicate cliente-side (server confirma)

## UX Targets

- p95 first-paint < 800ms
- 0 erros JS console
- Cabe em 1280px sem scroll horizontal (Larissa)
- TypeScript build verde
- Submit retorna `/products` lista (paridade legacy)

## UX Anti-patterns

- ❌ `sessionStorage` (usar `localStorage` com prefixo `oimpresso.produto.`)
- ❌ Cor crua `bg-blue-500`
- ❌ `auth()->user()->business_id` (canon UPOS: `session('user.business_id')`)

## Automation Hooks

- POST `/products` (store legacy intacto)
- GET `/products/create?d=N` duplicate
- Multi-tenant: global scope `business_id`

## Automation Anti-hooks

- ❌ Não dispara emails
- ❌ Não dispara jobs
- ❌ Não escreve no banco em GET
- ❌ Não chama Brain B
- ❌ Não acessa produto de outro `business_id`

## Pest GUARD (F4)

```php
it('Page Inertia existe em Pages/Produto/Create.tsx')
it('Page importa AppShellV2')
it('Page declara interface ProdutoCreatePageProps')
it('Page NÃO usa sessionStorage')
it('Page tem useForm com defaults type=single + enable_stock=true')
it('Controller isola business_id em dropdowns')
it('Cross-tenant biz=99 não acessa dropdowns biz=1')
```

## Refs

- Blueprint: [`produto-cockpit/produto-cockpit-page.jsx`](../../../prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx)
- RUNBOOK: [`memory/requisitos/Inventory/RUNBOOK-produto-create.md`](../../../memory/requisitos/Inventory/RUNBOOK-produto-create.md)
- Visual comparison: [`memory/requisitos/Inventory/produto-create-visual-comparison.md`](../../../memory/requisitos/Inventory/produto-create-visual-comparison.md)
- ADR 0149 screen-pattern reuse

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Charter criado em Wave 2 B4 Produto (Agent paralelo W2-C). |
