---
id: modules-crm-pages-crm-pedidos-index-charter
page: /crm/order-request
component: Modules/Crm/Resources/js/Pages/Crm/Pedidos/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Crm
related_adrs: [93, 104, 358]
tier: B
charter_version: 1
---

# Page Charter — /crm/order-request (DRAFT · pedidos de clientes)

> **Status:** draft, atrás da flag `useV2CrmPedidos` (OFF). Nasce na thread Crm/17
> (`OrderRequestController@listOrderRequests`, Blade → Inertia). Casos: [`Index.casos.md`](Index.casos.md) ·
> contrato: `governance/design/contracts/crm-pedidos.contract.json` (derivado do alvo `crm--pedidos--index` da A2) ·
> plano: `memory/requisitos/Crm/RUNBOOK-pedidos.md`.
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar à equipe os **pedidos que os clientes fizeram pelo portal do contato**: data, número,
contato, local, status, quantidade restante e total — e a soma do que está filtrado.

## Goals — Features (faz, nesta fase)

- Lista paginada (25) com as 7 colunas do protótipo e rodapé "Total dos pedidos filtrados".
- Filtros da Blade com os mesmos parâmetros: local do negócio, cliente, status, intervalo de datas.
  Mais busca por número ou contato.
- Escopo **igual ao da Blade**: a consulta que ela pede ao `SellController@index`
  (`getListSells('sales_order')` + `crm_is_order_request`), com locais permitidos e "só os meus".

## Non-Goals (nesta fase)

- ❌ Escrever. Mudar o status do pedido segue na Blade (`?classico=1`).
- ❌ "Adicionar solicitação de pedido" do protótipo: é ação do portal, não da equipe.
- ❌ A lista do portal (`/contact/order-request`): fica na Blade.

## Anti-hooks

- ❌ Calcular, arredondar ou somar valor no navegador. O total de cada pedido e a soma vêm do
  servidor; a tela só formata. Guarda: UC-CRMPED-04.
- ❌ Somar sobre o join cru com as linhas de venda (duplica o total). Guarda: UC-CRMPED-04.
- ❌ Listar pedido de outro negócio ou pedido de venda interno. Guarda: UC-CRMPED-02.
