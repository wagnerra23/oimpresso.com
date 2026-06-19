---
slug: inventory-produto-show-visual-comparison
title: "Produto — Comparativo visual da tela Detalhe"
type: visual-comparison
module: Inventory
status: approved
date: 2026-05-15
canon_reference: prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx
blade_source: resources/views/product/show.blade.php
inertia_target: resources/js/Pages/Produto/Show.tsx
approved_by: pending_wagner_screenshot_approval
pattern_reuse: true
blueprint_cowork: prototipo-ui/prototipos/produto-cockpit/
---

# Comparativo visual — Detalhe do produto (`/products/{id}`)

> Page full-width derivada do drawer pattern Cowork (ADR 0149 screen-pattern reuse)

## Resumo executivo

Blade legacy tem layout simples mostrando rack details + atributos básicos. Cowork blueprint tem drawer lateral rico com Hero KPIs + 6 tabs (Resumo · Composição · Variações · Preços · Movimento · Fiscal). MWART entrega versão Page full (não drawer — tela inteira) reusando pattern visual: hero KPIs + tabs + cards.

## Tabela comparativa abreviada

| Aspecto | Blade | MWART (pattern reuse blueprint) |
|---|---|---|
| Layout | Linear scroll | Hero KPIs + Tabs + Cards |
| Hero | Imagem + nome simples | 4 KPIs: Estoque · Custo · Preço · Vendas no mês |
| Tabs | Ausente (tudo na mesma página) | Resumo · Composição · Variações · Preços · Movimento · Fiscal |
| Rack details | Tabela simples | Visualização gráfica de faixa (mín/máx) — Cowork pattern |
| Ações | Link "Editar" outline | "Editar" primary + "Histórico estoque" outline |

## Pattern reuse

Mesma família AppShellV2 + tokens. Diferença: Cowork blueprint é drawer (320px lateral) — MWART entrega Page full. ADR 0149 admite pois entidade + família visual + design system tokens são iguais.

## Gaps catalogados

| Gap | Impacto | Plano |
|---|---|---|
| Tabs dinâmicas com counter | UX rico | Wave 3 |
| Movimento timeline visual (in/out/adj) | Larissa entende fluxo | Wave 3 |
| Variation grid quando type=variable | Visual rápido stock por variação | Wave 3 |

## Aprovação

⏳ Pendente Wagner screenshot approval.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Comparativo criado em Wave 2 B4 Produto. |
