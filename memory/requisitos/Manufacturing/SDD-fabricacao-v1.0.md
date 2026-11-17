---
id: requisitos-manufacturing-sdd-fabricacao
module: Manufacturing
title: "SDD — Fabricação (receita, ordem, consumo, estoque, custo, relatório)"
version: "1.0"
status: rascunho
last_updated: "2026-11-17"
---

# SDD — Fabricação · v1.0

> **O que este documento é.** O mapa do domínio da Fabricação no módulo `Manufacturing`, no formato
> do [SDD-TEMPLATE](../_DesignSystem/SDD-TEMPLATE.md). A v1.0 traz só o **§5 — o fluxo de ponta a
> ponta** (lote 4.7 da auditoria do módulo, 2026-11-09). O §6 (casos de uso CU-*) não foi escrito:
> é trabalho do agente `sdd-from-source` com conferência do [W], e até lá os UC das telas derivam do
> handoff.
>
> **O SDD do handoff** (`prototipo-ui/cowork/Felipe/handoff_fabricacao/contexto/SDD-tela-fabricacao-v1.0.md`)
> é o do protótipo, mora no espelho de design e não se edita aqui. Parte dele ficou obsoleta pelas
> decisões D1 e D4 da [ADR 0448](../../decisions/0448-fabricacao-cadastro-producao-execucao-product-bom-fonte-unica.md)
> (CU-MFG-17..20 da ordem; CU-MFG-05 e 14 do desperdício). Onde os dois discordarem, vale o código
> descrito abaixo, depois o que a ADR 0448 decidiu.

## §0 — Base empírica (foto datada — 2026-11-17)

Medido no `origin/main` `18fd05d4bf`, depois dos lotes 0 a 3 da auditoria. Lane
`PHP / Pest (Manufacturing · MySQL)` no main: run 44074565768, 281 passed, 0 skipped. Os UC citados
estão em `resources/js/Pages/Manufacturing/*.casos.md`, exceto o UC-EST-08B, que mora em
`resources/js/Pages/Estoque/Movimentacao.casos.md`.

## §1 — Visão geral

Fabricação é cadastro: a receita de cada produto fabricado e o custo de produzir pelo preço de compra
de hoje. A execução (ordem, apontamento, consumo, refugo, custo real) é da Produção pela D1 da ADR
0448, aceita pelo [W] em 2026-10-13. Até a etapa de unificação da ADR, a ordem ainda é lançada aqui,
num formulário Blade, consertado em valor e estoque pela decisão B1 do [W] (2026-11-09).

## §5 — Arquitetura

### 5.1 Visão em camadas

| Camada | Peças |
|---|---|
| Telas (Inertia) | `Recipes` · `IngredientesEditor` · `Insumos` · `Index` (ordens) com `_components/OrdemDrawer` · `Report` · `Settings` |
| Telas (Blade) | formulário da ordem (`production/create`, `production/{id}/edit`) e as telas antigas por `?legacy=1` em production, report e settings |
| Controllers | `RecipeController` · `ProductionController` · `SettingsController` |
| Services | `RecipeBomService` (custo da receita) · `ProductionService` (lista, indicadores, detalhe, relatório) · `InsumosDaReceitaService` e `ProdutosDeReceitaService` (contratos lidos por fora) |
| Núcleo usado | `ProductUtil::createOrUpdatePurchaseLines` / `decreaseProductQuantity` · `TransactionUtil::createOrUpdateSellLines` / `mapPurchaseSell` |

### 5.2 Modelo de dados (núcleo)

