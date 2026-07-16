---
name: Preço especial — regra + exceção (F1 protótipo)
description: Pino visual canônico da aba "Preço especial" do cadastro de produto. Lista de preço = REGRA (%); célula = EXCEÇÃO. Gate F1.5 ADR 0107 antes de F3.
type: prototype
status: F1-commit-only
created: 2026-07-16
persona: Larissa @ ROTA LIVRE · biz=4 · 1280px · não-técnica · vestuário
related:
  - resources/js/Pages/Produto/SellingPrices.charter.md
  - resources/js/Pages/Produto/SellingPrices.casos.md
  - memory/requisitos/Produto/SPEC.md
  - memory/sessions/2026-05-21-arte-grade-matrix-input-vestuario.md
  - memory/decisions/0104-processo-mwart-canonico-unico-caminho.md
  - memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md
  - memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md
---

# F1 — Preço especial (regra + exceção)

Pino visual da aba **Preço especial** do cadastro de produto — parte 2 da construção da tela
([F] 2026-07-16; a aba **Custos** já saiu). Cobre os dois eixos que se cruzam ali: **tabela de
preço** (em qual lista) e **variação por grade cor/tamanho** (qual filho).

## Por que existe

Variação × lista é **cartesiano**: 20 variações × 3 listas = **60 células**. A tela ingênua manda
digitar as 60. A pesquisa de mercado de 2026-07-16 (13 sistemas) achou que **nenhum deles faz
isso** — e que 4 independentes (Shopify B2B · Tiny/Olist · Bling · Odoo) convergiram no mesmo
modelo:

> **A lista de preço é uma REGRA (%). A grade é CALCULADA. A célula é EXCEÇÃO.**

Isso troca *60 células digitáveis* por *1 regra + as poucas exceções conscientes*.

Critério de decisão declarado por [F]: **"a melhor usabilidade ganha — o legado Delphi não entra
aqui"**. Por isso este protótipo **não** espelha `AR-PROD-1xx`; ele espelha o estado da arte.

## O que dá pra fazer nele (é navegável, não é imagem)

| Interação | O que prova |
|---|---|
| **§Faixas — em QUALQUER aba, inclusive a Base** | não ter tabela não invalida ter faixa. Base = contexto de preço (`tabela_preco_id NULL`) |
| **`de 10 até 49 un`** | o **teto é derivado** do piso da faixa seguinte — não é campo. Mude o piso da 2ª → o teto da 1ª acompanha |
| **Aba `Atacado` → §Faixas** | faixa `10un = R$80` passa · `50un = R$55` **avisa**: abaixo do custo, por variação afetada |
| **Corrija a faixa pra R$70** | o aviso **encolhe** — só a Azul-G (custo R$75) segue em prejuízo. Não avisa em bloco. |
| **Aba `Preço base`** | **sem faixas** — elas vivem DENTRO da tabela (Shopify: `priceList!`) |
| **Aba `Revenda`** | tabela em modo **manual** — **sem campo de %**: o cliente digita o preço do produto nela |
| **Troque o modo** da tabela (regra ↔ manual) | o mesmo valor deixa de ser "exceção com tarja" e vira "o preço" (neutro) — o visual não mente sobre o modo |
| **Tire os 2 modelos** (`Selecione um modelo…`) | vira **1 célula** (o `DUMMY`) — e as **tabelas continuam funcionando**. Grade e tabela são independentes. |
| **Deixe 1 modelo só** | vira **lista vertical**, não matriz |
| **Ligue o 3º eixo** (Material) | padrão Odoo: eixo 1 nas colunas, os demais **combinados** nas linhas (`Azul · Algodão`) |
| **Aba `Preço base`** | o preço é **da variação** — Azul-G nasce `120` e os outros `100`: a base **varia por célula** |
| **Base → `Atacado` (−20%)** | Azul-P vira `80,00` e Azul-G vira `96,00` — **uma regra, bases diferentes** |
| **Editar a base e voltar à tabela** | o preço da lista **segue sozinho** (base 200 → atacado 160) |
| **Trocar o modelo** (Tamanho/Cor/Numeração/Voltagem) | os eixos vêm do `variation_templates` — a grade inteira se refaz |
| **Desmarcar um chip** (PP, GG…) | marcar ≠ todos — o valor some da grade e o preview recalcula |
| **Clicar na célula hachurada** | reativa a combinação "não existe" — **desativa, nunca apaga** (padrão Bling) |
| Clicar `Varejo` / `Atacado` / `Revenda` | uma lista por vez — a dimensão que explode fica fechada |
| Mudar o **%** da regra | a lista inteira reprecifica com **um número** |
| Digitar numa célula | vira **exceção** (negrito + tarja + contador `•N` na aba) |
| `↺` na célula | volta ao calculado |
| Mudar a regra **com exceção viva** | **o invariante**: herdadas movem, exceção **não** |
| `Tab` / `Enter` | teclado canônico Cin7/Lightspeed (Tab = coluna, Enter = linha) |
| Célula `Vermelho-G` | hachurada = **combinação que não existe** (desmarcada na geração) |

