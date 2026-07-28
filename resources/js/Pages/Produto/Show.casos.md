---
id: resources-js-pages-produto-show-casos
casos: Ficha do produto · /products/{id}
irmaos: Show.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — consultar a ficha não pode mostrar MENOS (nem a quem não devia) do que a tela velha (Blade + Delphi) já mostrava.
owner: wagner
last_run: "2026-07-26"
last_run_ci: "0 UC executado — trio nasce agora (agent sdd-from-source, ADR 0351). UC-PSHOW-01/02/03/06 têm Pest failing-first (ProdutoShowContratoTest, lane Estoque · MySQL); 04/05/07 são stub test.fixme. Veredito ⬜/🔶 honesto até a lane publicar."
---

# Casos de Uso & Aceite — Ficha do produto

> **Âncora:** `CU-PROD-14` (ficha/consulta do produto) e `CU-PROD-10` `[T0]` (multi-tenant) do
> [SDD §6.1](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md), cruzados com o
> **contrato de paridade Delphi** ([ANTI-REGRESSAO-cadastro-produto-legacy.md](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md),
> Office Comercial 2026.1.1.38) e com a **ficha Blade que roda em produção hoje**
> (`resources/views/product/view-modal.blade.php`).
> Os UCs derivam do **contrato**, **nunca** do `Show.tsx` — teste derivado do código é tautológico e
> trava o desvio em vez de pegá-lo (`proibicoes.md` §5, 2026-06-05).
>
> **Como este arquivo nasceu:** agent `sdd-from-source` ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)),
> triangulando as **3 fontes** — React (`Show.tsx` → `GET /products/{id}` → `ProductController@show`
> :801-848) + Blade (`show.blade.php` **e** `view-modal.blade.php` + os 3 partials de detalhe) +
> Delphi (as ~120 âncoras `AR-PROD-*`). Fecha `trio:missing-casos:Produto/Show.tsx` do baseline
> do `casos-gate`.
>
> ⚠️ **A ficha legada NÃO é o `show.blade.php`.** O `show()` sem `X-Inertia` devolve
> `view('product.show')`, que é uma tabela de rack de **36 linhas**. A ficha que o operador
> realmente abre na lista é a **modal** `/products/view/{id}` → `view-modal.blade.php` (184 linhas
> + 3 partials). Comparar o React só contra o `show.blade.php` daria "paridade ✅" falsa. A
> paridade real é contra a modal — e é **ela** que gateia custo por permissão.
>
> ⛔ **Escopo honesto (não é incidente de produção).** As telas React do Produto ainda são
> inalcançáveis em prod — a sidebar usa `<a href>` puro, sem header `X-Inertia`, então roda o
> Blade (confirmado por [F] em 2026-07-24, `Edit.casos.md` §Trilha). O que está aqui é
> **bloqueador de migração**: define quando a ficha React pode ser ligada (MWART F5,
> [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) · US-PROD-023).
>
> ⚖️ **Força do veredito destes UC — `advisory`, não bloqueante.** Os testes rodam em
> `PHP / Pest (Estoque · MySQL)` no PR e no fullsuite nightly do CT 100, mas essa lane **não
> consta** em [`governance/required-checks-baseline.json`](../../../../governance/required-checks-baseline.json)
> (as Pest required são Financeiro, NfeBrasil e Unit) — **reprovação é visível e não bloqueia merge**.
>
> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado (stub/sem veredito) ·
> 🔶 backlog (achado a verificar / decisão [W]) · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-PSHOW-01 | Ficha não mostra custo a quem não pode ver preço de compra | must | `AR-PROD-015` + Blade `@can('view_purchase_price')` | `ProdutoShowContratoTest` (Pest) | ⬜ failing-first — vermelho esperado |
| UC-PSHOW-02 | Ficha de produto de outro business → 404 | must `[T0]` | `CU-PROD-10.2` + ADR 0093 + charter §Pest GUARD | `ProdutoShowContratoTest` (Pest) | ⬜ guard — verde esperado |
| UC-PSHOW-03 | Aba Estoque mostra o **nome do local** do rack | must | Blade `view-modal:140` + `AR-PROD-057` | `ProdutoShowContratoTest` (Pest) | ⬜ failing-first — vermelho esperado |
| UC-PSHOW-04 | Aba Variações identifica o **eixo** (Cor - Azul), não só o valor | should | Blade `variable_product_details:30` | `e2e/produto-show.spec.ts` (stub) | ⬜ não verificado |
| UC-PSHOW-05 | Preço de compra e de venda declaram a **mesma base de imposto** | must `[V0]` | Blade (4 colunas exc/inc rotuladas) + REGRA MESTRE | `e2e/produto-show.spec.ts` (stub) | 🔶 decisão [W] — não afirmo bug |
| UC-PSHOW-06 | Abrir a ficha não escreve no banco (GET é leitura pura) | must | charter §Anti-hooks + `AR-PROD-064` | `ProdutoShowContratoTest` (Pest) | ⬜ guard — verde esperado |
| UC-PSHOW-07 | Ficha mostra o alerta de estoque mínimo | should | Blade `view-modal:67-70` + `AR-PROD-053` | `e2e/produto-show.spec.ts` (stub) | ⬜ não verificado |

