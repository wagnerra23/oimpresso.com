---
slug: produto-sdd
title: "SDD — Tela de Cadastro de Produto (domínio Produto / registro-mãe do ERP)"
type: sdd
module: Produto
status: ativo
owner: wagner
version: 1.0.2
last_updated: 2026-07-17
related_docs:
  - SPEC.md
  - BRIEFING.md
  - CAPTERRA-FICHA.md
  - CAPTERRA-INVENTARIO.md
  - produtos-gap.md
  - UI-CATALOG.md
  - _telas/RUNBOOK-produto-create.md
  - _telas/RUNBOOK-produto-selling-prices.md
  - _telas/RUNBOOK-produto-stock-history.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0110-cockpit-pattern-v2-canon-list-detail
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0149-mwart-screen-pattern-reuse-cowork
  - 0190-primary-button-roxo-universal-295
  - produto/adr/arq/0001-selling-price-multiplier
---

# SDD — Software Design Document · Tela de Cadastro de Produto (domínio Produto)

> **Escopo deste documento:** consolidar, num único design document, a arquitetura, governança, design system e casos de uso da família de telas de **cadastro de produto** (`/products/*` e `/products/unificado`). Produto é o **registro-mãe do ERP** — o insumo de preço/custo/estoque que **Vendas, Compras, Fiscal e Produção** consomem. Este SDD **não substitui** o [SPEC.md](SPEC.md) (user stories `US-PROD-NNN`) nem os charters por página — ele é o mapa de cima que amarra tudo.
>
> **Fontes canônicas:** charters em `resources/js/Pages/Produto/*.charter.md`, RUNBOOKs em `_telas/RUNBOOK-produto-*.md`, [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (nota de capacidade 61/100), [produtos-gap.md](produtos-gap.md) (mockup Cowork), ADRs em `memory/decisions/`, design system em `memory/requisitos/_DesignSystem/` + handoff DS v6.
>
> **Documento-modelo:** [SDD — Tela de Vendas (família Sells)](../Sells/SDD-tela-vendas-FINAL-v1.2.md) — mesmo formato canônico.

> ### 🔖 Changelog v1.0.2 (2026-07-17) — corrige a premissa falsa do multiplicador
> A v1.0.0/v1.0.1 herdaram da ADR ARQ-0001 a leitura *"multiplicador oco / preço por tabela é 1:1 / não funciona"*. **É falsa** (Errata ARQ-0001, confirmada por 2 adversários + golden DB-backed): o preço por (variação × tabela) **funciona** (`fixed`+`percentage`, chega na venda em `ProductUtil.php:1064`/`SellPosController.php:1790`); o `mult=1.00` é prop cosmético do protótipo `/unificado`. Corrigidos: §0.2 (buraco "multiplicador oco"), §5.2 (modelo de dados), §5.3 F2. O gap G-02 vira **"falta a regra de tabela inteira"** (default por grupo), não "criar multiplicador". Re-enquadrar a nota C02/61 da FICHA e a US-PROD-022 = decisão [W].
>
> ### 🔖 Changelog v1.0.1 (2026-07-15) — correção de claim, não de escopo
> **`CU-PROD-10` deixa de ser `✅ (reusa guard)` e vira 🟡 parcial.** O ✅ da v1.0.0 nunca foi medido —
> era leitura de código. Quando o `UC-PTAB-04` rodou pela 1ª vez em CI ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)),
> **reprovou**: o guard reusado cobre `App\Product`, e a tabela de preço não é `Product` — o
> `price_group_id` entrava cru do request e gravava linha cross-tenant. Corrigido no mesmo PR
> (failing-first), mas **por validação explícita**, não por global scope. E o item 2 ("cross-tenant → 404")
> segue **falso no POST** (302 — exceção engolida por `catch` genérico); decisão [W] pendente.
> Itens 3-4 rebaixados a ⬜ não-verificado: nenhum teste os cita.
> **Nada no código piorou** — o que mudou foi o documento parar de afirmar o que não media.
> Lição de método registrada em [`proibicoes.md`](../../proibicoes.md) §5, entrada 2026-07-15.
>
> ### 🔖 Changelog v1.0.0 (2026-07-10)
> Primeiro SDD do domínio Produto, criado logo após o SPEC (G-04 da onda Produto). Consolida:
> - **§1–§5** — visão, personas, governança, design system e arquitetura do cadastro core.
> - **§6** — casos de uso `CU-PROD` (paridade/core) + `CV` (comunicação visual) + `OF` (oficina), cada um com lista de teste.
> - **§10** — roadmap de evolução por trilha (construção · comunicação visual · oficina · balcão/varejo).
> **Diferença central vs o SDD de Vendas:** aqui o eixo de risco não é fiscal, é **cálculo de valor/estoque (Tier 0 REGRA MESTRE)** — preço, custo, margem, multiplicador de tabela e `num_uf` (§3.1). E o produto atende **duas verticais com naturezas de catálogo diferentes** (§1.0).

---

## 0. Base empírica: benchmark de capacidade + as duas verticais

Esta seção registra **de onde vem** o material que fundamenta os casos de uso e o roadmap — sem histórico de suporte próprio ainda (o `casos.md` é a US-PROD-020), a base empírica é o benchmark de capacidade e o mockup Cowork das duas verticais.

### 0.1 Três fontes de verdade cruzadas

| Fonte | O que traz | Onde |
|---|---|---|
| **Código real** | O que o cadastro **já faz** hoje (CRUD, variação, preço por tabela, estoque, BOM, import) | `app/Http/Controllers/ProductController.php` (~2.729 LOC), `ProdutoUnificadoController.php`, `Inventory/ProductBomController.php`, `Pages/Produto/` (8 Pages) |
| **Benchmark Capterra** | Nota de capacidade **61/100** vs líderes BR/global + os 6 gaps priorizados (G-01..G-06) | [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) · [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) |
| **Mockup Cowork "Picker Mecânica"** | O que a UI viva **ainda não tem** (drawer rico, lista densa, tabela de preços por nível, fornecedores, BOM, aplicação por veículo) | [produtos-gap.md](produtos-gap.md) · `_cowork-handoff-staging/oimpresso-erp-conunica-o-visual/project/produtos-page.jsx` |

### 0.2 O que o benchmark expôs (leitura adversarial da FICHA §8)

A `module-grade 71` (UX/DS das 8 telas) **esconde** três buracos de valor/estoque que só o benchmark de capacidade contra Tiny/Linx/Shopify revelou:

| Buraco | Sintoma | CU/Gap |
|---|---|---|
| **Kardex é fachada** | `StockHistory.tsx` não recebe `movements` no render Inertia (prop `undefined`); timeline real só no Blade legacy (grade 47) | CU-PROD-11 · G-01 |
| **~~Multiplicador de preço oco~~ Regra de tabela inteira ausente** | ⚠️ **corrigido 2026-07-17** (Errata [ADR ARQ-0001](adr/arq/0001-selling-price-multiplier.md)): preço por (variação × tabela) **funciona** (`fixed` + `percentage`, chega na venda em `ProductUtil.php:1064`/`SellPosController.php:1790`). O `mult=1.00` é prop **cosmético** do protótipo `/unificado`, não multiplicador neutralizado — a coluna `mult` nem existe. Gap real: falta a **regra de tabela inteira** ("Atacado −15% em tudo") como default; hoje é célula a célula | CU-PROD-03 · G-02 |
| **Valor-em-estoque ausente** | KPIs `margem_media`/`sem_giro`/`stockQty` zerados no `/unificado`; sem custo médio nem valor de inventário | CU-PROD-12 · G-03 |

> ⚠️ A rede de segurança de valor **termina onde o Produto começa**: Produto **define** preço/custo/margem que Sells **consome** — o mesmo parser `num_uf` que inflou 16 vendas ×100k em Sells (incidente 2026-06-05) roda em `alert_quantity`/preços do produto, **sem teste E2E de que a conta fecha** (§3.1).

---

## 1. Visão geral

A tela de cadastro de produto é o **registro-mãe do ERP** — nada em Vendas, Compras, Fiscal ou Produção existe sem um produto cadastrado antes. É um **wrapper Inertia/React sobre `App\Product`** do UltimatePOS legacy, em migração Blade→React via processo MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) — **8 telas React existem, todas `status: draft`/`awaiting-smoke-browser`, zero `live`** (o Blade coexiste como fallback via branch dual no header `X-Inertia`). Finalizar e promover essas telas é a US-PROD-023.

### 1.0 As duas verticais do produto

O oimpresso é **dedicado a comunicação visual e oficina** ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md), modular especializado por vertical). O cadastro core atende **três naturezas de catálogo** — e a maturidade é assimétrica:

| Natureza | Exemplo | Precificação | Estado atual |
|---|---|---|---|
| **Balcão / revenda (varejo)** | Camiseta tam×cor (ROTA LIVRE), autopeça de prateleira | qtd × preço de tabela | ✅ Maduro — variação+SKU (C01), estoque inicial (C03), import/bulk (C08) 🟢 |
| **Comunicação visual (produção)** | Banner, adesivo, fachada, lona — **item sob medida** | **preço por m²** (L×A) + material/substrato + acabamento + BOM "desmonta peça-por-peça" (lona+tinta+ilhós+mão de obra) | 🟡 Parcial — o cadastro core trata como produto de prateleira; a máquina de m² vive **separada** em `Modules/ComunicacaoVisual` (`comvis_materiais`, `OrcamentoCalculator`) e **não conversa** com o `App\Product` (§5.4) |
| **Oficina (peça + serviço)** | Peça com **aplicação por veículo** (marca/modelo/ano) + código **OEM** + equivalências + fornecedor; **mão de obra** com tempo padrão | peça: custo+markup; serviço: tabela de tempo × valor/hora | 🟡 Parcial — `service_order_items` (`tipo: peca|mao_obra|servico_terceiro`) referencia `product_id`, mas o cadastro core **não tem** aplicação/OEM/equivalência/fornecedor (§5.4). O mockup Cowork "Picker Mecânica" já desenhou isso ([produtos-gap.md](produtos-gap.md)) |

> **Insight de mercado** (estado-da-arte, §11): sistemas líderes de gráfica calculam o preço por m² assim que largura×altura são informados e **"desmontam o produto peça por peça"** (tinta, lona, ilhós, mão de obra) pra garantir margem; sistemas de oficina **gravam automaticamente a aplicação da peça** (marca/modelo/ano) ao lançá-la numa OS. O cadastro core do oimpresso ainda não expressa nenhum dos dois nativamente — é o maior retorno do roadmap (§10.2/§10.3).

### 1.1 Família de telas

| Rota | Página Inertia | Charter | Status | Papel |
|---|---|---|---|---|
| `/products` | `Pages/Produto/Index.tsx` (~456 LOC) | Index.charter.md v1 · Tier A | draft (grade 83) | Lista lite — cards + busca + tabs de categoria + KPI strip |
| `/products/create` | `Pages/Produto/Create.tsx` | Create.charter.md v1 · Tier A | draft (grade 80) | Cadastro — form full-width, 8 campos + Avançado colapsável |
| `/products/{id}/edit` | `Pages/Produto/Edit.tsx` | Edit.charter.md · Tier A | draft (grade 79) | Edição |
| `/products/{id}` | `Pages/Produto/Show.tsx` | Show.charter.md · Tier A | draft (grade 70) | Detalhe do produto |
| `/products/{id}/selling-prices` | `Pages/Produto/SellingPrices.tsx` | SellingPrices.charter.md · Tier A | draft (grade 68) | Matriz preço por tabela × variação |
| `/products/bulk-edit` | `Pages/Produto/BulkEdit.tsx` | BulkEdit.charter.md · Tier A | draft (grade 81) | Edição em massa |
| `/products/{id}/stock-history` | `Pages/Produto/StockHistory.tsx` | StockHistory.charter.md · Tier A | draft (grade **47** — fachada) | Kardex — **hoje só linka Blade** (CU-PROD-11) |
| `/products/unificado` | `Pages/Produto/Unificado/Index.tsx` | Unificado/Index.charter.md · Tier A | draft (grade 56) | Cockpit denso 5 sub-views (produtos/insumos·BOM/tabelas/histórico) |

> ⚠️ **Não confundir** `Produto` (cadastro **interno**, este SDD) com `Modules/ProductCatalogue` (catálogo **público** + QR via `CatalogueQrService`, Blade) — domínios separados, não compartilham controller nem Pages.

---

## 2. Público-alvo e personas

