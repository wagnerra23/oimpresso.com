---
id: requisitos-estoque-spec
module: Estoque
title: "SPEC — Estoque (cross-cutting: saldo, reserva, movimentação)"
status: ativo
version: "1.0"
last_updated: "2026-06-04"
owner: wagner
owners: [W]
related_adrs:
  - "0093-multi-tenant-isolation-tier-0"
  - "0129-state-machine-canonica-fsm-rbac"
  - "0192-auto-faturar-os-venda-jobsheet-observer"
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

### US-ESTOQUE-001 · Toda transferência cai pendente na requisição e só move o estoque quando aprovada

> owner: wagner · priority: p2 · status: todo · type: story · origin: decisao-felipe-2026-10-15
> blocked_by: —

**Implementado em:** _pendente_ — não iniciada. Hoje quem preenche a transferência escolhe a situação (pendente, em trânsito ou concluída) num campo do formulário, e a concluída já move o saldo; não existe a tela da requisição.

Decisões de [F] em 2026-10-15. A tela da requisição é do [W].

- Toda transferência feita em /stock-transfers/create nasce **pendente** na tela da requisição e não mexe no estoque. **Não existe baixa automática** — não há configuração da empresa para pular a requisição.
- Na requisição, quem tem a permissão de aprovar decide: **aprovar** executa a transferência (o saldo sai da origem e entra no destino); **recusar** deixa a situação nova "recusada", que fica no histórico (não exclui).
- Responsável = permissão (ex.: aprovar transferência), não pessoa fixa.
- O campo situação sai do formulário de transferência.
- Depois disso: o "Transferir" do produto (painel e menu da linha da lista de produtos) abre a tela de transferência com produto e local de origem preenchidos.
- Estoque Tier 0: dupla confirmação do saldo (só muda na aprovação, nunca duas vezes) e antes→depois apresentado antes do merge.
- Detalhe: [`StockTransfer/Create.casos.md`](../../../resources/js/Pages/StockTransfer/Create.casos.md) e [`Index.casos.md`](../../../resources/js/Pages/StockTransfer/Index.casos.md), §Backlog.
