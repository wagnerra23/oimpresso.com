# CAPTERRA-FICHA — Produto (capacidade)

> Ficha canônica de benchmark de **capacidade** do domínio **Produto** (cadastro/catálogo/estoque — core UltimatePOS + MWART Inertia parcial). Produto é o **insumo de preço/custo/estoque de TODO o resto** — Sells e Compras dependem dele.
> **Gerada:** 2026-07-03 · agente `capterra-senior` · **Onda standalone** do programa de ondas (fila Produto→Cliente, aprovada por [W] 2026-07-03)
> **Persona primária:** Larissa @ ROTA LIVRE (`business_id=4`), balconista não-técnica, cadastra produto (variação tam×cor, preço por tabela, estoque inicial), monitor 1280×1024, vestuário Termas do Gravatal/SC. 99% do volume.
> **Alvo de código:** `app/Http/Controllers/ProductController.php` (~2729 LOC, UPOS canon) · `app/Http/Controllers/ProdutoUnificadoController.php` (222 LOC, Cockpit V2, cheio de TODOs) · `app/Http/Controllers/Inventory/ProductBomController.php` (161 LOC, CRUD BOM) · `resources/js/Pages/Produto/` (8 Pages, 8 charters `status: draft`, 0 live) · `App\Product` (global scope `business_id`)
> ADR governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) (Capterra-driven) + [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant Tier 0) + [0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) (tests biz=1) + [0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) (MWART)

> ⚠️ **Distinção de escopo.** `Modules/ProductCatalogue` (catálogo **público** + QR via `CatalogueQrService`, Blade) é domínio **separado** — NÃO é este. Aqui é o **cadastro interno** de produto (o registro-mãe que alimenta venda, compra, fiscal). Esta ficha mede **CAPACIDADE de catálogo** (variação/grade, preço-por-tabela, kardex, BOM/kit, importação, PIM) vs os líderes — eixo que a `module-grade 71` (UX/DS das 8 telas) **não mede**. Ver §8 "O que a nota esconde".

---

## 1. Identidade do módulo

- **Nome interno:** `Produto` (core UltimatePOS — **sem** diretório `Modules/` próprio; modelo `App\Product` em `app/`, controllers em `app/Http/Controllers/`)
- **Domínio:** cadastro de produto / catálogo / variação-grade / preço-por-tabela / estoque / BOM-kit — o **registro-mãe** do ERP
- **Função:** CRUD de produto (simples/variável/combo), variações tam×cor com SKU auto, preço por `SellingPriceGroup`, estoque inicial + kardex, importação Excel, bulk-edit, sync WooCommerce
- **Estado lifecycle:** UI React **8 Pages, 100% charter, TODAS `status: draft`/`awaiting-smoke-browser` — ZERO `live`**. Blade legado coexiste como fallback (branch dual header `X-Inertia`, [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)). Backend UPOS 100% funcional.
- **Clientes diretos:** ROTA LIVRE biz=4 (Larissa, 99% volume) + Wagner biz=1 (canary/smoke seguro)
- **Diferencial-chave:** multi-tenant Tier 0 real ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) + variação tam×cor com SKU auto e validação de duplicado + `Inertia::defer` em KPIs + BOM real (`ProductBom` domain model)

## 2. Concorrentes-alvo

Pricing qualitativo (Tier 0: não commitar valores BRL — [proibicoes](../../proibicoes.md)). Global em US$ (referência pública). Foco no **cadastro/catálogo de produto** deles, não no PDV.

