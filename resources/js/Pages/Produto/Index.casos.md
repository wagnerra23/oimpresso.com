---
id: resources-js-pages-produto-index-casos
casos: Lista do catálogo · /products
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a lista é a PORTA do catálogo — se ela esconde produto, esconde preço a quem não devia, ou mistura tenant, todo o resto do ERP herda o erro.
owner: wagner
last_run: "2026-07-26"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane Estoque · MySQL"
---

# Casos de Uso & Aceite — Lista do catálogo (`/products`)

> **Âncora:** `CU-PROD-15` (consultar/listar o catálogo) e `CU-PROD-10` `[T0]` (multi-tenant) do
> [SDD §6.1](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md), cruzados com o
> **contrato de paridade Delphi** ([ANTI-REGRESSAO-cadastro-produto-legacy.md](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md),
> Office Comercial 2026.1.1.38 — `AR-PROD-003/015/022/023`) e com a **lista Blade que roda em
> produção hoje** (`resources/views/product/index.blade.php` + o pipeline DataTables de
> `ProductController@index`).
> Os UCs derivam do **contrato**, **nunca** do `Index.tsx` — teste derivado do código é tautológico e
> trava o desvio em vez de pegá-lo (`proibicoes.md` §5, 2026-06-05).
>
> **Como este arquivo nasceu:** agent `sdd-from-source` ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)),
> triangulando as **3 fontes** — React (`Index.tsx` → `GET /products` → `ProductController@index`
> `:64-374` + os 3 builders `:380-494`) + Blade (`index.blade.php`, o **mesmo** método `index()` no
> branch sem `X-Inertia`) + Delphi (âncoras `AR-PROD-*`). Fecha
> `trio:missing-casos:Produto/Index.tsx` do baseline do `casos-gate`.
>
> ⚠️ **Aqui a Blade legada NÃO é um arquivo irmão — é o OUTRO BRANCH DO MESMO MÉTODO.** O
> `index()` serve três consumidores: `request()->ajax()` → DataTables server-side (`:73-312`),
> `X-Inertia` → `Produto/Index` (`:342-359`), e o resto → `view('product.index')` (`:361`). A
> comparação de paridade é **entre os dois branches do mesmo controller**, e é por isso que os
> gaps são tão nítidos: o mesmo autor, no mesmo arquivo, aplica regras diferentes.
>
> ⚖️ **Força do veredito destes UC — `advisory`, não bloqueante.** Os testes rodam em
> `PHP / Pest (Estoque · MySQL)` no PR e no fullsuite nightly do CT 100, mas essa lane **não
> consta** em [`governance/required-checks-baseline.json`](../../../../governance/required-checks-baseline.json)
> (as lanes Pest required são Financeiro, NfeBrasil e Unit). Ou seja: **reprovação aqui é visível
> e não bloqueia merge.** Isso não enfraquece o achado — só evita que a prosa deste arquivo soe
> mais forte que o enforcement real. Promover a required exige mordida provada
> ([ADR 0336](../../../../memory/decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)) + flip [W].
>
> ⛔ **Escopo honesto (não é incidente de produção).** As telas React do Produto seguem
> inalcançáveis em prod — a sidebar usa `<a href>` puro, sem header `X-Inertia`, então roda o
> Blade (confirmado por [F] em 2026-07-24, `Edit.casos.md` §Trilha). O que está aqui é
> **bloqueador de migração**: define quando a lista React pode ser ligada (MWART F5,
> [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) · US-PROD-023).
>
> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado (stub/sem veredito) ·
> 🔶 backlog (achado a verificar / decisão [W]) · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-PIDX-01 | Todo produto do catálogo é alcançável na lista (sem corte silencioso) | must | Blade DataTables server-side + `AR-PROD-022/023` | `ProdutoIndexContratoTest` (Pest) | ⬜ failing-first — vermelho esperado |
| UC-PIDX-02 | Busca é resolvida no servidor e acha por SKU de variação (`sub_sku`) | must | Blade `filterColumn('products.sku')` + `CU-PROD-02` | `ProdutoIndexContratoTest` (Pest) | ⬜ failing-first — vermelho esperado |
| UC-PIDX-03 | Preço e custo na lista respeitam a permissão de vê-los | must | Blade `@can` ×2 (`resources/views/product/index.blade.php:287 (verificado@5d5cac0),294`) + `AR-PROD-015` | `ProdutoIndexContratoTest` (Pest) | ⬜ failing-first — vermelho esperado |
| UC-PIDX-04 | Lista, KPIs e contadores só enxergam o business atual | must `[T0]` | `CU-PROD-10` + ADR 0093 + charter §Anti-hooks | `ProdutoIndexContratoTest` (Pest) | ⬜ guard — verde esperado |
| UC-PIDX-05 | Abrir a lista não escreve no banco (GET é leitura pura) | must | charter §Anti-hooks + `AR-PROD-064` | `ProdutoIndexContratoTest` (Pest) | ⬜ guard — verde esperado |
| UC-PIDX-06 | "Mostrar inativos" é resolvido no servidor | should | `AR-PROD-003/022` + Blade `active_state` (`:173-179`) | `ProdutoIndexContratoTest` (Pest) | ⬜ failing-first — vermelho esperado |

