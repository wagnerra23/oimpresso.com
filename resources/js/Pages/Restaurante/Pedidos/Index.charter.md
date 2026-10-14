---
id: resources-js-pages-restaurante-pedidos-index-charter
page: /modules/orders
component: resources/js/Pages/Restaurante/Pedidos/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/restaurante-operacao.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Restaurante
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/restaurante-operacao.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Pedidos]
  divergence_from_blueprint: "itens por servir = todo item não servido do atendente (a Blade), não só os preparados; sem 'Todos os atendentes' (a Blade exige escolher); sem Detalhes e sem imprimir item (ficam na clássica)"
related_runbook: memory/requisitos/Restaurante/RUNBOOK-pedidos.md
---

# Page Charter — /modules/orders (DRAFT)

> **Status:** draft. Thread `restaurante/playbook/05` (Pedidos). `GET /modules/orders` responde Inertia só com a flag
> `useV2RestaurantePedidos` ligada para o negócio; desligada, a Blade `restaurant.orders.index` segue. Vira `live` no
> cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md). Alvo de design:
> `governance/design/targets/restaurante--pedidos--index.alvo.json` (thread A1). Contrato:
> `governance/design/contracts/restaurante-pedidos.contract.json`.

## Mission

Mostrar ao atendente o que ele ainda tem a servir — item a item e pedido a pedido — e deixar marcar como servido.

## Goals

- Atendente vê os dele; quem não é atendente escolhe o atendente num seletor, como na Blade.
- "Itens por servir": item do atendente ainda não servido, com a situação de cada um.
- "Todos os seus pedidos": cartão por pedido com a situação derivada dos itens (`RestaurantUtil::situacaoDoPedido`).
- Marcar item ou pedido como servido, com confirmação, pelos endpoints da Blade; Atualizar recarrega só as listas.

## Non-Goals

- ❌ Mudar o que `markAsServed`/`markLineOrderAsServed` gravam, ou tocar valor, pagamento ou estoque.
- ❌ Filtrar "prontos" só pelos itens preparados sem decisão [W] (o protótipo filtra; a Blade não).
- ❌ Criar gate de permissão: o controller não tem hoje (decisão [W]).

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só pedidos e itens do negócio da sessão, nos locais permitidos | `RestaurantUtil::getAllOrders` · `getLineOrders` |
| R2 | Atendente (`is_service_staff`) vê os dele; outro usuário vê os do atendente escolhido, e nada sem escolher | `OrderController::index` |
| R3 | Item filtra por `res_service_staff_id`; pedido por `res_waiter_id` | `RestaurantUtil` |
| R4 | Com a flag desligada, ou com `?classico=1`, a rota devolve a Blade | `OrderController::index` (`FLAG_V2`) |
| R5 | Pedido marcado como servido continua na lista, com situação "servido": nada grava `res_order_status` | `OrderController::markAsServed` |

## Refs

RUNBOOK: [`RUNBOOK-pedidos.md`](../../../../../memory/requisitos/Restaurante/RUNBOOK-pedidos.md) · ADR 0093 · ADR 0104 · ADR 0358.
