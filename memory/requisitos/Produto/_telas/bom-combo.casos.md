---
id: memory-requisitos-produto-telas-bom-combo-casos
casos: Combo/kit + BOM · API /api/products/{id}/bom + App\Domain\Inventory\Services\BomResolver
irmaos: SDD-tela-cadastro-produto-v1.0.md §6.1 CU-PROD-05 (âncora) · ANTI-REGRESSAO-cadastro-produto-legacy.md Parte 4
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a composição decide de QUAL produto sai o estoque quando um kit é vendido — errar aqui baixa a peça errada, e o erro só aparece no inventário do mês seguinte.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane Estoque · MySQL"
---

# Casos de Uso & Aceite — Combo/kit + BOM (fluxo sem tela)

> **Âncora:** `CU-PROD-05` (combo/kit + BOM) e `CU-PROD-10` `[T0]` (multi-tenant) do
> [SDD §6.1](../SDD-tela-cadastro-produto-v1.0.md), cruzados com o **contrato de paridade
> Delphi** ([ANTI-REGRESSAO-cadastro-produto-legacy.md](../ANTI-REGRESSAO-cadastro-produto-legacy.md)
> **Parte 4 — aba Composição**, `AR-PROD-150..168` `[V0]`) e com a **Blade legada de combo**
> (`product/partials/combo_product_form_part.blade.php` + `combo_product_entry_row.blade.php`).
> Os UCs derivam do **contrato**, **nunca** do `ProductBomController` — teste derivado do código
> é tautológico (`proibicoes.md` §5, 2026-06-05).
>
> **Como este arquivo nasceu:** agent `sdd-from-source` ([ADR 0351](../../../decisions/0351-sdd-from-source.md)),
> fechando a lacuna `CU-PROD-05 sem UC` do painel [`_STATUS-GENERATED.md`](../_STATUS-GENERATED.md).
>
> **Status:** ✅ passa (prova na lane) · 🧪 teste cita o UC (veredito pendente) ·
> ⬜ não verificado · ❌ quebrou · 🔶 decisão [W].

---

## ⚠️ Três fatos medidos que emolduram TODOS os casos abaixo

Medidos em 2026-07-27, sha `16606e35c4`, repo completo (`git rev-parse --is-shallow-repository` = `false`).

| # | Fato | Como re-medir |
|---|---|---|
| **1** | **Não existe tela de composição.** Nos `.tsx` de `resources/js/Pages/Produto/`, `combo` aparece **3×** e as três são a mesma união de tipo TypeScript (`'single' \| 'variable' \| 'combo'` em `Create.tsx:48,79` e `Edit.tsx:38`). A UI drag-drop segue no backlog (ver §Rastreabilidade). | `git grep -n -i "combo" -- 'resources/js/Pages/Produto/*.tsx'` |
| **2** | **A cobertura que parecia existir NÃO RODA.** `tests/Feature/Domain/Inventory/{BomResolverTest,ReservarEstoqueBomTest}.php` fazem `markTestSkipped` quando `config('database.default') !== 'sqlite'`; o comentário deles diz *"cobertura real é na lane sqlite (per-PR)"* — mas eles **não estão** em `.github/ci-sqlite-pest.list`, e a varredura contada de `Domain/Inventory` em `.github/` + `scripts/` devolve **0**. O nightly do CT 100 roda `DB_CONNECTION=mysql` → auto-pulam. **Skip-as-pass em todo lugar.** | `git grep -n "Domain/Inventory" -- .github/ scripts/` · `grep -c "Domain/Inventory" .github/ci-sqlite-pest.list` |
| **3** | **`ProductBomController` tem ZERO testes.** A API de BOM (3 endpoints, guard Tier 0 em cada) nunca foi exercitada por teste algum. | `git grep -rln "ProductBomController" -- tests/ Modules/` |

