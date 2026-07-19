---
module: Produto
status: parcial
status_nota: "core UPOS ativo em prod (Blade legacy, ROTA LIVRE diário); UI React 8 telas draft, 0 live"
updated_at: "2026-07-18"
owner: W
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0104-processo-mwart-canonico-unico-caminho", "0190-primary-button-roxo-universal-295"]
---

# BRIEFING — Produto

Produto é o domínio **core** de cadastro de produto/variações do ERP (UltimatePOS herdado), **não** um módulo nWidart próprio (não existe pasta `Modules/` com esse nome). O modelo é `App\Product` (em `app/`, global scope `business_id`) e o backend é `app/Http/Controllers/ProductController.php` (~2.729 linhas, UPOS canon) + `app/Http/Controllers/ProdutoUnificadoController.php` (222 LOC, cheio de TODOs). As telas React vivem em `resources/js/Pages/Produto/`. Controlado por Wagner (owner dos charters). A camada Inertia/React **coexiste** com o Blade legacy via branch dual no header `X-Inertia` (ADR 0104 MWART) — quando ausente, cai no Blade antigo.

**Estado:** ativo (core, em prod via Blade), UI em **migração parcial** — as 8 telas React existem mas nenhuma é `live` (charters `status: draft`, todas `awaiting-smoke-browser`). Backend Blade segue funcional como fallback. Benchmark de capacidade **61/100** (CAPTERRA-FICHA); `module-grade 71` mede só UX/DS das 8 telas.

