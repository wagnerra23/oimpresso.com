---
page: /products/add-selling-prices/{id}
component: resources/js/Pages/Produto/SellingPrices.tsx
page_id: produto-tabela-preco
related_prototype: prototipo-ui/prototipos/produto-preco-especial/Produto - Preco Especial.html
owner: wagner
status: draft
last_validated: "2026-07-16"
parent_module: Produto
related_adrs: [93, 104, 107, 149, 182]
related_us: [US-PROD-022, US-PROD-023]
tier: A
charter_version: 3.8
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/produtos-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [SellingPrices]
  divergence_from_blueprint: "matriz variation × price_group é tabela densa específica — não é list cockpit padrão; mantém AppShellV2 + tokens + header pattern; diverge no conteúdo central. ADR 0149 §'Casos que NÃO se qualificam — bulk-edit datatable'"
---

# Page Charter — Tabela de Preço do produto (DRAFT)

> **v3 (2026-07-16).** Acrescenta o **modelo regra + exceção** (§ abaixo) — decidido por [F] com
> critério declarado *"a melhor usabilidade ganha; o legado Delphi não entra"*. A v2 descrevia a
> tela como declaração de preço **célula a célula**; a v3 mantém tudo o que ela diz sobre **quem é
> dono do quê** (fluxo canônico, Non-Goals) e troca só o **como se digita**: a lista passa a ser
> uma **regra (%)** e a célula vira **exceção**. Não revoga a v2 — refina o Goal "por
> linha/célula: valor + price_type". Âncora empírica: pesquisa de mercado 2026-07-16 (13 sistemas).

> **v2 (2026-07-15).** Reescrito pro modelo de negócio real, declarado por Wagner nesta data. A v1
> descrevia a tela como "matriz variations × price_groups" — verdadeiro como *forma*, mas escondia
> **quem é o dono do quê**: a tabela nasce fora do produto, o produto só declara o preço que assume
> nela, e o vínculo com cliente/tipo de venda acontece depois, em outro lugar. A v2 nomeia o fluxo
> inteiro pra que os três pedaços não sejam reinventados aqui.

## Mission

Declarar, no cadastro do produto, **o preço que ele assume em cada tabela de preço**. A tabela é
criada fora daqui; esta tela só a seleciona e precifica. Um produto tem **N tabelas**.

## Fluxo canônico (Wagner 2026-07-15 — vale sobre qualquer inferência)

1. **A tabela é criada fora do produto** — `/produto/unificado?tela=tabelas` (`App\SellingPriceGroup`).
2. **No cadastro do produto** (esta tela), o operador **seleciona** a tabela e define o preço que
   aquele produto assume **quando a tabela for aplicada**.
3. **Depois**, a tabela é vinculada **ao cadastro do cliente** ou **a um tipo de venda** — fora daqui.

> 🔑 **O produto NUNCA é vinculado diretamente ao cliente.** Preço de cliente é consequência da
> tabela que o cliente carrega, não de um vínculo produto×cliente. É o que torna o
> `AR-PROD-111..116` (Preço Especial do legado) um Non-Goal — ver abaixo.

## Modelo de digitação — REGRA + EXCEÇÃO (v3, 2026-07-16)