O design é dirigido por perfis reais (princípio "cliente como sinal qualificado" — [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

### P1 · Larissa — ROTA LIVRE (biz=4, vestuário) — 99% do volume
- Balconista não-técnica, Android low-end / monitor 1280×1024. Cadastra ~3-8 produtos/semana: variação tam×cor, preço por tabela, estoque inicial.
- **Decisões de design derivadas:** 8 campos sempre visíveis + ~22 colapsáveis em "Avançado" (`<details>`), defaults conservadores (`type='single'`, `enable_stock=true`, `tax_type='exclusive'`), parse numérico pt-BR anti-`num_uf`-×100 no submit, cabe em 1280px sem scroll horizontal, quick-add inline (cadastro mínimo nome+SKU sem sair do fluxo de venda/compra).
- **Regra de ouro de teste:** smoke **NUNCA em biz=4** — só biz=1 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).

### P2 · Wagner — WR2 SC (biz=1) — operador-dono e cobaia segura
- Usa o `/unificado` como gestor: valor em estoque, margem média, produtos sem giro, tabelas de preço. Único business de smoke/canary.
- **Decisões derivadas:** KPIs `Inertia::defer`, 5 sub-views densas, totalizadores de inventário (hoje zerados — G-03), permissão `can:product.view` no `/unificado` (hoje ausente — G-05).

### P3 · Gráficas comunicação visual (OfficeImpresso: Vargas, Extreme, Gold, Fixar, Produart)
- Cadastram **material precificado por m²** (lona, adesivo, ACM), **insumo consumível** (bobina, tinta, ilhós) com baixa automática, e **produto de produção** cujo preço vem do cálculo por m² + BOM, não digitado de cabeça.
- **Decisões derivadas:** tipo de linha "material/m²" com `preco_custo_m2`/`preco_venda_m2`/`gramatura`; BOM que "desmonta" o produto pra garantir margem; preço autoritativo server-side reusando `OrcamentoCalculator` (CV-05).

### P4 · Oficinas (OficinaAuto — candidato Martinho Caçambas)
- Cadastram **peça com aplicação por veículo** (marca/modelo/ano), **código OEM + equivalências**, **fornecedor com cotação**, e **serviço/mão de obra** com tempo padrão.
- **Decisões derivadas:** tipo de linha "peça" com aplicação+OEM+equivalência+fornecedor e "serviço" com tempo×valor/hora; kit de revisão = BOM de peças+serviços; drawer com "melhor cotação por fornecedor" (mockup Picker Mecânica).

---

## 3. Governança aplicável

Camadas em ordem de precedência (Constituição v2 — [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)).

### 3.1 Tier 0 — IRREVOGÁVEL (sem ADR mãe nova é proibido)

- **⚠️ REGRA MESTRE — cálculo de valor/estoque** ([proibicoes.md](../../proibicoes.md)): **este é o eixo de risco nº1 do Produto.** Toda mudança em **preço, custo, margem, multiplicador de tabela, valor/custo em estoque ou `num_uf`** exige **dupla-confirmação (2 caminhos numéricos independentes) + tabela antes→depois + aprovação humana** antes de mergear. Cai aqui: multiplicador de tabela (CU-PROD-03/G-02), agregação de valor em estoque (CU-PROD-12/G-03), preço por m² (CV-01), custo/margem por fornecedor (OF-03). Teste E2E ancora no **contrato** (SPEC/casos), nunca na implementação (senão vira tautológico — proibicoes §5).
- **`num_uf` em preço de custo/venda** — mesmo parser que inflou vendas ×100k em Sells (incidente 2026-06-05). Frontend **nunca** manda float locale-ambíguo; arredonda 2 casas no submit; separador de milhar tem sempre 3 dígitos. Guard de regressão obrigatório em qualquer campo monetário do form.
- **Multi-tenant isolation** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)): `business_id` global scope em `App\Product` + `ProductBom` (`ScopeByBusiness`) + `firstOrFail` cross-tenant → **404** (não 403). Toda query nova de catálogo herda o scope. `localStorage` sempre prefixado `oimpresso.produto.b<bizId>.*`.
- **Auditoria de estoque append-only** — movimento de kardex nunca sofre UPDATE/DELETE; ajuste é novo movimento. Kardex é a fonte-de-verdade de quantidade e é a base de qualquer valor-em-estoque futuro.

### 3.2 Processo de mudança

- **Charter Tier A** ao lado de cada `.tsx` (as 8 já têm charter `draft`). Promover `draft`→`live` exige **smoke browser biz=1 + `review.md`** (US-PROD-023).
- **MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) é o único caminho Blade→Inertia: branch dual `X-Inertia` no controller, 5 fases, gate visual F1.5/F3 ([ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)) — Wagner aprova **screenshot**, não tabela. Reuso de screen-pattern via [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md).
- **SPEC ancora o teste** — sem [SPEC.md](SPEC.md), teste de cálculo de valor vira tautológico. O SPEC (G-04) é pré-requisito de G-02/G-03; o `casos.md` (US-PROD-020) é pré-requisito do `casos-gate`.
- **Commit discipline:** 1 PR = 1 intent, ≤300 linhas, conventional commits.

### 3.3 Fronteira com os módulos verticais

- **Não** duplicar a máquina de m² dentro do core: `OrcamentoCalculator` é a **fonte de preço autoritativa** de comunicação visual (CV-05). O cadastro core empresta o material; o cálculo fica no módulo.
- **Não** inventar campos de veículo/OEM no core sem ADR — a aplicação por veículo é feature nova de escopo (§10.3), medida por sinal (ADR 0105) antes de investir.

---

## 4. Design system aplicável

Hierarquia da Constituição UI v2 ([ADR UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)) — camada superior herda e nunca contradiz. Tokens do **DS v6** (git SSOT, DTCG/Style Dictionary, OKLCH).

| Camada | O que vale pra Produto |
|---|---|
| **1 · Fundações** | Tokens imutáveis sem ADR. **Primary roxo** `oklch(0.55 0.15 295)` ([ADR 0190](../../decisions/0190-primary-button-roxo-universal-295.md)) — nunca o azul shadcn. Fonte operacional **IBM Plex Sans/Mono**. Type RAMP `--fs-1..9` (h1 = `--fs-7` 22px). `tabular-nums` (`.num`/`.tabular`) obrigatório em **todo preço/custo/margem/quantidade** — dinheiro sempre alinhado. Status: success(verde 162)/warning(âmbar 75)/destructive(vermelho 18)/info(azul 244), cada um com `-soft`/`-fg`. |
| **2 · Shell** | AppShellV2 (Persistent Layout) + PageHeader v3. Sidebar dark-fixed (`--sb-bg`). |
| **3 · Padrão de Tela** | **Index/Unificado = [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md)** (6 slots: PageHeader → ModuleTopNav → Toolbar → BulkBar → Table/Grid → Drawer). Matriz de paridade 3-way **CONFORME** ([produto-index-setor-matrix.md](_telas/produto-index-setor-matrix.md)). **Create/Edit = form full-width** (divergência declarada no charter: form não tem "conversa em foco", logo não usa o Cockpit 3-col). |
| **4 · Módulo** | Classes semânticas escopadas — **nunca cor crua Tailwind** (anti-padrão AP1; remover blue-leak `sky-700` e stone cru do `/unificado` — US-PROD-023). |

### 4.1 Componentes DS v6 relevantes (handoff DS v6)

Do handoff estão prontos e são os blocos de montagem das telas de produto:

- **Genéricos:** `PageHeader`, `DataTable`/`DataTablePro` (grid denso `/unificado`), `Drawer` (drawer rico do mockup), `Input`, `Checkbox`/`Switch`, `DropdownMenu`, `FilterChip`, `EmptyState`, `Skeleton`, `StatusBadge`, `TagChip` (categoria), `KpiCard`/`KpiFilterCard` (strip de totalizadores), `Modal`, `Toast`, `Pagination`.
- **Print-craft (comunicação visual):** `Dimension` (cota técnica L×A — "3.000 mm"), `RegistrationMark` (mira de registro — glyph do sistema), `ProofFrame` (folha de prova com crop marks), `ProofStrip` (tira de controle CMYK/densidade). Uso natural nos itens/preview de material por m² e no cabeçalho de seções de produção.
- **Oficina:** `PlacaVeiculo` (placa Mercosul) + `TagChip` por categoria/hue — casam com a aplicação por veículo e o "Picker Mecânica".

**Gates de qualidade visual:** PRE-MERGE-UI (4 camadas) + `node prototipo-ui/ds-guard.mjs <arquivos>` + `node prototipo-ui/integrity-check.mjs` ao formalizar + comparação visual aprovada por screenshot (gate F3).

---

## 5. Arquitetura

### 5.1 Visão em camadas

```
┌────────────────────────────────────────────────────────────────────┐
│ FRONTEND — Inertia/React (resources/js/Pages/Produto/)             │
│  Index · Create · Edit · Show · SellingPrices · BulkEdit ·         │
│  StockHistory · Unificado/Index                                    │
├────────────────────────────────────────────────────────────────────┤
│ HTTP — routes/web.php (auth + business middleware + can:product.*) │
│  Páginas: Route::resource('products') · /products/unificado        │
│  Variação: get_product_variation_row · validate_variation_skus     │
│  SKU:      check_product_sku · quick-add / save_quick_product      │
│  Preço:    add/save-selling-prices                                 │
│  Estoque:  opening-stock/add|save · stock-history                  │
│  Massa:    bulk-edit · bulk-update(-location) · mass-deactivate     │
│  Import:   import-products · import-opening-stock · download-excel  │
│  BOM:      /api/products/{id}/bom (GET/POST/DELETE)                 │
│  Canal:    toggle-woocommerce-sync                                  │
├────────────────────────────────────────────────────────────────────┤
│ CONTROLLERS — app/Http/Controllers/                                │
│  ProductController (~2.729 LOC, UPOS canon — store/update, tipos   │
│    single|variable|combo, SKU server-side, media, product_locations)│
│  ProdutoUnificadoController (222 LOC — cockpit V2, cheio de TODOs) │
│  Inventory/ProductBomController (CRUD BOM, ScopeByBusiness)         │
│  OpeningStockController · ImportProductsController · LabelsController│
├────────────────────────────────────────────────────────────────────┤
│ DOMÍNIO / DADOS — MySQL multi-tenant (business_id global scope)    │
│  products + variations + variation_location_details +              │
│  variation_group_prices + selling_price_groups +                   │
│  product_variations (combo) + product_bom (Domain\Inventory) +     │
│  transactions(purchase) → default_purchase_price (custo)           │
├────────────────────────────────────────────────────────────────────┤
│ FRONTEIRA COM AS VERTICAIS (não fundir com o core)                 │
│  Modules/ComunicacaoVisual: comvis_materiais (preco_*_m2,          │
│    gramatura) + OrcamentoCalculator (preço por m² autoritativo)   │
│  Modules/OficinaAuto: service_order_items (tipo peca|mao_obra) →   │
│    product_id (peça = App\Product)                                 │
└────────────────────────────────────────────────────────────────────┘
```

### 5.2 Modelo de dados (núcleo)

- **`products`** (UltimatePOS legacy) — `type ENUM('single','variable','combo')`, `unit_id`, `category_id`/`sub_category_id`, `brand_id`, `tax`, `barcode_type`, `alert_quantity`, `enable_stock`, `expiry_period`/`enable_sr_no`, `weight`, `product_custom_field1..20`, `woocommerce_disable_sync`.
- **`variations`** — 1 produto variável → N variações (grade tam×cor), cada uma com `sub_sku` (SKU auto + validação de duplicado batch), `default_purchase_price`/`dpp_inc_tax` (**custo**), `default_sell_price`.
- **`selling_price_groups`** + **`variation_group_prices`** — matriz tabela × variação. Preço por (variação × tabela) **funciona** — `price_type ∈ {fixed, percentage}`, lido em `ProductUtil::getVariationGroupPrice` e aplicado na venda. **O que falta** é a **regra de tabela inteira** (default por `selling_price_group`, ex. "−15% em tudo") — hoje o percentual é declarado célula a célula ([ADR ARQ-0001 produto](adr/arq/0001-selling-price-multiplier.md) proposed + Errata 2026-07-17 — CU-PROD-03). ⚠️ A v1 dizia *"`mult` hardcoded 1.00 — não funciona"*; era leitura falsa de um prop cosmético do protótipo.
- **`variation_location_details`** — estoque `qty_available` por variação × localização (base de qualquer valor-em-estoque).
- **`product_bom`** (`App\Domain\Inventory\Models\ProductBom`) — estrutura de componentes; CRUD API multi-tenant pronto, **UI drag-drop pendente** (CU-PROD-05).
- **Fronteira comvis:** **`comvis_materiais`** — `preco_custo_m2`, `preco_venda_m2`, `gramatura_g_m2`, `estoque_minimo_m2` (catálogo de material **separado**, não é `App\Product` — CV-01).
- **Fronteira oficina:** **`service_order_items`** — `tipo ENUM('peca','mao_obra','servico_terceiro')`, `product_id` nullable (peça referencia o core), `quantidade`/`valor_unitario`/`valor_total` (OF-01/OF-04).

### 5.3 Fluxos críticos

**F1 · Cadastrar produto (`store`):** Create.tsx → `useForm` (defaults conservadores) → `POST /products` (`ProductController@store`, DB transaction) → grava `products` + gera **SKU server-side** + cria `variations` (se `variable`) ou `combo_variations` (se `combo`) + `variation_location_details` (opening stock) + `Media`. **SKU nunca é gerado client-side** (charter Non-Goal). Duplicate via `?d=N` pré-preenche com `(copy)`.

**F2 · Preço por tabela (`saveSellingPrices`):** SellingPrices.tsx → matriz grupo × variação → `POST save-selling-prices`. Grava `variation_group_prices` (`fixed`/`percentage`) e o preço **chega na venda** (`SellPosController.php:1790`). O gap G-02 **não** é "1:1 / oco" (leitura corrigida 2026-07-17) — é a ausência da **regra de tabela inteira** (default por grupo). Implementá-la toca cálculo de preço → **REGRA MESTRE** (dupla-confirmação).

