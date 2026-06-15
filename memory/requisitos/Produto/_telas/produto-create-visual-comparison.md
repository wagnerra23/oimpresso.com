---
slug: inventory-produto-create-visual-comparison
title: "Produto — Comparativo visual da tela Novo produto"
type: visual-comparison
module: Inventory
status: approved
date: 2026-05-15
canon_reference: prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx
blade_source: resources/views/product/create.blade.php
inertia_target: resources/js/Pages/Produto/Create.tsx
approved_by: pending_wagner_screenshot_approval
pattern_reuse: true
blueprint_cowork: prototipo-ui/prototipos/produto-cockpit/
---

# Comparativo visual — Novo produto (`/products/create`)

> Form full-width AppShellV2 — derivado de Produto/Index (ADR 0149 screen-pattern reuse)

## Resumo executivo

Blade legacy `product.create` tem ~30+ campos compactados em formulário denso jQuery+Bootstrap. MWART entrega versão Inertia com seções Card (Identificação | Preço & Imposto | Estoque | Localizações | Avançado), 8 campos sempre visíveis + ~22 colapsáveis em `<details>` "Avançado", validações de tipo cliente-side (TypeScript). Defaults: type='single', enable_stock=true, tax_type='exclusive'.

## Pattern reuse blueprint

Mesma família visual AppShellV2 + tokens OKLCH Cowork do Index. Form não tem KPI strip (faz sentido em list); usa header pattern + Card layout + tokens semânticos (emerald=ok, rose=danger).

## Tabela comparativa abreviada

| Aspecto | Blade | MWART (pattern reuse) |
|---|---|---|
| Layout | 3+ telas scroll vertical | 1 tela scroll, 5 Cards |
| Campos visíveis | ~30 | 8 sempre + 22 colapsáveis |
| Defaults | type=single mas sem `enable_stock` default | type=single + enable_stock=true (Larissa default) |
| Validação | jQuery validate inline + server | TypeScript strict + Laravel validate server |
| Tax dropdown | Select2 + dropdown agrupada | Radix Select + Object.entries |
| Categoria | Hierárquico AJAX sub-categorias | Hierárquico Inertia partial reload |
| SKU | Server gera se vazio | Idem — preserva pipeline server |

## Gaps catalogados

| Gap | Impacto | Plano |
|---|---|---|
| Image upload preview | UX melhor com preview client | Wave 3 |
| Variation builder dinâmico | Variable type tem N variações | Wave 3 |
| Combo composition picker | Combo type lista insumos | Wave 3 |
| Brochure media upload | Multi-file upload | Wave 3 |

## Aprovação

⏳ Pendente Wagner screenshot approval.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Comparativo criado em Wave 2 B4 Produto. |