| Tabela | Papel | Empresa |
|---|---|---|
| `mfg_recipes` | cabeçalho da receita: `variation_id` do acabado, `total_quantity` (rendimento), `extra_cost` + `production_cost_type`, `ingredients_cost` e `final_price` gravados pelo servidor | pela cadeia `variation_id → products.business_id` |
| `mfg_recipe_ingredients` | linha: `variation_id` do insumo, `quantity`, `sub_unit_id`, grupo | pela receita |
| `mfg_ingredient_groups` | grupos de ingredientes | `business_id` próprio, `HasBusinessScope` |
| `transactions` `production_purchase` | a ordem: entrada do acabado (`purchase_lines`), `final_total`, `mfg_wasted_units`, `mfg_is_final` | `business_id` |
| `transactions` `production_sell` | filha da ordem: consumo dos insumos (`transaction_sell_lines`, com `mfg_waste_percent`) | `business_id` |

### 5.3 Fluxo de ponta a ponta

```
receita ──► ordem (rascunho) ──► ordem finalizada ──► estoque ──► custo ──► relatório
 editor      store/update          update/store         núcleo     gravado    reportByProduct
```

| # | Passo | O que o código faz | Onde | Travado por |
|---|---|---|---|---|
| 1 | **Receita** | O editor manda ingredientes, quantidades, sub-unidade, rendimento e custo extra. O servidor recusa receita sem ingrediente, quantidade ≤ 0 e sub-unidade que não é do insumo, troca as linhas inteiras e **recalcula** `ingredients_cost` e `final_price` (o do navegador não entra). Abrir pede `access_recipe`; gravar, `add_recipe` | `RecipeController::store` · `RecipeBomService::custoDosIngredientes` / `calculateCost` | UC-RECIPE-14..18 · UC-INGRED-* |
| 2 | **Custo da receita na leitura** | Σ quantidade × `variations.dpp_inc_tax` × multiplicador da sub-unidade, mais o custo extra (% sobre os ingredientes, valor × rendimento, ou fixo). Recalculado a cada leitura; o que está gravado em `mfg_recipes` não é usado na tela. Ingrediente com receita própria entra pelo preço de compra dele, sem descer na receita (ADR ARQ-0003, lote 4.4) | `RecipeBomService::calculateCost` / `listRecipesWithCost` | UC-RECIPE-* · UC-RECIPE-23 |
| 3 | **Ordem** | O formulário Blade monta o consumo a partir da receita; a quantidade de cada insumo vem da linha (editável, salvo `disable_editing_ingredient_qty`). O servidor confere que produto e local são da empresa (404) e que o local é permitido ao usuário (403), e recusa quantidade negativa ou zero antes de gravar | `ProductionController::store` · `garantirProdutoELocalDaEmpresa` · `StoreProductionRequest` | UC-OP-13 · UC-OP-14 · UC-OP-22 · UC-OP-21 |
| 4 | **Total da ordem** | Calculado no servidor a partir das linhas de consumo: Σ consumo × custo do insumo × multiplicador + custo extra. O `final_total` do request é ignorado. Unidades perdidas (`mfg_wasted_units`) saem da quantidade boa; o custo unitário do acabado = total ÷ quantidade boa | `ProductionController::totalDaOrdem` | UC-OP-19 · UC-OP-20 · UC-OP-09 |
| 5 | **Rascunho** | Grava a compra `production_purchase` com status `pending` e a venda `production_sell` com status `draft`. **Não mexe em estoque.** Trocar o local leva a ordem inteira; excluir o rascunho leva a venda filha e deixa trilha | `store` / `update` / `destroy` | UC-OP-18 · UC-OP-23 · UC-OP-17 |
| 6 | **Finalizar** | Numa transação: compra `received` dá entrada no acabado (`createOrUpdatePurchaseLines`), a venda `final` baixa cada insumo com estoque (`decreaseProductQuantity`) e o consumo é ligado aos lotes de compra (`mapPurchaseSell`). Sem lote suficiente o núcleo lança e tudo é desfeito, salvo `pos_settings.allow_overselling`. Não há checagem prévia nem trava | `store` / `update` com `finalize` | UC-EST-08B · UC-OP-16 · UC-OP-15 |
| 7 | **Custo gravado** | O `final_total` da ordem é o custo **do dia** em que foi gravada. As telas de ordens mostram esse valor (sufixo `fix` na finalizada); o painel lateral mostra também o custo pela receita de hoje | `ProductionService::listProductions` / `enrichProductionRows` / `detalheOrdem` | UC-OP-07 · UC-OP-10 |
| 8 | **Relatório** | Agrupa as ordens do período por produto. O custo de cada ordem é **recalculado** pela receita de hoje (`calculateUnitCost` × quantidade), não o gravado. "Só finalizadas" vem ligado | `ProductionService::reportByProduct` | UC-REPORT-03 · UC-REPORT-04 · UC-REPORT-05 |
| 9 | **Impacto reverso** | A aba Insumos mostra quais receitas usam um insumo e o custo delas com o preço simulado (−30% a +60%) | `RecipeBomService::usosDoInsumo` / `listInsumosComUso` | UC-INS-* |

