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
charter_version: 3.5
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
- 🟡 **Faixa de quantidade** (`De`/`Até`/`% Desconto`/`R$ Valor` — `AR-PROD-105..109`) —
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
- ❌ Editar nome de variation/price_group inline
- ❌ Bulk apply (mesmo preço em N variações) — Wave 3

## Invariantes de valor (Tier 0 — REGRA MESTRE)

> Esta tela declara **preço**. Toda regra abaixo é `[V0]` e tem teste que a defende — e, pela regra
> de precedência (`proibicoes.md`), **o teste verde vence este charter** se os dois discordarem.

- **Markup é o campo mestre** (`AR-PROD-095`, confirmado por Wagner 2026-07-15) — dele derivam
  margem, valor de venda e lucro previsto.
- **Grave 4 casas, exiba 2.** `variations.profit_percent` é `DECIMAL(22,4)`. Markup gravado com 2
  casas **não reconstitui** o preço de origem: custo 4.300,00 + markup 62,79% → **6.999,97**, não
  7.000,00 (medido no motor real). Com 2 casas, R$ 7.000,00 é inexpressável.
- **O mestre é condicional.** `AR-PROD-097` ("Mantém Margem na importação") decide quem ganha quando
  o custo muda: marcado → o Valor recalcula preservando a margem; desmarcado → o Valor fica.
  Não cravar "markup sempre vence" ignorando o flag.
