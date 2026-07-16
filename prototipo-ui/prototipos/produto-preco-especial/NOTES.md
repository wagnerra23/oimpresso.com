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
| Clicar `Varejo` / `Atacado` / `Revenda` | uma lista por vez — a dimensão que explode fica fechada |
| Mudar o **%** da regra | a lista inteira reprecifica com **um número** |
| Digitar numa célula | vira **exceção** (negrito + tarja + contador `•N` na aba) |
| `↺` na célula | volta ao calculado |
| Mudar a regra **com exceção viva** | **o invariante**: herdadas movem, exceção **não** |
| `Tab` / `Enter` | teclado canônico Cin7/Lightspeed (Tab = coluna, Enter = linha) |
| Célula `Vermelho-G` | hachurada = **combinação que não existe** (desmarcada na geração) |

## Verificado (browser, 2026-07-16)

Não é screenshot de intenção — o comportamento foi medido no DOM:

- Varejo 0% → 5 células a `100,00`; Atacado −20% → 5 células a `80,00`
- Exceção `Azul-G = 90,00` → `is-override` + badge `•1` no seletor
- **Regra −20% → −35%**: herdadas `80,00 → 65,00`; **exceção permaneceu `90,00`**
- Exceção do Atacado **não vaza** pro Varejo (troca de lista → `100,00` limpo)
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
