---
slug: produto
title: "Especificação funcional — Produto (cadastro core / catálogo do ERP)"
type: spec
module: Produto
status: ativo
owner: wagner
version: "1.0.0"
last_updated: "2026-07-03"
anchor_format: v1
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

## 2. User stories

> **Convenção de origem:** todas com `origin: onda-produto-passo2-2026-07-03` (parent_audit: CAPTERRA-INVENTARIO Produto).
> As US-PROD-001..011 são **retroativas** (capacidades ✅ já em prod, verificadas por Grep na FICHA). As US-PROD-020..026 são o **backlog ativo** (gaps aprovados por Wagner 2026-07-03 "ok pode fazer").

---

### US-PROD-001 · CRUD de produto (simples/variável/combo) + duplicar

**Implementado em:** `app/Http/Controllers/ProductController.php@store/update` · verificado@aef311d (2026-07-03) — `product_types` single/variable/combo (l.610-722); `Route::resource('products')`.

> owner: wagner · priority: p0 · status: done · type: story · origin: onda-produto-passo2-2026-07-03

### US-PROD-002 · Variação tam×cor com SKU auto + validação de SKU duplicado (batch)

**Implementado em:** `app/Http/Controllers/ProductController.php@getProductVariationRow/checkProductSku/validateVaritionSkus` · verificado@aef311d (2026-07-03) — rotas `get_product_variation_row` · `check_product_sku` · `validate_variation_skus` (l.413-417).

> owner: wagner · priority: p0 · status: done · type: story · origin: onda-produto-passo2-2026-07-03

### US-PROD-003 · Preço por tabela (SellingPriceGroup) por variação — matriz grupo×variação

**Implementado em:** `app/Http/Controllers/ProductController.php@addSellingPrices/saveSellingPrices` · `resources/js/Pages/Produto/SellingPrices.tsx` · verificado@aef311d (2026-07-03) — `variations.group_prices` eager-load l.1843; rota `save-selling-prices`. **Limite:** multiplicador por tabela oco → US-PROD-022.

> owner: wagner · priority: p0 · status: done · type: story · origin: onda-produto-passo2-2026-07-03

### US-PROD-004 · Estoque inicial (opening stock) por localização + alerta baixo + validade/lote

**Implementado em:** `OpeningStockController` · `app/Http/Controllers/ProductController.php` (`enable_stock`/`alert_quantity`/`expiry_period` l.643-665) · verificado@aef311d (2026-07-03) — `enable_product_expiry` l.662; `enable_lot_number` l.601.

> owner: wagner · priority: p0 · status: done · type: story · origin: onda-produto-passo2-2026-07-03

### US-PROD-005 · Importação (Excel) + import de estoque inicial + edição/ops em massa

**Implementado em:** `ImportProductsController` · `ImportOpeningStockController` · `ProductController@bulkEdit/bulkUpdate/updateProductLocation/massDeactivate/massDestroy/downloadExcel` · `resources/js/Pages/Produto/BulkEdit.tsx` · verificado@aef311d (2026-07-03).

> owner: wagner · priority: p1 · status: done · type: story · origin: onda-produto-passo2-2026-07-03

### US-PROD-006 · Atributos/PIM básico (categorias, marcas, unidades+sub, 20 custom fields, mídia, racks)

**Implementado em:** `app/Http/Controllers/ProductController.php` (`product_custom_field1..20` l.632, `get_sub_categories`/`get_sub_units`, `variations.media`, `rack_details`) · verificado@aef311d (2026-07-03).

> owner: wagner · priority: p1 · status: done · type: story · origin: onda-produto-passo2-2026-07-03

### US-PROD-007 · Multi-tenant Tier 0 (business_id global scope em toda query de produto)

**Implementado em:** `App\Product` global scope · `ProdutoUnificadoController` (`Product::where('business_id',...)`) · `App\Domain\Inventory\Models\ProductBom` (`ScopeByBusiness` + `firstOrFail` cross-tenant) · verificado@aef311d (2026-07-03) — [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md).

> owner: wagner · priority: p0 · status: done · type: story · origin: onda-produto-passo2-2026-07-03

### US-PROD-008 · Sync canal WooCommerce (toggle por produto)

**Implementado em:** `app/Http/Controllers/ProductController.php@toggleWooCommerceSync` · verificado@aef311d (2026-07-03) — rota `toggle-woocommerce-sync` (l.2682). **Limite:** sem trade-policy/multi-canal → backlog P2.

> owner: wagner · priority: p2 · status: done · type: story · origin: onda-produto-passo2-2026-07-03

### US-PROD-009 · Código de barras + etiquetas ZPL/PDF

**Implementado em:** `app/Http/Controllers/ProductController.php` (`barcode_types` l.56) · `LabelsController` (Vestuario/Etiquetas ZPL, screen-grade 74) · verificado@aef311d (2026-07-03). **Limite:** geração auto de GTIN por variação não explícita → backlog P2.