| # | Concorrente | Tipo | Faixa | Lacuna que o oimpresso pode preencher | Fonte |
|---|---|---|---|---|---|
| 1 | **Bling** | ERP PME BR | entrada → sério | Variação(atributos)+kit(composição c/ estoque de componentes)+lista de preços fortes; UI Bootstrap legado, sem multi-tenant Tier 0 | ajuda.bling.com.br |
| 2 | **Tiny (Olist)** | ERP PME BR | entrada → médio | **Grade + import/export planilha + update em massa de preço/descrição via API** fortes — nossa referência-topo BR de catálogo | ajuda.olist.com |
| 3 | **Omie** | ERP BR | médio | **Kardex real** (movimento detalhado + CMC + NF origem) + estrutura de produto/ordem de produção — expõe o kardex-de-brinquedo nosso (§8) | ajuda.omie.com.br |
| 4 | **Conta Azul** | ERP/financeiro PME BR | entrada → médio | **Tabela de preços com cálculo automático** (markup) + **custo médio** calculado na entrada + valor/custo total em estoque | ajuda.contaazul.com |
| 5 | **Hiper** | ERP pequeno-varejo BR | entrada | **Grade composta** (cor×tamanho) + GTIN + **gerar SKU/cód. barras auto por variação** + custo médio + markup mín. + preço por filial | ajuda.hiper.com.br |
| 6 | **Linx Microvix** | ERP varejo/moda enterprise BR | quote | **Grade tam×cor+coleção** nativa de moda + tabelas de preço por loja + filtros por marca/coleção/tamanho — o teto BR do nosso vertical (vestuário) | linx.com.br |
| 7 | **Nuvemshop** | plataforma e-commerce BR | free → planos | Até 3 variações × valores + import CSV massivo + GTIN/cód.barras; catálogo de loja, não ERP fiscal | atendimento.nuvemshop.com.br |
| 8 | **Sankhya / ERPFlex** | ERP médio BR | quote/médio | Cadastro de produto com variações + estrutura — referência de profundidade média BR | ajuda.sankhya.com.br |
| 9 | **Shopify** | e-commerce global | US$/mês por plano | **Até 2.048 variantes/produto (out/2025)** + **metafields tipados por categoria** + swatches — teto global de variação/atributo | help.shopify.com |
| 10 | **VTEX** | e-commerce enterprise global | enterprise | **SKU como entidade** + **trade policies** (catálogo/preço por canal) + specification groups por categoria — teto global de catálogo multi-canal | developers.vtex.com |
| 11 | **Akeneo** | PIM puro global | enterprise/open-src | **Families + family variants + attributes + asset manager** (mídia/vídeo/doc por família) — o teto de "gestão de produto séria" (PIM) | help.akeneo.com |
| 12 | **ERPFlex / Senior** | ERP BR médio | quote | Cadastro com grade + estrutura; referência de completude fiscal BR | docsnew.erpflex.com.br |

**Referência-topo BR do nosso vertical:** Linx Microvix (grade moda) e Tiny/Bling (catálogo PME). **Teto global:** Shopify (variantes), VTEX (SKU+trade policy), Akeneo (PIM/atributos/mídia).

## 3. Capacidades em produção (validadas)

