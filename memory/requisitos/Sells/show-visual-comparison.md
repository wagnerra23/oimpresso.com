---
slug: sells-show-visual-comparison
title: "Sells/Show — Visual Comparison F1.5 (MWART gate)"
type: visual-comparison
module: Sells
date: 2026-05-15
wave: W1-A
adr_pattern_reuse: 0149
---

# Visual Comparison — `/sells/{id}` (Show)

> **Pattern Reuse declarado:** ADR 0149 aplica — Show é tela DERIVADA de `Sells/Index` (mesma entidade `transactions`). Blueprint Cowork `prototipo-ui/prototipos/vendas-cockpit/` aprovado em F2 do ADR 0114 cobre Show.
> **Wagner aprovação SCREENSHOT:** referência `Vendas Cockpit.html` da pasta `vendas-cockpit/` + drawer `SaleSheet` pattern já aprovado no PR #261.

## Blueprint Cowork base

`prototipo-ui/prototipos/vendas-cockpit/Vendas Cockpit.html` + `visual-source-fsm-v1.html` — pattern detail view com:
- Cabeçalho dense `bg-card border-border rounded-lg` + nº da venda em h1 24px
- 4 KPI cards grandes (Total / Pago / Falta / Status pgto)
- Tabela de linhas zebra-strip leve + tipografia tabular-nums em valores
- Painel FSM lateral direito com action buttons (mesmo do SaleSheet)
- Timeline append-only (atividades) com avatar + relative time

## Divergence from blueprint

| Item | Blueprint (Cowork) | Show (alvo) | Justificativa |
|---|---|---|---|
| Layout | Drawer lateral 480px | Página full-width 2 colunas | Show é landing page (URL própria) — drawer só pra Index |
| Header | h1 SheetTitle 18px | h1 Page 24px | Hierarquia full-page demanda title maior |
| KPIs | 3 inline numeric | 4 KPI cards V2 | Padrão Index canon |
| FSM | painel inline drawer | sidebar 4/12 cols | Reuso `FsmActionPanel` shared sem mudanças |
| Timeline | seção colapsada | seção sticky direita | Acesso rápido ao histórico |

## Anti-padrões evitados

- ❌ Modal Bootstrap legacy (canon = página dedicada)
- ❌ DataTables jQuery legacy (canon = lista TanStack-like ou plain table dense)
- ❌ Cor crua `bg-blue-500`
- ❌ `font-bold` em h1
- ❌ AppShell sem V2
- ❌ Tabs `border-b-2` (canon = sem tabs, layout direto)

## Cutover smoke

Wagner valida em 1 venda real biz=1: confere visual + dados + ações FSM funcionam. ADR 0149 permite reuso sem novo Cowork loop F1.5.

## Refs

- [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- Blueprint Cowork: `prototipo-ui/prototipos/vendas-cockpit/`
- Index charter (parent visual): `resources/js/Pages/Sells/Index.charter.md`
