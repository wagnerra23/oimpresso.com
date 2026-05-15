---
slug: inventory-produto-selling-prices-visual-comparison
title: "Produto — Comparativo visual da tela Tabelas de preço"
type: visual-comparison
module: Inventory
status: approved
date: 2026-05-15
canon_reference: prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx
blade_source: resources/views/product/add-selling-prices.blade.php
inertia_target: resources/js/Pages/Produto/SellingPrices.tsx
approved_by: pending_wagner_screenshot_approval
pattern_reuse: true
divergence: "matriz variation × price_group — tabela densa específica"
blueprint_cowork: prototipo-ui/prototipos/produto-cockpit/
---

# Comparativo visual — Tabelas de preço (`/products/add-selling-prices/{id}`)

> Tela matriz price-group × variation — divergência justificada do blueprint Cockpit (ADR 0149 §"Casos que NÃO se qualificam — bulk-edit datatable distinto")

## Resumo executivo

Blade legacy tem tabela ampla com colunas dinâmicas (1 por price_group ativo) × linhas (1 por variation). MWART entrega Page Inertia mantendo família AppShellV2 + tokens canon + header pattern, mas matriz de preços diverge do blueprint Cockpit/drawer (que usa lista cards + drawer detalhe). Divergência justificada e declarada no charter.

## Tabela comparativa abreviada

| Aspecto | Blade | MWART (divergência justificada) |
|---|---|---|
| Layout | tabela ampla | tabela densa + header AppShellV2 |
| Header | título simples | "Tabelas de preço · {nome produto}" + SKU mono |
| Por célula | input + dropdown type | input numérico + Select (fixed/percentage) |
| Submit | botão "Save" rodapé | "Salvar tabelas" topo direito (sticky) |
| Variation rows | Linhas por variation | mesmo — linhas variation; colunas price_group |

## Divergência blueprint Cowork

Charter `divergence_from_blueprint`: "matriz variation × price_group é tabela densa específica — não é list cockpit padrão". ADR 0149 §"Casos que NÃO se qualificam" admite divergência. Mantém família AppShellV2 + tokens + header pattern; diverge no conteúdo central.

## Aprovação

⏳ Pendente Wagner screenshot approval.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Comparativo criado em Wave 2 B4 Produto. |
