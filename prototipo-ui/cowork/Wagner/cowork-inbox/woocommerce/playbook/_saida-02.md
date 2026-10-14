---
sessao: "_saida-02"
thread: "02 · Configuração da API + log de sincronização (apiSettings · viewSyncLog) → Inertia"
dono: "[CL]"
data: 2026-10-14
tipo: recibo
entregue_em: "PRs desta thread: F1 #9349 · F2 #9350 · F3 (claude/woocommerce-02-f3-telas)"
base_lida: wagnerra23/oimpresso.com@main 62cb42b3d7
---
# _saida-02

## Entregue: Configuração e Log em React, atrás da flag OFF
- `GET /woocommerce/api-settings` (`WoocommerceController::apiSettings`) → Inertia `Woocommerce/Configuracao/Index`.
- `GET /woocommerce/view-sync-log` (`WoocommerceController::viewSyncLog`) → Inertia `Woocommerce/Log/Index`.

As duas atrás da **mesma flag do painel**, `useV2WoocommercePainel`: as três telas do módulo viram juntas. Desligada
(default), ou com `?classico=1`, seguem as Blades `woocommerce.api_settings` e `woocommerce.sync_log`. Com isso as três
views Blade da thread (index · api_settings · sync_log) têm par Inertia (nota de provas do índice).

Forma: os alvos da A1, `woocommerce--api--index` e `woocommerce--log--index` (#9257). Pages em
`Modules/Woocommerce/Resources/js/Pages/Woocommerce/{Configuracao,Log}/`. As abas viraram um componente só
(`_components/WoocommerceAbas.tsx`), usado também pelo painel.

## Tier 0 — segredos
- Nenhum dos 6 segredos (consumer key, consumer secret, 4 de webhook) vai em prop, nem com parte do valor: a tela
  recebe só se cada um está preenchido. A Blade entregava a key e o secret em claro no HTML.
- Salvar: a tela envia `manter_segredos=1`; com ele, segredo em branco mantém o salvo e o marcador não entra no JSON.
  Sem ele (Blade), em branco continua apagando — travado no `ConfiguracaoBaselineTest`.
- Nada de segredo em log (o `updateSettings` só loga a exceção, como antes).
- Mordida provada no CT 100: incluir a key nos campos visíveis derruba UC-WOOC-03; desligar o marcador derruba
  UC-WOOC-04. Cada mutação casou 1 vez e o controller voltou com o mesmo md5.

## Log
- A regra de tipos por permissão saiu do `viewSyncLog()` para `tiposDeLogPermitidos()`: DataTables e tela usam a mesma.
  UC-WOOL-03 confere o conjunto pelos dois caminhos.
- O ramo React se decide por `X-Inertia`, antes do `ajax()`: o Inertia manda `X-Requested-With` em toda visita, e o
  DataTables da Blade não manda `X-Inertia` (UC-WOOL-02 cobre o AJAX com a flag ligada).

## Processo MWART (ADR 0104)
| fase | o quê | PR |
|---|---|---|
| F1 | `RUNBOOK-configuracao.md` · `RUNBOOK-log.md` | #9349 |
| F2 | `ConfiguracaoBaselineTest` · `LogBaselineTest` | #9350 |
| F3 | controller + 2 Pages + charters + casos (UC-WOOC-01..05, UC-WOOL-01..04) + contratos | este |
| F4 | smoke biz=1 com a flag ligada — pendente, depois do merge | — |
| F5 | cutover — decisão [W] | — |

Testes na lane `acessos-pest.yml` (MySQL). CT 100, numa cópia de `origin/main` em `/tmp` (o checkout do staging é de
21/09): `19 passed (93 assertions)` — os 4 arquivos novos mais os 2 do painel.

## Divergências do protótipo (nos charters)
- Configuração aceita troca das chaves (a tela é do administrador); o protótipo só mostra "ficam no servidor".
- Sincronização automática só liga/desliga (o ERP não guarda frequência). Sem "Testar conexão" (a tela não chama a loja).
- Log: a busca filtra a página; situação por execução sai dos erros registrados.