> ⚠️ **Nenhum status aqui é "✅".** Os 4 UCs com Pest nasceram agora e **não rodei** — testes são
> CT 100/CI ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
> "Vermelho esperado" é **predição ancorada em leitura** (a varredura está em cada UC), não
> veredito. O veredito vem da lane `Estoque · MySQL`, e é ele que manda (G-7).

---

## UC-PSHOW-01 · Ficha não mostra custo a quem não pode ver preço de compra · `must`
- **Persona:** vendedor/balconista de biz=1 com `product.view` mas **sem** direito de ver custo — abre a ficha de um produto pra conferir SKU e disponibilidade. Ele **não pode** ver quanto a empresa pagou pelo item.
- **Aceite:** Dado um usuário com `product.view` e **sem** `view_purchase_price` · Quando abre a ficha de um produto variável · Então **nenhuma variação expõe o preço de compra** — a coluna não existe (não é "vazia", não é read-only).
- **Teste:** [`tests/Feature/Produto/ProdutoShowContratoTest.php`](../../../../tests/Feature/Produto/ProdutoShowContratoTest.php) — `UC-PSHOW-01` (Pest, failing-first, lane `Estoque · MySQL`). Redundância de render em `e2e/produto-show.spec.ts` (stub).
- **Contrato (3 fontes, todas concordando):**
  - **Delphi `AR-PROD-015`** — *"Custo e Margem são gated por permissão — os dois campos **somem** da tela (não ficam read-only) para quem não tem o direito de ver custos (`liedtCusto.Visible := GetPodeVerCustos`)"*.
  - **Blade** — `@can('view_purchase_price')` e `@can('access_default_selling_price')` envolvem as colunas de preço nos **3** partials de detalhe (`single_product_details`, `variable_product_details`, `combo_product_details` — 6 ocorrências em cada) e na própria lista (`product_list.blade.php`). Varredura contada: **47 ocorrências em 15 arquivos** de `resources/views`.
  - **Canon** — `CU-PROD-14` (ficha) + o charter Goal "Multi-tenant scopado" (permissão é a mesma família de contenção).
- **Regressão que defende:** o branch Inertia de `ProductController@show` (`:801-848`) consulta **exatamente 3** permissões — `product.view` (`:803`), `product.update` (`:839`) e `product.delete` (`:840`). Varredura contada de `view_purchase_price|access_default_selling_price`: **12 ocorrências em 5 arquivos** de `app/` — `ProductController` **não está entre eles**; e **0** ocorrências em `Show.tsx`. Resultado: `defaultPurchasePrice` de toda variação viaja pra qualquer um com `product.view`. Ligar a tela React hoje **derruba um gate de custo que o Blade e o Delphi têm.**
- **Status: ⬜** — failing-first; **vermelho esperado**. Só a lane decide.

---