**F3 · Kardex (`productStockHistory`):** StockHistory.tsx → **hoje a prop `movements` fica `undefined`**; a timeline real só existe no path `request()->ajax()` (Blade `product.stock_history_details`). A tela React **linka o legacy** em vez de renderizar (grade 47). Fix = passar `movements` via `Inertia::defer` (CU-PROD-11 / G-01).

**F4 · Cockpit `/unificado`:** 5 sub-views (produtos/insumos·BOM/tabelas/histórico). **KPIs `margem_media`/`sem_giro`/`stockQty` zerados** (TODO) — agregação de valor/custo em estoque ausente (CU-PROD-12 / G-03). Falta `can:product.view` (G-05).

**F5 · Import/bulk:** `import-products` + `import-opening-stock` (Excel) + `bulk-edit`/`bulk-update`/`bulk-update-location` + `mass-deactivate`/`mass-delete` + `download-excel` — **forte** (pareia com Tiny, C08 ✅).

**F6 (vertical comvis) · Material como insumo de preço:** cadastro do material em `comvis_materiais` (m²) → `OrcamentoCalculator::calcular()` resolve preço (override do operador → `preco_venda_m2` do catálogo → erro), calcula `area_m2 = L × A × qtd` e `subtotal = area_m2 × preço/m²` **server-side authoritative** (frontend descartado). Alvo: expor esse material no cadastro core como tipo de linha "material/m²" (CV-01).

### 5.4 Onde os dois mundos ainda não se conversam (dívida central)

O `ProductLineCard` (e o cadastro core) conhecem **`qtd × preço unitário − desconto`**. As duas verticais precisam de mais:

- **Comunicação visual** já tem `OrcamentoCalculator` autoritativo (m², material, BOM, redaction PII) — mas **desconectado** do `App\Product`. O material vive em `comvis_materiais`, não no catálogo core. Um banner não é cadastrável como "produto por m²" hoje.
- **Oficina** referencia `product_id` na peça, mas o cadastro core **não tem** aplicação por veículo, OEM, equivalência nem fornecedor — o mockup "Picker Mecânica" desenhou tudo isso, mas é escopo novo (§10.3).

Fechar essa lacuna é o **maior retorno** do roadmap (§10.2/§10.3) e o que diferencia o cadastro de um PDV genérico.

---

## 6. Casos de uso

> **Convenção dos testes:** `[must]`/`[should]` prioridade · `[T0]` invariante multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) · `[V0]` **REGRA MESTRE valor/estoque** (dupla-confirmação + antes→depois) · `[perf]` · `[ux]`. Todo CU `must` mapeia pra teste Pest ancorado no SPEC/`casos.md` (nunca na implementação).
>
> **Estrutura:** §6.1 core/paridade (`CU-PROD`) · §6.2 comunicação visual (`CV`) · §6.3 oficina (`OF`) · §6.4 non-goals.

### 6.1 Core / paridade (`CU-PROD`)

#### CU-PROD-01 — Cadastrar produto simples `[must]` ✅
*Dado* nome/SKU/unidade/categoria/preço/imposto; *quando* salvo no Create; *então* persiste em `products` com SKU gerado server-side.
1. `[must]` Campos obrigatórios (name, unit, tax) validados client + server.
2. `[must]` SKU vazio → gerado **server-side**; SKU digitado → validado duplicado.
3. Defaults: `type='single'`, `enable_stock=true`, `tax_type='exclusive'`.
4. `[V0]` Preço de custo e venda passam pelo parser pt-BR sem ×100 (`num_uf`); arredondar 2 casas.
5. `[T0]` Dropdowns (categoria/marca/unidade/imposto) só do business atual.
6. Submit retorna `/products` (paridade legacy).

#### CU-PROD-02 — Produto variável (grade tam×cor) + SKU auto + validação duplicado `[must]` ✅
1. `[must]` Grade tam×cor gera N variações; cada uma com `sub_sku` auto.
2. `[must]` `validate_variation_skus` bloqueia SKU duplicado em **batch** antes de salvar.
3. Preço/estoque por variação, não só no produto-pai.
4. `[V0]` Preço por variação sem inflar decimal.
5. `[T0]` Variações carimbam o business atual.

#### CU-PROD-03 — Preço por tabela (SellingPriceGroup) `[must]` 🟡 **multiplicador oco** (G-02)
1. `[must]` Matriz grupo × variação salva preço por tabela (`variation_group_prices`).
2. `[V0][reg]` **Multiplicador/markup por tabela** — hoje `mult=1.00` hardcoded; resolver ADR ARQ-0001 sob dupla-confirmação (2 caminhos: coluna `multiplier` vs cálculo `VariationGroupPrice`) + tabela antes→depois.
3. `[V0]` Markup aplicado recalcula preço da tabela sem divergir do financeiro.
4. `[T0]` Tabelas só do business atual.

#### CU-PROD-04 — Estoque inicial + localização + alerta + validade/lote `[must]` ✅
1. `[must]` Opening stock por localização grava `variation_location_details`.
2. `alert_quantity` dispara alerta de estoque baixo.
3. `enable_product_expiry`/`enable_lot_number` habilitam validade/lote quando ligados no business.
4. `[V0]` Quantidade fracionada respeita a unidade; `num_uf` não strippa decimal.
5. `[T0]` Estoque no local do business correto.

#### CU-PROD-05 — Combo/kit + BOM `[should]` 🟡 (UI pendente — G-06)
1. Combo (`type='combo'`) monta produto de variações-filho.
2. BOM (`ProductBom`) CRUD API multi-tenant funciona; **UI drag-drop pendente**.
3. `[reg]` Baixa-de-componente do kit no PDV comprovada (Bling tem).
4. `[T0]` BOM `ScopeByBusiness` + `firstOrFail` cross-tenant.

#### CU-PROD-06 — Importação Excel + bulk-edit + mass-ops `[should]` ✅
1. `import-products` + `import-opening-stock` (Excel) + `download-excel`.
2. `bulk-edit`/`bulk-update`/`bulk-update-location` + `mass-deactivate`/`mass-delete`.
3. `[V0]` Import de preço/custo passa pelo mesmo guard `num_uf`.
4. `[T0]` Bulk valida `business_id` de **cada** ID antes de aplicar.

#### CU-PROD-07 — Duplicar produto `[should]` ✅
1. `?d=N` pré-preenche o form com o produto + `(copy)` no nome.
2. `[T0]` Só duplica produto do business atual (externo → 404).

#### CU-PROD-08 — Quick-add inline (sem sair do fluxo) `[should]` ✅
1. `quick_add`/`save_quick_product` cadastra mínimo (nome+SKU+preço) de dentro da venda/compra.
2. `[reg]` Não perde o contexto de origem (venda/OC) ao voltar.
3. `[T0]` Produto criado no business atual.

