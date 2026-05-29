# Módulo: Cms

> **Mini CMS (content management system) Module for DCP to help manage all frontend content like Landing page, Blog, Contact Us & many other pages.**

- **Alias:** `cms`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Cms`
- **Status:** 🟢 ativo
- **Providers:** Modules\Cms\Providers\CmsServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (17)
- 🔐 Registra 1 permissão(ões) Spatie

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 16 |
| Controllers | 5 |
| Entities (Models) | 3 |
| Services | 4 |
| FormRequests | 9 |
| Middleware | 0 |
| Views Blade | 45 |
| Migrations | 5 |
| Arquivos de lang | 1 |
| Testes | 17 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/` | `[Modules\Cms\Http\Controllers\CmsController::class, 'index']` |
| `GET` | `/old` | `[Modules\Cms\Http\Controllers\CmsController::class, 'indexLegacy']` |
| `GET` | `c/page/{page}/old` | `[Modules\Cms\Http\Controllers\CmsPageController::class, 'showPageLegacy']` |
| `GET` | `c/page/{page}` | `[Modules\Cms\Http\Controllers\CmsPageController::class, 'showPage']` |
| `GET` | `c/blogs/old` | `[Modules\Cms\Http\Controllers\CmsController::class, 'getBlogListLegacy']` |
| `GET` | `c/blogs` | `[Modules\Cms\Http\Controllers\CmsController::class, 'getBlogList']` |
| `GET` | `c/blog/{slug}-{id}/old` | `[Modules\Cms\Http\Controllers\CmsController::class, 'viewBlogLegacy']` |
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

- **`CmsController`** — 14 ação(ões): index, indexLegacy, create, store, show, edit, update, destroy +6
- **`CmsPageController`** — 8 ação(ões): index, create, store, showPage, showPageLegacy, edit, update, destroy
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 
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
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `cms.access`

## Processamento / eventos

**Commands (artisan):** `CmsHealthCommand`, `ImportWpOfficeImpressoCommand`

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
| atual (main) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ❌ |
| `origin/3.7-com-nfe` (versão antiga) | ❌ |

## Diferenças vs versões anteriores

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-05-29 08:06.**
**Reaxecutar com:** `php artisan module:spec Cms`
