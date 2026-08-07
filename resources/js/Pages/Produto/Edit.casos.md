---
id: resources-js-pages-produto-edit-casos
casos: Editar produto · /products/{id}/edit
irmaos: Edit.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — editar o cadastro não pode perder o que a tela velha (Blade + Delphi) preservava.
owner: wagner
last_run: "2026-07-29"
last_run_ci: "lane Estoque · MySQL, run 30366164436 (PR #4953), lido 2026-07-29: UC-PEDIT-05/06/07 ❌ vermelhos (3 defeitos independentes, recibo literal por UC); UC-PEDIT-03 🧪; UC-PEDIT-01/02/04 ⬜ stub test.fixme. Remédio diagnosticado e NÃO aplicado — decisão [W] sob a REGRA MESTRE (eixo estoque)"
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
| UC-PEDIT-01 | Editar produto variável não apaga as variações existentes | must | `CU-PROD-02` + AR-PROD-021/032 | `e2e/produto-edit.spec.ts` (stub) | ⬜ não verificado |
| UC-PEDIT-02 | Tipo (single/variable/combo) não muda após criação | should | Non-Goal charter + AR-PROD (tipo na criação) | `e2e/produto-edit.spec.ts` (stub) | ⬜ não verificado |
| UC-PEDIT-03 | Editar produto de outro business → 404 (não vaza, não 500) | must `[T0]` | `CU-PROD-10` + ADR 0093 + charter Goal | `ProdutoEditContratoTest` (Pest) + e2e stub | 🧪 achado CONFIRMADO + corrigido (`update()` → `firstOrFail`) |
| UC-PEDIT-04 | Campo monetário no update não infla no parser pt-BR | must `[V0]` | `CU-PROD-01.4` + REGRA MESTRE | `e2e/produto-edit.spec.ts` (stub) | ⬜ não verificado |
| UC-PEDIT-05 | Editar não desliga o controle de estoque (`enable_stock`) | must `[V0]` | `AR-PROD-051/056` + REGRA MESTRE + charter Goal | `ProdutoEditPayloadContratoTest` (Pest) | ❌ **CI vermelho** (run 30122611472) |
| UC-PEDIT-06 | Editar produto `single` persiste em vez de estourar 500 | must | charter §Goals ("Salvar") | `ProdutoEditPayloadContratoTest` (Pest) | ❌ **CI vermelho** (run 30122611472) |
| UC-PEDIT-07 | Editar não apaga flags que a tela não envia | must | `AR-PROD-003/042` | `ProdutoEditPayloadContratoTest` (Pest) | ❌ **CI vermelho** (run 30122611472) |

> ⚠️ **UC-PEDIT-01/02/04 seguem stub `test.fixme`** (não rodam, satisfazem só a rastreabilidade G-2)
> — ⬜ até o Pest rodar no CT100 ([ADR 0062]). O **03** nasceu do adversário de 2026-07-24, que pegou
> o que este casos.md v1 deixara como "🔶 não afirmado" (usando LC-08 como escudo pra NÃO ler —
> quando LC-08 manda ler). Os **05/06/07** nasceram do **B1-controle** (1º run real do agent
> `sdd-from-source`, evidência em [`_b1-controle-Edit.casos.agent.md`](../../../../memory/requisitos/Produto/_b1-controle-Edit.casos.agent.md))
> e têm Pest failing-first escrito — ficam ⬜ até a lane publicar o veredito, porque **status vem do
> teste, não da palavra** (G-7).

---

