---
id: requisitos-produto-spec
slug: produto
title: "Especificação funcional — Produto (cadastro core / catálogo do ERP)"
type: spec
module: Produto
status: ativo
owner: wagner
version: "1.0.0"
last_updated: "2026-10-06"
---

# Especificação funcional — Produto (cadastro core / catálogo do ERP)

> **Convenção do ID:** `US-PROD-NNN` para user stories.
> **Origem:** Passo 2 da onda standalone do programa de ondas ([template](../_Governanca/programa-ondas/template-onda-modulo.md), fila Produto→Cliente). Gap **G-04** do [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) — o core-dos-cores era o único módulo do programa **sem SPEC**. Nota de capacidade **61/100** ([CAPTERRA-FICHA.md](CAPTERRA-FICHA.md)).
> **Natureza do módulo:** Produto é **core UltimatePOS**, NÃO módulo nWidart (não há pasta própria em `Modules/` com esse nome). Modelo `App\Product`; backend `app/Http/Controllers/ProductController.php` (~2700 LOC) + `ProdutoUnificadoController.php` + `Inventory/ProductBomController.php`; telas em `resources/js/Pages/Produto/`.
> **Estado do React** (medido 2026-09-21; o estado vivo é o `status:` de cada charter, não esta linha — ver [BRIEFING](BRIEFING.md) §"Backlog vivo"): as **8 telas Inertia existem**, **1 `live`** (`Index`) e **7 `draft`**. O Blade legacy coexiste como fallback (branch dual `X-Inertia`, [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)). **O React do Produto ainda precisa ser finalizado** (Wagner 2026-07-03) — hoje partido em duas US pelo writer que a tela toca: **US-PROD-023** (as 6 não-cadastrais) e **US-PROD-029** (`Create`/`Edit`, que gravam no writer compartilhado).
>
> ⚠️ **As telas React são NÃO-LINKADAS, não inalcançáveis** (medido 2026-09-21): nenhuma entrada de navegação aponta pra elas, mas `/products/unificado` faz `Inertia::render` **incondicional** — quem digita a URL entra, e de lá `router.visit` alcança `Create`/`Edit` **com** o header `X-Inertia`. O gate de permissão existe dentro do `ProdutoUnificadoController:139` (`abort(403)`, `product.view` **OU** `product.create`, `UC-PUNI-06` + teste). [W] decidiu em 2026-09-21 que a Consulta **entra na navegação** — cabeamento no aceite da US-PROD-023.
> **Estimates:** recalibradas fator 10x IA-pair + margem 2x ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)); relógio humano mantido em smoke/canary.

## 1. Glossário

- **ROTA LIVRE** — `business_id=4`, Larissa, vestuário Termas do Gravatal/SC, 99% do volume. Cadastra produto (preço/estoque/variação tam×cor), monitor 1280×1024.
- **biz=1** — WR2 SC, Wagner — única empresa segura pra smoke ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).
- **Variação** — combinação tam×cor (grade) de um produto variável; cada uma tem SKU + preços por grupo + estoque por localização.
- **SellingPriceGroup** — tabela/lista de preço (varejo/atacado/etc). `mult` = multiplicador/markup por tabela (hoje oco — ver US-PROD-022).
- **Kardex** — histórico cronológico de movimento de estoque (entrada/saída/ajuste) por variação × localização, append-only.
- **BOM** (Bill of Materials) — estrutura de componentes de um produto composto (`App\Domain\Inventory\Models\ProductBom`).
- **`/unificado`** — cockpit denso `/products/unificado` (5 sub-views: produtos/insumos/BOM/tabelas de preço/histórico).
- **Tier 0 valor/estoque** — toda mudança em preço/custo/margem/estoque exige dupla-confirmação + antes→depois + aprovação humana ([proibicoes](../../proibicoes.md) "REGRA MESTRE").

## 2. Capacidades já em produção (contexto — não são backlog)

> Documentação do que **já existe** (✅ na [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) §3, verificado por Grep@aef311d). Descrito como prose de contexto — não como US com âncora de done-ness, porque não têm teste Pest per-capacidade a citar hoje (a cobertura de teste vem via casos.md — US-PROD-020).

- **CRUD produto (simples/variável/combo) + duplicar** — `ProductController@store/update` (`product_types` l.610-722), `Route::resource('products')`.
- **Variação tam×cor + SKU auto + validação de SKU duplicado (batch)** — `getProductVariationRow`/`checkProductSku`/`validateVaritionSkus` (rotas l.413-417).
- **Preço por tabela (SellingPriceGroup) por variação** — matriz grupo×variação em `addSellingPrices`/`saveSellingPrices` + `SellingPrices.tsx`. **Limite:** multiplicador oco → US-PROD-022.
- **Estoque inicial (opening stock) por localização + alerta baixo + validade/lote** — `OpeningStockController` + `enable_stock`/`alert_quantity`/`expiry_period` (l.643-665).
- **Importação (Excel) + import de estoque + edição/ops em massa** — `Import*Controller` + `bulkEdit`/`bulkUpdate`/`massDeactivate`/`massDestroy`/`downloadExcel` + `BulkEdit.tsx`.
- **Atributos/PIM básico** — categorias/subcategorias, marcas, unidades+sub, 20 custom fields, mídia por variação, racks.
- **Multi-tenant Tier 0** — `App\Product` global scope + `ProductBom` `ScopeByBusiness` + `firstOrFail` cross-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)). **Diferencial.**
- **Sync WooCommerce (toggle por produto)** — `toggleWooCommerceSync` (l.2682). Limite: sem multi-canal.
- **Código de barras + etiquetas ZPL/PDF** — `barcode_types` + `LabelsController` (Etiquetas screen-grade 74).
- **Catálogo público + QR** — `Modules/ProductCatalogue` + `CatalogueQrService` (domínio **separado**; diferencial vertical).
- **BOM — CRUD API multi-tenant** — `Inventory/ProductBomController.php` (API pronta; UI drag-drop pendente → US-PROD-025).