- **Piso de venda.** `AR-PROD-101` (`R$ Valor mínimo de venda`) é piso — preço de tabela abaixo dele
  precisa de decisão explícita (hoje sem contrato; ver Backlog).

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
- ❌ **Mais de 2 eixos no cadastro.** Com 3 a matriz deixa de ser matriz — o Odoo achata o eixo Y em
  combinações e a exclusão vira inviável (*"impossible to exclude only certain combinations… where
  you have more than 2 variant attributes"*). Akeneo (PIM sério) limita a 2; Microvix idem.

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

> ### ⚠️ [W]/[F] DECIDIR — a aba Base edita VENDA, mas "markup é o campo mestre"
>
> Aberto em 2026-07-16 pelo item 0-A. O §Invariantes de valor deste charter diz **"Markup é o campo
> mestre (`AR-PROD-095`, confirmado por Wagner 2026-07-15) — dele derivam margem, valor de venda e
> lucro previsto"**. O Blade legado é coerente com isso: mostra **custo + markup% + venda** por
> variação (`product_variation_row.blade.php:60-84`), os três ligados.
>
> A grade Base do protótipo edita **só `default_sell_price`** — escolha de legibilidade ([F]
> 2026-07-16: *"você decide ao ver"*). Três saídas, nenhuma escolhida:
> **(a)** digitar a venda **recalcula o markup por trás** (bidirecional, como o legado);
> **(b)** a Base é **read-only** aqui e o preço se edita na aba **Custos** (que [F] já construiu —
> falta saber como ela trata produto **variável**: markup por produto ou por variação?);
> **(c)** a célula abre custo+markup+venda (fiel ao legado, mas 3 campos × N células).
>
> Enquanto não decide, o protótipo mostra (a) sem o recálculo — **o markup não existe na tela**, o
> que é a divergência honesta a resolver antes do F3. Sendo `[V0]`, a decisão carrega a REGRA MESTRE.

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
| 2026-07-16 | [F+CC] | **v3.5 — remove o delta por eixo + REABRE preço por quantidade.** 5º corte de [F], duas frentes. **(1)** *"se na variação gerada eu consigo alterar o valor, pra quê a função ajuste por tamanho?"* → **REMOVIDO**: era ambíguo (2 caminhos pro mesmo dado), o botão `aplicar` **nunca teve handler** (controle morto que passou 2 rodadas de verificação — eu media cálculo, não se os botões respondem) e **o charter já proibia** (`❌ Bulk apply — Wave 3`; "ajuste por eixo" é bulk com outro nome). Origem do erro: importei o `Value Price Extra` do Odoo sem checar o modelo — lá o preço é **composto** (template+extra), aqui a base é **digitada por célula**; o problema não existia. + 3 anti-padrões (2 caminhos pro mesmo dado · controle sem handler · importar solução de outro modelo). **(2)** *"no modelo de grade não vi as fórmulas de variação por quantidade"* → o Non-Goal "pertence ao charter da Variação" assumia o modelo **do legado** (`VARIACAO_TIPO`: quantidade vs cor/tamanho **excludentes**) — e [F] cravou que o legado não entra. Vira 🟡 **reaberto**, pesquisa de mercado disparada (sem schema hoje; cruza com grade E tabela = 3ª dimensão). |
| 2026-07-16 | [F+CC] | **v3.4 — a tabela tem DOIS MODOS.** 4º corte de [F]: *"nem sempre o cliente define o valor do produto na tabela por porcentagem, muitas vezes ele define o valor do produto dentro da tabela e no protótipo vejo apenas o campo de percentual"*. **Procedente — e o schema JÁ suportava:** `variation_group_prices.price_type ∈ {'fixed','percentage'}` (`fixed` = o valor É o preço), lido por mim no 1º dia e ignorado ao modelar. A pesquisa também já dizia, na linha do **Bling** que eu mesmo citei no charter: *"lista 'Customizada' → o valor do produto será personalizado na lista"*. A v3 afirmava "a lista **é** uma regra" — meia-verdade que forçava inventar regra + marcar cada célula como "exceção". Agora: modo **regra %** (célula digitada = exceção, tarja) OU **preço por produto** (sem %, célula digitada = O preço, neutro; não digitada = base). + 3 anti-padrões (toda tabela é regra · "%" que mente · alarme em dado normal). Revenda nasce manual no pino pra o contraste ser visível. |
| 2026-07-16 | [F+CC] | **v3.3** — 3º corte de [F]: *"se não existe um modelo de grade escolhido, não vejo a opção de adicionar um valor do produto na tabela de preço… não ter modelo de grade não invalida a possibilidade de existir uma ou mais tabelas de preço"*. **Procedente — e o mais grave dos três: o contrato JÁ dizia isso** (a tabela do 0-A, linha "Só tabela = grade de 1 célula (o `DUMMY`) × as tabelas") **e o protótipo violava**, exigindo os 2 eixos pra desenhar qualquer coisa → sem grade, o produto não tinha onde ser precificado nem na Base nem nas tabelas. Escrever a regra não implementa a regra. + **4 formas** (0 eixos → 1 célula · 1 eixo → **lista**, não matriz · 2 eixos → matriz) + 1 anti-padrão. Sem eixo some só o §preview (não há grade a gerar). Bug lateral achado na verificação: o `\` do header `Cor \ Tamanho` era escape inválido em JS (` \ ` → espaço) — corrigido. |
| 2026-07-16 | [F+CC] | **v3.2** — 2º corte de [F]: *"o produto pode ter só variação, ou só tabela, ou os dois — mas no protótipo a adição do preço está ligada somente ao preço por lista"*. **Procedente e era furo de MODELO, não de tela:** eu tratava o preço base como escalar ("R$ 100,00, vem da aba Custos") e pendurava toda a digitação debaixo da tabela → produto com variação e **sem** tabela não tinha onde ser precificado. Verificado: `variations` tem custo/markup/venda **por filho** (2017 + `product_variation_row.blade.php:60-84`), e `createSingleProductVariation()` grava o preço numa variação `DUMMY` → **produto simples é grade de 1 célula, não outro modelo**. + **item 0-A** (Base é a 1ª aba do seletor; a regra incide sobre a base **de cada célula**: −20% → Azul-P 80,00 · Azul-G 96,00) + tabela dos 3 casos + 3 anti-padrões (base escalar · tudo debaixo da tabela · Base e tabela como irmãs) + **§Backlog: tensão markup-mestre × Base editando venda** ([W]/[F] decidem — 3 saídas mapeadas). |
| 2026-07-16 | [F+CC] | **v3.1** — corte de [F] sobre o protótipo: *"não encontrei opção de selecionar o modelo de grade desejado"*. **Procedente** — a v3 descrevia o **preço** e calava sobre **de onde vêm os eixos**, e o pino desenhava a grade já montada. + **item 0 do contrato** (modelo de grade: `variation_templates` → `values`, existe desde 2017 com CRUD vivo e 2 selects encadeados no Blade legado; a tela React não usa) + 4 anti-padrões (grade já-montada · delete destrutivo · regenerar do zero · >2 eixos). Protótipo: matriz agora **gerada dos eixos**, com chips desmarcáveis + célula reativável + preview que conta sozinho. |
| 2026-07-16 | [F+CC] | **v3 — modelo REGRA + EXCEÇÃO.** [F] está construindo a aba "Preço especial" do cadastro (parte 2 de N; a aba Custos já saiu) e cravou o critério: *"a melhor usabilidade ganha, o legado Delphi não entra aqui"*. Pesquisa de mercado (13 sistemas, 2026-07-16) achou que **nenhum** renderiza variação × lista como matriz: 4 sistemas independentes (Shopify B2B · Tiny/Olist · Bling · Odoo) convergiram em **lista = regra %, célula = exceção**. Adicionado §Modelo de digitação + §Dependência (a regra-mãe **não tem coluna** — promove a `US-PROD-022` de extra a pré-requisito) + 6 anti-padrões com fonte. `related_prototype` deixa de ser `n/a` → protótipo navegável verificado no browser (regra −20%→−35%: herdadas 80,00→65,00, exceção fixa em 90,00). **Não revoga a v2** — refina o "como se digita"; o fluxo canônico e os Non-Goals dela seguem de pé. Efeito colateral: o 0-row da `US-PROD-027` morre por construção (célula em branco = nenhuma linha gravada), mas o efeito de 2ª ordem em Labels/Woo (3 dos 5 consumidores não guardam o `''`) segue decisão [W]. |
