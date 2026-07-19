---
casos: Cadastro de produto · /products/create
irmaos: Create.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o produto que NASCE do cadastro não muda quando a tela vira aba.
owner: wagner
last_run: "2026-07-17"
last_run_ci: "3 UCs ativos (01/04/06) — todos passam; 02/03 retirados, 05 backlog (correção [F] 2026-07-17)"
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

---

## Pendência de CONTRATO (achado sem caso de uso — não vira UC até `[F]` decidir)

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
