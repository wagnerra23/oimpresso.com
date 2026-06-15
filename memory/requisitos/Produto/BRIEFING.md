# BRIEFING — Produto

Produto é o domínio **core** de cadastro de produto/variações do ERP (UltimatePOS herdado), **não** um módulo nWidart próprio (não existe pasta `Modules/` com esse nome). O modelo é `App\Product` (em `app/`, global scope `business_id`) e o backend é `app/Http/Controllers/ProductController.php` (~2700 linhas, UPOS canon) + `app/Http/Controllers/ProdutoUnificadoController.php`. As telas React vivem em `resources/js/Pages/Produto/`. Controlado por Wagner (owner dos charters). A camada Inertia/React **coexiste** com o Blade legacy via branch dual no header `X-Inertia` (ADR 0104 MWART) — quando ausente, cai no Blade antigo.

**Estado:** ativo (core), UI em **migração parcial** — telas React existem mas charters estão `status: draft` e as 8 estão `awaiting-smoke-browser` (nenhuma `live`). Backend Blade segue funcional como fallback.

**Capacidades REAIS (existem no código):**
- **8 Pages Inertia** com 100% de charter (`UI-CATALOG.md`): `Index` (grid lite), `Unificado/Index` (denso, 5 sub-views: produtos/insumos·BOM/tabelas de preço/histórico), `Create`, `Edit`, `Show`, `SellingPrices` (preço por tabela/price group + variações), `BulkEdit`, `StockHistory`.
- Backend (`routes/web.php`): CRUD via `Route::resource('products')`, variações (`get_product_variation_row`, `validate_variation_skus`), SKU check, quick-add, combo, mass-deactivate/mass-delete, bulk-update + bulk-update-location, selling-prices save, WooCommerce sync toggle, download Excel.
- Multi-tenant Tier 0 (`business_id` explícito por builder; `Inertia::defer` em KPIs).

**Capacidades PLANEJADAS (não construídas):**
- Charters virarem `status: live` + smoke browser — pendente aprovação Wagner de Non-Goals + Anti-hooks.
- Decisão de **unificar ou manter** `/products` (grid lite) vs `/products/unificado` (denso) — em aberto.
- Multiplicador de preço por tabela (`SellingPriceGroup.mult`) — **proposed** (ADR ARQ-0001 Produto, `mult` hardcoded 1.00 com TODO), bloqueia F3 do `/produto/unificado`.
- Middleware `can:product.view` na rota `/products/unificado` — TODO no código.

**Distinção vs `Modules/ProductCatalogue` (NÃO é o mesmo domínio):** ProductCatalogue é módulo nWidart **separado** — catálogo **público** (`/catalogue/{business_id}/{location_id}`, Blade) com **QR code** (`CatalogueQrService`). Não compartilha controller nem Pages com o domínio core Produto.

**Recebe (P6) — ADIADO (E1):** os RUNBOOKs e visual-comparisons de produto hoje moram em `memory/requisitos/Inventory/`. A migração desse material pra cá está **planejada, não executada**.

**Corpus:** `SPEC.md` ainda **não existe**. Atual: [UI-CATALOG.md](UI-CATALOG.md) (auto-gerado) + [adr/arq/0001](adr/arq/0001-selling-price-multiplier.md) (proposed).

---
**Tipo:** BRIEFING destilado (KL-E3). **Estado:** ativo (core) · UI parcial (8 telas draft, 0 live). **Fonte:** `ProductController.php` + `ProdutoUnificadoController.php` + `routes/web.php` + `Pages/Produto/` (charters) + `UI-CATALOG.md`. Verificado 2026-06-15.
