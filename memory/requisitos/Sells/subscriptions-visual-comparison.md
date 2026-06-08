---
slug: sells-subscriptions-visual-comparison
title: "Sells/Subscriptions — Visual Comparison F1.5 (MWART gate)"
type: visual-comparison
module: Sells
date: 2026-05-15
wave: W1-A
adr_pattern_reuse: 0149
---

# Visual Comparison — `/sells/subscriptions` (Subscriptions)

> **Pattern Reuse declarado:** ADR 0149. Subscriptions é tela DERIVADA de `Sells/Index` (mesma entidade + `is_recurring=1`).

## Blueprint Cowork base

Cockpit V2 do `Sells/Index` + cols específicas recurring.

## Divergence

| Item | Index (base) | Subscriptions |
|---|---|---|
| Título | "Vendas" | "Assinaturas" |
| KPIs | total/pago/atrasado | total/ativas/pausadas |
| Filtro DB | `status='final' + sub_type IS NULL` | `status='final' + is_recurring=1` |
| Cols extras | — | Intervalo + Próxima fatura + Faturas geradas + Toggle |
| Toggle inline | — | start/stop por linha (`recur_stopped_on`) |
| Drawer | `SaleSheet` | `SaleSheet` + bloco subscription_invoices |

## Refs

- [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- [RUNBOOK-subscriptions.md](RUNBOOK-subscriptions.md)