> ⚠️ **Nenhum status aqui é "✅".** Os 6 UCs nasceram agora e **não rodei** — testes são CT 100/CI
> ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
> "Vermelho esperado" é **predição ancorada em leitura** (a varredura está em cada UC), não
> veredito. O veredito vem da lane `Estoque · MySQL`, e é ele que manda (G-7).

---

## UC-PIDX-01 · Todo produto do catálogo é alcançável na lista · `must`
- **Persona:** Larissa (biz=4 no mundo real; biz=1 no teste) procurando a camiseta que acabou de cadastrar. Se a lista some com o produto **sem avisar**, ela conclui que o cadastro falhou e cadastra de novo — duplicata no catálogo.
- **Aceite:** Dado um catálogo com mais produtos do que cabe numa página · Quando abro `/products` · Então **todo** produto ativo é alcançável (por paginação, scroll infinito ou busca server-side) — e a quantidade exibida **bate** com o total anunciado no KPI.
- **Teste:** [`tests/Feature/Produto/ProdutoIndexContratoTest.php`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) — `UC-PIDX-01` (Pest, failing-first, lane `Estoque · MySQL`).
- **Contrato (2 fontes):**
  - **Blade** — o branch `request()->ajax()` (`ProductController@index:73-312`) entrega `Datatables::of($products)` **sem `limit`**: o DataTables pagina server-side e alcança o catálogo inteiro. É o comportamento que roda em produção hoje.
  - **Delphi `AR-PROD-022/023`** — *"Consultar — abre busca/listagem de produtos"* e *"o cadastro vira inativo e **some da lista, mas fica acessível por filtro**"*: no legado a lista é o caminho garantido pra chegar em **qualquer** produto; o que tira algo da vista é **filtro declarado**, nunca um corte invisível.
- **Regressão que defende:** `buildProdutoIndexRows` (`:425-448`) fecha com `->orderBy('products.name')->limit(200)` e **não há paginação em lugar nenhum** — nem prop, nem `<Deferred>` incremental, nem link. Varredura contada em `Index.tsx`: **0** ocorrências de `page`/`paginate`/`next`. O KPI "Total de produtos" (`buildProdutoIndexKpis:382-385`) conta o catálogo **inteiro**. Resultado: com 1.000 produtos, a tela anuncia 1.000 e entrega 200 — os 800 que sobram são os do fim do alfabeto, e **nada na tela diz que foram cortados**. O `EmptyState` ("Ajuste filtros ou cadastre o primeiro produto") reforça a leitura errada.
- **Nota de denominador:** os três builders usam recortes **diferentes** — `kpis.total` exclui `type='modifier'` e conta tudo; `rows` exclui `modifier` e corta em 200; `categorias` (`:477-494`) conta **todos** os produtos da categoria (inclusive `modifier` e inativos). Os números da mesma tela não fecham entre si. O UC trava o eixo principal (alcance); a divergência de contadores está no §Backlog.
- **Status: ⬜** — failing-first; **vermelho esperado**. Só a lane decide.

---

