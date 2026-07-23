---
id: requisitos-produto-spec
slug: produto
title: "Especificação funcional — Produto (cadastro core / catálogo do ERP)"
type: spec
module: Produto
status: ativo
owner: wagner
version: "1.0.0"
last_updated: "2026-07-21"
---

# Especificação funcional — Produto (cadastro core / catálogo do ERP)

> **Convenção do ID:** `US-PROD-NNN` para user stories.
> **Origem:** Passo 2 da onda standalone do programa de ondas ([template](../_Governanca/programa-ondas/template-onda-modulo.md), fila Produto→Cliente). Gap **G-04** do [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) — o core-dos-cores era o único módulo do programa **sem SPEC**. Nota de capacidade **61/100** ([CAPTERRA-FICHA.md](CAPTERRA-FICHA.md)).
> **Natureza do módulo:** Produto é **core UltimatePOS**, NÃO módulo nWidart (não há pasta própria em `Modules/` com esse nome). Modelo `App\Product`; backend `app/Http/Controllers/ProductController.php` (~2700 LOC) + `ProdutoUnificadoController.php` + `Inventory/ProductBomController.php`; telas em `resources/js/Pages/Produto/`.
> **Estado do React:** as **8 telas Inertia existem mas nenhuma é `live`** (todas `draft`/`awaiting-smoke-browser`) — o Blade legacy coexiste como fallback (branch dual `X-Inertia`, [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)). **O React do Produto ainda precisa ser finalizado** (Wagner 2026-07-03) — ver US-PROD-023.
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

**Por quê.** Este SPEC (G-04) fundou o contrato; falta a rede de casos que o defende. Sem `casos.md`, teste de valor vira tautológico (proibicoes §5) e o `casos-gate` não tem âncora. Pré-req de US-PROD-022/024.

**Aceite:**
- [ ] `casos.md` das telas críticas (Create, SellingPrices, StockHistory) com UC-IDs (contrato de não-regressão).
- [ ] Wagner revisa a seção §2 (capacidades já em prod) — confirma ou abre correção.
- [ ] Ligar os UCs ao `casos-gate` (ADR 0264).

### US-PROD-021 · [G-01] Kardex real na tela React StockHistory (deixar de linkar Blade)

> owner: wagner · priority: p0 · status: todo · type: story · estimate: 10h · origin: onda-produto-passo2-2026-07-03 · blocked_by: US-PROD-020

**Por quê.** Hoje a prop `movements` fica `undefined` no render Inertia — a timeline real só existe no path `request()->ajax()` (Blade `product.stock_history_details`). A tela React (`resources/js/Pages/Produto/StockHistory.tsx`) é **fachada** (screen-grade 47). Larissa não audita movimento de estoque na UI nova.

**Aceite:**
- [ ] Controller (`ProductController@productStockHistory`) passa `movements` (JSON) via `Inertia::defer` — data · operação · qty · stock_before · stock_after · ref clicável (OS/Compra/Venda).
- [ ] Cor semântica (emerald in / rose out / amber adj), append-only (sem mutação em GET).
- [ ] Hero KPIs entrada/saída 30d (charter já declara). Smoke browser biz=1. Sobe screen-grade de 47.

### US-PROD-022 · [G-02] ⚠️Tier0 · Multiplicador/markup por tabela de preço (SellingPriceGroup.mult)

> owner: wagner · priority: p1 · status: todo · type: story · estimate: 14h · origin: onda-produto-passo2-2026-07-03 · blocked_by: US-PROD-020

**Por quê.** "Preço por tabela" aparenta funcionar mas é **1:1** (`ProdutoUnificadoController@tabelas` retorna `'mult' => 1.00` hardcoded, l.183; [ADR ARQ-0001](adr/arq/0001-selling-price-multiplier.md)). Conta Azul (markup auto) e Linx (tabela por loja) têm. Desbloqueia F3 do `/unificado`.

**⚠️ Tier 0 valor** — resolver ADR ARQ-0001 (coluna `multiplier` OU cálculo via `VariationGroupPrice`); implementação exige **dupla-confirmação (2 caminhos numéricos) + tabela antes→depois + aprovação Wagner** antes de mergear. Teste E2E ancorado no contrato (não na implementação).

