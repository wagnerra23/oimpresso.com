# Módulo: Officeimpresso1

> **Sistema Offiline do Office Impresso**

- **Alias:** `Officeimpresso`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Officeimpresso1`
- **Status:** ⚪ inativo
- **Providers:** Modules\Officeimpresso\Providers\OfficeimpressoServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 2 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package
- ⚪ Inativo em `modules_statuses.json`
- 🔗 Acoplamento: depende de 1 outro(s) módulo(s)

- **Prioridade sugerida de migração:** baixa (desativado)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 16 |
| Controllers | 6 |
| Entities (Models) | 1 |
| Services | 0 |
| FormRequests | 0 |
| Middleware | 1 |
| Views Blade | 8 |
| Migrations | 2 |
| Arquivos de lang | 16 |
| Testes | 0 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/api` | `[Modules\Officeimpresso\Http\Controllers\OfficeimpressoController::class, 'index']` |
| `GET` | `/regenerate` | `[ClientController::class, 'regenerate']` |
| `GET` | `businessall` | `[Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'businessall']` |
| `GET` | `computadores` | `[Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'computadores']` |
| `GET` | `/licenca_computador/{id}/toggle-block` | `[Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'toggleBlock']` |
| `POST` | `/licenca_computador/businessupdate/{id}` | `[Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'businessupdate']` |
| `GET` | `/licenca_computador/businessbloqueado/{id}` | `[Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'businessbloqueado']` |
| `GET` | `/licenca_computador/licencas/{id}` | `[Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class, 'viewLicencas']` |
| `GET` | `/docs` | `function (` |
| `GET` | `/install` | `[Modules\Officeimpresso\Http\Controllers\InstallController::class, 'index']` |
| `POST` | `/install` | `[Modules\Officeimpresso\Http\Controllers\InstallController::class, 'install']` |
| `GET` | `/install/uninstall` | `[Modules\Officeimpresso\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `/install/update` | `[Modules\Officeimpresso\Http\Controllers\InstallController::class, 'update']` |
| `RESOURCE` | `client` | `[Modules\Officeimpresso\Http\Controllers\ClientController::class]` |
| `RESOURCE` | `licenca_computador` | `Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::class` |
| `RESOURCE` | `licenca_log` | `[Modules\Officeimpresso\Http\Controllers\LicencaLogController::class]` |

## Controllers

- **`ClientController`** — 8 ação(ões): index, create, store, show, edit, update, destroy, regenerate
- **`DataController`** — 2 ação(ões): superadmin_package, modifyAdminMenu
- **`InstallController`** — 4 ação(ões): index, install, uninstall, update
- **`LicencaComputadorController`** — 11 ação(ões): index, computadores, viewLicencas, businessall, store, show, update, destroy +3
- **`LicencaLogController`** — 3 ação(ões): index, create, store
- **`OfficeimpressoController`** — 7 ação(ões): index, create, store, show, edit, update, destroy

## Entities (Models Eloquent)

- **`Licenca_Computador`** (tabela: `licenca_computador`)

## Migrations

- `2024_11_05_101935_create_licenca_computador_table.php`
- `2024_11_07_083505_update_licenca_computador_table.php`

## Views (Blade)

**Total:** 8 arquivos

**Pastas principais:**

- `licenca_computador/` — 5 arquivo(s)
- `clients/` — 1 arquivo(s)
- `layouts/` — 1 arquivo(s)
- `licencas_log/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin

## Permissões

**Usadas nas views** (`@can`/`@cannot`):

- `superadmin`

## Peças adicionais

- **Seeders:** `ConnectorDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Officeimpresso` |
| `module_version` | `1.0` |
| `pid` | `19` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Officeimpresso` | 2 |

## Integridade do banco

**Foreign Keys** (1):

- `business_id` → `business.id`

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

- **Arquivos alterados:** 67
- **Linhas +:** 2400 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2024_11_05_101935_create_licenca_computador_table.php`
  - `Database/Migrations/2024_11_07_083505_update_licenca_computador_table.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/ConnectorDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/Licenca_Computador.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/ClientController.php`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/LicencaComputadorController.php`
  - `Http/Controllers/LicencaLogController.php`
  - `Http/Controllers/OfficeimpressoController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Middleware/CheckDemo.php`
  - `Http/Requests/.gitkeep`
  - `Providers/.gitkeep`
  - `Providers/OfficeimpressoServiceProvider.php`
  - `Providers/RouteServiceProvider.php`
  - `Resources/assets/.gitkeep`
  - `Resources/assets/js/app.js`
  - `Resources/assets/sass/app.scss`
  - `Resources/lang/.gitkeep`
  - `Resources/lang/ar/lang.php`
  - `Resources/lang/ce/lang.php`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 67
- **Linhas +:** 2400 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2024_11_05_101935_create_licenca_computador_table.php`
  - `Database/Migrations/2024_11_07_083505_update_licenca_computador_table.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/ConnectorDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/Licenca_Computador.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/ClientController.php`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/LicencaComputadorController.php`
  - `Http/Controllers/LicencaLogController.php`
  - `Http/Controllers/OfficeimpressoController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Middleware/CheckDemo.php`
  - `Http/Requests/.gitkeep`
  - `Providers/.gitkeep`
  - `Providers/OfficeimpressoServiceProvider.php`
  - `Providers/RouteServiceProvider.php`
  - `Resources/assets/.gitkeep`
  - `Resources/assets/js/app.js`
  - `Resources/assets/sass/app.scss`
  - `Resources/lang/.gitkeep`
  - `Resources/lang/ar/lang.php`
  - `Resources/lang/ce/lang.php`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:14.**
**Reaxecutar com:** `php artisan module:spec Officeimpresso1`
