---
id: resources-js-pages-produto-bulk-edit-casos
casos: Edição em massa · POST /products/bulk-edit → POST /products/bulk-update
irmaos: BulkEdit.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: editar N produtos de uma vez é a operação com maior raio de dano do catálogo — um erro silencioso aqui multiplica por N, e não há desfazer.
owner: wagner
last_run: "2026-07-26"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane Estoque · MySQL"
---

# Casos de Uso & Aceite — Edição em massa de produtos

> **Âncora:** `CU-PROD-06` (importação Excel + **bulk-edit** + mass-ops) e `CU-PROD-10` `[T0]`
> (multi-tenant) do [SDD §6.1](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md),
> cruzados com o **contrato de paridade Delphi**
> ([ANTI-REGRESSAO-cadastro-produto-legacy.md](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md),
> Office Comercial 2026.1.1.38 — `AR-PROD-006/007/008` `[V0]`) e com a **Blade que define o payload
> real** (`resources/views/product/bulk-edit.blade.php` + `partials/bulk_edit_variation_row.blade.php`).
> Os UCs derivam do **contrato**, **nunca** do `BulkEdit.tsx` — teste derivado do código é
> tautológico e trava o desvio em vez de pegá-lo (`proibicoes.md` §5, 2026-06-05).
>
> **Como este arquivo nasceu:** agent `sdd-from-source` ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)),
> triangulando as **3 fontes** — React (`BulkEdit.tsx` → `ProductController@bulkEdit`, branch
> `X-Inertia`) + Blade (o **outro branch do mesmo método**, `view('product.bulk-edit')`) + Delphi
> (âncoras `AR-PROD-*`). Fecha `trio:missing-casos:Produto/BulkEdit.tsx` — era a **última** tela do
> módulo Produto sem `casos.md`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito pendente) ·
> ⬜ não verificado · ❌ quebrou · 🔶 decisão [W].

---

## ⚠️ Três fatos medidos que emolduram TODOS os casos abaixo

Sem eles, este arquivo soaria mais forte do que a realidade. Medidos em 2026-07-26, sha `6cd0fbc4f2`:

| # | Fato | Como re-medir |
|---|---|---|
| **1** | **O operador não chega nesta tela.** O botão "Edição em massa" da lista está atrás de `config('constants.enable_product_bulk_edit')`, hardcoded **`false`** — com a nota upstream *"Will be depreciated in future"*. | `grep -n "enable_product_bulk_edit" config/constants.php resources/views/product/partials/product_list.blade.php` |
| **2** | **O botão Salvar da tela React aponta pra uma rota que não existe.** `BulkEdit.tsx` faz `post('/products/mass-update')`; **0** ocorrências em `routes/`. O writer real é `POST /products/bulk-update` → `ProductController@bulkUpdate` (`routes/web.php:443`). ⚖️ **DECIDIDO [W] 2026-07-27: repontar a tela** (não criar alias). O charter §Goals e o RUNBOOK §3.2 já declaram a rota certa; **a linha do `.tsx` ainda NÃO foi aplicada** — vai no PR seguinte, porque o hook MWART do project dir bloqueia até o fix de leitura de `related_runbook:` (neste PR) ser mergeado. O fato **1** segue valendo: a flag continua `false`. | `grep -rn "mass-update" routes/ resources/js/Pages/Produto/ memory/requisitos/Produto/` |
| **3** | **As telas React do Produto seguem inalcançáveis em prod** (sidebar usa `<a href>` puro, sem `X-Inertia` → cai no Blade). | mesmo enquadramento dos irmãos `Edit`/`Show`/`Index` |

**Consequência para o veredito:** vermelho aqui é **bloqueador de migração** (gate MWART F5,
[ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)) e **defeito
de endpoint vivo** — os endpoints `bulk-edit`/`bulk-update` respondem hoje pra qualquer um com
`product.update`, independentemente do botão estar escondido. **Não** é incidente de produção em curso.

⚖️ **Força do veredito destes UC — `advisory`, não bloqueante.** Os testes rodam em
`PHP / Pest (Estoque · MySQL)` no PR (allowlist do [`estoque-pest.yml`](../../../../.github/workflows/estoque-pest.yml))
e no fullsuite nightly do CT 100 (`phpunit.xml` inclui `./tests/Feature` recursivamente +
`scripts/tests/shards-plan.mjs` enumera o diretório), mas essa lane **não consta** em
[`governance/required-checks-baseline.json`](../../../../governance/required-checks-baseline.json).
Ou seja: **reprovação aqui é visível e não bloqueia merge.**

