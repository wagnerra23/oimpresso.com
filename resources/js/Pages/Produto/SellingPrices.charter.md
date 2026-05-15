---
page: /products/add-selling-prices/{id}
component: resources/js/Pages/Produto/SellingPrices.tsx
owner: wagner
status: draft
last_validated: 2026-05-15
parent_module: Produto
related_adrs: [0104, 0149, 0093, 0107]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/produto-cockpit/"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [SellingPrices]
  divergence_from_blueprint: "matriz variation × price_group é tabela densa específica — não é list cockpit padrão; mantém AppShellV2 + tokens + header pattern; diverge no conteúdo central. ADR 0149 §'Casos que NÃO se qualificam — bulk-edit datatable'"
---

# Page Charter — /products/add-selling-prices/{id} (DRAFT)

## Mission

Configurar preços de variations por price_group (matriz N×M). Cada célula = price + price_type (fixed/percentage). Tela densa pra usuário definir tabelas balcão/atacado/varejo por variação.

## Goals

- AppShellV2 + PageHeader "Tabelas de preço · {nome produto}" + SKU mono
- Tabela densa: linhas = variations ativas, colunas = price_groups ativos
- Por célula: input numérico + Select (fixed/percentage)
- Botão "Salvar tabelas" sticky topo
- Multi-tenant scopado business_id
- Submit POST `/products/save-selling-prices`

## Non-Goals

- ❌ Editar nome variation/price_group inline
- ❌ Criar price_group novo inline (rota separada)
- ❌ Bulk apply (mesma price em N variations) — Wave 3

## UX Targets

- p95 < 800ms
- 1280px responsivo (matriz pode ter scroll horizontal se >5 price_groups)
- Tabular-nums em valores

## Anti-patterns

- ❌ `auth()->user()->business_id` (canon UPOS session)
- ❌ Cor crua

## Pest GUARD

```php
it('Page Inertia existe em Pages/Produto/SellingPrices.tsx')
it('Page declara matriz variations × priceGroups')
it('Controller cross-tenant retorna 404')
```

## Refs

- RUNBOOK: `memory/requisitos/Inventory/RUNBOOK-produto-selling-prices.md`
- Visual comparison: `memory/requisitos/Inventory/produto-selling-prices-visual-comparison.md`
- ADR 0149

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Charter criado em Wave 2 B4 Produto. |
