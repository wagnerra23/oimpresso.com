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

### US-ESTOQUE-001 · Transferência pela configuração da empresa: baixa automática ou requisição aprovada por permissão

> owner: wagner · priority: p2 · status: todo · type: story · origin: decisao-felipe-2026-10-15
> blocked_by: —

**Implementado em:** _pendente_ — não iniciada. Hoje quem preenche a transferência escolhe a situação (pendente, em trânsito ou concluída) num campo do formulário; não existe a configuração da empresa nem a tela da requisição.

Decisões de [F] em 2026-10-15. A tela da requisição e a configuração da empresa são do [W].

- Configuração da empresa "Baixa automática na transferência".
- Ligada: concluir a tela /stock-transfers/create já executa a transferência (saldo muda na hora).
- Desligada: a transferência nasce pendente na tela da requisição e não mexe no estoque; quem tem a permissão de aprovar decide.
- Responsável = permissão (ex.: aprovar transferência), não pessoa fixa.
- Recusar = situação nova "recusada", fica no histórico (não exclui).
- O campo situação sai do formulário de transferência.
- Depois disso: o "Transferir" do produto (painel e menu da linha da lista de produtos) abre a tela de transferência com produto e local de origem preenchidos.
- Estoque Tier 0: dupla confirmação do saldo (só muda na aprovação, nunca duas vezes) e antes→depois apresentado antes do merge.
- Detalhe: [`StockTransfer/Create.casos.md`](../../../resources/js/Pages/StockTransfer/Create.casos.md) e [`Index.casos.md`](../../../resources/js/Pages/StockTransfer/Index.casos.md), §Backlog.
