---
slug: inventory-produto-edit-visual-comparison
title: "Produto — Comparativo visual da tela Editar"
type: visual-comparison
module: Inventory
status: approved
date: 2026-05-15
canon_reference: prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx
blade_source: resources/views/product/edit.blade.php
inertia_target: resources/js/Pages/Produto/Edit.tsx
approved_by: pending_wagner_screenshot_approval
pattern_reuse: true
blueprint_cowork: prototipo-ui/prototipos/produto-cockpit/
---

# Comparativo visual — Editar produto (`/products/{id}/edit`)

> Form full-width derivada de Produto/Create + Produto/Index (ADR 0149)

## Resumo executivo

Blade legacy tem mesma estrutura `product.create` com defaultValues. MWART reusa estrutura Create.tsx 100% (mesma família visual AppShellV2, mesmos Cards, mesmas seções), inicializando `useForm` com `product` props recebidos do controller. Header diferencia: "Editar produto · {nome} · SKU mono".

## Tabela comparativa abreviada

| Aspecto | Blade | MWART (pattern reuse Create) |
|---|---|---|
| Estrutura | igual create.blade.php com `old()` | igual Create.tsx com `useForm({...product})` |
| Header diferencial | título "Edit Product" | "Editar produto · {nome}" + SKU mono small |
| Botões | "Update" | "Salvar alterações" + "Cancelar" |
| Type select | disabled (não muda type após criar) | mesmo — disabled |

## Pattern reuse

ADR 0149: Edit deriva de Create (form-secundário mesma entidade) — sem divergência.

## Gaps catalogados

| Gap | Plano |
|---|---|
| Variation editor (variable) | Wave 3 |
| Opening stock link | Wave 3 |

## Aprovação

⏳ Pendente Wagner screenshot approval.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Comparativo criado em Wave 2 B4 Produto. |
