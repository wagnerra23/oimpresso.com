# Módulo: AiAssistance

> **AI Assistant module for UltimatePOS. This module used openAI API to help with in copywriting & reporting**

- **Alias:** `aiassistance`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/AiAssistance`
- **Status:** 🟢 ativo
- **Providers:** Modules\AiAssistance\Providers\AiAssistanceServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🔐 Registra 1 permissão(ões) Spatie
- 🔗 Acoplamento: depende de 1 outro(s) módulo(s)

- **Prioridade sugerida de migração:** alta (pequeno, ganho rápido)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 8 |
| Controllers | 3 |
| Entities (Models) | 1 |
| Services | 0 |
| FormRequests | 0 |
| Middleware | 0 |
| Views Blade | 6 |
| Migrations | 2 |
| Arquivos de lang | 4 |
| Testes | 0 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/dashboard` | `'AiAssistanceController@index'` |
| `GET` | `/create/{tool}` | `'AiAssistanceController@create'` |
| `POST` | `/generate/{tool}` | `'AiAssistanceController@generate'` |
| `GET` | `/history` | `'AiAssistanceController@history'` |
| `GET` | `install` | `[\Modules\AiAssistance\Http\Controllers\InstallController::class, 'index']` |
| `POST` | `install` | `[\Modules\AiAssistance\Http\Controllers\InstallController::class, 'install']` |
| `GET` | `install/uninstall` | `[\Modules\AiAssistance\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[\Modules\AiAssistance\Http\Controllers\InstallController::class, 'update']` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`AiAssistanceController`** — 4 ação(ões): index, create, generate, history
- **`DataController`** — 3 ação(ões): superadmin_package, modifyAdminMenu, user_permissions
- **`InstallController`** — 4 ação(ões): index, install, uninstall, update

## Entities (Models Eloquent)

- **`AiAssistanceHistory`** (tabela: `aiassistance_history`)

## Migrations

- `2023_02_17_140135_AddVersionForAiAssistance.php`
- `2023_02_21_182321_create_aiassistance_generation_table.php`

## Views (Blade)

**Total:** 6 arquivos

**Pastas principais:**

- `D:/` — 4 arquivo(s)
- `layouts/` — 2 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `aiassistance.access_aiassistance_module`

**Usadas nas views** (`@can`/`@cannot`):

- `aiassistance.access_aiassistance_module`

## Peças adicionais

- **Seeders:** `AiAssistanceDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `AiAssistance` |
| `module_version` | `1.1` |
| `pid` | `17` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Superadmin` | 1 |

## Assets (JS / CSS)

| Tipo | Qtde |
|---|---:|
| JavaScript (.js/.mjs) | 1 |
| TypeScript (.ts) | 0 |
| Vue SFC (.vue) | 0 |
| CSS/SCSS | 1 |
| Imagens | 0 |

- Build: **Vite** (vite.config.js/ts presente)
- `package.json` presente
- **Deps JS:** `axios`, `dotenv`, `dotenv-expand`, `laravel-vite-plugin`, `lodash`, `postcss`, `vite`

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

- **Arquivos alterados:** 44
- **Linhas +:** 1659 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2023_02_17_140135_AddVersionForAiAssistance.php`
  - `Database/Migrations/2023_02_21_182321_create_aiassistance_generation_table.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/AiAssistanceDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/AiAssistanceHistory.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/AiAssistanceController.php`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Requests/.gitkeep`
  - `Providers/.gitkeep`
  - `Providers/AiAssistanceServiceProvider.php`
  - `Providers/RouteServiceProvider.php`
  - `Resources/assets/.gitkeep`
  - `Resources/assets/js/app.js`
  - `Resources/assets/sass/app.scss`
  - `Resources/lang/.gitkeep`
  - `Resources/lang/ar/lang.php`
  - `Resources/lang/ce/lang.php`
  - `Resources/lang/en/lang.php`
  - `Resources/lang/fr/lang.php`
  - `Resources/views/.gitkeep`
  - `Resources/views/create.blade.php`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 44
- **Linhas +:** 1659 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2023_02_17_140135_AddVersionForAiAssistance.php`
  - `Database/Migrations/2023_02_21_182321_create_aiassistance_generation_table.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/AiAssistanceDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/AiAssistanceHistory.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/AiAssistanceController.php`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Requests/.gitkeep`
  - `Providers/.gitkeep`
  - `Providers/AiAssistanceServiceProvider.php`
  - `Providers/RouteServiceProvider.php`
  - `Resources/assets/.gitkeep`
  - `Resources/assets/js/app.js`
  - `Resources/assets/sass/app.scss`
  - `Resources/lang/.gitkeep`
  - `Resources/lang/ar/lang.php`
  - `Resources/lang/ce/lang.php`
  - `Resources/lang/en/lang.php`
  - `Resources/lang/fr/lang.php`
  - `Resources/views/.gitkeep`
  - `Resources/views/create.blade.php`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:13.**
**Reaxecutar com:** `php artisan module:spec AiAssistance`
