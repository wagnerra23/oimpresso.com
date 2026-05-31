# Módulo: Officeimpresso

> **Sistema Office Impresso descktop licenciamento**

- **Alias:** `officeimpresso`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Officeimpresso`
- **Status:** 🟢 ativo
- **Providers:** Modules\Officeimpresso\Providers\OfficeimpressoServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🟡 21 rotas — escopo médio
- ✅ Tem testes (12)
- 🔐 Registra 1 permissão(ões) Spatie
- ⚙️ Processamento assíncrono: 1 peça(s) (jobs/events/listeners)
- 🗄️ Tem triggers MySQL (4) — append-only / imutabilidade

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 21 |
| Controllers | 7 |
| Entities (Models) | 2 |
| Services | 4 |
| FormRequests | 5 |
| Middleware | 3 |
| Views Blade | 18 |
| Migrations | 8 |
| Arquivos de lang | 2 |
| Testes | 12 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `catalogue-qr` | `[OfficeimpressoController::class, 'generateQr']` |
| `GET` | `/catalogue/{business_id}/{location_id}` | `[OfficeimpressoController::class, 'index']` |
| `GET` | `/show-catalogue/{business_id}/{product_id}` | `[OfficeimpressoController::class, 'show']` |
| `GET` | `/regenerate` | `[ClientController::class, 'regenerate']` |
| `GET` | `businessall` | `[LicencaComputadorController::class, 'businessall']` |
| `GET` | `computadores` | `[LicencaComputadorController::class, 'computadores']` |
| `GET` | `/licenca_computador/{id}/toggle-block` | `[LicencaComputadorController::class, 'toggleBlock']` |
| `POST` | `/licenca_computador/businessupdate/{id}` | `[LicencaComputadorController::class, 'businessupdate']` |
| `GET` | `/licenca_computador/businessbloqueado/{id}` | `[LicencaComputadorController::class, 'businessbloqueado']` |
| `GET` | `/licenca_computado/licencas/{id}` | `[LicencaComputadorController::class, 'viewLicencas']` |
| `GET` | `licenca_log/timeline/{licenca_id}` | `[LicencaLogController::class, 'timeline']` |
| `GET` | `/docs` | `function (` |
| `GET` | `install` | `[InstallController::class, 'index']` |
| `POST` | `install` | `[InstallController::class, 'install']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `RESOURCE` | `client` | `ClientController::class` |
| `RESOURCE` | `licenca_computador` | `LicencaComputadorController::class` |
| `RESOURCE` | `licenca_log` | `LicencaLogController::class` |

### `api.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/officeimpresso` | `function (Request $request` |
| `POST` | `/officeimpresso/audit` | `[ \Modules\Officeimpresso\Http\Controllers\AuditController::class, 'store' ]` |

## Controllers

- **`AuditController`** — 1 ação(ões): store
- **`ClientController`** — 8 ação(ões): index, create, store, show, edit, update, destroy, regenerate
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 
- **`LicencaComputadorController`** — 13 ação(ões): index, computadores, viewLicencas, businessall, create, edit, store, show +5
- **`LicencaLogController`** — 3 ação(ões): index, timeline, show
- **`OfficeimpressoController`** — 3 ação(ões): index, show, generateQr

## Entities (Models Eloquent)

- **`LicencaLog`** (tabela: `licenca_log`)
- **`Licenca_Computador`** (tabela: `licenca_computador`)

## Migrations

- `2024_11_05_101935_create_licenca_computador_table.php`
- `2024_11_07_083505_update_licenca_computador_table.php`
- `2025_02_07_184909_add_officeimpresso_version.php`
- `2026_04_23_200000_create_licenca_log_table.php`
- `2026_04_23_200100_create_licenca_log_triggers.php`
- `2026_04_23_200200_add_indexes_to_licenca_computador.php`
- `2026_04_24_000000_drop_licenca_log_triggers.php`
- `2026_04_24_100500_add_business_location_id_to_licenca_log.php`

## Views (Blade)

**Total:** 18 arquivos

**Pastas principais:**

- `catalogue/` — 6 arquivo(s)
- `licenca_computador/` — 4 arquivo(s)
- `layouts/` — 3 arquivo(s)
- `licenca_log/` — 2 arquivo(s)
- `clients/` — 1 arquivo(s)
- `D:/` — 1 arquivo(s)
- `licencas_log/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `officeimpresso.access`

**Usadas nas views** (`@can`/`@cannot`):

- `superadmin`

## Processamento / eventos

**Commands (artisan):** `ImportOfficeimpressoCommand`, `OfficeimpressoHealthCommand`, `InspectDelphiApiCommand`, `ParseLicencaLogCommand`

**Listeners:** `LogPassportAccessToken`

## Peças adicionais

- **Seeders:** `OfficeimpressoDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Officeimpresso` |
| `module_version` | `1.0` |
| `pid` | `19` |

## Integridade do banco

**Foreign Keys** (1):

- `business_id` → `business.id`

**Triggers MySQL** (4): `licenca_log_after_oauth_access_token_insert`, `licenca_log_after_oauth_refresh_token_insert`, `licenca_log_after_oauth_access_token_insert`, `licenca_log_after_oauth_refresh_token_insert`

**Unique indexes:** 1

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
**Reaxecutar com:** `php artisan module:spec Officeimpresso`
