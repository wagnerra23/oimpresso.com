# Módulo: ProductCatalogue

> **Catalogue & Menu module**

- **Alias:** `productcatalogue`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/ProductCatalogue`
- **Status:** 🟢 ativo
- **Providers:** Modules\ProductCatalogue\Providers\ProductCatalogueServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 2 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 7 |
| Controllers | 3 |
| Entities (Models) | 0 |
| Services | 0 |
| FormRequests | 0 |
| Middleware | 0 |
| Views Blade | 8 |
| Migrations | 1 |
| Arquivos de lang | 1 |
| Testes | 0 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/catalogue/{business_id}/{location_id}` | `[\Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController::class, 'index']` |
| `GET` | `/show-catalogue/{business_id}/{product_id}` | `[\Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController::class, 'show']` |
| `GET` | `catalogue-qr` | `[\Modules\ProductCatalogue\Http\Controllers\ProductCatalogueController::class, 'generateQr']` |
| `GET` | `install` | `[\Modules\ProductCatalogue\Http\Controllers\InstallController::class, 'index']` |
| `POST` | `install` | `[\Modules\ProductCatalogue\Http\Controllers\InstallController::class, 'install']` |
| `GET` | `install/uninstall` | `[\Modules\ProductCatalogue\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[\Modules\ProductCatalogue\Http\Controllers\InstallController::class, 'update']` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`DataController`** — 2 ação(ões): superadmin_package, modifyAdminMenu
- **`InstallController`** — 4 ação(ões): index, install, uninstall, update
- **`ProductCatalogueController`** — 3 ação(ões): index, show, generateQr

## Migrations

- `2020_09_29_184909_add_product_catalogue_version.php`

## Views (Blade)

**Total:** 8 arquivos

**Pastas principais:**

- `catalogue/` — 6 arquivo(s)
- `D:/` — 1 arquivo(s)
- `layouts/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin

## Peças adicionais

- **Seeders:** `ProductCatalogueDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `ProductCatalogue` |
| `module_version` | `1.0` |
| `pid` | `8` |

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

- `plugins\easy.qrcode.min.js` (46.7 KB)

**Arquivos CSS/SCSS** (primeiros 1):

- `sass\app.scss` (0 B)

## Presença em branches

| Branch | Presente |
|---|:-:|
| atual (6.7-react) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ✅ |
| `origin/3.7-com-nfe` (versão antiga) | ✅ |
| `origin/6.7-bootstrap` | ✅ |

## Diferenças vs versões anteriores

### vs `origin/3.7-com-nfe`

- **Arquivos alterados:** 39
- **Linhas +:** 1315 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2020_09_29_184909_add_product_catalogue_version.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/ProductCatalogueDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/ProductCatalogueController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Requests/.gitkeep`
  - `Providers/.gitkeep`
  - `Providers/ProductCatalogueServiceProvider.php`
  - `Providers/RouteServiceProvider.php`
  - `Resources/assets/.gitkeep`
  - `Resources/assets/plugins/easy.qrcode.min.js`
  - `Resources/assets/sass/app.scss`
  - `Resources/lang/.gitkeep`
  - `Resources/lang/en/lang.php`
  - `Resources/views/.gitkeep`
  - `Resources/views/catalogue/generate_qr.blade.php`
  - `Resources/views/catalogue/index.blade.php`
  - `Resources/views/catalogue/partials/combo_product_details.blade.php`
  - `Resources/views/catalogue/partials/single_product_details.blade.php`
  - `Resources/views/catalogue/partials/variable_product_details.blade.php`
  - `Resources/views/catalogue/show.blade.php`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 39
- **Linhas +:** 1315 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2020_09_29_184909_add_product_catalogue_version.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/ProductCatalogueDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/ProductCatalogueController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Requests/.gitkeep`
  - `Providers/.gitkeep`
  - `Providers/ProductCatalogueServiceProvider.php`
  - `Providers/RouteServiceProvider.php`
  - `Resources/assets/.gitkeep`
  - `Resources/assets/plugins/easy.qrcode.min.js`
  - `Resources/assets/sass/app.scss`
  - `Resources/lang/.gitkeep`
  - `Resources/lang/en/lang.php`
  - `Resources/views/.gitkeep`
  - `Resources/views/catalogue/generate_qr.blade.php`
  - `Resources/views/catalogue/index.blade.php`
  - `Resources/views/catalogue/partials/combo_product_details.blade.php`
  - `Resources/views/catalogue/partials/single_product_details.blade.php`
  - `Resources/views/catalogue/partials/variable_product_details.blade.php`
  - `Resources/views/catalogue/show.blade.php`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:14.**
**Reaxecutar com:** `php artisan module:spec ProductCatalogue`