## UC-PSHOW-02 · Ficha de produto de outro business retorna 404 · `must` `[T0]`
- **Persona:** qualquer tenant. O pior bug do projeto é o catálogo de um business vazar pro outro — aqui pela URL da ficha.
- **Aceite:** Dado `GET /products/{id_de_outro_business}` · Quando acesso · Então **404** — nenhum dado do produto alheio chega ao render.
- **Teste:** [`ProdutoShowContratoTest`](../../../../tests/Feature/Produto/ProdutoShowContratoTest.php) — `UC-PSHOW-02`.
- **Contrato:** `CU-PROD-10.2` (*"Cross-tenant por ID → 404, não 403"*) + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) + o próprio `Show.charter.md` §Pest GUARD, que **promete** `it('Controller cross-tenant retorna 404')`.
- **Regressão que defende:** é um **guard** (espera-se verde: `show()` usa `Product::where('business_id', …)->findOrFail()` em `:811-813`), e o valor é travá-lo — a família reincidiu **duas vezes** no mesmo módulo: `UC-PEDIT-03` (`update()` com `first()` → 500, não 404) e `UC-PTAB-04` (`saveSellingPrices` engolia a exceção no `catch` genérico → 302, [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)).
- **⚠️ Achado de governança (não de código):** o charter promete este teste no §Pest GUARD e ele **não existia** — varredura contada em `tests/`: **0** testes citavam cross-tenant de `Produto/Show`. Os 2 arquivos que existem (`Wave2ShowInertiaTest`, `Wave2ShowBaselineTest`) são **string-match no fonte** (`expect($source)->toContain(...)`), que passariam mesmo com o isolamento quebrado. Ver §Achados de governança.
- **Status: ⬜** — verde esperado; sem veredito até a lane rodar.

---

## UC-PSHOW-03 · Aba Estoque mostra o nome do local do rack · `must`
- **Persona:** Larissa / conferente — abre a aba Estoque pra saber **onde** o produto está guardado. "R1 / F2 / P3" sem o nome do local não diz em qual loja/depósito procurar.
- **Aceite:** Dado um produto com rack cadastrado numa localização · Quando abro a aba Estoque da ficha · Então a coluna **Local** mostra o **nome da localização** — não `—`.
- **Teste:** [`ProdutoShowContratoTest`](../../../../tests/Feature/Produto/ProdutoShowContratoTest.php) — `UC-PSHOW-03` (insere `product_racks` e lê a prop deferida `rackDetails`).
- **Contrato:** Blade `view-modal.blade.php:127,140` — a tabela de rack abre com `<th>@lang('business.location')</th>` e preenche `{{$rd->name}}`; **Delphi `AR-PROD-055/057`** — *"Local de Estoque Padrão"* + *"Descrição do Local (RUA 1 - ARMÁRIO 2 - ANDAR 5)"*; charter Goal (aba de estoque na ficha).
- **Regressão que defende:** `ProductUtil::getRackDetails($biz, $id, true)` (`app/Utils/ProductUtil.php:1001-1007`) faz `select(['product_racks.rack','product_racks.row','product_racks.position','BL.name'])` — devolve a chave **`name`**. O `Show.tsx` declara `location_name?` (`:29`) e renderiza `{rd.location_name ?? '—'}` (`:283`). **As chaves não batem** → a coluna "Local" imprime `—` em **toda** linha, sempre. Pelo mesmo mecanismo, `current_stock` (`:33`, renderizado em `:288`) **nunca** é devolvido por `getRackDetails` → a coluna "Estoque atual" também é permanentemente `—`. Duas das três colunas da aba são decorativas.
- **⚠️ Escopo do UC:** cobre o **Local** (que o Blade prova). A coluna **"Estoque atual"** não tem par no `view-modal` (lá o saldo vem de outro bloco, `#view_product_stock_details` via ajax) — então é **backlog**, não UC, pra não inventar contrato (§Backlog).
- **Status: ⬜** — failing-first; **vermelho esperado**.

---

## UC-PSHOW-04 · Aba Variações identifica o eixo da variação · `should`
- **Persona:** Larissa / ROTA LIVRE (vestuário, grade tam×cor) — numa lista de variações, "Azul" e "M" sozinhos não dizem qual é cor e qual é tamanho. A ficha precisa dizer o **eixo**.
- **Aceite:** Dado um produto variável com eixo "Cor" e valor "Azul" · Quando abro a aba Variações · Então a linha identifica **"Cor - Azul"**, não só "Azul".
- **Teste:** `e2e/produto-show.spec.ts` — `UC-PSHOW-04` (stub `test.fixme`).
- **Contrato:** Blade `variable_product_details.blade.php:30` — `{{$variation->product_variation->name}} - {{ $variation->name }}`.
- **Regressão que defende:** o controller **carrega** o eixo (`->with([… 'variations.product_variation' …])`, `:812`) e depois **descarta**: o `map` (`:831-837`) só usa `$v->name`. O eager-load órfão é a evidência de que a intenção existia e se perdeu na migração — exatamente a classe de perda silenciosa que motivou a ADR 0351.
- **Status: ⬜** — stub.