## UC-PIDX-02 · Busca é resolvida no servidor e acha por SKU de variação · `must`
- **Persona:** balconista com a etiqueta do produto na mão. A etiqueta traz o **SKU da variação** (`sub_sku`, ex. `CAM-AZ-M`), não o SKU do produto-pai. Digitar o que está na etiqueta tem que achar o item.
- **Aceite:** Dado um produto variável cujas variações têm `sub_sku` próprio · Quando busco pelo `sub_sku` de uma variação · Então o produto aparece — e produtos que **não** casam com o termo **não** aparecem.
- **Teste:** [`ProdutoIndexContratoTest`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) — `UC-PIDX-02`.
- **Contrato (3 fontes):**
  - **Blade** — `filterColumn('products.sku', …)` (`:296-301`) busca `whereHas('variations', sub_sku like %termo%)` **`orWhere` `products.sku`**: o legado procura explicitamente no SKU da variação. Server-side, sobre o catálogo inteiro.
  - **`CU-PROD-02`** (SDD §6.1) — *"Grade tam×cor gera N variações; cada uma com `sub_sku` auto"*: o `sub_sku` é a identidade operacional da variação; buscar por ele é o uso previsto.
  - **`AR-PROD-023`** (Delphi) — *"Consultar — abre busca/listagem"*.
- **Regressão que defende:** o `filters.busca` chega ao controller (`:345`) e é **devolvido à página sem ser usado em query nenhuma** — `buildProdutoIndexRows` não recebe o termo (`:350` passa só `$business_id`). A filtragem acontece **no cliente** (`Index.tsx:126-131 (verificado@d4afe95)`), sobre `r.name` e `r.sku` do **produto-pai**, dentro do recorte de 200 do UC-PIDX-01. Varredura contada: `sub_sku` aparece **0 vezes** em `Index.tsx` e **0 vezes** nos 3 builders. Logo, buscar pela etiqueta da variação **não acha nada**, e buscar por nome só acha entre os 200 primeiros.
- **Por que o teste precisa do assert negativo:** como o servidor **ignora** o termo, um teste que só pergunte *"o produto buscado veio?"* passaria **por acidente** (ele vem porque vem tudo). O caso assere também que quem **não** casa fica de fora — sem isso o teste mediria não-execução (`proibicoes.md` §5, 2026-07-24).
- **Status: ⬜** — failing-first; **vermelho esperado**.

---

## UC-PIDX-03 · Preço e custo na lista respeitam a permissão de vê-los · `must`
- **Persona:** vendedor de biz=1 com `product.view`, **sem** direito de ver custo. Ele consulta o catálogo pra conferir SKU e disponibilidade; quanto a empresa pagou pelo item **não é dele**.
- **Aceite:** Dado um usuário sem `view_purchase_price` / `access_default_selling_price` · Quando abre a lista · Então **nenhuma linha** carrega o valor correspondente — o dado não chega ao navegador (não é "coluna escondida por CSS").
- **Teste:** [`ProdutoIndexContratoTest`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) — `UC-PIDX-03`.
- **Contrato (3 fontes, todas concordando):**
  - **Blade, na PRÓPRIA lista** — `resources/views/product/index.blade.php:287 (verificado@d4afe95)` e `:294` envolvem as colunas `purchase_price` e `selling_price` em `@can('view_purchase_price')` / `@can('access_default_selling_price')`. Varredura contada: **2** ocorrências no arquivo da lista; **14** arquivos de `resources/views` usam o gate (5 deles do Produto).
  - **Delphi `AR-PROD-015`** — *"Custo e Margem são gated por permissão — os dois campos **somem** da tela"* (`liedtCusto.Visible := GetPodeVerCustos`). Não é read-only: é ausência.
  - **Precedente do módulo** — `UC-PSHOW-01` (mesma família, na ficha) já está em failing-first; este é o mesmo furo na porta de entrada, onde o dado sai em **lote**.
- **Regressão que defende:** `buildProdutoIndexRows` (`:450-471`) monta **`price`**, **`cost`** e **`margin`** para **toda** linha, sem consultar permissão alguma. As únicas permissões que o branch Inertia lê são `create`/`update`/`delete`/`opening_stock` (`:352-357`) — varredura contada de `view_purchase_price|access_default_selling_price` em `ProductController.php`: **0**. O card renderiza `fmtBRL(row.price)` (`Index.tsx:403 (verificado@d4afe95)`) sem gate; `cost` e `margin` não são renderizados mas **viajam no JSON das props** — visíveis no HTML da página. Ligar a lista React hoje **derruba um gate de custo que o Blade e o Delphi têm**, e em escala (todo o catálogo de uma vez, não um produto por vez).
- **Não é `[V0]`:** não altera cálculo de valor — gateia **exibição**. A REGRA MESTRE (dupla-confirmação + antes→depois) não se aplica; marcar `[V0]` aqui inflaria o ritual sem proteger nada.
- **Status: ⬜** — failing-first; **vermelho esperado**.