> **A tabela de preço tem DOIS MODOS — e os dois são de primeira classe:**
> **(1) Regra %** — um número resolve a tabela inteira; a grade é **calculada**; a célula digitada é
> **exceção**. **(2) Preço definido por produto** — **sem %**; o cliente digita o valor do produto
> **naquela tabela**; a célula digitada **é o preço**, não um desvio; célula não digitada usa o
> **preço base**. Decidido por [F] 2026-07-16 sob o critério *"a melhor usabilidade ganha — o legado
> Delphi não entra"*. Protótipo navegável: [`prototipo-ui/prototipos/produto-preco-especial/`](../../../../prototipo-ui/prototipos/produto-preco-especial/Produto%20-%20Preco%20Especial.html).
>
> ⚠️ **A v3 dizia só "a lista É uma regra" — meia-verdade, corrigida na v3.4** (4º corte de [F]:
> *"nem sempre o cliente define o valor do produto na tabela por porcentagem, muitas vezes ele
> define o valor do produto dentro da tabela e no protótipo vejo apenas o campo de percentual"*).
> **O schema já suportava e o charter ignorava:** `variation_group_prices.price_type ∈
> {'fixed', 'percentage'}` — `fixed` significa *o valor É o preço*. E a própria pesquisa citada na
> tabela abaixo já dizia, na linha do **Bling**: *"se você selecionar a lista 'Customizada', não
> será necessário informar acréscimo ou desconto, pois **o valor do produto será personalizado na
> lista**"*. Modelar só o % forçava o operador a inventar uma regra que não quer e marcar **cada
> célula como "exceção"** — semanticamente errado.

**O problema que ele resolve.** Variação × lista é **cartesiano**: 20 variações × 3 listas = 60
células. Digitar 60 números é a tela ruim — e a pesquisa achou que **nenhum dos 13 sistemas
pesquisados faz isso**. O padrão convergente (4 sistemas, independentes) é:

| Sistema | Como faz | Fonte |
|---|---|---|
| Shopify B2B | `-20%` no catálogo inteiro; preço fixo por variante **sobrescreve** o % | [catalogs](https://help.shopify.com/en/manual/b2b/catalogs/creating-catalogs) |
| **Tiny/Olist** (BR — o mais próximo do nosso caso) | define **%** da lista → aplica ao pai → confirma pra todas as variações → **edita/remove individualmente** depois | [listas-de-precos](https://ajuda.olist.com/precificacao/listas-de-precos) |
| Bling | lista percentual/valor · só a **"customizada"** pede preço item a item | [listas de preços](https://ajuda.bling.com.br/hc/pt-br/articles/360054015233-Como-criar-listas-de-pre%C3%A7os-para-os-produtos) |
| Odoo | `Fixed Price` / `Discount (%)` / `Formula` | [pricing](https://www.odoo.com/documentation/17.0/applications/sales/sales/products_prices/prices/pricing.html) |

**O contrato da tela:**

0-A. **O PREÇO BASE mora na VARIAÇÃO — e é a primeira aba do seletor.** `variations` tem
   `default_purchase_price` / `profit_percent` / `default_sell_price` / `sell_price_inc_tax`
   **por filho** (migration 2017 + `product_variation_row.blade.php:60-84`). **Produto simples não
   é outro modelo:** `ProductUtil::createSingleProductVariation()` cria 1 variação `DUMMY`
   (`is_dummy=1`) e grava o preço nela → **simples = grade de 1 célula**. Logo o seletor é
   `[Preço base] │ [Varejo] [Atacado] [Revenda]`, e os 3 casos caem sem tela condicional:

   | Produto tem | O que ele vê |
   |---|---|
   | **Só variação** (sem tabela) | só a aba **Base** — a grade com o preço dos filhos |
   | **Só tabela** (sem variação) | grade de **1 célula** (o `DUMMY`) × as abas de tabela |
   | **Os dois** | grade cheia × Base + tabelas |
   | Nenhum | 1 célula, só Base — o produto simples de sempre |

   **Eixo vazio NÃO zera a tela** — vira o `DUMMY`. São **4 formas**, e a grade assume a sua:

   | Eixos | Forma | Exemplo |
   |---|---|---|
   | 0 | **1 célula** | produto simples — a variação `DUMMY` |
   | 1 | **lista vertical** (não matriz) | só Tamanho: P/M/G × 1 coluna de preço |
   | 2 | **matriz** | Cor × Tamanho |
   | **3+** | **matriz de linhas COMBINADAS** (padrão Odoo — v3.8) | Tamanho nas colunas · `Cor · Material` nas linhas |

   ⛔ **Não ter modelo de grade NÃO invalida ter tabela de preço** — as duas coisas são
   independentes. Origem: 3º corte de [F] 2026-07-16 (*"o fato dele não ter um modelo de grade não
   invalida a possibilidade de existir uma ou mais tabelas de preço que serão aplicadas a ele"*),
   sobre a v3.2 do protótipo, que exigia **os 2 eixos** pra desenhar qualquer coisa → sem grade, o
   produto não tinha onde ser precificado **nem na Base nem nas tabelas**. ⚠️ O contrato desta
   tabela (linha "Só tabela") já dizia isso **e o protótipo violava** — o charter estava certo e o
   pino errado. Sem eixo, some só o §preview de geração (não há grade a gerar; o `DUMMY` já existe).

   ⚠️ **Base e tabela NÃO são a mesma natureza** — base é **dado real** (digitado, fonte); tabela é
   **regra** (calculada, derivada). A UI não finge que são irmãs: a Base vem primeiro, separada por
   divisor, sem caixa de regra, e a célula de exceção mostra o `calc.` de onde desviou.
   **Corolário:** a regra incide sobre a base **de cada célula**, não sobre um escalar —
   `−20%` com Azul-P (base 100) → **80,00** e Azul-G (base 120) → **96,00**. Um número só, bases
   diferentes. Origem: corte de [F] 2026-07-16 — *"o produto pode ter só variação, ou só tabela, ou
   os dois, mas no protótipo a adição do preço está ligada somente ao preço por lista"*.

0-B. **Os eixos vêm do MODELO DE GRADE — e o modelo se escolhe aqui.** `variation_templates` (id,
   name, business_id) → `variation_value_templates` (name, template_id) **existem desde 2017**, com
   CRUD vivo (`VariationTemplateController` + rota `variation-templates`). O Blade legado já
   encadeia os 2 selects — **modelo → valores (múltipla escolha)** —
   (`product_variation_row.blade.php:19-23`, servido por `ProductController:1351`); **a tela React
   não usa**. A tela seleciona (o modelo nasce em *Produto → Modelo de Variação*, como a tabela
   nasce fora — mesmo princípio do fluxo canônico acima) e **marcar ≠ todos**: o operador escolhe
   quais valores este produto tem. Isso É o "não gerar o que não existe", que a pesquisa apontou
   como o ponto fraco do mercado inteiro. Origem: corte de [F] 2026-07-16 — *"não encontrei opção
   de selecionar o modelo de grade"* — sobre a v1 do protótipo, que desenhava a grade já montada.
1. **Uma lista por vez** — seletor no topo (`Varejo | Atacado | Revenda`). Nunca as N juntas: é a
   dimensão que causa a explosão. Contador de exceções por lista no seletor (`Atacado •3`) mostra
   divergência sem precisar abrir.
2. **O modo da tabela — regra % OU preço por produto.** No modo regra: `Atacado = −20% sobre o
   preço base` resolve a tabela inteira com **um número**, incidindo sobre a base **de cada célula**
   (ver 0-A). No modo manual: **o campo de % não existe** (esconder, nunca mostrar "%" mentindo) e o
   operador digita o preço direto. Persistência: 1 row em `variation_group_prices` nos dois modos —
   muda o `price_type` e o significado, não o lugar.

   ⛔ **O visual não pode mentir sobre o modo.** Célula digitada em tabela **com regra** = exceção
   (tarja de aviso — fugiu da regra). Em tabela **manual** = o preço (neutro — é o ponto da tabela).
   Mesmo dado, significados opostos: pintar de aviso um preço normal treina o operador a ignorar o
   aviso. Vale pro contador da aba também (`•3` = "3 exceções" vs "3 preços definidos").
3. **A matriz mostra o preço EFETIVO**, nunca célula vazia — cinza/itálico = **herdado** da regra;
   negrito + tarja = **exceção** manual. O operador vê a verdade, não um formulário em branco.
4. **Digitar numa célula cria a exceção** daquela variação naquela lista. `↺` por célula volta ao
   calculado; "limpar exceções desta lista" no rodapé.
5. ~~**Delta por eixo ANTES de exceção por célula**~~ — **REMOVIDO na v3.5** (5º corte de [F]:
   *"se na variação gerada eu consigo alterar o valor, pra quê a função ajuste por tamanho?"*).
   **Procedente, e por 3 motivos:** (a) **ambíguo** — com a célula editável, o delta cria **dois
   jeitos de dizer a mesma coisa** e o operador não sabe qual ganha; (b) o botão "aplicar" **nunca
   teve handler — era controle morto**; (c) **o charter já proibia**: `❌ Bulk apply (mesmo preço em
   N variações) — Wave 3` (§Non-Goals) — "ajuste por eixo" é bulk-apply com outro nome.
   **De onde veio o erro:** a pesquisa recomendou o `Value Price Extra` do Odoo — mas lá o preço da
   variante é **composto** (template + extra do atributo); **aqui a base é digitada por célula**.
   Importei a solução sem checar se o problema existia neste modelo. Se um dia bulk-apply entrar
   (grade grande, "GG +5 em 4 cores"), é **como bulk explícito da Wave 3**, não como 2º modelo de
   preço concorrendo com a célula.
6. **Mudar a regra recalcula o herdado e NÃO toca na exceção** — é o invariante que define o
   modelo. Verificado no protótipo: regra −20%→−35% moveu as herdadas 80,00→65,00 e a exceção
   ficou em 90,00.

**Efeito colateral bom — o 0-row morre por construção.** Célula em branco = *herdada da regra* =
**nenhuma linha gravada** em `variation_group_prices`. Hoje a UI pré-preenche com `0` e grava
(`SellingPrices.tsx:73` · `add-selling-prices.blade.php:50` · `saveSellingPrices` filtra por
`isset`, não por valor), o que torna "sem preço" indistinguível de "preço zero" — a dívida da
`US-PROD-027` / `UC-PTAB-05`. No modelo novo isso **nasce certo**. ⚠️ **Mas o efeito de segunda
ordem é real e é decisão [W]:** menos linhas gravadas = mais retorno `''` do
`getVariationGroupPrice`, e **3 dos 5 consumidores não guardam** esse retorno
(`LabelsController:143` · `WoocommerceUtil:341,731` — etiqueta sai sem preço, Woo sincroniza
vazio). Ver §Pendência de CONTRATO do [`casos.md`](SellingPrices.casos.md).

## Faixa de quantidade (v3.6 — 2026-07-16)

> **A faixa é uma LINHA ESPARSA DENTRO de um CONTEXTO DE PREÇO. Não é 3ª dimensão materializada,
> não é seção órfã, não sai da aba.** Âncora: pesquisa de mercado 2026-07-16 (11 plataformas + 9 BR,
> schemas primários — GraphQL/OpenAPI/source, não help center).
>
> ⚠️ **A v3.6 dizia "e nunca existe no Preço base" — ERRADO, corrigido na v3.8.** Corte de [F]
> 2026-07-16 (*"a opção de criar a faixa por quantidade não aparece na aba de preço base, porquê?"*).
> **É o espelho exato do corte anterior dele** (*"não ter modelo de grade não invalida ter tabela"*):
> **não ter tabela não invalida ter faixa**. Produto sem tabela nenhuma + cliente leva 10 → o preço
> pode mudar. Eu tinha importado o `priceList: PriceList!` do Shopify como se fosse lei — mas lá
> **não existe preço base fora de catálogo**: todo preço É de um catálogo. Aqui a **Base é um
> contexto de preço** como outro qualquer. No schema: `tabela_preco_id NULL` = contexto base —
> mesma forma do `variacao_id NULL` = todas as variações. **Divergir do Shopify aqui é correto**,
> porque o modelo de dados é diferente.

**A prova de que a faixa pertence à tabela** é de schema, não de prosa: o `QuantityPriceBreak` do
Shopify tem `priceList: PriceList!` — **non-null**. Não existe faixa fora de uma lista.
([GraphQL Admin](https://shopify.dev/docs/api/admin-graphql/latest/objects/QuantityPriceBreak))

**O que mata a explosão.** 20 variantes × 3 tabelas × 3 faixas = **180 células** se materializar.
O operador autora **9 linhas** (3 tabelas × 3 faixas, `variacao_id = NULL`) e cobre as 180 —
**20× menos autoria, mesma expressividade**. Ancorado em: Odoo *"If no variants are selected, then
this price will apply to all variants of the product"*; Shopify *"10 price breaks per product
**applied to each variant**"*; Tiny/Olist (aplica no pai → edita individualmente depois).

**Formato** (conceito, não código — reusa o `price_type` que já existe):

```
preco_faixa
  tabela_preco_id  NOT NULL   ← a faixa PERTENCE à tabela (Shopify: priceList!)
  produto_id       NOT NULL
  variacao_id      NULL       ← NULL = todas as variações (Odoo applied_on)
  qtd_min          NOT NULL   ← só PISO, sem teto
  price_type       ∈ {fixed, percentage}
  valor
UNIQUE (tabela_preco_id, produto_id, variacao_id, qtd_min)
```

**As 4 decisões, e por quê:**

1. **Guarda só o PISO — mas EXIBE "de X até Y"** (v3.8, corte de [F]: *"falta a opção de faixa de
   x até y = R$"*). As duas coisas são verdadeiras e não brigam:
   - **Storage = só piso.** VTEX não tem `maxQuantity` no OpenAPI; Shopify e Odoo idem. A faixa
     seguinte **fecha a anterior** → **sobreposição e buraco são impossíveis por construção**. Quem
     usa piso+teto **compra** a obrigação de validar: o BigCommerce validou, o **Medusa não** e tem
     issue aberta ([#3584](https://github.com/medusajs/medusa/issues/3584)).
   - **UI = intervalo.** *"De X até Y"* é como o operador pensa — e o `Y` é **derivado** do piso da
     faixa seguinte (`próxima.qtd − 1`), nunca digitado. A tela mostra ainda a **faixa 0**
     (`de 1 até <1º piso − 1> → preço normal`), que fecha a régua mental. Medido: mudar o piso da 2ª
     faixa pra 30 fez o teto da 1ª virar **29** sozinho.

   ⛔ **O teto NÃO é campo.** Se virar input, volta a classe inteira de bug (buraco/sobreposição)
   que o piso-só elimina de graça. Exibir ≠ armazenar.
   ⚠️ **Custo declarado:** com piso-só é **inexprimível** *"de 10 a 20 = R$8, e acima de 20 volta ao
   normal"* — o 21+ herda o R$8. Não achamos precedente disso no mercado (preço de atacado não
   costuma subir de volta), mas é limite real: se aparecer, exige teto explícito e a validação junto.
2. **VOLUME (bloco), não graduated** `[V0]`. Atingiu a faixa, **o pedido inteiro reprecifica**:
   12 un a R$8 = **R$96**, não 9×R$10 + 3×R$8 = R$114. É o que Shopify/BigCommerce(`fixed`)/VTEX/
   TOTVS fazem e o que o atacadista BR espera; graduated é padrão de metering SaaS. **A diferença é
   ~33% no mesmo pedido** — cravar isto no ADR e testar.
3. **SUBSTITUI, NÃO MESCLA** `[V0]`. Se a faixa venceu, ela **é** o preço — a regra % da tabela
   **não aplica por cima**. Shopify verbatim: *"After you apply volume pricing to a product the
   price becomes fixed. Any overall adjustment discount set on the catalog won't apply."*
   BigCommerce: *"If a variant has a Price Record, any existing product-level bulk pricing will not
   apply in the cart."* É a regra que o Medusa **não escreveu** — e por isso tem bug aberto.
4. **`variacao_id` nullable no schema, UI de exceção depois.** NULL cobre 100% dos casos conhecidos;
   faixa **por variação não tem precedente documentado no BR** (seria design original). Nasce no
   schema pra não exigir migration em tabela de preço (Tier 0) quando alguém pedir.

**Precedência** (adaptada do `_order` do Odoo — a única que existe em **código**, não em prosa:
`"applied_on, min_quantity desc, categ_id desc, id desc"`), do mais forte ao mais fraco:

```
1. Faixa + exceção da variação   (tabela × variação × qtd_min)
2. Faixa do produto              (tabela × NULL × qtd_min)
3. Preço digitado na tabela      (exceção/manual, sem faixa)
4. Regra % da tabela             (sobre a base)
5. default_sell_price da variação (a base)
```
Filtra `qtd_min <= quantidade`, ordena por especificidade → maior `qtd_min` → desempate menor preço.

### ⚠️ O penhasco — conhecido, aceito, NÃO avisado

O volume cria **inversão**: com `1+ = R$10` e `10+ = R$8`, comprar **9 un custa R$90** e **10 un
custa R$80**. Comprar mais paga menos. É **matemático**, não tem como evitar mantendo bloco.

**Decisão [F] 2026-07-16: não se avisa.** É comportamento esperado do atacado — e é a **alavanca
comercial** da faixa (o vendedor *quer* que o cliente feche 10). Nenhum dos pesquisados avisa.

### ⛔ O ÚNICO aviso da tela: preço abaixo do CUSTO

**Decisão [F] 2026-07-16** — *"Só exiba uma mensagem de aviso caso o preço definido for menor que o
custo do produto. De resto não precisa exibir nada."*

O custo já existe: **`variations.default_purchase_price`, por variação**. O aviso é **por variação
afetada**, não em bloco — *"R$ 70,00 está abaixo do custo de Azul-G (R$ 75,00) — venda com
prejuízo"*. Vale pra **qualquer** preço (base, tabela, exceção, faixa), não só faixa. Avisa,
**não bloqueia**.

> Isto **fecha** o item *"Piso de venda vs preço de tabela"* que estava no §Backlog desde a v2
> (*"A tabela pode furar o piso? Sem contrato hoje"*). Agora tem contrato: **pode furar, mas avisa**
> — e o piso comparado é o **custo**. ⚠️ O `AR-PROD-101` (`R$ Valor mínimo de venda`, piso
> explícito do legado que **bloqueia** venda) é **outra coisa** e segue sem contrato.

### ⚠️ O charter da Variação está STALE — não reintroduzir os "modos excludentes"

O [`VariacaoPrecos.charter.md`](VariacaoPrecos.charter.md) vive **parkeado** na branch
`docs/charter-variacao-precos-parked` (`41046e1`, PR [#4324](https://github.com/wagnerra23/oimpresso.com/pull/4324)
**fechado** — nunca entrou; charter sem `.tsx` não passa nos 3 gates). Ele declara:

> ## Os dois modos (`AR-PROD-171` — `VARIACAO_TIPO`, **excludentes**)
> ### Modo A — Preço por quantidade · ### Modo B — Cor e Tamanho (grade)

⛔ **REVOGADO na v3.8.** *"Excludentes"* é `VARIACAO_TIPO`, **campo do Delphi** — e o legado não
entra ([F] 2026-07-16). **Quantidade e variação são ORTOGONAIS**: *quantidade* = quanto leva;
*variação* = qual filho. Cruzam-se (faixa × grade), não se alternam. O protótipo prova: as faixas
funcionam com 0, 1, 2 ou 3 eixos ligados.

Idem o **"Produto Vinculado"** do `AR-PROD-178` (faixa que materializa SKU) — ver o Non-Goal de
sub-unidade acima. **Quando esse charter desparkear, nasce v2 sem os dois.**

## ⚠️ Dependência que não existe no banco (bloqueia o item 2)

`selling_price_groups` tem **`name` · `description` · `business_id` · `is_active`** — e **nenhuma
coluna de regra**. A regra-mãe (`op` + `valor`) não tem onde morar hoje. Isso é exatamente a
**[US-PROD-022](../../../../memory/requisitos/Produto/SPEC.md)** (`SellingPriceGroup.mult` hardcoded
`1.00` — [ADR ARQ-0001](../../../../memory/requisitos/Produto/adr/arq/0001-selling-price-multiplier.md),
`proposed`), hoje catalogada como *"multiplicador oco"*. **A pesquisa promove essa US de extra a
coração da tela:** sem ela, a aba cai de volta na digitação célula a célula.

Sendo `[V0]` sobre preço, a US carrega a **REGRA MESTRE** (dupla-confirmação + antes→depois + [W]).

## Goals

- Selecionar tabela(s) de preço existentes e definir o preço do produto em cada uma
- **Regra por lista + exceção por célula** (v3 — ver § acima). O `price_type` (`fixed`/`percentage`)
  por célula da v2 **continua válido** no banco; o modelo v3 sobe o `percentage` um nível (da célula
  pra regra da lista) e usa a célula pro caso `fixed` de exceção
- Por linha/célula: valor + `price_type` (`fixed` / `percentage`)
- **Produto simples** (o caso comum): lista de tabelas × preço — 1 linha por tabela
- **Produto com variação**: a mesma declaração vira matriz (variação × tabela) — a variação em si
  é contrato de outro charter (`VariacaoPrecos.charter.md`), aqui só o preço por célula
- AppShellV2 + PageHeader 3 zonas ([ADR 0182](../../../../memory/decisions/0182-pageheadertabs-canon-pattern-telas.md)) — "Tabelas de preço · {nome produto}" + SKU mono
- Botão "Salvar tabelas" sticky topo + dirty-state
- Multi-tenant scopado `business_id` ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- Submit POST `/products/save-selling-prices`

## Non-Goals

- ❌ **Criar tabela de preço nova inline** — nasce em `/produto/unificado?tela=tabelas` (passo 1 do fluxo)
- ❌ **Vincular tabela ao cliente ou ao tipo de venda** — acontece fora do cadastro do produto (passo 3)
- ❌ **Preço especial por (produto × cliente) direto** — `AR-PROD-111..116` do legado (`% Acréscimo` /
  `% Desconto` sobre o `Valor Original`, gravado por produto×cliente). **Non-Goal declarado**
  (Wagner 2026-07-15): no modelo novo o preço de cliente vem da tabela vinculada ao cadastro do
  cliente. Divergência consciente vs o legado — não é regressão.
- ✅ **Faixa de quantidade** — **RESOLVIDO na v3.6, virou contrato** (ver §Faixa de quantidade
  abaixo). Histórico do que era, preservado:
- ~~🟡 **Faixa de quantidade**~~ (`De`/`Até`/`% Desconto`/`R$ Valor` — `AR-PROD-105..109`) —
  **REABERTO na v3.5**, era Non-Goal ("pertence ao charter da Variação"). 5º corte de [F]:
  *"no modelo de grade não vi as fórmulas de variação por quantidade"*. **Por que reabre:** o
  despacho pro charter da Variação assumia o modelo **do legado** — lá `VARIACAO_TIPO`
  (`AR-PROD-171`) faz "preço por quantidade" e "cor/tamanho" serem **modos excludentes**, e o
  charter parkeado da Variação herdou isso (Modo A vs Modo B). Mas [F] cravou que **o legado não
  entra** — logo "excludentes" é herança que talvez não sobreviva: *quantidade* (quanto leva) e
  *variação* (qual filho) parecem **eixos ortogonais**, não alternativas. **Fatos:** não existe
  schema de faixa de quantidade hoje (nenhuma tabela/coluna) — é feature nova por inteiro; e ela
  cruza com grade **e** com tabela (5×4 grade × 3 tabelas × 3 faixas = 3ª dimensão).
  **Status:** pesquisa de mercado disparada 2026-07-16 (onde a faixa mora · por-variante? ·
  dentro-da-lista? · como não explodir a UI · precedência). Decisão depois dela — mesmo caminho que
  acertou o regra+exceção. ⚠️ Enquanto isso, **não é esquecimento, é fila**.
- ❌ **"Caixa com 12" / embalagem — é SUB-UNIDADE, não variação nem faixa** (v3.8). Levantado por
  [F] 2026-07-16 (*"existem ambos os casos"*). **Já existe e está VIVO** — não é gap:
  `units.base_unit_id` + `units.base_unit_multiplier`
  ([migration 2018](../../../../database/migrations/2018_11_28_104410_modify_units_table_for_multi_unit.php))
  + `products.sub_unit_ids` (2019) + `purchase_lines.sub_unit_id` + `transaction_sell_lines.sub_unit_id`.
  Vender 1 Caixa **baixa 12 do estoque** — `SellPosController:637`
  (`$decrease_qty * $base_unit_multiplier`). O [`Inventory/SPEC.md`](../../../../memory/requisitos/Inventory/SPEC.md)
  cataloga como *"Multi-unit (compra em caixa, vende em UN) — **Funciona**"*; o que falta lá é
  **UI Inertia** + custo per unit, e isso é charter de `Pages/Unit/*` que **ainda não existe** (a
  tela é Blade legado — `Route::resource('units', UnitController::class)`).

  **A régua pra não reabrir o debate:** *vender a caixa diminui o estoque das soltas?*
  **Sim** → sub-unidade (mesmo produto físico, jeito de contar). **Não** → é **outro produto** no
  cadastro (você compra e estoca caixas). Se os dois precisarem se relacionar (abrir caixa → 12 un),
  aí é **BOM** — `ProductBom` já tem CRUD API sem UI, é a `US-PROD-025`.

  ⛔ **Não usar SKU/variação pra representar embalagem** — cria **dois estoques do mesmo produto
  físico** que não conversam (vende 8 caixas e o sistema segue dizendo 120 soltas). A pesquisa
  2026-07-16 cataloga como anti-padrão explícito (*"tier virar SKU… destrói estoque, relatório e
  NFe"* — workaround do Shopify Community). É o que o **"Produto Vinculado"** do `AR-PROD-178` faz —
  e ele é artefato do Delphi, que **não tinha sub-unidade nem BOM**. Legado não entra ([F]).
- ❌ Editar nome de variation/price_group inline
- ❌ Bulk apply (mesmo preço em N variações) — Wave 3

## Invariantes de valor (Tier 0 — REGRA MESTRE)

> Esta tela declara **preço**. Toda regra abaixo é `[V0]` e tem teste que a defende — e, pela regra
> de precedência (`proibicoes.md`), **o teste verde vence este charter** se os dois discordarem.

- ⚠️ ~~**Markup é o campo mestre** (`AR-PROD-095`, confirmado por Wagner 2026-07-15)~~ —
  **CORRIGIDO na v3.7. A afirmação juntava DUAS colunas diferentes** e citava a errada:

  | O que | É | Estado |
  |---|---|---|
  | **`MARGEM`** (cabeçalho) | `((Valor / Custo) − 1) × 100` | ✅ **confirmado por 5 caminhos** — `AR-PROD-007` |
  | **`CALC_PMARKUP`** (`AR-PROD-095`) | perfil composto de `PRODUTO_MARKUP` | ❌ **fórmula desconhecida** |

  **`MARGEM` ≠ `CALC_PMARKUP`** — em base real de cliente, **nenhuma** das 3.668 linhas com os dois
  preenchidos tinha valores iguais, e o `CALC_PMARKUP` **não fecha** com `((V/C)−1)×100` em
  linha nenhuma. O charter citava o `095` (o desconhecido) pra afirmar o comportamento do outro.

- **O CUSTO é a âncora — e o binding é BIDIRECIONAL** `[V0]` (`AR-PROD-006/007/008`, resolvido
  2026-07-15 por **5 caminhos independentes**: print + fonte Delphi + fonte oimpresso + base demo +
  **base real de cliente, 3.668 linhas**):

  - editar **Valor** → `Margem := ((Valor / Custo) − 1) × 100`
  - editar **Margem** → `Valor := Custo × (1 + Margem/100)`
  - editar **Custo** → **não propaga** (assimétrico — é a âncora)

  > *"Não é 'Valor recalcula Margem OU vice-versa' — é **ambos**, e o Custo é assimétrico."*

  E o motor do oimpresso **já faz isso hoje, em produção**: `public/js/product.js:300-341` — digitar
  markup escreve a venda (`:313`), digitar a venda regrava o markup (`:338`, via
  `Util::get_percent`, que é `((V−C)/C)×100` — o gêmeo exato do `AR-PROD-007`).
  **Não existe campo mestre no motor: mestre é quem foi digitado por último.**

- **Grave 4 casas, exiba 2.** `variations.profit_percent` é `DECIMAL(22,4)`. Markup gravado com 2
  casas **não reconstitui** o preço de origem: custo 4.300,00 + markup 62,79% → **6.999,97**, não
  7.000,00 (medido no motor real). Com 2 casas, R$ 7.000,00 é inexpressável.
  ⚠️ No legado o furo é **o inverso**: as colunas são `DOUBLE PRECISION` (não decimal com escala) →
  **não há truncamento na persistência**; as "6 casas" são **máscara de display**. Lá quem mente é
  a tela (`6,99997` exibido como `7,00` → 3 centavos em 1.000 un). Aqui é a gravação.

- **A propagação custo→preço é FLAG POR PRODUTO — e o oimpresso só tem metade** `[V0]`
  (`AR-PROD-097` = `TEM_MARGEM_FIXA_CONTIBUICAO`). Quando chega nota de compra com custo maior:

  | Flag | Comportamento | Base real (oficina) |
  |---|---|---|
  | **`N`** | mantém o **preço**, deixa a margem flutuar | **83,8%** |
  | `S` | mantém a **margem**, **sobe o preço** sozinho | **8,2%** |

  O oimpresso **não tem a flag** — implementa só o modo `N`, implicitamente
  (`ProductUtil::updateProductFromPurchase` recalcula `profit_percent` e preserva o preço). Migrar
  como está **funciona por acidente pros 84% e quebra silencioso pros 8,2%**. É **capacidade a
  preservar**, não bug a corrigir. Nenhum dos 17 ERPs pesquisados (8 BR + 9 globais) propaga
  automático por default — a Linx vende esse par como diferencial.
- **Preço abaixo do CUSTO avisa, não bloqueia** `[V0]` (v3.6, decisão [F]). Custo =
  `variations.default_purchase_price`, **por variação**. Vale pra qualquer preço — base, tabela,
  exceção ou faixa. O aviso nomeia **quais** variações dão prejuízo, nunca em bloco. É o **único**
  aviso de preço da tela: o penhasco do volume **não** se avisa (ver §Faixa de quantidade).

  > 🕳️ **FURO CONHECIDO — custo zero (v3.7).** O aviso é `preço < custo`; com **custo = 0 ele
  > nunca dispara** (nada é menor que zero). E custo zero **não é raro**: base real de cliente,
  > **453 de 4.342 produtos = 10,4%** sem custo — e **242 desses (53,4%) estão com preço zero**.
  > É o produto de **serviço**, que não tem custo por natureza.
  >
  > Pior: `Valor = (1 + Margem/100) × Custo` com custo 0 dá **preço 0**, e **não há guarda em
  > nenhum dos dois sistemas** — nem no Delphi (`ValidaNumero` só trata NaN/Inf **depois** da
  > divisão) nem aqui (`calc_percentage(0, 100, 0) = 0` → produto vendável a zero, **sem exceção e
  > sem log**). A assimetria prova que é acidente: a margem **de contribuição** tem guard explícito
  > (*"Não é possível alterar a Margem de Contribuição quando o Custo é 0"*); a margem normal não.
  >
  > Contexto: dos 17 concorrentes pesquisados, **nenhum** documenta tratamento de custo zero — o
  > Odoo tem ≥7 módulos de terceiros só pra alertar margem baixa. É buraco de mercado.
  >
  > **Decisão [F]/[W] pendente:** avisar *"produto sem custo — preço não é conferível"*? Avisar só
  > quando preço **também** é zero (o caso que sangra)? Ou aceitar como está? Sendo `[V0]`, carrega
  > a REGRA MESTRE. Enquanto não decide, **o aviso da tela tem esse cego declarado**.
- **Piso de venda `AR-PROD-101`** (`R$ Valor mínimo de venda`) — piso **explícito** do legado que
  **bloqueia** venda. É **outra coisa** que o aviso de custo acima (aquele avisa; este bloqueia) e
  **segue sem contrato** — decisão pendente no Backlog.

## UX Targets

- p95 < 800ms
- 1280px responsivo (matriz pode ter scroll horizontal se >5 tabelas)
- Tabular-nums em valores
- Dirty-state visível (badge "Não salvo" + salvar desabilitado sem mudança)
- Cmd/Ctrl+S salva sem sair da tela (preserveScroll) + toast
- Navegação por teclado entre células (setas + Enter)
- Erros de validação do servidor por célula (`useForm` errors, chave `group_prices.{pg}.{v}.price`)

## Anti-patterns

- ❌ `auth()->user()->business_id` (canon UPOS é session)
- ❌ Cor crua (tokens v4: `bg-background`/`bg-card`/`text-foreground`/`border-border`/`destructive`)
- ❌ Exibir markup/margem com a precisão de gravação (2 casas na tela, 4 no banco — não inverter)
- ❌ Tratar a matriz como forma padrão — produto simples é o caso comum e vê uma lista

### Anti-padrões do modelo regra+exceção (v3 — cada um tem dono e fonte)

- ❌ **Renderizar variação × lista como matriz 2D** (as 60 células). **Ninguém no mercado faz** —
  pesquisa 2026-07-16, 13 sistemas, zero. Uma lista por vez.
- ❌ **Célula vazia** esperando digitação. A célula mostra o **preço efetivo** (herdado da regra);
  vazio esconde a verdade e é o que gera o 0-row de hoje.
- ❌ **Mudar a regra e sobrescrever exceção.** Exceção é decisão humana explícita — regra só move o
  herdado. É o invariante do modelo.
- ❌ **Bulk editor que tira o operador da tela.** Queixa documentada da Shopify: o bulk editor
  *"displays variants in the order they were sorted… not by product grouping"* + crash por heap
  ([klinkode](https://klinkode.com/shopify-bulk-edit-variants/)). Edição é **inline, onde ele já
  está olhando**.
- ❌ **Paginar a matriz** — WooCommerce pagina e o resultado documentado é *"viewing 300 variations
  requires clicking through more than 20 separate pages"*
  ([wpsheeteditor](https://wpsheeteditor.com/problems-editing-hundreds-variations/)). Matriz
  paginada não é matriz: virtualizar > paginar.
- ❌ **Grade virar mini-Excel** (paste, fórmula, coluna dinâmica). Larissa (persona real, biz=4,
  1280px, não-técnica) precisa de *"uma tabelinha com números"* — a Shopify separou os fluxos
  justamente por isso. Ver [arte grade 2026-05-21](../../../../memory/sessions/2026-05-21-arte-grade-matrix-input-vestuario.md)
  §Anti-pattern 2.
- ❌ **Desenhar a grade "já montada"** sem o operador escolher o modelo/valores — foi o furo da v1
  do protótipo, cortado por [F]. A grade é **consequência** dos eixos, nunca um dado fixo.
- ❌ **Tratar o preço base como ESCALAR do produto** ("vem da aba Custos: R$ 100,00"). Base é
  **por variação** (ver 0-A) — foi o 2º furo do protótipo, cortado por [F]. Escalar só existe
  quando a grade tem 1 célula, e aí é o `DUMMY`.
- ❌ **Pendurar TODA a digitação de preço debaixo da tabela.** Produto com variação e **sem** tabela
  não teria onde ser precificado — foi exatamente o corte de [F]. A aba **Base** é a fonte e existe
  sempre; as tabelas são opcionais.
- ❌ **Dois caminhos pro mesmo dado.** Se a célula é editável, um "ajuste por eixo" que mexe no
  mesmo valor é ambiguidade — o operador não sabe qual ganha. Um dado, um lugar de editar. (5º
  corte de [F]; ver item 5.)
- ❌ **Controle que não faz nada.** O `aplicar` do delta nunca teve handler — passou 2 rodadas de
  verificação porque eu media *cálculo*, não *se os botões respondem*. Checklist de pino: todo
  controle interativo tem handler, ou não existe.
- ❌ **Importar solução de outro modelo sem checar se o problema existe no nosso.** O `Value Price
  Extra` do Odoo resolve preço **composto** (template + extra); nossa base é **digitada por
  célula** — o problema que ele resolve aqui não existia.
- ❌ **Assumir que toda tabela é uma regra %.** O cliente muitas vezes **digita o valor do produto
  na tabela** — o `price_type='fixed'` do schema é isso, e o Bling chama de "Customizada". Forçar
  o % obriga a inventar uma regra que ninguém quer e a marcar cada célula como "exceção". 4º corte
  de [F]; o schema já suportava e o charter ignorava.
- ❌ **Mostrar "%" quando o modo não tem percentual.** Se o modo é manual, o campo **some** — sufixo
  que mente é pior que campo ausente.
- ❌ **Pintar preço normal com cor de aviso.** Célula digitada em tabela manual **não é exceção** —
  é o preço. Alarme em dado normal treina o operador a ignorar alarme.
- ❌ **Exigir eixo (ou os 2 eixos) pra desenhar a grade.** Sem modelo → `DUMMY` → **1 célula**, e o
  produto segue com base **e** tabelas. Com 1 eixo → **lista**, não matriz. Grade e tabela são
  **independentes**: uma não é pré-requisito da outra. Foi o 3º corte de [F] — e o mais grave,
  porque o contrato (tabela dos 3 casos, linha "Só tabela") **já dizia isso** e o protótipo
  violava. Lição: escrever a regra não implementa a regra.
- ❌ **DELETAR combinação que não existe.** Desativar, sempre. Na Shopify deletar é a **única**
  saída e leva junto SKU, preço, peso e histórico de estoque, **sem undo**
  ([craftshift](https://craftshift.com/delete-out-of-stock-variants-on-shopify/)); a própria doc
  deles recomenda *"manage publishing… instead of deleting"*. O Bling acerta: *"você pode
  **desativar** a variação"*.
- ❌ **Regenerar a grade do zero** ao adicionar um valor de eixo. Geração é **incremental**: cor
  nova = só as combinações novas; as existentes ficam **intactas** (padrão Shopify — *"the system
  generates three new variants"*). Regenerar = perder SKU e estoque já digitados.
- ⚠️ ~~**Mais de 2 eixos no cadastro**~~ — **REVOGADO na v3.8.** Corte de [F] 2026-07-16: *"nos
  modelos de grade, existem casos especiais onde será necessário utilizar mais de duas grades"*.
  **O anti-padrão era meu, não do mercado:** Shopify permite **3** options e **Lightspeed** suporta
  3 atributos (cor/tam/material) — a pesquisa dizia isso e eu li como "2 é o teto". O que Akeneo e
  Microvix limitam a 2 é o **eixo estrutural do PIM**, não a tela de preço.
  **O que continua verdade:** com 3 eixos **deixa de ser matriz**. A saída é a do **Odoo**, verbatim:
  *"the matrix is organized by using the first attribute values as X-coordinates and will then
  **combine all other attribute combinations as Y-coordinates**"* — eixo 1 nas colunas, os demais
  **combinados** nas linhas:

  ```
  Cor · Material \ Tamanho │  P  │  M  │  G
  Azul · Algodão           │     │     │
  Azul · Poliéster         │     │     │
  Vermelho · Algodão       │     │     │
  ```
  Custo honesto (declarar, não esconder): **as linhas explodem** — 5 tam × 4 cor × 2 mat = 8 linhas
  × 5 colunas = **40 células**. O 3º eixo é **opt-in** (nasce vazio) justamente por isso; quem liga
  aceita a lista larga. E a **exclusão por célula** continua funcionando (é por célula, não por par
  de valores — foi por isso que o `Exclude for` do Odoo quebrou com >2, e o nosso não).

## Pest GUARD

```php
it('Page Inertia existe em Pages/Produto/SellingPrices.tsx')
it('Page declara matriz variations × priceGroups')
// ⚠️ REVOGADO na v2: a v1 prometia `it('Controller cross-tenant retorna 404')`.
// O teste nunca existiu (o que havia era um grep de string no fonte) e, quando foi
// escrito de verdade, o CI provou que a promessa era FALSA pro POST: `saveSellingPrices`
// engole a ModelNotFoundException num catch(\Exception) generico e devolve 302, nao 404.
// O GET devolve 404. Contrato real (isolamento, nao status HTTP) vive em SellingPrices.casos.md
// UC-PTAB-02; a divergencia 302-vs-404 esta no §Backlog de la aguardando decisao [W].
```

Contrato executável da tela: [`SellingPrices.casos.md`](SellingPrices.casos.md) — `UC-PTAB-01/02/03/04`
(`tests/Feature/Produto/TabelaPrecoContratoTest.php`, lane `Estoque · MySQL`).
Teste de valor que defende os invariantes acima (ancorado em `AR-PROD-093/094/095/006`):
`tests/Feature/Produto/FormacaoPrecoParidadeLegadoTest.php` (lane `Pest (Unit)`).

## Backlog de contrato (dívida conhecida — não é Non-Goal, é buraco)

> ### 🟡 A aba Base edita VENDA — a tensão ENCOLHEU (v3.7), sobra 1 escolha
>
> **Aberto na v3.6** sobre a premissa *"markup é o campo mestre, e a Base editando venda contradiz"*.
> **A premissa caiu** (ver §Invariantes): não existe campo mestre — o **Custo é a âncora** e
> Valor↔Margem é **bidirecional**, tanto no Delphi (`AR-PROD-008`, 5 caminhos) quanto no oimpresso
> (`product.js:319-341`, **em produção hoje**). Logo a saída **(a)** — digitar a venda regrava o
> markup — **não é divergência: é paridade** com o que já roda.
>
> **E o conflito só existe porque a aba Base existe.** Verificado: o `SellingPrices.tsx` atual
> **não toca** `default_sell_price` (`git grep` → **0 hits**) — ele só escreve `variation_group_prices`,
> outra tabela. Markup e preço de tabela **nunca se cruzaram**. Quem cruzou fui eu, ao criar a Base
> (que resolve o corte legítimo de [F]: *"produto pode ter só variação, sem tabela"*).
>
> **Sobram 2 saídas** (a (c) morreu: 3 campos × N células é a grade ilegível que a pesquisa reprova):
> - **(a) Bidirecional** — digitar venda regrava `profit_percent` via `Util::get_percent`. É paridade
>   com prod. ⚠️ Pegadinha medida: **custo 0 → markup vira 0 silenciosamente** (`product.js:332`).
> - **(b) Base read-only aqui**, editável só na Formação de Preço. Zero conflito, zero código — mas
>   ⚠️ **a aba Custos/Formação de Preço NÃO EXISTE em git** (varrido 2026-07-16: 0 `.tsx`, 0 branch;
>   `FormacaoPrecoParidadeLegadoTest.php:56` confirma — *"crava o contrato ANTES da tela existir;
>   implementar é US separada"*). Escolher (b) **cria dependência de uma tela que não existe**.
>
> Sendo `[V0]`, a decisão carrega a REGRA MESTRE. Enquanto não decide, o protótipo faz (a) **sem** o
> recálculo — o markup não aparece na tela, e essa é a divergência honesta a fechar antes do F3.

> ✅ **Fechados no mesmo PR ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)) — esta lista
> foi escrita ANTES deles existirem e ficou falsa ao mergear:**
> - ~~`casos.md` não existe~~ → [`SellingPrices.casos.md`](SellingPrices.casos.md) existe, com
>   `UC-PTAB-01..04` verdes na lane `Estoque · MySQL`. O trio fechou; o baseline caiu 316 → 315.
> - ~~O `it('Controller cross-tenant retorna 404')` NÃO existe~~ → existe comportamento de verdade
>   (`UC-PTAB-02` GET/POST + `UC-PTAB-04` price_group alheio). E provou que a promessa de **404**
>   era falsa no POST — ver §Pest GUARD.

- **Os testes legados seguem tautológicos** — `Wave2SellingPricesInertiaTest` /
  `Wave2SellingPricesBaselineTest` só fazem grep de string no fonte ("contém `variations.map`").
  Nenhum UC os cita de propósito. Ficam até alguém decidir se apagam ou viram teste de verdade.
- **Multiplicador da tabela (`SellingPriceGroup.mult`) é oco** — hardcoded `1.00`. US-PROD-022 /
  ADR ARQ-0001 (proposed). O `Unificado/Index.charter.md` registra a mesma pendência.
- **Piso `AR-PROD-101` vs preço de tabela** — sem contrato: a tabela pode furar o valor mínimo?
- **302-vs-404 no POST cross-tenant** — decisão [W] pendente; detalhe no §Backlog do `casos.md`.

## Refs

- Fluxo + regras de negócio: Wagner 2026-07-15 (esta v2)
- Anti-regressão legado: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md) §I/§J/§K (`AR-PROD-090..123`)
- Paridade: [`PARIDADE-charter-vs-legado.md`](../../../../memory/requisitos/Produto/PARIDADE-charter-vs-legado.md)
- Charter irmão (variação): `VariacaoPrecos.charter.md`
- Charter irmão (cadastro da tabela): [`Unificado/Index.charter.md`](Unificado/Index.charter.md) — sub-view "Tabelas de preço"
- RUNBOOK: [`_telas/RUNBOOK-produto-selling-prices.md`](../../../../memory/requisitos/Produto/_telas/RUNBOOK-produto-selling-prices.md) · Visual comparison: [`_telas/produto-selling-prices-visual-comparison.md`](../../../../memory/requisitos/Produto/_telas/produto-selling-prices-visual-comparison.md)
  <br>_(a v1 apontava pra `memory/requisitos/Inventory/…` — os dois docs migraram pra `Produto/_telas/`; link-rot corrigido nesta v2.)_
- ADR 0149 · ADR 0182

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Charter criado em Wave 2 B4 Produto. |
| 2026-05-31 | [DS-upgrade] | Paleta stone→tokens v4; header hand-rolled→tokens (breadcrumb/título/SKU); + dirty-state, Cmd+S, navegação teclado, erros por célula, toast. Contrato backend (group_prices, POST save-selling-prices, price_type) intacto. |
| 2026-07-15 | [CC] | **v2** — reescrito pro modelo real (Wagner): tabela nasce fora → produto seleciona + precifica → tabela vincula a cliente/tipo de venda; produto nunca vinculado direto ao cliente. Preço Especial produto×cliente (`AR-PROD-111..116`) vira **Non-Goal declarado**. Faixa de quantidade (`AR-PROD-105..109`) movida pro charter da Variação. + §Invariantes de valor (markup mestre, 4 casas, condicional ao `AR-PROD-097`) ancorados em teste. + §Backlog de contrato explicitando os buracos (casos.md ausente, testes tautológicos, cross-tenant prometido e inexistente, `mult` oco). |
| 2026-07-16 | [F+CC] | **v3.8 — 3 cortes de [F], e 2 revogam o que EU tinha escrito.** **(1)** *"a faixa por quantidade não aparece na aba de preço base, porquê?"* → a v3.6 dizia *"nunca existe no Preço base"*: **ERRADO**. É o **espelho exato** do corte dele de antes (*"não ter grade não invalida ter tabela"*): **não ter tabela não invalida ter faixa**. Importei o `priceList: PriceList!` do Shopify como lei — mas lá **não existe preço base fora de catálogo**; aqui a Base **é** um contexto de preço. Schema: `tabela_preco_id NULL` = base (mesma forma do `variacao_id NULL` = todas). **(2)** *"falta a opção de faixa de x até y"* → **guarda o piso, EXIBE o intervalo**: o teto é **derivado** (`próxima.qtd − 1`), nunca digitado, + faixa 0 (`de 1 até <piso−1> → preço normal`). Medido: mudar o piso da 2ª pra 30 → teto da 1ª vira 29 sozinho. Exibir ≠ armazenar: o teto como campo devolveria a classe de bug que o piso-só elimina. Custo declarado: *"de 10 a 20 e acima volta ao normal"* segue inexprimível. **(3)** *"existem casos especiais onde será necessário mais de duas grades"* → o anti-padrão **"máx. 2 eixos" era MEU, não do mercado**: Shopify permite 3, Lightspeed 3 (cor/tam/material); o limite de 2 do Akeneo/Microvix é do **eixo de PIM**, não da tela de preço. Revogado. 3+ eixos usa o **padrão Odoo** (eixo 1 nas colunas, demais **combinados** nas linhas), opt-in, com o custo declarado (5×4×2 = 8 linhas × 5 col = 40 células — medido). |
| 2026-07-16 | [F+CC] | **v3.7 — o "markup é mestre" estava ERRADO: citava a coluna errada.** Levantamento no código + `docs/produto-custo-margem-evidencia` (branch `b04ca994`, **não mergeada**) fecharam o `[?]` do `AR-PROD-008` com **5 caminhos** (print + fonte Delphi + fonte oimpresso + base demo + **base real de cliente, 3.668 linhas**). **`MARGEM` ≠ `CALC_PMARKUP`** — colunas distintas; **nenhuma** das 3.668 linhas tinha valores iguais e o `CALC_PMARKUP` (que É o `AR-PROD-095`) **não fecha** com `((V/C)−1)×100` em linha nenhuma: **sua fórmula segue desconhecida**. O charter citava o `095` (o desconhecido) pra afirmar o comportamento do outro, e eu repeti 3×. **O certo:** o **Custo é a âncora** (editar custo não propaga) e **Valor↔Margem é bidirecional** — e o oimpresso **já faz isso em produção** (`product.js:300-341`: markup→venda `:313`, **venda→markup** `:338`). **Não existe campo mestre: mestre é quem foi digitado por último.** + `AR-PROD-097` = `TEM_MARGEM_FIXA_CONTIBUICAO`, **flag POR PRODUTO** que o oimpresso **não tem** (só o modo `N`) — 83,8% `N` / **8,2% `S`** na base real → migrar como está funciona por acidente pros 84% e **quebra silencioso pros 8,2%**. + 🕳️ **FURO do aviso de custo**: `preço < custo` **nunca dispara com custo 0**, e custo 0 é **10,4% da base real** (453/4.342) — **53,4% deles já com preço zero**. Sem guarda em nenhum dos 2 sistemas. Decisão [F]/[W] pendente. A tensão da Base encolheu: (a) bidirecional **é paridade com prod**, não divergência; (c) morreu; (b) depende de tela que **não existe em git**. |
| 2026-07-16 | [F+CC] | **v3.6 — FAIXA DE QUANTIDADE vira contrato** (fecha o 🟡 reaberto na v3.5). Pesquisa de mercado 2026-07-16 (11 plataformas + 9 BR, **schemas primários** — GraphQL/OpenAPI/source, não help center). **Achado central:** quantidade É 3ª dimensão, mas **ninguém materializa** — todos usam **linha esparsa** `(tabela × variante × qtd_min)`. Prova de schema: `QuantityPriceBreak.priceList: PriceList!` **non-null** (Shopify) → a faixa **pertence à tabela**. 180 células viram **9 linhas** autoradas (`variacao_id NULL` = todas — Odoo `applied_on` + cascata Tiny). 4 decisões: **só piso** (VTEX sem `maxQuantity` → overlap impossível por construção; BigCommerce tem teto e teve que validar, Medusa não validou → issue #3584) · **VOLUME/bloco** `[V0]` (~33% de diferença vs graduated) · **SUBSTITUI, NÃO MESCLA** `[V0]` (Shopify: *"the price becomes fixed. Any overall adjustment discount won't apply"*) · **`variacao_id` nullable no schema, UI depois** (sem precedente BR). Precedência adaptada do `_order` do Odoo (única em código, não prosa). **Penhasco** (9un=R$90 · 10un=R$80): conhecido, **aceito, NÃO avisado** — decisão [F]: é a alavanca comercial do atacado. **⛔ ÚNICO aviso da tela: preço < CUSTO** (decisão [F]: *"só exiba aviso caso o preço definido for menor que o custo. De resto não precisa exibir nada"*) — custo já existe (`variations.default_purchase_price`), aviso **por variação afetada**, avisa e não bloqueia. **Isso FECHA o backlog "piso de venda vs preço de tabela"** aberto na v2 (pode furar, mas avisa; o piso comparado é o custo). O `AR-PROD-101` (piso que **bloqueia**) é outra coisa e segue sem contrato. **Achado comercial:** Bling/Tiny/Conta Azul/Microvix = **zero** faixa; Omie = meia-solução em "característica", só no PDV; faixa real só em TOTVS/Sankhya (ERP grande) → **espaço aberto**. |
| 2026-07-16 | [F+CC] | **v3.5 — remove o delta por eixo + REABRE preço por quantidade.** 5º corte de [F], duas frentes. **(1)** *"se na variação gerada eu consigo alterar o valor, pra quê a função ajuste por tamanho?"* → **REMOVIDO**: era ambíguo (2 caminhos pro mesmo dado), o botão `aplicar` **nunca teve handler** (controle morto que passou 2 rodadas de verificação — eu media cálculo, não se os botões respondem) e **o charter já proibia** (`❌ Bulk apply — Wave 3`; "ajuste por eixo" é bulk com outro nome). Origem do erro: importei o `Value Price Extra` do Odoo sem checar o modelo — lá o preço é **composto** (template+extra), aqui a base é **digitada por célula**; o problema não existia. + 3 anti-padrões (2 caminhos pro mesmo dado · controle sem handler · importar solução de outro modelo). **(2)** *"no modelo de grade não vi as fórmulas de variação por quantidade"* → o Non-Goal "pertence ao charter da Variação" assumia o modelo **do legado** (`VARIACAO_TIPO`: quantidade vs cor/tamanho **excludentes**) — e [F] cravou que o legado não entra. Vira 🟡 **reaberto**, pesquisa de mercado disparada (sem schema hoje; cruza com grade E tabela = 3ª dimensão). |
| 2026-07-16 | [F+CC] | **v3.4 — a tabela tem DOIS MODOS.** 4º corte de [F]: *"nem sempre o cliente define o valor do produto na tabela por porcentagem, muitas vezes ele define o valor do produto dentro da tabela e no protótipo vejo apenas o campo de percentual"*. **Procedente — e o schema JÁ suportava:** `variation_group_prices.price_type ∈ {'fixed','percentage'}` (`fixed` = o valor É o preço), lido por mim no 1º dia e ignorado ao modelar. A pesquisa também já dizia, na linha do **Bling** que eu mesmo citei no charter: *"lista 'Customizada' → o valor do produto será personalizado na lista"*. A v3 afirmava "a lista **é** uma regra" — meia-verdade que forçava inventar regra + marcar cada célula como "exceção". Agora: modo **regra %** (célula digitada = exceção, tarja) OU **preço por produto** (sem %, célula digitada = O preço, neutro; não digitada = base). + 3 anti-padrões (toda tabela é regra · "%" que mente · alarme em dado normal). Revenda nasce manual no pino pra o contraste ser visível. |
| 2026-07-16 | [F+CC] | **v3.3** — 3º corte de [F]: *"se não existe um modelo de grade escolhido, não vejo a opção de adicionar um valor do produto na tabela de preço… não ter modelo de grade não invalida a possibilidade de existir uma ou mais tabelas de preço"*. **Procedente — e o mais grave dos três: o contrato JÁ dizia isso** (a tabela do 0-A, linha "Só tabela = grade de 1 célula (o `DUMMY`) × as tabelas") **e o protótipo violava**, exigindo os 2 eixos pra desenhar qualquer coisa → sem grade, o produto não tinha onde ser precificado nem na Base nem nas tabelas. Escrever a regra não implementa a regra. + **4 formas** (0 eixos → 1 célula · 1 eixo → **lista**, não matriz · 2 eixos → matriz) + 1 anti-padrão. Sem eixo some só o §preview (não há grade a gerar). Bug lateral achado na verificação: o `\` do header `Cor \ Tamanho` era escape inválido em JS (` \ ` → espaço) — corrigido. |
| 2026-07-16 | [F+CC] | **v3.2** — 2º corte de [F]: *"o produto pode ter só variação, ou só tabela, ou os dois — mas no protótipo a adição do preço está ligada somente ao preço por lista"*. **Procedente e era furo de MODELO, não de tela:** eu tratava o preço base como escalar ("R$ 100,00, vem da aba Custos") e pendurava toda a digitação debaixo da tabela → produto com variação e **sem** tabela não tinha onde ser precificado. Verificado: `variations` tem custo/markup/venda **por filho** (2017 + `product_variation_row.blade.php:60-84`), e `createSingleProductVariation()` grava o preço numa variação `DUMMY` → **produto simples é grade de 1 célula, não outro modelo**. + **item 0-A** (Base é a 1ª aba do seletor; a regra incide sobre a base **de cada célula**: −20% → Azul-P 80,00 · Azul-G 96,00) + tabela dos 3 casos + 3 anti-padrões (base escalar · tudo debaixo da tabela · Base e tabela como irmãs) + **§Backlog: tensão markup-mestre × Base editando venda** ([W]/[F] decidem — 3 saídas mapeadas). |
| 2026-07-16 | [F+CC] | **v3.1** — corte de [F] sobre o protótipo: *"não encontrei opção de selecionar o modelo de grade desejado"*. **Procedente** — a v3 descrevia o **preço** e calava sobre **de onde vêm os eixos**, e o pino desenhava a grade já montada. + **item 0 do contrato** (modelo de grade: `variation_templates` → `values`, existe desde 2017 com CRUD vivo e 2 selects encadeados no Blade legado; a tela React não usa) + 4 anti-padrões (grade já-montada · delete destrutivo · regenerar do zero · >2 eixos). Protótipo: matriz agora **gerada dos eixos**, com chips desmarcáveis + célula reativável + preview que conta sozinho. |
| 2026-07-16 | [F+CC] | **v3 — modelo REGRA + EXCEÇÃO.** [F] está construindo a aba "Preço especial" do cadastro (parte 2 de N; a aba Custos já saiu) e cravou o critério: *"a melhor usabilidade ganha, o legado Delphi não entra aqui"*. Pesquisa de mercado (13 sistemas, 2026-07-16) achou que **nenhum** renderiza variação × lista como matriz: 4 sistemas independentes (Shopify B2B · Tiny/Olist · Bling · Odoo) convergiram em **lista = regra %, célula = exceção**. Adicionado §Modelo de digitação + §Dependência (a regra-mãe **não tem coluna** — promove a `US-PROD-022` de extra a pré-requisito) + 6 anti-padrões com fonte. `related_prototype` deixa de ser `n/a` → protótipo navegável verificado no browser (regra −20%→−35%: herdadas 80,00→65,00, exceção fixa em 90,00). **Não revoga a v2** — refina o "como se digita"; o fluxo canônico e os Non-Goals dela seguem de pé. Efeito colateral: o 0-row da `US-PROD-027` morre por construção (célula em branco = nenhuma linha gravada), mas o efeito de 2ª ordem em Labels/Woo (3 dos 5 consumidores não guardam o `''`) segue decisão [W]. |