**Quem vê o quê:** receita, editor e Insumos pedem `manufacturing.access_recipe`; ordens, relatório e
configurações, `manufacturing.access_production` (decisão B3). Sem `view_purchase_price`, o servidor
tira custo de todas as respostas (`Support/OcultaCusto`, lote 2.6).

### 5.4 Onde os dois mundos ainda não se conversam

- **Ordem duas vezes.** A ordem desta tela (`production_purchase`) e a OS da Produção (`pcp_ordens`)
  existem lado a lado. Pela ADR 0448 §5, a ordem passa a ser só `pcp_ordens` e o
  `production_purchase` vira o lançamento de entrada que a conclusão gera.
- **Composição duas vezes.** A receita (`mfg_recipes`) alimenta a Produção pelo contrato
  `InsumosDaReceita`; o FSM da venda lê o `product_bom` pelo `BomResolver`. Pela D2, o `product_bom`
  vira a fonte única.
- **Desperdício.** A D4 tirou o desperdício da receita nas telas (2026-10-25), mas a ordem Blade
  ainda grava `mfg_waste_percent` por linha.
- **Custo gravado × recalculado.** Ordens mostram o custo do dia; Relatório e Receitas, o de hoje.
  É escolha declarada de cada tela, não divergência de conta.
- **"Lançado no Financeiro".** O rodapé do Relatório diz que o custo é lançado como entrada de
  estoque no Financeiro. Medido em 2026-11-17: o `TransactionObserver` do Financeiro cobre `sell` e
  `purchase`, não `production_purchase` (0 menções em `Modules/Financeiro` fora de teste). O valor
  entra no relatório de lucros e perdas do núcleo pelo `DataController::profitLossReportData`, não
  em título do Financeiro. A frase é texto verbatim do DoD da US-MANU-002; mudar é decisão do [W].

## §9 — Riscos e dívidas

- Finalizar com insumo em falta depende do `allow_overselling` da empresa: com ele ligado, o saldo
  fica negativo sem aviso do servidor.
- Duas ordens finalizadas ao mesmo tempo sobre o mesmo insumo não se travam (sem `lockForUpdate`).
- Ordens gravadas antes do lote 1 (2026-11-12) podem ter total vindo do navegador e quantidade
  em sub-unidade convertida duas vezes. Em produção eram 7 ordens, 0 com sub-unidade (medido
  2026-11-11).

## §11 — Referências

- [SPEC](SPEC.md) · [SCOPE](SCOPE.md) · [RUNBOOK](RUNBOOK.md) e os RUNBOOKs de tela
- [ADR 0448](../../decisions/0448-fabricacao-cadastro-producao-execucao-product-bom-fonte-unica.md) — D1 a D6
- ADR ARQ-0003 do módulo (`adr/arq/0003-receita-ordem-e-mrp-como-o-codigo-faz.md`, PR #11100, proposta)
- [PARIDADE-ordem-producao-blade-vs-prototipo](PARIDADE-ordem-producao-blade-vs-prototipo.md)
- Fronteira do lado da Produção: [Producao/SCOPE.md](../Producao/SCOPE.md)