---

## UC-PIDX-04 · Lista, KPIs e contadores só enxergam o business atual · `must` `[T0]`
- **Persona:** qualquer tenant. O pior bug do projeto é o catálogo de um business vazar pro outro — aqui em lote, na primeira tela que o operador abre.
- **Aceite:** Dado dois businesses com produtos · Quando abro `/products` no business A · Então nenhuma linha, KPI ou contador de categoria reflete produto do business B.
- **Teste:** [`ProdutoIndexContratoTest`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) — `UC-PIDX-04`.
- **Contrato:** `CU-PROD-10` `[T0]` (SDD §6.1) + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) + o próprio `Index.charter.md` §Automation Anti-hooks (*"❌ Não acessa produto de outro `business_id`"*) e §Pest GUARD, que **promete** `it('isolates products by business_id')`.
- **Regressão que defende:** é um **guard** (espera-se verde — os 3 builders carimbam `where('business_id', $businessId)`), e o valor é travá-lo, porque a contenção aqui é **acidental, não estrutural**: varredura contada em `app/Product.php` — **0** ocorrências de `addGlobalScope`; o model `extends Model` e usa só `LogsActivity`. **`App\Product` não tem global scope de `business_id`.** Todo o isolamento do módulo depende de cada query lembrar do `where` — e a família já reincidiu duas vezes (`UC-PTAB-04`, cross-tenant gravando em `variation_group_prices`, [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300); `UC-PEDIT-03`, `first()` → 500 em vez de 404).
- **⚠️ Achado de contrato (documentado, não afirmado como vazamento):** `buildProdutoIndexCategorias` (`:479-486`) faz `leftJoin('products', 'products.category_id', '=', 'categories.id')` e conta `COUNT(products.id)` **sem** carimbar `products.business_id`. Hoje isso não vaza porque `category_id` de um produto aponta pra categoria do próprio business — mas a contenção é **coincidência de dado**, não guard: num model sem global scope, a próxima agregação que junte `products` sem `where` vaza. O teste trava o invariante observável (contador não conta o alheio); **não** fabrico dado corrompido pra "provar" vazamento que hoje não ocorre.
- **Status: ⬜** — verde esperado; sem veredito até a lane rodar.

---

## UC-PIDX-05 · Abrir a lista não escreve no banco · `must`
- **Persona:** qualquer operador. Consultar o catálogo é o gesto mais frequente do ERP — se ele muta estado, a auditoria vira ruído e o `updated_at` deixa de significar "alguém alterou".
- **Aceite:** Dado um produto com `updated_at` conhecido · Quando abro a lista (inclusive as props deferidas) · Então nada é escrito — `updated_at` intacto e nenhum log de atividade novo.
- **Teste:** [`ProdutoIndexContratoTest`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) — `UC-PIDX-05`.
- **Contrato:** `Index.charter.md` §Automation Anti-hooks — *"❌ Não escreve no banco (read-only)"*, *"❌ Não roda jobs"*, *"❌ Não dispara emails"* — + **`AR-PROD-064`** (movimento é append-only; consulta não muta) + `CU-PROD-15.4`.
- **Regressão que defende:** é **guard**, e o irmão prova que a família existe neste módulo: `productStockHistory` **faz UPDATE dentro de um GET** no branch `ajax()` (documentado em `CU-PROD-11` / `StockHistory.casos.md`). A lista é o GET mais chamado do módulo; se um dia alguém pendurar "atualiza popularidade ao listar" (o `popularity` está hardcoded 0 esperando implementação — §Backlog), o teste morde.
- **Status: ⬜** — verde esperado.

---