#### CU-PROD-09 — Código de barras + etiqueta `[should]` 🟡
1. `barcode_types` (C128 etc) + etiquetas ZPL/PDF (`LabelsController`).
2. Geração auto de GTIN por variação (Hiper tem) — gap.

#### CU-PROD-10 — Isolamento multi-tenant `[must]` 🟡 **parcial** (era ✅ "reusa guard" — falso, ver v1.0.1)
1. `[must][T0]` `App\Product` global scope em toda query. — 🟡 **o guard reusado cobre `Product`, não o que pendura nele.** `VariationGroupPrice` não tem global scope (`$guarded = ['id']`) e o `price_group_id` entrava **cru da chave do array do request** em `saveSellingPrices`: produto MEU + `price_group` ALHEIO gravava linha cross-tenant. Provado vermelho em CI ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300), `UC-PTAB-04`) e **corrigido no mesmo PR** (`$allowedPriceGroupIds` resolvido antes do laço + skip + `Log::warning`). ✅ hoje **por validação explícita**, não por global scope — a distinção importa: o próximo model pendurado em `Product` nasce com o mesmo buraco.
2. `[T0]` Cross-tenant por ID → **404** (não 403). — 🔴 **verdadeiro só no GET.** O `addSellingPrices` devolve 404. O **POST** `saveSellingPrices` roda o `findOrFail` dentro de `try { } catch (\Exception $e)`: a `ModelNotFoundException` é engolida pelo catch genérico e vira `redirect('products')` + `success: 0` — **302**. Isolamento não vaza (aborta antes do write + rollback), mas o contrato prometido é falso e a tentativa cross-tenant fica indistinguível de erro de banco no `Log::emergency`. Decisão [W] pendente no §Backlog de [`SellingPrices.casos.md`](../../../resources/js/Pages/Produto/SellingPrices.casos.md): US pra re-lançar antes do catch, ou Non-Goal declarado.
3. `[T0]` `ProductBom` `ScopeByBusiness` + `firstOrFail`. — ⬜ **não verificado** (nenhum teste cita; o ✅ da v1.0.0 valia por leitura de código, não por execução).
4. `[T0]` `localStorage` sempre `oimpresso.produto.b<bizId>.*`. — ⬜ **não verificado** (idem).

> ⚠️ **Por que este CU mudou de ✅ pra 🟡 sem o código piorar:** o ✅ da v1.0.0 nunca foi medido — era leitura de código. Quando o `UC-PTAB-04` rodou pela 1ª vez, reprovou. Os itens 1-2 hoje têm **teste vivo** (`tests/Feature/Produto/TabelaPrecoContratoTest.php`, lane `Estoque · MySQL`); os itens 3-4 seguem sem. O 🟡 é **mais honesto** que o ✅ anterior: mede o que existe em vez de afirmar o que se supunha. Lição registrada em `proibicoes.md` §5, entrada 2026-07-15.

#### CU-PROD-11 — Kardex real na tela React `[must]` 🟡 **fachada** (G-01)
1. `[must][reg]` Controller passa `movements` (JSON) via `Inertia::defer` — data · operação · qty · `stock_before`/`stock_after` · ref clicável (OS/Compra/Venda). Hoje `undefined`.
2. Cor semântica (verde in / vermelho out / âmbar ajuste); **append-only** (sem mutação em GET).
3. Hero KPIs entrada/saída 30d.
4. `[T0]` Kardex só do business; `[perf]` `defer` < 600ms.

#### CU-PROD-12 — Correção de valor / agregação de inventário `[V0]` 🟡 **ausente** (G-03)
1. `[V0]` Agregação **valor em estoque** (Σ preço × qty) e **custo em estoque** (Σ custo × qty) + **margem média** nos KPIs do `/unificado` — hoje zerados.
2. `[V0]` Custo médio recalculado na entrada de compra (SPIKE de descoberta primeiro — a máquina já roda parcialmente, US-PROD-024).
3. `[V0][reg]` Toda conta com **dupla-confirmação (2 caminhos)** + tabela antes→depois + aprovação humana antes de mergear.
4. `[T0]` Agregação nunca soma outro tenant.

### 6.2 Vertical comunicação visual (`CV`)

#### CV-01 — Produto/material precificado por m² 🟡 *criar*
1. Tipo de linha "material/m²" com `preco_custo_m2` · `preco_venda_m2` · `gramatura_g_m2` · `estoque_minimo_m2`.
2. `[V0]` Preço calculado `area_m2 = L × A × qtd` × `preço/m²` — **server-side authoritative** (frontend descartado).
3. `[reg]` Não tratar item por m² como produto de prateleira (qtd × preço fixo).
4. Cota técnica L×A exibida com componente `Dimension`.

#### CV-02 — Insumo consumível com baixa automática 🟡 *criar*
1. Insumo (bobina, tinta, ilhós) com unidade coerente (m², litro, unidade).
2. Baixa automática de insumo ao concluir produção + alerta de reposição (`estoque_minimo`).
3. `[V0]` Baixa por m² consumido não strippa decimal.

#### CV-03 — BOM de produção "desmonta peça por peça" 🟡 *criar*
1. Produto de produção com BOM (lona + tinta + ilhós + mão de obra) → **custo exato** → garante margem.
2. `[V0]` Custo do produto = Σ componentes; margem = (preço − custo)/preço, com dupla-confirmação.
3. Reusa `ProductBom` + integra ao cálculo de preço.

#### CV-04 — Acabamento como item adicional 🟡 *criar*
1. Acabamento (`comvis_acabamentos`) soma ao subtotal do item.
2. Preço de acabamento no cálculo authoritative (`OrcamentoCalculator`).

#### CV-05 — Preço autoritativo via `OrcamentoCalculator` (não digitado) 🟡 *criar*
1. `[V0]` A fonte de preço de comunicação visual é o `OrcamentoCalculator` server-side — o cadastro core **empresta o material**, não duplica a máquina de m².
2. `[T0]` Lookup de `Material` via Model com global scope (filtra business_id).
3. `[reg]` Observações livres redactadas (PII) antes de log/span.

### 6.3 Vertical oficina (`OF`)

#### OF-01 — Peça com aplicação por veículo 🟡 *criar*
1. Peça grava **aplicação** (marca/modelo/ano) — ao lançar na OS, o sistema associa automaticamente ao veículo.
2. Filtro/busca de peça por aplicação (modelo+ano).
3. Placa exibida com componente `PlacaVeiculo`.

#### OF-02 — Código OEM + equivalências 🟡 *criar*
1. Peça com código **OEM** + lista de **equivalentes/similares**.
2. Busca por OEM retorna a peça e suas equivalências.

