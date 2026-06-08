# Módulo: Grow

> **Sistema de produção do Office impresso**

- **Alias:** `grow`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Grow`
- **Status:** ⚪ inativo
- **Providers:** Modules\Grow\Providers\GrowServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🔴 +50 rotas — módulo grande, migrar em fases
- 🔴 +50 views — trabalho pesado
- ⚪ Inativo em `modules_statuses.json`
- 🔐 Registra 1 permissão(ões) Spatie
- 🔗 Acoplamento: depende de 1 outro(s) módulo(s)

- **Prioridade sugerida de migração:** baixa (desativado)
- **Risco estimado:** baixo

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 797 |
| Controllers | 142 |
| Entities (Models) | 0 |
| Services | 0 |
| FormRequests | 34 |
| Middleware | 213 |
| Views Blade | 957 |
| Migrations | 1 |
| Arquivos de lang | 64 |
| Testes | 0 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `test` | `"Test@index"` |
| `POST` | `test` | `"Test@index"` |
| `GET` | `test` | `[\Modules\Grow\Http\Controllers\Test::class, 'index']` |
| `POST` | `test` | `[\Modules\Grow\Http\Controllers\Test::class, 'index']` |
| `ANY` | `/` | `function (` |
| `ANY` | `home` | `'Home@index'` |
| `GET` | `/login` | `"Authenticate@logIn"` |
| `POST` | `/login` | `"Authenticate@logInAction"` |
| `GET` | `/forgotpassword` | `"Authenticate@forgotPassword"` |
| `POST` | `/forgotpassword` | `"Authenticate@forgotPasswordAction"` |
| `GET` | `/signup` | `"Authenticate@signUp"` |
| `POST` | `/signup` | `"Authenticate@signUpAction"` |
| `GET` | `/resetpassword` | `"Authenticate@resetPassword"` |
| `POST` | `/resetpassword` | `"Authenticate@resetPasswordAction"` |
| `GET` | `/access` | `"Authenticate@directLoginAccess"` |
| `ANY` | `logout` | `function (` |
| `ANY` | `/search` | `"Clients@index"` |
| `POST` | `/delete` | `"Clients@destroy"` |
| `GET` | `/change-category` | `"Clients@changeCategory"` |
| `POST` | `/change-category` | `"Clients@changeCategoryUpdate"` |
| `GET` | `/{client}/client-details` | `"Clients@details"` |
| `POST` | `/{client}/client-details` | `"Clients@updateDescription"` |
| `GET` | `/logo` | `"Clients@logo"` |
| `PUT` | `/logo` | `"Clients@updateLogo"` |
| `GET` | `/{client}/billing-details` | `"Clients@editBillingDetails"` |
| `PUT` | `/{client}/billing-details` | `"Clients@updatebillingDetails"` |
| `GET` | `/{client}/change-account-owner` | `"Clients@changeAccountOwner"` |
| `POST` | `/{client}/change-account-owner` | `"Clients@changeAccountOwnerUpdate"` |
| `GET` | `/{client}/pinning` | `"Clients@togglePinning"` |
| `ANY` | `/{client}/{section}` | `"Clients@showDynamic"` |
| `ANY` | `/client/{x}/profile` | `"Clients@profile"` |
| `ANY` | `/search` | `"Contacts@index"` |
| `GET` | `/updatepreferences` | `"Contacts@updatePreferences"` |
| `POST` | `/delete` | `"Contacts@destroy"` |
| `ANY` | `/search` | `"Team@index"` |
| `GET` | `/updatepreferences` | `"Team@updatePreferences"` |
| `GET` | `/avatar` | `"User@avatar"` |
| `PUT` | `/avatar` | `"User@updateAvatar"` |
| `GET` | `/notifications` | `"User@notifications"` |
| `PUT` | `/notifications` | `"User@updateNotifications"` |

_... +757 rotas_

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`Webhooks`** — 2 ação(ões): index, getKey
- **`Ipn`** — 2 ação(ões): index, initialiseIPN
- **`Webhooks`** — 2 ação(ões): index, onetimePayment
- **`Webhooks`** — 1 ação(ões): index
- **`ForgotPasswordController`** — 0 ação(ões): 
- **`LoginController`** — 0 ação(ões): 
- **`RegisterController`** — 0 ação(ões): 
- **`ResetPasswordController`** — 0 ação(ões): 
- **`Authenticate`** — 8 ação(ões): logIn, signUp, forgotPassword, resetPassword, logInAction, forgotPasswordAction, resetPasswordAction, signUpAction
- **`Calendar`** — 7 ação(ões): index, create, store, update, destroy, show, deleteFiles
- **`Canned`** — 9 ação(ões): index, create, store, show, edit, update, destroy, search +1
- **`Categories`** — 8 ação(ões): index, create, store, edit, update, destroy, showTeam, updateTeam
- **`Clients`** — 18 ação(ões): index, create, store, show, showDynamic, edit, update, destroy +10
- **`Comments`** — 3 ação(ões): index, store, destroy
- **`Contacts`** — 8 ação(ões): index, show, create, store, edit, update, destroy, updatePreferences
- **`Contracts`** — 26 ação(ões): index, create, store, show, showPublic, editingContract, destroy, bulkDelete +18
- **`Controller`** — 0 ação(ões): 
- **`DataController`** — 3 ação(ões): superadmin_package, modifyAdminMenu, user_permissions
- **`Documents`** — 3 ação(ões): updateHero, updateDetails, updateBody
- **`Estimates`** — 34 ação(ões): index, create, store, applyDefaultAutomation, show, showPublic, saveEstimate, publishEstimate +26
- **`Events`** — 3 ação(ões): topNavEvents, markMyEventRead, markAllMyEventRead
- **`Expenses`** — 20 ação(ões): index, create, store, show, edit, update, destroy, downloadAttachment +12
- **`Clients`** — 1 ação(ões): index
- **`Estimates`** — 1 ação(ões): index
- **`Expenses`** — 1 ação(ões): index
- **`Invoices`** — 1 ação(ões): index
- **`Items`** — 1 ação(ões): index
- **`Leads`** — 1 ação(ões): index
- **`Payments`** — 1 ação(ões): index
- **`Projects`** — 1 ação(ões): index
- **`Fooos`** — 1 ação(ões): index
- **`Tasks`** — 1 ação(ões): index
- **`Tickets`** — 1 ação(ões): index
- **`Timesheets`** — 1 ação(ões): index
- **`Feed`** — 12 ação(ões): companyNames, contactNames, emailAddress, tags, projects, leads, leadNames, projectAssignedUsers +4
- **`Files`** — 24 ação(ões): index, externalRequest, create, store, edit, renameFile, showImage, download +16
- **`Fileupload`** — 9 ação(ões): save, saveWebForm, saveGeneralImage, saveLogo, saveAvatar, saveAppLogo, saveTinyMCEImage, uploadImportFiles +1
- **`Fooos`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`GrowController`** — 4 ação(ões): index, create, generate, history
- **`Home`** — 6 ação(ões): index, csAffiliateDashboard, teamDashboard, clientDashboard, adminDashboard, widgetLeads
- **`Clients`** — 2 ação(ões): create, store
- **`Common`** — 1 ação(ões): showErrorLog
- **`Leads`** — 2 ação(ões): create, store
- **`InstallController`** — 4 ação(ões): index, install, uninstall, update
- **`Invoices`** — 36 ação(ões): redirectURL, index, createSelector, create, store, show, saveInvoice, publishInvoice +28
- **`Items`** — 18 ação(ões): index, categoryItems, create, store, show, edit, update, destroy +10
- **`KBCategories`** — 6 ação(ões): index, create, store, edit, update, destroy
- **`Knowledgebase`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`Test`** — 1 ação(ões): index
- **`Leads`** — 66 ação(ões): index, indexList, indexKanban, create, getCustomFields, store, customFieldValidationFailed, show +58
- **`Messages`** — 8 ação(ões): index, getFeed, storeText, destroy, countMessages, getDeletedMessages, storeFiles, getUserStatus
- **`Milestones`** — 7 ação(ões): index, create, store, edit, update, destroy, updatePositions
- **`Notes`** — 9 ação(ões): index, create, store, show, edit, update, destroy, downloadAttachment +1
- **`Payments`** — 11 ação(ões): index, create, store, show, edit, update, destroy, thankYou +3
- **`Polling`** — 3 ação(ões): generalPoll, timersPoll, activeTimerPoll
- **`Preferences`** — 5 ação(ões): updateTableConfig, create, store, edit, update
- **`Projects`** — 39 ação(ões): index, create, store, show, showDynamic, edit, update, getCustomFields +31
- **`Proposals`** — 24 ação(ões): index, create, store, applyDefaultAutomation, editAutomation, updateAutomation, show, showPublic +16
- **`Reminders`** — 10 ação(ões): show, edit, create, store, getResourceItem, delete, close, topNavFeed +2
- **`Reports`** — 1 ação(ões): index
- **`Clients`** — 2 ação(ões): overview, category
- **`Dynamic`** — 1 ação(ões): showDynamic
- **`Estimates`** — 4 ação(ões): overview, month, client, category
- **`IncomeStatement`** — 1 ação(ões): report
- **`Invoices`** — 4 ação(ões): overview, month, client, category
- **`Projects`** — 3 ação(ões): overview, client, category
- **`Start`** — 1 ação(ões): showStart
- **`Timesheets`** — 3 ação(ões): team, client, project
- **`Template`** — 5 ação(ões): index, create, store, edit, update
- **`Fooos`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`Search`** — 12 ação(ões): index, clients, projects, contracts, proposals, tickets, tasks, leads +4
- **`Bank`** — 2 ação(ões): index, update
- **`Captcha`** — 2 ação(ões): index, update
- **`Clients`** — 2 ação(ões): index, update
- **`Company`** — 2 ação(ões): index, update
- **`Contracts`** — 2 ação(ões): index, update
- **`Cronjobs`** — 1 ação(ões): index
- **`Currency`** — 2 ação(ões): index, update
- **`Customfields`** — 16 ação(ões): showClient, updateClient, showProject, updateProject, showLead, updateLead, showTask, updateTask +8
- **`Dynamic`** — 1 ação(ões): showDynamic
- **`Email`** — 16 ação(ões): general, updateGeneral, smtp, updateSMTP, testEmail, testEmailAction, testSMTP, queueShow +8
- **`Emailtemplates`** — 3 ação(ões): index, show, update
- **`Errorlogs`** — 4 ação(ões): index, download, delete, update
- **`Estimates`** — 4 ação(ões): index, automation, automationUpdate, update
- **`Expenses`** — 2 ação(ões): index, update
- **`Files`** — 10 ação(ões): showGeneral, updateGeneral, folders, updatefolders, defaultFolders, createFolder, storeFolder, editFolder +2
- **`Formbuilder`** — 10 ação(ões): buildForm, saveForm, availableFormFields, cleanLanguage, update, formSettings, saveSettings, formStyle +2
- **`General`** — 2 ação(ões): index, update
- **`Home`** — 1 ação(ões): index
- **`Invoices`** — 2 ação(ões): index, update
- **`Knowledgebase`** — 9 ação(ões): index, update, categories, storeCategory, create, editCategory, updateCategory, destroy +1
- **`Leads`** — 11 ação(ões): general, updateGeneral, statuses, editStatus, updateStatus, createStatus, storeStatus, move +3
- **`Logos`** — 3 ação(ões): index, logo, updateLogo
- **`Milestones`** — 9 ação(ões): index, update, categories, storeCategory, create, editCategory, updateCategory, destroy +1
- **`Modules`** — 2 ação(ões): index, update
- **`Mollie`** — 2 ação(ões): index, update
- **`Paypal`** — 2 ação(ões): index, update
- **`Paystack`** — 2 ação(ões): index, update
- **`Projects`** — 8 ação(ões): general, clientPermissions, staffPermissions, updateGeneral, updateClientPermissions, updateStaffPermissions, automation, automationUpdate
- **`Proposals`** — 4 ação(ões): index, update, automation, automationUpdate
- **`Razorpay`** — 2 ação(ões): index, update
- **`Roles`** — 8 ação(ões): index, create, store, edit, editHomePage, updateHomePage, update, destroy
- **`Sources`** — 6 ação(ões): index, create, store, edit, update, destroy
- **`Stripe`** — 2 ação(ões): index, update
- **`Subscriptions`** — 2 ação(ões): index, update
- **`System`** — 1 ação(ões): clearLaravelCache
- **`Tags`** — 2 ação(ões): index, update
- **`Tap`** — 2 ação(ões): index, update
- **`Tasks`** — 20 ação(ões): index, update, statuses, editStatus, updateStatus, createStatus, storeStatus, move +12
- **`Taxrates`** — 6 ação(ões): index, create, store, edit, update, destroy
- **`Template`** — 2 ação(ões): index, update
- **`Theme`** — 2 ação(ões): index, update
- **`Tickets`** — 16 ação(ões): index, update, statuses, editStatus, updateStatus, createStatus, storeStatus, move +8
- **`Tweak`** — 2 ação(ões): index, update
- **`Updates`** — 2 ação(ões): index, checkUpdates
- **`Webforms`** — 8 ação(ões): index, create, edit, store, embedCode, destroy, assignedUsers, updateAssignedUsers
- **`WebmailTemplates`** — 6 ação(ões): index, create, edit, store, update, destroy
- **`DatabaseResponse`** — 1 ação(ões): toResponse
- **`FinishResponse`** — 1 ação(ões): toResponse
- **`IndexResponse`** — 1 ação(ões): toResponse
- **`RequirementsResponse`** — 1 ação(ões): toResponse
- **`ServerInfoResponse`** — 1 ação(ões): toResponse
- **`SettingsResponse`** — 1 ação(ões): toResponse
- **`Setup`** — 11 ação(ões): index, serverInfo, checkRequirements, showDatabase, updateDatabase, updateSettings, updateUser, createUserSpace +3
- **`UserResponse`** — 1 ação(ões): toResponse
- **`Spaces`** — 1 ação(ões): showDynamic
- **`Subscriptions`** — 12 ação(ões): index, create, getProductPrices, store, show, setupStripePayment, subscriptionInvoices, edit +4
- **`Tags`** — 6 ação(ões): index, create, store, edit, update, destroy
- **`Tasks`** — 57 ação(ões): index, indexList, indexKanban, create, getCustomFields, store, customFieldValidationFailed, show +49
- **`Team`** — 7 ação(ões): index, create, store, edit, update, updatePreferences, destroy
- **`Contracts`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`Projects`** — 11 ação(ões): index, create, store, show, showDynamic, edit, update, destroy +3
- **`Proposals`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`Test`** — 1 ação(ões): index
- **`Tickets`** — 23 ação(ões): index, create, store, show, edit, update, reply, storeReply +15
- **`Timebilling`** — 1 ação(ões): index
- **`Timeline`** — 2 ação(ões): projectTimeline, clientTimeline
- **`Timesheets`** — 6 ação(ões): index, create, store, edit, update, destroy
- **`Runonce`** — 1 ação(ões): index
- **`User`** — 9 ação(ões): avatar, updateAvatar, updatePassword, updatePasswordAction, updateTheme, updateThemeAction, updateLanguage, updateNotifications +1
- **`Webform`** — 4 ação(ões): showWeb, saveForm, formFieldsArray, formFields
- **`Compose`** — 3 ação(ões): compose, send, prefillTemplate

## Migrations

- `2023_02_17_140135_AddVersionForGrow.php`

## Views (Blade)

**Total:** 957 arquivos

**Pastas principais:**

- `pages/` — 892 arquivo(s)
- `misc/` — 18 arquivo(s)
- `modules/` — 14 arquivo(s)
- `layout/` — 7 arquivo(s)
- `errors/` — 6 arquivo(s)
- `nav/` — 6 arquivo(s)
- `modals/` — 5 arquivo(s)
- `grow/` — 3 arquivo(s)
- `notifications/` — 3 arquivo(s)
- `vendor/` — 2 arquivo(s)
- `frontend/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `grow.access_grow_module`

**Usadas nas views** (`@can`/`@cannot`):

- `aiassistance.access_aiassistance_module`

## Processamento / eventos

**Commands (artisan):** `Kernel`

## Peças adicionais

- **Seeders:** `GrowDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Grow` |
| `module_version` | `3.8` |
| `pid` | `22` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Superadmin` | 1 |

## Assets (JS / CSS)

| Tipo | Qtde |
|---|---:|
| JavaScript (.js/.mjs) | 2 |
| TypeScript (.ts) | 0 |
| Vue SFC (.vue) | 0 |
| CSS/SCSS | 2 |
| Imagens | 0 |

- Build: **Vite** (vite.config.js/ts presente)
- `package.json` presente
- **Deps JS:** `axios`, `dotenv`, `dotenv-expand`, `laravel-vite-plugin`, `lodash`, `postcss`, `vite`

**Frameworks/libs detectados no JS:** Laravel Echo

**Arquivos JS** (primeiros 2):

- `js\app.js` (262 B)
- `js\bootstrap.js` (1.6 KB)

**Arquivos CSS/SCSS** (primeiros 2):

- `sass\app.css` (54 B)
- `sass\app.scss` (2 B)

## Presença em branches

| Branch | Presente |
|---|:-:|
| atual (6.7-react) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ✅ |
| `origin/3.7-com-nfe` (versão antiga) | ❌ |
| `origin/6.7-bootstrap` | ✅ |

## Diferenças vs versões anteriores

### vs `origin/3.7-com-nfe`

- **Arquivos alterados:** 2436
- **Linhas +:** 327915 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/app.php`
  - `Config/auth.php`
  - `Config/broadcasting.php`
  - `Config/cache.php`
  - `Config/config.php`
  - `Config/database.php`
  - `Config/debugbar.php`
  - `Config/dompdf.php`
  - `Config/filesystems.php`
  - `Config/flare.php`
  - `Config/ignition.php`
  - `Config/image.php`
  - `Config/imap.php`
  - `Config/logging.php`
  - `Config/mail.php`
  - `Config/modules.php`
  - `Config/money.php`
  - `Config/purifier.php`
  - `Config/queue.php`
  - `Config/recaptcha.php`
  - `Config/services.php`
  - `Config/session.php`
  - `Config/settings.php`
  - `Config/system.php`
  - `Config/trustedproxy.php`
  - `Config/view.php`
  - `Console/Kernel.php`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2023_02_17_140135_AddVersionForGrow.php`
  - `Database/Seeders/.gitkeep`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 2436
- **Linhas +:** 327915 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/app.php`
  - `Config/auth.php`
  - `Config/broadcasting.php`
  - `Config/cache.php`
  - `Config/config.php`
  - `Config/database.php`
  - `Config/debugbar.php`
  - `Config/dompdf.php`
  - `Config/filesystems.php`
  - `Config/flare.php`
  - `Config/ignition.php`
  - `Config/image.php`
  - `Config/imap.php`
  - `Config/logging.php`
  - `Config/mail.php`
  - `Config/modules.php`
  - `Config/money.php`
  - `Config/purifier.php`
  - `Config/queue.php`
  - `Config/recaptcha.php`
  - `Config/services.php`
  - `Config/session.php`
  - `Config/settings.php`
  - `Config/system.php`
  - `Config/trustedproxy.php`
  - `Config/view.php`
  - `Console/Kernel.php`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2023_02_17_140135_AddVersionForGrow.php`
  - `Database/Seeders/.gitkeep`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:14.**
**Reaxecutar com:** `php artisan module:spec Grow`
