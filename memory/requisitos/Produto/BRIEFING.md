---
id: requisitos-produto-briefing
module: Produto
status: parcial
updated_at: "2026-07-20"
owner: W
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0104-processo-mwart-canonico-unico-caminho", "0121-oimpresso-modular-especializado-por-vertical"]
---

# BRIEFING — Produto

Produto é a porta de entrada do cadastro interno que alimenta preço, custo, variação, disponibilidade e composição no ERP. O domínio reúne o registro do produto e seus cadastros auxiliares, mantém a convivência entre o Blade herdado e as Pages Inertia/React e oferece seus dados aos fluxos de venda, compra, estoque, produção, integrações e verticais; estes consumidores são âncoras relacionadas, não partes absorvidas pelo módulo.

## Origem e linhagem

O domínio nasceu no núcleo herdado do UltimatePOS e recebeu requisitos de paridade do WR Comercial/OfficeImpresso em Delphi. A estratégia do oimpresso foi estender o núcleo Laravel e conectá-lo às verticais, sem reescrever o legado como um único módulo novo. A história convergida está em [História & Linhagem](../../HISTORIA-LINHAGEM.md); a paridade específica do cadastro está em [PARIDADE-charter-vs-legado.md](PARIDADE-charter-vs-legado.md), [ANTI-REGRESSAO-cadastro-produto-legacy.md](ANTI-REGRESSAO-cadastro-produto-legacy.md) e [ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md](ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md).

## Mapa do módulo

