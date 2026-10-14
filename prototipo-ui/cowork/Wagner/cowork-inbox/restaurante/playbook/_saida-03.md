---
sessao: "_saida-03"
thread: "03 · Reservas (BookingController@index) → Inertia"
dono: "[CL]"
data: 2026-10-14
tipo: recibo
entregue_em: "PRs #9288, #9290, #9296 (mergeados em main, 13 e 14/10)"
base_lida: wagnerra23/oimpresso.com@main 62cb42b3d7
---
# _saida-03

> Recibo escrito depois da entrega, pela sessão que fecha a contabilidade do placar, a partir do git e do CI.
> As sessões que executaram a thread em 13/10 não deixaram o `_saida`. Cada linha abaixo aponta um PR ou um run.

## Entregue: Reservas em React, atrás da flag OFF
`Restaurant/BookingController` responde Inertia `Restaurante/Reservas/Index` quando a flag `useV2RestauranteReservas` está ligada para o negócio. Desligada (default),
segue a Blade. Ligar é o cutover (F5), decisão [W].

## PRs
| fase | PR |
|---|---|
| F1 RUNBOOK com a paridade da Blade | #9288 |
| F2 baseline da Blade | #9290 |
| F3 tela React + charter + casos + contrato | #9296 |

## Testes (pelo nome, no último run de main que os executou)
Lane acessos-pest.yml run 38519689721 (push em main @19db7ee36e): 461 passed · 2 todos · 2562 assertions.

- `ReservasBaselineTest`: PASS
- `ReservasContratoTest`: PASS

Não rodei Pest local (regra: CT 100 ou CI).

## Pendente
- F4: smoke no biz=1 com a flag ligada. Não feito.
- F5: ligar a flag para as empresas. Decisão [W] (handoff `2026-10-14-0300-gestao-fila-merges-13-out-3.md`, pendência 1).
