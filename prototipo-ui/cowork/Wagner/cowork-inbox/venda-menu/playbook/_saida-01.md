---
sessao: "01"
titulo: "Lista de POS → Sells/Pos/Index — saída da thread"
autor: "[CL]"
criado: 2026-10-01
base: 55cdb3dcf
thread: 01-telas-legadas.md §01
veredito: "entregue em 2 PRs (backend → tela, pelo PARAR SE de 300 linhas): #8488 endpoint de dados · #8490 tela. Prova da thread no #8490. Pest nasce sem run; nada mergeado."
---

# _saída 01 · Lista de POS

## O que saiu

| PR | conteúdo |
|---|---|
| [#8488](https://github.com/wagnerra23/oimpresso.com/pull/8488) | `SellController@inertiaList` ganha `is_direct_sale` opcional (whitelist 0/1, default inalterado). Com `0`: gate do legado (`sell.view` ou `sell.create`) e `permitted_locations()`. Teste `SellsPosIndexContratoTest` (UC-POS-01..05) na lane `sells-pest.yml`. |
| [#8490](https://github.com/wagnerra23/oimpresso.com/pull/8490) | `SellPosController@index` (só o `index`): branch dual, `Inertia::render('Sells/Pos/Index'` com `X-Inertia`, Blade no GET comum. Page PT-01 + charter + casos (textos de `ListaPos.*`) + `RUNBOOK-pos.md` + US-SELL-064 + stub E2E UC-06..08 + `SUPERFICIE.md`. Empilhado sobre o #8488. |

Seções do alvo (`vendas--pos--index.secoes.json`) com âncora `data-contract` na Page: `header` · `tabs` · `filtros` · `lista` · `toolbar` · `rodape`.

## Como foi verificado

- `tsc --noEmit` sem erro em `Sells/Pos` (baseline sem regressão); `vite build` do Inertia com a Page no manifest.
- `ds-guard`, `memory-schemas/validate.mjs`, `anchor-lint --check` e `--check-entry --check-covers`, `doneness-lint`, `charter-us-lint --check`, `anchor-content-check`, `casos-coverage-guard`, `screen-coverage-map --check`, `module-surface --all --check`: verdes, rodados depois de commitar.
- Pest não roda local: o veredito do teste é o da lane `sells-pest.yml` no CI.
- Não medido: o render da Page contra o alvo (`secao-check`) e o smoke em prod — exigem deploy.

## O que ficou fora (e por quê)

- Contagem por status e por forma no rodapé, e filtros de local, cliente, vendedor e tipo de serviço: o endpoint não os oferece; estender o payload é o próximo passo.
- Restrições `view_paid_sells_only`/`view_due_sells_only` do legado: o `inertiaList` não as aplica em modo nenhum.
- UC-POS-06..08 (drawer, recibo, devolução): só stub E2E `test.fixme`.
- Cutover F5 (GET comum continua no Blade): decisão humana.

## Achado fora do prefixo (não consertado)

O filtro de período do `Sells/Index` manda `date_to=YYYY-MM-DD` e o `inertiaList` compara `transaction_date <= 'YYYY-MM-DD'`, que exclui as vendas do próprio dia depois da meia-noite. A Lista de POS contorna mandando `23:59:59`; o `Sells/Index` não foi tocado.

## PARAR SE

- "PR passar de 300 linhas": disparou, e a entrega foi partida em backend → tela.
- "Endpoint novo": não disparou — só um parâmetro num endpoint existente.
- "Tela de valor sem teste do mesmo total": não se aplica; nenhum cálculo mudou e os totais esperados estão derivados à mão no teste.
