---
slug: compras-runbook-purchase-order-index
title: "Compras — Runbook da tela Pedidos de compra"
type: runbook
module: Compras
tela: PurchaseOrder/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Pedidos de compra (`/purchase-order`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `compras/playbook/06`
> **Fonte de design:** `prototipo-ui/cowork/Wagner/compras-extras.jsx` → `telaPedidos()` (rota `cmp-pedidos`, `window.ComprasExtrasPage`). Alvo medido: `governance/design/targets/compras--pedidos--index.alvo.json` (thread A2, #9195).
> **Não é tela de valor.** A lista da Blade não mostra o total do pedido (o controller seleciona `final_total`, mas ele não é coluna). "Quantidade restante" é quantidade, e não movimenta estoque: quem movimenta é a compra criada a partir do pedido.

## Estado final esperado

`GET /purchase-order` (`PurchaseOrderController::index`) responde Inertia `PurchaseOrder/Index` **quando a flag
`useV2ComprasPedidos` está ligada** para o negócio; desligada, ou com `?legacy=1`, segue a Blade `purchase_order/index`.
A página lista os pedidos com data, nº de referência, local, fornecedor, status, quantidade restante, status do envio e
quem adicionou, com os mesmos filtros da Blade (local, fornecedor, status, status do envio, período).

## 1. Objetivo

Trocar a Blade (DataTable + modais) pela tela React sem mudar o SQL da lista, o escopo por negócio, o filtro por local,
o "ver os próprios" nem as permissões.

## 2. Pré-condições

- Permissão `purchase_order.view_all` **ou** `purchase_order.view_own` (sem nenhuma das duas: 403).
- Pedido = `transactions.type = 'purchase_order'`; linhas em `purchase_lines` (`quantity`, `po_quantity_purchased`).
- Status do pedido: `ordered` · `partial` · `completed`. Status do envio é outro eixo: `ordered` · `packed` · `shipped` ·
  `delivered` · `cancelled` (`TransactionUtil::shipping_statuses()`).

## 3. Passo-a-passo

1. **F1/F2 (este PR):** RUNBOOK + [`purchase-order-parity.md`](./purchase-order-parity.md) + `PedidosCompraBaselineTest`
   na lane `purchase-pest.yml` (que passa a disparar também com `PurchaseOrderController.php`).
2. **F3:** `index()` ganha o ramo Inertia atrás da flag (`ajax() && ! inertia()` no DataTable). Page + charter + casos +
   contrato. A consulta da lista sai para um método único, usado pelo DataTable e pela tela.
3. **F4:** smoke biz=1 com a flag só para biz=1. **F5 (cutover):** decisão [W].

## 4–8. Tokens · estados · responsividade · atalhos · contrato

Só tokens do DS. Estados: lista com pedidos · filtro sem resultado · vazio. Tabela rola abaixo de 768px. `/` busca.
`data-contract`: `page-header`, `pedidos-kpis`, `pedidos-table`, `pedidos-filtros`.

## 9. DoD checklist

- [x] RUNBOOK + paridade · [x] Pest baseline (tenant 98 × 99; restante por dois caminhos)
- [ ] Ramo Inertia atrás da flag + Page + charter + casos + contrato (F3)
- [ ] Ações de escrita na tela nova (alterar status, editar envio, excluir) — PR próprio
- [ ] Smoke biz=1 (F4) · cutover e remoção da Blade `purchase_order/index` (F5, decisão [W])

## 10. Pegadinhas e divergências do protótipo

- **Inertia v3 manda `X-Requested-With`.** O ramo DataTable é `if (request()->ajax())`: sem `! request()->inertia()` ele
  engole a visita React.
- **`from_dashboard`.** O filtro do painel faz `where status != 'completed'` e depois `orHavingRaw('po_qty_remaining > 0')`.
  É comportamento da Blade; a tela nova não o usa e não o reescreve.
- **"Ver os próprios".** Com `view_own` sem `view_all`, a lista filtra `transactions.created_by` = usuário da sessão.
- **Filtro por local.** `permitted_locations()` lê as permissões **diretas** do usuário (`location.<id>`), não as do papel.
- **Status inline só para admin** e só fora de `completed` (`getEditPurchaseOrderStatus`). Envio editável por quem tem
  `access_shipping`/`access_own_shipping`/`access_commission_agent_shipping` ou é admin (`SellController::editShipping`).
- **Protótipo × Blade.** Os KPIs "Pedidos em aberto" e "Quantidade restante" são contagem e soma de quantidade; "Vindos de
  requisição" depende de `purchase_requisition_ids` e não está na Blade — entra só com decisão [W].

## 11. ADR de origem

[ADR 0104](../../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