## UC-PIDX-06 · "Mostrar inativos" é resolvido no servidor · `should`
- **Persona:** Larissa com catálogo grande. Produto inativo é ruído no dia a dia — e, quando ela **precisa** dele (reativar, consultar preço antigo), o filtro tem que trazê-lo de volta.
- **Aceite:** Dado um produto inativo e um ativo · Quando abro a lista com o toggle **desligado** (default) · Então o inativo **não vem no payload**; quando ligo o toggle · Então ele vem, marcado como inativo.
- **Teste:** [`ProdutoIndexContratoTest`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) — `UC-PIDX-06`.
- **Contrato (3 fontes):**
  - **Blade** — `active_state` chega por request e o servidor aplica `$products->Active()` / `->Inactive()` (`ProductController@index:173-179`). É filtro de query, não de tela.
  - **Delphi `AR-PROD-022`** — *"o cadastro vira inativo e some da lista, mas fica acessível por filtro de excluídos/inativos"*; **`AR-PROD-003`** — *"produto inativo não some do cadastro, só do fluxo de venda"*.
  - **Charter** §Goals — *"Toggle 'Mostrar inativos' (default: oculto)"*.
- **Regressão que defende:** `filters.mostrarInativos` é lido (`:347`) e **devolvido sem uso**; `buildProdutoIndexRows` não filtra por `is_inactive` — manda ativos e inativos sempre. O ocultamento acontece no cliente (`Index.tsx:120-122 (verificado@d4afe95)`), **depois** do corte de 200: um catálogo com muitos inativos gasta as 200 vagas com linhas que a tela vai esconder, e produtos ativos legítimos somem da vista (composição com UC-PIDX-01). Pelo mesmo caminho, o badge do Blade para `not_for_selling` (`:263-264`) não tem par no React — §Backlog.
- **Status: ⬜** — failing-first; **vermelho esperado**.

---

## Achados de governança (o trio prometia o que não tinha)

> Não são bugs de comportamento — são **contratos frouxos** que deixariam a tela ser promovida
> com a rede furada. Cada um verificado por leitura direta de arquivo, não por inferência.
> Os corrigíveis-por-fato foram reconciliados no charter neste mesmo PR (Fase 2.6 do `sdd-from-source`);
> os que exigem decisão de produto ficam abertos pro [W] (§Divergências).

| # | Achado | Evidência |
|---|---|---|
| G-a | O `Index.charter.md` §Pest GUARD promete **10** testes em `tests/Feature/Produto/IndexCharterTest.php`. **O arquivo não existe.** O que existe (`Wave2Index{Inertia,Baseline}Test.php`) é string-match no fonte (`expect($source)->toContain(...)`) — passaria com o isolamento quebrado, com a lista vazia e com o preço vazando. Os 6 UCs deste arquivo cobrem 5 das 10 promessas com comportamento real. | `Index.charter.md:121-136` × `ls tests/Feature/Produto/` |
| G-b | ⚠️ **CORRIGIDO 2026-07-26 — a redação anterior era falsa (LC-08).** Dizia *"os 2 testes de Index não rodam em lane nenhuma… verdes decorativos"*, concluindo isso de um `grep` em `.github/workflows/`. Isso é análise **file-scoped** apresentada como **system-scoped**: `phpunit.xml` inclui `./tests/Feature` **recursivamente** e `scripts/tests/shards-plan.mjs` enumera `tests/Feature/Produto` como shard → **os `Wave2Index*` RODAM no fullsuite nightly do CT 100**. O que é verdade é mais estreito: não estão na allowlist da lane de PR, logo **não rodam no PR** — mas rodam no nightly. As 3 perguntas são distintas: *roda?* (`phpunit.xml` + `shards-plan`) · *roda no PR?* (allowlist) · *bloqueia merge?* (`required-checks-baseline.json`). | `phpunit.xml` (testsuite Feature) + `scripts/tests/shards-plan.mjs` |
| G-c | ⚠️ **CORRIGIDO 2026-07-26 — era vermelho REAL, não "latente".** `Wave2IndexInertiaTest.php:106 (verificado@d4afe95),110` afirmava `file_exists('memory/requisitos/Inventory/…')`; os arquivos moram em `Produto/_telas/`. Como o arquivo **roda** no fullsuite (ver G-b), isso vinha reprovando no nightly e contribuindo pro floor. **Os 7 asserts quebrados do módulo foram reapontados** (`Wave2{BulkEdit,Create,Edit,Index×2,SellingPrices,StockHistory}InertiaTest`) — o do `Show` já havia sido corrigido antes. | `ls memory/requisitos/Inventory/` = `BRIEFING.md`, `SPEC.md` · `grep -rn "requisitos/Inventory" tests/Feature/Produto/` → 0 |
| G-d | Charter §Automation Hooks declarava **2 endpoints inexistentes**: `GET /produto` (a rota é `/products`) e `GET /produto/{id}/sheet-data` — varredura contada: `sheet-data` existe **só** para `/sells/{id}` (`routes/web.php:485 (verificado@d4afe95)`), **0** ocorrências para produto. Corrigido no charter (fato). | `grep -rn "sheet-data" routes/ app/` |
| G-e | Charter §Goals declarava o gate `product.view_own`. Varredura contada em `app/`, `Modules/`, `routes/`, `resources/` (excl. charters): **0** ocorrências — a permissão **não existe** no projeto. O gate real é `product.view` **ou** `product.create` (`:66-68`). Corrigido no charter (fato). | `grep -rn "product.view_own"` |
| G-f | Charter §Refs apontava `ui_kits/cowork-2026-05-09/prod-page.jsx` — caminho **inexistente**. O blueprint vivo é `prototipo-ui/cowork/produtos-page.jsx`, que o próprio frontmatter (`bundle_source`) já declara. Corrigido no charter (fato). | `ls ui_kits/…` → No such file |
| G-g | O charter está `status: live` (promovido em 2026-07-12 por `charter-promote-signal.mjs`, sinal `route-hits:16`) enquanto o SDD §1.1 e o SPEC afirmam *"as 8 telas existem, nenhuma é `live`"*. **Não reconciliei**: o critério de promoção é decisão de processo, não fato de arquivo. Ver §Divergências. | `Index.charter.md:8,166` × `SDD §1.1` × `SPEC.md:18` |

