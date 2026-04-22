# Módulo: IProduction

> **Software de Gestão de Produção e Fabricação**

- **Alias:** `iproduction`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/IProduction`
- **Status:** ⚪ inativo
- **Providers:** Modules\IProduction\Providers\IProductionServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Sem migrations próprias (pode depender de tabelas de outros módulos)
- ⚪ Inativo em `modules_statuses.json`

- **Prioridade sugerida de migração:** baixa (desativado)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 14 |
| Controllers | 1 |
| Entities (Models) | 0 |
| Services | 0 |
| FormRequests | 0 |
| Middleware | 0 |
| Views Blade | 20 |
| Migrations | 0 |
| Arquivos de lang | 0 |
| Testes | 0 |

## Rotas

### `routes.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/install` | `'InstallController@index'` |
| `POST` | `/install` | `'InstallController@install'` |
| `GET` | `/install/update` | `'InstallController@update'` |
| `GET` | `/install/uninstall` | `'InstallController@uninstall'` |
| `GET` | `/is-recipe-exist/{variation_id}` | `'RecipeController@isRecipeExist'` |
| `GET` | `/ingredient-group-form` | `'RecipeController@getIngredientGroupForm'` |
| `GET` | `/get-recipe-details` | `'RecipeController@getRecipeDetails'` |
| `GET` | `/get-ingredient-row/{variation_id}` | `'RecipeController@getIngredientRow'` |
| `GET` | `/add-ingredient` | `'RecipeController@addIngredients'` |
| `GET` | `/report` | `'ProductionController@getBoletoReport'` |
| `POST` | `/update-product-prices` | `'RecipeController@updateRecipeProductPrices'` |
| `RESOURCE` | `/recipe` | `'RecipeController'` |
| `RESOURCE` | `/production` | `'ProductionController'` |
| `RESOURCE` | `/settings` | `'SettingsController'` |

## Controllers

- **`InstallController`** — 4 ação(ões): index, install, update, uninstall

## Views (Blade)

**Total:** 20 arquivos

**Pastas principais:**

- `recipe/` — 8 arquivo(s)
- `production/` — 6 arquivo(s)
- `layouts/` — 4 arquivo(s)
- `D:/` — 1 arquivo(s)
- `settings/` — 1 arquivo(s)

## Permissões

**Usadas nas views** (`@can`/`@cannot`):

- `boleto.access_recipe`
- `boleto.access_production`
- `boleto.add_recipe`

## Peças adicionais

- **Seeders:** `IProductionDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `IProduction` |
| `module_version` | `1.0` |
| `pid` | `10` |

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
| atual (6.7-react) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ✅ |
| `origin/3.7-com-nfe` (versão antiga) | ❌ |
| `origin/6.7-bootstrap` | ✅ |

## Diferenças vs versões anteriores

### vs `origin/3.7-com-nfe`

- **Arquivos alterados:** 72
- **Linhas +:** 2645 **-:** 0
- **Primeiros arquivos alterados:**
  - `.htaccess`
  - `Config/.gitkeep`
  - `Config/.htaccess`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Console/.htaccess`
  - `Database/.htaccess`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/.htaccess`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/.htaccess`
  - `Database/Seeders/IProductionDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Database/factories/.htaccess`
  - `Entities/.gitkeep`
  - `Entities/.htaccess`
  - `Http/.htaccess`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/.htaccess`
  - `Http/Controllers/InstallController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Middleware/.htaccess`
  - `Http/Requests/.gitkeep`
  - `Http/Requests/.htaccess`
  - `Http/routes.php`
  - `Providers/.gitkeep`
  - `Providers/.htaccess`
  - `Providers/IProductionServiceProvider.php`
  - `Resources/.htaccess`
  - `Resources/assets/.gitkeep`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 72
- **Linhas +:** 2645 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `.htaccess`
  - `Config/.gitkeep`
  - `Config/.htaccess`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Console/.htaccess`
  - `Database/.htaccess`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/.htaccess`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/.htaccess`
  - `Database/Seeders/IProductionDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Database/factories/.htaccess`
  - `Entities/.gitkeep`
  - `Entities/.htaccess`
  - `Http/.htaccess`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/.htaccess`
  - `Http/Controllers/InstallController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Middleware/.htaccess`
  - `Http/Requests/.gitkeep`
  - `Http/Requests/.htaccess`
  - `Http/routes.php`
  - `Providers/.gitkeep`
  - `Providers/.htaccess`
  - `Providers/IProductionServiceProvider.php`
  - `Resources/.htaccess`
  - `Resources/assets/.gitkeep`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:14.**
**Reaxecutar com:** `php artisan module:spec IProduction`