```yaml
capacidades_em_prod:
  - cap: CRUD produto (simples/variável/combo) + duplicar
    score: P0
    onde: "ProductController.php@store/update (product_types: single/variable/combo, l.610-722)"
    evidencia: "Route::resource('products') + product_types() l.610"

  - cap: "Variação tam×cor com SKU auto + validação de SKU duplicado (batch)"
    score: P0
    onde: "ProductController.php@getProductVariationRow/checkProductSku/validateVaritionSkus (l.413-417)"
    evidencia: "rotas get_product_variation_row · check_product_sku · validate_variation_skus"

  - cap: "Preço por tabela (SellingPriceGroup) por variação — matriz grupo×variação"
    score: P0
    onde: "ProductController.php@addSellingPrices/saveSellingPrices (l.1964-1980, group_prices) + Pages/Produto/SellingPrices.tsx"
    evidencia: "variations.group_prices eager-load l.1843 · rota save-selling-prices"

  - cap: "Estoque inicial (opening stock) por localização + alerta de estoque baixo + validade/lote"
    score: P0
    onde: "OpeningStockController + ProductController enable_stock/alert_quantity/expiry_period (l.643-665)"
    evidencia: "rotas opening-stock/add|save · enable_product_expiry l.662 · enable_lot_number l.601"

  - cap: "Combo/kit (produto composto de variações)"
    score: P1
    onde: "ProductController.php@store type=='combo' → combo_variations (l.704-722)"
    evidencia: "get-combo-product-entry-row l.420"

  - cap: "BOM (Bill of Materials) — CRUD API multi-tenant"
    score: P1
    onde: "Inventory/ProductBomController.php (ProductBom domain model, ScopeByBusiness)"
    evidencia: "GET/POST/DELETE /api/products/{id}/bom · business_id firstOrFail l.33-36 (UI drag-drop = US-INV-002 pendente)"

  - cap: "Importação de produto (Excel) + import de estoque inicial + download Excel"
    score: P1
    onde: "ImportProductsController + ImportOpeningStockController + ProductController@downloadExcel"
    evidencia: "rotas import-products/store · import-opening-stock/store · products/download-excel"

  - cap: "Edição em massa (bulk-edit/bulk-update) + por localização + ativar/desativar/excluir em massa"
    score: P1
    onde: "ProductController@bulkEdit/bulkUpdate/updateProductLocation/massDeactivate/massDestroy + Pages/Produto/BulkEdit.tsx"
    evidencia: "rotas bulk-edit · bulk-update · bulk-update-location · mass-deactivate · mass-delete"

  - cap: "Atributos/PIM básico: categorias (subcategorias), marcas, unidades+sub-unidades, 20 custom fields, imagens (media), racks"
    score: P1
    onde: "ProductController form_fields l.632 (product_custom_field1..20) + get_sub_categories/get_sub_units + variations.media"
    evidencia: "20 custom fields · sub_units · brand_id/category_id · rack_details"

  - cap: "Sync canal WooCommerce (toggle por produto)"
    score: P2
    onde: "ProductController@toggleWooCommerceSync (l.2682)"
    evidencia: "rota toggle-woocommerce-sync"

  - cap: "Código de barras (tipos C128 etc) + etiquetas ZPL/PDF"
    score: P2
    onde: "ProductController barcode_types l.56 + LabelsController (Vestuario/Etiquetas ZPL, grade 74)"
    evidencia: "barcode_types() · /labels/add-product-row"

  - cap: "Multi-tenant Tier 0 (business_id global scope em toda query de produto)"
    score: P0
    onde: "App\\Product global scope + ProdutoUnificadoController Product::where('business_id',...) + ProductBom ScopeByBusiness"
    evidencia: "ADR 0093 · builder explícito por business_id"
```

## 4. Dimensões de capacidade P0-P3 — comparativa

Legenda: ✅ pareia/supera líder · 🟡 parcial · ❌ ausente. Nota /10 por mecanismo concreto.