### US-PROD-023 · [G-05] Finalizar + promover as 8 telas React do Produto (draft→live) + `can:product.view`

> owner: wagner · priority: p1 · status: todo · type: epic · estimate: 6h · origin: onda-produto-passo2-2026-07-03 · blocked_by: US-PROD-020

**Por quê (Wagner 2026-07-03).** O React do Produto **ainda precisa ser feito**: as 8 telas em `resources/js/Pages/Produto/` existem como `.tsx` mas nenhuma é `live` (todas `awaiting-smoke-browser`, 0 `review.md`). Unificado 56 + StockHistory 47 puxam a nota. Falta o gate `can:product.view` no `/products/unificado` (TODO no código).

**Aceite (por tela):**
- [ ] `can:product.view` na rota `/products/unificado`.
- [ ] Trocar native `<select>`/`<input>` por `@/Components/ui`; remover blue-leak (sky-700) e stone cru; PageHeader + token roxo.
- [ ] Smoke browser biz=1 + `review.md` → promover charter `draft`→`live`.
- [ ] Priorizar as de menor nota (StockHistory 47 → via US-PROD-021; Unificado 56; SellingPrices 68).

### US-PROD-024 · [G-03] ⚠️Tier0 · Custo médio + valor/custo em estoque — SPIKE de descoberta primeiro

> owner: wagner · priority: p2 · status: todo · type: epic · estimate: 24h · origin: onda-produto-passo2-2026-07-03 · blocked_by: US-PROD-020

**Por quê (Wagner 2026-07-03: "estudar melhor o custo médio, muita coisa já tem pronta").** NÃO é greenfield — o UltimatePOS já calcula custo por compra. Antes de construir agregação, **mapear a máquina de custo que já roda**.

**Fase 1 — SPIKE de descoberta (obrigatória antes de codar):**
- [ ] Inventariar o que já existe: `default_purchase_price`/`dpp_inc_tax` por variação, `VariationLocationDetails` (qty_available), fluxo de custo na entrada de compra (`PurchaseController`/`TransactionUtil`), relatórios `stock-report`/`stock-by-sell-price`/`get-opening-stock`.
- [ ] Documentar: custo médio já é recalculado na compra? Onde? Qual a fonte-de-verdade de "valor em estoque"? Registrar em `casos.md` ou nota.

**Fase 2 — só depois do spike:**
- [ ] Expor agregação valor/custo em estoque + margem média nos KPIs do `/unificado` (hoje `margem_media`/`sem_giro`/`stockQty` zerados; reusa o que já existe).
- [ ] **⚠️ Tier 0 estoque/valor** — dupla-confirmação (2 caminhos) + antes→depois + aprovação Wagner. Medir demanda (ADR 0105) antes de investir as ~20-30h.

### US-PROD-025 · [G-06] UI de BOM drag-drop + baixa-de-componente do kit no PDV

> owner: wagner · priority: p2 · status: todo · type: story · estimate: 14h · origin: onda-produto-passo2-2026-07-03 · blocked_by: US-PROD-020

**Por quê.** `ProductBom` (`Inventory/ProductBomController.php`) tem CRUD API mas sem UI. Bling tem kit com estoque de componente. Comprovar baixa-de-componente do kit no PDV.

### US-PROD-026 · Fornecedores/cotação por produto (melhor preço no drawer)

> owner: wagner · priority: p3 · status: todo · type: story · estimate: 12h · origin: onda-produto-passo2-2026-07-03 · blocked_by: US-PROD-020

**Por quê.** Feature do drawer rico do mockup Cowork ([produtos-gap.md](produtos-gap.md) Parte 6): melhor cotação por fornecedor destacada. Hoje `ProdutoUnificadoController::insumos()` retorna `fornecedor => null` (TODO). Único ❌ AUSENTE do inventário.

### US-PROD-027 · [V0] Travar o acidente do 0-row: preço zero em tabela é inerte só por sorte do PHP

> owner: wagner · priority: p1 · status: todo · type: story · estimate: 3h · origin: adversario-tabela-preco-2026-07-15

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

- **2026-07-03** — SPEC criado (G-04 da onda Produto, Passo 2). Registra as capacidades já em prod (§2, prose) + 7 US de backlog do batch aprovado por Wagner ("ok pode fazer"). Fonte: [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md). [CC]