## Onde o preço mora (o 2º corte de [F])

**Na variação, sempre.** `variations` tem `default_purchase_price` / `profit_percent` /
`default_sell_price` / `sell_price_inc_tax` **por filho** (migration 2017 +
`product_variation_row.blade.php:60-84`). E **produto simples não é outro modelo**:
`ProductUtil::createSingleProductVariation()` cria 1 variação `DUMMY` (`is_dummy=1`) e grava o
preço nela → **simples = grade de 1 célula**.

Por isso o seletor é `[Preço base] │ [Varejo] [Atacado] [Revenda]` — e os 3 casos que [F] levantou
caem **sem tela condicional**:

| Produto tem | O que ele vê |
|---|---|
| Só variação (sem tabela) | só a aba **Base** |
| Só tabela (sem variação) | grade de **1 célula** (`DUMMY`) × as tabelas |
| Os dois | grade cheia × Base + tabelas |

⚠️ **Base ≠ tabela.** Base é dado real (digitado, fonte); tabela é regra (calculada, derivada). A UI
não finge que são irmãs: divisor no seletor, sem caixa de regra na Base, e a exceção mostra o
`calc.` de onde desviou.

## De onde vêm os eixos (não inventei)

`variation_templates` (id, name, business_id) → `variation_value_templates` (name, template_id).
**Existem no schema desde 2017**, com CRUD vivo (`VariationTemplateController` + rota
`variation-templates`). O Blade legado **já encadeia os 2 selects** — modelo → valores (múltipla
escolha) — em `product_variation_row.blade.php:19-23`, servido por
`ProductController:1351`. **A tela React do cadastro não usa isso hoje**; este protótipo usa.

O "marcar só alguns valores" do legado é, por acaso, a base do **"não gerar o que não existe"** que
a pesquisa apontou como o ponto fraco do mercado inteiro.

## Verificado (browser, 2026-07-16)

Não é screenshot de intenção — o comportamento foi medido no DOM:

**Os 2 modos da tabela (o schema já suportava — `price_type ∈ {fixed, percentage}`):**
- **Atacado = regra %** (−20%) → campo de % visível · Azul-P `80,00` · Azul-G `96,00`
- **Revenda = preço por produto** → **campo de % escondido** · Azul-P `75,00` como `is-set` (neutro, sem tarja) · Azul-G `120,00` herdado da base · **sem** subline `calc.` (não há regra da qual desviar)
- **Trocar regra → manual**: o `90,00` que era `is-override` (tarja de aviso) vira `is-set` (neutro); o `calc.` some; herdadas caem pra base
- Contador `•N` muda de significado no `title`: "3 exceções à regra" vs "3 preços definidos nesta tabela"

**Faixa de quantidade (v3.8 — 3 cortes de [F]):**
- **Base TAMBÉM tem faixas** — não ter tabela não invalida ter faixa (espelho do "não ter grade não invalida ter tabela")
- **`de 1 até 9` → preço normal · `de 10 até 49` → 80,00 · `de 50 até ∞` → 55,00** — o teto é **derivado**, nunca digitado
- Mudar o piso da 2ª faixa pra `30` → o teto da 1ª virou **29** sozinho
- Guarda o piso (overlap impossível), exibe o intervalo (é como o operador pensa)

**3+ eixos (padrão Odoo — v3.8):**
- `Cor · Material \ Tamanho` — eixo 1 nas colunas, demais **combinados** nas linhas
- 5 tam × 4 cor × 2 mat = **8 linhas × 5 colunas = 40 células**, preview "Criar 40"
- tirar o 3º eixo volta pra matriz normal · núcleo intacto (chave `Azul · Algodão|PP`, atacado 80,00)

