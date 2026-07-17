---
page: /products/create
component: resources/js/Pages/Produto/Create.tsx
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-17"
parent_module: Produto
related_adrs: [104, 149, 93, 107, 101, 264]
related_us: [US-PROD-020, US-PROD-023]
tier: A
charter_version: 2
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/produtos-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente — Wave 2 B4 Produto 2026-05-15)"
  derived_screens: [Create]
  divergence_from_blueprint: "form full-width AppShellV2 (não Cockpit 3-col) — Create é form não-list; preserva tokens + header pattern + design system; não usa drawer pattern"
---

# Page Charter — /products/create (DRAFT)

> **v2 (2026-07-17).** A v1 nasceu em Wave 2 B4 (2026-05-15, Agent W2-C paralelo) e **nunca foi
> confrontada com a execução**. Esta v2 não redesenha nada — ela **corrige o que o CI provou falso**
> (US-PROD-020, o trio do Create). Três coisas que a v1 afirmava e não eram verdade estão marcadas
> `⚠️ REVOGADO` abaixo. Deriva de Index/produto-cockpit blueprint via ADR 0149.
>
> **Contrato executável da tela:** [`Create.casos.md`](Create.casos.md) — `UC-PCAD-01..06`
> (`tests/Feature/Produto/CadastroProdutoContratoTest.php`, lane `Estoque · MySQL`).
>
> **Precedência** (`proibicoes.md` §REGRA DE PRECEDÊNCIA): *teste verde citando o UC > `casos.md` >
> este charter > SPEC*. **Onde este charter discordar do teste, o teste ganha** — é o que esta v2 faz.

## Mission

Cadastrar produto novo (single/variable/combo) com validação cliente-side TypeScript + server-side Laravel. Preserva pipeline UPOS legacy (geração SKU server-side, sync product_locations, Media upload). 8 campos sempre visíveis + ~22 colapsáveis em `<details>` "Avançado".

## Goals — Features (faz)

- AppShellV2 + PageHeader "Novo produto" + ações "Cancelar"+"Salvar"
- 5 seções Card: Identificação · Preço & Imposto · Estoque · Localizações · Avançado
- Campos sempre visíveis (8): name · sku · type · unit · category · brand · tax · alert_quantity
- Campos avançados colapsáveis: barcode_type · sub_category · sub_units · weight · product_description · enable_sr_no · expiry (se enabled) · racks (se enabled) · custom_fields 1-20
- Defaults: type='single' · enable_stock=true · tax_type='exclusive'
- Suporte duplicate via `?d=N` (preenche form com produto+`(copy)`)
- TypeScript estrito sem `any`
- Multi-tenant: dropdowns via business_id scope
- Cores semânticas tokens OKLCH Cowork

## Non-Goals (NÃO faz)

> ⚠️ Anti-alucinação. Wagner aprova.

- ❌ Variation builder dinâmico inline (variable type — Wave 3)
- ❌ Combo composition picker inline (combo type — Wave 3)
- ❌ Multi-image gallery upload (apenas 1 image — paridade legacy)
- ❌ NÃO gera SKU client-side (server-side em `store()`)
- ❌ NÃO modifica método `store()` PHP (out of scope)
- ❌ NÃO valida SKU duplicate cliente-side (server confirma)

## UX Targets

- p95 first-paint < 800ms
- 0 erros JS console
- Cabe em 1280px sem scroll horizontal (Larissa)
- TypeScript build verde
- Submit retorna `/products` lista (paridade legacy)

## UX Anti-patterns

- ❌ `sessionStorage` (usar `localStorage` com prefixo `oimpresso.produto.`)
- ❌ Cor crua `bg-blue-500`
- ❌ `auth()->user()->business_id` (canon UPOS: `session('user.business_id')`)

## Automation Hooks

- POST `/products` (store legacy intacto)
- GET `/products/create?d=N` duplicate
- Multi-tenant: global scope `business_id`

## Automation Anti-hooks

- ❌ Não dispara emails
- ❌ Não dispara jobs
- ❌ Não escreve no banco em GET
- ❌ Não chama Brain B
- ❌ Não acessa produto de outro `business_id`

## Pest GUARD (F4)

> ⚠️ **REVOGADO na v2 — a lista da v1 era metade promessa.** Os 5 primeiros existem
> (`Wave2CreateInertiaTest`) mas são `file_get_contents` + `toContain` no fonte do `.tsx`: provam que
> uma **string** está no arquivo, não que a tela se comporta. Renomear a variável `dup` deixa vermelho
> sem mudar comportamento. O 6º — `it('Controller isola business_id em dropdowns')` — **nunca existiu**
> (verificado 2026-07-17: `grep -rl "isola business_id em dropdowns" tests/` → vazio). Mesma doença que
> o `SellingPrices.charter.md` carregava com o `it('Controller cross-tenant retorna 404')`, e que o
> [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300) derrubou.

O contrato de comportamento mora no [`Create.casos.md`](Create.casos.md) — `UC-PCAD-01..06`, defendidos
por `tests/Feature/Produto/CadastroProdutoContratoTest.php` (lane `Estoque · MySQL`, biz=1 + biz=2).
Os `Wave2Create*Test` ficam como higiene estrutural até alguém decidir se apagam ou viram teste de
verdade — **nenhum UC os cita de propósito**.

