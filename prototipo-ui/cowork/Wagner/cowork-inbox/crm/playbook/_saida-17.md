---
sessao: "_saida-17"
thread: "17 · Pedidos de clientes (OrderRequestController) → Inertia"
dono: "[CL]"
data: 2026-10-14
tipo: recibo
entregue_em: "PRs #9304, #9306, #9312 (mergeados em main, 13 e 14/10)"
base_lida: wagnerra23/oimpresso.com@main 62cb42b3d7
---
# _saida-17

> Recibo escrito depois da entrega, pela sessão que fecha a contabilidade do placar, a partir do git e do CI.
> As sessões que executaram a thread em 13/10 não deixaram o `_saida`. Cada linha abaixo aponta um PR ou um run.

## Entregue: Pedidos em React, atrás da flag OFF
`OrderRequestController` responde Inertia `Crm/Pedidos/Index` quando a flag `useV2CrmPedidos` está ligada para o negócio. Desligada (default),
segue a Blade. Ligar é o cutover (F5), decisão [W].

## PRs
| fase | PR |
|---|---|
| F1 RUNBOOK + paridade | #9304 |
| F2 baseline da Blade | #9306 |
| F3 tela React + charter + casos + contrato | #9312 |

## Testes (pelo nome, no último run de main que os executou)
Lane verticais-pest.yml run 38518244616 (push em main @d68c0219b2): 372 passed · 9 skipped · 2088 assertions.

- `CrmPedidosBaselineTest`: PASS
- `CrmPedidosContratoTest`: PASS

Não rodei Pest local (regra: CT 100 ou CI).

## Pendente
- F4: smoke no biz=1 com a flag ligada. Não feito.
- F5: ligar a flag para as empresas. Decisão [W] (handoff `2026-10-14-0300-gestao-fila-merges-13-out-3.md`, pendência 1).