| ID | Capacidade | Peso | Líder do eixo (mecanismo SOTA) | oimpresso Produto hoje | Nota /10 |
|---|---|:-:|---|---|:-:|
| **C01 (P0)** | Variação/grade tam×cor + matriz SKU + geração auto SKU + validação duplicado | 4 | Shopify (2.048 variantes, out/25) / Hiper (gera SKU+GTIN por variação) / Linx (grade moda) | ✅ variação com `get_product_variation_row` + `check_product_sku` + `validate_variation_skus` (batch); grade tam×cor real | **8** |
| **C02 (P0)** | Preço por tabela / price group (+ **regra de tabela inteira**) | 4 | Conta Azul (cálculo automático/markup) / VTEX (price table por trade policy) / Linx (tabela por loja) | 🟡 preço por (variação × grupo) **funciona** (`group_prices`, `fixed`+`percentage`, chega na venda). Falta a **regra de tabela inteira** (default por grupo). ⚠️ **nota sob revisão [W]** — o `5` foi dado sobre a premissa falsa "multiplicador oco / 1:1" (Errata [ADR ARQ-0001](adr/arq/0001-selling-price-multiplier.md), 2026-07-17) | **5** ⚠️ |
| **C03 (P0)** | Estoque inicial + por localização + alerta estoque baixo + validade/lote | 4 | Hiper/Omie (estoque por filial + custo médio) / Conta Azul (min/max + custo médio) | ✅ opening stock por local + `alert_quantity` + `enable_product_expiry`/`lot_number`; multi-localização | **8** |
| **C04 (P0)** | **Kardex / histórico de movimento de estoque (timeline auditável real)** | 4 | Omie (kardex: cada movimento + CMC + NF origem + tipo op.) | 🟡 backend `getVariationStockHistory` existe (Blade), mas a **tela React `StockHistory` NÃO recebe `movements`** no render Inertia (prop fica `undefined`) — timeline **só linka o Blade legacy**. Grade 47 (§8) | **4** |
| **C05 (P0)** | Custo médio + valor/custo em estoque (agregação de inventário) | 4 | Conta Azul (custo médio na entrada + valor/custo total) / Omie (CMC) | 🟡 `default_purchase_price` por variação existe; **agregação valor/custo em estoque + custo médio recalculado = ausente** no `/unificado` (KPIs `margem_media=0`, `sem_giro=0`, `stockQty=null` — TODOs) | **4** |
| **C06 (P0)** | Isolamento multi-tenant (Tier 0) | 4 | — (concorrentes multi-empresa, não Tier 0 rígido) | ✅ `business_id` global scope + `ProductBom` `ScopeByBusiness` + `firstOrFail` cross-tenant | **9** |
| **C07 (P1)** | Combo/kit + BOM (estrutura de componentes) | 2 | Bling (kit c/ estoque de componentes) / Omie (estrutura + ordem produção) | 🟡 combo (`type=='combo'`) + BOM CRUD API real (`ProductBom`), mas **UI de BOM drag-drop pendente (US-INV-002)** e kit sem baixa-de-componente no PDV comprovada | **6** |
| **C08 (P1)** | Importação/edição em massa (Excel/CSV import + bulk-edit + mass ops) | 2 | Tiny (import/export planilha + update massa preço/descrição via API) / Nuvemshop (CSV) | ✅ `import-products` + `import-opening-stock` + `bulk-edit`/`bulk-update` + `mass-deactivate/delete` + `download-excel` | **8** |
| **C09 (P1)** | Atributos/PIM (categorias, marca, unidades, custom fields, mídia) | 2 | Akeneo (families+attributes+asset manager) / VTEX (specification groups) | 🟡 categoria/subcategoria + marca + unidade+sub + **20 custom fields** + media por variação; mas sem families/atributos tipados nem asset manager | **6** |
| **C10 (P1)** | Sync canal e-commerce/marketplace | 2 | VTEX (trade policy multi-canal) / Shopify (canais) / Nuvemshop | 🟡 toggle WooCommerce por produto; sem trade-policy/multi-marketplace nem preço por canal | **4** |
| **C11 (P1)** | Código de barras / etiqueta (GTIN, ZPL, gerar auto) | 2 | Hiper (gera SKU+GTIN+cód.barras auto por variação) | 🟡 `barcode_types` + etiquetas ZPL/PDF (Vestuario/Etiquetas grade 74); geração auto de GTIN por variação não explícita | **6** |
| **C12 (P1)** | UX cadastro rápido (quick-add, densidade 1280px, dedup) | 2 | Bling/Conta Azul (cadastro mínimo nome+SKU) | ✅ `quick_add`/`save_quick_product` + Create/Edit densidade 1280px + dedup (Create grade 80, Edit 79) | **7** |
| **C13 (P2)** | Catálogo denso / cockpit (lista + KPIs + sub-views) | 1 | Linx (filtros marca/coleção/tamanho) | 🟡 `/produto/unificado` 5 sub-views (produtos/insumos/BOM/tabelas/histórico), mas **KPIs zerados** (populares/margem/sem_giro = TODO), native `<select>`/`<input>`, blue-leak (grade 56) | **4** |
| **C14 (P2)** | Perceived perf (defer, skeleton) | 1 | Shopify (Polaris) | 🟡 `Inertia::defer` em KPIs/rows na `Index`; `Unificado` sem defer, TODOs de cache/N+1 | **5** |
| **C15 (P2)** | UX/DS estado-da-arte (tokens, PageHeader, empty states) | 1 | Shopify Polaris / Linear | 🟡 Index 83 / BulkEdit 81 / Create 80 fortes, mas Unificado 56 + StockHistory 47 puxam pra baixo (tokens crus, header hand-rolled) | **6** |
| **C16 (P2)** | Catálogo público / QR (venda-social) | 1 | — (ERP genérico não tem) | ✅ `Modules/ProductCatalogue` (catálogo público + QR `CatalogueQrService`) — **domínio separado**, diferencial vertical | **8** |
| **C17 (P3)** | Permissão granular na tela de catálogo | 0.5 | VTEX/Akeneo (roles por catálogo) | 🟡 `Route::resource` tem gate; **`/products/unificado` SEM `can:product.view`** (TODO no código) — gap de permissão | **4** |
| **C18 (P3)** | Fornecedores/cotação por produto (melhor preço) | 0.5 | Omie/Bling (fornecedor no produto) | ❌ `insumos()` retorna `fornecedor => null` (TODO); sem cotação/melhor-fornecedor | **2** |

