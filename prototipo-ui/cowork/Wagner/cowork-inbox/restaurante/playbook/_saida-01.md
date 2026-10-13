---
sessao: "_saida-01"
thread: "01 · Mesas (TableController@index) → Inertia"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "PR desta thread (claude/restaurante-01-mesas)"
base_lida: wagnerra23/oimpresso.com@main 8fe939db25
---
# _saida-01

## Entregue: Mesas em React, atrás da flag OFF
`GET /modules/tables` (`TableController::index`) responde Inertia `Restaurante/Mesas/Index` quando a flag
`useV2RestauranteMesas` está ligada para o negócio. Desligada (default, sem a chave no GrowthBook), ou com
`?classico=1`, segue a Blade `restaurant.table.index`, que hospeda os modais de cadastrar, editar e excluir. A
DataTable da Blade continua respondendo o `ajax()` sem `X-Inertia` (o ramo exige `ajax() && ! inertia()`, porque o
Inertia v3 manda `X-Requested-With` em toda visita — molde `AccountController::index`).

Prova da thread (`contem`): `app/Http/Controllers/Restaurant/TableController.php` contém `Inertia::render(`.

Forma: o alvo da A1, `governance/design/targets/restaurante--mesas--index.alvo.json` (PR da A1, #9256) — cabeçalho,
abas, aviso, KPIs e tabela.

## Processo MWART (ADR 0104)
| fase | o quê |
|---|---|
| F1 | `memory/requisitos/Restaurante/RUNBOOK-mesas.md` |
| F2 | `tests/Feature/Restaurante/MesasBaselineTest.php` trava a Blade de hoje: DataTable só com mesas do negócio (98 × 99), 403 sem `access_tables`, editar e excluir só para `Admin#<negócio>` |
| F3 | ramo Inertia no controller + `Index.tsx` + charter + casos (UC-MESA-01..04) + `MesasContratoTest.php` |
| F4 | smoke biz=1 com a flag ligada — pendente, depois do merge |
| F5 | cutover (flag default ligada) — decisão [W] |

Os dois testes rodam na lane `acessos-pest.yml` (MySQL), que ganhou o gatilho de `TableController`, `RestaurantUtil`,
`Pages/Restaurante/**` e `tests/Feature/Restaurante/**` e passou a executar `tests/Feature/Restaurante/`. Não rodei
Pest local (regra: só CT 100 ou CI); o veredito é o da lane no PR.

## Divergências do protótipo (registradas no charter)
- **Situação da mesa.** O protótipo deriva "ocupada" de um atendente *em atendimento* no local; o ERP não tem esse
  estado. A tela usa a regra que Cozinha e Pedidos já usam: `RestaurantUtil::getAllOrders()` (venda `final` ainda não
  `served`). Ocupada = tem pedido aberto; atendente = o do pedido aberto mais recente. Uma fonte só, sem regra nova.
- **Pedidos hoje** = vendas `final` do dia com mesa, do negócio.
- **Aba Atendentes** não vira link (o índice não lhe deu dono, `00-INDICE.md:238`). As outras abas (Reservas,
  Cozinha, Pedidos) vêm do `shell.menu`, então só aparece o que o usuário pode abrir.
- **Cadastrar/editar/excluir** ficam na clássica, e o link só aparece para `Admin#<negócio>` — o mesmo `@role` da
  DataTable.

## Gates locais
`casos-coverage-guard` sem violação nova · `screen-coverage-map --check` catraca ok (charter 296→297) ·
`pt-conformance --check` ok · `uc-id-lint` 0 fora do formato · `memory-schemas/validate.mjs` RUNBOOK e charter ok ·
`tsc` sem erro em `Restaurante/Mesas`.

## Fora deste PR
- Drawer de cadastro e edição (backlog nos casos).
- Excluir mesa com pedido aberto: o legado deixa; decidir se a tela nova impede é [W].
- Fora do prefixo, mas exigidos: o RUNBOOK (o hook `block-mwart-violation` não deixa escrever a Page sem ele) e a lane
  `acessos-pest.yml` (sem ela o teste não roda em PR nenhum).
