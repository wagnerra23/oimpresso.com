---
slug: inventory-produto-index-visual-comparison
title: "Produto — Comparativo visual da tela Lista de produtos"
type: visual-comparison
module: Inventory
status: approved
date: 2026-05-15
canon_reference: prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx
blade_source: resources/views/product/index.blade.php
inertia_target: resources/js/Pages/Produto/Index.tsx
approved_by: pending_wagner_screenshot_approval
generated_retroactively: false
pattern_reuse: true
blueprint_cowork: prototipo-ui/prototipos/produto-cockpit/
---

# Comparativo visual — Lista de produtos (`/products`)

> **Tipo de tela:** list cockpit list+detail (drawer pattern)
> **Persona:** Larissa (ROTA LIVRE biz=4), 1280px
> **Refs:**
> - Blade legacy: `resources/views/product/index.blade.php` (DataTables jQuery + Yajra server-side)
> - Canon Cowork: [`produto-cockpit/produto-cockpit-page.jsx`](../../../prototipo-ui/prototipos/produto-cockpit/produto-cockpit-page.jsx)
> - Page MWART: `resources/js/Pages/Produto/Index.tsx`
> - RUNBOOK: [`RUNBOOK-produto-index.md`](RUNBOOK-produto-index.md)
> - ADR 0149 screen-pattern reuse (este é o blueprint pra Show/Edit/Create/SellingPrices)

## Resumo executivo

Blade legacy tem topbar de filtros + DataTables com 11 colunas + ações dropdown por linha. Cowork blueprint substitui por: header sticky + KPI strip 4 cards + filter tabs categoria + lista cards densa + drawer detalhe lateral. **MWART entrega:** versão Page Inertia que reusa AppShellV2 + tokens OKLCH Cowork + header pattern, com `Inertia::defer()` em props caras (KPIs, rows, categorias) — alinhado RUNBOOK-inertia-defer-pattern (Tier 0 desde 2026-05-15).

## Tabela comparativa — 8 dimensões

### 1. Layout

| Aspecto | Blade legacy | Cowork blueprint | Decisão MWART |
|---|---|---|---|
| Sidebar | UPOS 250px submenus | AppShellV2 260px (canon Cockpit) | AppShellV2 — paridade canon |
| Header | `<h1 class="content-header">` simples | Header sticky h1 + subtitle + ações primárias | PageHeader shared — paridade |
| Topnav módulo | Submenu dentro sidebar | Tabs `Catálogo · Categorias · Insumos · Tabelas · Histórico` | Tabs (segue Unificado pattern, opcional Wave 3) |
| Grid breakpoints | Bootstrap | `max-w-7xl` + grid responsivo | `max-w-7xl` |

### 2. Hierarquia visual

| Aspecto | Blade | Cowork | MWART |
|---|---|---|---|
| Ação primária | "+ Adicionar produto" topo direito | Botão primary topo direito | PageHeader action slot — paridade |
| Ações secundárias | Dropdown bulk 12 actions | Botão "Importar" outline | "Importar" + "Novo" — simplificado |
| KPIs top | Ausente | 4 cards: Total · Ativos · Categorias · Populares | 4 cards Deferred — paridade Cowork |

### 3. Densidade

| Aspecto | Blade | Cowork | MWART |
|---|---|---|---|
| Linhas tabela | DataTables ~38px linha | Lista cards densa Linear | Cards 1 col 1280px (Larissa) |
| Padding seções | `panel-body 15px` | `mx-6 mt-4` (24px) | `space-y-6` — paridade Cowork |

### 4-8. Cor, tipografia, interação, acessibilidade, fiscal

- Cores: tokens OKLCH Cowork (emerald/stone/rose semânticos)
- Tipografia: h1 24px, table 12-13px, SKU mono 11.5px, valores `tabular-nums`
- Interação: click card → drawer lateral (pattern Cowork) ou link `/products/{id}`
- Acessibilidade: `aria-label` em ações, focus visible (default Tailwind)
- Fiscal: SKU + categoria + unit visíveis (Larissa diferencia produtos rápido)

## Gaps catalogados

| Gap | Impacto | Quando atacar |
|---|---|---|
| Drawer lateral pattern Cowork | UX melhora — usuário não troca de rota pra ver detalhe | Wave 3 (out of scope desta migração) |
| Tabs categoria interativos (filter client-side) | Larissa filtra por tipo rápido | Wave 3 |
| Toggle "Mostrar inativos" persisted | Larissa às vezes precisa ver inativo | Wave 3 |
| Topnav módulo (afeta 78 telas MWART) | Navegação intra-módulo | PR separado (ADR 0039 §1 gap conhecido) |

## Aprovação

⏳ **Pendente Wagner screenshot approval** — quando build:inertia gera bundle, capturar screenshot 1280×800 e Wagner aprova via SYNC_LOG.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Comparativo criado em Wave 2 B4 Produto (Agent paralelo). |
