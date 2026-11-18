---
module: Manufacturing
purpose: "Fabricação: cadastro da receita (ficha técnica) e do custo de produzir, consultas de ordens, insumos e relatório. Pela D1 (ADR 0448, aceita) a ordem nasce na Produção e a aba de ordens vira consulta; enquanto a etapa de unificação não sai, a ordem Blade antiga (transactions.type=production_purchase) segue aqui, consertada (B1)."
migracao_ui: "pendente — tem Blade servido, sem duvida de escopo; fila em module-surface --migracao"
contains:
  - "RecipeController — receitas (/manufacturing/recipe), editor de ingredientes (/add-ingredient), janela Nova receita, busca de insumo, aba Insumos (/insumos), detalhe da receita para a ordem Blade e o Atualizar preço de venda (/update-product-prices)"
  - "ProductionController — ordens: lista e painel lateral (Inertia), relatório do período (/report), e o formulário Blade da ordem (create/store/edit/update/destroy) enquanto a D1 não o tira daqui"
  - "SettingsController — configurações do módulo (index Inertia + store), exige manufacturing.access_production"
  - "DataController — hooks UltimatePOS (pacote, permissões, menu, linha de produção no relatório de lucros e perdas)"
  - "InstallController — instalação do módulo"
  - "ManufacturingController — scaffold sem rota e sem view (index/create/show/edit apontam views que não existem, exceto index.blade); candidato a remoção"
  - "RecipeBomService — custo da receita recalculado na leitura, lista com custo, insumos e impacto reverso, editor da ficha; implementa app/Contracts/Fabricacao/ReceitaDoProduto"
  - "ProductionService — linhas da lista de ordens, indicadores com filtro, detalhe da ordem e relatório por produto"
  - "InsumosDaReceitaService — implementa app/Contracts/Fabricacao/InsumosDaReceita: insumos de uma receita para uma quantidade, lido pela Produção (reserva, falta, materiais da etapa)"
  - "ProdutosDeReceitaService — implementa app/Contracts/Fabricacao/ProdutosDeReceita: quais produtos têm receita, lido pelo cadastro de produto do núcleo"
  - "Support/OcultaCusto — tira custo das respostas quando falta view_purchase_price (lote 2.6)"
  - "Support/EmpresaDaTrilhaDaReceita — business_id da trilha (activity_log) da receita"
not_contains:
  - "Ordem de produção nova (por item da venda ou para estoque), apontamento, refugo e custo real → Modules/Producao (D1, ADR 0448)"
  - "Composição usada pelo FSM da venda (product_bom, BomResolver) → núcleo app/Domain/Inventory; pela D2 passa a ser a fonte única da composição"
  - "Custo do insumo pela regra da empresa (CustoDoInsumo) → Modules/Compras; aqui só é lido se o contrato estiver ligado"
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
depends_on: []
db_tables_owned:
  - mfg_recipes
  - mfg_recipe_ingredients
  - mfg_ingredient_groups
db_tables_consumed:
  - products
  - variations
  - product_variations
  - variation_location_details
  - units
  - categories
  - transactions
  - transaction_sell_lines
  - purchase_lines
  - business
  - business_locations
trust_required: L3
owner: wagner
permission_prefix: manufacturing.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0448-fabricacao-cadastro-producao-execucao-product-bom-fonte-unica
url_prefixes:
  - /manufacturing/*
drift_alerts: []
---

# Modules/Manufacturing

## Missão

Fabricação é **cadastro**: a receita de cada produto fabricado (ingredientes, quantidades,
sub-unidade, custo extra, instruções) e o custo de produzir, recalculado pelo preço de compra de
hoje. Em volta disso, três consultas: ordens já lançadas, insumos com o impacto reverso de um
reajuste, e o relatório de produção do período.

A execução (ordem, apontamento, consumo, refugo, custo real) é da Produção, pela D1 da
[ADR 0448](../../decisions/0448-fabricacao-cadastro-producao-execucao-product-bom-fonte-unica.md),
aceita pelo [W] em 2026-10-13. O formulário Blade da ordem ainda vive aqui até a etapa de
unificação da ADR (§5 e §6); pela decisão B1 do [W] (2026-11-09) ele foi consertado em valor e
estoque enquanto existir (lote 1 da auditoria).

## Dependências

`depends_on` fica vazio de propósito: o módulo não exige outro módulo ligado.

- **Lê do núcleo:** as tabelas de `db_tables_consumed` e os utils `ProductUtil`, `TransactionUtil`,
  `ModuleUtil`, `BusinessUtil`.
- **Lê de Compras, se estiver ligado:** `app/Contracts/Compras/CustoDoInsumo`, consultado com
  `app()->bound(...)` no editor de ingredientes; sem ele o editor segue sem o método de custo.
- **É lido por:** Produção (`InsumosDaReceita`, em `FaltaDeMaterialDaOs`, `MateriaisDaEtapa` e
  `ReservaDeInsumoDaOs`) e pelo `ProductController` do núcleo (`ProdutosDeReceita` e `ReceitaDoProduto`, de
  `app/Contracts/Fabricacao`).

## Isolamento por empresa

`mfg_recipes` e `mfg_recipe_ingredients` não têm `business_id`: a barreira é a cadeia
`variation_id → variations → products.business_id`. `mfg_ingredient_groups` tem `business_id`
próprio, com `HasBusinessScope`. A ordem usa o `business_id` de `transactions`.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.1.0** (2026-11-17) — Reescrito no lote 4.2 da auditoria: `purpose` alinhado com a D1,
  `contains` com services e contratos, `not_contains` com a fronteira da Produção,
  `db_tables_owned`/`db_tables_consumed` medidos no código, `depends_on` declarado vazio.
- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