---

## Divergências abertas — decisão [W] (não escolhi vencedor)

> Fase 2.6 do `sdd-from-source`: **fato** eu corrijo; **intenção** e **promessa não cumprida** eu
> registro nos dois lados e levo pro dono. Nenhum item abaixo foi alterado no charter.

| # | Divergência | Os dois lados |
|---|---|---|
| D-1 | **Popularidade é fachada.** O charter promete "barra de popularidade" no card e o KPI "Populares (popularity ≥ 70)". O controller devolve `'popularity' => 0` fixo (`:467`, comentário *"deferred a Wave 3"*) — **toda** barra fica em 0% e o ramo emerald (`≥70`, `Index.tsx:411 (verificado@d4afe95)`) é código morto. Pior: o KPI "Populares" usa outra régua (**≥30 vendas em 30d**, `:401-409`) e o subtítulo da tela diz "≥30 vendas/mês" — duas definições de "popular" na mesma tela. | Podar a promessa do charter **ou** implementar. Decisão de produto. |
| D-2 | **Card abre drawer ou navega?** Charter §Goals: *"Click card abre drawer (mesma DetailSheet do `/produto/unificado`)"*. Código: `<Link href={/products/{id}}>` (`Index.tsx:423 (verificado@d4afe95)`) — navega pra ficha. Além disso o link é gated por `permissions.update`, enquanto a ficha exige `product.view`: **quem só pode ver não consegue clicar**, e quem pode editar clica pra uma tela de leitura. | Drawer (charter) × navegação (código) é desenho; o gate invertido é bug derivado da escolha. |
| D-3 | **O menu de Ações da lista.** O Blade oferece **10 ações por linha** (`:202-258`: etiqueta/código de barras · ver · editar · excluir · reativar · estoque inicial · histórico de estoque · tabela de preço · duplicar · baixar anexo). O React oferece **1** interação (o card inteiro como link, contado: 2 `<Link>` no arquivo, um deles o botão "Novo produto"). O charter declara Non-Goal para **parte** disso (`❌ CRUD inline`, `❌ Bulk actions`, `❌ Stock management`, `❌ edição de preço inline`) — mas **não** para etiqueta/código de barras (`CU-PROD-09`), histórico de estoque (`CU-PROD-11`), tabela de preço (`CU-PROD-03`), duplicar (`CU-PROD-07`) nem reativar (`AR-PROD-003/022`). | É **exatamente** a classe de regressão que motivou este agent (o menu de Ações da lista de vendas que sumiu no rewrite #1032). Não virou UC porque decidir *quais* ações a lista lite deve ter é escopo de produto — mas as 5 sem Non-Goal precisam de veredito: entram ou viram Non-Goal explícito. |
| D-4 | **`status: live` × "nenhuma tela é live".** Ver G-g. O charter foi promovido por sinal automático de tráfego (`route-hits:16`) enquanto o SDD/SPEC tratam a tela como `draft` e a US-PROD-023 ainda pede "finalizar + promover". Ou o sinal está medindo o Blade (mesma rota!), ou o SDD está stale. | Suspeito do **primeiro**: `/products` serve os dois branches, então "route-hits" não distingue React de Blade. Se for isso, o promotor automático está promovendo telas React por tráfego do Blade — vale pras 8. |

---

## Paridade Blade→React (gaps do cutover — decisão [W]/[F], não bug afirmado)

> A lista React mostra 6 atributos por card. O **mesmo método** `index()`, no branch DataTables,
> entrega 14 colunas + 7 filtros + 2 ações em massa. Cada linha é âncora que **não** deve sumir
> sem Non-Goal declarado.

| Gap na lista React | Onde existe no legado | Âncora Delphi | Natureza |
|---|---|---|---|
| **Saldo em estoque na linha** | coluna `current_stock` (`:279-287`, com unidade) | `AR-PROD-012` (Quant. Estoque) | prop declarada (`stockQty`) e **sempre `null`** (`:465`) |
| **Imagem do produto** | coluna `image` (`:272-274`) | — | ausente (card usa ícone genérico) |
| **Localizações do produto** | coluna `product_locations` (`:196-200`) | `AR-PROD-055` | ausente |
| **Marca · unidade · imposto · tipo** | colunas `brand`/`unit`/`tax`/`type` | `AR-PROD-014` (Tipo) | só `unit` chega; resto ausente |
| **20 custom fields** (7 na grade) | `:326-357` | `AR-PROD-040/041` | ausente |
| **Filtros: tipo · marca · unidade · imposto · localização · not-for-selling** | `resources/views/product/index.blade.php:21-110 (verificado@5d5cac0)` (7 filtros) | `AR-PROD-060` (filtros de consulta) | só categoria + inativos; charter declara Non-Goal p/ "filtros avançados" (backlog) |
| **Seleção em massa + excluir/desativar selecionados** | `mass_delete` (`:276-278`) + `:438-496` | `AR-PROD-022` (soft-delete) | Non-Goal declarado no charter (`❌ Bulk actions`) — **ok, não é regressão** |
| **Aba "Relatório de estoque"** (custo, valor de venda, lucro potencial) | 2ª tabela do mesmo Blade (`:617-670`) | `AR-PROD-051/061` | ausente · pareia com `CU-PROD-12` `[V0]` (agregação de valor) |
| **Badge "não vendável"** (`not_for_selling`) | `:263-264` | — | ausente (só o badge "inativo" migrou) |
| **Selo WooCommerce sincronizado** | `:266-268` | — | ausente |
| **Ordenação por qualquer coluna** | DataTables server-side | `AR-PROD-062` (grid agrupável) | ausente (ordem fixa por nome) |

---

## Âncoras propostas pro SPEC (o agent PROPÕE; [W]/humano aplica)

> **Não aplicadas ao `SPEC.md` neste run** — tocar o SPEC legado do Produto acorda o `anchor-lint`
> diff-aware sobre a dívida grandfathered dele (lápide `proibicoes.md` §5 2026-07-12; o módulo está
> em `cov 11.1%`, 8 de 9 US em `sem_campo`). Ficam como proposta pra [W] aplicar quando a
> US-PROD-023 avançar:

- `US-PROD-023` (promover as 8 telas React) → **Implementado em:** `resources/js/Pages/Produto/Index.tsx` → `GET /products` → [`ProductController@index`](../../../../app/Http/Controllers/ProductController.php) · casos: `Index.casos.md` · verificado@`<sha-quando-a-lane-rodar>`
- `US-PROD-020` (governança: casos.md + casos-gate) → **Implementado em:** `resources/js/Pages/Produto/Index.casos.md` · teste: [`tests/Feature/Produto/ProdutoIndexContratoTest.php`](../../../../tests/Feature/Produto/ProdutoIndexContratoTest.php) · verificado@`<sha>`

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG] Os três denominadores da tela não fecham** — `kpis.total` (todos, sem `modifier`),
  `rows` (sem `modifier`, cortado em 200) e `categorias[].count` (**tudo**, inclusive `modifier` e
  inativos). A soma das abas ≠ total do KPI ≠ cards na tela. Não virou UC porque o número correto
  de cada contador depende de qual pergunta a tela responde — decisão de desenho.