#### OF-03 — Fornecedor + cotação (melhor preço) 🟡 *criar* (❌ hoje ausente)
1. Peça com N fornecedores + cotação; **melhor preço destacado** no drawer.
2. `[V0]` Custo por fornecedor entra no cálculo de margem sob dupla-confirmação.
3. `insumos()` hoje retorna `fornecedor => null` (TODO) — CU-PROD-26/C18.

#### OF-04 — Serviço / mão de obra (tempo padrão) 🟡 *criar*
1. Tipo de item "serviço/mão de obra" com tempo padrão × valor/hora (`service_order_items.tipo='mao_obra'`).
2. `[V0]` Valor = tempo × valor/hora, sem inflar decimal.

#### OF-05 — Kit de serviço (revisão) 🟡 *criar*
1. Kit "revisão" = BOM de peças + serviços (óleo + filtro + mão de obra).
2. Expandir kit na OS baixa componentes e soma serviços.

### 6.4 Non-goals explícitos (por design, não regressão)

- ❌ Gerar SKU client-side (server confirma no `store()`).
- ❌ Modificar o método `store()` legacy neste escopo (é o refator C2/US-PROD-040 futuro).
- ❌ Variation builder / combo picker inline no Create (Wave 3 — hoje o legacy cobre).
- ❌ Multi-image gallery (1 imagem — paridade legacy).
- ❌ Duplicar a máquina de m² dentro do core — `OrcamentoCalculator` é a fonte (CV-05).
- ❌ Inventar campos de veículo/OEM/fornecedor sem ADR + sinal (ADR 0105).
- ❌ Confundir `Produto` (cadastro interno) com `Modules/ProductCatalogue` (catálogo público + QR).

---

## 7. Requisitos não-funcionais

| Categoria | Alvo | Fonte |
|---|---|---|
| Performance | p95 first-paint < 800ms (Create/Index) · KPIs `defer` < 600ms · kardex `defer` < 600ms | charters |
| Compatibilidade | Cabe em 1280×1024 sem scroll horizontal (Larissa) · mobile usável | Create.charter |
| Confiabilidade | 0 erros JS console em smoke biz=1 · rollback via remover `X-Inertia` (cai no Blade) | RUNBOOK-create §5 |
| Segurança | Tier 0 `business_id` em tudo · 404 cross-tenant · `can:product.view` no `/unificado` (G-05) | ADR 0093 |
| **Valor/estoque (V0)** | **Dupla-confirmação (2 caminhos) + antes→depois + aprovação humana** em preço/custo/margem/multiplicador/valor-em-estoque · `num_uf` guard em todo campo monetário | proibicoes REGRA MESTRE |
| Governança | SPEC + `casos.md` ancoram todo teste (não-tautológico) · 8 charters `draft`→`live` com smoke | ADR 0094 §5 · US-PROD-020/023 |

---

## 8. Estratégia de qualidade e rollout

### 8.1 Testes
- **Pest Feature** em `tests/Feature/Produto/`: baseline por página (`Wave2CreateBaselineTest`, `Wave2CreateInertiaTest`), guards de multi-tenant, estruturais de página.
- **Suítes-alvo do SDD** (ancoradas no SPEC/`casos.md`): `ProdutoValorGuardTest` (`[V0]` — `num_uf`, multiplicador, valor-em-estoque, **dupla-confirmação**), `ProdutoKardexTest` (CU-PROD-11), `ProdutoTenantGuardTest` (CU-PROD-10), `ProdutoMaterialM2Test` (CV-01..05), `ProdutoPecaOficinaTest` (OF-01..05). Todo teste `[reg]` é **failing-first**.
- **Guards mecânicos de design:** `ds-guard.mjs` + `integrity-check.mjs` + PRE-MERGE-UI.
- **`casos-gate`** (US-PROD-020) trava UC crítico — elemento some → build vermelho.

### 8.2 Rollout canônico
1. Branch dual `X-Inertia` no controller — flag OFF = Blade legacy, zero impacto.
2. Baseline Pest ANTES de mudar comportamento (incluir `[V0]` guards).
3. Smoke **biz=1, NUNCA biz=4** ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).
4. `review.md` por tela → promover charter `draft`→`live` (US-PROD-023).
5. **Qualquer mudança Tier 0 valor/estoque** — dupla-confirmação + tabela antes→depois + aprovação Wagner **antes** de mergear.
6. Canary Wagner (biz=1) → habilitar ROTA LIVRE → monitor → remover Blade legacy.

---

## 9. Riscos e dívidas conhecidas

| Item | Risco | Mitigação/Plano |
|---|---|---|
| **`num_uf` em preço/custo (mesmo vetor do incidente Sells ×100k)** | **Inflar valor/estoque em todo o ERP** (Produto alimenta Sells+Compras+Fiscal) | `[V0]` guard em todo campo monetário · dupla-confirmação · teste E2E ancorado no SPEC (G-04) |
| Multiplicador de tabela oco (`mult=1.00`) | Preço por tabela aparenta funcionar mas é 1:1 | ADR ARQ-0001 + dupla-confirmação (G-02 / CU-PROD-03) |
| Kardex fachada (grade 47) | Larissa não audita movimento de estoque na UI nova | Passar `movements` no render Inertia (G-01 / CU-PROD-11) |
| Valor/custo em estoque ausente | Sem visão de inventário; KPIs zerados | SPIKE de descoberta antes de codar (US-PROD-024 / CU-PROD-12) |
| 8 telas `draft`, 0 `live` | UI de produto inteira atrás de flag; `module-grade 71` mede telas que nem entraram em prod | Promover `draft`→`live` com smoke biz=1 (US-PROD-023) |
| Sem SPEC/`casos.md` até 2026-07 | Teste vira tautológico (proibicoes §5) | SPEC criado (G-04); `casos.md` = US-PROD-020 (pré-req dos testes de valor) |
| Verticais desconectadas do core | Banner não é cadastrável por m²; peça sem aplicação/OEM/fornecedor | Trilhas §10.2 (comvis) e §10.3 (oficina) — medir sinal (ADR 0105) |
| `/products/unificado` sem `can:product.view` | Gap de permissão (TODO no código) | G-05 |

---

## 10. Roadmap de evolução

