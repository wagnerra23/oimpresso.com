# CAPTERRA-INVENTÁRIO — Produto

> Gerado pela skill `comparativo-do-modulo` (v2) em 2026-07-03 — **Passo 2** da onda standalone do programa de ondas ([template](../_Governanca/programa-ondas/template-onda-modulo.md) · fila Produto→Cliente).
> Fontes: [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (nota capacidade **61/100**) + código real (`app/Http/Controllers/ProductController.php`, `ProdutoUnificadoController.php`, `Inventory/ProductBomController.php`, `routes/web.php`, `resources/js/Pages/Produto/`) + [BRIEFING.md](BRIEFING.md) + [produtos-gap.md](produtos-gap.md) + board [SCREEN-GRADE-BOARD-2026-05-30.md](../../governance/scorecards/SCREEN-GRADE-BOARD-2026-05-30.md).
> ADR: [0089](../../decisions/0089-capterra-driven-module-evolution.md) (Capterra-driven) + [0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) (tests biz=1).
>
> ⚠️ **Produto é core UltimatePOS, NÃO módulo nWidart** (não há pasta própria em `Modules/` com esse nome) — a auditoria cruza a FICHA com o código core em `app/` + Pages Inertia, não com um módulo nWidart.
> ⚠️ **Não existe `SPEC.md` de Produto ainda** (é o gap G-04). Enquanto não existir, a US aprovada NÃO pode ser apendada a SPEC — a 1ª task do batch **cria** o SPEC.

## Resumo

- ✅ **APROVADO: 6** de 18 (fundação de cadastro sólida)
- 🟡 **PARCIAL: 11** (a maioria dos gaps de valor/estoque cai aqui)
- ❌ **AUSENTE: 1**
- **Score de capacidade: 61/100** (abaixo da `module-grade 71` de UX — ver §8 da FICHA: a tela bonita esconde buracos de valor/estoque)
- **Score médio dos gaps priorizáveis: P0-P1** (kardex, multiplicador de preço, SPEC, permissão)

## Inventário detalhado (18 capacidades — eixo features/UX/automação)

| ID | Capacidade | Score | Status | Evidência (código) | Próximo passo |
|---|---|:-:|:-:|---|---|
| C01 | Variação tam×cor + matriz SKU + geração auto + validação duplicado batch | P0 | ✅ APROVADO | `ProductController@getProductVariationRow/checkProductSku/validateVaritionSkus` (rotas l.413-417) | — |
| C03 | Estoque inicial (opening stock) + por local + alerta baixo + validade/lote | P0 | ✅ APROVADO | `OpeningStock*` + `alert_quantity`/`enable_product_expiry`/`enable_lot_number` | — |
| C06 | Multi-tenant Tier 0 (`business_id` global scope) | P0 | ✅ APROVADO | `App\Product` scope + `ProductBom ScopeByBusiness` + `firstOrFail` cross-tenant | — (diferencial) |
| C08 | Import Excel + bulk-edit/update + mass-deactivate/delete + download | P1 | ✅ APROVADO | `Import*Controller` + `ProductController@bulkEdit/massDestroy` + `BulkEdit.tsx` | — |
| C12 | UX cadastro rápido (quick-add, densidade 1280px, dedup) | P1 | ✅ APROVADO | `quick_add`/`save_quick_product` + Create(80)/Edit(79) | — |
| C16 | Catálogo público / QR (venda-social) | P2 | ✅ APROVADO | `Modules/ProductCatalogue` + `CatalogueQrService` (domínio separado) | — (diferencial vertical) |
| C02 | Preço por tabela + **multiplicador/markup por tabela** | P0 | 🟡 PARCIAL | `group_prices` por variação existe, mas `SellingPriceGroup.mult` **hardcoded 1.00** ([ADR ARQ-0001](adr/arq/0001-selling-price-multiplier.md)) | **G-02** |
| C04 | **Kardex / histórico de movimento (timeline real)** | P0 | 🟡 PARCIAL | backend `getVariationStockHistory` (Blade); tela React `StockHistory` **NÃO recebe `movements`** (grade 47) | **G-01** |
| C05 | Custo médio + valor/custo em estoque (agregação) | P0 | 🟡 PARCIAL | `default_purchase_price` por variação; agregação/CMC **ausente** (`margem_media=0` TODO) | **G-03** |
| C07 | Combo/kit + BOM (estrutura de componentes) | P1 | 🟡 PARCIAL | combo `type=='combo'` + `ProductBom` CRUD API real; **UI drag-drop pendente (US-INV-002)** | **G-06** |
| C09 | Atributos/PIM (categorias, marca, unidades, 20 custom fields, mídia) | P1 | 🟡 PARCIAL | categoria/marca/unidade+sub + 20 custom fields + media; sem families/atributos tipados nem asset manager | backlog P2 |
| C10 | Sync canal e-commerce/marketplace | P1 | 🟡 PARCIAL | toggle WooCommerce por produto; sem trade-policy/multi-canal nem preço por canal | backlog P2 |
| C11 | Código de barras / etiqueta (GTIN auto, ZPL) | P1 | 🟡 PARCIAL | `barcode_types` + etiquetas ZPL/PDF (Etiquetas 74); geração auto GTIN por variação não explícita | backlog P2 |
| C13 | Catálogo denso / cockpit (`/unificado` 5 sub-views) | P2 | 🟡 PARCIAL | 5 sub-views, mas **KPIs zerados** (populares/margem/sem_giro TODO), native `<select>`, blue-leak (grade 56) | **G-03** (KPIs) + design |
| C14 | Perceived perf (defer, skeleton) | P2 | 🟡 PARCIAL | `Inertia::defer` na Index; `Unificado` sem defer, TODOs N+1 | backlog P2 |
| C15 | UX/DS estado-da-arte (tokens, PageHeader, empty states) | P2 | 🟡 PARCIAL | Index 83/BulkEdit 81/Create 80 fortes; Unificado 56 + StockHistory 47 puxam pra baixo | **G-05** (charters draft→live) |
| C17 | Permissão granular na tela de catálogo | P3 | 🟡 PARCIAL | `Route::resource` tem gate; `/products/unificado` **SEM `can:product.view`** (TODO no código) | **G-05** |
| C18 | Fornecedores/cotação por produto (melhor preço) | P3 | ❌ AUSENTE | `insumos()` retorna `fornecedor => null` (TODO); sem cotação | backlog P3 |

## Tasks propostas (aguardando aprovação [W] — publication-policy)

> Priorizadas P0→P3. Esforço em horas IA-pair (ADR 0106). ⚠️ = toca valor/estoque → **regra-mestre Tier 0** (dupla-confirmação + antes→depois + aprovação) antes de mergear.

| # | Prio | Task | Cap/Gap | Esforço | Evidência / porquê |
|---|:-:|---|---|---|---|
| 1 | **P0** | **Criar `SPEC.md` do Produto** (US-PROD-001..) — contrato de capacidade do core-dos-cores | G-04 | S ~4-6h | Sem SPEC, teste vira tautológico (proibicoes §5); é o pré-req barato que destrava os testes de valor. **Começa aqui.** |
| 2 | **P0** | **Kardex real na tela React `StockHistory`** — passar `movements` no render Inertia + timeline JSON `defer` (deixa de linkar Blade) | G-01 / C04 | M ~8-12h | `StockHistory.tsx` prop `movements` fica `undefined`; grade 47 "fachada"; Larissa não audita estoque hoje |
| 3 | **P1** | ⚠️ **Multiplicador/markup por tabela de preço** — resolver `SellingPriceGroup.mult` (ADR ARQ-0001) | G-02 / C02 | M ~10-16h | `mult=1.00` hardcoded; preço-por-tabela aparenta funcionar mas é 1:1; desbloqueia F3 `/unificado`. **Tier 0 valor** |
| 4 | **P1** | **`can:product.view` em `/products/unificado` + promover 8 charters draft→live** (smoke browser biz=1) | G-05 / C17+C15 | S ~3-6h | TODO no código (rota sem gate); 8 telas `awaiting-smoke-browser`, 0 `live` |
| 5 | **P2** | ⚠️ **Agregação valor/custo em estoque + custo médio** no `/unificado` (KPIs margem/sem_giro/stockQty hoje zerados) | G-03 / C05+C13 | L ~20-30h | totalizadores de inventário do mockup Cowork (produtos-gap.md); **Tier 0 estoque/valor** — medir + dupla-confirmação |
| 6 | **P2** | **UI de BOM drag-drop** (US-INV-002) + baixa-de-componente do kit no PDV comprovada | G-06 / C07 | M ~12-16h | `ProductBom` API existe, UI não; kit sem baixa-de-componente comprovada |
| 7 | **P3** | **Fornecedores/cotação por produto** (melhor preço no drawer) | C18 | M ~10-14h | `insumos()` retorna `fornecedor => null`; feature do drawer rico do mockup Cowork |

**Fora do batch (backlog P2/P3 sem sinal ainda — ADR 0105):** C09 PIM avançado (families/atributos tipados/asset manager), C10 multi-canal/trade-policy, C11 GTIN auto por variação, C14 defer no `/unificado`. Viram US quando houver cliente/sinal.

## Notas de governança

- **Não criei nenhuma task** — aguardo [W] aprovar quais (ex: "aprova 1,2,4" ou "todas P0+P1"). Só então `tasks-create` no MCP (module:Produto, parent metadata `parent_audit=CAPTERRA-INVENTARIO Produto`).
- **US só apendam a SPEC depois** que a task #1 (G-04) criar o `SPEC.md`. Antes disso, as US ficam registradas só nas tasks MCP.
- Tasks 3 e 5 são **Tier 0 valor/estoque** — a implementação exige dupla-confirmação (2 caminhos) + tabela antes→depois + aprovação humana antes de mergear ([proibicoes](../../proibicoes.md) "REGRA MESTRE").

---

**Próxima revisão:** quando o batch aprovado virar cycle ativo, OU na próxima passada da onda (Passo 3 — régua por tela).
**Onda:** standalone (Produto — programa de ondas, fila Produto→Cliente). Passo 1 = [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md); Passo 2 = este inventário.