## 3. User stories (backlog ativo)

> Batch aprovado por Wagner 2026-07-03 ("ok pode fazer"). `origin: onda-produto-passo2-2026-07-03` · `parent_audit: CAPTERRA-INVENTARIO Produto`.
> Refinamentos Wagner: US-PROD-024 (custo médio) começa por SPIKE — "muita coisa já pronta"; US-PROD-023 = finalizar + promover o React (draft→live).

### US-PROD-020 · [G-04] Governança do Produto: casos.md + revisar SPEC

> owner: wagner · priority: p0 · status: todo · type: epic · estimate: 6h · origin: onda-produto-passo2-2026-07-03

**Implementado em:** _pendente_ — US aberta, aceite não fechado (falta a revisão [W] da §2 + o fechamento formal). Parte do item 1 **já existe por fora desta US**: as 7 telas do módulo têm `casos.md` com UC-IDs e os testes de contrato rodam na lane `Estoque · MySQL`, entregues pelas corridas do agent `sdd-from-source` ([ADR 0351](../../decisions/0351-sdd-from-source.md), jul/2026) — fora do escopo desta US.

**Por quê.** Este SPEC (G-04) fundou o contrato; falta a rede de casos que o defende. Sem `casos.md`, teste de valor vira tautológico (proibicoes §5) e o `casos-gate` não tem âncora. Pré-req de US-PROD-022/024.

**Aceite:**
- [ ] `casos.md` das telas críticas (Create, SellingPrices, StockHistory) com UC-IDs (contrato de não-regressão).
- [ ] Wagner revisa a seção §2 (capacidades já em prod) — confirma ou abre correção.
- [ ] Ligar os UCs ao `casos-gate` (ADR 0264).

### US-PROD-021 · [G-01] Kardex real na tela React StockHistory (deixar de linkar Blade)

> owner: wagner · priority: p0 · status: todo · type: story · estimate: 10h · origin: onda-produto-passo2-2026-07-03 · blocked_by: US-PROD-020

**Implementado em:** _pendente_ — US aberta: faltam a cor semântica em `StockHistory.tsx`, os hero KPIs 30d e o smoke biz=1 (charter ainda `draft`, screen-grade 47). O item 1 do aceite **já landou por fora desta US**: `movements` vem por `Inertia::defer` (`ProductController@productStockHistory`) desde o PR #4658 (2026-07-21), ancorado em `CU-PROD-11` e coberto por `StockHistoryContratoTest` na lane `Estoque · MySQL`.

