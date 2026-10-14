---
id: resources-js-pages-restaurante-cozinha-index-charter
page: /modules/kitchen
component: resources/js/Pages/Restaurante/Cozinha/Index.tsx
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
  derived_screens: [Cozinha]
  divergence_from_blueprint: "sem botão Detalhes (o detalhe da venda é modal parcial da Blade, não página); sem busca; a fila é a da Blade, só reordenada do mais antigo para o mais novo"
related_runbook: memory/requisitos/Restaurante/RUNBOOK-cozinha.md
---

# Page Charter — /modules/kitchen (DRAFT)

> **Status:** draft. Thread `restaurante/playbook/04` (Cozinha). `GET /modules/kitchen` responde Inertia só com a flag
> `useV2RestauranteCozinha` ligada para o negócio; desligada, a Blade `restaurant.kitchen.index` segue. Vira `live` no
> cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md). Alvo de design:
> `governance/design/targets/restaurante--cozinha--index.alvo.json` (thread A1). Contrato:
> `governance/design/contracts/restaurante-cozinha.contract.json`.

## Mission

Mostrar à cozinha o que falta preparar, do pedido mais antigo para o mais novo, e deixar marcar o pedido como preparado.

## Goals

- Cartão por pedido (#nota · espera · situação · feito às · cliente · mesa · local · itens por preparar).
- KPIs: pedidos na fila, itens por preparar (soma das quantidades) e pedidos esperando há 15 min ou mais.
- "Marcar como preparado" com confirmação, pelo endpoint da Blade; a fila recarrega depois.
- Botão Atualizar (recarrega só a fila) e abas para Mesas, Reservas e Pedidos derivadas do menu.

## Non-Goals

- ❌ Mudar o que `markAsCooked` grava, ou marcar item a item: a Blade marca o pedido inteiro.
- ❌ Tocar valor, pagamento ou estoque: a ação muda só `res_line_order_status`.
- ❌ Atualização ao vivo: a Blade e o protótipo têm o botão Atualizar (`_saida-P1`).

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só pedidos do negócio da sessão, nos locais permitidos | `RestaurantUtil::getAllOrders` |
| R2 | Fila = pedido de cozinha (`is_kitchen_order`) com algum item `received`/nulo | `KitchenController::FILTRO_FILA` |
| R3 | Situação derivada dos itens na ordem do `show_orders.blade.php` | `KitchenController::filaDaCozinha` |
| R4 | Com a flag desligada, ou com `?classico=1`, a rota devolve a Blade | `KitchenController::index` (`FLAG_V2`) |
| R5 | `sell.view` para abrir (o mesmo predicado do item de menu, US-GOV-059) | `KitchenController::index` |

## Refs

RUNBOOK: [`RUNBOOK-cozinha.md`](../../../../../memory/requisitos/Restaurante/RUNBOOK-cozinha.md) · ADR 0093 · ADR 0104 · ADR 0358.