---

## Rastreabilidade

| UC | Caso de uso | Prio | Contrato | Teste | Status |
|----|-------------|------|----------|-------|--------|
| UC-PBULK-01 | A matriz entrega o preço de venda corrente da variação | must `[V0]` | Blade `bulk_edit_variation_row` + `AR-PROD-008` | `ProdutoBulkEditContratoTest` | 🧪 (era ⨯ **confirmado** na lane; corrigido 2026-07-27 — veredito deste PR) |
| UC-PBULK-02 | Produto de outro business não entra na matriz | must `[T0]` | `CU-PROD-06`.4 + `CU-PROD-10` + RUNBOOK §1 | `ProdutoBulkEditContratoTest` | 🧪 (verde esperado) |
| UC-PBULK-03 | Tabela de preço de outro business não grava row no `bulk-update` | must `[T0][V0]` | `CU-PROD-10`.1 + precedente `UC-PTAB-04` | `ProdutoBulkEditContratoTest` | 🧪 (era ⨯ **confirmado** Tier 0; guard aplicado 2026-07-27 — veredito deste PR) |
| UC-PBULK-04 | Lote com produto alheio não persiste nada (rollback total) | must `[T0]` | `CU-PROD-06`.4 | `ProdutoBulkEditContratoTest` | 🧪 (verde esperado) |
| UC-PBULK-05 | O payload que a tela React edita persiste sem zerar o resto | must `[V0]` | charter §Goals + Blade (payload real) | `ProdutoBulkEditContratoTest` | 🧪 (vermelho **esperado** — predição) |
| UC-PBULK-06 | Preço em pt-BR (`"1.234,56"`) persiste 1234.56 — sem inflar | must `[V0]` | `CU-PROD-06`.3 + `AR-PROD-006/008` + REGRA MESTRE | `ProdutoBulkEditContratoTest` | 🧪 (verde esperado) |

