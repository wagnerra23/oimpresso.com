---
slug: restaurante-runbook-pedidos
title: "Restaurante — Runbook da tela Pedidos"
type: runbook
module: Restaurante
tela: Restaurante/Pedidos/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Pedidos (`/modules/orders`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `restaurante/playbook/05`.
> **Fonte de design:** alvo `governance/design/targets/restaurante--pedidos--index.alvo.json` (thread A1), medido sobre `prototipo-ui/cowork/Wagner/restaurante-operacao.jsx` → `RestauranteOperacaoPage view="cfg-pedidos-rest"`.
> **Decisão de origem:** [W] 07/10 no formulário do SINCRONIZAR: *"Usamos — migrar para React"*.

## Estado final esperado

`GET /modules/orders` (`OrderController::index`) responde Inertia `Restaurante/Pedidos/Index` **quando a flag
`useV2RestaurantePedidos` está ligada** para o negócio. Desligada (default), ou com `?classico=1`, segue a Blade
`restaurant.orders.index`. Os dois botões de servir chamam os mesmos endpoints
(`GET /modules/orders/mark-as-served/{id}` e `GET /modules/orders/mark-line-order-as-served/{id}`).

## 1. Objetivo

Mostrar ao atendente os itens e os pedidos dele ainda não servidos, no desenho do alvo da A1, e marcar como servido
pelos endpoints que já existem.

## 2. Pré-condições

- **Nenhuma permissão é exigida hoje**: o gate de `OrderController::index` está comentado desde o legado. A tela React
  mantém (mudar é decisão [W]); quem chega é quem o item de menu deixa ver.
- Módulo `service_staff` ligado no negócio para o item aparecer no menu.
- Lane `acessos-pest.yml` (MySQL) roda `tests/Feature/Restaurante/`.

## 3. Paridade com a Blade (o que existe hoje)

| Blade `restaurant.orders.index` | Fonte | Na tela React |
|---|---|---|
| Atendente (papel `is_service_staff`) vê só os dele; outro usuário escolhe o atendente num seletor | `is_service_staff` · `service_staff_dropdown` · `?service_staff=` | o mesmo: seletor só para quem não é atendente |
| "Pedidos por item" (`line_orders`): item do atendente ainda não servido | `getLineOrders(['waiter_id'])` — filtra `res_service_staff_id` | lista de itens por servir, com a situação de cada um |
| "Todos os seus pedidos" | `getAllOrders(['waiter_id'])` — filtra `res_waiter_id` | cartões de pedido com situação derivada dos itens |
| Marcar pedido como servido / item como servido | `markAsServed` · `markLineOrderAsServed` | mesmos endpoints, recarrega |
| Imprimir item | `printLineOrder` (HTML) | fica na clássica |

## 4. Passo-a-passo

1. F2 — `PedidosBaselineTest` trava a Blade e os endpoints hoje: atendente vê só os dele, seletor para quem não é
   atendente, pedidos só do negócio (98 × 99), `markAsServed` não toca pedido de outro negócio.
2. F3 — `OrderController::index` ganha o ramo Inertia atrás da flag; itens e pedidos saem de props deferidas.
3. F3 — `Restaurante/Pedidos/Index.tsx` + charter + casos + `PedidosContratoTest`.
4. F4 — smoke em biz=1 com a flag ligada (`flag:set useV2RestaurantePedidos --biz=1`).
5. F5 — cutover (flag default ligada) é decisão [W].

## 5–8. Tokens · estados · responsividade · atalhos

Herdados do `AppShellV2`. Estados: carregando (props deferidas), sem atendente escolhido, nada por servir.

## 9. DoD checklist

- [ ] `PedidosBaselineTest` e `PedidosContratoTest` verdes na lane `acessos-pest.yml`, com assertions > 0.
- [ ] `secao-check --tela restaurante--pedidos--index --servir-espelho` conforme.
- [ ] Smoke biz=1 com a flag ligada, screenshot.

## 10. Pegadinhas

- **Estado de pedido, não valor nem estoque.** Os dois endpoints mudam só `res_line_order_status` para `served`. A regra
  mestre de valor/estoque não se aplica; a tela React não muda esses endpoints.
- **⚠️ Tier 0 aberto, achado nesta leitura — fora do escopo da thread.** `markLineOrderAsServed($id)` busca
  `TransactionSellLine::where('id', $id)` **sem filtro de negócio**: um usuário logado de qualquer empresa marca como
  servido o item de outra, por GET, sabendo o id. Os irmãos (`markAsServed`, `markAsCooked`) filtram por `t.business_id`.
  A tela React chama o mesmo endpoint e herda o furo; consertar é PR próprio, com teste cross-tenant que reprove hoje.
- **Item × pedido.** O protótipo mostra em "Itens prontos para servir" só itens já preparados. A Blade mostra todo item
  do atendente ainda não servido, preparado ou não. A React mostra o que a Blade mostra (comportamento) e diz a situação
  de cada item; filtrar só os preparados é decisão [W].
- **Dois donos do "atendente".** O item filtra por `res_service_staff_id` (linha), o pedido por `res_waiter_id` (venda).
- **`is_service_staff` olha o primeiro papel** do usuário (`roles->first()`): quem tem dois papéis depende da ordem.
- **Locais permitidos.** As duas consultas filtram por `permitted_locations()` — a React herda.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md).