> owner: wagner · priority: p2 · status: done · type: story · origin: onda-produto-passo2-2026-07-03

### US-PROD-010 · Catálogo público + QR (venda-social)

**Implementado em:** `Modules/ProductCatalogue` · `CatalogueQrService` · verificado@aef311d (2026-07-03) — domínio **separado** do core Produto; diferencial vertical.

> owner: wagner · priority: p2 · status: done · type: story · origin: onda-produto-passo2-2026-07-03

### US-PROD-011 · BOM (Bill of Materials) — CRUD API multi-tenant

**Implementado em:** _parcial_ · `app/Http/Controllers/Inventory/ProductBomController.php` · verificado@aef311d (2026-07-03) — GET/POST/DELETE `/api/products/{id}/bom`, `business_id firstOrFail` l.33-36. **Falta:** UI drag-drop → US-PROD-025.

> owner: wagner · priority: p1 · status: doing · type: story · origin: onda-produto-passo2-2026-07-03

---

### US-PROD-020 · [G-04] Fundar a governança do Produto (este SPEC) + backfill de casos

**Implementado em:** _parcial_ · `memory/requisitos/Produto/SPEC.md` · verificado@aef311d (2026-07-03) — SPEC criado (esta 1ª versão registra 11 US retroativas + 7 ativas). **Falta:** `casos.md` por tela (contrato de não-regressão, defende gate) + revisar US retroativas com Wagner.

> owner: wagner · priority: p0 · status: doing · type: epic · estimate: 6h · origin: onda-produto-passo2-2026-07-03

**Por quê.** Sem contrato, teste de valor vira tautológico (proibicoes §5) e o `casos-gate` não tem âncora. Pré-req de US-PROD-022/024.

**Aceite:**
- [x] `SPEC.md` no formato canônico (US-PROD-NNN), passa `memory-schema` gate.
- [ ] `casos.md` das telas críticas (Create, SellingPrices, StockHistory) com UC-IDs.
- [ ] Wagner revisa as US retroativas (marca done confirmado ou abre correção).

### US-PROD-021 · [G-01] Kardex real na tela React StockHistory (deixar de linkar Blade)

**Implementado em:** _pendente_ · `resources/js/Pages/Produto/StockHistory.tsx` · `app/Http/Controllers/ProductController.php@productStockHistory`.

> owner: wagner · priority: p0 · status: todo · type: story · estimate: 10h · origin: onda-produto-passo2-2026-07-03
> blocked_by: US-PROD-020

**Por quê.** Hoje a prop `movements` fica `undefined` no render Inertia — a timeline real só existe no path `request()->ajax()` (Blade `product.stock_history_details`). A tela React é **fachada** (screen-grade 47). Larissa não audita movimento de estoque na UI nova.

**Aceite:**
- [ ] Controller passa `movements` (JSON) via `Inertia::defer` — data · operação · qty · stock_before · stock_after · ref clicável (OS/Compra/Venda).
- [ ] Cor semântica (emerald in / rose out / amber adj), append-only (sem mutação em GET).
- [ ] Hero KPIs entrada/saída 30d (charter já declara). Smoke browser biz=1. Sobe screen-grade de 47.

### US-PROD-022 · [G-02] ⚠️Tier0 · Multiplicador/markup por tabela de preço (SellingPriceGroup.mult)

**Implementado em:** _pendente_ · `app/Http/Controllers/ProdutoUnificadoController.php@tabelas` (`'mult' => 1.00` hardcoded l.183) · [ADR ARQ-0001](adr/arq/0001-selling-price-multiplier.md).

> owner: wagner · priority: p1 · status: todo · type: story · estimate: 14h · origin: onda-produto-passo2-2026-07-03
> blocked_by: US-PROD-020

**Por quê.** "Preço por tabela" aparenta funcionar mas é **1:1** (mult=1.00). Conta Azul (markup auto) e Linx (tabela por loja) têm. Desbloqueia F3 do `/unificado`.

**⚠️ Tier 0 valor** — resolver ADR ARQ-0001 (coluna `multiplier` OU cálculo via `VariationGroupPrice`); implementação exige **dupla-confirmação (2 caminhos numéricos) + tabela antes→depois + aprovação Wagner** antes de mergear. Teste E2E não-tautológico ancorado no SPEC (não na implementação).

### US-PROD-023 · [G-05] Finalizar + promover as 8 telas React do Produto (draft→live) + `can:product.view`

**Implementado em:** _parcial_ · `resources/js/Pages/Produto/*.tsx` (8 telas, todas `draft`) · rota `/products/unificado` (sem `can:product.view` — TODO no código).

> owner: wagner · priority: p1 · status: todo · type: epic · estimate: 6h · origin: onda-produto-passo2-2026-07-03
> blocked_by: US-PROD-020

