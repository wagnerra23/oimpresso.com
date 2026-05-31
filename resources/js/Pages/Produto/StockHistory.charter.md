---
page: /products/stock-history/{id}
component: resources/js/Pages/Produto/StockHistory.tsx
owner: wagner
status: live
last_validated: 2026-05-31
parent_module: Produto
related_adrs: [0104, 0149, 0093, 0107]
tier: A
charter_version: 2
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/produto-cockpit/"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [StockHistory]
  divergence_from_blueprint: "timeline movimento por variação tem filtros location/variation próprios + resumo entrada/saída/ajuste/saldo do período — pattern cronológico distinto de list cockpit. Mantém AppShellV2 + tokens + header pattern; diverge no conteúdo central"
---

# Page Charter — /products/stock-history/{id} (LIVE)

## Mission

Auditoria de movimentos de estoque por variation × location. Larissa precisa entender entrada/saída/ajuste e onde foi reportado (OS/Compra/Venda) — INLINE, sem abrir o sistema legado.

## Goals

- AppShellV2 + PageHeader canon "Histórico de estoque" + descrição {nome produto · sku · unidade} — feito 2026-05-31
- Filter bar: variation_id select + location_id select (persist localStorage `oimpresso.produto.stockHistory.*`)
- Timeline movimento INLINE real (deferred): data · tipo (entrada/saída/ajuste) · origem · referência · quantidade assinada · saldo corrente (running balance) — feito 2026-05-31
- Resumo do período (deferred): Entradas · Saídas · Ajustes · Saldo do período (tiles)
- Cor semântica via tokens DS: foreground (entrada), destructive (saída), muted (ajuste)
- Carregamento via `Inertia::defer('movements')` + `<Deferred>` skeleton (SPA feel; partial reload `only:['movements','filters']` ao trocar filtro)
- Multi-tenant scopado business_id (Tier 0) — reusa `ProductUtil::getVariationStockHistory` (já escopa business_id + location_id + variation_id)

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
- ❌ Cor crua (só tokens DS — proibido stone/sky/hex/oklch inline; primary roxo via `bg-primary`)
- ❌ iframe/embed do Blade legado como solução final (removido 2026-05-31 — link legado é só fallback de rodapé)

## Fonte de dados (front ↔ back)

A tela consome a prop **`movements`** (carregada via `Inertia::defer`) — array de:
`{ id, kind: 'entrada'|'saida'|'ajuste', dateLabel, quantity (number, saída/ajuste negativo), quantityLabel, balanceLabel (saldo corrente após o movimento), origin, refNo }`.

Backend: `ProductController::productStockHistory()` (branch X-Inertia) →
`'movements' => Inertia::defer(fn () => $this->buildStockMovements($business_id, $variationId, $locationId))`.
`buildStockMovements` delega a query canônica a **`ProductUtil::getVariationStockHistory($variation_id, $location_id, $business_id)`** que une:
- `purchase_lines`           → entrada (+) · `transactions.type IN (purchase, purchase_transfer, opening_stock), status=received`
- `transaction_sell_lines`   → saída   (−) · `transactions.type IN (sell, sell_transfer), status=final`
- `stock_adjustment_lines`   → ajuste  (−) · `transactions.type=stock_adjustment`

Filtros `variation_id` + `location_id` (query string). A tela inicializa na primeira variação + primeiro local (o helper exige ambos preenchidos).

## Pest GUARD

```php
it('Page Inertia existe em Pages/Produto/StockHistory.tsx')
it('Page declara variations + businessLocations dropdowns')
it('Controller cross-tenant retorna 404')
it('Page renderiza timeline movimento inline (Deferred movements)')
```

## TODO (Wave 3)

- Drill-down: `refNo` clicável levando à OS/Compra/Venda original
- Devoluções (sell_return/purchase_return) com sinal correto e badge própria na timeline
- Paginação real (cursor) — hoje a timeline vem completa do helper (ordenada desc)
- Aposentar o link legado de rodapé quando saldo corrente + drill-down estiverem cobertos

## Refs

- RUNBOOK: `memory/requisitos/Inventory/RUNBOOK-produto-stock-history.md`
- Visual comparison: `memory/requisitos/Inventory/produto-stock-history-visual-comparison.md`
- ADR 0149

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Charter criado em Wave 2 B4 Produto. |
| 2026-05-31 | [arte] | Timeline movimento INLINE real (defer+skeleton via `getVariationStockHistory`), PageHeader canon + tokens DS, resumo do período, partial reload por filtro. iframe legado demovido a fallback de rodapé. status draft→live. |
