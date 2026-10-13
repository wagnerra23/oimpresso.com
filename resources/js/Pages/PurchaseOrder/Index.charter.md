---
id: resources-js-pages-purchaseorder-index-charter
page: /purchase-order
component: resources/js/Pages/PurchaseOrder/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/compras-extras.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Compras
related_adrs: [93, 104, 358]
tier: B
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/compras-extras.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [PedidosCompra]
  divergence_from_blueprint: "sem o KPI 'Vindos de requisição' (a Blade não tem); status inline, envio, excluir e notificação na tela clássica"
related_runbook: memory/requisitos/Compras/_telas/RUNBOOK-purchase-order-index.md
---

# Page Charter — /purchase-order (DRAFT)

> **Status:** draft. Thread `compras/playbook/06` (Pedidos de compra). `GET /purchase-order` responde Inertia só com a
> flag `useV2ComprasPedidos` ligada para o negócio; desligada, ou com `?legacy=1`, a Blade `purchase_order/index` segue.
> Vira `live` no cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md).
> Âncora de design: `compras-extras.jsx` → `telaPedidos()` (rota `cmp-pedidos`), alvo medido em
> `governance/design/targets/compras--pedidos--index.alvo.json` (thread A2).

## Mission

Mostrar ao comprador os pedidos de compra do negócio — de quem, para qual local, em que status, quanto ainda falta
comprar e em que pé está o envio — com os mesmos números e o mesmo escopo que a Blade mostra.

## Goals

- Lista dos pedidos (data, nº de referência, local, fornecedor, status, quantidade restante, status do envio, quem
  adicionou) com os filtros da Blade: local, fornecedor, status e status do envio.
- O mesmo SQL da Blade (`PurchaseOrderController::consultaPedidos`) e a quantidade restante formatada pela mesma
  expressão do `@format_quantity`.
- KPIs: pedidos em aberto (status diferente de concluído) e quantidade restante somada da lista.
- Ver o pedido, baixar o PDF, editar (com `purchase_order.update`) e criar (com `purchase_order.create`).

## Non-Goals

- ❌ Mostrar ou somar o valor do pedido na lista: a Blade não mostra; o valor vive no detalhe.
- ❌ Alterar status, editar envio, excluir ou enviar notificação nesta tela antes de PR próprio — seguem na tela
  clássica (`?legacy=1`).
- ❌ KPI "Vindos de requisição" do protótipo: depende de `purchase_requisition_ids` e não está na Blade (decisão [W]).

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só pedidos do negócio da sessão | `PurchaseOrderController::consultaPedidos` |
| R2 | Com a flag desligada, ou com `?legacy=1`, a rota devolve a Blade | `PurchaseOrderController::index` (`FLAG_V2`) |
| R3 | Sem `access_all_locations`, só pedidos dos locais permitidos | `consultaPedidos` (`permitted_locations`) |
| R4 | Com `view_own` sem `view_all`, só pedidos que o usuário criou | `consultaPedidos` |
| R5 | Restante = Σ(`quantity` − `po_quantity_purchased`), um só SQL para Blade e React | `consultaPedidos` |

## Refs

RUNBOOK: [`RUNBOOK-purchase-order-index.md`](../../../../memory/requisitos/Compras/_telas/RUNBOOK-purchase-order-index.md) · paridade: [`purchase-order-parity.md`](../../../../memory/requisitos/Compras/_telas/purchase-order-parity.md) · ADR 0093 · ADR 0104 · ADR 0358.
