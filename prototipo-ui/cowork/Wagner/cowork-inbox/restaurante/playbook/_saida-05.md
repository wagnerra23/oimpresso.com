---
sessao: "_saida-05"
thread: "05 · Pedidos (OrderController@index) → Inertia"
dono: "[CL]"
data: 2026-10-14
tipo: recibo
entregue_em: "PRs #9308, #9309, #9313, #9325 (mergeados em main, 13 e 14/10)"
base_lida: wagnerra23/oimpresso.com@main 62cb42b3d7
---
# _saida-05

> Recibo escrito depois da entrega, pela sessão que fecha a contabilidade do placar, a partir do git e do CI.
> As sessões que executaram a thread em 13/10 não deixaram o `_saida`. Cada linha abaixo aponta um PR ou um run.

## Entregue: Pedidos em React, atrás da flag OFF
`Restaurant/OrderController` responde Inertia `Restaurante/Pedidos/Index` quando a flag `useV2RestaurantePedidos` está ligada para o negócio. Desligada (default),
segue a Blade. Ligar é o cutover (F5), decisão [W].

## PRs
| fase | PR |
|---|---|
| F1 RUNBOOK com a paridade da Blade | #9308 |
| F2 baseline da Blade | #9309 |
| F3 tela React + charter + casos + contrato | #9313 |
| Tier 0: item servido escopado ao negócio da sessão | #9325 |

## Testes (pelo nome, no último run de main que os executou)
Lane acessos-pest.yml run 38519689721 (push em main @19db7ee36e): 461 passed · 2 todos · 2562 assertions.

- `PedidosBaselineTest`: PASS
- `PedidosContratoTest`: PASS
- `PedidosItemServidoIsolamentoTest`: PASS

Não rodei Pest local (regra: CT 100 ou CI).

## Pendente
- F4: smoke no biz=1 com a flag ligada. Não feito.
- F5: ligar a flag para as empresas. Decisão [W] (handoff `2026-10-14-0300-gestao-fila-merges-13-out-3.md`, pendência 1).