> 🧪 **e não ✅/❌**: eu não rodo teste (CT 100 · [ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
> "Vermelho esperado" é **predição** derivada de leitura + varredura contada — o veredito é da lane
> (G-7 · `proibicoes.md` §5 2026-07-15).

---

## UC-PBULK-01 · A matriz entrega o preço de venda corrente da variação · `must` `[V0]`

- **Persona:** Larissa / ROTA LIVRE — seleciona 12 peças da grade e abre a edição em massa pra
  reajustar preço. Se a coluna "venda" abre com um número que não é o preço do produto, ela
  reajusta em cima de dado errado — e confirma para os 12 de uma vez.
- **Aceite:** *Dado* uma variação com preço de venda gravado (líquido `233,11` / com imposto `256,42`)
  · *Quando* o `POST /products/bulk-edit` monta a matriz (`X-Inertia`) · *Então* algum campo daquela
  variação no payload carrega o **preço de venda corrente** — não zero.
- **Teste:** [`ProdutoBulkEditContratoTest`](../../../../tests/Feature/Produto/ProdutoBulkEditContratoTest.php)
  — `UC-PBULK-01 · a matriz entrega o preço de venda corrente da variação`.
- **Contrato:** a Blade renderiza os **dois** campos de venda com o valor do banco
  (`Form::text(...[default_sell_price], @num_format($variation->default_sell_price))` e
  `...[sell_price_inc_tax], @num_format($variation->sell_price_inc_tax)`); o Delphi destaca o
  **R$ Valor** como campo editável do cabeçalho (`AR-PROD-008` `[V0]`).
- **✅ CORRIGIDO em 2026-07-27** (decisão [W]: `sell_price_inc_tax`, preservando a intenção do nome
  quebrado e a paridade com a coluna "venda (inc)" do Blade). Os 4 sites leem a coluna real;
  `grep -rn "default_sell_price_inc_tax" app/` = **0**. O `UC-PBULK-01` era ⨯ na lane
  ([run 30264246760](https://github.com/wagnerra23/oimpresso.com/actions/runs/30264246760)) com
  *"Nenhum campo da variação carrega o preço de venda corrente (233.11 nem 256.42)"* — o veredito
  novo é da lane deste PR. O texto abaixo fica como **registro do achado**, não do estado atual.
- **Regressão que defende (o achado):** o branch Inertia montava
  `'defaultSellPrice' => (float) ($v->default_sell_price_inc_tax ?? 0)` — e
  **`default_sell_price_inc_tax` não é coluna de `variations`** (o schema tem `default_sell_price` e
  `sell_price_inc_tax`; varredura contada em `database/`: **0** referências ao nome). Sem coluna e
  sem accessor no `App\Variation` (sem `$appends`, sem `get…Attribute`), o Eloquent devolve `null` →
  `?? 0` → **toda variação chega à tela com preço de venda `0`**. O mesmo literal aparece em **4
  sites** (`ProductController@show :841` · `@addSellingPrices :2043` · `@bulkEdit :2452` ·
  `ProdutoUnificadoController :113`) — é defeito de módulo, não desta tela.
  > 🔧 **Corrige uma leitura anterior:** o `Show.casos.md` (§UC-PSHOW-04) e o SDD §CU-PROD-14
  > descreveram esse campo como *"venda **com imposto**"*, assumindo que a coluna existisse. Não
  > existe — o valor é sempre `0`. A pendência "qual base usar (exc/inc)" **continua sendo decisão
  > [W]**; o que muda é que hoje não é nenhuma das duas.
  >
  > Por isso o assert aceita **qualquer uma das duas bases**: escolher `233,11` *ou* `256,42` seria
  > escolher o remédio antes do diagnóstico (`proibicoes.md` §5, 2026-07-15).
- **Status: 🧪** — vermelho **esperado** (predição; veredito da lane).

---

## UC-PBULK-02 · Produto de outro business não entra na matriz · `must` `[T0]`

- **Persona:** qualquer tenant. A matriz é a lista do que o `bulk-update` vai gravar — se um produto
  alheio entra aqui, o passo seguinte escreve nele.
- **Aceite:** *Dado* `selected_products = "<meu_id>,<id_de_outro_business>"` · *Quando* a matriz é
  montada · *Então* o payload traz **o meu** (pré-condição) e **não** traz o alheio.
- **Teste:** [`ProdutoBulkEditContratoTest`](../../../../tests/Feature/Produto/ProdutoBulkEditContratoTest.php)
  — `UC-PBULK-02 · produto de outro business não entra na matriz (multi-tenant Tier 0)`.
- **Contrato:** `CU-PROD-06` item 4 `[T0]` (*"Bulk valida `business_id` de **cada** ID antes de
  aplicar"*) + `CU-PROD-10` + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
  + RUNBOOK §1 (*"`business_id` isola: produtos cross-tenant não aparecem"*) + charter §Goals.
- **Regressão que defende:** é literalmente o `it('Controller cross-tenant não inclui produtos
  biz=99')` que o `BulkEdit.charter.md` §Pest GUARD promete — e que **nunca existiu** (varredura
  contada nos 2 testes Wave2 da tela: 0 ocorrências). Os dois só fazem `grep` de string no fonte
  (*"contém `Inertia::render('Produto/BulkEdit'`"*), mesmo padrão que o `TabelaPrecoContratoTest`
  desmentiu no SellingPrices. O guard real existe no código (`Product::where('business_id', …)
  ->whereIn('id', …)`); este UC o **trava** contra refactor.
  > ⚠️ **Nota honesta de comportamento:** o ID alheio é **silenciosamente descartado**, não gera 404.
  > Isolamento não vaza; o contrato "cross-tenant → 404" do `CU-PROD-10`.2 continua falso nos POSTs
  > (mesma pendência já aberta em `SellingPrices.casos.md`). Não invento veredito novo aqui.
- **Status: 🧪** — verde esperado (o UC **trava** o invariante; não é achado).

---

## UC-PBULK-03 · Tabela de preço de outro business não grava row no `bulk-update` · `must` `[T0][V0]`

- **Persona:** qualquer tenant. É o eixo *tabela* do cross-tenant (o UC-PBULK-02 é o eixo *produto*).
- **Aceite:** *Dado* um lote com `group_prices[<tabela_do_outro_business>] = "99,00"` na minha
  variação · *Quando* o `POST /products/bulk-update` roda · *Então* a linha da **minha** tabela
  persiste (pré-condição: o laço rodou) e **nenhuma** linha é criada para a tabela alheia.
- **Teste:** [`ProdutoBulkEditContratoTest`](../../../../tests/Feature/Produto/ProdutoBulkEditContratoTest.php)
  — `UC-PBULK-03 · tabela de preço de outro business não grava row no bulk-update (Tier 0)`.
- **Contrato:** `CU-PROD-10` item 1 `[must][T0]` — e o próprio texto do CU já avisa: *"o próximo
  model pendurado em `Product` nasce com o mesmo buraco"*.
- **Regressão que defende (o achado):** é o **mesmo defeito** que o `UC-PTAB-04` provou vermelho em
  CI ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)) — `price_group_id` vindo **cru
  da chave do array do request**, num model (`VariationGroupPrice`) que **não tem global scope**
  (`$guarded = ['id']`) e cuja FK só exige que o grupo **exista**, não que seja seu. Lá o fix foi o
  guard `$allowedPriceGroupIds` (resolvido antes do laço + `skip` + `Log::warning`); aqui
  `bulkUpdate` fazia `VariationGroupPrice::updateOrCreate(['price_group_id' => $k, …])` **sem guard
  nenhum**. Uma tela foi corrigida, a irmã não — é exatamente a assimetria que o CU previu.
- **✅ CORRIGIDO em 2026-07-27** — a predição bateu: o `UC-PBULK-03` reprovou na lane real
  ([run 30264246760](https://github.com/wagnerra23/oimpresso.com/actions/runs/30264246760)) com
  *"Gravou preço da MINHA variação numa tabela de preço de OUTRO business (price_group_id 2)"*,
  e a falha foi no **segundo** assert (`:311`) — a **pré-condição anti-vácuo passou**, provando que
  o laço de `group_prices` rodou de verdade. Não foi verde-por-não-execução. Fix: guard
  `$allowedPriceGroupIds` resolvido antes do laço + `continue` + `Log::warning`, **idêntico** ao
  `saveSellingPrices` ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)).
- **Status: 🧪 → veredito da lane deste PR** (era vermelho **confirmado**, não mais predição).

---

## UC-PBULK-04 · Lote com produto alheio não persiste nada (rollback total) · `must` `[T0]`

- **Persona:** Larissa. Ela seleciona 12 produtos; se um deles for inválido, o pior desfecho é
  **meia-edição gravada** — parte do catálogo alterada + mensagem genérica de erro, sem saber o quê.
- **Aceite:** *Dado* que o mesmo lote só-com-produtos-meus **persiste** (fase 1, pré-condição) ·
  *Quando* submeto um lote misto (meu + de outro business) · *Então* nem a variação alheia é tocada,
  nem a minha metade fica meio-aplicada.
- **Teste:** [`ProdutoBulkEditContratoTest`](../../../../tests/Feature/Produto/ProdutoBulkEditContratoTest.php)
  — `UC-PBULK-04 · lote com produto de outro business não persiste nada (rollback total)`.
- **Contrato:** `CU-PROD-06` item 4 `[T0]` + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o `bulkUpdate` **não valida antes** — ele aplica dentro de uma
  transação e o `findOrFail` escopado só estoura no meio do laço; quem salva o dia é o
  `DB::rollBack()` do `catch` genérico. O contrato do CU (*"valida … antes de aplicar"*) é
  satisfeito **no resultado**, não no mecanismo. Este UC trava o resultado: se alguém trocar a
  transação por saves soltos (ou mover o `commit` pra dentro do laço), a meia-edição volta.
- **Status: 🧪** — verde esperado (trava de invariante).

---

## UC-PBULK-05 · O payload que a tela React edita persiste sem zerar o resto · `must` `[V0]`

- **Persona:** Larissa clica "Confirmar (12)". O charter promete que isso **atualiza os 12**.
- **Aceite:** *Dado* o payload **exato** do `useForm` de `BulkEdit.tsx` (2 campos por variação:
  `default_purchase_price` + `default_sell_price`) · *Quando* o writer o recebe · *Então* (a) o custo
  editado persiste **e** (b) o custo **com imposto** não fica menor que o líquido (i.e. a chave
  ausente não virou zero).
- **Teste:** [`ProdutoBulkEditContratoTest`](../../../../tests/Feature/Produto/ProdutoBulkEditContratoTest.php)
  — `UC-PBULK-05 · o payload que a tela React edita persiste sem zerar o resto`.
- **Contrato:** `BulkEdit.charter.md` §Goals (*"Botão 'Atualizar {N} produtos'"* + *"Submit"*) + o
  payload real da Blade (5 campos numéricos por variação) + REGRA MESTRE (`proibicoes.md` Tier 0 —
  valor não muda em silêncio).
- **Regressão que defende (o achado):** o writer lê **5** chaves por variação
  (`default_purchase_price` · `dpp_inc_tax` · `profit_percent` · `default_sell_price` ·
  `sell_price_inc_tax`) **sem `??`**, e a tela React manda **2**. Ausência de chave vira
  `ErrorException` → engolida pelo `catch (\Exception)` genérico → `rollBack()` → o operador vê
  *"algo deu errado"* e **perde o lote inteiro**. É a mesma família do `UC-PEDIT-05/06/07`
  (`preparation_time_in_minutes` sem `??` no `update()`), agora no caminho de massa.
  A perna (b) existe porque o remédio óbvio (`?? 0`) trocaria um defeito **barulhento** por um
  **silencioso** — zerar `dpp_inc_tax`/`sell_price_inc_tax` de N produtos é exatamente o que a
  REGRA MESTRE proíbe. O assert é neutro quanto ao remédio: passa se o controller tolerar a ausência
  **preservando** o valor, ou se a tela passar a mandar os 5 campos.
- **Status: 🧪** — vermelho **esperado** (predição; veredito da lane).

---

## UC-PBULK-06 · Preço em pt-BR (`"1.234,56"`) persiste 1234.56 — sem inflar · `must` `[V0]`

- **Persona:** Larissa digita `1.234,56` (ou cola do Excel). O separador de milhar pt-BR não pode
  virar `123456`.
- **Aceite:** *Dado* o payload da Blade com `default_purchase_price = "1.234,56"` e
  `default_sell_price = "2.000,00"` · *Quando* o lote é gravado · *Então* o banco guarda `1234.56`
  e `2000.00`.
- **Teste:** [`ProdutoBulkEditContratoTest`](../../../../tests/Feature/Produto/ProdutoBulkEditContratoTest.php)
  — `UC-PBULK-06 · preço em pt-BR ("1.234,56") persiste 1234.56 — sem inflar (V0)`.
- **Contrato:** `CU-PROD-06` item 3 `[V0]` (*"passa pelo mesmo guard `num_uf`"*) + `AR-PROD-006`
  (precisão do custo) / `AR-PROD-008` (parser pt-BR sem inflar ×100) + REGRA MESTRE
  (`proibicoes.md` Tier 0 — origem: incidente 2026-06-05, `num_uf` strippando o ponto decimal e
  inflando venda ~×100k em biz=4).
- **Regressão que defende:** o `bulkUpdate` **aplica** `num_uf` nos 5 campos hoje — e é a única
  coisa entre o texto formatado da tela e a coluna `decimal(22,4)`. Nenhum teste cobria esse
  caminho: a edição em massa é justamente onde um erro de parsing se multiplica por N produtos.
  Primo do guard que `ProductUtil::fixVariationStockMisMatch` ganhou em
  [#4636](https://github.com/wagnerra23/oimpresso.com/pull/4636) — **mesmo remédio (`num_uf`), eixo
  diferente** (lá estoque, aqui valor) e **caminho de código diferente**: este UC **não** prova
  aquele, e vice-versa.
  > 🔍 **Por que a US daquele fix não é citada por id aqui:** o
  > [`requisitos-status.mjs`](../../../../scripts/governance/requisitos-status.mjs) mede "US entregue
  > sem contrato" por **substring** (`casos.src.includes(us.id)`) — bastaria escrever o id nesta
  > frase pra a lacuna sumir do painel **sem** nenhum UC provar o que foi entregue. Seria fechar a
  > régua em vez do buraco. A lacuna daquela US é real e está descrita na devolutiva/session log.
- **Status: 🧪** — verde esperado (trava de invariante `[V0]`).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = **órfão** = violação no `casos-gate` (e trava o
> merge de quem for atendê-lo). Contrato em **1 fonte só** ou achado sem âncora fica aqui, como
> prosa visível.

- ~~**[BACKLOG] O destino do submit da tela não existe**~~ — **DECIDIDO [W] 2026-07-27: repontar.**
  Era: `BulkEdit.tsx` postava em `/products/mass-update` (0× em `routes/`), com o charter §Goals e o
  RUNBOOK §3.2 declarando a mesma rota fantasma. [W] escolheu **repontar a tela** pro
  `/products/bulk-update` em vez de criar alias — alias abriria superfície de escrita nova numa
  feature que o upstream declara que vai depreciar. Os 3 artefatos foram corrigidos no mesmo PR
  (regra de precedência), mas **a linha do `.tsx` fica pro PR seguinte** — o hook MWART do project
  dir bloqueia até o fix de leitura de `related_runbook:` (neste PR) ser mergeado. A flag
  `enable_product_bulk_edit` segue **`false`**: repontar **não** religa a tela.
- **[BACKLOG] O que a tela React perdeu em relação à Blade legada** (`AR-PROD-*` / MWART) — a Blade
  edita **6 famílias** que o React não tem: (1) **buscar e ADICIONAR produto** à matriz sem voltar
  pra lista (`#search_product` → `/products/get-product-to-edit/{id}`); (2) **custo com imposto**
  (`dpp_inc_tax`); (3) **margem %** (`profit_percent`) com recálculo encadeado custo↔margem↔venda —
  o coração do `AR-PROD-007`/`AR-PROD-093`; (4) **venda com imposto** (`sell_price_inc_tax`);
  (5) **preços por tabela** (`group_prices` por variação); (6) **localizações** do produto (o charter
  §Goals lista a coluna "Locations (multi)"; a tabela React não a renderiza — o payload só a
  round-tripa). Cada família vira UC quando [W] decidir se é **Non-Goal** (bulk enxuto por desenho)
  ou **gap de paridade** a fechar. Registrado também na
  [PARIDADE-charter-vs-legado.md](../../../../memory/requisitos/Produto/PARIDADE-charter-vs-legado.md).
- **[BACKLOG] Custo/margem sem gate de permissão** — `AR-PROD-015` `[V0]`: no Delphi os campos
  **somem** para quem não pode ver custo. A matriz (React **e** Blade) mostra custo a qualquer um
  com `product.update`, sem consultar `view_purchase_price`. **1 fonte só** (Delphi) — a Blade desta
  tela também não gateia, então não há regressão de migração a provar; vira UC se [W] decidir que a
  paridade Delphi vale aqui (o `UC-PSHOW-01` já cobre o eixo ficha, onde a Blade gateia).
- **[BACKLOG] `403` sem `product.update`** — RUNBOOK §1 promete. O guard existe (`abort(403)` nos 3
  métodos). Vira UC quando houver fixture de usuário sem permissão que não dependa do papel do
  usuário seedado (hoje o seed de biz=1 é admin, então o caso não é exercitável sem montar user novo).
- **[BACKLOG] `bulk-edit` sem `selected_products` devolve corpo vazio** — o método simplesmente não
  retorna nada (`if (! empty(...))` sem `else`), então o Laravel devolve **200 com corpo vazio**. O
  RUNBOOK §1 promete *"sem `selected_products` → redirect Index"*. Fato ≠ promessa; o remédio é
  decisão [W] (redirect vs 422).
- **[BACKLOG] `enable_product_bulk_edit = false`** — a feature inteira está desligada por config, com
  a nota upstream *"Will be depreciated in future"*. Decisão [W]: **ligar** (e aí os UCs acima viram
  P0), **manter desligada** (e então a tela React é candidata a Non-Goal/remoção), ou **substituir**
  pelo caminho `/unificado`. Enquanto não há decisão, os endpoints seguem vivos e os UCs seguem
  válidos.

---

## Refs

- Charter (lei): [`BulkEdit.charter.md`](BulkEdit.charter.md) — `v1`, `draft`, `last_validated: 2026-05-15`
- SDD (âncora dos CU): [`SDD-tela-cadastro-produto-v1.0.md`](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md)
  §5.3 **F5** (fluxo import/bulk) + §6.1 `CU-PROD-06` / `CU-PROD-10`
- Paridade Delphi: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md)
  (`AR-PROD-006/007/008/015`)
- RUNBOOK: [`_telas/RUNBOOK-produto-bulk-edit.md`](../../../../memory/requisitos/Produto/_telas/RUNBOOK-produto-bulk-edit.md)
- Blade que define o payload: `resources/views/product/bulk-edit.blade.php` +
  `resources/views/product/partials/bulk_edit_variation_row.blade.php`
- Controller: `app/Http/Controllers/ProductController.php` — `bulkEdit()` (branch dual) e
  `bulkUpdate()` (writer). Re-localize com
  `grep -n "function bulkEdit\|function bulkUpdate" app/Http/Controllers/ProductController.php`
- Irmão que fechou o mesmo eixo Tier 0 primeiro: [`SellingPrices.casos.md`](SellingPrices.casos.md)
  (`UC-PTAB-04`, [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300))
- Gate: `scripts/casos-coverage-guard.mjs` (G-1/G-2/G-5/G-6/G-7 — [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md))
  · lane `PHP / Pest (Estoque · MySQL)` (**advisory** — fora do `required-checks-baseline.json`)