> 🔍 **Qual porta eu medi** (a distinção que o LC-08 cobra): *"roda em algum lugar?"* → `phpunit.xml`
> (`./tests/Feature` recursivo) + [`shards-plan.mjs`](../../../../scripts/tests/shards-plan.mjs) —
> **sim, o nightly os enumera**, e é justamente lá que eles se auto-pulam. *"roda no PR?"* →
> allowlist das lanes — **não**. *"bloqueia merge?"* →
> [`required-checks-baseline.json`](../../../../governance/required-checks-baseline.json) — **não**.
> O fato #2 é sobre as **duas primeiras**: existir no shard e ser executado não são a mesma coisa.

**Consequência para o escopo:** os UCs abaixo cobrem o que **existe e é alcançável** — a API de
BOM e o resolver que consome — em **MySQL real**, onde `product_bom` vive no schema baseline
(`grep -c product_bom database/schema/mysql-schema.sql` → 3). Nenhum UC antecipa a UI da
`US-PROD-025` (`todo`): caso sem código vira órfão, e órfão bloqueia o merge de quem for
implementar (`proibicoes.md` §5, 2026-07-16).

⚖️ **Força do veredito destes UC — `advisory`.** Lane `PHP / Pest (Estoque · MySQL)`, fora do
[`required-checks-baseline.json`](../../../../governance/required-checks-baseline.json):
**reprovação é visível e não bloqueia merge.**

---

## Rastreabilidade

| UC | Caso de uso | Prio | Contrato | Teste | Status |
|----|-------------|------|----------|-------|--------|
| UC-PBOM-01 | Componente de outro business não entra no BOM | must `[T0]` | `CU-PROD-05`.4 + [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md) | `ProdutoBomContratoTest` | 🧪 (verde esperado) |
| UC-PBOM-02 | Variação de outro business não vira componente do kit | must `[T0]` | `CU-PROD-10`.1 + precedente `UC-PTAB-04` | `ProdutoBomContratoTest` | 🧪 (vermelho **esperado** — predição) |
| UC-PBOM-03 | O BOM gravado pela API é o que o resolver baixa, multiplicado | must `[V0]` | `CU-PROD-05`.2/.3 + `AR-PROD-156/158` | `ProdutoBomContratoTest` | 🧪 (verde esperado) |
| UC-PBOM-04 | Kit alheio não é listado nem tem componente removido | must `[T0]` | `CU-PROD-05`.4 (hoje **⬜** no SDD) | `ProdutoBomContratoTest` | 🧪 (verde esperado) |

> 🧪 **e não ✅/❌**: eu não rodo teste (CT 100 · [ADR 0062](../../../decisions/0062-separacao-runtime-hostinger-ct100.md)).
> "Vermelho esperado" é **predição** derivada de leitura + varredura contada — o veredito é da lane.

---

## UC-PBOM-01 · Componente de outro business não entra no BOM · `must` `[T0]`

- **Persona:** qualquer tenant. O componente **também é um produto** — e é dele que o estoque sai
  quando o kit é vendido. Se uma peça alheia entra na receita, a venda de um kit meu baixa saldo
  do vizinho de servidor.
- **Aceite:** *Dado* que um componente **meu** grava (pré-condição, `201`) · *Quando* posto
  `component_id` de **outro** business · *Então* nenhuma linha do meu kit aponta pra aquele produto.
- **Teste:** [`ProdutoBomContratoTest`](../../../../tests/Feature/Produto/ProdutoBomContratoTest.php)
  — `UC-PBOM-01 · componente de outro business não entra no BOM (Tier 0)`.
- **Contrato:** `CU-PROD-05` item 4 `[T0]` (*"BOM `ScopeByBusiness` + `firstOrFail` cross-tenant"*)
  + [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md) + o próprio docblock do
  controller (*"Tier 0: componente também precisa pertencer ao business"*).
