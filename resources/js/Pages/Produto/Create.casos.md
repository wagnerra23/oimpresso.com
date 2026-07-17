---
casos: Cadastro de produto · /products/create
irmaos: Create.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o produto que NASCE do cadastro não muda quando a tela vira aba.
owner: wagner
last_run: "2026-07-17"
last_run_ci: "run 29588143635 · lane Estoque · MySQL · biz=1+biz=2 · 2 pass / 4 fail"
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
| UC-PCAD-02 | Campo obrigatório ausente não cria produto órfão | must | `CU-PROD-01.1` | `CadastroProdutoContratoTest` | ❌ **CI vermelho** |
| UC-PCAD-03 | Defaults conservadores valem quando o operador não escolhe | must | `CU-PROD-01.3` | `CadastroProdutoContratoTest` | ❌ **CI vermelho** |
| UC-PCAD-04 | Custo e preço de venda não inflam no parser pt-BR | must `[V0]` | `CU-PROD-01.4` | `CadastroProdutoContratoTest` | 🧪 passa |
| UC-PCAD-05 | Cadastro não aceita insumo de outro business | must `[T0]` | `CU-PROD-01.5` | `CadastroProdutoContratoTest` | ❌ **CI vermelho (Tier 0)** |
| UC-PCAD-06 | Duplicar produto de outro business não vaza | should `[T0]` | `CU-PROD-07.2` | `CadastroProdutoContratoTest` | ❌ **CI vermelho** |

**Veredito: 2 passam, 4 reprovam — e os 4 vermelhos são reais.** Run `29588143635` (lane `Estoque ·
MySQL`, MySQL real, biz=1+biz=2). O `UC-PCAD-01` e o `UC-PCAD-04` (`[V0]`, os dois casos de `num_uf`)
passam: o **endpoint** cadastra e parseia pt-BR sem inflar. Os 4 vermelhos provam que o **`✅` do
`CU-PROD-01` no SDD é falso** — `store()` não valida (`02`), não tem default server-side (`03`), aceita
`category_id` cross-tenant (`05`, **Tier 0**) e crasha 500 ao duplicar alheio (`06`).

> **⚠️ O PR está vermelho DE PROPÓSITO.** Os 4 UCs são `❌` (não `🧪`/`✅`) porque o teste **prova a falha**
> — corrigir exige tocar o `store()`, que é Non-Goal declarado do `Create.charter.md`, e o `CU-PROD-01`
> é `[must]`. Isso é decisão de contrato (`[F]`), não bug de tela. As saídas estão no §Backlog de
> contrato do charter. Isto é exatamente o §Lição do `SellingPrices.casos.md`: *"eu tinha afirmado a
> conclusão lendo o código antes de rodar; o CI foi quem separou o que era verdade do que era
> narrativa."* Aqui eu não afirmei verde — o CI falou, e os 4 vermelhos viraram contrato.

---

## UC-PCAD-01 · Cadastro mínimo persiste e o SKU nasce no servidor · `must`
- **Persona:** Larissa / ROTA LIVRE — digita nome, unidade e imposto, deixa o SKU em branco e salva. Se o SKU não vier, o produto não entra em venda nem em etiqueta.
- **Aceite:** Dado nome + `unit_id` + `tax` válidos e SKU **vazio** · Quando envio `POST /products` · Então o produto persiste no meu business **com SKU gerado no servidor** (não-vazio), e o SKU **não** veio do cliente.
- **Teste:** `tests/Feature/Produto/CadastroProdutoContratoTest.php` — `UC-PCAD-01 · SKU vazio nasce gerado no servidor`.
- **Contrato:** `CU-PROD-01` item 2 — *"SKU vazio → gerado **server-side**; SKU digitado → validado duplicado"*.
- **Regressão que defende:** o `Create.charter.md` declara Non-Goal *"❌ NÃO gera SKU client-side (server-side em `store()`)"*. Hoje nada prova que o servidor cumpre a outra metade — se o `store()` passar a confiar num SKU do request, o Non-Goal cai calado.
- **Status: 🧪** — passou (run 29588143635, lane Estoque · MySQL).

---

