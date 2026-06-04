---
module: Estoque
title: "SPEC — Estoque (cross-cutting: saldo, reserva, movimentação)"
status: ativo
owner: wagner
related_adrs: [0093, 0129, 0192]
doc_raiz: ./DOC-RAIZ-ESTOQUE.md
---

# SPEC — Estoque

> Módulo **cross-cutting** (não é um `Modules/<X>`): governa o saldo de estoque
> (`variation_location_details.qty_available`), reserva (FSM `stock_reservations`) e toda
> movimentação (compra/venda/devolução/transferência/ajuste/opening/OS). A arquitetura,
> invariantes e riscos vivem no **[DOC-RAIZ-ESTOQUE.md](./DOC-RAIZ-ESTOQUE.md)** — leitura
> obrigatória antes de qualquer US deste SPEC.

## Invariantes (do doc raiz §7)

- INV-1 saldo só por caminho auditável · INV-2 rascunho não movimenta · INV-3 tudo em transação ·
  INV-4 reserva ≠ baixa · INV-5 `enable_stock=0` não movimenta · INV-6 tenant fixado por IDs globais.

## User Stories

<!-- US-ESTOQUE-* criadas via MCP tasks-create -->
