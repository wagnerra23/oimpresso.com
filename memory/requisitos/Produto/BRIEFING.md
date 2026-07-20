---
module: Produto
status: parcial
status_nota: "core UPOS ativo em prod (Blade legacy, ROTA LIVRE diário); UI React em migração parcial (0 telas live); inclui âncoras de estoque + restaurant"
updated_at: "2026-07-20"
owner: W
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0104-processo-mwart-canonico-unico-caminho", "0121-oimpresso-modular-especializado-por-vertical", "0190-primary-button-roxo-universal-295"]
---

# BRIEFING — Produto

Produto é o **registro-mãe do ERP** (UltimatePOS herdado) — o insumo de preço/custo/estoque que Vendas, Compras, Fiscal e Produção consomem. **Não** é um módulo nWidart próprio (não há pasta `Modules/Produto`): o modelo é `App\Product` (global scope `business_id`), o motor é `app/Utils/ProductUtil.php`, e a superfície é um **sub-sistema** (cadastro + variação + combo/BOM + tabelas de preço + estoque inicial + import + etiquetas) — não uma tela. A camada Inertia/React **coexiste** com o Blade legacy via header `X-Inertia` (ADR 0104 MWART) — ausente, cai no Blade. Owner do módulo: Wagner.

> **Âncoras relacionadas (Wagner 2026-07-20):** movimentação de estoque (`StockAdjustment`, `StockTransfer`) e modifiers de restaurante (`ProductModifierSet`) têm responsabilidade adjacente **mas dependem do produto** — ficam neste briefing como âncoras, não como core de cadastro.

## Origem / linhagem (por que existem 3 gerações)

O cadastro tem 3 gerações, e a paridade da migração mira **2 alvos**:

1. **OfficeImpresso (Delphi WR)** — ERP legado da WR Sistemas (26 anos, setor gráfico). Cadastro rico: fórmulas m², composição multi-nível. É o que as gráficas clientes usam hoje; o oimpresso quer substituí-lo.
2. **oimpresso Blade (fork UltimatePOS, [ADR 0001](../../decisions/0001-estender-ultimatepos-opcao-c.md))** — em vez de continuar o Delphi, a WR forkou o UltimatePOS; o cadastro Blade é herdado dele (mais simples que o Delphi). Roda em prod hoje (ROTA LIVRE, diário).
3. **oimpresso React** — migração da UI Blade→React (MWART), em curso.

**A paridade tem 2 alvos:** não regredir o Blade (mínimo) **e** alcançar o Delphi (features que nem o Blade tem — o retorno das gráficas). Timeline completa: [`HISTORIA-LINHAGEM.md`](../../HISTORIA-LINHAGEM.md).

## Mapa do módulo — áreas × estado da migração Blade→React

> **Sem números que apodrecem.** Grade, status `draft/live` e % de migração por tela **não são digitados aqui** — vêm dos geradores: [UI-CATALOG.md](UI-CATALOG.md) (auto-gerado do filesystem) + `module:grade`. Estado do trio (charter/casos/teste): `scripts/casos-coverage-baseline.json` (do CI). Este mapa fixa só a **estrutura** (quais áreas, onde vivem, qual o achado) — recibo: medido 2026-07-20 via `Inertia::render` por controller.

**Eixo cadastro — `Pages/Produto/*` (React):**

