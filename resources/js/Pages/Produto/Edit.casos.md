---
id: resources-js-pages-produto-edit-casos
casos: Editar produto · /products/{id}/edit
irmaos: Edit.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — editar o cadastro não pode perder o que a tela velha (Blade + Delphi) preservava.
owner: wagner
last_run: "2026-10-26"
last_run_ci: "PR #9747 (aviso de SKU que não vira código EAN, UC-PEDIT-15), 2026-10-26, head 9918672 com o #9744 já no main: job Frontend / Vite build 121560485044: 114 testes passaram em 14 arquivos, 37 deles em produto-formulario.test.tsx. A tela Edit.tsx é a mesma, byte a byte, que o #9744 validou em 21/10; a data mudou só pela entrada dele no main. Antes: PR #9744 (natureza fiscal no Editar, UC-PEDIT-14), 2026-10-21: lane PHP / Pest (Estoque · MySQL), job 119451939591: 533 passed (2508 assertions), com os 4 casos do UC-PEDIT-14 verdes; job Frontend / Vite build 119452570399: 105 testes passaram em 13 arquivos, 32 deles em produto-formulario.test.tsx (UC-PEDIT-11 a 14). Antes: PR #9482, lane PHP / Pest (Estoque · MySQL), job 116997588268: 485 passed (2356 assertions); UC-PEDIT-01 a 09 verdes. Revalidação depois do refactor que passou as seções do formulário para _components/formulario/ (sem mudança de comportamento: HTML e estado do formulário idênticos antes/depois em 3 cenários do Editar no Vitest; o PUT envia os mesmos campos)"
---

# Casos de Uso & Aceite — Editar produto

> **Âncora:** `CU-PROD-02` (produto variável), `CU-PROD-10` (multi-tenant) e `CU-PROD-01.4` `[V0]` do
> [SDD §6.1](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md), cruzados com o
> **contrato de paridade Delphi** ([ANTI-REGRESSAO-cadastro-produto-legacy.md](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md), Office Comercial 2026.1.1.38).
> Os UCs derivam do **contrato** (CU-PROD + charter + AR-PROD), **nunca** do `Edit.tsx` — teste derivado do
> código é tautológico e trava o desvio em vez de pegá-lo (`proibicoes.md` §5, 2026-06-05).
>
> **Como este arquivo nasceu:** piloto do agent `sdd-from-source` ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)),
> triangulando as **3 fontes** — React (`Edit.tsx` → `PUT /products/{id}` → `ProductController@update`) +
> Blade (`resources/views/product/edit.blade.php`, o cadastro real em prod) + Delphi (as ~120 âncoras `AR-PROD-*`).
> Fecha a lacuna medida: `Produto/Edit.tsx` era `trio:missing-casos` no baseline do `casos-gate`.
>
> **O que ele NÃO faz:** não descreve o layout. Um caso de uso é contrato de **comportamento** — "editar o
> produto não apaga as variações" vale igual se o form mora no `Edit.tsx`, numa aba nova ou no Blade.
>
> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado (stub) · 🔶 backlog (achado a verificar) · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-PEDIT-01 | Editar produto variável não apaga as variações existentes | must | `CU-PROD-02` + AR-PROD-021/032 | `ProdutoEditContratoTest` (Pest) | 🧪 verde no CT 100 2026-09-18 |
| UC-PEDIT-02 | Tipo (single/variable/combo) não muda após criação | should | Non-Goal charter + AR-PROD (tipo na criação) | `ProdutoEditContratoTest` (Pest) | 🧪 verde no CT 100 2026-09-18 |
| UC-PEDIT-03 | Editar produto de outro business → 404 (não vaza, não 500) | must `[T0]` | `CU-PROD-10` + ADR 0093 + charter Goal | `ProdutoEditContratoTest` (Pest) + e2e stub | 🧪 achado CONFIRMADO + corrigido (`update()` → `firstOrFail`) |
| UC-PEDIT-04 | Campo monetário no update não infla no parser pt-BR | must `[V0]` | `CU-PROD-01.4` + REGRA MESTRE | `ProdutoEditContratoTest` (Pest) | 🧪 verde no CT 100 2026-09-18 |
| UC-PEDIT-05 | Editar não desliga o controle de estoque (`enable_stock`) | must `[V0]` | `AR-PROD-051/056` + REGRA MESTRE + charter Goal | `ProdutoEditPayloadContratoTest` (Pest) | 🧪 achado CONFIRMADO + **corrigido** (vermelho reproduzido no CT 100 em 2026-09-18, verde depois) |
| UC-PEDIT-06 | Editar produto `single` persiste em vez de estourar 500 | must | charter §Goals ("Salvar") | `ProdutoEditPayloadContratoTest` (Pest) | 🧪 achado CONFIRMADO + **corrigido em 2 pontos** (`preparation_time_in_minutes` e `single_variation_id`) |
| UC-PEDIT-07 | Editar não apaga flags que a tela não envia | must | `AR-PROD-003/042` | `ProdutoEditPayloadContratoTest` (Pest) | 🧪 achado CONFIRMADO + **corrigido** |
| UC-PEDIT-08 | Desligar as 3 flags continua possível pela Blade (`hidden 0`) | must `[V0]` | par obrigatório do 05/07 + `AR-PROD-051/056` | `ProdutoEditPayloadContratoTest` (Pest) | 🧪 nasce verde — trava o par que o revert do #4994 mostrou ser obrigatório |
| UC-PEDIT-09 | Editar a origem da mercadoria: grava 0–8, recusa fora da tabela, Blade não zera, outra empresa → 404, e a nota leva a origem | should `[T0]` | `AR-PROD-128` + ADR 0093 | `ProdutoOrigemMercadoriaTest` (Pest) | 🧪 passa no CT 100 |
| UC-PEDIT-10 | Editar pela tela React não apaga os campos personalizados nem a validade | must | `AR-PROD-003/042` + charter Goal | `ProdutoEditPayloadContratoTest` (Pest) + `produto-editar-preserva.test.tsx` (Vitest) | ⬜ Vitest passa; Pest aguarda a lane |
| UC-PEDIT-11 | Editar o NCM: grava, apagar volta a sem NCM, fora do formato é recusado, sem mexer não muda, outra empresa → 404 | must `[T0]` | `CU-PROD-16` + ADR 0093 | `CadastroProdutoNcmTest` (Pest) + `produto-formulario.test.tsx` (Vitest) | 🧪 escrito, sem veredito |
| UC-PEDIT-12 | Editar o CEST: grava, apagar volta a sem CEST, fora do formato é recusado, sem mexer não muda, outra empresa → 404 | should `[T0]` | `CU-PROD-16` + ADR 0093 | `CadastroProdutoCestTest` (Pest) + `produto-formulario.test.tsx` (Vitest) | 🧪 escrito, sem veredito |
| UC-PEDIT-13 | Editar peso líquido e dimensões: grava, apagar volta a nulo, inválido é recusado, Blade não muda, outra empresa → 404 | should `[T0]` | `CU-PROD-17` + ADR 0093 | `CadastroProdutoPesoDimensoesTest` (Pest) + `produto-formulario.test.tsx` (Vitest) | 🧪 escrito, sem veredito |
| UC-PEDIT-14 | Ver a natureza fiscal (mercadoria · serviço · a decidir) que o contador decidiu; serviço não recebe o aviso de "sem NCM"; decisão de outra empresa não aparece | should `[T0]` | `CU-PROD-19` + D-NATUREZA + ADR 0093 | `ProdutoNaturezaFiscalCadastroTest` (Pest) + `produto-formulario.test.tsx` (Vitest) | 🧪 escrito, sem veredito |
| UC-PEDIT-15 | Com tipo EAN-13, EAN-8 ou UPC-A, SKU que não vira código de barras válido é avisado; salvar continua liberado | should | `CU-PROD-18` | `produto-formulario.test.tsx` (Vitest) | 🧪 escrito, sem veredito |

