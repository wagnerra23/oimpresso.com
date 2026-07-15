---
page: /products/{id}/variacoes
component: resources/js/Pages/Produto/VariacaoPrecos.tsx
page_id: produto-variacao-precos
related_prototype: n/a (rodada Claude Design pendente — ver Backlog)
owner: wagner
status: draft
last_validated: "2026-07-15"
parent_module: Produto
related_adrs: [93, 104, 107, 182]
tier: A
charter_version: 1
---

# Page Charter — Variação de preço do produto (DRAFT)

> **Charter novo (2026-07-15).** Nasce da extração do `SellingPrices.charter.md` v2 + do modelo
> declarado por Wagner nesta data. Cobre a aba **Variação** do legado (`AR-PROD-170..187`, doc
> anti-regressão separado). **Tela ainda não existe** — este charter é contrato ANTES do código,
> não descrição do que está lá. `page`/`component` são **propostos**; a rodada de design confirma.

## Mission

Definir o preço das **variações** de um produto, em um de dois modos: **preço por quantidade**
(faixa de qtd) ou **preço por cor e tamanho** (grade).

## Gate condicional (⚠️ o invariante que define esta tela)

> **Esta tela SÓ existe se o tipo do produto for "Variação"** (`AR-PROD-170`, legado `TEM_VARIACAO`;
> no oimpresso `products.type = 'variable'` — dicionário `memory/dominio/estoque.md`, onde
> `type ∈ {single, variable, modifier}`).
>
> Em produto **simples** o bloco inteiro **não renderiza** — condicional de *render*, não de
> `disabled`. Wagner 2026-07-15, textual: *"somente aparece se o tipo do produto cadastrado for
> 'Variação'. Não esqueça disso."*

## Os dois modos (`AR-PROD-171` — `VARIACAO_TIPO`, excludentes)

### Modo A — Preço por quantidade (`AR-PROD-176..186`)

Faixa de quantidade com preço próprio. Cada faixa **pode** materializar um **produto filho
vinculado** (SKU próprio que aparece na lista/busca) — atacado escalonado como SKUs.

- Grid: `De` · `Tipo` · `Quantidade` · `Porcentagem` · `Valor` · `Referência` · `Código Único` (SKU) · `Produto Vinculado` · `Ver Histórico` (`AR-PROD-176` `[V0]`)
- Faixa por tipo **`Até`** ou **`Acima de`**; contíguas, sem buraco (`AR-PROD-179`)
- **Produto Vinculado é OPCIONAL** (`AR-PROD-178` `[V0]`) — faixa com filho (SKU próprio) OU sem filho (só preço-por-qtd no próprio produto)
- `Ver Histórico` por linha (`AR-PROD-180`) · `Referência` + SKU por faixa (`AR-PROD-181`)
- Rodapé: "Preço Fixo por faixa de Quantidade" + checkbox **"Tabela de Preço pela Quantidade de Peças em vez da Quantidade"** (`AR-PROD-182`)
- Diálogo Adicionar/Alterar: **Configurar Quantidade** (`Qual o tipo de Cálculo?` = `Até`/`Acima de` · `Quantidade Inicial` · `Quantidade`) + **Configurar Valor** (`Valor Inicial` read-only · `% Desconto` · `Valor Final`) (`AR-PROD-186`)

### Modo B — Cor e Tamanho (grade) (`AR-PROD-183..185`, `187`)

- Matriz **cor × tamanho** (`AR-PROD-183`)
- **Modelo de Grade reutilizável** aplicado via botão "Modelo Grade" — tamanhos `T1..T11` + tipo de medidas (`AR-PROD-184`)
- Cada célula (cor×tamanho) = **variação-filho** com SKU + preço + estoque próprios, governado por `VARIACAO_VARIA_PRECO` / `VARIACAO_CONTROLA_ESTOQUE` (`AR-PROD-185` `[V0]`)

## Goals

- Selecionar o modo (`AR-PROD-171`) e editar as variações do modo escolhido
- Flags do pai: **"Filhos Tem Preço Individual"** (`AR-PROD-172`) · **"Filhos Tem Descrição Individual"** (`AR-PROD-173`) — mudam **de onde vem** preço/descrição na venda
- Ações: Adicionar · Alterar · Excluir · Modelo Grade (`AR-PROD-174`)
- Cabeçalho com estoque agregado do pai (`AR-PROD-175`)
- AppShellV2 + PageHeader 3 zonas ([ADR 0182](../../../../memory/decisions/0182-pageheadertabs-canon-pattern-telas.md))
- Multi-tenant scopado `business_id` ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

## Non-Goals

- ❌ **Renderizar em produto simples** — ver Gate condicional. Não é `disabled`, é ausência.
- ❌ **Cadastrar o Modelo de Grade aqui** (`AR-PROD-187`) — nasce em tela própria (menu **Produto → Modelo de Variação**, "Cadastro de Modelo de Grade": `Descrição` · `Ativo` · `Tipo da Grade` + itens ordenáveis). Mesmo padrão da tabela de preço: **o modelo nasce fora, a tela do produto só aplica.**
- ❌ **Preço do produto por tabela de preço** — é o `SellingPrices.charter.md`. Eixos ortogonais: tabela = *em qual lista de preço*; variação = *qual filho*. Cruzam-se numa matriz, mas o contrato é separado.
- ❌ **Criar/editar o cadastro do produto pai** (nome, categoria, custo) — outra tela.
- ❌ **`type = 'combo'` / kit** — **não existe** no dicionário de domínio (`type ∈ {single, variable, modifier}`). A composição do legado vira `ProductBom` + motor de fórmula, nunca `type=combo`.

