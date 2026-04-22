# Módulo: Cms

> **Mini CMS (content management system) Module for DCP to help manage all frontend content like Landing page, Blog, Contact Us & many other pages.**

- **Alias:** `cms`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Cms`
- **Status:** 🟢 ativo
- **Providers:** Modules\Cms\Providers\CmsServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 1 hook(s) UltimatePOS: modifyAdminMenu

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 12 |
| Controllers | 5 |
| Entities (Models) | 3 |
| Services | 0 |
| FormRequests | 0 |
| Middleware | 0 |
| Views Blade | 45 |
| Migrations | 5 |
| Arquivos de lang | 1 |
| Testes | 0 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/` | `[Modules\Cms\Http\Controllers\CmsController::class, 'index']` |
| `GET` | `c/page/{page}` | `[Modules\Cms\Http\Controllers\CmsPageController::class, 'showPage']` |
| `GET` | `c/blogs` | `[Modules\Cms\Http\Controllers\CmsController::class, 'getBlogList']` |
| `GET` | `c/blog/{slug}-{id}` | `[Modules\Cms\Http\Controllers\CmsController::class, 'viewBlog']` |
| `GET` | `c/contact-us` | `[Modules\Cms\Http\Controllers\CmsController::class, 'contactUs']` |
| `POST` | `c/submit-contact-form` | `[Modules\Cms\Http\Controllers\CmsController::class, 'postContactForm']` |
| `GET` | `install` | `[\Modules\Cms\Http\Controllers\InstallController::class, 'index']` |
| `POST` | `install` | `[\Modules\Cms\Http\Controllers\InstallController::class, 'install']` |
| `GET` | `install/uninstall` | `[\Modules\Cms\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[\Modules\Cms\Http\Controllers\InstallController::class, 'update']` |
| `RESOURCE` | `cms-page` | `\Modules\Cms\Http\Controllers\CmsPageController::class` |
| `RESOURCE` | `site-details` | `\Modules\Cms\Http\Controllers\SettingsController::class` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`CmsController`** — 11 ação(ões): index, create, store, show, edit, update, destroy, getBlogList +3
- **`CmsPageController`** — 7 ação(ões): index, create, store, showPage, edit, update, destroy
- **`DataController`** — 1 ação(ões): modifyAdminMenu
- **`InstallController`** — 4 ação(ões): index, install, uninstall, update
- **`SettingsController`** — 7 ação(ões): index, create, store, show, edit, update, destroy

## Entities (Models Eloquent)

- **`CmsPage`** (tabela: `—`)
- **`CmsPageMeta`** (tabela: `—`)
- **`CmsSiteDetail`** (tabela: `—`)

## Migrations

- `2022_08_04_143146_create_cms_pages_table.php`
- `2022_09_10_161849_add_layout_column_to_cms_pages_table.php`
- `2022_09_10_163209_create_cms_site_details_table.php`
- `2022_09_15_122547_create_cms_page_metas_table.php`
- `2022_09_16_130337_create_default_data_for_cms.php`

## Views (Blade)

**Total:** 45 arquivos

**Pastas principais:**

- `frontend/` — 16 arquivo(s)
- `components/` — 11 arquivo(s)
- `settings/` — 9 arquivo(s)
- `page/` — 8 arquivo(s)
- `layouts/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin

## Peças adicionais

- **Notifications:** `NewLeadGeneratedNotification`
- **Seeders:** `CmsDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Cms` |
| `module_version` | `1.0` |
| `pid` | `15` |

## Integridade do banco

**Foreign Keys** (1):

- `cms_page_id` → `cms_pages.id`

## Assets (JS / CSS)

| Tipo | Qtde |
|---|---:|
| JavaScript (.js/.mjs) | 1 |
| TypeScript (.ts) | 0 |
| Vue SFC (.vue) | 0 |
| CSS/SCSS | 1 |
| Imagens | 3 |

- Build: **Laravel Mix** (webpack.mix.js presente)
- `package.json` presente
- **Deps JS:** `cross-env`, `laravel-mix`, `laravel-mix-merge-manifest`

**Arquivos JS** (primeiros 1):

- `js\cms.js` (4.6 KB)

**Arquivos CSS/SCSS** (primeiros 1):

- `css\cms.css` (37.4 KB)

## Presença em branches

| Branch | Presente |
|---|:-:|
| atual (6.7-react) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ✅ |
| `origin/3.7-com-nfe` (versão antiga) | ❌ |
| `origin/6.7-bootstrap` | ✅ |

## Diferenças vs versões anteriores

### vs `origin/3.7-com-nfe`

- **Arquivos alterados:** 103
- **Linhas +:** 9154 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2022_08_04_143146_create_cms_pages_table.php`
  - `Database/Migrations/2022_09_10_161849_add_layout_column_to_cms_pages_table.php`
  - `Database/Migrations/2022_09_10_163209_create_cms_site_details_table.php`
  - `Database/Migrations/2022_09_15_122547_create_cms_page_metas_table.php`
  - `Database/Migrations/2022_09_16_130337_create_default_data_for_cms.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/CmsDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/CmsPage.php`
  - `Entities/CmsPageMeta.php`
  - `Entities/CmsSiteDetail.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/CmsController.php`
  - `Http/Controllers/CmsPageController.php`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/SettingsController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Requests/.gitkeep`
  - `Notifications/NewLeadGeneratedNotification.php`
  - `Providers/.gitkeep`
  - `Providers/CmsServiceProvider.php`
  - `Providers/RouteServiceProvider.php`
  - `Resources/assets/.gitkeep`
  - `Resources/assets/css/cms.css`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 103
- **Linhas +:** 9154 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2022_08_04_143146_create_cms_pages_table.php`
  - `Database/Migrations/2022_09_10_161849_add_layout_column_to_cms_pages_table.php`
  - `Database/Migrations/2022_09_10_163209_create_cms_site_details_table.php`
  - `Database/Migrations/2022_09_15_122547_create_cms_page_metas_table.php`
  - `Database/Migrations/2022_09_16_130337_create_default_data_for_cms.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/CmsDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/CmsPage.php`
  - `Entities/CmsPageMeta.php`
  - `Entities/CmsSiteDetail.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/CmsController.php`
  - `Http/Controllers/CmsPageController.php`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/SettingsController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Requests/.gitkeep`
  - `Notifications/NewLeadGeneratedNotification.php`
  - `Providers/.gitkeep`
  - `Providers/CmsServiceProvider.php`
  - `Providers/RouteServiceProvider.php`
  - `Resources/assets/.gitkeep`
  - `Resources/assets/css/cms.css`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:13.**
**Reaxecutar com:** `php artisan module:spec Cms`
