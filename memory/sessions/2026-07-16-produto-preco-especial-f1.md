---
date: "2026-07-16"
hour: "19:30"
topic: Produto · aba "Preço especial" — F1 do protótipo + charter v2→v3.8
authors: [F, CC]
outcomes:
  - "F1 navegável da aba Preço especial (verificado no browser, DOM medido)"
  - "Charter SellingPrices v2 → v3.8 — 8 cortes de [F], todos procedentes; 2 revogaram o que eu tinha escrito"
  - "2 pesquisas de mercado (24 plataformas, schemas primários) — achado: Bling/Tiny/ContaAzul/Microvix têm ZERO faixa de quantidade"
  - "proibicoes.md §5 +2 entradas — as 2 famílias de erro que REPETIRAM na sessão"
  - "Correções de canon: AR-PROD-095 cita a coluna errada · ADR ARQ-0001 tem premissa falsa · charter da Variação stale"
prs: []
us: [US-PROD-022, US-PROD-023, US-PROD-027]
related_adrs: [0104-processo-mwart-canonico-unico-caminho, 0107-emendation-0104-visual-comparison-gate-f3]
---

# Aba "Preço especial" do cadastro de produto — F1 + charter v3.8

**Quem dirigiu:** [F]. **O que eu fiz:** protótipo + charter. **Quem achou os erros:** [F], 8 vezes.

## O pedido

[F] está construindo a tela de produto em partes (a aba **Custos** já saiu). A próxima é a
**Preço especial** — *"onde entra a tabela de preço e a variação de preço por grade cor/tamanho"*.
Critério declarado: **"a melhor usabilidade ganha; o legado Delphi não entra aqui"**.

## O que saiu

- **Protótipo F1 navegável** — [`prototipo-ui/cowork/produto-preco-especial/`](../../prototipo-ui/cowork/produto-preco-especial/)
  (verificado no browser a cada rodada; DOM medido, não screenshot de intenção)
- **Charter v2 → v3.8** — [`SellingPrices.charter.md`](../../resources/js/Pages/Produto/SellingPrices.charter.md)
- **2 pesquisas de mercado** (24 plataformas somadas, schemas primários — GraphQL/OpenAPI/source)
- **11 commits** em `claude/tabela-preco-regra-excecao` — **sem push**

## O modelo que sobreviveu

| Camada | Contrato |
|---|---|
| **Modelo de grade** | 1-3 eixos do `variation_templates` (existe desde 2017, CRUD vivo, a tela React não usava). 0 eixos → 1 célula (`DUMMY`) · 1 → lista · 2 → matriz · 3+ → linhas combinadas (padrão Odoo) |
| **Preço base** | mora na **variação** (`default_sell_price`). Produto simples = grade de 1 célula |
| **Tabela** | 2 modos: **regra %** (célula digitada = exceção) ou **preço por produto** (célula = o preço). O schema já suportava: `price_type ∈ {fixed, percentage}` |
| **Faixa de quantidade** | linha esparsa **em qualquer contexto de preço** (inclusive a Base). Guarda o **piso**, exibe **de X até Y**. VOLUME/bloco. Substitui, não mescla |
| **Único aviso** | preço **abaixo do custo**, por variação afetada. O penhasco do volume **não** se avisa |

## Achado comercial

**Bling, Tiny/Olist, Conta Azul e Linx Microvix: zero faixa de quantidade.** Omie tem meia-solução
enxertada em "característica do produto", só no PDV. Faixa real só em TOTVS/Sankhya (ERP de grande
porte). **Espaço aberto** no tier do oimpresso.

## Os 8 cortes de [F] — todos procedentes

| # | O corte | O que era, de verdade |
|---|---|---|
| 1 | *"não deveria mexer no estoque, é outra competência"* | acoplei `setStock` a um contrato de **preço** — e o estoque **nem era a causa** do vermelho |
| 2 | *"não encontrei opção de selecionar o modelo de grade"* | eu tinha levantado o `variation_templates` **na mesma conversa** e não usei |
| 3 | *"a adição do preço está ligada somente à tabela"* | furo de **modelo**: base é por variação, não escalar. Produto só-com-variação não tinha onde ser precificado |
| 4 | *"não ter grade não invalida ter tabela"* | **o charter já dizia isso e o protótipo violava** |
| 5 | *"nem sempre define por porcentagem"* | o **schema já suportava** (`price_type='fixed'`) e a **pesquisa já dizia** (Bling "Customizada") — li os dois e ignorei |
| 6 | *"pra quê a função ajuste por tamanho?"* | ambíguo + **o botão nunca teve handler** + o charter já proibia bulk-apply |
| 7 | *"não estou vendo a grade por quantidade"* | estava em `top:1046` num quadro de 860. E revelou **2 bugs de `hidden`** que eu tinha "verificado" |
| 8 | *"mais de duas grades"* + *"faixa de x até y"* + *"faixa na base"* | **2 dos 3 revogaram o que EU tinha escrito no charter** |