**Capacidades REAIS (existem no código):**
- **8 Pages Inertia** com 100% de charter: `Index` (grade 83), `Create` (80), `Edit` (79), `Show` (70), `SellingPrices` (68 — matriz preço × tabela × variação), `BulkEdit` (81), `StockHistory` (**47 — fachada**: `movements` fica `undefined` no render, timeline real só no Blade), `Unificado/Index` (56, cockpit denso 5 sub-views).
- Backend (`routes/web.php`): CRUD `Route::resource('products')` (single/variable/combo + duplicar), variações + SKU auto/validação batch, quick-add, opening stock, bulk/mass-ops + import Excel, selling-prices save, toggle WooCommerce, download Excel, BOM CRUD API (`Inventory/ProductBomController`, sem UI).
- **Trio da tabela de preço FECHADO** (#4300): `SellingPrices.casos.md` com 4 UCs (UC-PTAB-01..04) **rodando e passando** na lane `Estoque · MySQL` (biz=1+biz=2 semeados) + `tests/Feature/Produto/TabelaPrecoContratoTest.php`. Dois nasceram **vermelhos** e viraram correção Tier 0 no mesmo PR: `saveSellingPrices` agora valida `price_group_id` contra o business (o "reusa guard" do CU-PROD-10 era **falso** — `VariationGroupPrice` não tem global scope; `price_group` alheio gravava linha cross-tenant).
- Multi-tenant Tier 0 (`business_id` explícito; `firstOrFail` cross-tenant → 404 no GET). ⚠️ No POST `saveSellingPrices` o cross-tenant volta **302** (exceção engolida por `catch` genérico) — não vaza, mas o proxy 404 do charter é falso; decisão [W] pendente.

**Aba "Preço especial" — DESENHADA, ainda NÃO em código (#4403):**
- É **protótipo F1 navegável** (`prototipo-ui/cowork/produto-preco-especial/`, `status: F1-commit-only`) + `SellingPrices.charter.md` promovido a **v3** (draft), após **8 cortes de [F]** (2026-07-16). Modelo novo: **a lista de preço é REGRA (%), a grade é CALCULADA, a célula é EXCEÇÃO** — 60 células (20 variações × 3 listas) viram 1 regra + poucas exceções conscientes. Cobre 2 modos (regra % vs preço manual), preço **base por variação**, faixa de quantidade (linha esparsa dentro da tabela) e 0/1/2/3+ eixos de grade. Âncora empírica: pesquisa de mercado 2026-07-16 (13 sistemas — Shopify B2B/Tiny/Bling/Odoo convergentes).
- ⚠️ **Ainda NÃO implementado:** `SellingPrices.tsx` hoje é o modelo **v2 célula-a-célula** (pré-preenche `price: 0` no 0-row, `price_type` por célula — sem regra/faixa/base/modos). O v3 vive em charter + pino, **não** em `.tsx`.

**Capacidades PLANEJADAS (não construídas) — batch US-PROD-020..027 (SPEC 2026-07-03, "ok pode fazer"):**
- **US-PROD-023** — finalizar + promover as 8 telas `draft`→`live` + `can:product.view` no `/products/unificado` (ainda TODO no código). 0 telas em prod oficialmente.
- **US-PROD-022** ⚠️Tier0 — multiplicador/markup por tabela (`SellingPriceGroup.mult` **hardcoded 1.00**, `ProdutoUnificadoController:186` TODO; ADR ARQ-0001 `proposed`). Sem ele "preço por tabela" é 1:1 — e a aba Preço especial cai de volta na digitação célula a célula (a regra-mãe não tem coluna onde morar: `selling_price_groups` só tem name/description/business_id/is_active).
- **US-PROD-021** — Kardex real na StockHistory (sai de fachada; sobe grade 47).
- **US-PROD-024** ⚠️Tier0 — custo médio + valor em estoque (SPIKE de descoberta primeiro; máquina de custo já roda parcial).
- **US-PROD-025/026** — UI BOM drag-drop; fornecedor/cotação no drawer (`insumos()` retorna `fornecedor => null`).
- **US-PROD-027** [V0] — TEST-ONLY: cravar que preço 0 em tabela é inerte só por sorte do `!empty()` do PHP (vira UC-PTAB-05). Não muda comportamento.

**Distinção vs `Modules/ProductCatalogue` (NÃO é o mesmo domínio):** ProductCatalogue é módulo nWidart **separado** — catálogo **público** (`/catalogue/{business_id}/{location_id}`, Blade) com **QR** (`CatalogueQrService`). Não compartilha controller nem Pages com o core Produto.

**Duas verticais no cadastro (SDD §1.0):** balcão/varejo ✅ maduro; **comunicação visual** (preço por m² via `OrcamentoCalculator` em `Modules/ComunicacaoVisual`, hoje **desconectado** do `App\Product`) e **oficina** (peça com aplicação por veículo/OEM/fornecedor) ainda 🟡 **não expressas** no core — maior retorno do roadmap, medido por sinal (ADR 0105).

**Corpus (mudou desde 2026-06-15 — SPEC/SDD/casos agora EXISTEM):**
- [SPEC.md](SPEC.md) (v1.0.0, 2026-07-03 — US-PROD-020..027) · [SDD-tela-cadastro-produto-v1.0.md](SDD-tela-cadastro-produto-v1.0.md) (v1.0.1, 2026-07-15 — mapa de cima, 2 verticais, CU-PROD-01..12; #4319 fez o CU-PROD-10 parar de afirmar o que não mede) · [SellingPrices.casos.md](../../../resources/js/Pages/Produto/SellingPrices.casos.md) (4 UCs verdes) · [PROTOTIPO-preco-especial.md](PROTOTIPO-preco-especial.md).
- [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (61/100) · [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) (✅6/🟡11/❌1) · [UI-CATALOG.md](UI-CATALOG.md) (auto, stale 2026-05-17) · [adr/arq/0001](adr/arq/0001-selling-price-multiplier.md) (proposed) · RUNBOOKs por tela em [`_telas/`](_telas/) (já migrados — a nota antiga "recebe de Inventory" está resolvida).

---
**Tipo:** BRIEFING destilado (KL-E3). **Estado:** parcial — core ativo (Blade, em prod) · UI React 8 telas draft, 0 live. **Fonte:** `ProductController.php` (~2.729 LOC) + `ProdutoUnificadoController.php` + `SellingPrices.tsx`/`.charter.md` v3 + `routes/web.php` + SPEC/SDD/casos.
**Atualizado:** 2026-07-18 — refresh de frescor briefing↔código [CC]. Destaque: aba **Preço especial** (protótipo F1 + charter v3 regra+exceção — ainda NÃO em `.tsx`, o `SellingPrices.tsx` segue v2 célula-a-célula) + **trio da tabela de preço fechado** (#4300, UC-PTAB-01..04 verdes na lane Estoque·MySQL) + SPEC/SDD/casos agora existem (o BRIEFING de 2026-06-15 dizia "SPEC não existe"). Verificado por Grep@código 2026-07-18.