## UC-PEDIT-01 · Editar produto variável não apaga as variações existentes · `must`
- **Persona:** Larissa / ROTA LIVRE — abre um produto com grade tam×cor (N variações), corrige o nome e salva. As variações (SKU, preço, estoque por variação) **não podem sumir** só porque ela editou o cabeçalho.
- **Aceite:** Dado um produto variável com **N variações** · Quando envio `PUT /products/{id}` alterando **apenas** o `name` · Então as **N variações persistem** (mesmo `sub_sku`/preço/estoque) — `ProductController@update` **não** recria nem apaga a grade.
- **Teste:** `e2e/produto-edit.spec.ts` — `UC-PEDIT-01` (stub `test.fixme`; virar Pest `ProdutoEditContratoTest` no CT100).
- **Contrato:** `CU-PROD-02` (produto variável — grade preservada) + Edit.charter Non-Goal *"❌ Editar variations dinamicamente (Wave 3)"* — editar o cabeçalho **não é** editar a grade.
- **Paridade Delphi:** `AR-PROD-021`/`AR-PROD-032` — no legado, "Alterar" edita o registro **preservando** o que já estava carregado (trocar de aba/salvar não perde o produto).
- **Regressão que defende:** `update()` monta a variação a partir do request; se o payload da Edit React (que **não** manda os campos de variação) fizer o backend zerar a grade, o produto perde SKU/preço/estoque calado.
- **Status: ⬜** — stub; vira 🧪/✅ quando o Pest rodar no CT100.

---

## UC-PEDIT-02 · Tipo (single/variable/combo) não muda após criação · `should`
- **Persona:** qualquer operador — o `type` define a natureza do produto (simples × grade × kit). Mudá-lo depois de criado corromperia variações/BOM já existentes.
- **Aceite:** Dado um produto `type='single'` · Quando um `PUT /products/{id}` forja `type='variable'` · Então o produto **continua** `single` (o backend ignora a troca de tipo).
- **Teste:** `e2e/produto-edit.spec.ts` — `UC-PEDIT-02` (stub).
- **Contrato:** Edit.charter Non-Goal *"❌ Mudar `type` (Single/Variable/Combo) após criar"* + o `<Input>` de tipo é `disabled` na Edit React (defesa de UI, não de contrato — o UC prova o **backend**).
- **Paridade Delphi:** o tipo é definido na criação; a tela de alteração não expõe troca de tipo.
- **Regressão que defende:** o React desabilita o campo, mas UI desabilitada **não** impede o request de mandar `type` — mesma família do furo `UC-PCAD-05`/`UC-PTAB-04` (dropdown escopado ≠ request escopado).
- **Status: ⬜** — stub.

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
- **Status: ⬜** — stub.

---

