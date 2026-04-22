# Módulo: Officeimpresso

> **Sistema Office Impresso descktop licenciamento**

- **Alias:** `officeimpresso`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Officeimpresso`
- **Status:** ⚪ inativo
- **Providers:** Modules\Officeimpresso\Providers\OfficeimpressoServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 2 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package
- ⚪ Inativo em `modules_statuses.json`

- **Prioridade sugerida de migração:** baixa (desativado)
- **Risco estimado:** baixo

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
| `GET` | `catalogue-qr` | `[\Modules\Officeimpresso\Http\Controllers\OfficeimpressoController::class, 'generateQr']` |
| `GET` | `/catalogue/{business_id}/{location_id}` | `[\Modules\Officeimpresso\Http\Controllers\OfficeimpressoController::class, 'index']` |
| `GET` | `/show-catalogue/{business_id}/{product_id}` | `[\Modules\Officeimpresso\Http\Controllers\OfficeimpressoController::class, 'show']` |
| `GET` | `install` | `[\Modules\Officeimpresso\Http\Controllers\InstallController::class, 'index']` |
| `POST` | `install` | `[\Modules\Officeimpresso\Http\Controllers\InstallController::class, 'install']` |
| `GET` | `install/uninstall` | `[\Modules\Officeimpresso\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[\Modules\Officeimpresso\Http\Controllers\InstallController::class, 'update']` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`DataController`** — 2 ação(ões): superadmin_package, modifyAdminMenu
- **`InstallController`** — 4 ação(ões): index, install, uninstall, update
- **`OfficeimpressoController`** — 3 ação(ões): index, show, generateQr

## Migrations

- `2025_02_07_184909_add_officeimpresso_version.php`

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

- **Seeders:** `OfficeimpressoDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Officeimpresso` |
| `module_version` | `1.0` |
| `pid` | `19` |

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
- **Linhas +:** 1319 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2025_02_07_184909_add_officeimpresso_version.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/OfficeimpressoDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/OfficeimpressoController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Requests/.gitkeep`
  - `Providers/.gitkeep`
  - `Providers/OfficeimpressoServiceProvider.php`
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
- **Linhas +:** 1319 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2025_02_07_184909_add_officeimpresso_version.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/OfficeimpressoDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/OfficeimpressoController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Requests/.gitkeep`
  - `Providers/.gitkeep`
  - `Providers/OfficeimpressoServiceProvider.php`
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
**Reaxecutar com:** `php artisan module:spec Officeimpresso`