| Área | Controller | Blade | React | Achado |
|---|---|---|---|---|
| Listagem | `ProductController@index` | `index` | `Produto/Index` | — |
| Cadastro | `@create/store` | `create` + partials | `Produto/Create` | ⚠️ **sem preço/imagem** (paridade) |
| Edição | `@edit/update` | `edit` | `Produto/Edit` | ⚠️ tem `image`, mas Create não (divergem) |
| Detalhe | `@show` | `show` | `Produto/Show` | — |
| Tabela de preço | `@saveSellingPrices` | `add-selling-prices` | `Produto/SellingPrices` | ✅ **trio completo** (#4300) |
| Bulk edit | `@bulkEdit` | `bulk-edit` | `Produto/BulkEdit` | — |
| Kardex | `@productStockHistory` | `stock_history` | `Produto/StockHistory` | 🔴 **fachada** (`movements` undefined) |
| Cockpit | `ProdutoUnificadoController` | — | `Produto/Unificado/Index` | KPIs zerados |

**Eixo gestão de catálogo — ainda SÓ BLADE (sem tela React):**

`OpeningStockController` (estoque inicial) · `ImportProductsController` + `ImportOpeningStockController` (import) · `LabelsController` (etiquetas ZPL/PDF) · `SellingPriceGroupController` (config das tabelas de preço) · `VariationTemplateController` (modelos de grade) · `Inventory/ProductBomController` (composição/BOM — API-only, UI = US-PROD-025).

**Âncoras relacionadas (adjacentes, dependem do produto — Wagner 2026-07-20):**

`StockAdjustmentController` → `StockAdjustment/{Index,Create}` (React, namespace próprio) · `StockTransferController` → `StockTransfer/{Index,Create}` (React) · `Restaurant/ProductModifierSetController` (só Blade).

## Superfície de código (recibo: varredura 2026-07-20 — cada arquivo é um link)

- **Controllers** — [`ProductController`](../../../app/Http/Controllers/ProductController.php) (cadastro) · [`ProdutoUnificadoController`](../../../app/Http/Controllers/ProdutoUnificadoController.php) (cockpit) · [`Inventory/ProductBomController`](../../../app/Http/Controllers/Inventory/ProductBomController.php) · [`OpeningStockController`](../../../app/Http/Controllers/OpeningStockController.php) · [`ImportProductsController`](../../../app/Http/Controllers/ImportProductsController.php) · [`ImportOpeningStockController`](../../../app/Http/Controllers/ImportOpeningStockController.php) · [`LabelsController`](../../../app/Http/Controllers/LabelsController.php) · [`SellingPriceGroupController`](../../../app/Http/Controllers/SellingPriceGroupController.php) · [`VariationTemplateController`](../../../app/Http/Controllers/VariationTemplateController.php). Âncoras: [`StockAdjustmentController`](../../../app/Http/Controllers/StockAdjustmentController.php) · [`StockTransferController`](../../../app/Http/Controllers/StockTransferController.php) · [`Restaurant/ProductModifierSetController`](../../../app/Http/Controllers/Restaurant/ProductModifierSetController.php).
- **Motor:** [`app/Utils/ProductUtil.php`](../../../app/Utils/ProductUtil.php) (`createSingleProductVariation`, `generateProductSku`, `num_uf` [V0]).
- **Models:** [`Product`](../../../app/Product.php) · [`Variation`](../../../app/Variation.php) · [`ProductVariation`](../../../app/ProductVariation.php) · [`VariationGroupPrice`](../../../app/VariationGroupPrice.php) · [`VariationLocationDetails`](../../../app/VariationLocationDetails.php) · [`SellingPriceGroup`](../../../app/SellingPriceGroup.php) · [`VariationTemplate`](../../../app/VariationTemplate.php) · [`ProductRack`](../../../app/ProductRack.php) · [`ProductBom`](../../../app/Domain/Inventory/Models/ProductBom.php).
- **Views Blade:** [`resources/views/product/`](../../../resources/views/product/) (telas + partials de form/variação/combo).
- **Rotas web:** [`routes/web.php`](../../../routes/web.php) (resource + AJAX variação/SKU + selling-prices + opening-stock + bulk + import + BOM). ⚠️ [`routes/api.php`](../../../routes/api.php) **raiz** não tem produto.
- **API REST — módulo `Connector`:** [`Modules/Connector/Routes/api.php`](../../../Modules/Connector/Routes/api.php) → `/connector/api/product` (index/show), `variation/{id?}`, `selling-price-group`, `product-stock-report`, `new_product`. Controllers em [`Modules/Connector/Http/Controllers/Api/`](../../../Modules/Connector/Http/Controllers/Api/) (`ProductController`, `ProductSellController`, `Brand/Category/Unit`). Transformers `ProductResource`/`VariationResource`/`NewProductResource`. Request `StoreProductApiRequest`.
- **Client:** [`public/js/product.js`](../../../public/js/product.js) (cálculo dpp↔markup↔dsp [V0], quick-add) — **não migrado**.

> **Critério de link (pra não voltar a ficar inconsistente):** todo **arquivo/pasta-fonte** aqui na Superfície e no Índice é **link** (o leitor abre a fonte); nas **tabelas do mapa** os nomes são só **menção** (backtick) — o controller já está linkado uma vez acima, linkar cada célula polui. O gate `charter-refs` valida que todos resolvem.

## Estado — o que está fechado e o que falta

- **Trio da tabela de preço FECHADO** (#4300) + **trio do cadastro FECHADO** (#4417 — achado no caminho: duplicar alheio dava 500 → `findOrFail`). Estado vivo do trio por tela: `casos-coverage`.
- **Multi-tenant Tier 0** — 404 cross-tenant no GET; `store()` valida FKs de insumo (categoria/marca/unidade) contra o business (**#4554** — resolveu o UC-PCAD-05, CT100-verificado). Aberto: POST `saveSellingPrices` volta 302 (exceção engolida por `catch` genérico — não vaza, decisão [W] pendente).
- **Backlog** — batch US-PROD-020..027 (ver [SPEC.md](SPEC.md)): promover telas draft→live (023), regra de tabela inteira (022, ⚠️Tier0 — **não** é "multiplicador oco": preço por tabela funciona, gap = granularidade célula→grupo, corrigido #4464), kardex real (021), custo médio/valor em estoque (024 ⚠️Tier0), BOM UI + fornecedor (025/026), preço-0 inerte (027 [V0]).
- **Não têm US nem doc ainda:** migração das áreas só-Blade + preço no `Create.tsx` (P0 da paridade — bloco de preço + `product.js`).
- **Aba "Preço especial"** (#4403) — desenhada (protótipo F1 + charter v3 regra/exceção), **não em código**; `SellingPrices.tsx` segue v2 célula-a-célula.

## Duas verticais (SDD §1.0)
Balcão ✅; comunicação visual (m² via `OrcamentoCalculator` em `Modules/ComunicacaoVisual`, desconectado do `App\Product`) e oficina 🟡 não expressas no core — maior retorno, medido por sinal (ADR 0105).

## vs Modules/ProductCatalogue
Módulo nWidart separado — catálogo público + QR. Não compartilha controller/Pages.

## Índice — TODOS os artefatos (a porta aponta pros arquivos, não repete o conteúdo)

**A. Estratégia / visão** — [SDD-tela-cadastro-produto-v1.0.md](SDD-tela-cadastro-produto-v1.0.md) (CU-PROD-01..12 ⚠️ só eixo cadastro) · [SPEC.md](SPEC.md) (US-PROD-020..027)

**B. Origem / migração / paridade** — [HISTORIA-LINHAGEM.md](../../HISTORIA-LINHAGEM.md) · [PARIDADE-charter-vs-legado.md](PARIDADE-charter-vs-legado.md) (Delphi×Blade×React) · [ANTI-REGRESSAO-cadastro-produto-legacy.md](ANTI-REGRESSAO-cadastro-produto-legacy.md) + [-variacao-legacy.md](ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md) (fonte bruta)

**C. Benchmark** — [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) · [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) (a nota vive lá, não aqui)

**D. Fonte de design (protótipos)** — [PROTOTIPO-preco-especial.md](PROTOTIPO-preco-especial.md) · [produtos-gap.md](produtos-gap.md) (mockup "Picker Mecânica") · [`prototipo-ui/cowork/produto-preco-especial/`](../../../prototipo-ui/cowork/produto-preco-especial/) · [`prototipo-ui/cowork/produtos-page.jsx`](../../../prototipo-ui/cowork/produtos-page.jsx)

**E. Catálogo / estado (auto-gerado — a fonte que não apodrece)** — [UI-CATALOG.md](UI-CATALOG.md) (telas + grades + status draft/live) · [`scripts/casos-coverage-baseline.json`](../../../scripts/casos-coverage-baseline.json) + [`scripts/casos-test-results.json`](../../../scripts/casos-test-results.json) (estado do trio, do CI)

**F. Por tela — trio + operacional** — [`resources/js/Pages/Produto/`](../../../resources/js/Pages/Produto/) (`*.charter.md` lei + `*.casos.md` contrato ao lado de cada `.tsx`) · [`_telas/`](_telas/) (RUNBOOK + visual-comparison por tela) · [_telas/produto-index-setor-matrix.md](_telas/produto-index-setor-matrix.md)

**G. Testes** — [`tests/Feature/Produto/`](../../../tests/Feature/Produto/) — contrato: `CadastroProdutoContratoTest` · `TabelaPrecoContratoTest` · `FormacaoPrecoParidadeLegadoTest`; + `Wave2*` (⚠️ tautológicos, a substituir).

**H. ADR** — [adr/arq/0001-selling-price-multiplier.md](adr/arq/0001-selling-price-multiplier.md) (proposed + Errata #4464)

---
**Atualizado:** 2026-07-20 [M+CC] — expandido de "8 telas" pra módulo inteiro (cadastro + gestão só-Blade + âncoras estoque/restaurant por decisão Wagner) + API REST no Connector + origem/linhagem + índice completo A-H. **Poda anti-apodrecimento:** grades, status `draft/live`, % de migração e contagens exatas **saíram do corpo** — apontam pros geradores (UI-CATALOG, casos-coverage, module:grade) que se atualizam sozinhos. O que fica é estrutura + achados + recibo datado; frescor vigiado por `briefing-code-staleness`. Verificado por varredura view/controller/utils/rotas/connector/prototipo-ui/tests 2026-07-20.