## 5. Cálculo da nota ponderada

Pesos canônicos: **P0=4 · P1=2 · P2=1 · P3=0.5**.

```
P0 (peso 4): (C01 8 + C02 5 + C03 8 + C04 4 + C05 4 + C06 9) = 38 × 4 = 152
P1 (peso 2): (C07 6 + C08 8 + C09 6 + C10 4 + C11 6 + C12 7) = 37 × 2 =  74
P2 (peso 1): (C13 4 + C14 5 + C15 6 + C16 8)                 = 23 × 1 =  23
P3 (peso 0.5):(C17 4 + C18 2)                                =  6 × 0.5=   3

Σ ponderado = 152 + 74 + 23 + 3 = 252

Máximo possível:
  P0: 6×10×4 = 240 · P1: 6×10×2 = 120 · P2: 4×10×1 = 40 · P3: 2×10×0.5 = 10  → 410

nota_capacidade = 252 / 410 × 100 = 61.5 → 61/100
```

```
NOTA CAPACIDADE oimpresso Produto: 61/100
Referência-topo BR (Tiny/Bling — grade+import+kit+lista de preço):   ~78/100
Referência BR vertical (Linx Microvix — grade moda+tabela por loja): ~80/100
Referência global (Shopify/VTEX/Akeneo — variantes/metafields/PIM):  ~85/100 (mas sem fiscal BR / não Tier 0)

Gap pro topo BR (Linx/Tiny): ~-18 pts. Causa: kardex-de-fachada (C04) + multiplicador de preço oco (C02) + agregação de valor/custo em estoque ausente (C05) + cockpit unificado com KPIs zerados (C13).
Vantagem sobre o mercado em: multi-tenant Tier 0 (C06), variação+SKU auto+validação batch (C01), catálogo público/QR (C16), import/bulk fortes (C08).
```

**Leitura honesta:** a capacidade (61) fica **abaixo** da `module-grade 71` (UX/DS agregada das 8 telas) — e isso é o ponto. O benchmark de catálogo contra Tiny/Linx/Shopify expõe que o **registro-mãe do ERP** tem fundação sólida de cadastro/variação/import, mas **três buracos de valor/estoque** (kardex fachada, multiplicador oco, valor-em-estoque ausente) que a tela bonita esconde. Produto é o **core-dos-cores** (alimenta Sells+Compras) e é o **mais fraco em governança** do programa (§8.4).

## 6. Top gaps P0/P1 (pra subir a nota)