> Diagnóstico (FICHA §10): o registro-mãe tem **cadastro/variação/import de nível de mercado** (61/100), mas é fraco nos **eixos de valor/estoque** (kardex fachada, multiplicador oco, valor-em-estoque ausente) e ainda **não expressa as duas verticais** nativamente. Priorização respeita [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — o heatmap Firebird e o mockup Cowork já são sinal.

### 10.1 Construção (engenharia — destrava o resto)

| ID | Melhoria | Motivação | Amarração |
|---|---|---|---|
| **C1** | Criar **`casos.md`** das telas críticas (Create, SellingPrices, StockHistory) + ligar ao `casos-gate` | Sem rede de casos, teste de valor vira tautológico | US-PROD-020 · pré-req de tudo |
| **C2** | **`[V0]` guard `num_uf`** unificado em todo campo monetário do form | Mesmo parser que inflou vendas ×100k | pré-req dos testes de valor |
| **C3** | Extrair **serviço de pricing autoritativo server-side** (custo/markup/multiplicador) reusando `OrcamentoCalculator` | Client hoje calcula; servidor confia no payload | pré-req de G-02/CV-01/OF-03 |
| **C4** | Promover as **8 telas `draft`→`live`** + `can:product.view` no `/unificado` | 0 telas em prod oficialmente | US-PROD-023 / G-05 |

### 10.2 Comunicação visual (perna forte de mercado, desconectada do core)

| ID | Melhoria | Pain/evidência | Amarração |
|---|---|---|---|
| **CV-a** | **Material por m² no cadastro core** (empresta `comvis_materiais`) | Banner não cadastrável por m² hoje | CV-01 · depende C3 |
| **CV-b** | **BOM "desmonta peça por peça"** (lona+tinta+ilhós+mão de obra) → margem exata | Líderes de gráfica fazem; garante margem | CV-03 · reusa `ProductBom` |
| **CV-c** | **Insumo consumível + baixa automática** (bobina/tinta/ilhós) + alerta | "Produção para por falta de material" | CV-02 |
| **CV-d** | Preço via `OrcamentoCalculator` (não digitado) | `OrcamentoCalculator` já é authoritative | CV-05 |

### 10.3 Oficina (peça + serviço — mockup Picker Mecânica pronto)

| ID | Melhoria | Pain/evidência | Amarração |
|---|---|---|---|
| **OF-a** | **Aplicação por veículo** na peça (marca/modelo/ano) | Sistemas de oficina gravam aplicação ao lançar na OS | OF-01 |
| **OF-b** | **OEM + equivalências** na peça | Busca por código original + similar | OF-02 |
| **OF-c** | **Fornecedor/cotação (melhor preço)** no drawer | `insumos()` retorna `fornecedor=null` (❌ único AUSENTE) | OF-03 · C18 |
| **OF-d** | **Serviço/mão de obra** (tempo padrão) como tipo de item | `service_order_items.tipo='mao_obra'` já existe | OF-04 |

### 10.4 Balcão / varejo (base já boa — refinos)

| ID | Melhoria | Amarração |
|---|---|---|
| **B1** | Kardex real na tela React | G-01 / CU-PROD-11 |
| **B2** | Multiplicador/markup por tabela | G-02 / CU-PROD-03 ⚠️ V0 |
| **B3** | Agregação valor/custo em estoque + custo médio (SPIKE primeiro) | G-03 / CU-PROD-12 ⚠️ V0 |
| **B4** | UI de BOM drag-drop + baixa-de-componente do kit | G-06 / CU-PROD-05 |

### 10.5 Sequência recomendada (ondas)

1. **Onda governança:** C1 (`casos.md`) + C2 (`num_uf` guard) — destrava testes de valor não-tautológicos.
2. **Onda estrutural:** C3 (pricing server-side) + C4 (charters `draft`→`live`) — base pra tudo.
3. **Onda kardex/valor:** B1 (kardex real) + B3 (valor em estoque, com SPIKE) — fecha as fachadas visíveis.
4. **Onda comvis:** CV-a + CV-b + CV-c — cadastro core passa a expressar comunicação visual.
5. **Onda oficina:** OF-a..OF-d — peça com aplicação/OEM/fornecedor + serviço.
6. **Paralelo:** B2 (multiplicador ⚠️ V0), B4 (BOM UI).

**Gate de entrada de cada item:** US no SPEC → CU/`casos.md` → Pest failing-first (`[V0]` onde toca valor) → implementação → gates visuais → smoke biz=1 → (se V0) dupla-confirmação + aprovação → canary.

---

## 11. Referências

- **Specs e operacional:** [SPEC.md](SPEC.md) (US-PROD-020..026) · [BRIEFING.md](BRIEFING.md) · [UI-CATALOG.md](UI-CATALOG.md) · RUNBOOKs `_telas/RUNBOOK-produto-{index,create,edit,show,selling-prices,stock-history,bulk-edit}.md`
- **Benchmark de capacidade:** [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (61/100, gaps G-01..G-06) · [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) (✅6/🟡11/❌1)
- **Mockup Cowork / gap vertical:** [produtos-gap.md](produtos-gap.md) (Picker Mecânica) · [produto-index-setor-matrix.md](_telas/produto-index-setor-matrix.md)
- **Charters (contrato por página):** `resources/js/Pages/Produto/*.charter.md`
- **Fronteira verticais:** `Modules/ComunicacaoVisual/Services/OrcamentoCalculator.php` + `Entities/{Material,Substrato,Acabamento}.php` (`comvis_materiais`, preço por m²) · `Modules/OficinaAuto/Entities/ServiceOrderItem.php` (`tipo peca|mao_obra|servico_terceiro`)
- **Design system:** DS v6 handoff (`colors_and_type.css`, componentes `Dimension`/`RegistrationMark`/`ProofFrame`/`ProofStrip`/`PlacaVeiculo`) · [Constituição UI v2 · UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) · [PT-01 Lista](../_DesignSystem/padroes-tela/PT-01-Lista.md) · [PRE-MERGE-UI](../_DesignSystem/PRE-MERGE-UI.md)
- **ADRs centrais:** [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Tier 0 · [0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) MWART · [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) modular por vertical · [0190](../../decisions/0190-primary-button-roxo-universal-295.md) roxo · [ARQ-0001 produto](adr/arq/0001-selling-price-multiplier.md) multiplicador (proposed)
- **Estado-da-arte (mercado, §0/§1.0):** sistemas de gráfica com preço automático por m² + "desmonta peça por peça" (calcgraf.com.br, alfanetworks.com.br) · sistemas de oficina com aplicação por veículo + OEM + fornecedor + mão de obra (oficinaintegrada.com.br, gestaoclick.com.br)
- **Documento-modelo:** [SDD — Tela de Vendas (família Sells)](../Sells/SDD-tela-vendas-FINAL-v1.2.md)

---

**Histórico:** 2026-07-10 — SDD v1.0.0 criado com referência no SDD de Vendas, especializado para o cadastro de produto e as duas verticais do oimpresso (comunicação visual + oficina). Fontes cruzadas: código real + CAPTERRA (61/100) + mockup Cowork + estado-da-arte de mercado. [CC]