## Invariantes de valor (Tier 0 — REGRA MESTRE)

> Toda regra abaixo é `[V0]`. Pela regra de precedência (`proibicoes.md`), **teste verde vence este
> charter** se discordarem.

- **`AR-PROD-177` `[V0][calc]` — porcentagem negativa é ACRÉSCIMO, não desconto.** Ex real: faixa
  1001–10000 com **−7,34%** → R$ 1,90, **acima** do R$ 1,00 da faixa anterior. Desconto positivo
  (base R$ 60,00): 16,67%→50 · 33,33%→40 · 41,67%→35 · 53,33%→28. Inverter o sinal aqui precifica
  ao contrário — e o legado usa as duas direções na mesma coluna.
- **`AR-PROD-178` `[V0]` — mexer no preço de uma faixa mexe num SKU vendável** quando há filho
  vinculado. Não é edição local: dupla-confirmação (REGRA MESTRE) antes de gravar.
- **`AR-PROD-172`/`173` — as flags mudam a origem do preço/descrição na venda.** Alterar qualquer
  uma reprecifica o que o vendedor vê.
- **`AR-PROD-175` `[V0]` — estoque negativo agregado NÃO é regra da variação.** É efeito de
  "Controla Estoque" desligado no produto (Wagner 2026-07-13, ex −7.155,3576). Não "corrigir"
  o negativo aqui nem bloquear a venda por causa dele.
- **Precisão herdada** — o `Valor Inicial` das faixas vem do valor de venda do produto, que a aba
  Custos produz. Markup mestre gravado a 2 casas faz 7.000,00 virar 6.999,97 (medido no motor
  real); o desconto de faixa cascateia sobre a base. Mesma decisão da aba Custos vale aqui:
  **grave 4 casas, exiba 2** (`variations.profit_percent` é `DECIMAL(22,4)`).

## UX Targets

- p95 < 800ms
- 1280px responsivo (grade cor×tamanho pode ter scroll horizontal)
- Tabular-nums em valores e quantidades
- Dirty-state visível + Cmd/Ctrl+S com toast
- Faixa contígua validada na UI (`AR-PROD-179`) — buraco entre faixas é erro de campo, não 500

## Anti-patterns

- ❌ `auth()->user()->business_id` (canon UPOS é session)
- ❌ Cor crua (tokens v4: `bg-background`/`bg-card`/`text-foreground`/`border-border`/`destructive`)
- ❌ Tratar `Porcentagem` como sempre-desconto (ver `AR-PROD-177`)
- ❌ Assumir que toda faixa tem filho vinculado (`AR-PROD-178` — é opcional)
- ❌ Renderizar `disabled` em produto simples em vez de não renderizar

## Pest GUARD (a escrever junto com a tela — nenhum existe hoje)

```php
it('Page não renderiza para produto type=single (gate AR-PROD-170)')
it('Porcentagem negativa vira ACRÉSCIMO sobre a base (AR-PROD-177)')
it('Faixa sem produto vinculado é válida (AR-PROD-178)')
it('Controller cross-tenant retorna 404')
```

## Backlog de contrato (buracos conhecidos)

- **A tela não existe.** `page`/`component` deste charter são **propostos** — a rodada de design
  confirma a rota. Se mudar, é `charter_version: 2`, não sobrescrita.
- **Sem US no SPEC.** `memory/requisitos/Produto/SPEC.md` tem `US-PROD-020..026`; nenhuma cobre a
  Variação. Precisa nascer antes de virar código (`related_us` vazio de propósito).
- **Sem `casos.md`** — obrigatório quando a `.tsx` nascer (G-1 trio, ADR 0264). Tela nova sem trio
  = violação NOVA no `casos-gate` (as 268 legadas estão perdoadas no baseline; esta não estaria).
- **`AR-PROD-182` colide com `AR-PROD-108`** — o checkbox "Tabela de Preço pela Quantidade de Peças"
  aparece **aqui** e na aba Custos (`§J`, faixa de quantidade). Duas telas mexendo no mesmo conceito:
  resolver de quem é o dono antes de implementar as duas.
- **Multi-nível** (kit dentro de kit) — suportado pelo schema legado (`ORDEM_ARVORE`), confirmar em
  uso real quando migrar. Fora deste charter (é Composição/BOM).

## Refs

- Anti-regressão: [`ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md`](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md) (`AR-PROD-170..187`)
- Paridade: [`PARIDADE-charter-vs-legado.md`](../../../../memory/requisitos/Produto/PARIDADE-charter-vs-legado.md) §4 (colisão Non-Goal × legado)
- Charter irmão (tabela de preço): [`SellingPrices.charter.md`](SellingPrices.charter.md)
- Teste de valor da base de preço: `tests/Feature/Produto/FormacaoPrecoParidadeLegadoTest.php`
- Dicionário de domínio: `memory/dominio/estoque.md` (`products.type ∈ {single, variable, modifier}`)
- ADR 0182 (header) · ADR 0093 (multi-tenant) · ADR 0264 (trio)

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-07-15 | [CC] | Charter criado. Extraído do `SellingPrices.charter.md` v2 (que conflatava variação + tabela) + modelo declarado por Wagner 2026-07-15: variação cobre preço-por-quantidade OU cor/tamanho, e **só existe se o tipo do produto for "Variação"**. Ancorado em `AR-PROD-170..187`. Tela ainda não existe — contrato antes do código. |