| # | Gap | Cap | Esforço (IA-pair, ADR 0106) | ROI (persona Larissa) | Sinal ADR 0105 | Concorrente que tem |
|---|---|---|---|---|---|---|
| **G-01** | **Kardex real na tela React** — passar `movements` no render Inertia (timeline JSON + `defer`) em vez de linkar Blade legacy. Fecha C04, sobe `StockHistory` de 47 | C04 | M (~8-12h) | **P0** — Larissa não audita movimento de estoque hoje (tela fachada) | ✅ execute (feature declarada incompleta no board) | Omie (kardex CMC+NF) |
| **G-02** | **Regra de tabela inteira** — default por `selling_price_group` ("−15% em tudo") que a célula sobrescreve. **Não** é "criar multiplicador que falta": o preço por célula já funciona (Errata ARQ-0001) — é dar granularidade de grupo | C02 | M (~10-16h) ⚠️ **Tier 0 valor** | ⚠️ **reavaliar [W]** — o "alto/execute" pressupunha "1:1 quebrado" (falso); sem cliente pedindo, é feature-wish sem sinal (ADR 0105), não dor de catálogo confirmada | ⚠️ **decidir [W]** | Conta Azul (markup auto), Linx |
| **G-03** | **Agregação valor/custo em estoque + custo médio** no `/unificado` (KPIs `margem_media`/`sem_giro`/`stockQty` hoje zerados) | C05/C13 | L (~20-30h) ⚠️ **Tier 0 estoque/valor** | alto (visão de inventário; totalizadores do mockup Cowork) | 🟡 medir; exige dupla-confirmação | Conta Azul, Omie |
| **G-04** | **SPEC.md do Produto** — o core-dos-cores não tem SPEC (só BRIEFING). US-PROD-XXX ancoradas | — | S (~4-6h) | **P0 governança** — sem contrato, teste vira tautológico (§9) | ✅ execute (fragilidade estrutural) | — (dever interno) |
| **G-05** | **`can:product.view` no `/products/unificado`** + promover 8 charters draft→live (smoke browser biz=1) | C17 | S (~3-6h) | médio-alto (permissão + validar UI em prod) | ✅ execute (TODO no código) | — |
| **G-06** | **UI de BOM drag-drop** (US-INV-002) + baixa-de-componente do kit no PDV comprovada | C07 | M (~12-16h) | médio (kit/composição vertical) | 🟡 medir demanda | Bling (kit c/ estoque componente) |

## 7. Diferenciais oimpresso vs concorrentes

1. **Multi-tenant Tier 0 real** (`business_id` global scope + `ProductBom` `ScopeByBusiness` + `firstOrFail` cross-tenant) — [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md). Concorrentes são multi-empresa mas sem isolamento auditável desse nível.
2. **Variação tam×cor com SKU auto + validação de duplicado em batch** (`validate_variation_skus`) — parity com Hiper/Linx no eixo grade, com validação de colisão que muitos PME BR não expõem.
3. **Catálogo público + QR** (`Modules/ProductCatalogue` + `CatalogueQrService`) — venda-social/vitrine que ERP horizontal genérico não tem. Diferencial vertical.
4. **Import + bulk-edit + mass-ops completos** (`import-products`, `import-opening-stock`, `bulk-update-location`, `mass-deactivate/delete`, `download-excel`) — pareia com o forte do Tiny.
5. **Stack moderna** Laravel 13.6 + React 19 + Inertia v3 com `defer`/skeleton (Index 83) — vs Bootstrap legado (Bling) / UI pesada (Linx/Microvix).
6. **BOM como domain model** (`App\Domain\Inventory\Models\ProductBom`, não módulo Mfg improvisado) — base limpa pra estrutura de produto, ainda sem UI.

## 8. O que a nota "71 module-grade" esconde (leitura adversarial)

O coração da onda: procurar o que a `module-grade 71` (UX/DS das 8 telas) **esconde** sobre CAPACIDADE de catálogo. Cinco achados, cada um verificado no código:

1. **Kardex é fachada.** `StockHistory` (grade **47 Beginner**) parece uma tela de histórico de estoque, mas o render Inertia **não passa `movements`** — a prop `movements?: Movement[]` fica `undefined`, e a timeline real só existe no path `request()->ajax()` que retorna Blade (`product.stock_history_details`, `ProductController.php:2639`). Ou seja: a tela React **linka o legacy**, não renderiza a timeline. Larissa **não audita movimento de estoque de verdade** na UI nova. O board admite: *"timeline só linka pro Blade legacy — feature incompleta"*.

