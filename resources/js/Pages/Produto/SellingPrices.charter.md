---
page: /products/add-selling-prices/{id}
component: resources/js/Pages/Produto/SellingPrices.tsx
page_id: produto-tabela-preco
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-15"
parent_module: Produto
related_adrs: [93, 104, 107, 149, 182]
related_us: [US-PROD-022, US-PROD-023]
tier: A
charter_version: 2
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/produtos-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [SellingPrices]
  divergence_from_blueprint: "matriz variation × price_group é tabela densa específica — não é list cockpit padrão; mantém AppShellV2 + tokens + header pattern; diverge no conteúdo central. ADR 0149 §'Casos que NÃO se qualificam — bulk-edit datatable'"
---

# Page Charter — Tabela de Preço do produto (DRAFT)

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

## Goals

- Selecionar tabela(s) de preço existentes e definir o preço do produto em cada uma
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
- ❌ **Faixa de quantidade** (`De`/`Até`/`% Desconto`/`R$ Valor` — `AR-PROD-105..109`) — é *preço por
  quantidade*, que pertence ao charter da Variação, não a este
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

## Pest GUARD

```php
it('Page Inertia existe em Pages/Produto/SellingPrices.tsx')
it('Page declara matriz variations × priceGroups')
it('Controller cross-tenant retorna 404')   // ⚠️ PROMETIDO, NÃO EXISTE — ver Backlog
```

Teste de valor que defende os invariantes acima (ancorado em `AR-PROD-093/094/095/006`):
`tests/Feature/Produto/FormacaoPrecoParidadeLegadoTest.php`.

## Backlog de contrato (dívida conhecida — não é Non-Goal, é buraco)

- **`casos.md` não existe** — a tela está nas 268 violações `trio:missing-casos` do baseline.
- **Os testes atuais são tautológicos** — `Wave2SellingPricesInertiaTest` / `Wave2SellingPricesBaselineTest`
  só fazem grep de string no fonte ("contém `variations.map`"). Nenhum exercita comportamento.
- **O `it('Controller cross-tenant retorna 404')` do Pest GUARD acima NÃO existe** — o que existe é um
  grep procurando `session()->get('user.business_id')` no fonte do controller. Buraco Tier 0.
- **Multiplicador da tabela (`SellingPriceGroup.mult`) é oco** — hardcoded `1.00`. US-PROD-022 /
  ADR ARQ-0001 (proposed). O `Unificado/Index.charter.md` registra a mesma pendência.
- **Piso `AR-PROD-101` vs preço de tabela** — sem contrato: a tabela pode furar o valor mínimo?

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