**As 4 formas da grade (eixo vazio → `DUMMY`, nunca zera a tela):**
- **0 eixos** → 1 célula `Produto (sem grade) │ Preço único` · base `100,00` · **Atacado −20% → `80,00`** ← o corte de [F]
- **1 eixo** (Tamanho) → **lista vertical** `PP..GG` × 1 coluna, 5 células, preview "Criar 5 variações"
- **1 eixo** (Cor) → idem, 4 células
- **2 eixos** → matriz `Cor \ Tamanho`, 4×5 = 20 células, preview "Criar 16" (20 − 1 desmarcada − 3 existentes)

**Base → tabela (o modelo):**
- Abre no **Base**: `Azul|P` `100,00` · `Azul|M` `100,00` · **`Azul|G` `120,00`** · sem itálico, sem caixa de regra
- **Atacado −20%** → `Azul|P` **`80,00`** e **`Azul|G` `96,00`** — **uma regra, bases diferentes**
- Editar base `Azul|P` 100 → 200 · voltar ao Atacado → **`160,00`** (seguiu sozinho)
- Exceção `Azul|G = 90,00` → `is-override` + badge `•1` + subline **`calc. 96,00`** (de onde desviou)
- Regra −20% → −35%: herdadas `65,00`/`130,00`, **exceção intacta `90,00`**, subline recalcula pra `calc. 78,00`
- Na Base **não existe exceção** (`Azul|G` volta a ser `120,00`, sem override)

**Eixos → grade:**
- Tamanho(P,M,G) × Cor(Azul,Vermelho) → 3 colunas × 2 linhas; `PP`/`GG` desmarcados **não** viram coluna
- Trocar eixo 1 pra **Numeração** → colunas `37..42`, header `Cor \ Numeração`, preview `12`, delta acompanha
- Desmarcar o chip `42` → coluna some
- Preview: 6 total − 1 desmarcada − 3 já existentes = **2 novas** (a conta fecha)
- Reativar a célula hachurada → preview sobe pra **3**

**Regra → exceção:**
- Atacado −20% → 5 células a `80,00` · exceção `Azul|G = 90,00` → `is-override` + badge `•1`
- **Regra −20% → −35%**: herdadas `80,00 → 65,00`; **exceção permaneceu `90,00`**
- Exceção do Atacado **não vaza** pro Varejo
- Zero erro no console

## O que este protótipo NÃO decide

- **A regra-mãe não tem onde morar.** `selling_price_groups` tem só `name`/`description`/
  `business_id`/`is_active` — **nenhuma coluna de regra**. É a `US-PROD-022`
  (`SellingPriceGroup.mult` hardcoded `1.00`, ADR ARQ-0001 `proposed`). Sem ela a aba cai de volta
  na digitação célula a célula. Sendo `[V0]`, carrega a **REGRA MESTRE** (dupla-confirmação +
  antes→depois + [W]).
- **O preview de geração** (§3 da tela — *"vai criar 5 de 6, 1 desmarcada"*) é o **diferencial**:
  nenhum dos 13 sistemas pesquisados mostra isso antes de criar. Aqui está como pino estático — o
  fluxo de geração em si é contrato do
  [`VariacaoPrecos.charter.md`](../../../resources/js/Pages/Produto/VariacaoPrecos.charter.md)
  (hoje **parked** em `docs/charter-variacao-precos-parked`, esperando a `.tsx`).
- **O efeito de 2ª ordem em Labels/Woo.** Célula em branco = nenhuma linha gravada = mais retorno
  `''` do `getVariationGroupPrice` — e **3 dos 5 consumidores não guardam** esse retorno
  (`LabelsController:143` · `WoocommerceUtil:341,731`). Decisão [W] pendente; ver §Pendência de
  CONTRATO do `SellingPrices.casos.md`.

## Como abrir

`file://` é bloqueado no browser da sessão — servir por HTTP:

```bash
cd prototipo-ui/prototipos/produto-preco-especial
python -m http.server 8899
# → http://localhost:8899/Produto%20-%20Preco%20Especial.html
```

## Evolução