2. **~~Multiplicador de preço por tabela é oco~~ Falta a regra de tabela inteira.** ⚠️ **corrigido 2026-07-17** (Errata [ADR ARQ-0001](adr/arq/0001-selling-price-multiplier.md)): o preço por (variação × grupo) **funciona** — `price_type ∈ {fixed, percentage}`, lido em `ProductUtil::getVariationGroupPrice` e aplicado na venda (`SellPosController.php:1790`), com golden DB-backed (`CalculoValorProdutoTest.php:232`). O `'mult' => 1.00` de `ProdutoUnificadoController::tabelas()` é prop **cosmético** do protótipo `/unificado` (o autor comentou *"não existe nativamente"*), não um multiplicador neutralizado — a coluna nem existe. O gap real vs Conta Azul/Linx é o **default de tabela inteira** (declarar "−15%" uma vez por grupo em vez de célula a célula). A leitura "1:1 / aparenta funcionar mas não funciona" que este parágrafo trazia era **falsa**.

3. **8 telas draft, 0 live.** As 8 Pages têm charter, mas **todas `status: draft`/`awaiting-smoke-browser`** — nenhuma promovida a `live`, nenhuma `review.md`, nenhuma validada em prod. A UI de produto inteira está atrás de flag/branch-dual. A `module-grade 71` mede telas que **oficialmente nem entraram em produção**.

4. **Sem SPEC — a governança mais fraca do programa.** O domínio que alimenta **Sells + Compras + Fiscal** (o insumo de preço/custo/estoque de tudo) **não tem `SPEC.md`** (só BRIEFING + UI-CATALOG + ADR ARQ-0001 proposed + produtos-gap). Sem contrato de capacidade, qualquer teste vira **tautológico** (deriva da implementação, proibido em [proibicoes §5](../../proibicoes.md)) e o `casos-gate` não tem âncora.

5. **Zero prova de correção de valor/estoque — mesmo vetor do incidente `num_uf` de Sells.** Produto **define** preço/custo/margem/valor-em-estoque que Sells **consome**. O `store()`/`update()` chamam `num_uf` em `alert_quantity`, `expiry_period`, preços — o **mesmo parser** que inflou 16 vendas ×100k em Sells (incidente 2026-06-05). Não há teste E2E de que **preço-por-tabela/custo/margem persistem certo** ao cadastrar variação, nem de que `num_uf` não strippa decimal de preço de custo. O `ProdutoUnificadoController` calcula `margin = (price - cost)/price` inline sem cobertura. **A rede de segurança de valor termina onde Produto começa.**

**Síntese adversarial:** a `71` diz "telas OK"; a capacidade (61) diz "o registro-mãe do ERP tem cadastro sólido, mas o kardex é de brinquedo, o multiplicador é oco, o valor-em-estoque não existe, e não há SPEC nem teste de que a conta de custo/preço fecha". O G-04 (SPEC) é o pré-requisito barato: sem contrato, os gaps de valor (G-02/G-03) não têm âncora pra teste não-tautológico.

## 9. Anti-padrões / pegadinhas Tier 0 (Produto)

- ⛔ **REGRA MESTRE cálculo valor/estoque** ([proibicoes](../../proibicoes.md)): mexer em **preço, custo, margem, multiplicador de tabela, valor/custo em estoque, `num_uf`** exige **dupla confirmação** (2 caminhos com números) + **tabela antes→depois** + aprovação humana. G-02 (multiplicador), G-03 (valor em estoque) e qualquer preço-por-variação caem aqui.
- ⛔ **`num_uf` em preço de custo/venda** — mesmo parser que inflou vendas ×100k em Sells (incidente 2026-06-05). Frontend NUNCA manda float locale-ambíguo; arredondar 2 casas no submit; separador de milhar tem SEMPRE 3 dígitos.
- ⛔ **`business_id` global scope** obrigatório em toda query de produto ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — `ProductBom` usa `ScopeByBusiness` + `firstOrFail` cross-tenant; qualquer nova query de catálogo herda.
- ⛔ **Teste tautológico** ([proibicoes §5](../../proibicoes.md)) — teste de Produto deve ancorar em contrato (SPEC/ADR/charter/casos), NÃO na implementação. Como **não há SPEC** (G-04), este risco é agudo: criar SPEC ANTES de escrever teste de cálculo.
- ⛔ **Smoke em `business_id=4`** (ROTA LIVRE prod, 99% volume) — usar biz=1 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).
- ⛔ **Charter obrigatório** antes de Edit/Write em `Pages/Produto/*.tsx` ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) — as 8 já têm charter (draft); promover a live exige smoke browser.
- ⛔ **Não confundir `Produto` (cadastro interno) com `Modules/ProductCatalogue` (catálogo público+QR)** — domínios separados; não misturar controllers/Pages.

