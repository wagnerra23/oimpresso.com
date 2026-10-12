---
sessao: "_saida-04"
thread: "04 · Transferências e ajustes — React como padrão"
dono: "[CL]"
data: 2026-10-09
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main fe17bb457
---
# _saida-04

## Entregue
Decisão D1 ([W] 2026-10-07): *"por tela: React vira padrão, Blade só com ?classico=1"*.
Molde `UnitController@index`.

- `StockTransferController` e `StockAdjustmentController`, `index` e `create`: GET comum
  (o menu) abre a Page React; `?classico=1` abre o Blade; `?v=2` continua abrindo o React; o
  DataTable do Blade (AJAX sem `X-Inertia`) segue com o JSON.
- **Defeito consertado junto (as duas listas).** O ramo `ajax()` vinha antes do `X-Inertia`,
  e o Inertia manda `X-Requested-With` junto (§5 2026-09-08). O filtro da Page (`router.get`)
  recebia o JSON do DataTable. Estava anotado como `[BACKLOG]` nos dois `Index.casos.md`;
  ganharam a nota de conserto datada.

## Provas
`tests/Feature/CutoverMenu/EstoqueSemXInertiaTest.php` (5 casos sobre as 4 telas), ligado na
lane `estoque-pest.yml` (os dois filtros de path + `echo ... >> /tmp/run.txt`): GET comum →
Page; `?classico=1` → Blade; visita Inertia real → Page; `?v=2` → Page; AJAX sem `X-Inertia`
nas duas listas → JSON. Pest local é proibido: o veredito é a lane.

## Para o [W] antes do merge (cutover)
- Vale para todas as empresas, incluindo a ROTA LIVRE (D1 = por tela).
- **Novo ajuste e nova transferência mexem em estoque.** O `store()` é o mesmo dos dois
  caminhos e não mudou aqui.
- **O botão "Ver" das duas listas React, lido no código (não medido em tela):** chama
  `router.visit('/stock-transfers/{id}')` / `'/stock-adjustments/{id}'`. O `show()` devolve
  `stock_transfer.show` / `stock_adjustment.show`, que são **fragmentos de modal** do Blade
  (`<div class="modal-dialog">`), não uma Page Inertia. Com o React como padrão, quem clicar em
  "Ver" deve receber o erro de resposta não-Inertia. Não existe Page `Show` dessas telas; criar
  uma está fora do prefixo desta thread. Pede thread nova (ou "Ver" abrir o `?classico=1`).
- `Edit` e `Show` ficaram como estavam.
