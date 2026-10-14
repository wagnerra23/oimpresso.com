---
id: resources-js-pages-restaurante-reservas-index-charter
page: /bookings
component: resources/js/Pages/Restaurante/Reservas/Index.tsx
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
  derived_screens: [Reservas]
  divergence_from_blueprint: "sem drawers (criar, ver, mudar situação e excluir seguem na tela clássica); sem alternância Confortável/Compacto; filtro de local da Blade mantido"
related_runbook: memory/requisitos/Restaurante/RUNBOOK-reservas.md
---

# Page Charter — /bookings (DRAFT)

> **Status:** draft. Thread `restaurante/playbook/03` (Reservas). `GET /bookings` responde Inertia só com a flag
> `useV2RestauranteReservas` ligada para o negócio; desligada, a Blade `restaurant.booking.index` segue. Vira `live` no
> cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md). Alvo de design:
> `governance/design/targets/restaurante--reservas--index.alvo.json` (thread A1). Contrato:
> `governance/design/contracts/restaurante-reservas.contract.json`.

## Mission

Mostrar as reservas do negócio, da mais próxima para a mais distante, no desenho do protótipo.

## Goals

- Lista (início · cliente · mesa · local · atendente · situação) do primeiro dia do mês corrente em diante.
- Filtro por situação (em aberto · concluídas · canceladas · todas) e por local quando o negócio tem mais de um.
- KPIs: em aberto de hoje em diante, aguardando confirmação, concluídas.
- Abas para Mesas, Cozinha e Pedidos derivadas do menu (só as que o usuário pode abrir).
- Tela clássica (`?classico=1`) alcançável para criar, ver, mudar situação e excluir.

## Non-Goals

- ❌ Criar, editar, mudar situação ou excluir reserva nesta tela antes do drawer.
- ❌ Calendário: o protótipo trocou por lista (`_saida-P1`); voltar ao calendário é Tweak [W].
- ❌ Ampliar quem vê o quê: a regra é a do calendário da Blade (RUNBOOK §10).

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só reservas do negócio da sessão | `BookingController::reservasDoNegocio` |
| R2 | Com a flag desligada, ou com `?classico=1`, a rota devolve a Blade | `BookingController::index` (`FLAG_V2`) |
| R3 | Sem `crud_all_bookings` e sem `Admin#<negócio>`, só as reservas que o usuário criou | `BookingController::index` (`$user_id`) |
| R4 | Reserva sem cliente não aparece, como no calendário | `reservasDoNegocio` (`has('customer')`) |

## Refs

RUNBOOK: [`RUNBOOK-reservas.md`](../../../../../memory/requisitos/Restaurante/RUNBOOK-reservas.md) · ADR 0093 · ADR 0104 · ADR 0358.
