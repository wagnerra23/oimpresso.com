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
| **Tire os 2 modelos** (`Selecione um modelo…`) | vira **1 célula** (o `DUMMY`) — e as **tabelas continuam funcionando**. Grade e tabela são independentes. |
| **Deixe 1 modelo só** | vira **lista vertical**, não matriz |
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
| 2026-07-16 | [F+CC] | **+ §4 formas — 3º corte de [F]: *"se não existe um modelo de grade escolhido, não vejo a opção de adicionar um valor do produto na tabela de preço"*.** O mais grave dos três: **o charter já dizia** ("Só tabela = grade de 1 célula (`DUMMY`) × as tabelas") **e o pino violava** — exigia os 2 eixos pra desenhar. Escrever a regra não implementa a regra. Agora 0 eixos → 1 célula · 1 eixo → lista · 2 eixos → matriz; sem eixo some só o preview. Bug lateral: `\` do header era escape inválido em JS. |
| 2026-07-16 | [F+CC] | **+ §Preço base — [F] cortou: *"o produto pode ter só variação, ou só tabela, ou os dois, mas a adição do preço está ligada somente ao preço por lista"*.** Procedente, e era furo de **modelo**: eu tratava a base como escalar ("R$ 100 da aba Custos") e pendurava tudo debaixo da tabela → produto com variação e sem tabela não tinha onde ser precificado. Agora `[Preço base]│[tabelas]`, base **por célula**, regra sobre a base de cada uma. **Aberto:** tensão markup-mestre × Base editando venda direto (§Backlog do charter — [W]/[F]). |
| 2026-07-16 | [F+CC] | **+ §Modelo de grade — [F] cortou: *"não encontrei opção de selecionar o modelo de grade desejado"*.** Procedente: a v1 do pino desenhava a grade **já montada** (Azul/Vermelho × P/M/G caindo do céu) e pulava o passo que vem antes. A matriz agora é **gerada dos eixos**, não hardcoded: 2 selects (`variation_templates`) + chips de valores (`variation_value_templates`, marcar ≠ todos) + célula desmarcável/reativável + preview que conta sozinho. O invariante regra-vs-exceção foi re-medido após a reescrita e sobreviveu. |