**Por quê (redigido em 2026-07-03; o 1º parágrafo foi superado pelo PR #4658).** ~~Hoje a prop `movements` fica `undefined` no render Inertia — a timeline real só existe no path `request()->ajax()` (Blade `product.stock_history_details`). A tela React é **fachada**.~~ Desde 2026-07-21 o Controller resolve `movements` via `Inertia::defer` e o `StockHistory.tsx` consome com `<Deferred>`. O que segue valendo: a tela ainda não foi para `live` (screen-grade 47, charter `draft`), então Larissa não audita movimento de estoque na UI nova.

**Aceite:**
- [ ] Controller (`ProductController@productStockHistory`) passa `movements` (JSON) via `Inertia::defer` — data · operação · qty · stock_before · stock_after · ref clicável (OS/Compra/Venda).
- [ ] Cor semântica (emerald in / rose out / amber adj), append-only (sem mutação em GET).
- [ ] Hero KPIs entrada/saída 30d (charter já declara). Smoke browser biz=1. Sobe screen-grade de 47.

### US-PROD-022 · [G-02] ⚠️Tier0 · Multiplicador/markup por tabela de preço (SellingPriceGroup.mult)

> owner: wagner · priority: p1 · status: todo · type: story · estimate: 14h · origin: onda-produto-passo2-2026-07-03 · blocked_by: US-PROD-020

**Implementado em:** _pendente_ — não iniciada: `ProdutoUnificadoController.php:186` ainda devolve `'mult' => 1.00` hardcoded e a [ADR ARQ-0001](adr/arq/0001-selling-price-multiplier.md) segue `proposed`.

**Por quê.** "Preço por tabela" aparenta funcionar mas é **1:1** (`ProdutoUnificadoController@tabelas` retorna `'mult' => 1.00` hardcoded, l.183; [ADR ARQ-0001](adr/arq/0001-selling-price-multiplier.md)). Conta Azul (markup auto) e Linx (tabela por loja) têm. Desbloqueia F3 do `/unificado`.

**⚠️ Tier 0 valor** — resolver ADR ARQ-0001 (coluna `multiplier` OU cálculo via `VariationGroupPrice`); implementação exige **dupla-confirmação (2 caminhos numéricos) + tabela antes→depois + aprovação Wagner** antes de mergear. Teste E2E ancorado no contrato (não na implementação).

### US-PROD-023 · [G-05] Finalizar + promover as **6 telas React não-cadastrais** do Produto (draft→live) + ligar a Consulta na navegação

> owner: wagner · priority: p1 · status: todo · type: epic · estimate: 6h · origin: onda-produto-passo2-2026-07-03 · blocked_by: US-PROD-020

**Implementado em:** _pendente_ — não iniciada: 6 dos 7 charters no escopo desta US seguem `draft`. O único `live` (`Index.charter.md`) foi promovido pelo passe de governança `charter-promote-signal` (PR #4155), fora do escopo desta US.

**Por quê (Wagner 2026-07-03).** O React do Produto **ainda precisa ser feito**: as telas em `resources/js/Pages/Produto/` existem como `.tsx` mas quase nenhuma é `live` (`awaiting-smoke-browser`, 0 `review.md`). Unificado 56 + StockHistory 47 puxam a nota.

**Escopo redefinido em 2026-09-21 — o critério é qual writer a tela toca** ([proposal](../../decisions/proposals/2026-09-21-produto-8-telas-react-limbo-decisao-w.md)): as **6 telas que NÃO encostam no caminho de gravação da Larissa** são desta US — `Index`, `Show`, `StockHistory`, `Unificado/Index` (read-only), `BulkEdit` (writer próprio `bulk-update`) e `SellingPrices` (writer próprio `save-selling-prices`). **`Create` e `Edit` saíram** para a US-PROD-029: elas gravam no `store()`/`update()` **compartilhado**, e é exatamente isso que a 029 existe para resolver.

**O `can:product.view` saiu do aceite, e o motivo fica registrado:** medido em 2026-09-21, o gate **já existe** dentro do `ProdutoUnificadoController:139` (`abort(403)`, aceita `product.view` **OU** `product.create`), é declarado em comentário, tem `UC-PUNI-06` e **teste** (`ProdutoUnificadoContratoTest.php:340`). Pôr `middleware('can:product.view')` na rota seria **mais estrito que o controller** e trancaria quem só tem `product.create` — regressão. ⚠️ **E nenhum teste pegaria essa regressão** — medido em 2026-09-21 por revisão adversarial do próprio fechamento: o único caso que exercita o gate (`ProdutoUnificadoContratoTest.php:340`) revoga **as duas** permissions antes de exigir `403`, então o `403` vindo do middleware satisfaria o assert igual; e o caminho `create`-only não tem cobertura (os 3 testes que concedem só `product.create` têm **zero** referência a `products/unificado`). Isso torna o `TODO [CL]` de `routes/web.php:699` **mais** perigoso, não menos — ele **foi corrigido** no [#7583](https://github.com/wagnerra23/oimpresso.com/pull/7583) (mergeado 2026-09-21) — o comentário agora aponta pro gate verdadeiro e diz explicitamente pra **não** adicionar o middleware.

**Aceite (por tela):**
- [ ] Ligar a Consulta `/products/unificado` na navegação — **decisão [W] 2026-09-21** (*"sim, entra na navegação"*). É **aditiva** (não substitui `/products`): sem cutover F5, sem canary. Fazer **depois** de as telas saírem de `draft`, para não expor função incompleta (🔴 do [BRIEFING](BRIEFING.md)).
- [ ] Trocar native `<select>`/`<input>` por `@/Components/ui`; remover blue-leak (sky-700) e stone cru; PageHeader + token roxo.
- [ ] Smoke browser biz=1 + `review.md` → promover charter `draft`→`live`.
- [ ] Priorizar as de menor nota (StockHistory 47 → via US-PROD-021; Unificado 56; SellingPrices 68).
- [x] Repontar `BulkEdit.tsx` pro writer real `/products/bulk-update` — decisão [W] de 2026-07-27, destravada pelo [#7523](https://github.com/wagnerra23/oimpresso.com/pull/7523) (que entregou o `UC-PBULK-05`) e aplicada em 2026-09-21. ⚠️ Não liga a feature: `enable_product_bulk_edit` segue `false`.

### US-PROD-024 · [G-03] ⚠️Tier0 · Custo médio + valor/custo em estoque — SPIKE de descoberta primeiro

> owner: wagner · priority: p2 · status: todo · type: epic · estimate: 24h · origin: onda-produto-passo2-2026-07-03 · blocked_by: US-PROD-020

**Implementado em:** _pendente_ — não iniciada: o SPIKE (Fase 1) não produziu inventário algum em `memory/requisitos/Produto/`; as menções a custo médio no módulo são as pré-US (CAPTERRA-FICHA/INVENTARIO + SDD), não entrega desta.

**Por quê (Wagner 2026-07-03: "estudar melhor o custo médio, muita coisa já tem pronta").** NÃO é greenfield — o UltimatePOS já calcula custo por compra. Antes de construir agregação, **mapear a máquina de custo que já roda**.

**Fase 1 — SPIKE de descoberta (obrigatória antes de codar):**
- [ ] Inventariar o que já existe: `default_purchase_price`/`dpp_inc_tax` por variação, `VariationLocationDetails` (qty_available), fluxo de custo na entrada de compra (`PurchaseController`/`TransactionUtil`), relatórios `stock-report`/`stock-by-sell-price`/`get-opening-stock`.
- [ ] Documentar: custo médio já é recalculado na compra? Onde? Qual a fonte-de-verdade de "valor em estoque"? Registrar em `casos.md` ou nota.

**Fase 2 — só depois do spike:**
- [ ] Expor agregação valor/custo em estoque + margem média nos KPIs do `/unificado` (hoje `margem_media`/`sem_giro`/`stockQty` zerados; reusa o que já existe).
- [ ] **⚠️ Tier 0 estoque/valor** — dupla-confirmação (2 caminhos) + antes→depois + aprovação Wagner. Medir demanda (ADR 0105) antes de investir as ~20-30h.

### US-PROD-025 · [G-06] UI de BOM drag-drop + baixa-de-componente do kit no PDV

> owner: wagner · priority: p2 · status: todo · type: story · estimate: 14h · origin: onda-produto-passo2-2026-07-03 · blocked_by: US-PROD-020

**Implementado em:** _pendente_ — não iniciada: não há UI de BOM em `resources/js/Pages/Produto/` (o `/unificado` só exibe o contador `bomCount` e o rótulo da sub-view) nem baixa-de-componente no PDV. O CRUD API `products.bom.*` é pré-US (§2), não entrega desta.

**Por quê.** `ProductBom` (`Inventory/ProductBomController.php`) tem CRUD API mas sem UI. Bling tem kit com estoque de componente. Comprovar baixa-de-componente do kit no PDV.

### US-PROD-026 · Fornecedores/cotação por produto (melhor preço no drawer)

> owner: wagner · priority: p3 · status: todo · type: story · estimate: 12h · origin: onda-produto-passo2-2026-07-03 · blocked_by: US-PROD-020

**Implementado em:** _pendente_ — não iniciada: `ProdutoUnificadoController.php:167` ainda devolve `'fornecedor' => null` (TODO no código).

**Por quê.** Feature do drawer rico do mockup Cowork ([produtos-gap.md](produtos-gap.md) Parte 6): melhor cotação por fornecedor destacada. Hoje `ProdutoUnificadoController::insumos()` retorna `fornecedor => null` (TODO). Único ❌ AUSENTE do inventário.

### US-PROD-027 · [V0] Travar o acidente do 0-row: preço zero em tabela é inerte só por sorte do PHP

> owner: wagner · priority: p1 · status: todo · type: story · estimate: 3h · origin: adversario-tabela-preco-2026-07-15

**Implementado em:** _pendente_ — não iniciada: `UC-PTAB-05` não existe nem em [`SellingPrices.casos.md`](../../../resources/js/Pages/Produto/SellingPrices.casos.md) nem em teste algum; segue como bullet do §Backlog de casos, sem id.

**Por quê.** Uma row em `variation_group_prices` com `price_inc_tax = 0` + `price_type = 'fixed'` é **inofensiva no PDV** — mas por **coincidência de semântica do PHP**, não por invariante desenhado. O `SellPosController:1791` faz `if (! empty($variation_group_prices['price_inc_tax']))`, e `!empty(0)` é `false` → cai no preço padrão. Um refactor razoável (`isset()`, `!== null`, tipar `?float`) **destrava venda a preço zero** em todo produto que já tem 0 gravado. **Nada testa esse acidente.**

E há zeros gravados: a UI (React **e** Blade) pré-preenche célula sem preço com `0` e envia — `row[v.id] = existing ?? { price: 0, price_type: 'fixed' }` (`SellingPrices.tsx`) e `... : 0` (`add-selling-prices.blade.php`). Salvar a tela converte "sem row (usa o padrão)" em "row com preço 0".

**Escopo — TEST-ONLY, não muda comportamento, não precisa de decisão [W]:** cravar o comportamento atual como contrato explícito. Vira `UC-PTAB-05` em [`SellingPrices.casos.md`](../../../resources/js/Pages/Produto/SellingPrices.casos.md) (hoje está no §Backlog de casos sem id).

**Aceite:**
- [ ] Teste na lane `Estoque · MySQL` (allowlist do `estoque-pest.yml`): dado row `(variação × tabela)` com `price_inc_tax = 0` e `price_type = 'fixed'`, quando a venda busca o preço com aquele price_group, então usa o **preço padrão da variação** — não zero.
- [ ] Cobrir também o caso **sem row** (`getVariationGroupPrice` devolve `''`) → também cai no padrão. É o caso NORMAL e o que sangra em Labels/Woo.
- [ ] `UC-PTAB-05` no `casos.md` ancorado em `CU-PROD-03` + REGRA MESTRE, com `// Cobre UC-PTAB-05` no teste (G-2).
- [ ] Comentário no `SellPosController` marcando o `!empty()` como **load-bearing** (hoje quem refatora não tem como saber).

**NÃO cobre (decisão [W] separada — §Backlog do `casos.md`):** consertar o default 0 da UI · guard em `LabelsController:145`/`WoocommerceUtil:343,733` (que **não** guardam e quebram no `''`) · preço 0 legítimo inexprimível. As três **brigam entre si** — parar de gravar zeros piora etiqueta/Woo.

**Origem:** passe adversarial 2026-07-15 sobre o ecossistema da tabela de preço (PRs #4299/#4300/#4308/#4319).

### US-PROD-028 · Blindar `fixVariationStockMisMatch` com parsing locale-safe

> owner: wagner · priority: p1 · status: done · type: story · estimate: 2h · origin: funcao-scorecard-productutil-2026-07-21 · completed_by: PR #4636

**Implementado em:** PR #4636 · [`ProductUtil::fixVariationStockMisMatch`](../../../app/Utils/ProductUtil.php) · [`EstoqueFixMismatchNumUfTest`](../../../tests/Feature/Estoque/EstoqueFixMismatchNumUfTest.php)

**Critérios de aceite:**

- `fixVariationStockMisMatch('1.500')` grava `1500.0`, aplicando o parser locale-safe canônico.
- O caminho irmão `updateProductQuantity('1.500')` preserva o mesmo contrato numérico.
- O teste roda na lane Estoque/MySQL e declara `@covers-us US-PROD-028`.

**Testado em:** `tests/Feature/Estoque/EstoqueFixMismatchNumUfTest.php`

**Resolvido em 2026-07-21.** `ProductUtil::fixVariationStockMisMatch($biz,$var,$loc,$stock)` passou a normalizar `$stock` com `num_uf()` antes de gravar `qty_available` ([ProductUtil.php](../../../app/Utils/ProductUtil.php)). Varredura contada: **1/1 consumidor** = `ReportController::adjustProductStock`.

**Âncora (externa, não inventada):** REGRA MESTRE ([proibicoes.md](../../proibicoes.md) Tier 0 — toda escrita de valor/estoque deve ser locale-safe, origem incidente 2026-06-05) + [DOC-RAIZ-ESTOQUE §10](../Estoque/DOC-RAIZ-ESTOQUE.md) ("usar SEMPRE ProductUtil pra mexer `qty_available`").

**Teste de regressão:** [`tests/Feature/Estoque/EstoqueFixMismatchNumUfTest.php`](../../../tests/Feature/Estoque/EstoqueFixMismatchNumUfTest.php) preserva o RED anterior no recibo e prova, após o fix, `fixVariationStockMisMatch('1.500')` → 1500. A contrap prova o mesmo contrato no irmão `updateProductQuantity`.

**Escopo honesto (por que p1, não p0):** o fluxo sancionado manda `total_stock_calculated` (float cru, sem agrupamento de milhar) → **não corrompe hoje**. A falha é (a) endpoint **GET** com `stock` arbitrário na query → tampering grava qualquer valor sem `num_uf`/validação/CSRF (qualquer user com `report.stock_details`); (b) ausência da defesa `num_uf` que a REGRA MESTRE exige. Escalar a p0 se auditoria mostrar exploração.

**Correção aplicada:** opção 1 (`num_uf($stock)`) foi aprovada sob a REGRA MESTRE e mergeada no PR #4636. As opções GET→POST/CSRF e recomputação server-side continuam fora do escopo desta US; só viram nova US com sinal próprio.

**Origem:** full-sweep funcao-scorecard de `ProductUtil` ([app-utils-productutil.yaml](../../governance/scorecards/funcoes/app-utils-productutil.yaml), fixVariationStockMisMatch C2), PR #4628.

### US-PROD-029 · Cadastro de produto em ROTA PARALELA (o `ProductController` da Larissa não é tocado)

> owner: felipe · priority: p2 · status: todo · type: epic · estimate: 24h · origin: decisao-felipe-2026-08-24

**Implementado em:** _pendente_ — decisão tomada em 2026-08-24, execução adiada para sessão própria. Nada foi construído: `/products/create` e `/products/{id}/edit` seguem no `Route::resource('products', ProductController::class)` ([routes/web.php:732](../../../routes/web.php)).

**Escopo (explicitado em 2026-09-21):** as telas **`Produto/Create` e `Produto/Edit`** são desta US — são as únicas 2 das 8 que gravam pelo writer **compartilhado** com a Larissa (`POST /products` → `store()`; `PUT /products/{id}` → `update()`), medido submit por submit. A promoção `draft→live` delas acontece **na rota nova**, não na bifurcação por header.

**O `blocked_by: US-PROD-023` foi removido em 2026-09-21, e o motivo importa:** ele apontava pro lado contrário da intenção. A 023 prometia promover as 8 telas *assumindo o controller compartilhado*, e esta US declara que **muda o desenho dela** para o cadastro — logo fazer a 023 primeiro produziria o trabalho que esta descarta. Com `Create`/`Edit` fora do escopo da 023, a dependência deixou de existir: as duas podem correr em paralelo. ([proposal 2026-09-21](../../decisions/proposals/2026-09-21-produto-8-telas-react-limbo-decisao-w.md) §4.)

**A decisão (Felipe 2026-08-24).** O cadastro de produto ganha **endereço próprio e controller próprio**, como a Consulta já tem (`/products/unificado` → `ProdutoUnificadoController`). Motivo: a tela e o caminho de gravação da Larissa (ROTA LIVRE, biz=4, 99% do volume) **não podem ser tocados de forma alguma** — e hoje não há como acrescentar um campo ao cadastro sem editar o código por onde ela salva.

**O que foi MEDIDO antes de decidir (não presumir de novo):**

- A separação hoje é por **header, não por endereço**. `ProductController` bifurca em `request()->header('X-Inertia')`: presente → `Inertia::render('Produto/Create'|'Produto/Edit')`; ausente → `view('product.create'|'product.edit')` (linhas 574/613 e 914/960). A Larissa navega sem o header e cai no Blade — as telas `.tsx` **não são as dela**.
- Mas o **controller é compartilhado**: `Route::resource` dá **um** `store()` e **um** `update()`, e o formulário Blade dela posta exatamente ali. Acrescentar campo ao cadastro encosta no caminho de gravação dela, ainda que a tela não mude um pixel.
- Tamanho do que seria duplicado: `ProductController` tem **2.906 linhas**; `store()` 174, `update()` 252, `create()` 126, `edit()` 110 — e essas ~660 se apoiam no resto (variações, estoque inicial, tabelas de preço, imagens, campos personalizados, combos). Para comparar: o `ProdutoUnificadoController` da Consulta tem 1.088 linhas e é **só leitura**.

**⚠️ O risco que esta US assume, de olhos abertos.** Passam a existir **dois códigos que gravam a mesma tabela**. Enquanto os dois viverem, toda regra nova (validação, arredondamento, trava) precisa entrar nos dois; no dia em que entrar em um só, o mesmo produto passa a ser salvo de dois jeitos conforme a porta usada — defeito que não dá erro e aparece meses depois. Já mordeu neste repositório ([§5 2026-08-02](../../proibicoes.md) — "corrigir UMA de N implementações duplicadas: o fix pousou na cópia que o consumidor não usa"). Mitigação obrigatória no aceite.

**Aceite:**

- [ ] Rota e controller próprios, fora do `Route::resource('products')`. **Zero linhas** alteradas em `ProductController@store`/`@update` — provado por `git diff --stat` no PR.
- [ ] Teste de não-regressão que posta **como o Blade posta** (sem header `X-Inertia`) e prova que o caminho da Larissa continua idêntico.
- [ ] Contra a duplicação: extrair a gravação para um Service compartilhado **ou** declarar no código por que as duas existem e o que sincroniza. Não deixar duas cópias mudas.
- [ ] Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) + REGRA MESTRE de valor/estoque ([proibicoes.md](../../proibicoes.md)) — o cadastro escreve preço e estoque inicial.
- [ ] Charter + `casos.md` + testes da tela nova (trio), como manda a governança do módulo.

**Pega carona nesta US (não vale sozinha):** coluna `products.observacao_critica` + caixa de seleção ao lado da observação, para o chip de recado da Consulta ficar **vermelho quando a nota é crítica** — §3.2 do handoff V3, hoje impossível porque `products` não tem campo de severidade (só `product_description`, texto livre, e os `product_custom_field1..13`, que servem a outra coisa). Sem lugar para marcar, a flag nasce sempre falsa; por isso ela espera o cadastro novo em vez de virar US própria.

**Relação com a US-PROD-023.** Aquela US promove as 8 telas React `draft→live` assumindo o controller compartilhado de hoje. Esta decisão **muda o desenho dela** para o cadastro: se o endereço passa a ser próprio, a promoção do Create/Edit acontece na rota nova, não na bifurcação por header. Reconciliar as duas quando esta entrar em execução.

**Ficou de fora, e é de outra tela.** O `+N reservado` na coluna Disponível (§6 do handoff V3) precisa da **natureza do local** (venda / bloqueado / custódia) em `business_locations`. Felipe 2026-08-24: é decisão do fluxo de estoque, responsabilidade de outra tela — não entra aqui nem vira US do Produto.

### US-PROD-030 · ⚠️Tier0 · Política de preço por produto: atualizar a cada custo novo OU manter o preço

> owner: wagner · priority: p2 · status: todo · type: story · estimate: 16h · origin: pedido-wagner-2026-10-06

**Implementado em:** _pendente_ — não iniciada: não existe coluna nem tela para essa escolha. `products` e `variations` não têm flag de política de preço, e `ProductUtil::updateProductFromPurchase` / `RecipeController::updateRecipeProductPrices` aplicam a mesma regra para todo produto.

**Casos de uso ([W] 2026-10-06, textual):** *"tem produto que deve ser sempre atualizado o preço a cada compra, ou modificação do item da composição. e tem produto que existe tabela de preço dos vendedores e o cliente não quer mudar o valor toda hora então ele escolhe manter desatualizado."*

- **CU-A · Atualiza sempre.** O preço de venda acompanha o custo: a cada **compra do próprio item** ou a cada **mudança de custo de um componente** da composição/receita, o preço é recalculado mantendo a margem.
- **CU-B · Mantém o preço.** O produto tem tabela de preço dos vendedores, e o cliente não quer o valor mudando a toda hora. O preço fica parado de propósito mesmo com custo novo, e a margem é que varia.

A escolha é **por produto**, não por empresa: os dois casos convivem no mesmo cadastro.

**O que existe hoje (medido em `origin/main` 2026-10-06):**

| Caminho | Chave | Comportamento |
|---|---|---|
| Compra (`ProductUtil::createOrUpdatePurchaseLines` → `updateProductFromPurchase`) | `business.enable_editing_product_from_purchase`, **por empresa** | grava custo e preço de venda do **item comprado** com o que foi digitado na tela de compra. Não toca as receitas que usam o item. |
| Produção finalizada (`ProductionController.php:288`) | `manufacturing_settings.enable_updating_product_price`, **por empresa** | atualiza o **custo** do produto fabricado. |
| Botão "Atualizar preço do produto" na lista de receitas (`RecipeController::updateRecipeProductPrices`) | manual | grava o custo da receita como custo do produto e **mantém o preço de venda** (margem flutua). |

O custo da receita já acompanha o insumo, porque é recalculado na leitura. O **preço de venda** do produto composto nunca muda sozinho. Ou seja: o oimpresso implementa **só o CU-B**, e para todos os produtos.

**Paridade com o legado (Delphi / Office Comercial):** o CU-A × CU-B é a flag `PRODUTO.TEM_MARGEM_FIXA_CONTIBUICAO` (`S` = mantém margem e sobe o preço · `N` = mantém o preço), com `PODE_ATUALIZAR_VALORES_VENDA` no produto e na linha da nota de entrada (`NF_ENTRADA_PRODUTOS`). Numa base real de oficina medida em 2026-07-15: **83,8% `N` · 8,2% `S` · 8,0% `NULL`** ([ANTI-REGRESSAO A-1](ANTI-REGRESSAO-cadastro-produto-legacy.md)). Migrar como está **quebra em silêncio** quem depende do `S`.

**Perguntas para [W] — respostas de 2026-10-06:**

1. **Default** de produto novo e de produto migrado sem valor (`NULL`) — **mantém o preço** ([W] aceitou a sugestão da pesquisa). Produtos `S` do legado migram como *atualiza*. Medir a flag numa base de cliente CV antes de ligar a migração.
2. No CU-A, as **tabelas de preço dos vendedores** (`SellingPriceGroup` / `variation_group_prices`) também acompanham? — **a política é por tabela** ([W] aceitou a sugestão): tabela em % sobre o preço base acompanha o preço; tabela de valor fixo nunca muda sozinha e dispara o aviso da pergunta 3. Pareia com US-PROD-022 (multiplicador por tabela). Nota: no legado a flag também existe **por tabela** (`PRODUTO_TABELA_PRECO.TEM_MARGEM_FIXA_CONTIBUICAO`).
3. No CU-B, avisar que o produto está desatualizado? — **sim, na receita** ([W]). A receita mostra que o custo subiu e a margem caiu, com o antes e o depois.
4. Composição em mais de um nível propaga até o produto final no CU-A? — **sim** ([W]).
5. O CU-A recalcula na hora ou vira pendência? — **os dois casos existem** ([W]): há produto que recalcula **na hora** (no lançamento da compra ou na alteração do componente) e há produto em que o preço novo vira **pendência que alguém confirma**. Proposta, a confirmar com [W]: a política por produto passa a ter três valores — *atualiza na hora* · *atualiza com confirmação* · *mantém o preço* (CU-B). Na confirmação, a pendência aparece na receita, no mesmo lugar do aviso da pergunta 3.

**Referência de mercado (pesquisa 2026-10-06 — evidência, não contrato):** nenhum ERP de comunicação visual documenta essas regras em público (Mubisys, Zênite, Calcgraf, Visua e Sisgraf: não documentado). O padrão documentado nos ERPs genéricos é que **o custo sobe sozinho e o preço novo é decisão de alguém**.

| Pergunta | Mercado (fato documentado) | Legado WR Comercial | Premissa vale aqui? |
|---|---|---|---|
| 1 · Default | Tablet Cloud: reajuste por markup **desligado por padrão**, e a ajuda dele diz que não serve a quem tem preço impresso ([fonte](https://ajuda.tabletcloud.com.br/retaguarda/produtos/atualizacao-automatica-de-preco-de-venda-por-custo-markup)). Varredura anterior (8 BR + 9 globais): nenhum propaga custo→preço por default ([ANTI-REGRESSAO A-1](ANTI-REGRESSAO-cadastro-produto-legacy.md)) | `TEM_MARGEM_FIXA_CONTIBUICAO` default `S` no código, mas **83,8% `N`** numa base de oficina; **nunca medido em cliente de CV** | sim, para item de catálogo (vinil em rolo, tabela por m² da loja). Item sob encomenda já é precificado no orçamento, com o custo atual |
| 2 · Tabelas | Alterdata: tabela como **fator sobre outra tabela**, recalcula sozinha quando a origem muda; também aceita valor fixo ([fonte](https://ajuda.alterdata.com.br/retaguarda/cadastro-geral-retaguarda/tabelas-de-precos-retaguarda)). Odoo: tabela pode ser fórmula sobre o custo ([fonte](https://www.odoo.com/documentation/18.0/applications/sales/sales/products_prices/prices/pricing.html)) | a mesma flag **por par produto×tabela** em `PRODUTO_TABELA_PRECO` (com valor, % desconto, % acréscimo), e cópia por tabela na nota de entrada | sim: tabela de vendedor impressa ou combinada com cliente pede política própria |
| 3 · Aviso | Tablet Cloud: acima de um teto %, **alerta** em vez de aplicar. Avanço: "Preço de Venda Sugerido" a partir da nota de entrada. Aviso dentro da ficha técnica: não documentado | preço sugerido (`CALC_VVENDA_SUGERIDO`) guardado separado do praticado (`VALOR_VENDA`) | sim — e reforça a decisão de mostrar na receita |
| 4 · Vários níveis | Odoo: recálculo pela BOM é **manual** por produto ([fonte](https://odoo.com/forum/help-1/automated-action-compute-price-from-bom-when-bom-is-changed-194756)). Business Central: "Roll Up Standard Cost" percorre todos os níveis, mas **só gera sugestão** até alguém aplicar ([fonte](https://learn.microsoft.com/en-us/previous-versions/dynamicsnav-2016/hh172114(v=nav.90))) | botão manual **"Atualizar Preços"** na composição (AR-PROD-161); a propagação automática está **comentada no código**, com a nota *"AQUI DEVE PERGUNTAR SE MANTEM O CALCULO"* | a decisão de [W] (propagar em todos os níveis) vai além do mercado; é segura para o **custo**, e o cuidado fica no **preço** |
| 5 · Quando aplica | Linx: tela que altera custo, markup e preço **a partir da nota de entrada**. Nenhum sistema documenta "na hora × com confirmação" escolhido **por produto** | `PODE_ATUALIZAR_VALORES_VENDA` no produto **e por linha da nota de entrada** (quem lança a compra decide item a item) | sim; o legado é o que mais se aproxima dos dois casos de [W] |

**Sugestões que saem da pesquisa — aceitas por [W] em 2026-10-06 e incorporadas ao aceite abaixo:**
- 1 · default *mantém o preço* para produto novo e migrado `NULL`; produtos `S` do legado migram como *atualiza*. Medir a flag numa base de cliente CV antes de cravar.
- 2 · política **por tabela**, como no legado: tabela em % sobre o preço base acompanha o preço; tabela de valor fixo nunca muda sozinha e dispara o aviso da pergunta 3.
- 3 · mostrar também o preço sugerido ao lado do praticado, e repetir o aviso na linha do item na tela de compra.
- 4 · proteger contra ciclo na composição e registrar cada alteração de preço propagada.
- 5 · opção por linha da compra para recusar o reajuste naquela nota (paridade `NF_ENTRADA_PRODUTOS`); teto % com alerta contra nota digitada errada.

**Aceite:**

- [ ] Campo de política por produto, visível no cadastro, e importado do legado a partir de `TEM_MARGEM_FIXA_CONTIBUICAO`.
- [ ] CU-A: compra do item e mudança de custo de componente recalculam o preço mantendo a margem, **na hora** ou **como pendência confirmada** conforme o produto, propagando por todos os níveis da composição. CU-B: preço intocado nos dois gatilhos.
- [ ] CU-B: a receita mostra o aviso de desatualizado (custo antes→depois, margem antes→depois) e o **preço sugerido ao lado do praticado**; o mesmo aviso aparece na linha do item na tela de compra.
- [ ] Default *mantém o preço* para produto novo e migrado `NULL`; `S` do legado migra como *atualiza* — depois de medir a flag numa base de cliente CV.
- [ ] Política **por tabela de preço**: % sobre o preço base acompanha; valor fixo nunca muda sozinho e gera o aviso.
- [ ] Na compra, opção por linha para **recusar o reajuste** daquela nota; **teto %** de reajuste acima do qual vira alerta em vez de aplicar.
- [ ] Propagação multinível protegida contra **ciclo** na composição, com registro de cada alteração de preço propagada.
- [ ] Teste de contrato dos dois modos nos dois gatilhos (compra × composição), no tenant de teste 98.
- [ ] **REGRA MESTRE** ([proibicoes.md](../../proibicoes.md)): dupla confirmação do cálculo + tabela antes→depois dos produtos afetados + aprovação [W] antes de ligar em produção.
- [ ] Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)): a propagação de componente para composto não atravessa `business_id`.

**Relação com a Fabricação:** independe da correção da janela "Nova receita" (a proposta em análise é a cópia de receita **não** levar o preço de venda). Esta US é o recurso que falta para o preço do composto acompanhar o custo quando o produto pede isso.


## 4. Backlog fora do batch (sem sinal ainda — ADR 0105)

Viram US quando houver cliente/sinal ou drift de métrica:
- **PIM avançado** — families/atributos tipados/asset manager (Akeneo-like) vs os 20 custom fields atuais.
- **Multi-canal/trade-policy** — preço por canal, sync marketplace (VTEX-like) além do toggle WooCommerce.
- **GTIN auto por variação** — geração automática de código de barras (Hiper-like).
- **`Inertia::defer` no `/unificado`** — hoje sem defer, TODOs de N+1/cache.

## 5. Referências

- [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (capacidade 61/100) · [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) (✅6/🟡11/❌1) · [BRIEFING.md](BRIEFING.md) · [produtos-gap.md](produtos-gap.md) · [UI-CATALOG.md](UI-CATALOG.md)
- [adr/arq/0001-selling-price-multiplier.md](adr/arq/0001-selling-price-multiplier.md) (proposed — US-PROD-022)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) · [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0089](../../decisions/0089-capterra-driven-module-evolution.md)
- Board screen-grade: [SCREEN-GRADE-BOARD-2026-05-30.md](../../governance/scorecards/SCREEN-GRADE-BOARD-2026-05-30.md)
- Plano da onda: [template-onda-modulo.md](../_Governanca/programa-ondas/template-onda-modulo.md)

## 6. Histórico

- **2026-10-06** — US-PROD-030 registrada: política de preço por produto (atualiza a cada custo novo × mantém o preço), a partir dos dois casos de uso descritos por [W]. Estado atual e paridade com o legado medidos em `origin/main`; 5 perguntas abertas para [W] antes de codar; no mesmo dia [W] respondeu 3 (aviso na receita · propaga em todos os níveis · recalcula na hora OU com confirmação, conforme o produto), e aceitou as sugestões da pesquisa de mercado (1, 2 e os complementos). [W+C]
- **2026-09-21** — **Reconciliação US-PROD-023 ↔ US-PROD-029 + decisão [W] sobre a navegação.** [W] textual: *"sim, entra na navegação — aplica as duas linhas e a reconciliação"*. **(a)** A 023 passa a ser **6 telas** e a 029 ganha `Create`/`Edit`: o critério é **qual writer a tela toca** — medido submit por submit, só 2 das 8 encostam no `store()`/`update()` compartilhado com a Larissa. **(b)** O `blocked_by: US-PROD-023` saiu da 029: ele contradizia o corpo dela, que declara mudar o desenho da 023 (fazer a 023 antes produziria o trabalho que a 029 descarta). **(c)** O `can:product.view` saiu do aceite da 023 — medição mostrou que o gate **já existe** no controller (`:139`, `view` OU `create`, com `UC-PUNI-06` + teste) e que o middleware seria **regressão**; o `TODO` do código **foi corrigido em vez de cumprido** ([#7583](https://github.com/wagnerra23/oimpresso.com/pull/7583), mergeado 2026-09-21). **(d)** Origem do trabalho: o chip do [#7522](https://github.com/wagnerra23/oimpresso.com/pull/7522) pedia decisão sobre o limbo das telas React, com o enquadramento *"são inalcançáveis"*. **Medido e corrigido:** são **não-linkadas**, não inalcançáveis — `/products/unificado` faz `Inertia::render` incondicional, e de lá `router.visit` alcança `Create`/`Edit` **com** header. Detalhe, recibos e a errata do que eu publiquei errado na [proposal 2026-09-21](../../decisions/proposals/2026-09-21-produto-8-telas-react-limbo-decisao-w.md). [CC]
- **2026-08-24** — US-PROD-029 registrada: o cadastro de produto ganha rota paralela, decisão de Felipe, execução adiada para sessão própria. Origem: ao fechar as divergências §15 do pacote V3 na Consulta ([PR #6184](https://github.com/wagnerra23/oimpresso.com/pull/6184)), duas do handoff ficaram sem como ser feitas por falta de campo no cadastro. A investigação mediu que a separação Blade↔React é por header `X-Inertia` e que `store()`/`update()` são compartilhados com o caminho da Larissa — daí a rota paralela. O `+N reservado` foi declarado fora do módulo (fluxo de estoque). [M+C]
- **2026-07-27** — Campo `**Implementado em:**` declarado nas 8 US que não o tinham (anchor coverage do módulo 11,1% → 100%). Estado verificado US a US contra o código em `b6b5fac`, não presumido — **8 `_pendente_`**, cada uma com a evidência da não-implementação na razão. Duas delas (US-020 e US-021) têm **código pré-existente entregue por fora da US** (os `casos.md`+testes das corridas `sdd-from-source`; o `movements` por `Inertia::defer` do PR #4658) — isso está **dito na razão**, não convertido em `_parcial_`: pela [ADR 0302](../../decisions/0302-fonte-unica-doneness-anchor-aposenta-status-spec.md), US com `status:` aberto e âncora `parcial` é conflito, e a US em si continua aberta. O 1º parágrafo do "Por quê" da US-021 estava superado pelo #4658 e foi corrigido no mesmo PR (regra de precedência — código provado > SPEC). [CC]
- **2026-07-03** — SPEC criado (G-04 da onda Produto, Passo 2). Registra as capacidades já em prod (§2, prose) + 7 US de backlog do batch aprovado por Wagner ("ok pode fazer"). Fonte: [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md). [CC]
