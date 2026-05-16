---
slug: sells-drafts-visual-comparison
title: "Sells/Drafts — Visual Comparison F1.5 (MWART gate)"
type: visual-comparison
module: Sells
date: 2026-05-15
wave: W1-A
adr_pattern_reuse: 0149
---

# Visual Comparison — `/sells/drafts` (Drafts)

> **Pattern Reuse declarado:** ADR 0149. Drafts é tela DERIVADA de `Sells/Index` (mesma entidade, status=draft).

## Blueprint Cowork base

Reuso do Cockpit Pattern V2 do `Sells/Index.tsx`:
- AppShellV2
- PageHeader "Rascunhos"
- KPI single ou pair
- Tabela 5 cols (data + nº + cliente + itens + ações)
- Drawer `SaleSheet` lateral

## Divergence

| Item | Index (base) | Drafts |
|---|---|---|
| Filtros | 5 status pills | apenas filter customer/location |
| KPIs | 3 cards | 1 card (Total rascunhos) |
| Coluna total | exibe final_total | exibe total_items |
| Botão primário | "Nova venda" | "Continuar rascunho" no drawer |

## Refs

- [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- [Index.charter.md](../../../../resources/js/Pages/Sells/Index.charter.md)
