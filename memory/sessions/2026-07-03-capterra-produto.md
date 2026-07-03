---
data: 2026-07-03
tipo: session-log
agente: capterra-senior
modulo: Produto
onda: standalone (programa de ondas, fila Produto→Cliente)
nota_capacidade: 61
artefatos:
  - memory/requisitos/Produto/CAPTERRA-FICHA.md
  - memory/sessions/2026-07-03-capterra-produto.md
---

# Session log — Capterra Produto (capacidade de catálogo)

## TL;DR

- **Nota de capacidade: 61/100** — abaixo do topo BR (Tiny/Bling ~78, Linx Microvix moda ~80) e do teto global (Shopify/VTEX/Akeneo ~85, mas sem fiscal BR / não Tier 0).
- **Gap ~-18 pts pro topo BR** vem de: kardex de fachada (C04), multiplicador de preço por tabela oco (C02), agregação valor/custo em estoque ausente (C05), cockpit `/unificado` com KPIs zerados (C13).
- **A `module-grade 71` (UX/DS das 8 telas) esconde a capacidade real** — ver 5 achados adversariais §8.
- **Onde ganha do mercado:** multi-tenant Tier 0 (C06), variação tam×cor + SKU auto + validação batch (C01), catálogo público/QR (C16), import+bulk (C08).
- **Fragilidade estrutural nº 1:** Produto é o core-dos-cores (alimenta Sells+Compras+Fiscal) e é o **único sem `SPEC.md`** no programa.
- **Top 3 P0:** G-04 (criar SPEC, S) → G-01 (kardex real na tela React, M) → G-02 (multiplicador/markup por tabela, M ⚠️Tier 0).

## Método

Formato da ficha = o de `Sells/CAPTERRA-FICHA.md` (10 seções). 25+ WebSearch por dimensão de catálogo (variação/grade · preço-por-tabela · kardex/estoque · combo/BOM/kit · import massa · PIM/atributos/mídia). Anti-falso-positivo Tier 0: Grep de keywords em `ProductController.php` (2729 LOC), `ProdutoUnificadoController.php`, `ProductBomController.php`, `routes/web.php`, `Pages/Produto/` ANTES de marcar 🟡/❌. Worktree fresco de origin/main (`produto-capterra`).

## Fase 1 — Pesquisa por concorrente (limpa)

### BR PME / vertical

