---
slug: inventory-produto-stock-history-visual-comparison
title: "Produto — Comparativo visual da tela Histórico de estoque"
type: visual-comparison
module: Inventory
status: approved
date: 2026-05-15
canon_reference: prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx
blade_source: resources/views/product/stock_history.blade.php
inertia_target: resources/js/Pages/Produto/StockHistory.tsx
approved_by: pending_wagner_screenshot_approval
pattern_reuse: true
divergence: "timeline movimento por variação — pattern cronológico distinto"
blueprint_cowork: prototipo-ui/prototipos/produto-cockpit/
---

# Comparativo visual — Histórico de estoque (`/products/stock-history/{id}`)

> Tela timeline cronológica por variação — divergência declarada (ADR 0149)

## Resumo executivo

Blade legacy tem dropdown variation + location + tabela cronológica de movimentos. MWART entrega Page Inertia mantendo família AppShellV2 + tokens canon + header pattern, mas timeline com filtros location/variation é pattern distinto de list cockpit (ADR 0149 §"Casos que NÃO se qualificam"). Divergência justificada.

## Tabela comparativa abreviada

| Aspecto | Blade | MWART (divergência) |
|---|---|---|
| Layout | dropdown topo + tabela | filter bar + Hero KPIs + tabela cronológica |
| Hero | ausente | Estoque atual · Entrada 30d · Saída 30d (deferred) |
| Filter | variation_id select + location_id select | mesmo via Inertia partial reload |
| Tabela | data, tipo, qty, stock após | mesmo + cor semântica (emerald in, rose out, amber adj) |
| Ref | texto plain | link clicável pra OS/Compra/Venda |

## Divergência blueprint

Não cabe no pattern drawer Cockpit. Mantém família + tokens; diverge no conteúdo central.

## Aprovação

⏳ Pendente Wagner screenshot approval.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Comparativo criado em Wave 2 B4 Produto. |
