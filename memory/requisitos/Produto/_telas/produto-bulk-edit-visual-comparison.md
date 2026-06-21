---
slug: inventory-produto-bulk-edit-visual-comparison
title: "Produto — Comparativo visual da tela Edição em massa"
type: visual-comparison
module: Inventory
status: approved
date: 2026-05-15
canon_reference: prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx
blade_source: resources/views/product/bulk-edit.blade.php
inertia_target: resources/js/Pages/Produto/BulkEdit.tsx
approved_by: pending_wagner_screenshot_approval
pattern_reuse: true
divergence: "datatable multi-row edit — pattern distinto Index Cockpit"
blueprint_cowork: prototipo-ui/prototipos/produto-cockpit/
---

# Comparativo visual — Edição em massa (`/products/mass-edit`)

> Tela datatable multi-row edit — divergência declarada do blueprint Cockpit (ADR 0149)

## Resumo executivo

Blade legacy tem tabela editable inline pra N produtos selecionados. MWART entrega Page Inertia mantendo família AppShellV2 + tokens canon, mas datatable multi-row edit é pattern distinto de Index Cockpit/drawer (ADR 0149 §"Casos que NÃO se qualificam"). Divergência justificada e declarada no charter.

## Tabela comparativa abreviada

| Aspecto | Blade | MWART (divergência) |
|---|---|---|
| Layout | tabela ampla edit-in-place | tabela densa edit-in-place + header AppShellV2 |
| Header | "Bulk Edit Products" | "Edição em massa · {N} produtos" |
| Colunas editáveis | Category · Sub · Brand · Tax · Locations · Prices | mesmo |
| Submit | "Update All" rodapé | "Atualizar {N} produtos" sticky topo |
| Aviso destrutivo | nenhum | banner "Estas alterações afetam {N} produtos simultaneamente" |

## Divergência blueprint

ADR 0149 §"Casos que NÃO se qualificam": "Bulk-edit datatable (interação multi-row distinta de Index)". Charter declara explicitamente.

## Aprovação

⏳ Pendente Wagner screenshot approval.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Comparativo criado em Wave 2 B4 Produto. |