> O mapa interpreta a estrutura encontrada; estado por tela, cobertura, notas e contagens pertencem aos geradores indicados em [Estado vivo e anti-apodrecimento](#estado-vivo-e-anti-apodrecimento).

| Área | Controller | Blade | React | Achado |
|---|---|---|---|---|
| Catálogo e consulta | `ProductController@index/show` | `product/index`, `product/show`, modais | `Produto/Index`, `Produto/Show` | 🟡 Coexistência Blade↔Inertia; a fonte de rollout é o charter, não este resumo |
| Cadastro e manutenção | `ProductController@create/store/edit/update` | `product/create`, `product/edit` + partials | `Produto/Create`, `Produto/Edit` | 🔴 A casca React ainda não cobre toda a função de produto simples, variável e combo já disponível no Blade |
| Variações e formação de preço | `ProductController`, `VariationTemplateController`, `SellingPriceGroupController` | `product/partials/*`, `variation/*`, `selling_price_group/*` | `Produto/SellingPrices` | 🟡 A persistência por variação e tabela existe; o contrato regra+exceção do protótipo ainda não é a implementação React |
| Operações em massa e importação | `ProductController`, `ImportProductsController` | `product/bulk-edit`, `import_products/index` | `Produto/BulkEdit` | 🟡 Fluxo dual; importação permanece Blade |
| Estoque inicial e histórico | `OpeningStockController`, `ImportOpeningStockController`, `ProductController@productStockHistory` | `opening_stock/*`, `import_opening_stock/index`, `product/stock_history*` | `Produto/StockHistory` | 🔴 A Page espera movimentos que o render não fornece; o caminho AJAX legado também reconcilia saldo durante um GET |
| Cadastros auxiliares | `BrandController`, `TaxonomyController`, `UnitController`, `WarrantyController` | `brand/*`, `taxonomy/*`, `unit/*`, `warranties/*` | — | 🟡 São parte da entrada de Produto, mas continuam em Blade |
| Cockpit unificado | `ProdutoUnificadoController` | — | `Produto/Unificado/Index` | 🔴 Possui dados placeholder, dependência de relação ausente em `Category` e rota com permissão ainda marcada como TODO |
| Composição/BOM | `Inventory/ProductBomController` | combo legado nos partials de produto | consumido pelo cockpit/protótipos | 🟡 API normalizada e fallback de combo existem; a experiência de edição é backlog |
| API e integrações | controllers e transformers do `Modules/Connector` | — | consumers externos | 🟡 Leitura REST existe; há contrato de request para criação sem rota de criação correspondente |
| Isolamento multi-tenant | filtros distribuídos pelos controllers e `HasBusinessScope` no BOM | — | — | 🔴 `App\Product` e models legacy relacionados não aplicam o global scope exigido pela ADR 0093; a proteção atual depende de filtros explícitos e contratos pontuais |

## Fronteiras

**Core Produto:** cadastro e consulta; produto simples, variável e combo; marcas, categorias, unidades, modelos de variação e garantias; custo e preço por variação; tabelas de preço; importação; estoque inicial; histórico por produto; BOM diretamente ligado ao produto; superfícies Blade, React, JS e Connector correspondentes.

**Âncoras relacionadas:** Compras, Vendas/PDV, relatórios, ajuste e transferência de estoque, Manufacturing, WooCommerce, Repair/OficinaAuto, ComunicacaoVisual, Officeimpresso e FSM de reserva/consumo. O BRIEFING aponta para a integração, mas não incorpora os controllers e as telas internas desses domínios.

**Outro módulo:** [ProductCatalogue](../ProductCatalogue/BRIEFING.md) é o catálogo público com QR e rotas próprias. Ele consome Produto, mas não é o cadastro interno.

## Superfície de código

### Controllers e rotas

- Núcleo: [ProductController.php](../../../app/Http/Controllers/ProductController.php) · [ProdutoUnificadoController.php](../../../app/Http/Controllers/ProdutoUnificadoController.php) · [ProductBomController.php](../../../app/Http/Controllers/Inventory/ProductBomController.php).
- Cadastros auxiliares: [BrandController.php](../../../app/Http/Controllers/BrandController.php) · [TaxonomyController.php](../../../app/Http/Controllers/TaxonomyController.php) · [UnitController.php](../../../app/Http/Controllers/UnitController.php) · [VariationTemplateController.php](../../../app/Http/Controllers/VariationTemplateController.php) · [WarrantyController.php](../../../app/Http/Controllers/WarrantyController.php) · [SellingPriceGroupController.php](../../../app/Http/Controllers/SellingPriceGroupController.php).
- Importação e estoque inicial: [ImportProductsController.php](../../../app/Http/Controllers/ImportProductsController.php) · [OpeningStockController.php](../../../app/Http/Controllers/OpeningStockController.php) · [ImportOpeningStockController.php](../../../app/Http/Controllers/ImportOpeningStockController.php).
- WEB: [routes/web.php](../../../routes/web.php). O menu correspondente é montado em [AdminSidebarMenu.php](../../../app/Http/Middleware/AdminSidebarMenu.php).
- API Connector: [Routes/api.php](../../../Modules/Connector/Routes/api.php) · [Api/ProductController.php](../../../Modules/Connector/Http/Controllers/Api/ProductController.php) · [Api/BrandController.php](../../../Modules/Connector/Http/Controllers/Api/BrandController.php) · [Api/CategoryController.php](../../../Modules/Connector/Http/Controllers/Api/CategoryController.php) · [Api/UnitController.php](../../../Modules/Connector/Http/Controllers/Api/UnitController.php) · [Api/CommonResourceController.php](../../../Modules/Connector/Http/Controllers/Api/CommonResourceController.php) · [Api/ProductSellController.php](../../../Modules/Connector/Http/Controllers/Api/ProductSellController.php).

### Motor e domínio

- Motor principal: [ProductUtil.php](../../../app/Utils/ProductUtil.php).
- BOM: [ProductBom.php](../../../app/Domain/Inventory/Models/ProductBom.php) · [BomResolver.php](../../../app/Domain/Inventory/Services/BomResolver.php).
- Integração com reserva e baixa: [ReservarEstoque.php](../../../app/Domain/Fsm/SideEffects/ReservarEstoque.php) · [ConsumirEstoque.php](../../../app/Domain/Fsm/SideEffects/ConsumirEstoque.php).
- Evento e exportação: [ProductsCreatedOrModified.php](../../../app/Events/ProductsCreatedOrModified.php) · [ProductsExport.php](../../../app/Exports/ProductsExport.php).

### Models e persistência

- Registro e variações: [Product.php](../../../app/Product.php) · [ProductVariation.php](../../../app/ProductVariation.php) · [Variation.php](../../../app/Variation.php) · [VariationLocationDetails.php](../../../app/VariationLocationDetails.php) · [VariationGroupPrice.php](../../../app/VariationGroupPrice.php) · [ProductRack.php](../../../app/ProductRack.php).
- Cadastros auxiliares: [Brands.php](../../../app/Brands.php) · [Category.php](../../../app/Category.php) · [Unit.php](../../../app/Unit.php) · [SellingPriceGroup.php](../../../app/SellingPriceGroup.php) · [VariationTemplate.php](../../../app/VariationTemplate.php) · [VariationValueTemplate.php](../../../app/VariationValueTemplate.php) · [Warranty.php](../../../app/Warranty.php) · [ProdutoGrupo.php](../../../app/ProdutoGrupo.php).
- Evolução do schema: [database/migrations](../../../database/migrations/). O histórico inclui as tabelas do catálogo, variações, estoque por localização, grupos de preço, garantias, campos Officeimpresso e BOM.

### Views e client

- Blade central e partials: [resources/views/product](../../../resources/views/product/).
- Blade auxiliar: [brand](../../../resources/views/brand/) · [taxonomy](../../../resources/views/taxonomy/) · [unit](../../../resources/views/unit/) · [variation](../../../resources/views/variation/) · [warranties](../../../resources/views/warranties/) · [selling_price_group](../../../resources/views/selling_price_group/) · [opening_stock](../../../resources/views/opening_stock/) · [import_products](../../../resources/views/import_products/) · [import_opening_stock](../../../resources/views/import_opening_stock/).
- Pages, charters e casos: [resources/js/Pages/Produto](../../../resources/js/Pages/Produto/).
- Client legado: [public/js/product.js](../../../public/js/product.js) · [public/js/opening_stock.js](../../../public/js/opening_stock.js).
- Contrato TS reutilizável: [resources/js/Types/api-schemas/products.ts](../../../resources/js/Types/api-schemas/products.ts).
- Traduções-base PT: [product.php](../../../lang/pt/product.php) · [brand.php](../../../lang/pt/brand.php) · [category.php](../../../lang/pt/category.php) · [unit.php](../../../lang/pt/unit.php).

## Estado vivo e anti-apodrecimento

- Telas, charters e rollout: consulte [UI-CATALOG.md](UI-CATALOG.md) e confirme cada tela com `npm run screen:files -- Produto/<Tela>`, implementado por [screen-coverage-map.mjs](../../../scripts/qa/screen-coverage-map.mjs). O catálogo é artefato gerado; divergência com o código deve ser corrigida no gerador ou regenerada, não copiada para cá.
- Casos e cobertura: os contratos ficam ao lado das Pages em [resources/js/Pages/Produto](../../../resources/js/Pages/Produto/) e os testes em [tests/Feature/Produto](../../../tests/Feature/Produto/). A catraca/CI é a fonte do que está efetivamente coberto.
- Nota e comparativos: execute `php artisan module:grade Produto` ou `php artisan module:grade-v4 Produto`; [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) e [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) guardam a análise, não o estado operacional deste BRIEFING.
- Backlog vivo: [SPEC.md](SPEC.md) é o contrato de US; estado de execução pertence ao board/MCP. Não inferir `todo`, `review`, `done`, `draft` ou `live` a partir deste arquivo.

**Recibo:** superfície revarrida em 2026-07-20 sobre `origin/main@58447832` via `git`, `rg`, `npm run screen:files`, histórico de PRs e handoffs recentes. O recibo prova a varredura desta edição; não congela contagens.

## Estado: entregue e por fazer

### Entregas históricas fechadas

- Migração dual Blade→Inertia do núcleo de telas: [PR #928](https://github.com/wagnerra23/oimpresso.com/pull/928).
- Sinal repetível de promoção do catálogo: [PR #4155](https://github.com/wagnerra23/oimpresso.com/pull/4155).
- Anti-regressão, SDD e paridade com o legado: [PR #4260](https://github.com/wagnerra23/oimpresso.com/pull/4260).
- Contrato e proteção multi-tenant da tabela de preço: [PR #4300](https://github.com/wagnerra23/oimpresso.com/pull/4300).
- Protótipo e charter do modelo regra+exceção: [PR #4403](https://github.com/wagnerra23/oimpresso.com/pull/4403).
- Casos e contrato do cadastro: [PR #4417](https://github.com/wagnerra23/oimpresso.com/pull/4417).
- Correção da premissa do multiplicador: [PR #4464](https://github.com/wagnerra23/oimpresso.com/pull/4464).
- Decisão dos campos de custo, margem e valor: [PR #4471](https://github.com/wagnerra23/oimpresso.com/pull/4471).
- Proteção de FK cross-tenant no cadastro: [PR #4554](https://github.com/wagnerra23/oimpresso.com/pull/4554).

### Backlog contratual

O backlog completo e seus critérios estão em [SPEC.md](SPEC.md). As frentes registradas incluem completar os trios de domínio, tornar o histórico React funcional, fechar a paridade do cadastro, evoluir regra de preço, descobrir a fonte de custo/valor em estoque antes de alterar cálculo, entregar edição de BOM e conectar fornecedor/cotação. Mudança em preço, custo, margem ou estoque segue a REGRA MESTRE de [proibicoes.md](../../proibicoes.md): dupla confirmação, impacto antes→depois e aprovação humana.

## Índice A–H

### A · Entrada e contrato

- [BRIEFING.md](BRIEFING.md) · [SPEC.md](SPEC.md) · [SDD-tela-cadastro-produto-v1.0.md](SDD-tela-cadastro-produto-v1.0.md) · [UI-CATALOG.md](UI-CATALOG.md).

### B · Paridade e regras de negócio

- [PARIDADE-charter-vs-legado.md](PARIDADE-charter-vs-legado.md) · [ANTI-REGRESSAO-cadastro-produto-legacy.md](ANTI-REGRESSAO-cadastro-produto-legacy.md) · [ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md](ANTI-REGRESSAO-cadastro-produto-variacao-legacy.md).

### C · Código de interface

- [Pages/Produto](../../../resources/js/Pages/Produto/) · [views/product](../../../resources/views/product/) · [product.js](../../../public/js/product.js) · [opening_stock.js](../../../public/js/opening_stock.js).

### D · Backend, motor e API

- [ProductController.php](../../../app/Http/Controllers/ProductController.php) · [ProductUtil.php](../../../app/Utils/ProductUtil.php) · [routes/web.php](../../../routes/web.php) · [Connector/Routes/api.php](../../../Modules/Connector/Routes/api.php).

### E · Dados, estoque e composição

- [Product.php](../../../app/Product.php) · [Variation.php](../../../app/Variation.php) · [VariationLocationDetails.php](../../../app/VariationLocationDetails.php) · [VariationGroupPrice.php](../../../app/VariationGroupPrice.php) · [ProductBom.php](../../../app/Domain/Inventory/Models/ProductBom.php) · [BomResolver.php](../../../app/Domain/Inventory/Services/BomResolver.php).

### F · Qualidade e operação

- [tests/Feature/Produto](../../../tests/Feature/Produto/) · [Modules/Connector/Tests](../../../Modules/Connector/Tests/) · [_telas](_telas/) · [screen-coverage-map.mjs](../../../scripts/qa/screen-coverage-map.mjs).

### G · Design e protótipos

- [PROTOTIPO-preco-especial.md](PROTOTIPO-preco-especial.md) · [produto-preco-especial](../../../prototipo-ui/cowork/produto-preco-especial/) · [produtos-page.jsx](../../../prototipo-ui/cowork/produtos-page.jsx) · [protótipo Produto Unificado](../../../prototipo-ui/cowork/prototipo-ui-patch/prototipos/produto/) · [produtos-gap.md](produtos-gap.md).

### H · Decisões e histórico

- [ADR local de estratégia de tabela](adr/arq/0001-selling-price-multiplier.md) · [História & Linhagem](../../HISTORIA-LINHAGEM.md) · [handoff do mapa vivo e UC-PCAD-05](../../handoffs/2026-07-19-2130-mapa-vivo-resolver-uc-pcad-05.md) · [handoff da tabela de preço](../../handoffs/2026-07-15-1930-produto-tabela-preco-trio-tier0.md) · [handoff do protótipo de preço especial](../../handoffs/2026-07-16-1930-produto-preco-especial-f1-charter-v3.md).

---

**Assinatura:** [M+CC]
