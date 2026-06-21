---
page: /products/stock-history/{id}
component: resources/js/Pages/Produto/StockHistory.tsx
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
  derived_screens: [StockHistory]
  divergence_from_blueprint: "timeline movimento por variação tem filtros location/variation próprios + Hero KPIs específicos (entrada/saída 30d) — pattern cronológico distinto de list cockpit. Mantém AppShellV2 + tokens + header pattern; diverge no conteúdo central"
---

# Page Charter — /products/stock-history/{id} (DRAFT)

## Mission

Auditoria de movimentos de estoque por variation × location. Larissa precisa entender entrada/saída/ajuste e onde foi reportado (OS/Compra/Venda).

## Goals

- AppShellV2 + PageHeader "Histórico de estoque · {nome produto}"
- Filter bar: variation_id select + location_id select + período (preset 7d/30d/90d)
- Hero KPIs (deferred): Estoque atual · Entrada 30d · Saída 30d
- Tabela cronológica (deferred): data · operação · qty · stock_before · stock_after · ref clicável
- Cor semântica linhas: emerald (in), rose (out), amber (adj)
- Link `ref` clica e leva pra OS/Compra/Venda original
- Multi-tenant scopado business_id

## Non-Goals

- ❌ Editar movimento (audit append-only)
- ❌ Deletar movimento
- ❌ Criar ajuste inline (rota separada `Stock Adjustment`)

## UX Targets

- p95 < 1s
- 1280px responsivo
- Tabela densa pra Larissa scrolar rápido

## Anti-patterns

- ❌ Mutação em GET (audit imutável)
- ❌ Cor crua

## Pest GUARD

```php
it('Page Inertia existe em Pages/Produto/StockHistory.tsx')
it('Page declara variations + businessLocations dropdowns')
it('Controller cross-tenant retorna 404')
it('Page tem Hero KPIs entrada/saída 30d')
```

## Refs

- RUNBOOK: `memory/requisitos/Inventory/RUNBOOK-produto-stock-history.md`
- Visual comparison: `memory/requisitos/Inventory/produto-stock-history-visual-comparison.md`
- ADR 0149

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Charter criado em Wave 2 B4 Produto. |