**Por quê (Wagner 2026-07-03).** O React do Produto **ainda precisa ser feito**: as 8 telas existem como `.tsx` mas nenhuma é `live` (todas `awaiting-smoke-browser`, 0 `review.md`). Unificado 56 + StockHistory 47 puxam a nota. Falta o gate `can:product.view` no `/unificado`.

**Aceite (por tela):**
- [ ] `can:product.view` na rota `/products/unificado`.
- [ ] Trocar native `<select>`/`<input>` por `@/Components/ui`; remover blue-leak (sky-700) e stone cru; PageHeader + token roxo.
- [ ] Smoke browser biz=1 + `review.md` → promover charter `draft`→`live`.
- [ ] Priorizar as de menor nota (StockHistory 47 → via US-PROD-021; Unificado 56; SellingPrices 68).

### US-PROD-024 · [G-03] ⚠️Tier0 · Custo médio + valor/custo em estoque — SPIKE de descoberta primeiro

**Implementado em:** _pendente_ · `app/Http/Controllers/ProdutoUnificadoController.php` (KPIs `margem_media`/`sem_giro`/`stockQty` zerados, TODO).

> owner: wagner · priority: p2 · status: todo · type: epic · estimate: 24h · origin: onda-produto-passo2-2026-07-03
> blocked_by: US-PROD-020

**Por quê (Wagner 2026-07-03: "estudar melhor o custo médio, muita coisa já tem pronta").** NÃO é greenfield — o UltimatePOS já calcula custo por compra. Antes de construir agregação, **mapear a máquina de custo que já roda**.

**Fase 1 — SPIKE de descoberta (obrigatória antes de codar):**
- [ ] Inventariar o que já existe: `default_purchase_price`/`dpp_inc_tax` por variação, `VariationLocationDetails` (qty_available), fluxo de custo na entrada de compra (`PurchaseController`/`TransactionUtil`), relatórios `stock-report`/`stock-by-sell-price`/`get-opening-stock`.
- [ ] Documentar: custo médio já é recalculado na compra? Onde? Qual a fonte-de-verdade de "valor em estoque"? Registrar em `casos.md` ou nota.

**Fase 2 — só depois do spike:**
- [ ] Expor agregação valor/custo em estoque + margem média nos KPIs do `/unificado` (reusa o que já existe; não recalcula errado).
- [ ] **⚠️ Tier 0 estoque/valor** — dupla-confirmação (2 caminhos) + antes→depois + aprovação Wagner. Medir demanda (ADR 0105) antes de investir as ~20-30h.

### US-PROD-025 · [G-06] UI de BOM drag-drop + baixa-de-componente do kit no PDV

**Implementado em:** _pendente_ · UI de `App\Domain\Inventory\Models\ProductBom` (API já existe — US-PROD-011).

> owner: wagner · priority: p2 · status: todo · type: story · estimate: 14h · origin: onda-produto-passo2-2026-07-03
> blocked_by: US-PROD-011

**Por quê.** `ProductBom` tem CRUD API mas sem UI. Bling tem kit com estoque de componente. Comprovar baixa-de-componente do kit no PDV.

### US-PROD-026 · Fornecedores/cotação por produto (melhor preço no drawer)

**Implementado em:** _pendente_ · `ProdutoUnificadoController::insumos()` (`fornecedor => null`, TODO).

> owner: wagner · priority: p3 · status: todo · type: story · estimate: 12h · origin: onda-produto-passo2-2026-07-03
> blocked_by: US-PROD-020

**Por quê.** Feature do drawer rico do mockup Cowork ([produtos-gap.md](produtos-gap.md) Parte 6): melhor cotação por fornecedor destacada. Único ❌ AUSENTE do inventário.

---

## 3. Backlog fora do batch (sem sinal ainda — ADR 0105)

Viram US quando houver cliente/sinal ou drift de métrica:
- **PIM avançado** — families/atributos tipados/asset manager (Akeneo-like) vs os 20 custom fields atuais.
- **Multi-canal/trade-policy** — preço por canal, sync marketplace (VTEX-like) além do toggle WooCommerce.
- **GTIN auto por variação** — geração automática de código de barras (Hiper-like).
- **`Inertia::defer` no `/unificado`** — hoje sem defer, TODOs de N+1/cache.

## 4. Refs

- [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (capacidade 61/100) · [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) (✅6/🟡11/❌1) · [BRIEFING.md](BRIEFING.md) · [produtos-gap.md](produtos-gap.md) · [UI-CATALOG.md](UI-CATALOG.md)
- [adr/arq/0001-selling-price-multiplier.md](adr/arq/0001-selling-price-multiplier.md) (proposed — US-PROD-022)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) · [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0089](../../decisions/0089-capterra-driven-module-evolution.md)
- Board screen-grade: [SCREEN-GRADE-BOARD-2026-05-30.md](../../governance/scorecards/SCREEN-GRADE-BOARD-2026-05-30.md)
- Plano da onda: [template-onda-modulo.md](../_Governanca/programa-ondas/template-onda-modulo.md)