## UC-PEDIT-05 · Editar não desliga o controle de estoque (`enable_stock`) · `must` `[V0]`
- **Persona:** Larissa / ROTA LIVRE — corrige o **nome** de um produto que controla estoque e salva. O produto tem que continuar controlando estoque. Nada na tela disse "desligar estoque".
- **Aceite:** Dado um produto com `enable_stock = 1` · Quando envio `PUT /products/{id}` com **o payload que a tela React manda** (18 chaves, sem `enable_stock`) · Então `enable_stock` **continua 1**.
- **Teste:** [`tests/Feature/Produto/ProdutoEditPayloadContratoTest.php`](../../../../tests/Feature/Produto/ProdutoEditPayloadContratoTest.php) — `UC-PEDIT-05` (Pest, failing-first, lane `Estoque · MySQL`).
- **Contrato:** `AR-PROD-051`/`AR-PROD-056` (no Delphi, "controla estoque" é atributo do produto — editar a ficha não é o gesto que liga/desliga) + `proibicoes.md` §REGRA MESTRE (valor/estoque) + Edit.charter §Goals.
- **Regressão que defende:** o writer trata **ausência como zero** (`update()` L76-79: `if (! empty($request->input('enable_stock')) && == 1) {1} else {0}`). A tela não manda a chave → salvar o nome **apagaria o controle de estoque em silêncio**: o save "funciona", a tela não reclama, e o estoque some do produto.
- **Status: ❌ ACHADO CONFIRMADO** pela lane (run 30122611472, 2026-07-24): `enable_stock` foi de **1 → 0** ao editar só o nome. A pré-condição anti-vácuo passou (o save ACONTECEU), então não é ausência-de-escrita: é **zeragem**. Re-confirmado no run 30366164436 (2026-07-29). ⚠️ **O remédio não é o óbvio** — ver [§Diagnóstico do remédio](#diagnóstico-do-remédio-2026-07-29--a-correção-óbvia-é-a-errada): fazer o writer preservar a ausência **quebra o desligar no Blade**, que é o que roda em prod.

---

## UC-PEDIT-06 · Editar produto `single` persiste em vez de estourar 500 · `must`
- **Persona:** qualquer operador — clicar "Salvar" e receber tela de erro é o pior desfecho possível de um cadastro.
- **Aceite:** Dado um produto `type='single'` · Quando envio `PUT /products/{id}` com o payload da tela React · Então a resposta **não é 500** e o `name` novo está no banco.
- **Teste:** [`ProdutoEditPayloadContratoTest`](../../../../tests/Feature/Produto/ProdutoEditPayloadContratoTest.php) — `UC-PEDIT-06`.
- **Contrato:** Edit.charter §Goals — "Salvar" é Goal declarado da tela.
- **Regressão que defende:** no ramo `single`, `update()` lê `single_variation_id` de um `$request->only([...])` que **não contém a chave** (`:1111-1112`) → `Variation::find(null)` → `null` → atribuição de propriedade em `null` → `\Error`. O `catch (\Exception)` (`:1173`) **não pega `\Error`** → 500. É a mesma família do `UC-PEDIT-03` (o `catch` genérico que mascara o desfecho real).
- **Defeito INDEPENDENTE do UC-PEDIT-05** (`proibicoes.md` §5, 2026-07-15): consertar um não conserta o outro, e as correções podem brigar — por isso teste próprio, não um "fix da raiz".
- **Status: ❌ ACHADO CONFIRMADO** (run 30122611472): o PUT com o payload da tela **não persiste** — aborta em `preparation_time_in_minutes` (hoje `ProductController:1042`, sem `??`), o `catch (\Exception)` engole e vira redirect. **Sem 500** — falha silenciosa, pior que erro visível. Re-confirmado no run 30366164436 (2026-07-29). ⚠️ **Não consertar isoladamente:** este abort é o que hoje impede a zeragem do UC-PEDIT-05 — destravar o `save()` sozinho **piora** o eixo estoque ([§Diagnóstico do remédio](#diagnóstico-do-remédio-2026-07-29--a-correção-óbvia-é-a-errada)).

---

## UC-PEDIT-07 · Editar não apaga flags que a tela não envia · `must`
- **Persona:** operador que marcou o produto como "não disponível para venda" ou "controla número de série" — editar o nome não pode desmarcar.
- **Aceite:** Dado um produto com `not_for_selling = 1` e `enable_sr_no = 1` · Quando envio `PUT /products/{id}` com o payload da tela React (que não manda nenhuma das duas) · Então **ambas continuam 1**.
- **Teste:** [`ProdutoEditPayloadContratoTest`](../../../../tests/Feature/Produto/ProdutoEditPayloadContratoTest.php) — `UC-PEDIT-07`.
- **Contrato:** `AR-PROD-003`/`AR-PROD-042` — no legado, alterar a ficha preserva o que já estava gravado; ausência de um campo no formulário não é "desmarcar".
- **Regressão que defende:** mesmo padrão ausência→zero do `UC-PEDIT-05`, em `not_for_selling` (`:82`) e `enable_sr_no` (`:101-104`); `sub_unit_ids` (`:71`) vira `null` pela mesma razão. Generaliza o defeito: **não é uma flag, é o contrato do payload**.
- **Status: ❌ ACHADO CONFIRMADO** (run 30122611472): `not_for_selling` foi de **1 → 0**. Recibo literal: `Failed asserting that 0 is identical to 1` em `ProdutoEditPayloadContratoTest.php:212 (verificado@d4afe95)`. Re-confirmado no run 30366164436 (2026-07-29). Mesmo remédio do UC-PEDIT-05 — ver [§Diagnóstico do remédio](#diagnóstico-do-remédio-2026-07-29--a-correção-óbvia-é-a-errada).

---

## Diagnóstico do remédio (2026-07-29) — a correção óbvia é a errada

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