| Data | Autor | Mudança |
|---|---|---|
| 2026-07-16 | [F+CC] | Protótipo criado. Modelo regra+exceção ancorado na pesquisa de mercado do mesmo dia (13 sistemas; Shopify B2B/Tiny-Olist/Bling/Odoo convergentes). Charter da tabela vai a **v3** citando este pino em `related_prototype`. |
| 2026-07-16 | [F+CC] | **+ §Faixas de quantidade** (fecha a 2ª frente do 5º corte). Pesquisa 2026-07-16 (11 plataformas + 9 BR, schemas primários): faixa = **linha esparsa DENTRO da tabela** (`QuantityPriceBreak.priceList: PriceList!` non-null) · **só piso** (overlap impossível por construção) · **VOLUME/bloco** · `variacao NULL` = todas → **9 linhas cobrem 180 células**. **Penhasco** (9un=90 · 10un=80): [F] decidiu **não avisar** — é a alavanca do atacado. **Único aviso: preço < custo** (`variations.default_purchase_price`), **por variação afetada**, avisa e não bloqueia — fecha o backlog "piso vs tabela" aberto na v2. |
| 2026-07-16 | [F+CC] | **+ faixa na Base · "de X até Y" · 3º eixo — 3 cortes de [F], e 2 revogam o charter.** (1) *"a faixa não aparece na aba de preço base, porquê?"* → a v3.6 dizia "nunca existe no Base": **errado**, é o espelho do corte "não ter grade não invalida ter tabela". Importei o `priceList!` do Shopify como lei — mas lá não existe preço base fora de catálogo. (2) *"falta faixa de x até y"* → guarda o **piso**, exibe o **intervalo** (teto derivado). (3) *"casos especiais com mais de duas grades"* → o anti-padrão "máx. 2 eixos" era **meu**: Shopify permite 3, Lightspeed 3. Revogado; 3+ usa o padrão Odoo (linhas combinadas), opt-in. |
| 2026-07-16 | [F+CC] | **− delta por eixo (5º corte de [F]): *"se na variação gerada eu consigo alterar o valor, pra quê a função ajuste por tamanho?"*.** Removido: ambíguo (2 caminhos pro mesmo dado) + **o botão `aplicar` nunca teve handler** (controle morto que passou 2 rodadas de verificação — eu media cálculo, não se os botões respondem) + o charter já proibia bulk-apply (Wave 3). Veio de importar o `Value Price Extra` do Odoo sem checar o modelo: lá o preço é composto, aqui a base é digitada por célula. **Preço por quantidade** (2ª frente do mesmo corte) → 🟡 reaberto no charter, pesquisa disparada. |
| 2026-07-16 | [F+CC] | **+ §2 modos — 4º corte de [F]: *"nem sempre o cliente define o valor por porcentagem, muitas vezes define o valor do produto dentro da tabela, e no protótipo vejo apenas o campo de percentual"*.** Procedente — **o schema já suportava** (`price_type ∈ {fixed, percentage}`, lido no 1º dia e ignorado) e a **pesquisa também** (linha do Bling: lista "Customizada" = valor personalizado, citada por mim no charter). Modelar só o % forçava inventar regra + marcar cada célula como "exceção". Agora modo **regra %** vs **preço por produto** (sem %, célula = O preço, neutro). Revenda nasce manual pro contraste ser visível. |
| 2026-07-16 | [F+CC] | **+ §4 formas — 3º corte de [F]: *"se não existe um modelo de grade escolhido, não vejo a opção de adicionar um valor do produto na tabela de preço"*.** O mais grave dos três: **o charter já dizia** ("Só tabela = grade de 1 célula (`DUMMY`) × as tabelas") **e o pino violava** — exigia os 2 eixos pra desenhar. Escrever a regra não implementa a regra. Agora 0 eixos → 1 célula · 1 eixo → lista · 2 eixos → matriz; sem eixo some só o preview. Bug lateral: `\` do header era escape inválido em JS. |
| 2026-07-16 | [F+CC] | **+ §Preço base — [F] cortou: *"o produto pode ter só variação, ou só tabela, ou os dois, mas a adição do preço está ligada somente ao preço por lista"*.** Procedente, e era furo de **modelo**: eu tratava a base como escalar ("R$ 100 da aba Custos") e pendurava tudo debaixo da tabela → produto com variação e sem tabela não tinha onde ser precificado. Agora `[Preço base]│[tabelas]`, base **por célula**, regra sobre a base de cada uma. **Aberto:** tensão markup-mestre × Base editando venda direto (§Backlog do charter — [W]/[F]). |
| 2026-07-16 | [F+CC] | **+ §Modelo de grade — [F] cortou: *"não encontrei opção de selecionar o modelo de grade desejado"*.** Procedente: a v1 do pino desenhava a grade **já montada** (Azul/Vermelho × P/M/G caindo do céu) e pulava o passo que vem antes. A matriz agora é **gerada dos eixos**, não hardcoded: 2 selects (`variation_templates`) + chips de valores (`variation_value_templates`, marcar ≠ todos) + célula desmarcável/reativável + preview que conta sozinho. O invariante regra-vs-exceção foi re-medido após a reescrita e sobreviveu. |
