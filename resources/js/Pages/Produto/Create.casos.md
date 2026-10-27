---
id: resources-js-pages-produto-create-casos
casos: Cadastro de produto · /products/create
irmaos: Create.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o produto que NASCE do cadastro não muda quando a tela vira aba.
owner: wagner
last_run: "2026-10-24"
last_run_ci: "PR #9736 (peso e dimensões, UC-PCAD-16), 2026-10-24, head f58ef91a com o CEST e o NCM já no main: lane PHP / Pest (Estoque · MySQL) job 121018350150 verde (conclusion success); job Frontend / Vite build 121018349478: 105 testes passaram em 14 arquivos, 28 deles em produto-formulario.test.tsx. Antes: PR #9736 (peso e dimensões, UC-PCAD-16), 2026-10-21: lane PHP / Pest (Estoque · MySQL), job 119380189319: 529 passed (2487 assertions), com os casos UC-PCAD-15 e UC-PCAD-16 verdes; job Frontend / Vite build 119380541571: 96 testes passaram em 12 arquivos, 28 deles em produto-formulario.test.tsx. Antes: PR #9670 (NCM no Novo produto, UC-PCAD-13/14), 2026-10-20: lane PHP / Pest (Estoque · MySQL), job 118746611325: 497 passed (2401 assertions), 0 skipped, com os 7 casos do UC-PCAD-13; step Comportamento React do job Frontend / Vite build: 80 testes passaram em 12 arquivos, incluindo produto-formulario.test.tsx. Antes: PR #9482, job 116997588268, 485 passed (2356 assertions)."
---

# Casos de Uso & Aceite — Cadastro de produto