> ⚠️ **UC-PEDIT-01/02/04 DEIXARAM de ser stub em 2026-09-18** — viraram Pest de comportamento no
> `ProdutoEditContratoTest` (o arquivo que esta nota mandava usar), com `PUT` real, `actingAs` e
> assert de banco. Os três saíram **verdes** no CT 100: são **guards** (travam comportamento
> correto), não achados. _Fato preservado: até 2026-09-18 eram ⬜ stub `test.fixme`, satisfazendo
> só a rastreabilidade G-2, ⬜ até o Pest rodar no CT100 ([ADR 0062])._ O **03** nasceu do adversário de 2026-07-24, que pegou
> o que este casos.md v1 deixara como "🔶 não afirmado" (usando LC-08 como escudo pra NÃO ler —
> quando LC-08 manda ler). Os **05/06/07** nasceram do **B1-controle** (1º run real do agent
> `sdd-from-source`, evidência em [`_b1-controle-Edit.casos.agent.md`](../../../../memory/requisitos/Produto/_b1-controle-Edit.casos.agent.md))
> e tinham Pest failing-first escrito. O veredito saiu e o vermelho era real — **corrigidos**,
> porque status vem do teste, não da palavra (G-7).
>
> ⚠️ **O 08 é o par obrigatório do 05/07, e existe por causa de um revert.** Um fix anterior
> ([#4943](https://github.com/wagnerra23/oimpresso.com/pull/4943)) foi revertido
> ([#4994](https://github.com/wagnerra23/oimpresso.com/pull/4994)) por mexer no Blade de produção sem
> smoke. A lição que o revert deixou: preservar-ausência **sozinho** tornaria impossível DESLIGAR as
> flags na tela que roda em prod — checkbox desmarcado não envia chave nenhuma. O 08 trava o `hidden 0`
> mecanicamente, para que ninguém remova metade do par sem o CI acusar.

---

## UC-PEDIT-01 · Editar produto variável não apaga as variações existentes · `must`
- **Persona:** Larissa / ROTA LIVRE — abre um produto com grade tam×cor (N variações), corrige o nome e salva. As variações (SKU, preço, estoque por variação) **não podem sumir** só porque ela editou o cabeçalho.
- **Aceite:** Dado um produto variável com **N variações** · Quando envio `PUT /products/{id}` alterando **apenas** o `name` · Então as **N variações persistem** (mesmo `sub_sku`/preço/estoque) — `ProductController@update` **não** recria nem apaga a grade.
- **Teste:** `e2e/produto-edit.spec.ts` — `UC-PEDIT-01` (stub `test.fixme`; virar Pest `ProdutoEditContratoTest` no CT100).
- **Contrato:** `CU-PROD-02` (produto variável — grade preservada) + Edit.charter Non-Goal *"❌ Editar variations dinamicamente (Wave 3)"* — editar o cabeçalho **não é** editar a grade.
- **Paridade Delphi:** `AR-PROD-021`/`AR-PROD-032` — no legado, "Alterar" edita o registro **preservando** o que já estava carregado (trocar de aba/salvar não perde o produto).
- **Regressão que defende:** `update()` monta a variação a partir do request; se o payload da Edit React (que **não** manda os campos de variação) fizer o backend zerar a grade, o produto perde SKU/preço/estoque calado.
- **Status: 🧪** — **virou Pest em 2026-09-18**, como este casos.md mandava. O `ProdutoEditContratoTest` faz `PUT /products/{id}` com o payload real da Edit React (que não manda `product_variation`) e asserta que as 2 variações sobrevivem **com os mesmos ids** — recriar a grade com ids novos rompe o vínculo de estoque/venda mesmo mantendo a contagem igual. Recibo: rodado no CT 100 (container `oimpresso-staging`, MySQL `oimpresso_staging`) em 2026-09-18 — **6 passed (16 assertions)**; o mesmo arquivo em `main` dava 2 passed (2 assertions). Cerco de hash do `ProductController.php` idêntico antes e depois do run (o checkout de lá é compartilhado entre sessões). Bite-test por mutação dos 4 asserts novos: 4 mutações → 4 vermelhos, então eles leem dado real. Não vira ✅ enquanto a lane não publicar o veredito — status vem do teste, não da palavra (G-7).

---

## UC-PEDIT-02 · Tipo (single/variable/combo) não muda após criação · `should`
- **Persona:** qualquer operador — o `type` define a natureza do produto (simples × grade × kit). Mudá-lo depois de criado corromperia variações/BOM já existentes.
- **Aceite:** Dado um produto `type='single'` · Quando um `PUT /products/{id}` forja `type='variable'` · Então o produto **continua** `single` (o backend ignora a troca de tipo).
- **Teste:** `e2e/produto-edit.spec.ts` — `UC-PEDIT-02` (stub).
- **Contrato:** Edit.charter Non-Goal *"❌ Mudar `type` (Single/Variable/Combo) após criar"* + o `<Input>` de tipo é `disabled` na Edit React (defesa de UI, não de contrato — o UC prova o **backend**).
- **Paridade Delphi:** o tipo é definido na criação; a tela de alteração não expõe troca de tipo.
- **Regressão que defende:** o React desabilita o campo, mas UI desabilitada **não** impede o request de mandar `type` — mesma família do furo `UC-PCAD-05`/`UC-PTAB-04` (dropdown escopado ≠ request escopado).
- **Status: 🧪** — **virou Pest em 2026-09-18**. O teste manda `type=variable` num produto `single` (request forjado, hostil de propósito) e asserta que o tipo **não** muda. Verde: o `update()` nunca atribui `type` a partir do request — varredura contada no método, 3 ocorrências de `$product->type`, **todas leitura** (`==`), zero atribuição. É guard anti-regressão, não achado. Recibo: rodado no CT 100 (container `oimpresso-staging`, MySQL `oimpresso_staging`) em 2026-09-18 — **6 passed (16 assertions)**; o mesmo arquivo em `main` dava 2 passed (2 assertions). Cerco de hash do `ProductController.php` idêntico antes e depois do run (o checkout de lá é compartilhado entre sessões). Bite-test por mutação dos 4 asserts novos: 4 mutações → 4 vermelhos, então eles leem dado real. Não vira ✅ enquanto a lane não publicar o veredito — status vem do teste, não da palavra (G-7).

---

## UC-PEDIT-03 · Editar produto de outro business → 404 (não vaza, não 500) · `must` `[T0]`
- **Persona:** qualquer tenant. O pior bug do projeto é o catálogo de um business vazar pro outro — aqui pela URL da edição.
- **Aceite:** Dado `GET /products/{id_de_outro_business}/edit` **ou** `PUT /products/{id_de_outro_business}` · Quando acesso · Então **404** — nenhum dado do produto alheio chega ao form nem é gravado.
- **Teste:** `tests/Feature/Produto/ProdutoEditContratoTest.php` — `UC-PEDIT-03 · GET edit ...` + `UC-PEDIT-03 · PUT update ...` (Pest, failing-first, lane `Estoque · MySQL` no CT100). O `e2e/produto-edit.spec.ts` mantém o id citado (redundância G-2).
- **Contrato:** `CU-PROD-10` + Edit.charter Goal *"Multi-tenant: produto cross-tenant retorna 404"* + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** é **exatamente** a família que já nasceu vermelha 2× no Produto — `UC-PCAD-06` (`create()` `find()`→`findOrFail()`, era 500) e `UC-PTAB-04` (`saveSellingPrices` engolia a `ModelNotFoundException` no `catch` genérico → 302, não 404, [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)).
- **Status: 🧪 achado CONFIRMADO + corrigido no mesmo PR (adversário 2026-07-24).** A varredura que faltava (2 linhas): `edit()` (GET) usa `firstOrFail()` (`app/Http/Controllers/ProductController.php:872-875 (verificado@5d5cac0)`) → **já era 404 ✅**; mas `update()` (PUT) usava `first()` (`:978-981`) → id alheio vira `null` → atribuição em `null` (`:990`) → `\Error` → o `catch (\Exception)` (`:1173`) **não** pega `\Error` → **500** (nunca 404). O `[T0]` estava a um `grep firstOrFail` de distância; o casos.md v1 usou LC-08 como escudo pra NÃO ler, quando LC-08 **manda** ler. **Fix (mesmo PR):** `firstOrFail()` **antes do try** no `update()` (fora do `catch`, pra o 404 não virar 302 como no #4300) → `app/Http/Controllers/ProductController.php:972-980 (verificado@5d5cac0)`. Vermelho→verde provado pela lane MySQL do CI.

---

## UC-PEDIT-04 · Campo monetário no update não infla no parser pt-BR · `must` `[V0]`
- **Persona:** Larissa — se editar o custo/preço e digitar `1.234,56`, o sistema tem que gravar mil duzentos e trinta e quatro, nunca um milhão.
- **Aceite:** Dado um `PUT /products/{id}` com `single_dpp='1.234,56'` e `single_dsp='2.000,00'` · Quando salvo · Então a variação grava `default_purchase_price ≈ 1234.56` / `default_sell_price ≈ 2000.00` — **nunca** ordem de grandeza maior.
- **Teste:** `e2e/produto-edit.spec.ts` — `UC-PEDIT-04` (stub; Pest com o mesmo par `1.234,56`/`204.99605` do `UC-PCAD-04`).
- **Contrato:** `CU-PROD-01` item 4 `[V0]` + REGRA MESTRE valor/estoque (`proibicoes.md`). `ProductController@update` roda `num_uf` em `single_dpp`/`single_dsp`/`profit_percent` (`ProductController.php:1102-1106 (verificado@d4afe95)`) e `alert_quantity` (`:997`) — **o mesmo parser** que inflou 16 vendas ×100k na ROTA LIVRE (incidente 2026-06-05).
- **⚠️ Achado de paridade (NÃO afirmado como bug — decisão [W]/[F]):** a **Edit React não tem campo de preço** — o `useForm` não manda `single_dpp`/`single_dsp`/`profit_percent`, e o card "Preço & Imposto" só traz `tax`/`tax_type` (mesmo padrão do `Create.tsx`, §Pendência de contrato do `Create.casos.md`). Já o **Delphi edita Custo/Valor/Margem** com binding bidirecional (`AR-PROD-006`/`007`/`008`). Então: (a) o UC defende o **endpoint** (`update()` parseia pt-BR, caminho Blade/legado); (b) a **ausência do preço na Edit React** é gap de paridade Blade/Delphi→React, registrado abaixo — não afirmo se é Non-Goal ou bug (igual à Pendência do Create; segue [F] reconstruindo o cadastro em abas).
- **Status: 🧪** — **virou Pest em 2026-09-18**, com o MESMO par de valores do `UC-PCAD-04` (`1.234,56` e `204.99605`), de propósito: é o mesmo parser nos dois endpoints. Verde nos dois casos — o `num_uf` já carrega a heurística pt-BR canônica, então este UC é **guard anti-regressão do fix de 2026-06-05**, não achado novo. ⛔ **TEST-ONLY**: nada de cálculo foi tocado; a REGRA MESTRE (dupla prova + tabela antes→depois + [W]) vale pra quem for mexer. Recibo: CT 100 (`oimpresso-staging`, MySQL), 2026-09-18 — 6 passed (16 assertions) no arquivo; o bite-test por mutação derruba os dois asserts deste UC.

---

## UC-PEDIT-05 · Editar não desliga o controle de estoque (`enable_stock`) · `must` `[V0]`
- **Persona:** Larissa / ROTA LIVRE — corrige o **nome** de um produto que controla estoque e salva. O produto tem que continuar controlando estoque. Nada na tela disse "desligar estoque".
- **Aceite:** Dado um produto com `enable_stock = 1` · Quando envio `PUT /products/{id}` com **o payload que a tela React manda** (18 chaves, sem `enable_stock`) · Então `enable_stock` **continua 1**.
- **Teste:** [`tests/Feature/Produto/ProdutoEditPayloadContratoTest.php`](../../../../tests/Feature/Produto/ProdutoEditPayloadContratoTest.php) — `UC-PEDIT-05` (Pest, failing-first, lane `Estoque · MySQL`).
- **Contrato:** `AR-PROD-051`/`AR-PROD-056` (no Delphi, "controla estoque" é atributo do produto — editar a ficha não é o gesto que liga/desliga) + `proibicoes.md` §REGRA MESTRE (valor/estoque) + Edit.charter §Goals.
- **Regressão que defende:** o writer trata **ausência como zero** (`update()` L76-79: `if (! empty($request->input('enable_stock')) && == 1) {1} else {0}`). A tela não manda a chave → salvar o nome **apagaria o controle de estoque em silêncio**: o save "funciona", a tela não reclama, e o estoque some do produto.
- **Status: 🧪 achado CONFIRMADO e CORRIGIDO (2026-09-18).** Reproduzido no CT 100 (tenant 98): `enable_stock` foi de **1 → 0** ao editar só o nome. A pré-condição anti-vácuo passou (o save ACONTECEU), então não é ausência-de-escrita: é **zeragem**. Re-confirmado no run 30366164436 (2026-07-29). ⚠️ **O remédio óbvio sozinho seria errado** (ver §DESFECHO: foi aplicado **com** o par `hidden 0`) — ver [§Diagnóstico do remédio](#diagnóstico-do-remédio-2026-07-29--a-correção-óbvia-é-a-errada): fazer o writer preservar a ausência **quebra o desligar no Blade**, que é o que roda em prod.

---

## UC-PEDIT-06 · Editar produto `single` persiste em vez de estourar 500 · `must`
- **Persona:** qualquer operador — clicar "Salvar" e receber tela de erro é o pior desfecho possível de um cadastro.
- **Aceite:** Dado um produto `type='single'` · Quando envio `PUT /products/{id}` com o payload da tela React · Então a resposta **não é 500** e o `name` novo está no banco.
- **Teste:** [`ProdutoEditPayloadContratoTest`](../../../../tests/Feature/Produto/ProdutoEditPayloadContratoTest.php) — `UC-PEDIT-06`.
- **Contrato:** Edit.charter §Goals — "Salvar" é Goal declarado da tela.
- **Regressão que defende:** no ramo `single`, `update()` lê `single_variation_id` de um `$request->only([...])` que **não contém a chave** (`:1111-1112`) → `Variation::find(null)` → `null` → atribuição de propriedade em `null` → `\Error`. O `catch (\Exception)` (`:1173`) **não pega `\Error`** → 500. É a mesma família do `UC-PEDIT-03` (o `catch` genérico que mascara o desfecho real).
- **Defeito INDEPENDENTE do UC-PEDIT-05** (`proibicoes.md` §5, 2026-07-15): consertar um não conserta o outro, e as correções podem brigar — por isso teste próprio, não um "fix da raiz".
- **Status: 🧪 achado CONFIRMADO e CORRIGIDO (2026-09-18).** Reproduzido no CT 100: o PUT com o payload da tela **não persistia** — aborta em `preparation_time_in_minutes` (hoje `ProductController:1042`, sem `??`), o `catch (\Exception)` engole e vira redirect. **Sem 500** — falha silenciosa, pior que erro visível. Re-confirmado no run 30366164436 (2026-07-29). ⚠️ **Não consertar isoladamente:** este abort é o que hoje impede a zeragem do UC-PEDIT-05 — destravar o `save()` sozinho **piora** o eixo estoque ([§Diagnóstico do remédio](#diagnóstico-do-remédio-2026-07-29--a-correção-óbvia-é-a-errada)).

---

## UC-PEDIT-07 · Editar não apaga flags que a tela não envia · `must`
- **Persona:** operador que marcou o produto como "não disponível para venda" ou "controla número de série" — editar o nome não pode desmarcar.
- **Aceite:** Dado um produto com `not_for_selling = 1` e `enable_sr_no = 1` · Quando envio `PUT /products/{id}` com o payload da tela React (que não manda nenhuma das duas) · Então **ambas continuam 1**.
- **Teste:** [`ProdutoEditPayloadContratoTest`](../../../../tests/Feature/Produto/ProdutoEditPayloadContratoTest.php) — `UC-PEDIT-07`.
- **Contrato:** `AR-PROD-003`/`AR-PROD-042` — no legado, alterar a ficha preserva o que já estava gravado; ausência de um campo no formulário não é "desmarcar".
- **Regressão que defende:** mesmo padrão ausência→zero do `UC-PEDIT-05`, em `not_for_selling` (`:82`) e `enable_sr_no` (`:101-104`); `sub_unit_ids` (`:71`) vira `null` pela mesma razão. Generaliza o defeito: **não é uma flag, é o contrato do payload**.
- **Status: 🧪 achado CONFIRMADO e CORRIGIDO (2026-09-18).** Reproduzido no CT 100: `not_for_selling` foi de **1 → 0**. Recibo literal: `Failed asserting that 0 is identical to 1` em `ProdutoEditPayloadContratoTest.php:212 (verificado@d4afe95)`. Re-confirmado no run 30366164436 (2026-07-29). Mesmo remédio do UC-PEDIT-05 — ver [§Diagnóstico do remédio](#diagnóstico-do-remédio-2026-07-29--a-correção-óbvia-é-a-errada).

---

## UC-PEDIT-09 · Editar a origem da mercadoria · `should` `[T0]`
- **Persona:** quem corrige o cadastro de um produto importado — a nota passa a sair com a origem certa.
- **Aceite:** Dado um produto · Quando escolho outra origem no card "Preço & Imposto" e salvo · Então ela grava · E um código fora da tabela A (ex.: `9`) é recusado **sem gravar nada** · E salvar pelo Blade (que não manda o campo) **não zera** a origem escolhida na React · E editar a origem de produto de outra empresa devolve **404** sem gravar · E a emissão por item (NfeBrasil) leva a origem do produto ao `icms.orig`, lida só da própria empresa; linha sem a chave sai com `0`, como antes.
- **Teste:** [`ProdutoOrigemMercadoriaTest`](../../../../tests/Feature/Produto/ProdutoOrigemMercadoriaTest.php) — `UC-PEDIT-09` (6 casos).
- **Contrato:** `AR-PROD-128` + ADR 0093 (Tier 0) + tabela A do MOC. O "Blade não zera" segue a lição do UC-PEDIT-05/07: o `update()` serve dois clientes, e chave ausente no Blade não pode apagar o que a React escolheu.
- **Status: 🧪** — Recibo: CT 100 (`oimpresso-staging`, MySQL `oimpresso_staging`, worktree isolado do branch, sem tocar o checkout compartilhado), 2026-10-15 — **8 passed (19 assertions)**. Bite-test por mutação: emissão de volta a `0`, limite `0,9` no lugar de `0,8`, `update()` sem o guarda `filled` e `linhasDaVenda` sem a coluna — cada uma derruba 1 caso. Não vira ✅ enquanto a lane não publicar o veredito (G-7).

---

## UC-PEDIT-10 · Editar pela tela React não apaga os campos personalizados nem a validade · `must`
- **Persona:** empresa que usa campos personalizados de produto além do 4º (Configurações → rótulos) ou controla validade — corrigir o nome de um produto pela tela nova não pode apagar o que estava preenchido.
- **Aceite:** Dado um produto com o campo personalizado 5 e o 20 preenchidos e validade de 12 meses, numa empresa que usa validade · Quando abro o Editar e salvo · Então a tela recebe o valor gravado dos 20 campos e da validade, devolve todos no envio, e os 4 valores continuam gravados.
- **Teste:** [`ProdutoEditPayloadContratoTest`](../../../../tests/Feature/Produto/ProdutoEditPayloadContratoTest.php) — `UC-PEDIT-10` (o `edit()` entrega e o PUT preserva) · [`produto-editar-preserva.test.tsx`](../../../../tests/js/produto-editar-preserva.test.tsx) — `UC-PEDIT-10` (a tela copia o gravado para o envio, 3 casos).
- **Contrato:** `AR-PROD-003`/`AR-PROD-042` — alterar a ficha preserva o que já estava gravado (mesmo contrato do UC-PEDIT-07).
- **Regressão que defende:** o `update()` grava `''` em todo `product_custom_fieldN` (1 a 20) que não vem no request e, com `business.enable_product_expiry` ligado, zera `expiry_period`/`expiry_period_type` que não vêm. A tela mandava só os campos 1 a 4 e nenhuma validade. **O servidor não mudou de propósito:** o Blade só envia os campos personalizados que têm nome, então "ausência preserva" mudaria o Blade, a tela que roda em produção (a mesma armadilha do §Diagnóstico do remédio abaixo). A correção é a tela mandar tudo.
- **Status: ⬜ Vitest passa (3 de 3) e morde:** com o `Edit.tsx` do `main` os 3 casos caem por asserção (`expected '' to be 'Ref. interna'`, `expected undefined to be '12'`). O Pest aguarda a lane `Estoque · MySQL`.

---

## UC-PEDIT-11 · Editar o NCM · `must` `[T0]`
- **Persona:** quem corrige o cadastro de uma mercadoria que vai em nota fiscal. O NCM entrou no Novo produto (`UC-PCAD-13` do `Create.casos.md`); sem ele no Editar, o produto antigo só podia ganhar NCM pela tela de Tributação.
- **Aceite:** Dado um produto · Quando abro o Editar · Então o campo NCM mostra o NCM gravado, só com dígitos (`4911.10.90` aparece `49111090`) · E, se mudo o NCM e salvo, ele grava · E, se apago o NCM e salvo, o produto volta para "sem NCM" (o `'0'` da coluna) · E um NCM fora do formato é recusado **sem gravar nada** · E, se **não mexo** no NCM, o envio não leva o campo e o NCM gravado fica como estava — mesmo um NCM antigo fora do formato, que mostra o aviso *"O NCM tem 8 dígitos."* mas não impede salvar outro campo · E salvar pelo Blade (que não manda o campo) não muda o NCM · E editar o NCM de produto de outra empresa devolve **404** sem gravar.
- **Teste:** [`CadastroProdutoNcmTest`](../../../../tests/Feature/Produto/CadastroProdutoNcmTest.php) — `UC-PEDIT-11` (o servidor: grava, apagar, sem o campo, fora do formato, outra empresa, e o `edit()` entregando o NCM) · [`produto-formulario.test.tsx`](../../../../tests/js/produto-formulario.test.tsx) — `UC-PEDIT-11` (a tela: só dígitos, sem mexer não envia, NCM antigo fora do formato não barra, alterar envia, apagar envia vazio).
- **Contrato:** `CU-PROD-16` do [SDD](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) (decisão [M] 2026-10-20: o NCM entra no cadastro, sem ser obrigatório) + ADR 0093. O "sem mexer não envia" segue a mesma lição do UC-PEDIT-05/07/09: o `update()` atende o Blade e a tela React, e campo ausente não pode mudar o que está gravado.
- **Status: 🧪** — escrito junto do código, sem veredito da lane `PHP / Pest (Estoque · MySQL)` nem do job `Frontend / Vite build` (G-7).

---

## UC-PEDIT-12 · Editar o CEST · `should` `[T0]`
- **Persona:** quem corrige o cadastro de uma mercadoria com substituição tributária.
- **Aceite:** Dado um produto · Quando abro o Editar · Então o campo CEST mostra o CEST gravado, só com dígitos (`28.038.00` aparece `2803800`) · E, se mudo o CEST e salvo, ele grava · E, se apago o CEST e salvo, o produto fica sem CEST (coluna nula) · E um CEST fora do formato é recusado **sem gravar nada** · E, se **não mexo** no CEST, o envio não leva o campo e o CEST gravado fica como estava, mesmo um CEST antigo fora do formato · E salvar pelo Blade (que não manda o campo) não muda o CEST · E editar o CEST de produto de outra empresa devolve **404** sem gravar.
- **Teste:** [`CadastroProdutoCestTest`](../../../../tests/Feature/Produto/CadastroProdutoCestTest.php) — `UC-PEDIT-12` (o servidor: grava, apagar, sem o campo, fora do formato, outra empresa, e o `edit()` entregando o CEST) · [`produto-formulario.test.tsx`](../../../../tests/js/produto-formulario.test.tsx) — `UC-PEDIT-12` (a tela: mostra só dígitos e, sem mexer, não envia; alterar envia; apagar envia vazio).
- **Contrato:** `CU-PROD-16` do [SDD](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) + Convênio ICMS 142/2018, cláusula sexta + ADR 0093. Mesma regra do `UC-PEDIT-11` (NCM): o `update()` atende o Blade e a tela React, e campo ausente não muda o que está gravado.
- **Status: 🧪** — escrito junto do código, sem veredito da lane `PHP / Pest (Estoque · MySQL)` nem do job `Frontend / Vite build` (G-7).

---

## UC-PEDIT-13 · Editar peso líquido e dimensões · `should` `[T0]`
- **Persona:** quem corrige o cadastro de um produto que vai ser despachado.
- **Aceite:** Dado um produto · Quando abro o Editar · Então "Mais opções" mostra o "Peso bruto (kg)" de sempre e o peso líquido, a largura, a altura e o comprimento gravados · E, se mudo e salvo, grava · E, se apago um campo e salvo, ele volta a nulo · E um valor inválido (negativo, texto, acima de 99999) é recusado **sem gravar nada** · E salvar pelo Blade (que não manda os campos) não muda o que está gravado · E editar produto de outra empresa devolve **404** sem gravar.
- **Teste:** [`CadastroProdutoPesoDimensoesTest`](../../../../tests/Feature/Produto/CadastroProdutoPesoDimensoesTest.php) — `UC-PEDIT-13` (o servidor: grava, apagar, Blade, inválido, outra empresa, e o `edit()` entregando os valores) · [`produto-formulario.test.tsx`](../../../../tests/js/produto-formulario.test.tsx) — `UC-PEDIT-13` (a tela: mostra e devolve os valores; apagar envia vazio).
- **Contrato:** `CU-PROD-17` do [SDD](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) + ADR 0093. A tela sempre manda os quatro campos (são colunas novas, sem dado antigo fora do formato); o Blade não manda, e campo ausente não muda o que está gravado.
- **Status: 🧪** — escrito junto do código, sem veredito da lane `PHP / Pest (Estoque · MySQL)` nem do job `Frontend / Vite build` (G-7).

## UC-PEDIT-14 · Ver a natureza fiscal do produto · `should` `[T0]`
- **Persona:** quem corrige o cadastro de um produto que tanto pode ser vendido como mercadoria quanto como serviço (arte, instalação, impressão sob encomenda).
- **Aceite:** Dado um produto cuja natureza o contador já decidiu na Tributação · Quando abro o Editar · Então "Preço & Imposto" mostra "Natureza fiscal: Serviço" (com o item da LC 116), "Mercadoria" ou "A decidir pelo contador" · E diz que quem decide é o contador, com o link "Ver na Tributação" para quem tem acesso · E, se é serviço e o NCM está vazio, **não** aparece o aviso de "sem NCM" (nota de serviço não leva NCM) · E NCM fora do formato continua avisado · E produto sem decisão não mostra a natureza · E uma decisão gravada com o `business_id` de outra empresa não aparece.
- **Teste:** [`ProdutoNaturezaFiscalCadastroTest`](../../../../tests/Feature/Produto/ProdutoNaturezaFiscalCadastroTest.php) — `UC-PEDIT-14` (o servidor: serviço com item, mercadoria, sem decisão, decisão de outra empresa) · [`produto-formulario.test.tsx`](../../../../tests/js/produto-formulario.test.tsx) — `UC-PEDIT-14` (a tela: serviço sem aviso de NCM, mercadoria com aviso, formato errado avisado, sem decisão).
- **Contrato:** `CU-PROD-19` do [SDD](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) + D-NATUREZA ([W] 2026-10-06: o contador decide, por produto, com fonte registrada) + ADR 0093. O cadastro **só mostra**: não grava natureza. O núcleo pede o dado ao módulo fiscal pelo gancho `getModuleData('produto_natureza_fiscal')`, que só responde se o NfeBrasil estiver instalado; sem o módulo, a natureza não aparece e o aviso de NCM fica como era.
- **Status: 🧪** — escrito junto do código, sem veredito da lane `PHP / Pest (Estoque · MySQL)` nem do job `Frontend / Vite build` (G-7).

## UC-PEDIT-15 · SKU que não vira código EAN válido é avisado · `should`
- **Persona:** quem corrige o cadastro de uma mercadoria que tem etiqueta com código de barras.
- **Aceite:** Dado um produto com tipo de código de barras EAN-13, EAN-8 ou UPC-A · Quando abro o Editar ou mudo o SKU · Então, se o SKU tem letra ou símbolo, dígitos demais ou o último dígito errado, aparece o aviso embaixo do tipo de código de barras · E salvar continua liberado · E com Code 128 o mesmo SKU não avisa.
- **Teste:** [`produto-formulario.test.tsx`](../../../../tests/js/produto-formulario.test.tsx) — `UC-PEDIT-15` (SKU com letra num produto EAN-13 avisa; com Code 128 não).
- **Contrato:** `CU-PROD-18` do [SDD](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md). A regra é a mesma do `UC-PCAD-17` (`Create.casos.md`): a etiqueta recusa esses códigos; a nota fiscal não usa o código hoje.
- **Status: 🧪** — escrito junto do código, sem veredito do job `Frontend / Vite build` (G-7).

## Diagnóstico do remédio (2026-07-29) — a correção óbvia é a errada

> ## ⚠️ DESFECHO (2026-09-18): a Via B foi ADOTADA — **com a compensação que faltava**
>
> Esta seção fica **inteira e intacta** abaixo: ela era honesta no dia, e o dado que a sustenta
> continua verdadeiro. O que mudou não é o dado — é que a via B nunca foi avaliada **junto do
> seu par**. Pela regra de precedência (`proibicoes.md`: *teste verde > casos > charter > SPEC*),
> o teste verde manda e o texto perdedor se corrige no mesmo PR; é o que esta nota faz.
>
> **A premissa que caiu:** *"trocar o `update()` para ausência = preservar tira da Larissa a
> capacidade de desligar controle de estoque pelo Blade"*. Verdadeiro **se e somente se** o Blade
> continuar sem `hidden`. Com `<input type="hidden" name="..." value="0">` antes de cada checkbox,
> desmarcar passa a **declarar** o `0` em vez de omitir a chave — e o desligar sobrevive.
>
> **Medido em 2026-09-18, não argumentado** (staging CT 100, tenant 98, plugin `input-icheck` ativo
> no navegador — a camada que o Pest não executa e que esta seção corretamente apontava como fora
> do alcance dela):
>
> | gesto | payload que o form serializa | resultado no banco |
> |---|---|---|
> | desmarcar (Blade **com** `hidden`) | `["0"]` | `0 0 0` — **desliga** ✅ |
> | marcar | `["0","1"]` → last-wins `1` | `1 1 1` — **liga** ✅ |
> | **controle negativo**: `hidden` removido do DOM, desmarcado | chave **ausente** | `1 1 1` — preserva (= o desligar quebraria **sem** o par) |
>
> A terceira linha é a prova de que o par é obrigatório, e é exatamente o risco que esta seção
> anteviu. Ele agora está travado por máquina: **`UC-PEDIT-08`** falha se o `hidden` sumir do Blade.
>
> **Por que não a Via A** (risco zero na tabela abaixo): ela move o contrato para o emissor, e o
> emissor é a tela React — **inalcançável hoje** (re-medido em 2026-09-18: zero `<Link>` Inertia
> para `/products` fora das próprias páginas do Produto). Fechá-la ali deixaria o Blade, que é o que
> de fato roda, ainda zerando flags por ausência. A via B+par corrige **os dois emissores** de uma vez.
>
> ⚠️ **Precedente que não se repete:** um fix idêntico ([#4943](https://github.com/wagnerra23/oimpresso.com/pull/4943))
> foi revertido ([#4994](https://github.com/wagnerra23/oimpresso.com/pull/4994)) por mergear a mudança
> do Blade **sem smoke**. Desta vez o smoke veio antes, e está colado acima.

> **Decisão [W] 2026-07-29: só diagnóstico.** Nenhum código de correção aqui — o eixo é ESTOQUE
> (`proibicoes.md` §REGRA MESTRE) e a escolha do remédio é dele. Esta seção grava **o dado que
> decide**, pra próxima sessão não "consertar" o writer e derrubar produção.

**Recibo da medição** — lane `Estoque · MySQL`, [run 30366164436](https://github.com/wagnerra23/oimpresso.com/actions/runs/30366164436) do [#4953](https://github.com/wagnerra23/oimpresso.com/pull/4953), lido em 2026-07-29:
`UC-PINIC-01..04` **passaram** (o #4953 destravou as pré-condições do estoque inicial);
`UC-PEDIT-05/06/07` seguem vermelhos, com os recibos literais citados em cada UC acima.

### O writer não está simplesmente errado — ele serve dois clientes com semânticas opostas

| Cliente | O que "chave ausente" significa | Prova (medida, não lida no olho) |
|---|---|---|
| **Blade** — o que roda em prod hoje | *o operador desmarcou* | `resources/views/product/edit.blade.php:125 (verificado@5d5cac0)/221/231` usa `Form::checkbox` e **não existe hidden** para nenhum dos 3 flags (grep: "NENHUM hidden"). Checkbox desmarcado não é enviado pelo browser — spec HTML, não implementação. Sem hidden, ausência **é** o gesto de desligar. |
| **React** — `Edit.tsx` (draft) | *a tela não gerencia esse campo* | `grep -E "enable_stock\|not_for_selling\|enable_sr_no"` no `Edit.tsx` → **zero ocorrências**. A tela não oferece o gesto. |

**Consequência dura:** trocar o `update()` para "ausência = preservar" **tira da Larissa a capacidade
de desligar controle de estoque pelo Blade** — regressão Tier 0 no eixo estoque, produzida por um fix
que se apresenta como correção. Via descartada **por medição**, não por opinião.

### ⚠️ Ordem das correções: o `??` sozinho PIORA o estoque

Hoje o abort do `preparation_time_in_minutes` (UC-PEDIT-06) é o que **impede** a zeragem do
`enable_stock`: nada é escrito. Consertar só o `??` destrava o `save()` e a zeragem passa a acontecer
**de verdade**. É a lápide `proibicoes.md` §5 2026-07-15 em ato — três defeitos independentes cujas
correções **brigam entre si**. Qualquer PR que toque o `??` fecha o contrato do payload no mesmo
movimento, ou piora o que veio consertar.

### Não é incidente de produção (re-verificado nesta data)

`preparation_time_in_minutes` é **incondicional** no Blade (`resources/views/product/edit.blade.php:316-317 (verificado@5d5cac0)`, fora de qualquer
`@if`) → prod sempre manda a chave e o abort não a atinge. Somado à inalcançabilidade das telas React
(sidebar `<a href>` puro, sem header `X-Inertia`), isto segue **bloqueador de migração MWART F5**
([ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)) — não incêndio.

### Remédios avaliados (para quando [W] decidir)

| Via | O que faz | Risco em prod | Fecha |
|---|---|---|---|
| **A** — tela React envia o que não gerencia (pass-through) **+** `?? null` no writer | `enableStock`/`subUnitIds` **já** estão nos props do `edit()` (`ProductController` ~L929); faltam `not_for_selling`, `enable_sr_no`, `preparation_time_in_minutes` | **zero** — writer do Blade intacto | 05 · 06 · 07 |
| **B** — writer preserva quando a chave falta | inverte o padrão ausência→zero | **ALTO — quebra o desligar no Blade** | ⛔ descartada acima |
| **D** — writer discrimina por cliente (o `update()` já é dual, `X-Inertia` em `:342/909`) | ramo Inertia preserva; ramo Blade mantém como hoje | baixo, mas bifurca um writer Tier 0 | 05 · 07 |

Nenhuma das vias altera linha existente: o efeito é só sobre edições futuras vindas da tela React —
**sem backfill, sem UPDATE retroativo**.

---

## Paridade Delphi→React (gaps do cutover — decisão [W]/[F], não bug afirmado)

> O `Edit.tsx` (draft) migrou **8 campos do cabeçalho** (nome/SKU/tipo/unidade/categoria/marca/imposto/alerta/peso/descrição/custom). O Delphi "Alterar produto" faz **muito mais** — cada item abaixo é uma âncora `AR-PROD-*` que **NÃO** deve sumir sem Non-Goal declarado ([ANTI-REGRESSAO §](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md)). Vira US/Non-Goal quando [W]/[F] decidir (a Edit segue a US-PROD-023 — promover as 8 telas).

| Gap na Edit React | Âncora Delphi | Onde já existe no oimpresso | Natureza |
|---|---|---|---|
| Editar **Custo · Valor · Margem** (binding bidirecional) `[V0]` | AR-PROD-006/007/008 | `SellingPrices.tsx` (preço por tabela) + `update()` variação | aba Custos ([F], #4403) — provável Non-Goal do `/edit` |
| **Última Alteração** (timestamp read-only de auditoria) | AR-PROD-004 | — | gap |
| **Atividade** (log append-only de modificações do cadastro) | AR-PROD-131..134 | — | gap |
| **Excluir** = soft-delete (inativo + filtro, nunca hard-delete) | AR-PROD-022 | charter Non-Goal *"❌ Deletar produto inline"* | Non-Goal declarado |
| **Estoque** (disponibilidade, kardex, fornecedor, compras) | AR-PROD-050..084 | `StockHistory.tsx` (fachada, G-01) | tela separada |

---

## Âncoras propostas pro SPEC (o agent PROPÕE; [W]/humano aplica)

> **Não aplicadas ao `SPEC.md` neste piloto** — tocar o SPEC legado do Produto acorda o `anchor-lint`
> diff-aware sobre a dívida grandfathered dele (lápide `proibicoes.md` §5 2026-07-12). Ficam como
> proposta pra [W] aplicar quando a US-PROD-023 avançar:

- `US-PROD-023` (promover as 8 telas React) → **Implementado em:** `resources/js/Pages/Produto/Edit.tsx` → `PUT /products/{id}` → [`ProductController@update`](../../../../app/Http/Controllers/ProductController.php) · casos: `Edit.casos.md` · verificado@`<sha-quando-rodar>`

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **Editar produto simples grava os campos do cabeçalho** — o caminho feliz óbvio; vira UC quando o Pest `ProdutoEditContratoTest` cobrir o happy-path (hoje só cobre o cross-tenant; o resto seria tautológico contra o `.tsx`).
- **Locais de armazenagem e endereço no estoque** — `CU-PROD-20`, pedido da [M] em 2026-10-22, só no protótipo. No Editar: a quantidade aparece como "Em estoque", só leitura (muda por compra, venda, ajuste ou transferência, nunca pelo cadastro), e local com saldo não pode ser tirado. Vira UC quando a tela tiver o bloco e um teste que o cite; decidido em 2026-10-22: endereço em prateleira, fileira e posição, e as duas seções ficam; o local padrão é o local sugerido; a venda continua baixando do local da venda.
- **Editar Custo/Valor/Margem** (`AR-PROD-006..008`) — só quando a aba Custos existir em git ([F], #4403) e sob a REGRA MESTRE `[V0]`.
- **[V0] risco a verificar — preço-zero no update via React** (achado lateral do adversário 2026-07-24): `update()` só parseia preço no ramo `type=='single'` lendo `single_dpp` de `$request->only([...])`; a Edit.tsx (`useForm`) **não manda** `single_dpp` → chave ausente → `num_uf(null)` → **potencial gravação de preço 0** (mesmo eixo `[V0]` do incidente ×100k). NÃO afirmado como bug — precisa ler `num_uf(null)` + dupla-confirmação (REGRA MESTRE) + decisão [W]/[F]. Vira UC/US quando investigado (não corrigir cego).

---

## Refs

- Charter (lei): [`Edit.charter.md`](Edit.charter.md) — `v1`, `draft`, `last_validated: 2026-05-15`
- SDD (âncora dos CU): [`SDD-tela-cadastro-produto-v1.0.md`](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) §6.1
- Contrato de paridade Delphi: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md)
- Irmãos que fecharam o trio: [`Create.casos.md`](Create.casos.md) · [`SellingPrices.casos.md`](SellingPrices.casos.md)
- SPEC (a US que promove a tela): [`SPEC.md`](../../../../memory/requisitos/Produto/SPEC.md) — US-PROD-023
- Mecanismo: [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) (`sdd-from-source`, piloto)
- Gate: `scripts/casos-coverage-guard.mjs` (G-1/G-2/G-5/G-6/G-7 — ADR 0264)

## Trilha do tempo
- 2026-07-24 · [CC] nascido pelo piloto do agent `sdd-from-source` (ADR 0351) — trio fechado (charter existia + casos novo + stub e2e), triangulando React+Blade+Delphi. Fecha `trio:missing-casos:Produto/Edit.tsx` do baseline. UCs stubbed (⬜/🔶), veredito honesto até rodar no CT100. Refs: ADR 0351 · 0264 G-1/G-2.
- 2026-07-24 · [CC] **revisão adversarial** (3 céticos read-only sobre o piloto) — o UC-PEDIT-03, que era 🔶 "não afirmado", virou achado Tier 0 **CONFIRMADO**: `update()` cross-tenant = 500 (`first()` + atribuição em null), não 404. Corrigido no mesmo PR (`firstOrFail` antes do try) + Pest `ProdutoEditContratoTest` (failing-first). Registrado o risco `[V0]` preço-zero via React (backlog). Lição: usei LC-08 como escudo pra NÃO ler — LC-08 manda ler.
- 2026-07-24 · [F+CC] **B1-controle** — 1º run REAL do agent `sdd-from-source` ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)), que a [errata 0352](../../../../memory/decisions/0352-errata-0351-venue-distiller-citacao-taxonomia.md) admitia nunca ter sido executado. Rodado como grupo de controle **nesta tela** (a que já tinha `casos.md` feito à mão), evidência em [`_b1-controle-Edit.casos.agent.md`](../../../../memory/requisitos/Produto/_b1-controle-Edit.casos.agent.md). Veredito: **abaixo do humano no contrato** (perdeu o UC-PEDIT-01 e o log de Atividade), **acima no payload** — a triangulação React×Blade×Delphi rendeu 4 candidatos que a redação à mão não tinha. Daí nascem **UC-PEDIT-05/06/07**, com Pest failing-first ([`ProdutoEditPayloadContratoTest`](../../../../tests/Feature/Produto/ProdutoEditPayloadContratoTest.php)) e a lane `Estoque · MySQL` estendida — inclusive adotando o `ProdutoEditContratoTest`, que existia **fora de lane** desde ontem ("verde impossível" do `anchor-lint`). Enquadramento corrigido no mesmo dia: **não é incidente de produção** (as telas React do Produto são inalcançáveis — sidebar usa `<a href>` puro, sem header `X-Inertia`; roda o Blade, confirmado por [F]) e sim **bloqueador de migração** — define quando a tela pode ser ligada (MWART F5, [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)).
- 2026-07-29 · [C] **diagnóstico do remédio, sem correção** — [W] mandou atacar o eixo estoque; a medição mudou o remédio e a decisão dele foi *"só diagnóstico agora"*. O achado que faltava: o `update()` **serve dois clientes com semânticas opostas** para "chave ausente" (Blade = *desmarcou*, provado pela ausência de hidden nos 3 `Form::checkbox`; React = *não gerencia*, provado por zero ocorrência dos flags no `Edit.tsx`) — logo o fix intuitivo (writer preservar) **quebraria o desligar em produção**, e o `??` isolado **pioraria** a zeragem hoje contida pelo abort. Recibo: run 30366164436 (PR [#4953](https://github.com/wagnerra23/oimpresso.com/pull/4953)), onde `UC-PINIC-01..04` passaram e os 05/06/07 seguem vermelhos. Nada de código de produção tocado. Refs: `proibicoes.md` §REGRA MESTRE · §5 2026-07-15 (correções que brigam entre si).
- 2026-10-15 · [CL] **UC-PEDIT-09** — origem da mercadoria por produto (`products.origem_mercadoria`, DEFAULT 0): campo no card "Preço & Imposto", validação 0–8 fora do `try`, gravação só quando o campo vem, e as duas emissões passam a ler a origem do produto. Mesmo PR cria o UC-PCAD-07 no Create.
- 2026-10-19 · [F+C] **UC-PEDIT-10** — o Editar React apagava, ao salvar, os campos personalizados 5 a 20 e a validade (o `update()` grava `''`/zera o que não vem; a tela mandava só os campos 1 a 4). O `edit()` passa a entregar o valor gravado dos 20 campos e da validade, e o `Edit.tsx` devolve todos no envio. O `update()` e o Blade não mudam.