## O padrão dos meus erros (o que foi pro §5)

Duas famílias, e as duas **repetiram**:

1. **Importar solução de outro sistema sem checar se o problema existe aqui** — 3× na mesma sessão
   (delta do Odoo · `priceList!` do Shopify · "máx 2 eixos" do Akeneo). A pesquisa estava certa; a
   **tradução** estava errada. E 2 dessas viraram **lei no charter** — erro que a próxima sessão
   obedeceria.
2. **Medir a propriedade errada e chamar de verificado** — `.hidden` (atributo) em vez de `display`
   computado → 2 bugs vivos passaram por 2 rodadas; `offsetTop` (relativo ao offsetParent) → quase
   concluí que o colapso não economizava nada.

Ambas em [`proibicoes.md` §5](../proibicoes.md) (2026-07-16).

> **O denominador:** em quase todos, **eu já tinha a informação e não liguei os pontos**. Não foi
> falta de dado — foi não usar o que já estava na mesa. E o que me salvou nunca foi o CI: foi [F]
> olhando a tela.

## Correções de canon (achados que valem além da tela)

- **`AR-PROD-095` "markup é mestre" está ERRADO** — junta 2 colunas. `MARGEM` = `((V/C)−1)×100` ✅
  (5 caminhos, base real 3.668 linhas) · `CALC_PMARKUP` = **fórmula desconhecida**, 0/3.668. O certo:
  **Custo é âncora**, Valor↔Margem **bidirecional** — e o oimpresso **já faz** (`product.js:313`/`:338`).
- **`AR-PROD-097`** = `TEM_MARGEM_FIXA_CONTIBUICAO`, **flag por produto** que o oimpresso **não tem**
  (só o modo `N`). Base real: 83,8% `N` · **8,2% `S`** → migrar como está **quebra silencioso pros 8,2%**.
- **🕳️ Furo no aviso de custo:** `preço < custo` **nunca dispara com custo 0** — e custo 0 é **10,4%**
  da base real, com **53,4% deles já a preço zero**. Sem guarda em nenhum dos 2 sistemas.
- **A ADR ARQ-0001** (multiplicador) tem **premissa falsa**: diz que "UPOS é explícito, Cowork sugere
  multiplicador — modelos diferentes". **Não são** — `price_type='percentage'` **já é** multiplicador
  desde 2023, um ano antes da ADR, e ela nunca o cita. Precisa de emenda.
- **A aba Custos NÃO EXISTE em git** — 0 `.tsx`, 0 branch. Está só na máquina de [F] ou no papel.
- **`AR-PROD-101`/MSP** existe **só no browser** (zero enforcement no servidor) e o "piso" é o
  próprio preço de venda. **Não** é a mesma coisa que custo.

## Fica aberto

| # | O quê | Quem decide |
|---|---|---|
| 1 | **Markup na aba Base** — (a) bidirecional (= paridade com prod) ou (b) read-only (depende da aba Custos, que não existe em git) | [F]/[W] · `[V0]` |
| 2 | **Custo zero** — avisar "produto sem custo"? só quando preço também é 0? aceitar? | [F]/[W] · `[V0]` |
| 3 | **`AR-PROD-101`** — piso que **bloqueia**, ≠ do aviso que **avisa** | [W] |
| 4 | **`US-PROD-022`** — a regra da tabela não tem coluna. **Pré-requisito** da aba | [W] · Tier 0 |
| 5 | **PR [#4321](https://github.com/wagnerra23/oimpresso.com/pull/4321)** — 79 checks verdes, `MERGEABLE`, aberto desde 15/jul. É a evidência que derrubou o "markup é mestre" | [W] merge |
| 6 | **Colisão de nome** — o charter declara *"Preço Especial" (`AR-PROD-111..116`) Non-Goal*, e a aba se chama "Preço especial". Coisas diferentes, mesmo nome | [F]/[W] |

## Refs

- Charter: [`SellingPrices.charter.md`](../../resources/js/Pages/Produto/SellingPrices.charter.md) v3.8
- Protótipo: [`produto-preco-especial/`](../../prototipo-ui/cowork/produto-preco-especial/) + `NOTES.md`
- Lições: [`proibicoes.md` §5](../proibicoes.md) — 2 entradas 2026-07-16
- Evidência custo/margem: PR [#4321](https://github.com/wagnerra23/oimpresso.com/pull/4321) (`docs/produto-custo-margem-evidencia`)
- `US-PROD-027` / `UC-PTAB-05`: PR [#4328](https://github.com/wagnerra23/oimpresso.com/pull/4328) **fechado** — a US não é executável como escrita (aceite aponta pro PDV, fora do cadastro; o caso que cabe no cadastro ela exclui)
