# Módulo: Manufacturing

> **Used for businesses where products needs to be manufactured**

- **Alias:** `manufacturing`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Manufacturing`
- **Status:** 🟢 ativo
- **Providers:** Modules\Manufacturing\Providers\ManufacturingServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (17)
- 🔐 Registra 4 permissão(ões) Spatie

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 14 |
| Controllers | 6 |
| Entities (Models) | 3 |
| Services | 2 |
| FormRequests | 7 |
| Middleware | 0 |
| Views Blade | 20 |
| Migrations | 13 |
| Arquivos de lang | 14 |
| Testes | 17 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/install` | `[Modules\Manufacturing\Http\Controllers\InstallController::class, 'index']` |
| `POST` | `/install` | `[Modules\Manufacturing\Http\Controllers\InstallController::class, 'install']` |
| `GET` | `/install/update` | `[Modules\Manufacturing\Http\Controllers\InstallController::class, 'update']` |
| `GET` | `/install/uninstall` | `[Modules\Manufacturing\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `/is-recipe-exist/{variation_id}` | `[Modules\Manufacturing\Http\Controllers\RecipeController::class, 'isRecipeExist']` |
| `GET` | `/ingredient-group-form` | `[Modules\Manufacturing\Http\Controllers\RecipeController::class, 'getIngredientGroupForm']` |
| `GET` | `/get-recipe-details` | `[Modules\Manufacturing\Http\Controllers\RecipeController::class, 'getRecipeDetails']` |
| `GET` | `/get-ingredient-row/{variation_id}` | `[Modules\Manufacturing\Http\Controllers\RecipeController::class, 'getIngredientRow']` |
| `GET` | `/add-ingredient` | `[Modules\Manufacturing\Http\Controllers\RecipeController::class, 'addIngredients']` |
| `GET` | `/report` | `[Modules\Manufacturing\Http\Controllers\ProductionController::class, 'getManufacturingReport']` |
| `POST` | `/update-product-prices` | `[Modules\Manufacturing\Http\Controllers\RecipeController::class, 'updateRecipeProductPrices']` |
| `RESOURCE` | `/recipe` | `'Modules\Manufacturing\Http\Controllers\RecipeController'` |
| `RESOURCE` | `/production` | `'Modules\Manufacturing\Http\Controllers\ProductionController'` |
| `RESOURCE` | `/settings` | `'Modules\Manufacturing\Http\Controllers\SettingsController'` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`DataController`** — 4 ação(ões): superadmin_package, user_permissions, modifyAdminMenu, profitLossReportData
- **`InstallController`** — 0 ação(ões): 
- **`ManufacturingController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`ProductionController`** — 9 ação(ões): index, create, store, show, edit, update, destroy, indexV2 +1
- **`RecipeController`** — 11 ação(ões): index, create, store, show, getIngredientRow, addIngredients, getRecipeDetails, getIngredientGroupForm +3
- **`SettingsController`** — 2 ação(ões): index, store

## Entities (Models Eloquent)

- **`MfgIngredientGroup`** (tabela: `—`)
- **`MfgRecipe`** (tabela: `—`)
- **`MfgRecipeIngredient`** (tabela: `—`)

## Migrations

- `2019_07_15_114211_add_manufacturing_module_version_to_system_table.php`
- `2019_07_15_114403_create_mfg_recipes_table.php`
- `2019_07_18_180217_add_production_columns_to_transactions_table.php`
- `2019_07_26_110753_add_manufacturing_settings_column_to_business_table.php`
- `2019_07_26_170450_add_manufacturing_permissions.php`
- `2019_08_08_110035_create_mfg_recipe_ingredients_table.php`
- `2019_08_08_172837_add_recipe_add_edit_permissions.php`
- `2019_08_12_114610_add_ingredient_waste_percent_columns.php`
- `2019_11_05_115136_create_ingredient_groups_table.php`
- `2020_02_22_120303_add_column_to_mfg_recipe_ingredients_table.php`
- `2020_08_19_103831_add_production_cost_type_to_recipe_and_transaction_table.php`
- `2021_02_16_190302_add_manufacturing_module_indexing.php`
- `2021_04_07_154331_add_mfg_ingredient_group_id_to_transaction_sell_lines_table.php`

## Views (Blade)

**Total:** 20 arquivos

**Pastas principais:**

- `recipe/` — 8 arquivo(s)
- `production/` — 6 arquivo(s)
- `layouts/` — 4 arquivo(s)
- `D:/` — 1 arquivo(s)
- `settings/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `manufacturing.access_recipe`
- `manufacturing.add_recipe`
- `manufacturing.edit_recipe`
- `manufacturing.access_production`

**Usadas nas views** (`@can`/`@cannot`):

- `manufacturing.access_recipe`
- `manufacturing.access_production`
- `manufacturing.add_recipe`

## Processamento / eventos

**Commands (artisan):** `ManufacturingHealthCommand`

## Peças adicionais

- **Seeders:** `ManufacturingDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Manufacturing` |
| `module_version` | `3.1` |
| `pid` | `4` |

## Integridade do banco

**Foreign Keys** (1):

- `mfg_recipe_id` → `mfg_recipes.id`

## Assets (JS / CSS)

| Tipo | Qtde |
|---|---:|
| JavaScript (.js/.mjs) | 1 |
| TypeScript (.ts) | 0 |
| Vue SFC (.vue) | 0 |
| CSS/SCSS | 1 |
| Imagens | 0 |

- Build: **Laravel Mix** (webpack.mix.js presente)
- `package.json` presente
- **Deps JS:** `cross-env`, `laravel-mix`, `laravel-mix-merge-manifest`

**Arquivos JS** (primeiros 1):

- `js\app.js` (0 B)

**Arquivos CSS/SCSS** (primeiros 1):

- `sass\app.scss` (0 B)

## Presença em branches

| Branch | Presente |
|---|:-:|
| atual (main) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ❌ |
| `origin/3.7-com-nfe` (versão antiga) | ✅ |

## Diferenças vs versões anteriores

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-05-29 08:06.**
**Reaxecutar com:** `php artisan module:spec Manufacturing`