- **Regressão que defende:** o guard existe (`Product::where('id', $data['component_id'])
  ->where('business_id', $businessId)->firstOrFail()`) e **nunca foi executado por teste**. É o
  segundo `firstOrFail` do método — o tipo de linha que some num refactor de "simplificar
  validação" sem que nada acuse.
- **Status: 🧪** — verde esperado (trava de invariante).

---

## UC-PBOM-02 · Variação de outro business não vira componente do kit · `must` `[T0]`

- **Persona:** qualquer tenant. É o **eixo variação** do cross-tenant (o UC-PBOM-01 é o eixo
  produto). Numa grade tam×cor, é a variação que endereça o saldo — apontar a variação alheia
  baixa o estoque dela.
- **Aceite:** *Dado* que o par componente+variação **meus** gravam (pré-condição, `201`) ·
  *Quando* posto `component_id` **meu** com `component_variation_id` de **outro** business ·
  *Então* nenhuma linha do meu kit referencia aquela variação.
- **Teste:** [`ProdutoBomContratoTest`](../../../../tests/Feature/Produto/ProdutoBomContratoTest.php)
  — `UC-PBOM-02 · variação de outro business não vira componente do kit (Tier 0)`.
- **Contrato:** `CU-PROD-10` item 1 `[must][T0]` — cujo texto **já previu este caso**: *"o próximo
  model pendurado em `Product` nasce com o mesmo buraco"*.
- **Regressão que defende (o achado):** o controller valida o **produto** componente contra o
  business, mas `component_variation_id` e `parent_variation_id` passam só por
  `'nullable|integer'` na `$request->validate` e vão **diretos** pro `ProductBom::create`.
  Varredura contada no arquivo (2026-07-27): **4** `where('business_id', …)` + **4**
  `firstOrFail()` — todos sobre `Product` (pai/componente) ou `ProductBom` — e **0** que toquem
  `variations`. É a **terceira instância** da mesma família:
  `price_group_id` cru em `saveSellingPrices` (`UC-PTAB-04`, vermelho em CI,
  [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)) → o mesmo em `bulkUpdate`
  (`UC-PBULK-03`) → agora a variação no BOM.
  > O assert é **neutro quanto ao remédio**: rejeitar a requisição (422/404) ou ignorar o campo
  > são dois fixes legítimos; o UC só nega a persistência do id alheio.
- **Status: 🧪** — vermelho **esperado** (predição; veredito da lane).

---

## UC-PBOM-03 · O BOM gravado pela API é o que o resolver baixa, multiplicado · `must` `[V0]`

- **Persona:** Larissa monta um "kit uniforme" (4 botões + 0,5 m de tecido) e vende 3. O estoque
  que tem de sair é **12 botões e 1,5 m** — não 1 kit.
- **Aceite:** *Dado* dois componentes gravados pela API (`qty_required` 4 e 0,5) · *Quando* o
  resolver resolve o kit com multiplicador 3 · *Então* o kit **não** volta como folha, e os
  componentes somam 12 e 1,5.
- **Teste:** [`ProdutoBomContratoTest`](../../../../tests/Feature/Produto/ProdutoBomContratoTest.php)
  — `UC-PBOM-03 · o BOM gravado pela API é o que o resolver baixa, com a quantidade multiplicada`.
- **Contrato:** `CU-PROD-05` item 2 (*"BOM CRUD API multi-tenant funciona"*) + item 3 `[reg]`
  (*"baixa-de-componente do kit no PDV comprovada"*) + `AR-PROD-158` `[V0]` (o valor da linha do
  componente é quantidade × valor) + `AR-PROD-156` `[V0][calc]` (quantidade **fracionária** por
  fórmula/dimensão é a regra no legado, ex. `21,0000 M`).
