---
slug: sells-quotations-visual-comparison
title: "Sells/Quotations — Visual Comparison F1.5 (MWART gate)"
type: visual-comparison
module: Sells
date: 2026-05-15
wave: W1-A
adr_pattern_reuse: 0149
---

# Visual Comparison — `/sells/quotations` (Quotations)

> **Pattern Reuse declarado:** ADR 0149. Quotations é tela DERIVADA de `Sells/Drafts` (estrutura idêntica + `sub_status='quotation'`).

## Blueprint Cowork base

Reuso 100% do `Sells/Drafts.tsx` (lista compacta + drawer).

## Divergence

| Item | Drafts (base) | Quotations |
|---|---|---|
| Título | "Rascunhos" | "Cotações" |
| Filtro DB | `status='draft' + sub_status IS NULL` | `status='draft' + sub_status='quotation'` |
| Coluna extra | — | Validade (data limite) |
| Ações drawer | Editar/Excluir/Finalizar | + Enviar WhatsApp/email + Converter em venda |
| FSM | sem stage explícito | `quote_sent` → `quote_accepted`/`quote_rejected`/`quote_expired` |

## Refs

- [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- [RUNBOOK-drafts.md](RUNBOOK-drafts.md)