- **[BACKLOG] `stockQty` sempre `null`** — a interface declara (`Index.tsx:56 (verificado@d4afe95)`), o builder manda
  `null` fixo (`:465`) e o card **não renderiza**. O Blade tem a coluna `current_stock` com unidade.
  Vira UC quando [W] confirmar que o saldo entra no card (toca `[V0]`: saldo é estoque).
- **[BACKLOG] Badge "não vendável" (`not_for_selling`)** — existe no Blade (`:263-264`), some no
  React. Sem âncora Delphi clara; 1 fonte só.
- **[BACKLOG] Ordenação e filtros avançados** — o charter declara "filtros avançados" como backlog
  explícito; a ordenação por coluna não tem Non-Goal nem contrato. Vira UC junto com a paginação
  (UC-PIDX-01), porque as duas dependem de listar server-side.
- **[BACKLOG] O promotor automático de charter pode estar lendo tráfego do Blade** — `/products`
  serve os dois branches; `route-hits` não distingue. Se confirmado, afeta as 8 telas do módulo e
  qualquer tela em branch dual MWART. É achado de **mecanismo**, não de tela — vira issue de
  governança, não UC.

---

## Refs

- Charter (lei): [`Index.charter.md`](Index.charter.md) — `v2`, `status: live`, `last_validated: 2026-07-12`
- SDD (âncora dos CU): [`SDD-tela-cadastro-produto-v1.0.md`](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) §5.3 F8 · §6.1 `CU-PROD-15`
- Contrato de paridade Delphi: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md)
- Paridade do cutover: [`PARIDADE-charter-vs-legado.md`](../../../../memory/requisitos/Produto/PARIDADE-charter-vs-legado.md) §2
- RUNBOOK MWART: [`_telas/RUNBOOK-produto-index.md`](../../../../memory/requisitos/Produto/_telas/RUNBOOK-produto-index.md)
- Irmãos do trio: [`Show.casos.md`](Show.casos.md) · [`Edit.casos.md`](Edit.casos.md) · [`Create.casos.md`](Create.casos.md) · [`SellingPrices.casos.md`](SellingPrices.casos.md) · [`StockHistory.casos.md`](StockHistory.casos.md)
- SPEC (a US que promove a tela): [`SPEC.md`](../../../../memory/requisitos/Produto/SPEC.md) — US-PROD-020 · US-PROD-023
- Mecanismo: [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) (`sdd-from-source`) · [errata 0352](../../../../memory/decisions/0352-errata-0351-venue-distiller-citacao-taxonomia.md)
- Gate: `scripts/casos-coverage-guard.mjs` (G-1/G-2/G-5/G-6/G-7 — [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md))

## Trilha do tempo
- 2026-07-26 · [CC] nascido pelo agent `sdd-from-source` ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)) — 3ª tela do módulo (depois de `Edit` e `Show`), trio fechado (charter existia + casos novo + `ProdutoIndexContratoTest` failing-first). Triangulação incomum: aqui a Blade legada **é o outro branch do mesmo método** `index()`, então a paridade é comparação interna do controller. **Achado principal:** a lista React entrega `price`/`cost`/`margin` de todo o catálogo sem consultar permissão, enquanto a **própria lista Blade** gateia as duas colunas com `@can` (UC-PIDX-03) — é o `UC-PSHOW-01` de novo, agora em lote. **Achado de alcance:** `limit(200)` sem paginação, com KPI anunciando o total — perda silenciosa (UC-PIDX-01). **Achado de arquitetura:** `App\Product` **não tem global scope** (`addGlobalScope` = 0 ocorrências); o isolamento do módulo inteiro é `where` manual repetido (UC-PIDX-04). **Governança:** o §Pest GUARD prometia 10 testes e o arquivo não existe; 3 refs factuais do charter estavam podres e foram corrigidas. Nenhum UC declarado ✅ — a lane `Estoque · MySQL` publica o veredito.