## 10. Decisão / Nota / Recomendação

### Nota de capacidade
**61/100** — abaixo do topo BR (Tiny/Bling ~78, Linx moda ~80) e do teto global (Shopify/VTEX/Akeneo ~85, mas sem fiscal BR / não Tier 0). Honesto: o Produto é **sólido no cadastro-fundação** (variação+SKU C01, estoque inicial C03, import/bulk C08, multi-tenant C06) e **fraco nos eixos de valor/estoque** (kardex fachada C04, multiplicador oco C02, valor-em-estoque ausente C05, cockpit com KPIs zerados C13) — exatamente o oposto do que a `module-grade 71` sugere.

### Causa principal do gap (1 frase)
**O registro-mãe do ERP tem cadastro/variação/import de nível de mercado, mas carece de (a) kardex real na UI nova, (b) multiplicador/markup de preço por tabela, (c) agregação de valor/custo em estoque e (d) SPEC + teste de correção de valor — os pilares que fazem catálogo virar controle de inventário confiável.**

### Top 3 P0 pra fechar (executável)
1. **G-04 — Criar `SPEC.md` do Produto** (US-PROD-XXX): o pré-requisito mais barato (S, ~4-6h). Sem contrato, G-02/G-03 não têm âncora de teste não-tautológico. Comece por aqui.
2. **G-01 — Kardex real na tela React** (passar `movements` no render Inertia + timeline `defer`): fecha a fachada mais visível (StockHistory 47), ROI P0 pra Larissa. Esforço M.
3. **G-02 — Multiplicador/markup por tabela** (resolver ADR ARQ-0001): desbloqueia F3 do `/unificado` e o preço-por-tabela real. Esforço M ⚠️ Tier 0 valor (dupla-confirmação + antes→depois).

### Referências
- [BRIEFING.md](BRIEFING.md) · [produtos-gap.md](produtos-gap.md) · [UI-CATALOG.md](UI-CATALOG.md) · [adr/arq/0001-selling-price-multiplier.md](adr/arq/0001-selling-price-multiplier.md) (proposed)
- Screen-grade board: [SCREEN-GRADE-BOARD-2026-05-30.md](../../governance/scorecards/SCREEN-GRADE-BOARD-2026-05-30.md) (Produto agregado 71; Index 83, BulkEdit 81, Create 80, Edit 79, Show 70, SellingPrices 68, Unificado/Index 56, StockHistory 47)
- Ficha-modelo: [Sells/CAPTERRA-FICHA.md](../Sells/CAPTERRA-FICHA.md) (formato canônico)
- Session log: [2026-07-03-capterra-produto.md](../../sessions/2026-07-03-capterra-produto.md)
- Fontes concorrentes (§2): ajuda.bling.com.br · ajuda.olist.com · ajuda.omie.com.br · ajuda.contaazul.com · ajuda.hiper.com.br · linx.com.br · help.shopify.com · developers.vtex.com · help.akeneo.com · atendimento.nuvemshop.com.br

---

**Próxima revisão:** 2026-10-03 (trimestre) ou quando G-04 (SPEC) + G-01 (kardex real) fecharem.
**Onda:** standalone (Produto — programa de ondas, fila Produto→Cliente).
