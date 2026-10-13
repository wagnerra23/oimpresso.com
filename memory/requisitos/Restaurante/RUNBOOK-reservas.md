---
slug: restaurante-runbook-reservas
title: "Restaurante — Runbook da tela Reservas"
type: runbook
module: Restaurante
tela: Restaurante/Reservas/Index
owner: W
status: ativo
last_validated: "2026-10-13"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Reservas (`/bookings`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `restaurante/playbook/03`.
> **Fonte de design:** alvo `governance/design/targets/restaurante--reservas--index.alvo.json` (thread A1), medido sobre `prototipo-ui/cowork/Wagner/restaurante-operacao.jsx` → `RestauranteOperacaoPage view="cfg-reservas"`.
> **Decisão de origem:** [W] 07/10 no formulário do SINCRONIZAR: *"Usamos — migrar para React"*.

## Estado final esperado

`GET /bookings` (`BookingController::index`) responde Inertia `Restaurante/Reservas/Index` **quando a flag
`useV2RestauranteReservas` está ligada** para o negócio. Desligada (default), ou com `?classico=1`, segue a Blade
`restaurant.booking.index`, com o calendário e os modais de criar, ver, mudar situação e excluir. O JSON do calendário
(`ajax()` sem `X-Inertia`) continua respondendo igual.

## 1. Objetivo

Listar as reservas do negócio (início · cliente · mesa · local · atendente · situação), no desenho do alvo da A1, sem
mudar o que a Blade grava.

## 2. Pré-condições

- Permissão `crud_all_bookings` **ou** `crud_own_bookings` (o controller exige uma das duas em toda ação).
- Módulo `booking` ligado no negócio (`enabled_modules`) para o item aparecer no menu.
- Lane `acessos-pest.yml` (MySQL) roda `tests/Feature/Restaurante/`.

## 3. Paridade com a Blade (o que existe hoje)

| Blade `restaurant.booking.index` | Fonte | Na tela React |
|---|---|---|
| Seletor de local (só com 2+ locais) | `BusinessLocation::forDropdown` | filtro de local |
| "Reservas de hoje" (DataTable: cliente · início · fim · mesa · local · atendente) | `getTodaysBookings` — só `booked` de hoje | KPI e linhas "hoje" na lista |
| Calendário (fullcalendar, mês) | `index` ajax → `RestaurantUtil::getBookingsForCalendar` | lista ordenada por início (o protótipo não tem calendário, `_saida-P1`) |
| Modal "Nova reserva", modal de detalhe (situação · excluir · notificar) | `store` · `show` · `update` · `destroy` | ficam na clássica (`?classico=1`) até o drawer |

## 4. Passo-a-passo

1. F2 — `ReservasBaselineTest` trava a Blade hoje: calendário só com reservas do negócio (98 × 99), 403 sem as duas
   permissões, e quem só tem `crud_own_bookings` vê só as reservas que criou.
2. F3 — `BookingController::index` ganha o ramo Inertia atrás da flag; a lista sai de uma prop deferida.
3. F3 — `Restaurante/Reservas/Index.tsx` + charter + casos + `ReservasContratoTest`.
4. F4 — smoke em biz=1 com a flag ligada (`flag:set useV2RestauranteReservas --biz=1`).
5. F5 — cutover (flag default ligada) é decisão [W].

## 5–8. Tokens · estados · responsividade · atalhos

Herdados do PT-01 e do `AppShellV2`. Estados: carregando (prop deferida), vazio (sem reserva), filtro sem resultado.

## 9. DoD checklist

- [ ] `ReservasBaselineTest` e `ReservasContratoTest` verdes na lane `acessos-pest.yml`, com assertions > 0.
- [ ] `secao-check --tela restaurante--reservas--index --servir-espelho` conforme.
- [ ] Smoke biz=1 com a flag ligada, screenshot.

## 10. Pegadinhas

- **Quem vê o quê.** Sem `crud_all_bookings` e sem o papel `Admin#<negócio>`, o calendário filtra por usuário. A
  query do `getBookingsForCalendar` aplica `created_by = usuário` **e** depois o `OR` de criador/correspondente/atendente
  — na prática, só as reservas que ele criou. A lista "de hoje" (`getTodaysBookings`) usa o `OR` sozinho e mostra mais.
  A tela React segue o calendário (a regra mais estreita); unificar é decisão [W].
- **Reserva sem cliente** some do calendário (`continue` no `getBookingsForCalendar`). A React faz o mesmo.
- **Inertia v3 manda `X-Requested-With`** em toda visita: o ramo do calendário exige `ajax() && ! inertia()`
  (§5 2026-09-08, molde `AccountController::index`).
- **Período.** O calendário pede um mês por vez. A React lista do primeiro dia do mês corrente em diante.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md).