- **Regressão que defende:** hoje as duas metades do fluxo **não têm nenhum teste em comum** — a
  API não tem teste, e o teste do resolver monta o próprio schema sqlite e não roda em lane
  nenhuma (fato #2 acima). Este UC é a costura: prova que o que a API grava é o que o consumo lê,
  em MySQL real. O componente fracionário (0,5) é deliberado: é onde um cast pra inteiro ou um
  `round()` indevido apareceria.
- **Status: 🧪** — verde esperado (trava do contrato de ida-e-volta).

---

## UC-PBOM-04 · Kit alheio não é listado nem tem componente removido · `must` `[T0]`

- **Persona:** qualquer tenant. Quem **lê** o BOM alheio mapeia a receita e o custo do concorrente;
  quem **apaga** sabota a produção dele.
- **Aceite:** *Dado* que o `GET` do **meu** kit responde `200` (pré-condição) · *Quando* faço
  `GET` do BOM de um produto de **outro** business · *Então* recebo `404`. E *quando* faço
  `DELETE` de uma linha de BOM alheia · *Então* a linha continua lá.
- **Teste:** [`ProdutoBomContratoTest`](../../../../tests/Feature/Produto/ProdutoBomContratoTest.php)
  — `UC-PBOM-04 · kit de outro business não é listado nem tem componente removido (Tier 0)`.
- **Contrato:** `CU-PROD-05` item 4 `[T0]`, **literalmente** — e o SDD marca esse item hoje como
  **⬜ não verificado** (*"nenhum teste cita; o ✅ da v1.0.0 valia por leitura de código, não por
  execução"*). Este UC é a execução que faltava.
- **Regressão que defende:** o `destroy` escopa por **três** condições (`id` + `business_id` +
  `parent_product_id`) e o `index` por duas — tudo em `firstOrFail`. Sem teste, qualquer
  simplificação (*"o id do BOM já é único, não precisa do business"*) abre a porta e passa no
  code review. Este é também o único UC do módulo que exercita `ScopeByBusiness` sobre um model
  que **não** é `App\Product`.
- **Status: 🧪** — verde esperado (fecha um ⬜ declarado no SDD).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> ⚠️ Este diretório **não** é varrido pelo `casos-coverage-guard` (que vê só `Pages/**`), então o
> G-2 não pune um órfão aqui — o critério de parada é disciplina, não gate. Contrato em **1 fonte
> só**, ou remédio ainda não decidido, fica como prosa.

- **[BACKLOG] Ciclo transitivo A→B→A é aceito no cadastro e só explode no consumo.** O controller
  bloqueia apenas a **auto-referência** direta (`component_id === $productId` → 422) e declara no
  próprio comentário que *"BomResolver pega ciclos transitivos em runtime"*. Medido: o resolver
  lança `LogicException` (`visited[$productId]`) — ou seja, o kit fica **cadastrável e
  invendável**, e o erro aparece na hora da venda. Não vira UC porque asserir o contrário seria
  asserir **contra uma decisão de desenho declarada** — é [W] quem decide se validar no cadastro
  vale o custo (o legado é multi-nível por árvore, `AR-PROD-150`/`ORDEM_ARVORE`).
- **[BACKLOG] Não há endpoint de UPDATE nem dedupe.** A API tem `index`/`store`/`destroy`; alterar
  a quantidade de um componente exige apagar e recriar, e o **mesmo componente pode ser adicionado
  N vezes** (nenhuma unique key em `product_bom`). O legado tem "adicionar/duplicar/remover"
  explícitos (`AR-PROD-153`) — mas duplicar lá é intencional. Decisão [W]: unique
  `(parent_product_id, parent_variation_id, component_product_id, component_variation_id)` ou não.
- **[BACKLOG] O maior gap de paridade do cadastro: composição por FÓRMULA.** `AR-PROD-156a`
  enumera **11 tipos de fórmula** (`ÁREA QUADRADA` · `PERÍMETRO` · `ILHÓS` · `FOLHAS/CHAPA` ·
  `BARRAS` · `PROPORCIONAL` · …) que calculam a quantidade real a partir das dimensões, mais a
  planilha embutida (`AR-PROD-162..166`) e o **Rendimento** por componente (`AR-PROD-157`).
  `product_bom` tem `qty_required` fixo — sem dimensão, sem fórmula, sem rendimento. É o núcleo
  da precificação de comunicação visual. Já catalogado no SDD §5.4 e na
  [PARIDADE-charter-vs-legado.md](../PARIDADE-charter-vs-legado.md); é escopo de US, não de UC.
- **[BACKLOG] "Diferença no Valor" (`AR-PROD-159` `[V0]`) e os agregados do rodapé
  (`AR-PROD-160`).** O plug manual de reconciliação entre a soma das matérias-primas e o preço de
  venda do kit não tem contraparte. **1 fonte só** (Delphi) → gap, não UC.
- **[BACKLOG] O fallback `type='combo'` (`variations.combo_variations`) não tem teste em lane.** O
  resolver ainda aceita o formato legado do UltimatePOS, e a Blade
  `combo_product_form_part.blade.php` ainda o produz (`ProductController` monta `$combo_variations`
  em `store` e `update`). A única cobertura é o caso 5 do `BomResolverTest`, que não roda (fato #2).
  Vira UC quando houver fixture de produto `combo` legado com `combo_variations` preenchido.
- **[BACKLOG] O CONSUMO do kit já tem contrato no módulo ESTOQUE** — caso *"Fabricação / kit →
  consome componentes + produz acabado"* (`decreaseProductQuantityCombo` → `EstoqueFabricacaoTest`,
  este sim na allowlist da lane). Não se duplica aqui: o eixo deste arquivo é **cadastro +
  resolução**, o de lá é **movimentação**.
  > 🔍 **Por que o id daquele caso não é escrito por extenso aqui:** o
  > [`requisitos-status.mjs`](../../../../scripts/governance/requisitos-status.mjs) atribui a
  > **posse** de um UC ao primeiro `casos.md` que o cita, e ele varre por módulo. Citar o id de um
  > caso do módulo Estoque dentro de um `casos.md` do Produto faria o painel do Produto **contá-lo
  > como seu** — inflando o placar com requisito alheio. O ponteiro por título + arquivo de teste
  > re-localiza igual, sem mentir o número.

---

## Refs

- SDD (âncora dos CU): [`SDD-tela-cadastro-produto-v1.0.md`](../SDD-tela-cadastro-produto-v1.0.md)
  §5.3 **F10** (fluxo do BOM) + §6.1 `CU-PROD-05` / `CU-PROD-10` + §5.4 (a dívida central)
- Paridade Delphi: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../ANTI-REGRESSAO-cadastro-produto-legacy.md)
  **Parte 4** (`AR-PROD-150..168`)
- Controller: `app/Http/Controllers/Inventory/ProductBomController.php` (`index`/`store`/`destroy`).
  Re-localize com `grep -n "class ProductBomController" -r app/`
- Serviço de consumo: `app/Domain/Inventory/Services/BomResolver.php` (`resolve` + fallback legado)
- Model: `app/Domain/Inventory/Models/ProductBom.php` (tabela `product_bom`)
- Blade legada do combo: `resources/views/product/partials/combo_product_form_part.blade.php`
  + `combo_product_entry_row.blade.php`
- Vizinho que cobre o CONSUMO (módulo **Estoque**, não conta no placar do Produto):
  [`Estoque/Movimentacao.casos.md`](../../../../resources/js/Pages/Estoque/Movimentacao.casos.md)
  → caso *"Fabricação / kit → consome componentes + produz acabado"* (`EstoqueFabricacaoTest`)
- Painel da cadeia: [`_STATUS-GENERATED.md`](../_STATUS-GENERATED.md)
- Lane: `PHP / Pest (Estoque · MySQL)` (**advisory** — fora do `required-checks-baseline.json`)
