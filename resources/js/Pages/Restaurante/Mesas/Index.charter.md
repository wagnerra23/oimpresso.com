---
id: resources-js-pages-restaurante-mesas-index-charter
page: /modules/tables
component: resources/js/Pages/Restaurante/Mesas/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/integra-extras.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Restaurante
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/integra-extras.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Mesas]
  divergence_from_blueprint: "situação derivada do pedido aberto (o ERP não tem atendente 'em atendimento'); sem aba Atendentes; cadastro e edição na tela clássica até o drawer"
related_runbook: memory/requisitos/Restaurante/RUNBOOK-mesas.md
---

# Page Charter — /modules/tables (DRAFT)

> **Status:** draft. Thread `restaurante/playbook/01` (Mesas). `GET /modules/tables` responde Inertia só com a flag
> `useV2RestauranteMesas` ligada para o negócio; desligada, a Blade `restaurant.table.index` segue. Vira `live` no
> cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md). Alvo de design:
> `governance/design/targets/restaurante--mesas--index.alvo.json` (thread A1).

## Mission

Mostrar as mesas do negócio e quais estão com pedido em aberto, no desenho do protótipo.

## Goals

- Lista das mesas (mesa · local · descrição · situação · atendente) com busca por nome ou local.
- Situação pela regra de pedido aberto que a Cozinha e os Pedidos já usam (`RestaurantUtil::getAllOrders`).
- KPIs: mesas ocupadas, livres e pedidos de hoje.
- Abas para Reservas, Cozinha e Pedidos derivadas do menu (só as que o usuário pode abrir).
- Tela clássica (`?classico=1`) alcançável para cadastrar, editar e excluir — só para quem o legado deixa (`Admin#`).

## Non-Goals

- ❌ Cadastrar, editar ou excluir mesa nesta tela antes do drawer.
- ❌ Guardar "ocupada" na mesa ou inventar estado de atendente: a situação vem do pedido.
- ❌ Aba Atendentes: o índice do playbook não lhe deu dono.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só mesas do negócio da sessão | `TableController::mesasDoNegocio` |
| R2 | Com a flag desligada, ou com `?classico=1`, a rota devolve a Blade | `TableController::index` (`FLAG_V2`) |
| R3 | Ocupada = pedido aberto (venda `final` ainda não `served`) nos locais permitidos | `RestaurantUtil::getAllOrders` |
| R4 | Gerenciar (link para a clássica) só com o papel `Admin#<negócio>`, como a DataTable | `TableController::index` |

## Refs

RUNBOOK: [`RUNBOOK-mesas.md`](../../../../../memory/requisitos/Restaurante/RUNBOOK-mesas.md) · ADR 0093 · ADR 0104 · ADR 0358.