- **Bling** — variação por atributos (tam/cor), **kit/composição** com escolha de gestão de estoque (só produto / só componentes / ambos), lista de preços vinculável por produto. UI Bootstrap legado. [ajuda.bling.com.br — variação](https://ajuda.bling.com.br/hc/pt-br/articles/360035987033) · [kit](https://ajuda.bling.com.br/hc/pt-br/articles/360035495774).
- **Tiny (Olist)** — grade (cor/tamanho) via extensão de variações; **import/export planilha** com layout próprio; **update em massa de preço e descrição via API**; lista de preços com import por planilha. Referência-topo BR de catálogo PME. [ajuda.olist.com — listas de preços](https://ajuda.olist.com/precificacao/listas-de-precos) · [import planilha](https://suporte.olist.com/kb/articles/erp/inicio/ferramentas/importar-planilha-de-produtos).
- **Omie** — **Kardex** = movimento detalhado por produto/período com **CMC (custo)**, NF de origem, tipo de operação, cliente/fornecedor; estrutura de produto + ordem de produção; histórico de alterações. Expõe nosso kardex-de-fachada. [ajuda.omie.com.br — Kardex](https://ajuda.omie.com.br/pt-BR/articles/6845092).
- **Conta Azul** — **tabela de preços com cálculo automático** (markup na atualização) OU manual; **custo médio** calculado na entrada (produto+frete+impostos); gestão de estoque com **valor/custo total em estoque** + min/max. [ajuda.contaazul.com — tabela de preços](https://ajuda.contaazul.com/hc/pt-br/articles/30735447480461) · [custo médio](https://ajuda.contaazul.com/hc/pt-br/articles/8469337991565).
- **Hiper** — **grade composta** (cor×tamanho), GTIN, **gerar SKU/cód. barras auto por variação e por unidade**, custo médio + markup mínimo + preço mínimo, estoque por filial. [ajuda.hiper.com.br — grade composta](https://ajuda.hiper.com.br/ajudahiper/s/article/Como-cadastrar-um-produto-com-grade-composta-no-Hiper-Gest%C3%A3o).
- **Linx Microvix** — grade tam×cor+**coleção** nativa de moda; múltiplas tabelas de preço (Varejo/Atacado) aplicáveis em faturamento+POS; filtros por marca/coleção/tamanho/linha; multi-loja. Teto BR do vertical vestuário (persona Larissa). [linx.com.br — ERP moda](https://mkt.linx.com.br/retail-microvix-e-caito-maia) · [tabelas de preço](https://share.linx.com.br/pages/viewpage.action?pageId=168638400).
- **Nuvemshop** — até 3 variações × valores; **import CSV massivo**; SKU único + GTIN/cód. barras. Catálogo de loja, não ERP fiscal. [atendimento.nuvemshop.com.br — variações](https://atendimento.nuvemshop.com.br/pt_BR/organizar-produtos/como-cadastrar-variacoes-em-meus-produtos).

### Global (teto de catálogo/PIM)

- **Shopify** — **até 2.048 variantes/produto (out/2025)**, 3 opções/produto, **metafields tipados por categoria** (reutilizáveis, swatches). Teto de variação/atributo. [help.shopify.com — variants](https://help.shopify.com/en/manual/products/variants) · [forthcast — 2048 variants](https://www.forthcast.io/blog/manage-complex-shopify-catalogs-variants).
- **VTEX** — **SKU como entidade** (cada variação física); **specification groups** por categoria (specs replicadas em produtos/SKUs); **trade policies** (catálogo/preço/logística por canal); price tables por trade policy. Teto multi-canal. [developers.vtex.com — catalog](https://developers.vtex.com/docs/guides/catalog-overview) · [trade policies](https://help.vtex.com/en/docs/tutorials/how-trade-policies-work).
- **Akeneo (PIM)** — **families + family variants + attributes** (atributo = característica; famílias agrupam atributos comuns); **asset manager** (imagens/vídeo/doc por família); product models pra propriedades comuns de variantes. Teto de "gestão de produto séria". [help.akeneo.com — families/variants](https://help.akeneo.com/serenity-build-your-catalog/30-serenity-manage-your-families-and-variant-families).

**Ranking referência do segmento:** (1) Akeneo — PIM/atributos/mídia; (2) Shopify/VTEX — variantes/SKU/multi-canal; (3) Linx Microvix — grade moda BR; (4) Tiny/Bling — catálogo PME BR completo. **Outlier interessante:** Conta Azul — tabela de preço com **cálculo automático de markup** (exatamente o que o oimpresso finge ter com `mult=1.00`).

## Fase 2/3 — Matriz comparativa + código real (18 capacidades)

Anti-falso-positivo aplicado (Grep antes de marcar). Evidências-chave:

| ID | Cap | Nota | Evidência no código (Grep confirmado) |
|---|---|:-:|---|
| C01 (P0) | Variação/grade + SKU auto + validação dup | 8 ✅ | `get_product_variation_row`, `check_product_sku`, `validate_variation_skus` (routes l.413-417) |
| C02 (P0) | Preço por tabela + **multiplicador** | 5 🟡 | `group_prices` eager (l.1843/1980) OK; **`ProdutoUnificadoController::tabelas() 'mult'=>1.00` hardcoded TODO l.183**; SellingPriceGroup sem coluna mult (ADR ARQ-0001) |
| C03 (P0) | Estoque inicial + local + alerta + validade | 8 ✅ | `OpeningStockController`; `enable_stock`/`alert_quantity` (l.643-659); `enable_product_expiry` l.662; `enable_lot_number` l.601 |
| C04 (P0) | **Kardex/timeline real** | 4 🟡 | `getVariationStockHistory` existe mas **render Inertia `StockHistory` NÃO passa `movements`** (l.2652-2669) → prop `undefined`; timeline real só no path `request()->ajax()` Blade (l.2639). Grade 47 |
| C05 (P0) | Custo médio + valor/custo em estoque | 4 🟡 | `default_purchase_price` por variação OK; `/unificado` KPIs `margem_media=0`/`sem_giro=0`/`stockQty=null` TODO (l.83-124) |
| C06 (P0) | Multi-tenant Tier 0 | 9 ✅ | `App\Product` global scope; `ProductBom` `ScopeByBusiness` + `firstOrFail` cross-tenant (l.33-36) |
| C07 (P1) | Combo/kit + BOM | 6 🟡 | `type=='combo'` → `combo_variations` (l.704-722); `ProductBom` CRUD API real; **UI BOM drag-drop = US-INV-002 pendente** |
| C08 (P1) | Import + bulk + mass ops | 8 ✅ | `ImportProductsController` + `ImportOpeningStockController` + `bulk-edit`/`bulk-update`/`bulk-update-location` + `mass-deactivate`/`mass-delete` + `download-excel` |
| C09 (P1) | Atributos/PIM básico | 6 🟡 | categoria/subcategoria + marca + unidade+sub + **20 custom fields** (l.632) + `variations.media`; sem families/atributos tipados |
| C10 (P1) | Sync canal e-commerce | 4 🟡 | `toggleWooCommerceSync` (l.2682) só WooCommerce toggle; sem trade-policy/multi-marketplace |
| C11 (P1) | Cód. barras/etiqueta | 6 🟡 | `barcode_types()` l.56 + `LabelsController` ZPL/PDF (Vestuario/Etiquetas grade 74); geração auto GTIN não explícita |
| C12 (P1) | UX cadastro rápido | 7 ✅ | `quick_add`/`save_quick_product`; Create 80 / Edit 79 densidade 1280px + dedup |
| C13 (P2) | Catálogo denso/cockpit | 4 🟡 | `/unificado` 5 sub-views mas KPIs zerados + native `<select>`/`<input>` + blue-leak. Grade 56 |
| C14 (P2) | Perceived perf | 5 🟡 | `Inertia::defer` na Index; Unificado sem defer + TODOs cache/N+1 |
| C15 (P2) | UX/DS estado-da-arte | 6 🟡 | Index 83/BulkEdit 81/Create 80 fortes; Unificado 56 + StockHistory 47 puxam pra baixo |
| C16 (P2) | Catálogo público/QR | 8 ✅ | `Modules/ProductCatalogue` + `CatalogueQrService` (domínio separado) |
| C17 (P3) | Permissão granular no catálogo | 4 🟡 | `Route::resource` gated; **`/products/unificado` SEM `can:product.view`** (só `->name`, l.391) |
| C18 (P3) | Fornecedor/cotação por produto | 2 ❌ | `insumos()` `fornecedor=>null` TODO (l.164); sem cotação |

## Fase 4/5 — Cálculo bruto da nota

```
P0 (peso 4): C01 8, C02 5, C03 8, C04 4, C05 4, C06 9  = 38 → ×4 = 152
P1 (peso 2): C07 6, C08 8, C09 6, C10 4, C11 6, C12 7  = 37 → ×2 =  74
P2 (peso 1): C13 4, C14 5, C15 6, C16 8                = 23 → ×1 =  23
P3 (peso 0.5): C17 4, C18 2                            =  6 → ×0.5=   3
Σ = 252
Máx = P0 240 + P1 120 + P2 40 + P3 10 = 410
nota = 252/410 × 100 = 61.5 → 61/100
```

Referências: Tiny/Bling ~78, Linx moda ~80, Shopify/VTEX/Akeneo ~85 (sem fiscal BR / não Tier 0). Gap pro topo BR ~-18.

## Fase — 5 achados adversariais (§8)

1. **Kardex é fachada** — `StockHistory` (grade 47) render Inertia sem `movements`; timeline só linka Blade legacy (`ProductController.php:2639/2652`). Larissa não audita estoque na UI nova.
2. **Multiplicador de preço oco** — `mult=1.00` hardcoded (`ProdutoUnificadoController:183`), SellingPriceGroup sem coluna; "preço por tabela" é 1:1. Bloqueia F3 `/unificado`.
3. **8 telas draft, 0 live** — todas `status: draft`/`awaiting-smoke-browser`, sem `review.md`. `module-grade 71` mede telas que oficialmente não entraram em prod.
4. **Sem SPEC** — o core-dos-cores (alimenta Sells+Compras+Fiscal) é o único sem `SPEC.md`; teste vira tautológico (proibicoes §5), `casos-gate` sem âncora.
5. **Zero prova de correção de valor/estoque** — Produto define preço/custo/margem que Sells consome; `num_uf` (mesmo parser do incidente ×100k de Sells 2026-06-05) roda em preços/`alert_quantity` sem teste E2E de persistência correta. A rede de segurança de valor termina onde Produto começa.

## Top gaps (§6) — resumo

- **G-01** Kardex real na tela React (M, C04, P0) — passar `movements` no render + timeline defer.
- **G-02** Multiplicador/markup por tabela (M ⚠️Tier 0, C02) — resolver ADR ARQ-0001.
- **G-03** Agregação valor/custo em estoque + custo médio (L ⚠️Tier 0, C05/C13).
- **G-04** Criar `SPEC.md` do Produto (S, governança) — pré-req dos testes de valor.
- **G-05** `can:product.view` no `/unificado` + charters draft→live (S, C17).
- **G-06** UI BOM drag-drop US-INV-002 + baixa-de-componente do kit (M, C07).

## Próximo passo sugerido

`/comparativo Produto` pra cruzar esta ficha com (o inexistente) SPEC → gerar CAPTERRA-INVENTARIO + propor batch tasks (começando por G-04 criar SPEC, que destrava os testes de valor de G-02/G-03).

## Fontes (WebSearch)

Bling, Tiny/Olist, Omie, Conta Azul, Hiper, Linx Microvix, Nuvemshop (BR) · Shopify, VTEX, Akeneo (global) — URLs específicas citadas na Fase 1 e na §10 da ficha.
