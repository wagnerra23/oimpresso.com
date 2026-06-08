# Módulo: ProductCatalogue

> **Catalogue & Menu module**

- **Alias:** `productcatalogue`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/ProductCatalogue`
- **Status:** 🟢 ativo
- **Providers:** Modules\ProductCatalogue\Providers\ProductCatalogueServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- ✅ Tem testes (7)
- 🔐 Registra 1 permissão(ões) Spatie
- 🔗 Acoplamento: depende de 1 outro(s) módulo(s)

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 7 |
| Controllers | 3 |
| Entities (Models) | 0 |
| Services | 2 |
| FormRequests | 5 |
| Middleware | 0 |
| Views Blade | 8 |
| Migrations | 1 |
| Arquivos de lang | 1 |
| Testes | 7 |

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

- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 
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
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `productcatalogue.access`

## Processamento / eventos

**Commands (artisan):** `ProductCatalogueHealthCommand`

## Peças adicionais

- **Seeders:** `ProductCatalogueDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `ProductCatalogue` |
| `module_version` | `1.0` |
| `pid` | `8` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Jana` | 1 |

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
**Reaxecutar com:** `php artisan module:spec ProductCatalogue`
