---
name: "SUPERFÍCIE — Manufacturing"
description: "Índice GERADO dos artefatos do módulo Manufacturing reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Manufacturing
---

# 🗺️ Superfície de código — Manufacturing

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Manufacturing --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Manufacturing/**` + `resources/js/Pages/Manufacturing/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 82 arquivos em 15 papéis.

## Controllers — 6

- [DataController.php](../../../Modules/Manufacturing/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Manufacturing/Http/Controllers/InstallController.php)
- [ManufacturingController.php](../../../Modules/Manufacturing/Http/Controllers/ManufacturingController.php)
- [ProductionController.php](../../../Modules/Manufacturing/Http/Controllers/ProductionController.php)
- [RecipeController.php](../../../Modules/Manufacturing/Http/Controllers/RecipeController.php)
- [SettingsController.php](../../../Modules/Manufacturing/Http/Controllers/SettingsController.php)

## Requests (validação) — 7

- [DestroyRecipeRequest.php](../../../Modules/Manufacturing/Http/Requests/DestroyRecipeRequest.php)
- [StoreIngredientGroupRequest.php](../../../Modules/Manufacturing/Http/Requests/StoreIngredientGroupRequest.php)
- [StoreProductionRequest.php](../../../Modules/Manufacturing/Http/Requests/StoreProductionRequest.php)
- [StoreRecipeRequest.php](../../../Modules/Manufacturing/Http/Requests/StoreRecipeRequest.php)
- [UpdateIngredientGroupRequest.php](../../../Modules/Manufacturing/Http/Requests/UpdateIngredientGroupRequest.php)
- [UpdateProductionRequest.php](../../../Modules/Manufacturing/Http/Requests/UpdateProductionRequest.php)
- [UpdateRecipeRequest.php](../../../Modules/Manufacturing/Http/Requests/UpdateRecipeRequest.php)

## Services — 2

- [ProductionService.php](../../../Modules/Manufacturing/Services/ProductionService.php)
- [RecipeBomService.php](../../../Modules/Manufacturing/Services/RecipeBomService.php)

## Models / Entities — 3

- [MfgIngredientGroup.php](../../../Modules/Manufacturing/Entities/MfgIngredientGroup.php)
- [MfgRecipe.php](../../../Modules/Manufacturing/Entities/MfgRecipe.php)
- [MfgRecipeIngredient.php](../../../Modules/Manufacturing/Entities/MfgRecipeIngredient.php)

## Console / Commands — 1

- [ManufacturingHealthCommand.php](../../../Modules/Manufacturing/Console/Commands/ManufacturingHealthCommand.php)

## Providers — 2

- [ManufacturingServiceProvider.php](../../../Modules/Manufacturing/Providers/ManufacturingServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Manufacturing/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Manufacturing/Routes/api.php)
- [web.php](../../../Modules/Manufacturing/Routes/web.php)

## Migrations (schema) — 13

- [2019_07_15_114211_add_manufacturing_module_version_to_system_table.php](../../../Modules/Manufacturing/Database/Migrations/2019_07_15_114211_add_manufacturing_module_version_to_system_table.php)
- [2019_07_15_114403_create_mfg_recipes_table.php](../../../Modules/Manufacturing/Database/Migrations/2019_07_15_114403_create_mfg_recipes_table.php)
- [2019_07_18_180217_add_production_columns_to_transactions_table.php](../../../Modules/Manufacturing/Database/Migrations/2019_07_18_180217_add_production_columns_to_transactions_table.php)
- [2019_07_26_110753_add_manufacturing_settings_column_to_business_table.php](../../../Modules/Manufacturing/Database/Migrations/2019_07_26_110753_add_manufacturing_settings_column_to_business_table.php)
- [2019_07_26_170450_add_manufacturing_permissions.php](../../../Modules/Manufacturing/Database/Migrations/2019_07_26_170450_add_manufacturing_permissions.php)
- [2019_08_08_110035_create_mfg_recipe_ingredients_table.php](../../../Modules/Manufacturing/Database/Migrations/2019_08_08_110035_create_mfg_recipe_ingredients_table.php)
- [2019_08_08_172837_add_recipe_add_edit_permissions.php](../../../Modules/Manufacturing/Database/Migrations/2019_08_08_172837_add_recipe_add_edit_permissions.php)
- [2019_08_12_114610_add_ingredient_waste_percent_columns.php](../../../Modules/Manufacturing/Database/Migrations/2019_08_12_114610_add_ingredient_waste_percent_columns.php)
- [2019_11_05_115136_create_ingredient_groups_table.php](../../../Modules/Manufacturing/Database/Migrations/2019_11_05_115136_create_ingredient_groups_table.php)
- [2020_02_22_120303_add_column_to_mfg_recipe_ingredients_table.php](../../../Modules/Manufacturing/Database/Migrations/2020_02_22_120303_add_column_to_mfg_recipe_ingredients_table.php)
- [2020_08_19_103831_add_production_cost_type_to_recipe_and_transaction_table.php](../../../Modules/Manufacturing/Database/Migrations/2020_08_19_103831_add_production_cost_type_to_recipe_and_transaction_table.php)
- [2021_02_16_190302_add_manufacturing_module_indexing.php](../../../Modules/Manufacturing/Database/Migrations/2021_02_16_190302_add_manufacturing_module_indexing.php)
- [2021_04_07_154331_add_mfg_ingredient_group_id_to_transaction_sell_lines_table.php](../../../Modules/Manufacturing/Database/Migrations/2021_04_07_154331_add_mfg_ingredient_group_id_to_transaction_sell_lines_table.php)

## Seeders — 1

- [ManufacturingDatabaseSeeder.php](../../../Modules/Manufacturing/Database/Seeders/ManufacturingDatabaseSeeder.php)

## Config — 2

- [config.php](../../../Modules/Manufacturing/Config/config.php)
- [retention.php](../../../Modules/Manufacturing/Config/retention.php)

## Views (Blade) — 20

- 20 arquivos em [Modules/Manufacturing/Resources/views/](../../../Modules/Manufacturing/Resources/views) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Telas (Inertia/React) — 1

- [Index.tsx](../../../resources/js/Pages/Manufacturing/Index.tsx)

## Charters (lei da tela) — 1

- [Index.charter.md](../../../resources/js/Pages/Manufacturing/Index.charter.md)

## Testes (Pest) — 17

- 17 arquivos em [Modules/Manufacturing/Tests/Feature/](../../../Modules/Manufacturing/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 4

- [AssertsBusinessChain.php](../../../Modules/Manufacturing/Concerns/AssertsBusinessChain.php)
- [HasManufacturingProductChain.php](../../../Modules/Manufacturing/Concerns/HasManufacturingProductChain.php)
- [LogsWithPiiRedactor.php](../../../Modules/Manufacturing/Concerns/LogsWithPiiRedactor.php)
- [ManufacturingUtil.php](../../../Modules/Manufacturing/Utils/ManufacturingUtil.php)