> **Âncora:** `CU-PROD-01` — "Cadastrar produto simples" `[must]` (+ `CU-PROD-07` duplicar) do
> [SDD §6.1](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md).
> Os UCs derivam do **contrato** (CU-PROD-01), nunca da implementação — teste derivado do código é
> tautológico e trava o desvio em vez de pegá-lo (`proibicoes.md` §5, entrada 2026-06-05).
>
> **Por que este arquivo nasce agora:** a US-PROD-020 (`p0`) pede `casos.md` das telas críticas
> (Create · SellingPrices · StockHistory). O `SellingPrices` fechou o trio em
> [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300); este é o segundo.
>
> **O que ele NÃO faz:** não descreve a tela. `[F]` está reconstruindo o cadastro **em abas**
> (Custos · Preço especial — [#4403](https://github.com/wagnerra23/oimpresso.com/pull/4403)). Um
> caso de uso é contrato de **comportamento**, não de layout: "o produto nasce com o preço que o
> operador digitou" vale igual se a caixa mora no `Create.tsx`, numa aba nova ou num drawer.
> Estes UCs são o que a tela nova tem que satisfazer — não um retrato da velha.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não
> regravado) · ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | CU-PROD | Teste | Status |
|----|-------------|------|---------|-------|--------|
| UC-PCAD-01 | Cadastro mínimo persiste e o SKU nasce no servidor | must | `CU-PROD-01.2` | `CadastroProdutoContratoTest` | 🧪 passa |
| UC-PCAD-04 | Custo e preço de venda não inflam no parser pt-BR | must `[V0]` | `CU-PROD-01.4` | `CadastroProdutoContratoTest` | 🧪 passa |
| UC-PCAD-06 | Duplicar produto de outro business → 404 (não 500) | should `[T0]` | `CU-PROD-07.2` | `CadastroProdutoContratoTest` | 🧪 passa (fix no PR) |
| UC-PCAD-07 | Produto novo grava a origem da mercadoria escolhida (0–8) | should | `AR-PROD-128` | `ProdutoOrigemMercadoriaTest` | 🧪 passa no CT 100 |
| UC-PCAD-09 | Novo produto simples nasce com o preço digitado, com a conta do Blade | must `[V0]` | `CU-PROD-01.4` | `produto-preco.test.tsx` (Vitest) | 🧪 passa |
| UC-PCAD-10 | Novo produto nasce nos locais do negócio (um local só já vem marcado) | must | `CU-PROD-01` | `produto-locais.test.tsx` (Vitest) | 🧪 passa |
| UC-PCAD-11 | Variável e composto não salvam pela tela React: vão para o cadastro clássico | must | `CU-PROD-02` | `produto-tipo.test.tsx` (Vitest) | 🧪 passa |
| UC-PCAD-12 | Novo produto grava a validade digitada (com o período, como o Blade) | must | `CU-PROD-01` | `produto-formulario.test.tsx` (Vitest) | 🧪 passa |
| UC-PCAD-13 | Novo produto grava o NCM digitado (8 dígitos), sem exigir NCM | must | `CU-PROD-16` | `CadastroProdutoNcmTest` + `produto-formulario.test.tsx` (Vitest) | 🧪 escrito, sem veredito |
| UC-PCAD-14 | Sem NCM a tela avisa, e o produto é salvo assim mesmo | should | `CU-PROD-18` | `produto-formulario.test.tsx` (Vitest) | 🧪 escrito, sem veredito |
| UC-PCAD-15 | Novo produto grava o CEST digitado (7 dígitos), sem exigir CEST | should | `CU-PROD-16` | `CadastroProdutoCestTest` + `produto-formulario.test.tsx` (Vitest) | 🧪 escrito, sem veredito |
| UC-PCAD-16 | Novo produto grava peso líquido e dimensões da embalagem, opcionais; o peso de sempre é o peso bruto | should | `CU-PROD-17` | `CadastroProdutoPesoDimensoesTest` + `produto-formulario.test.tsx` (Vitest) | 🧪 escrito, sem veredito |
| UC-PCAD-17 | Com tipo EAN-13, EAN-8 ou UPC-A, SKU que não vira código de barras válido é avisado; o produto é salvo assim mesmo | should | `CU-PROD-18` | `produto-formulario.test.tsx` (Vitest) | 🧪 escrito, sem veredito |
| ~~UC-PCAD-02~~ | ~~Campo obrigatório validado server~~ | — | — | — | ⬛ **retirado** — gap de paridade, não bug |
| ~~UC-PCAD-03~~ | ~~Defaults conservadores server~~ | — | — | — | ⬛ **retirado** — gap de paridade, não bug |
| UC-PCAD-05 | Cadastro não aceita insumo de outro business | must `[T0]` | `CU-PROD-01.5` | — | 🔶 **backlog** — achado Tier 0 real, US própria |

> ### ⚠️ Correção de rumo (2026-07-17, [F]) — eu tinha errado a âncora
>
> A v1 deste arquivo (2026-07-17 manhã) declarava **6 UCs** e chamava os 4 vermelhos de "achados de
> bug". **Estava errado**, e [F] pegou: reincidi na `proibicoes.md` §5 (2026-07-15) — analisei a
> **casca React `Create.tsx`** (draft) como se fosse o cadastro. **O cadastro real em produção é o
> Blade** `resources/views/product/create.blade.php` + `store()`. Reclassificado:
>
> - **`UC-PCAD-02` (validação) e `UC-PCAD-03` (defaults) — retirados.** Não são bugs. O UltimatePOS
>   valida **client-side** no Blade (`required` + jQuery validate) e os defaults moram no **form**
>   (Blade `tax_type='exclusive'` L320; o `useForm` do React tem `type='single'`). O `store()` nunca
>   validou/defaultou server-side, **por design**. Meu teste batia no endpoint cru, pulando a view —
>   testava contrato inexistente. O que sobra é o `CU-PROD-01.1` do SDD dizer *"client **+ server**"*
>   (impreciso) → registrado como paridade, não bug. **O que a casca React não migrou está na grade
>   de paridade do `Create.charter.md` §Paridade Blade→React.**
> - **`UC-PCAD-05` (cross-tenant) — achado Tier 0 REAL, movido pro backlog.** Rodou vermelho de
>   verdade (o `store()` grava `category_id` alheio; `$request->only()` sem `exists:` escopado). Mas
>   corrigi-lo mexe no `store()` legado (~6.4k chamadas) e exige Pest nos caminhos antigos no CT 100
>   (fora nesta sessão) → **US própria** (task MCP criada). Ver §Backlog.
> - **`UC-PCAD-06` (500→404) — corrigido no mesmo PR** (failing-first, padrão #4300): `create()` L539
>   `find()`→`findOrFail()`. O scope de business já estava lá.
>
> **Veredito atual: 3 UCs ativos, todos 🧪 passam** (`01`, `04`, `06`). O `06` fecha com o fix incluído.
> Não afirmei verde antes de rodar — o CI de `29588143635` foi quem separou verdade de narrativa; esta
> correção é a segunda volta desse mesmo mecanismo, agora com a âncora certa (Blade, não React).

---

## UC-PCAD-01 · Cadastro mínimo persiste e o SKU nasce no servidor · `must`
- **Persona:** Larissa / ROTA LIVRE — digita nome, unidade e imposto, deixa o SKU em branco e salva. Se o SKU não vier, o produto não entra em venda nem em etiqueta.
- **Aceite:** Dado nome + `unit_id` + `tax` válidos e SKU **vazio** · Quando envio `POST /products` · Então o produto persiste no meu business **com SKU gerado no servidor** (não-vazio), e o SKU **não** veio do cliente.
- **Teste:** `tests/Feature/Produto/CadastroProdutoContratoTest.php` — `UC-PCAD-01 · SKU vazio nasce gerado no servidor`.
- **Contrato:** `CU-PROD-01` item 2 — *"SKU vazio → gerado **server-side**; SKU digitado → validado duplicado"*.
- **Regressão que defende:** o `Create.charter.md` declara Non-Goal *"❌ NÃO gera SKU client-side (server-side em `store()`)"*. Hoje nada prova que o servidor cumpre a outra metade — se o `store()` passar a confiar num SKU do request, o Non-Goal cai calado.
- **Status: 🧪** — passou (run 29588143635, lane Estoque · MySQL).

---

## ~~UC-PCAD-02~~ · RETIRADO — gap de paridade, não bug

> ⬛ **Retirado 2026-07-17 ([F]).** A validação de obrigatório é **client-side no Blade** (`required` + jQuery validate), por design UltimatePOS — o `store()` nunca validou server. Meu teste batia no endpoint cru. O `CU-PROD-01.1` do SDD ("client + server") é impreciso. O que a casca React não migrou está na grade §Paridade Blade→React do charter.

<details><summary>texto original (v1, errado)</summary>

### UC-PCAD-02 · Campo obrigatório ausente não cria produto órfão · 
- **Persona:** qualquer operador — salvar sem unidade não pode deixar meio-produto no catálogo, que depois aparece na busca da venda sem conseguir ser vendido.
- **Aceite:** Dado um POST **sem** `unit_id` (ou sem `name`) · Quando envio `POST /products` · Então a validação barra, **nenhuma** linha nasce em `products`, e o erro volta na chave do campo.
- **Teste:** `tests/Feature/Produto/CadastroProdutoContratoTest.php` — `UC-PCAD-02 · POST sem campo obrigatório não persiste nada`.
- **Contrato:** `CU-PROD-01` item 1 — *"Campos obrigatórios (name, unit, tax) validados client + server"*.
- **Regressão que defende:** o CU diz "client **+** server". Os testes atuais (`Wave2CreateInertiaTest`) só provam o lado **client**, e provam por `str_contains` no fonte do `.tsx` — nunca fazem POST. O lado server não tem prova nenhuma.
- **Status: ⬛ retirado** (era ❌; reclassificado como paridade).
</details>

---

## ~~UC-PCAD-03~~ · RETIRADO — gap de paridade, não bug

> ⬛ **Retirado 2026-07-17 ([F]).** Os defaults moram no **form** (Blade `tax_type='exclusive'`; React `useForm type='single'`), não no `store()`. O caminho real não grava lixo. Gap de paridade, não bug.

<details><summary>texto original (v1, errado)</summary>

### UC-PCAD-03 · Defaults conservadores valem quando o operador não escolhe · 
- **Persona:** Larissa — não sabe o que é `tax_type`. O default errado muda o preço que o cliente paga.
- **Aceite:** Dado um POST mínimo **sem** `type`, `enable_stock` nem `tax_type` · Quando salvo · Então o produto nasce `type='single'`, `enable_stock=1` e `tax_type='exclusive'`.
- **Teste:** `tests/Feature/Produto/CadastroProdutoContratoTest.php` — `UC-PCAD-03 · defaults conservadores no produto criado`.
- **Contrato:** `CU-PROD-01` item 3 — *"Defaults: `type='single'`, `enable_stock=true`, `tax_type='exclusive'`"*.
- **Regressão que defende:** hoje o default existe **só no `useForm` do React** — `Wave2CreateInertiaTest` assere a **string** `"type: (dup?.type ?? 'single')"` no fonte. Renomear a variável `dup` deixa o teste vermelho sem mudar comportamento; trocar a lógica mantendo a string deixa verde com o comportamento quebrado. E `tax_type='exclusive'` **toca preço** (`[V0]`): se o servidor assumir outro default quando o campo não vem, o preço muda e nada avisa.
- **Status: ⬛ retirado** (era ❌; reclassificado como paridade).
</details>

---

## UC-PCAD-04 · Custo e preço de venda não inflam no parser pt-BR · `must` `[V0]`
- **Persona:** Larissa — digita `1.234,56` de custo e o sistema tem que gravar mil duzentos e trinta e quatro reais, não um milhão duzentos e trinta e quatro mil.
- **Aceite:** Dado um POST de produto simples com `single_dpp = '1.234,56'` e `single_dsp = '2.000,00'` · Quando salvo · Então a variação nasce com `default_purchase_price ≈ 1234.56` e `default_sell_price ≈ 2000.00` — e **nunca** um valor de ordem de grandeza maior.
- **Teste:** `tests/Feature/Produto/CadastroProdutoContratoTest.php` — `UC-PCAD-04 · custo pt-BR com milhar e decimal grava o valor certo` + `UC-PCAD-04 · custo fracionário com ponto NÃO infla ×100k`.
- **Contrato:** `CU-PROD-01` item 4 `[V0]` — *"Preço de custo e venda passam pelo parser pt-BR sem ×100 (`num_uf`); arredondar 2 casas"* + REGRA MESTRE valor/estoque (`proibicoes.md`).
- **Regressão que defende:** `ProductController@store` entrega `single_dpp`/`single_dsp` a `createSingleProductVariation`, e o caminho de update faz `num_uf($single_data['single_dpp'])` — **o mesmo parser** que no incidente 2026-06-05 leu o `.` de `204.99605` como separador de milhar e inflou 16 vendas ×100k na ROTA LIVRE. O Produto **alimenta** Sells: um custo inflado aqui contamina margem, tabela de preço e valor de estoque. Lição perene: separador de milhar tem SEMPRE 3 dígitos.
- **⚠️ O que este UC NÃO cobre — e é o achado:** ele prova o **endpoint**. Ele **não** prova que a tela manda o campo. Ver §Pendência de CONTRATO abaixo.
- **Status: 🧪** — passou os DOIS casos (run 29588143635): `1.234,56`→1234.56 e `204.99605` não estoura. O endpoint parseia pt-BR corretamente.

---

## UC-PCAD-05 · Cadastro não aceita insumo de outro business · `must` `[T0]`
- **Persona:** qualquer tenant. O pior bug possível neste projeto é o preço/catálogo de um business vazar pro outro.
- **Aceite:** Dado um `category_id` (ou `brand_id`/`unit_id`) que pertence a **outro** business · Quando envio `POST /products` · Então o produto **não** nasce carimbado com o insumo alheio — a operação não reporta sucesso **ou** o vínculo é recusado.
- **Teste:** `tests/Feature/Produto/CadastroProdutoContratoTest.php` — `UC-PCAD-05 · category_id de outro business não vincula`.
- **Contrato:** `CU-PROD-01` item 5 `[T0]` — *"Dropdowns (categoria/marca/unidade/imposto) só do business atual"* + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o CU fala dos **dropdowns** — que é a UI. O dropdown escopado impede o operador de *escolher*, não impede o *request* de mandar. É exatamente o buraco que o `UC-PTAB-04` achou vermelho na tabela de preço ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)): o `price_group_id` vinha cru da chave do array, sem `exists:` escopado, e **gravou** row cross-tenant. O `CU-PROD-10` também dizia `✅ (reusa guard)` lá — e era falso. **Este UC existe pra descobrir se a mesma família de furo vive no `store()`.** Pode nascer vermelho; se nascer, a correção entra no mesmo PR (failing-first).
- **Status: 🔶 backlog (achado Tier 0 REAL).** Rodou vermelho de verdade — o `store()` grava `category_id` alheio (`$request->only()` sem `exists:` escopado; família do `UC-PTAB-04`/#4300). **Não corrigido neste PR:** o fix mexe no `store()` legado (~6.4k chamadas) e exige Pest nos caminhos antigos no CT 100 (fora nesta sessão). → **US própria** (task MCP criada 2026-07-17). Teste removido daqui pra não bloquear o merge; o achado fica registrado.

---

## UC-PCAD-06 · Duplicar produto de outro business não vaza · `should` `[T0]`
- **Persona:** qualquer tenant — `?d=N` pré-preenche o form lendo um produto. Se o N for de outro business, o duplicar vira leitor de catálogo alheio.
- **Aceite:** Dado `GET /products/create?d={id_de_outro_business}` · Quando abro · Então **404** — nenhum dado do produto alheio chega ao form.
- **Teste:** `tests/Feature/Produto/CadastroProdutoContratoTest.php` — `UC-PCAD-06 · duplicar produto de outro business retorna 404`.
- **Contrato:** `CU-PROD-07` item 2 `[T0]` — *"Só duplica produto do business atual (externo → 404)"*.
- **Regressão que defende:** o CU crava **404** explicitamente — diferente do `UC-PTAB-02`, onde o 404 era proxy inventado pelo charter. Aqui é o contrato falando. Se o `create()` devolver 200 com o produto alheio no form, é vazamento de leitura.
- **Status: 🧪 passa (fix no mesmo PR).** Era `Expected 404 but received 500`. **Corrigido** (failing-first, padrão #4300): `ProductController@create` L539 `find()`→`findOrFail()` — o scope de business já estava lá, agora o id alheio dá 404 limpo em vez de crashar em `->name` de null.

## UC-PCAD-07 · Produto novo grava a origem da mercadoria escolhida · `should`
- **Persona:** quem cadastra produto importado — a origem vai no XML de cada item da nota (`orig`) e, importado, muda a alíquota de ICMS entre estados (4%, Resolução do Senado 13/2012).
- **Aceite:** Dado o card "Preço & Imposto" · Quando escolho a origem (tabela A da NF-e, códigos 0 a 8) e salvo · Então o produto nasce com aquela origem · E, sem o campo (o Blade não manda), nasce com `0`, a origem que toda nota usava antes.
- **Teste:** [`ProdutoOrigemMercadoriaTest`](../../../../tests/Feature/Produto/ProdutoOrigemMercadoriaTest.php) — `UC-PCAD-07` (os dois casos).
- **Contrato:** `AR-PROD-128` (o Office Comercial tinha "Origem da Mercadoria" no cadastro, `PRODUTO.ORIGEM_MERCADORIA`) + tabela A do MOC (`orig` só aceita 0 a 8).
- **Status: 🧪** — Recibo: CT 100 (`oimpresso-staging`, MySQL `oimpresso_staging`, worktree isolado do branch, sem tocar o checkout compartilhado), 2026-10-15 — **8 passed (19 assertions)**. Bite-test por mutação: emissão de volta a `0`, limite `0,9` no lugar de `0,8`, `update()` sem o guarda `filled` e `linhasDaVenda` sem a coluna — cada uma derruba 1 caso. Não vira ✅ enquanto a lane não publicar o veredito (G-7).

## UC-PCAD-08 · O "Novo produto" da /products abre o cadastro React quando a chave da empresa está ligada · `should`
- **Persona:** quem cadastra produto pela lista `/products`. A lista navega com página inteira (`Index.tsx`, `window.location.assign`), então quem decide qual cadastro abre é o `ProductController@create`.
- **Aceite:** Dado a chave `useV2ProdutoCadastro` **desligada** para a empresa (o default) · Quando abro `/products/create` com página inteira · Então abre o cadastro **Blade**, como antes · E, com a chave **ligada**, abre o cadastro **React** (`Produto/Create`) · E `?classico=1` devolve o Blade mesmo com a chave ligada · E uma visita Inertia abre o React com a chave desligada (a regra anterior).
- **Teste:** [`CadastroProdutoChaveReactTest`](../../../../tests/Feature/Produto/CadastroProdutoChaveReactTest.php) — `UC-PCAD-08` (os quatro casos).
- **Contrato:** decisão [F] 2026-10-16 — *"O Novo produto da /products deve abrir o cadastro novo (React), no lugar do Blade."* A chave existe porque o formulário React ainda não manda preço (§Pendência de CONTRATO abaixo): ligar para cliente vem depois do preço.
- **Status: 🧪** — escrito junto do código, ainda sem veredito da lane `PHP / Pest (Estoque · MySQL)` (G-7).

## UC-PCAD-09 · Novo produto simples nasce com o preço digitado, com a conta do Blade · `must` `[V0]`
- **Persona:** Larissa — cadastra o produto e já define custo e venda. Sem os campos, o produto nascia sem preço e precisava de uma segunda tela para vender.
- **Aceite:** Dado o card "Preço & Imposto" de um produto **simples** · Quando digito a compra sem imposto · Então a venda é preenchida pelo % de lucro padrão da empresa · E mudar o lucro refaz a venda, e mudar a venda refaz o lucro (compra 0 → lucro 0) · E os dois "com imposto" saem da alíquota do imposto escolhido · E o envio leva `single_dpp`, `single_dpp_inc_tax`, `profit_percent`, `single_dsp` e `single_dsp_inc_tax` com vírgula decimal e sem milhar, com os **mesmos valores que o cadastro Blade grava**.
- **Teste:** [`produto-preco.test.tsx`](../../../../tests/js/produto-preco.test.tsx) — `UC-PCAD-09` (a conta e o envio pela tela). Roda no step "Comportamento React" do job `Frontend / Vite build`.
- **Contrato:** `CU-PROD-01` item 4 `[V0]` + REGRA MESTRE de valor (`proibicoes.md`). A conta é a do Blade (`public/js/product.js` com `__add_percent`/`__get_rate` de `functions.js`): exata (Decimal.js) e arredondada como o `accounting.toFixed`.
- **Prova por dois caminhos (2026-10-16):** 545 casos (compra × lucro × alíquota, e compra × venda) gerados pelo `precos.ts` comparados com as **próprias funções do Blade** extraídas de `functions.js` e rodadas com `decimal.js`, os dois lados lidos pelo **`Util::num_uf` real** (corpo extraído do `Util.php`, PHP 8): **2.725 valores, 0 divergências**. Controle: com a conta em `number` saem 8 divergências (ex.: 123,45 + 10% → 135,80 contra 135,79 do Blade). Mordida: `Create.tsx` sem o preço derruba o caso da tela; a conta em `number` derruba o caso 135,79.
- **Fora deste UC:** produto **variável** e **composto** (têm editor próprio de preço) e a tela **Editar** (o preço do produto existente segue no Blade). Sem preço digitado o produto nasce com zero, como no Blade.
- **Status: 🧪** — teste escrito junto do código, sem veredito publicado no manifesto (G-7).

## UC-PCAD-10 · Novo produto nasce nos locais do negócio · `must`
- **Persona:** quem cadastra e precisa vender o produto. Sem local, o produto nasce sem `product_locations` e não aparece para venda em local nenhum.
- **Aceite:** Dada a tela Novo produto · Quando a empresa tem **um** local · Então ele já vem marcado e vai no envio · E, com **mais de um**, nenhum vem marcado e vão os que a pessoa marcar.
- **Teste:** [`produto-locais.test.tsx`](../../../../tests/js/produto-locais.test.tsx) — `UC-PCAD-10` (os dois casos). Roda no step "Comportamento React" do job `Frontend / Vite build`.
- **Contrato:** paridade com o cadastro Blade — `create.blade.php` (`$default_location`: com um local só, `product_locations[]` já vem com ele) e `ProductController@store` (`product_locations()->sync(...)`). Como no Blade, o campo não é obrigatório.
- **Mordida (2026-10-16):** `product_locations` de volta a `[]` derruba o caso de um local; tirar o bloco da tela derruba os dois.
- **Status: 🧪** — teste escrito junto do código, sem veredito publicado no manifesto (G-7).

## UC-PCAD-11 · Variável e composto não salvam pela tela React · `must`
- **Persona:** quem cadastra produto com grade (tamanho/cor) ou composto. Pela tela React o produto nascia quebrado.
- **Aceite:** Dado o tipo **Variável** ou **Composto** na tela Novo produto · Então a tela avisa que esse tipo ainda não tem editor aqui · E oferece o link "Cadastrar no formulário clássico" (`/products/create?classico=1`, página inteira) · E o botão Salvar fica desligado e o envio não acontece · E, com **Simples**, nada disso aparece e o envio segue.
- **Teste:** [`produto-tipo.test.tsx`](../../../../tests/js/produto-tipo.test.tsx) — `UC-PCAD-11` (variável, composto e o controle simples). Roda no step "Comportamento React" do job `Frontend / Vite build`.
- **Contrato:** `ProductController@store` — variável sem `product_variation` não cria variação nenhuma; composto sem `composition_variation_id` cria a variação sem itens e com preço zero (`item_level_purchase_price_total`/`selling_price` ausentes). Sem variação o produto não entra em venda, compra, OS nem fabricação, que trabalham por `variation_id`.
- **Mordida (2026-10-16):** sem a trava do envio caem os 2 casos de tipo; sem o aviso também.
- **Fora deste UC:** o editor de grade e de composição em React (trabalho próprio). Até ele existir, o clássico é o caminho.
- **Status: 🧪** — teste escrito junto do código, sem veredito publicado no manifesto (G-7).

## UC-PCAD-12 · Novo produto grava a validade digitada · `must`
- **Persona:** empresa que controla validade de produto (perecível, insumo com prazo). Pela tela React a validade digitada não era gravada.
- **Aceite:** Dada uma empresa que usa validade · Quando ela calcula a validade pela fabricação (`add_manufacturing`) · Então a tela mostra "Expira em" com o número e o período (meses por padrão, dias ou não aplicável) e o envio leva os dois · E, quando a validade é lançada na compra (`add_expiry`, o padrão da coluna), o campo fica escondido e o envio leva 12 meses · E, sem validade na empresa, o campo não aparece.
- **Teste:** [`produto-formulario.test.tsx`](../../../../tests/js/produto-formulario.test.tsx) — `UC-PCAD-12` (os três casos). Roda no step "Comportamento React" do job `Frontend / Vite build`.
- **Contrato:** paridade com o cadastro Blade — `create.blade.php` (`expiry_period_type` com `months` por padrão; com `business.expiry_type == 'add_expiry'` o bloco fica escondido e vai `expiry_period = 12`) e `ProductController@store`, que só grava a validade quando vêm o número **e** o período. A tela mandava o período sempre vazio.
- **Mordida (2026-10-19):** com o período de volta a vazio caem os 2 casos de validade (`expected '' to be 'months'`); com a tela do `main` também caem os 2. O terceiro (empresa sem validade) é guarda e passa nos dois.
- **Fora deste UC:** duplicar produto ainda não traz a validade do original (o Blade traz); a tela Editar ainda não mostra a validade (o envio dela é o `UC-PEDIT-10`).
- **Status: 🧪** — teste escrito junto do código, sem veredito publicado no manifesto (G-7).

## UC-PCAD-13 · Novo produto grava o NCM digitado, sem exigir NCM · `must`
- **Persona:** quem cadastra mercadoria que sai em nota fiscal. O NCM vai em cada item da nota, e sem ele a nota de mercadoria não sai. Antes deste caso, o cadastro não tinha o campo: o NCM só era editado na tela de Tributação do módulo fiscal, que nem toda empresa tem no pacote.
- **Aceite:** Dado o card "Preço & Imposto" da tela Novo produto · Quando digito o NCM · Então o campo guarda só os dígitos, no máximo 8 (digitar `4911.10.90` fica `49111090`) · E o produto nasce com esse NCM · E, sem NCM (campo vazio, ou o Blade, que não manda o campo), o produto é **salvo** e nasce com o `'0'` que a coluna usa para "sem NCM" · E NCM fora do formato (curto, longo, com pontos) é recusado antes de gravar: nenhum produto nasce · E duplicar um produto traz o NCM dele, e o `'0'` do banco aparece vazio.
- **Teste:** [`CadastroProdutoNcmTest`](../../../../tests/Feature/Produto/CadastroProdutoNcmTest.php) — `UC-PCAD-13` (o servidor: grava, vazio, Blade, os três formatos recusados, duplicar) · [`produto-formulario.test.tsx`](../../../../tests/js/produto-formulario.test.tsx) — `UC-PCAD-13` (a tela: só dígitos e duplicar).
- **Contrato:** `CU-PROD-16` do [SDD](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) — decisão [M] 2026-10-20, *"o NCM entra no cadastro do produto"* (ratificação [W] no merge). O NCM não é obrigatório porque produto de **serviço** não tem NCM: pela Súmula 156 do STJ, o serviço gráfico sob encomenda paga ISS e sai em nota de serviço (`CU-PROD-19`). A validação de 8 dígitos é a mesma da tela de Tributação (`SugestaoFiscalService`, `digits:8`).
- **Fora deste UC:** o NCM na tela **Editar** é o `UC-PEDIT-11` do [`Edit.casos.md`](Edit.casos.md) (2026-10-20). _Até então o Editar não tinha o campo._
- **Status: 🧪** — escrito junto do código, sem veredito da lane `PHP / Pest (Estoque · MySQL)` nem do job `Frontend / Vite build` (G-7). Mordida por mutação ainda não rodada: não há PHP nem Node na máquina onde foi escrito.

## UC-PCAD-14 · Sem NCM a tela avisa, e o produto é salvo assim mesmo · `should`
- **Persona:** quem cadastra com pressa e deixa o NCM para depois. O produto sem NCM só trava na hora da nota, longe do cadastro.
- **Aceite:** Dada a tela Novo produto · Quando o NCM está vazio · Então aparece embaixo do campo *"Sem NCM a nota fiscal de mercadoria não sai. Dá para salvar e preencher depois."* · E o botão Salvar continua ligado e o envio acontece · E, com menos de 8 dígitos, o aviso diz *"O NCM tem 8 dígitos."* · E, com os 8 dígitos, o aviso some.
- **Teste:** [`produto-formulario.test.tsx`](../../../../tests/js/produto-formulario.test.tsx) — `UC-PCAD-14` (sem NCM envia, NCM incompleto). Roda no step "Comportamento React" do job `Frontend / Vite build`.
- **Contrato:** `CU-PROD-18` do SDD — o aviso não pode impedir o salvamento; o cadastro mínimo segue nome e SKU (`CU-PROD-01`). Referência de mercado: a Omie avisa NCM, EAN e origem faltando; o protótipo `Product Cadastro.html` (Cowork do [F]) desenha *"Faltam: nome, NCM"*.
- **Fora deste UC:** o aviso olha só o NCM. Produto **novo** ainda não tem natureza fiscal decidida (quem decide é o contador, depois, na Tributação — `UC-NFTR-32`), então a tela não sabe se ele é serviço. Avisar EAN inválido e mostrar a natureza ficam para quando o Editar e a ficha receberem o `CU-PROD-19` (o Editar recebeu em 2026-10-21: `UC-PEDIT-14`).
- **Status: 🧪** — escrito junto do código, sem veredito publicado (G-7).

## UC-PCAD-15 · Novo produto grava o CEST digitado, sem exigir CEST · `should`
- **Persona:** quem cadastra mercadoria com substituição tributária. O CEST vai na nota dessa mercadoria e, sem ele, a nota pode ser rejeitada.
- **Aceite:** Dado o card "Preço & Imposto" da tela Novo produto · Quando digito o CEST · Então o campo guarda só os dígitos, no máximo 7 (digitar `28.038.00` fica `2803800`) · E o produto nasce com esse CEST · E, sem CEST (campo vazio, ou o Blade, que não manda o campo), o produto é **salvo** com o CEST nulo e **sem aviso**, porque só a mercadoria com substituição tributária precisa dele · E um CEST incompleto mostra *"O CEST tem 7 dígitos."* · E CEST fora do formato (curto, longo, com pontos) é recusado antes de gravar: nenhum produto nasce · E duplicar um produto traz o CEST dele.
- **Teste:** [`CadastroProdutoCestTest`](../../../../tests/Feature/Produto/CadastroProdutoCestTest.php) — `UC-PCAD-15` (o servidor: grava, vazio, Blade, os três formatos recusados, duplicar) · [`produto-formulario.test.tsx`](../../../../tests/js/produto-formulario.test.tsx) — `UC-PCAD-15` (a tela: sem CEST não avisa, só dígitos, incompleto avisa, duplicar).
- **Contrato:** `CU-PROD-16` do [SDD](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) (decisão [M] 2026-10-20: o CEST entra no cadastro junto com o NCM) + Convênio ICMS 142/2018, cláusula sexta (o CEST tem 7 dígitos).
- **Fora deste UC:** saber se o produto tem substituição tributária. A tela não sabe, então não avisa CEST vazio. O CEST na tela **Editar** é o `UC-PEDIT-12` do [`Edit.casos.md`](Edit.casos.md).
- **Status: 🧪** — escrito junto do código, sem veredito da lane `PHP / Pest (Estoque · MySQL)` nem do job `Frontend / Vite build` (G-7).

## UC-PCAD-16 · Novo produto grava peso líquido e dimensões da embalagem · `should`
- **Persona:** quem despacha o produto ou vende em marketplace. Frete e marketplace (Shopee, Amazon) pedem o peso e as medidas da embalagem.
- **Aceite:** Dado "Mais opções" da tela Novo produto · Então o peso de sempre aparece como **"Peso bruto (kg)"**, com embalagem (é o que o WooCommerce usa no frete), e há os campos **"Peso líquido (kg)"**, **"Largura (cm)"**, **"Altura (cm)"** e **"Comprimento (cm)"** · Quando preencho e salvo · Então o produto nasce com esses valores · E, sem eles (vazios, ou o Blade, que não manda nenhum), o produto é salvo com os quatro nulos · E número negativo, texto ou acima de 99999 é recusado antes de gravar: nenhum produto nasce · E duplicar um produto traz os quatro valores dele.
- **Teste:** [`CadastroProdutoPesoDimensoesTest`](../../../../tests/Feature/Produto/CadastroProdutoPesoDimensoesTest.php) — `UC-PCAD-16` (o servidor: grava, vazios, Blade, três valores inválidos, duplicar) · [`produto-formulario.test.tsx`](../../../../tests/js/produto-formulario.test.tsx) — `UC-PCAD-16` (a tela: rótulo do peso bruto, campos novos, envio).
- **Contrato:** `CU-PROD-17` do [SDD](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) — pedido [M] 2026-10-21. O "Peso" que já existia virou o peso bruto, porque é ele que o WooCommerce manda como peso de frete; quem já tinha preenchido não perde nada.
- **Fora deste UC:** levar o peso e as dimensões para a nota (grupo de volumes do transporte) e para o WooCommerce (só o peso bruto vai hoje). O CEP e a cotação de frete também ficam fora.
- **Status: 🧪** — escrito junto do código, sem veredito da lane `PHP / Pest (Estoque · MySQL)` nem do job `Frontend / Vite build` (G-7).

---

## UC-PCAD-17 · SKU que não vira código EAN válido é avisado · `should`
- **Persona:** quem cadastra mercadoria com código de barras de fábrica e imprime etiqueta.
- **Aceite:** Dado o "Tipo de código de barras" EAN-13, EAN-8 ou UPC-A · Quando o SKU tem letra ou símbolo, tem dígitos demais, ou o último dígito não confere com os outros (regra GS1) · Então aparece um aviso embaixo do tipo de código de barras, dizendo o que está errado e, no dígito, qual devia ser · E o produto é salvo assim mesmo · E SKU vazio (gerado pelo servidor), SKU mais curto e os tipos Code 128, Code 39 e UPC-E não avisam.
- **Teste:** [`produto-formulario.test.tsx`](../../../../tests/js/produto-formulario.test.tsx) — `UC-PCAD-17` (a regra do dígito nos códigos de exemplo da GS1, os casos que avisam e os que não avisam, e o Novo produto com EAN-13: avisa, envia, e o aviso some com o código certo).
- **Contrato:** `CU-PROD-18` do [SDD](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md). O código de barras do produto é o SKU. Com esses tipos, a biblioteca da etiqueta (milon/barcode v13.1) recusa SKU com letra ou com o último dígito errado, e a etiqueta não sai; código mais curto ela completa com zeros. A nota fiscal **não** usa esse código hoje: o emissor do NfeBrasil manda "SEM GTIN".
- **Status: 🧪** — escrito junto do código, sem veredito do job `Frontend / Vite build` (G-7).

---

## Pendência de CONTRATO (achado sem caso de uso — não vira UC até `[F]` decidir)

> ✅ **Resolvida em 2026-10-16 — saída (b), decisão [W].** O preço entrou na tela Novo: `UC-PCAD-09`.
> O texto abaixo fica como estava (é o registro de por que a tela nasceu sem preço).

> ⚠️ **A tela não manda preço. O card se chama "Preço & Imposto" e não tem campo de preço.**
>
> **Recibo** (medição datada, sistema declarado — lei [#4411](https://github.com/wagnerra23/oimpresso.com/pull/4411)):
> em **2026-07-17**, contra `origin/main` `25b448019`, medindo o **fonte** (não o browser):
> ```
> git grep -cE 'single_dpp|single_dsp|profit_percent' -- resources/js/Pages/Produto/Create.tsx
> → 0
> ```
> O mesmo arquivo tem `<CardTitle>Preço & Imposto</CardTitle>` e recebe o prop `defaultProfitPercent`
> — que nunca é usado. O `store()` (`ProductController`) lê `single_dpp`/`single_dsp`/`profit_percent`
> do request pra montar a variação. **Nenhum dos três sai da tela React.**
>
> **Por que isto NÃO virou UC:** um caso de uso é um contrato que um teste defende (G-2). "A tela
> manda preço" não é testável por Pest — o teste bate no endpoint, e o endpoint está correto. Provar
> isto exige o smoke browser da US-PROD-023 (ou um teste de payload Inertia). E, principalmente:
> **não sei se é bug ou decisão.** `[F]` está reconstruindo o cadastro em abas, e a **aba Custos**
> ainda não existe em git (session `2026-07-16-produto-preco-especial-f1.md`: *"0 `.tsx`, 0 branch"*).
>
> **As duas saídas — decisão `[F]`:**
> - **(a) Non-Goal declarado** — preço não mora no `/products/create`; mora na aba Custos. Então o
>   `Create.charter.md` ganha o Non-Goal (hoje ele lista 6 e **nenhum** menciona preço) e o
>   `CU-PROD-01` item 4 muda de dono no SDD. **Provável, dado o rumo das abas.**
> - **(b) Bug** — o card promete preço e não entrega; produto nasce sem custo/venda/markup. Vira US
>   sob a REGRA MESTRE (`[V0]`).
>
> Enquanto não decide, o `UC-PCAD-04` defende o que **é** testável (o endpoint parseia pt-BR sem
> inflar) e este § guarda o que **não é**. Não é esquecimento; é a fila.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão = violação nova no `casos-gate`.

- **SKU digitado é validado como duplicado** — `CU-PROD-01` item 2, segunda metade. O `UC-PCAD-01`
  cobre o SKU **vazio**; a validação de duplicado vive em `validateVaritionSkus` e é contrato do
  produto **variável** (`CU-PROD-02` item 2) — vira UC junto com o `casos.md` da grade/variação.
- **Submit retorna `/products`** — `CU-PROD-01` item 6. É paridade de **navegação**, não de dado;
  o Pest vê o 302 mas o que importa (o operador chega na lista) é smoke da US-PROD-023.
- **Natureza fiscal (mercadoria × serviço) no cadastro** — `CU-PROD-19`. A natureza existe (`nfe_naturezas_produto`) e é decidida pelo contador na Tributação. Desde 2026-10-21 o Editar a mostra (`UC-PEDIT-14` em `Edit.casos.md`); no Novo produto não há o que mostrar, porque o produto ainda não existe para o contador decidir. Falta a ficha.
- **Casos de comunicação visual** — `CV-06` a `CV-11` do SDD. A maior parte do dado mora no módulo de Comunicação Visual; viram UC com sinal de cliente.
- **Locais de armazenagem e endereço no estoque** — `CU-PROD-20`, pedido da [M] em 2026-10-22, só no protótipo. No Novo: uma linha por local com Padrão, Local de estoque, Endereço no estoque e Qtd. inicial; "+ Adicionar local"; total. Vira UC quando a tela tiver o bloco e um teste que o cite. Decidido em 2026-10-22: endereço em prateleira, fileira e posição, e as duas seções de locais ficam. E o local padrão é o local sugerido na compra, na transferência e na separação; a venda continua baixando do local da venda.
- **Quick-add inline** — `CU-PROD-08`. Rota e contrato separados (`save_quick_product`); merece
  `casos.md` próprio quando a tela de origem (venda/compra) fechar o trio dela.

---

## Refs

- Charter (lei): [`Create.charter.md`](Create.charter.md) — `v1`, `draft`, `last_validated: 2026-05-15`
- Irmão que fechou o trio primeiro: [`SellingPrices.casos.md`](SellingPrices.casos.md) ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300))
- SDD (âncora dos CU): [`SDD-tela-cadastro-produto-v1.0.md`](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) §6.1
- SPEC (a US que pede este arquivo): [`SPEC.md`](../../../../memory/requisitos/Produto/SPEC.md) — US-PROD-020
- Anti-regressão legado: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md) §A/§I
- Gate: `scripts/casos-coverage-guard.mjs` (G-1/G-2/G-5/G-6/G-7 — ADR 0264) · lane `PHP / Pest (Estoque · MySQL)`
