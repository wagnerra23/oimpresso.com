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
related_runbook: memory/requisitos/Produto/_telas/RUNBOOK-produto-create.md
related_visual_comparison: memory/requisitos/Produto/_telas/produto-create-visual-comparison.md
tier: A
charter_version: 3
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

## 2 bugs reais no controller (independem da migração)

> Correção de rumo (2026-07-17, [F]): a v2 deste charter chamava **4 UCs de "bugs"**. **Estava errado**
> — 2 deles (`UC-PCAD-02` validação, `UC-PCAD-03` defaults) **não são bugs**, são **gaps de paridade**
> (ver grade abaixo): o UltimatePOS valida client-side no Blade e os defaults moram no form. Sobram
> **2 bugs reais**, que vivem no `store()`/`create()` e independem de qual front chama:

- **✅ `UC-PCAD-06` (500→404) — corrigido neste PR.** `ProductController@create` L539 `find()`→
  `findOrFail()`. Duplicar produto de outro business dava 500 (acesso a `->name` de `null`); o scope
  de business já estava lá, agora dá 404 limpo. Failing-first, padrão #4300.
- **🔶 `UC-PCAD-05` (cross-tenant Tier 0) — achado real, US própria.** O `store()` grava `category_id`
  de outro business (`$request->only()` sem `exists:` escopado; família do `UC-PTAB-04`/#4300).
  **Não corrigido aqui** — mexe no `store()` legado (~6.4k chamadas) e exige Pest nos caminhos antigos
  no CT 100 (fora nesta sessão). Task MCP criada. O §Automation Anti-hooks diz *"❌ Não acessa produto
  de outro `business_id`"* — verdadeiro pro **produto**, mas o **insumo** (FK) precisa do mesmo scope.

## Paridade Blade → React (o que a casca draft ainda não migrou)

> O cadastro **real em produção** é o Blade `resources/views/product/create.blade.php` + `store()`.
> O `Create.tsx` é **draft** — cópia ~70% completa. A migração fecha esta lista. Ancorado no Blade
> lido por inteiro (395 linhas) + `useForm` do React (2026-07-17). Legenda: ✅ migrado · ⚠️ parcial · ❌ falta.

| Falta no React | Blade (linha) | Crit. |
|---|---|---|
| **Formação de preço** (`single_dpp`/`_inc_tax`, `profit_percent`, `single_dsp`/`_inc_tax`, todos `required`) | `single_product_form_part` L26-47 | **P0** `[V0]` |
| **Imagem** (upload) | L157 + form-part L52 | **P0** |
| **Validação client-side** (`required` + jQuery validate em ~8 campos) | todo o form | P1 |
| **custom_field 5-20** (React só 1-4) | L272+ | P1 |
| **`product_racks`** (rack/row/position) | L240-249 | P1 |
| **`module_form_part` / `pos_module_data`** (campos de módulos) | L144, L303 | P1 |
| **`type=variable`** (grade tam×cor) · **`type=combo`** (picker) | partials | P1 (já Non-Goal "Wave 3") |
| `secondary_unit_id` · `product_brochure` · `preparation_time_in_minutes` · quick-add unidade/marca | L71/168/297/54 | P2 |

> **Nota sobre os defaults/validação:** o §Goals abaixo lista "Defaults: type='single'…" — correto
> **sobre o form** (Blade + `useForm`). O `store()` não default nem valida server-side, **por design**
> UltimatePOS (validação é client). Isso **não é bug** — é a arquitetura legada que a migração herda.
> O `CU-PROD-01.1` do SDD dizer *"validados client **+ server**"* é o único ponto impreciso (só client).

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
| 2026-07-17 | [F+CC] | **v2** — fecha o trio (US-PROD-020): + link pro `Create.casos.md`. **§Pest GUARD REVOGADO** (teste fantasma). + §Backlog com "4 vermelhos do CI" **(depois corrigido na v3)**. |
| 2026-07-17 | [F+CC] | **v3 — correção de rumo ([F] pegou meu erro).** A v2 chamou os 4 vermelhos de "bugs". Errado: analisei a **casca React `Create.tsx`** (draft) como se fosse o cadastro — o real é o **Blade** `create.blade.php` + `store()`. Reclassificado: **02/03 não são bugs** (validação client-side no Blade + defaults no form, por design UltimatePOS) → viraram **§Paridade Blade→React** (grade do que falta migrar). **05** (cross-tenant) = achado Tier 0 real → US própria. **06** (500→404) = corrigido neste PR. §Pendência do preço vira 1ª linha da grade de paridade (o Blade tem preço; a casca React não migrou). |