---

## UC-PSHOW-05 · Preço de compra e de venda declaram a mesma base de imposto · `must` `[V0]`
- **Persona:** Wagner / Larissa olhando a ficha pra decidir preço. Se "Preço compra" for **sem** imposto e "Preço venda" **com** imposto, a margem calculada de cabeça sai errada — e ninguém na tela avisa.
- **Aceite:** Dado a aba Variações · Quando exibe preço de compra e de venda na mesma linha · Então ou usam a **mesma base** de imposto, ou o rótulo **declara** a base de cada um.
- **Teste:** `e2e/produto-show.spec.ts` — `UC-PSHOW-05` (stub).
- **Contrato:** Blade `variable_product_details.blade.php:12-20` — o legado exibe **4 colunas explicitamente rotuladas**: compra `(exc_of_tax)`, compra `(inc_of_tax)`, venda `(exc_of_tax)`, venda `(inc_of_tax)`, mais `profit_percent`. A base **nunca** é ambígua. + **REGRA MESTRE** valor ([proibicoes.md](../../../../memory/proibicoes.md)) + `AR-PROD-007` (margem = `((Valor/Custo)−1)×100`, confirmada por 5 caminhos).
- **⚠️ Correção de premissa (2026-07-27):** até esta data o parágrafo abaixo afirmava que
  `defaultSellPrice` vinha de `default_sell_price_inc_tax`, **com** imposto. Esse campo **não existe**
  em `variations` — o Eloquent devolvia `null → 0`, então a ficha não mostrava preço de venda nenhum,
  e a "mistura de bases" descrita aqui era **hipotética**. Provado por dois caminhos: schema/migration
  (`grep` em `database/` = 0) e `UC-PBULK-01` vermelho na lane real
  ([run 30264246760](https://github.com/wagnerra23/oimpresso.com/actions/runs/30264246760)):
  *"Nenhum campo da variação carrega o preço de venda corrente (233.11 nem 256.42)"*. Repontado pra
  `sell_price_inc_tax` por decisão [W] — o que torna este UC um achado **vivo**, não mais mascarado
  pelo zero.
- **⚠️ Por que 🔶 e não achado afirmado:** a varredura mostra que o controller manda `defaultPurchasePrice` ← `default_purchase_price` (**sem** imposto, `:835`) e `defaultSellPrice` ← `sell_price_inc_tax` (**com** imposto, `:836`), e o `Show.tsx` rotula as colunas "Preço compra" / "Preço venda" (`:236-237`) — bases diferentes sob rótulos neutros. **Mas** qual base a ficha *deve* usar é **decisão de produto** ([W]), não dedução minha: pode ser "mostrar as 4 como o Blade", "mostrar só inc", ou "rotular". Afirmar "é bug" seria escolher o remédio antes do diagnóstico (`proibicoes.md` §5, 2026-07-15). **Não toquei em cálculo** — a REGRA MESTRE `[V0]` vale pra quem for mexer.
- **Status: 🔶** — decisão [W]/[F].

---

## UC-PSHOW-06 · Abrir a ficha não escreve no banco · `must`
- **Persona:** qualquer um. Consultar não é alterar — e auditoria de estoque é append-only por lei do projeto.
- **Aceite:** Dado um produto · Quando faço `GET /products/{id}` · Então o `updated_at` do produto **não muda** (e a ficha renderizou de verdade — pré-condição no teste).
- **Teste:** [`ProdutoShowContratoTest`](../../../../tests/Feature/Produto/ProdutoShowContratoTest.php) — `UC-PSHOW-06`.
- **Contrato:** `Show.charter.md` §Anti-hooks — *"❌ Não escreve no banco em GET"* + §Anti-patterns *"❌ Mutação em GET"*; **Delphi `AR-PROD-064`** (movimento rastreia origem/usuário, append-only — não editar/apagar).
- **Regressão que defende:** guard, e não é paranoia no módulo: o irmão `productStockHistory` **faz** um UPDATE em `VariationLocationDetails` dentro do branch `ajax()` de um GET (documentado no cabeçalho do `StockHistoryContratoTest`). A ficha precisa provar que **não** herdou o padrão.
- **Status: ⬜** — verde esperado.

---

## UC-PSHOW-07 · Ficha mostra o alerta de estoque mínimo · `should`
- **Persona:** Larissa — abre a ficha pra saber se precisa repor. O nível de alerta é a informação que responde isso.
- **Aceite:** Dado um produto com `enable_stock` e `alert_quantity` preenchido · Quando abro a ficha · Então o alerta de estoque mínimo **aparece**.
- **Teste:** `e2e/produto-show.spec.ts` — `UC-PSHOW-07` (stub).
- **Contrato:** Blade `view-modal.blade.php:67-70` — `@if($product->enable_stock) <b>@lang('product.alert_quantity')</b> {{$product->alert_quantity}}` + **Delphi `AR-PROD-053`** (*"Quantidades padrões: Estoque Máx. e Estoque Mín. — base de alerta de reposição"*) + charter Goal *"Faixa de reposição visual (mín/máx) — pattern Cowork blueprint"*.
- **Regressão que defende:** **prop morta**. O controller manda `alertQuantity` (`:826`), a interface declara (`Show.tsx:23`) — e a tela **nunca** renderiza: varredura contada, `alertQuantity` aparece **1 vez** no arquivo inteiro (a declaração). Mesmo padrão em `image` (`:25`, controller `:828`): **1 ocorrência**, nunca renderizada, enquanto o Blade exibe a foto do produto (`view-modal:112-114`). O dado chega e é jogado fora.
- **Status: ⬜** — stub.

---

## Achados de governança (o trio prometia o que não tinha)

> Não são bugs de comportamento — são **contratos frouxos** que deixariam a tela ser promovida
> com a rede furada. Cada um verificado por leitura direta de arquivo, não por inferência.

| # | Achado | Evidência |
|---|---|---|
| G-a | O `Show.charter.md` §Pest GUARD promete **5** testes; nenhum dos 5 existe com esse contrato. O que existe é string-match no fonte (`toContain('@/Layouts/AppShellV2')`) — passaria com o isolamento quebrado e com a tela vazia. O prometido `it('Controller cross-tenant retorna 404')` **não existia** (agora é o UC-PSHOW-02). | `Show.charter.md:66-74` × `tests/Feature/Produto/Wave2Show*Test.php` |
| G-b | ⚠️ **CORRIGIDO 2026-07-26 — a redação anterior era falsa (LC-08).** Dizia *"os 2 testes de Show não rodam em lane nenhuma… verdes decorativos"*, concluindo de um `grep` em `.github/workflows/` — análise **file-scoped** vendida como **system-scoped**. `phpunit.xml` (testsuite `Feature`) inclui `./tests/Feature` **recursivamente** e `scripts/tests/shards-plan.mjs` enumera `tests/Feature/Produto` como shard → **os `Wave2Show*` RODAM no fullsuite nightly do CT 100**. O verdadeiro é mais estreito: **não estão na allowlist da lane de PR**, logo não rodam *no PR*. As 3 perguntas são distintas — *roda?* · *roda no PR?* · *bloqueia merge?* | `phpunit.xml` (testsuite Feature) + `scripts/tests/shards-plan.mjs`; allowlist da lane: `estoque-pest.yml`, step `Pest (Estoque)` |
| G-c | ⚠️ **CORRIGIDO 2026-07-26 — era vermelho REAL, não "latente".** O `Wave2ShowInertiaTest` afirmava `file_exists('memory/requisitos/Inventory/RUNBOOK-produto-show.md')`; o arquivo mora em `Produto/_telas/`. Como o teste **roda** no fullsuite (ver G-b), isso vinha reprovando no nightly e somando ao floor. A mesma linha estava copiada em **7 asserts de 6 arquivos** do módulo (`Wave2{BulkEdit,Create,Edit,Index×2,SellingPrices,StockHistory,Show}`) — **todos reapontados** nesta sessão. O `Show.charter.md` §Refs (que repetia o caminho velho enquanto o frontmatter apontava o certo) também foi reconciliado. | `ls memory/requisitos/Inventory/` = `BRIEFING.md`, `SPEC.md` · `grep -rn "requisitos/Inventory" tests/Feature/Produto/` → 0 |
| G-d | O charter promete **Hero KPIs (4 cards)** e **6 abas** (Resumo · Composição · Variações · Preços · Movimento · Fiscal) + "faixa de reposição (mín/máx)". A tela tem **0 KPIs** e **3 abas** (`Show.tsx:54-58`). O RUNBOOK §1 repete a promessa como "estado final esperado". Charter e código discordam há tempo e nada mede. | `Show.charter.md:31-35` × `Show.tsx:54-58` |

---

## Paridade Blade/Delphi→React (gaps do cutover — decisão [W]/[F], não bug afirmado)

> A ficha React migrou ~10 campos. A **modal Blade** (`view-modal.blade.php`, o que o operador vê
> hoje) e o **Delphi** mostram muito mais. Cada linha é âncora que **não** deve sumir sem Non-Goal
> declarado. Vira US/Non-Goal quando [W]/[F] decidir (a ficha segue a US-PROD-023).

| Gap na ficha React | Onde existe no legado | Âncora Delphi | Natureza |
|---|---|---|---|
| **Imagem do produto** | `view-modal:112-114` | — | prop enviada e ignorada (`image`) |
| **20 custom fields** com rótulo do business | `view-modal:23-34` | `AR-PROD-040/041` (classificação) | gap |
| **Localizações onde o produto está disponível** | `view-modal:37-42` | `AR-PROD-055` | gap |
| **Anexo/brochure** (download) | `view-modal:43-51` | `AR-PROD-117..123` (aba Anexo) | gap · PARIDADE §3 |
| **Garantia** (warranty) | `view-modal:72-76` | — | gap |
| **Validade** (expiry period) · **peso** | `view-modal:80-93` | `AR-PROD-124..130` (pesos, aba Fiscal) | gap |
| **Imposto aplicável + tipo (inc/exc)** | `view-modal:94-100` | `AR-PROD-124..130` | gap · pareia com UC-PSHOW-05 `[V0]` |
| **Código de barras / tipo** | `view-modal:17-18` | `AR-PROD-010` (EAN) | gap · CU-PROD-09 |
| **Preço por tabela (group prices) por variação** | `variable_product_details:56-73` | `AR-PROD-090..109` | tela separada (`SellingPrices`) |
| **Imagens por variação** | `variable_product_details:74-78` | — | gap |
| **Saldo de estoque por localização** | `view-modal:165-173` (ajax) | `AR-PROD-144/145` (saldo por local) | gap · pareia UC-PSHOW-03 |
| **Detalhe de combo** | `combo_product_details` | `AR-PROD-150..168` (Composição) | Non-Goal? aba "Variações" não resolve combo |
| **Imprimir a ficha** | `view-modal:176-180` | — | gap |
| **Descrição em HTML** | `view-modal:108` (`{!! !!}`) | `AR-PROD-042` (Observações) | React imprime texto cru (`:203-205`) — **divergência**, provavelmente desejável (XSS); confirmar |
| **Ativo/inativo · última alteração · cadastro** | — (só no Delphi) | `AR-PROD-003/004/013` | gap · mesmo da `Edit.casos.md` |

---

## Âncoras propostas pro SPEC (o agent PROPÕE; [W]/humano aplica)

> **Não aplicadas ao `SPEC.md` neste run** — tocar o SPEC legado do Produto acorda o `anchor-lint`
> diff-aware sobre a dívida grandfathered dele (lápide `proibicoes.md` §5 2026-07-12; o módulo está
> em `cov 11.1%`, 8 de 9 US em `sem_campo`). Ficam como proposta pra [W] aplicar quando a
> US-PROD-023 avançar:

- `US-PROD-023` (promover as 8 telas React) → **Implementado em:** `resources/js/Pages/Produto/Show.tsx` → `GET /products/{id}` → [`ProductController@show`](../../../../app/Http/Controllers/ProductController.php) · casos: `Show.casos.md` · verificado@`<sha-quando-a-lane-rodar>`
- `US-PROD-020` (governança: casos.md + casos-gate) → **Implementado em:** `resources/js/Pages/Produto/Show.casos.md` · teste: [`tests/Feature/Produto/ProdutoShowContratoTest.php`](../../../../tests/Feature/Produto/ProdutoShowContratoTest.php) · verificado@`<sha>`

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG] Coluna "Estoque atual" da aba Estoque** — `Show.tsx:33,288` lê `current_stock`, que
  `getRackDetails` **não devolve** (mesma família do UC-PSHOW-03). Não virou UC porque o
  `view-modal` **não** tem par: lá o saldo vem de outro bloco (`#view_product_stock_details`, ajax).
  Definir de onde o saldo deve vir é decisão de desenho — vira UC quando tiver contrato.
- **[BACKLOG] Hero KPIs (Estoque · Custo · Preço · Vendas no mês)** — prometidos pelo charter e pelo
  RUNBOOK, ausentes na tela. Tocam **valor** → nascem `[V0]` (dupla-confirmação + antes→depois).
  Vira UC quando [W] confirmar que os KPIs entram (e com qual fonte de custo).
- **[BACKLOG] Abas Composição · Preços · Movimento · Fiscal** — charter promete 6, tela tem 3.
  Cada uma tem dono possível em tela irmã (`SellingPrices`, `StockHistory`) — decidir é
  arquitetura de navegação, não caso de uso.
- **[BACKLOG] Ficha de produto `combo`** — a aba se chama "Variações" e lista `variations`; pro
  combo o legado usa `combo_product_details` (componentes do kit). Hoje um combo mostraria a
  variação-fantasma. Depende da decisão `type=combo` × `ProductBom` (PARIDADE §4).
- **[BACKLOG] `/products/view/{id}` (a modal legada) segue viva e sem cobertura** — 184 linhas +
  3 partials, é a ficha real de produção, e nenhum teste a toca. Se a migração ligar a React sem
  desligar a modal, ficam duas fichas divergentes.

---

## Refs

- Charter (lei): [`Show.charter.md`](Show.charter.md) — `v1`, `draft`, `last_validated: 2026-05-15`
- SDD (âncora dos CU): [`SDD-tela-cadastro-produto-v1.0.md`](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) §5.3 F7 · §6.1 `CU-PROD-14`
- Contrato de paridade Delphi: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md)
- Paridade do cutover: [`PARIDADE-charter-vs-legado.md`](../../../../memory/requisitos/Produto/PARIDADE-charter-vs-legado.md) §2
- RUNBOOK MWART: [`_telas/RUNBOOK-produto-show.md`](../../../../memory/requisitos/Produto/_telas/RUNBOOK-produto-show.md)
- Irmãos do trio: [`Edit.casos.md`](Edit.casos.md) · [`Create.casos.md`](Create.casos.md) · [`SellingPrices.casos.md`](SellingPrices.casos.md) · [`StockHistory.casos.md`](StockHistory.casos.md)
- SPEC (a US que promove a tela): [`SPEC.md`](../../../../memory/requisitos/Produto/SPEC.md) — US-PROD-020 · US-PROD-023
- Mecanismo: [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) (`sdd-from-source`) · [errata 0352](../../../../memory/decisions/0352-errata-0351-venue-distiller-citacao-taxonomia.md)
- Gate: `scripts/casos-coverage-guard.mjs` (G-1/G-2/G-5/G-6/G-7 — [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md))

## Trilha do tempo
- 2026-07-26 · [CC] nascido pelo agent `sdd-from-source` ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)) — trio fechado (charter existia + casos novo + `ProdutoShowContratoTest` failing-first + stub e2e), triangulando React + Blade (`view-modal`, não o `show.blade.php` raso) + Delphi. Fecha `trio:missing-casos:resources/js/Pages/Produto/Show.tsx` do baseline. **Achado principal:** a ficha React entrega preço de compra a quem o Blade e o Delphi escondem (UC-PSHOW-01). **Achados de shape:** `location_name`/`current_stock` nunca chegam (UC-PSHOW-03); `alertQuantity`/`image` chegam e são ignorados (UC-PSHOW-07). **Achados de governança:** o §Pest GUARD do charter prometia 5 testes sem lastro, os 2 que existem não rodam em lane e um afirma um caminho de RUNBOOK inexistente. Nenhum UC declarado ✅ — a lane `Estoque · MySQL` publica o veredito.
