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
> **O que isto é:** o inventário completo das raízes `Modules/Manufacturing/**` + `resources/js/Pages/Manufacturing/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](../../../Modules/Manufacturing/SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 117 arquivos em 15 papéis.

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

- [index.blade.php](../../../Modules/Manufacturing/Resources/views/index.blade.php)
- [master.blade.php](../../../Modules/Manufacturing/Resources/views/layouts/master.blade.php)
- [nav.blade.php](../../../Modules/Manufacturing/Resources/views/layouts/nav.blade.php)
- [common_script.blade.php](../../../Modules/Manufacturing/Resources/views/layouts/partials/common_script.blade.php)
- [sidebar.blade.php](../../../Modules/Manufacturing/Resources/views/layouts/partials/sidebar.blade.php)
- [create.blade.php](../../../Modules/Manufacturing/Resources/views/production/create.blade.php)
- [edit.blade.php](../../../Modules/Manufacturing/Resources/views/production/edit.blade.php)
- [index.blade.php](../../../Modules/Manufacturing/Resources/views/production/index.blade.php)
- [production_script.blade.php](../../../Modules/Manufacturing/Resources/views/production/production_script.blade.php)
- [report.blade.php](../../../Modules/Manufacturing/Resources/views/production/report.blade.php)
- [show.blade.php](../../../Modules/Manufacturing/Resources/views/production/show.blade.php)
- [add_ingredients.blade.php](../../../Modules/Manufacturing/Resources/views/recipe/add_ingredients.blade.php)
- [create.blade.php](../../../Modules/Manufacturing/Resources/views/recipe/create.blade.php)
- [index.blade.php](../../../Modules/Manufacturing/Resources/views/recipe/index.blade.php)
- [ingredient_group.blade.php](../../../Modules/Manufacturing/Resources/views/recipe/ingredient_group.blade.php)
- [ingredient_row.blade.php](../../../Modules/Manufacturing/Resources/views/recipe/ingredient_row.blade.php)
- [ingredient_row_for_production.blade.php](../../../Modules/Manufacturing/Resources/views/recipe/ingredient_row_for_production.blade.php)
- [ingredients_for_production.blade.php](../../../Modules/Manufacturing/Resources/views/recipe/ingredients_for_production.blade.php)
- [show.blade.php](../../../Modules/Manufacturing/Resources/views/recipe/show.blade.php)
- [index.blade.php](../../../Modules/Manufacturing/Resources/views/settings/index.blade.php)

## Telas (Inertia/React) — 1

- [Index.tsx](../../../resources/js/Pages/Manufacturing/Index.tsx)

## Charters (lei da tela) — 1

- [Index.charter.md](../../../resources/js/Pages/Manufacturing/Index.charter.md)

## Testes (Pest) — 17

- 17 arquivos em [Modules/Manufacturing/Tests/Feature/](../../../Modules/Manufacturing/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Demais arquivos (manifestos, docs, assets e misc) — 39

- [AssertsBusinessChain.php](../../../Modules/Manufacturing/Concerns/AssertsBusinessChain.php)
- [HasManufacturingProductChain.php](../../../Modules/Manufacturing/Concerns/HasManufacturingProductChain.php)
- [LogsWithPiiRedactor.php](../../../Modules/Manufacturing/Concerns/LogsWithPiiRedactor.php)
- [.gitkeep](../../../Modules/Manufacturing/Config/.gitkeep)
- [.gitkeep](../../../Modules/Manufacturing/Console/.gitkeep)
- [.gitkeep](../../../Modules/Manufacturing/Database/Migrations/.gitkeep)
- [.gitkeep](../../../Modules/Manufacturing/Database/Seeders/.gitkeep)
- [.gitkeep](../../../Modules/Manufacturing/Database/factories/.gitkeep)
- [.gitkeep](../../../Modules/Manufacturing/Entities/.gitkeep)
- [.gitkeep](../../../Modules/Manufacturing/Http/Controllers/.gitkeep)
- [.gitkeep](../../../Modules/Manufacturing/Http/Middleware/.gitkeep)
- [.gitkeep](../../../Modules/Manufacturing/Http/Requests/.gitkeep)
- [License.txt](../../../Modules/Manufacturing/License.txt)
- [.gitkeep](../../../Modules/Manufacturing/Providers/.gitkeep)
- [.gitkeep](../../../Modules/Manufacturing/Resources/assets/.gitkeep)
- [app.js](../../../Modules/Manufacturing/Resources/assets/js/app.js)
- [app.scss](../../../Modules/Manufacturing/Resources/assets/sass/app.scss)
- [.gitkeep](../../../Modules/Manufacturing/Resources/lang/.gitkeep)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/ar/lang.php)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/ce/lang.php)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/de/lang.php)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/en/lang.php)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/es/lang.php)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/fr/lang.php)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/hi/lang.php)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/id/lang.php)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/nl/lang.php)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/ps/lang.php)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/pt/lang.php)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/sq/lang.php)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/tr/lang.php)
- [lang.php](../../../Modules/Manufacturing/Resources/lang/vi/lang.php)
- [.gitkeep](../../../Modules/Manufacturing/Resources/views/.gitkeep)
- [.gitkeep](../../../Modules/Manufacturing/Tests/.gitkeep)
- [ManufacturingUtil.php](../../../Modules/Manufacturing/Utils/ManufacturingUtil.php)
- [composer.json](../../../Modules/Manufacturing/composer.json)
- [module.json](../../../Modules/Manufacturing/module.json)
- [package.json](../../../Modules/Manufacturing/package.json)
- [webpack.mix.js](../../../Modules/Manufacturing/webpack.mix.js)
