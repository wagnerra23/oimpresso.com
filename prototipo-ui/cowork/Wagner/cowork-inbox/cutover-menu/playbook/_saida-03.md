---
sessao: "_saida-03"
thread: "03 · Compras lista e nova — React como padrão"
dono: "[CL]"
data: 2026-10-09
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main fe17bb457
---
# _saida-03

## Entregue
Decisão D1 ([W] 2026-10-07): *"por tela: React vira padrão, Blade só com ?classico=1"*.
Molde `UnitController@index`.

- `PurchaseController@index` e `@create`: GET comum (o menu) abre `Purchase/Index` e
  `Purchase/Create`; `?classico=1` abre o Blade; `?v=2` (o opt-in antigo) continua abrindo o
  React; o DataTable do Blade (AJAX sem `X-Inertia`) segue com o JSON. O `index()` já decidia
  o Inertia antes do ramo AJAX (hot-fix pós-#601), então não havia o defeito da Thread 02.
- **Consequência consertada junto:** o botão de exportar do `Compras/Index` (módulo Compras)
  abria `/purchases` em outra aba pra usar os exports do DataTable do Blade. Com o React como
  padrão, abriria a lista React sem exports. Passa a abrir `/purchases?classico=1`.

## Provas
`tests/Feature/CutoverMenu/ComprasSemXInertiaTest.php` (5 casos, cita `UC-PURIDX-01` e
`UC-PURCRE-01`), ligado na lane `purchase-pest.yml` (allowlist + os dois filtros de path):
GET comum → Page; `?classico=1` → Blade; visita Inertia real → Page; `?v=2` → Page;
AJAX sem `X-Inertia` → JSON. Pest local é proibido: o veredito é a lane.

Testes de forma que fixavam a condição antiga, atualizados com nota datada:
`IndexPageTest.php` e `Wave2CreateInertiaTest.php`. O deste último passaria verde à toa,
porque o `edit()` manteve a condição antiga; agora conta a nova (2: index e create).
`Purchase/Index.casos.md` e `Create.casos.md` ganharam a atualização no UC-01.

## Para o [W] antes do merge (cutover)
- Vale para todas as empresas, incluindo a ROTA LIVRE (D1 = por tela).
- **A nova compra mexe em valor e estoque.** O charter de `Purchase/Create` diz
  *"aguarda smoke/canary Wagner"* e o `Create.casos.md` lista o UC-PURCRE-03 (`[T0]` `[V0]`)
  com defesa só estrutural. Com o merge, quem lança compra pelo menu passa a usar o React.
  O `store()` é o mesmo dos dois caminhos (não mudou nesta thread).
- `Edit` e `Show` de compra ficaram como estavam (fora da thread).