## Backlog de contrato (o CI provou que a v1 mentia — decisão pendente)

> Run `29587401307` / `29588143635` (lane `Estoque · MySQL`, MySQL real, biz=1+biz=2). **Não corrigidos
> aqui** porque os três exigem tocar o `store()`, que é Non-Goal declarado desta tela — e o
> `CU-PROD-01` é `[must]` do registro-mãe do ERP. Ver §Pendência de CONTRATO do `casos.md`.

- **⚠️ `store()` não valida nada** (`UC-PCAD-02` 🔴) — só `$request->only($form_fields)`, zero
  `$request->validate()`. Produto nasce **sem unidade** e entra no catálogo. O `CU-PROD-01` item 1
  promete *"validados client **+ server**"*; o server nunca validou. **A tela é a única validação
  que existe** — e isso importa agora que o cadastro está virando abas.
- **⚠️ Os defaults só existem no React** (`UC-PCAD-03` 🔴) — POST sem `type` → produto nasce
  `type = null`, não `'single'`. O §Goals abaixo diz "Defaults: type='single'…" e está certo **sobre o
  form**; o servidor não tem default nenhum. Aba nova que esqueça o campo grava lixo, calada.
- **⚠️ Tier 0 — insumo de outro business vincula** (`UC-PCAD-05` 🔴) — `category_id` de outro
  `business_id` **grava** no meu produto: *"Produto do meu business ficou vinculado a categoria de
  OUTRO business"*. O `category_id` vem cru do `$request->only()`, sem `exists:` escopado. É a **mesma
  família** do `UC-PTAB-04` ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)), onde o
  `price_group_id` vinha cru da chave do array e gravou cross-tenant. O §Automation Anti-hooks abaixo
  diz *"❌ Não acessa produto de outro `business_id`"* — verdadeiro pro **produto**, falso pro **insumo**.
- **⚠️ Duplicar produto alheio dá 500, não 404** (`UC-PCAD-06` 🔴) — `CU-PROD-07` item 2 crava 404.
  Não vaza dado (crasha antes), mas é exceção não-tratada. Aqui o 404 é o **contrato** falando, não
  proxy inventado por charter.

## Pendência de CONTRATO — preço não é Non-Goal nem Goal (decisão `[F]`)

> A tela tem `<CardTitle>Preço & Imposto</CardTitle>` e **nenhum campo de preço**. Recibo datado
> (lei [#4411](https://github.com/wagnerra23/oimpresso.com/pull/4411) — doc não restateia fato derivado):
> em **2026-07-17**, contra `origin/main` `25b448019`, medindo o **fonte**:
> `git grep -cE 'single_dpp|single_dsp|profit_percent' -- resources/js/Pages/Produto/Create.tsx` → **0**.
>
> O `store()` lê os três do request; nenhum sai do React. O prop `defaultProfitPercent` é recebido e
> nunca usado. **Os 6 Non-Goals abaixo não incluem preço** — logo isto é buraco não-declarado, não
> decisão. As duas saídas (`[F]`): **(a)** Non-Goal declarado — preço mora na aba Custos → entra na
> lista abaixo; **(b)** US sob REGRA MESTRE (`[V0]`). Detalhe no §Pendência de CONTRATO do `casos.md`.

## Refs

- Contrato executável: [`Create.casos.md`](Create.casos.md) · irmão que fechou o trio primeiro: [`SellingPrices.casos.md`](SellingPrices.casos.md)
- SDD (âncora dos CU): [`SDD-tela-cadastro-produto-v1.0.md`](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) §6.1 — `CU-PROD-01` · `CU-PROD-07`
- SPEC (a US que pediu este trio): [`SPEC.md`](../../../../memory/requisitos/Produto/SPEC.md) — US-PROD-020
- Blueprint: `produto-cockpit/produto-cockpit-page.jsx`
- RUNBOOK: [`memory/requisitos/Produto/_telas/RUNBOOK-produto-create.md`](../../../../memory/requisitos/Produto/_telas/RUNBOOK-produto-create.md)
- Visual comparison: [`memory/requisitos/Produto/_telas/produto-create-visual-comparison.md`](../../../../memory/requisitos/Produto/_telas/produto-create-visual-comparison.md)
  <br>_(a v1 apontava os dois pra `memory/requisitos/Inventory/…` — a pasta não tem mais esses arquivos; link-rot corrigido nesta v2, mesmo caso do `SellingPrices.charter.md` v2.)_
- ADR 0149 screen-pattern reuse · ADR 0264 (casos-gate G-1/G-2/G-5/G-6/G-7) · ADR 0101 (biz=1, nunca biz=4)

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Charter criado em Wave 2 B4 Produto (Agent paralelo W2-C). |
| 2026-07-17 | [F+CC] | **v2** — fecha o trio (US-PROD-020): + link pro [`Create.casos.md`](Create.casos.md) (`UC-PCAD-01..06`). **§Pest GUARD REVOGADO** — o `it('Controller isola business_id em dropdowns')` nunca existiu e os outros 5 são grep de string. + §Backlog de contrato com os **4 vermelhos do CI** (store() sem validate · defaults só no React · **Tier 0 insumo cross-tenant grava** · duplicar alheio = 500). + §Pendência do preço (card "Preço & Imposto" sem campo de preço — recibo datado). Link-rot `Inventory/` → `Produto/_telas/` corrigido. |