## UC-PCAD-02 · Campo obrigatório ausente não cria produto órfão · `must`
- **Persona:** qualquer operador — salvar sem unidade não pode deixar meio-produto no catálogo, que depois aparece na busca da venda sem conseguir ser vendido.
- **Aceite:** Dado um POST **sem** `unit_id` (ou sem `name`) · Quando envio `POST /products` · Então a validação barra, **nenhuma** linha nasce em `products`, e o erro volta na chave do campo.
- **Teste:** `tests/Feature/Produto/CadastroProdutoContratoTest.php` — `UC-PCAD-02 · POST sem campo obrigatório não persiste nada`.
- **Contrato:** `CU-PROD-01` item 1 — *"Campos obrigatórios (name, unit, tax) validados client + server"*.
- **Regressão que defende:** o CU diz "client **+** server". Os testes atuais (`Wave2CreateInertiaTest`) só provam o lado **client**, e provam por `str_contains` no fonte do `.tsx` — nunca fazem POST. O lado server não tem prova nenhuma.
- **Status: ❌** — **CI vermelho** (run 29588143635): `Produto nasceu SEM unidade`. O `store()` não tem `$request->validate()` — só `$request->only()`. O `CU-PROD-01.1` (client **+ server**) é falso no server.

---

## UC-PCAD-03 · Defaults conservadores valem quando o operador não escolhe · `must`
- **Persona:** Larissa — não sabe o que é `tax_type`. O default errado muda o preço que o cliente paga.
- **Aceite:** Dado um POST mínimo **sem** `type`, `enable_stock` nem `tax_type` · Quando salvo · Então o produto nasce `type='single'`, `enable_stock=1` e `tax_type='exclusive'`.
- **Teste:** `tests/Feature/Produto/CadastroProdutoContratoTest.php` — `UC-PCAD-03 · defaults conservadores no produto criado`.
- **Contrato:** `CU-PROD-01` item 3 — *"Defaults: `type='single'`, `enable_stock=true`, `tax_type='exclusive'`"*.
- **Regressão que defende:** hoje o default existe **só no `useForm` do React** — `Wave2CreateInertiaTest` assere a **string** `"type: (dup?.type ?? 'single')"` no fonte. Renomear a variável `dup` deixa o teste vermelho sem mudar comportamento; trocar a lógica mantendo a string deixa verde com o comportamento quebrado. E `tax_type='exclusive'` **toca preço** (`[V0]`): se o servidor assumir outro default quando o campo não vem, o preço muda e nada avisa.
- **Status: ❌** — **CI vermelho** (run 29588143635): `Failed asserting that null is identical to 'single'`. POST sem `type` → produto nasce `type=null`. O default só existe no `useForm` do React.

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
- **Status: ❌** — **CI vermelho (Tier 0)** (run 29588143635): `Produto do meu business ficou vinculado a categoria de OUTRO business`. O `category_id` vem cru do `$request->only()`, sem `exists:` escopado. Mesma família do `UC-PTAB-04` (#4300).

---

## UC-PCAD-06 · Duplicar produto de outro business não vaza · `should` `[T0]`
- **Persona:** qualquer tenant — `?d=N` pré-preenche o form lendo um produto. Se o N for de outro business, o duplicar vira leitor de catálogo alheio.
- **Aceite:** Dado `GET /products/create?d={id_de_outro_business}` · Quando abro · Então **404** — nenhum dado do produto alheio chega ao form.
- **Teste:** `tests/Feature/Produto/CadastroProdutoContratoTest.php` — `UC-PCAD-06 · duplicar produto de outro business retorna 404`.
- **Contrato:** `CU-PROD-07` item 2 `[T0]` — *"Só duplica produto do business atual (externo → 404)"*.
- **Regressão que defende:** o CU crava **404** explicitamente — diferente do `UC-PTAB-02`, onde o 404 era proxy inventado pelo charter. Aqui é o contrato falando. Se o `create()` devolver 200 com o produto alheio no form, é vazamento de leitura.
- **Status: ❌** — **CI vermelho** (run 29588143635): `Expected 404 but received 500`. Não vaza (crasha antes), mas é exceção não-tratada onde o CU crava 404.

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
