# Módulo: Connector

> **Provide the API's for POS**

- **Alias:** `connector`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Connector`
- **Status:** 🟢 ativo
- **Providers:** Modules\Connector\Providers\ConnectorServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🔴 +50 rotas — módulo grande, migrar em fases
- ✅ Tem testes (9)
- 🔐 Registra 1 permissão(ões) Spatie
- 🔗 Acoplamento: depende de 3 outro(s) módulo(s)

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 61 |
| Controllers | 30 |
| Entities (Models) | 0 |
| Services | 2 |
| FormRequests | 15 |
| Middleware | 1 |
| Views Blade | 2 |
| Migrations | 1 |
| Arquivos de lang | 16 |
| Testes | 9 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[Modules\Connector\Http\Controllers\InstallController::class, 'index']` |
| `POST` | `install` | `[Modules\Connector\Http\Controllers\InstallController::class, 'install']` |
| `GET` | `install/uninstall` | `[Modules\Connector\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[Modules\Connector\Http\Controllers\InstallController::class, 'update']` |
| `GET` | `/api` | `[Modules\Connector\Http\Controllers\ConnectorController::class, 'index']` |
| `GET` | `/regenerate` | `[Modules\Connector\Http\Controllers\ClientController::class, 'regenerate']` |
| `RESOURCE` | `client` | `Officeimpresso\ClientController` |
| `RESOURCE` | `/client` | `'Modules\Connector\Http\Controllers\ClientController'` |

### `api.php`

| Método | URI | Controller |
|---|---|---|
| `POST` | `processa-dados-cliente` | `[ \Modules\Connector\Http\Controllers\Api\LicencaComputadorController::class, 'ProcessaDadosCliente' ]` |
| `POST` | `salvar-cliente` | `[ \Modules\Connector\Http\Controllers\Api\BusinessController::class, 'saveBusiness' ]` |
| `POST` | `salvar-equipamento/{business_id}` | `[ \Modules\Connector\Http\Controllers\Api\LicencaComputadorController::class, 'saveEquipamento' ]` |
| `POST` | `oimpresso/registrar` | `[ \Modules\Connector\Http\Controllers\Api\OImpressoRegistroController::class, 'registrar' ]` |
| `POST` | `check-update` | `[ \Modules\Connector\Http\Controllers\Api\CheckUpdateController::class, 'check' ]` |
| `POST` | `contactapi-payment` | `[Modules\Connector\Http\Controllers\Api\ContactController::class, 'contactPay']` |
| `GET` | `selling-price-group` | `[Modules\Connector\Http\Controllers\Api\ProductController::class, 'getSellingPriceGroup']` |
| `GET` | `variation/{id?}` | `[Modules\Connector\Http\Controllers\Api\ProductController::class, 'listVariations']` |
| `GET` | `user/loggedin` | `[Modules\Connector\Http\Controllers\Api\UserController::class, 'loggedin']` |
| `POST` | `user-registration` | `[Modules\Connector\Http\Controllers\Api\UserController::class, 'registerUser']` |
| `GET` | `payment-accounts` | `[Modules\Connector\Http\Controllers\Api\CommonResourceController::class, 'getPaymentAccounts']` |
| `GET` | `payment-methods` | `[Modules\Connector\Http\Controllers\Api\CommonResourceController::class, 'getPaymentMethods']` |
| `POST` | `sell-return` | `[Modules\Connector\Http\Controllers\Api\SellController::class, 'addSellReturn']` |
| `GET` | `list-sell-return` | `[Modules\Connector\Http\Controllers\Api\SellController::class, 'listSellReturn']` |
| `POST` | `update-shipping-status` | `[Modules\Connector\Http\Controllers\Api\SellController::class, 'updateSellShippingStatus']` |
| `GET` | `expense-refund` | `[Modules\Connector\Http\Controllers\Api\ExpenseController::class, 'listExpenseRefund']` |
| `GET` | `expense-categories` | `[Modules\Connector\Http\Controllers\Api\ExpenseController::class, 'listExpenseCategories']` |
| `GET` | `business-details` | `[Modules\Connector\Http\Controllers\Api\CommonResourceController::class, 'getBusinessDetails']` |
| `GET` | `profit-loss-report` | `[Modules\Connector\Http\Controllers\Api\CommonResourceController::class, 'getProfitLoss']` |
| `GET` | `product-stock-report` | `[Modules\Connector\Http\Controllers\Api\CommonResourceController::class, 'getProductStock']` |
| `GET` | `notifications` | `[Modules\Connector\Http\Controllers\Api\CommonResourceController::class, 'getNotifications']` |
| `GET` | `active-subscription` | `[Modules\Connector\Http\Controllers\Api\SuperadminController::class, 'getActiveSubscription']` |
| `GET` | `packages` | `[Modules\Connector\Http\Controllers\Api\SuperadminController::class, 'getPackages']` |
| `GET` | `get-attendance/{user_id}` | `[Modules\Connector\Http\Controllers\Api\AttendanceController::class, 'getAttendance']` |
| `POST` | `clock-in` | `[Modules\Connector\Http\Controllers\Api\AttendanceController::class, 'clockin']` |
| `POST` | `clock-out` | `[Modules\Connector\Http\Controllers\Api\AttendanceController::class, 'clockout']` |
| `GET` | `holidays` | `[Modules\Connector\Http\Controllers\Api\AttendanceController::class, 'getHolidays']` |
| `POST` | `update-password` | `[Modules\Connector\Http\Controllers\Api\UserController::class, 'updatePassword']` |
| `POST` | `forget-password` | `[Modules\Connector\Http\Controllers\Api\UserController::class, 'forgetPassword']` |
| `GET` | `get-location` | `[Modules\Connector\Http\Controllers\Api\CommonResourceController::class, 'getLocation']` |
| `GET` | `new_product` | `[Modules\Connector\Http\Controllers\Api\ProductSellController::class, 'newProduct']` |
| `GET` | `new_sell` | `[Modules\Connector\Http\Controllers\Api\ProductSellController::class, 'newSell']` |
| `GET` | `new_contactapi` | `[Modules\Connector\Http\Controllers\Api\ProductSellController::class, 'newContactApi']` |
| `GET` | `follow-up-resources` | `[Modules\Connector\Http\Controllers\Api\Crm\FollowUpController::class, 'getFollowUpResources']` |
| `GET` | `leads` | `[Modules\Connector\Http\Controllers\Api\Crm\FollowUpController::class, 'getLeads']` |
| `POST` | `call-logs` | `[Modules\Connector\Http\Controllers\Api\Crm\CallLogsController::class, 'saveCallLogs']` |
| `GET` | `field-force` | `[Modules\Connector\Http\Controllers\Api\FieldForce\FieldForceController::class, 'index']` |
| `POST` | `field-force/create` | `[Modules\Connector\Http\Controllers\Api\FieldForce\FieldForceController::class, 'store']` |
| `POST` | `field-force/update-visit-status/{id}` | `[Modules\Connector\Http\Controllers\Api\FieldForce\FieldForceController::class, 'updateStatus']` |
| `RESOURCE` | `business-location` | `Modules\Connector\Http\Controllers\Api\BusinessLocationController::class` |

_... +13 rotas_

## Controllers

- **`ApiController`** — 7 ação(ões): getStatusCode, setStatusCode, respondUnauthorized, respond, modelNotFoundExceptionResult, otherExceptions, getClient
- **`AttendanceController`** — 4 ação(ões): getAttendance, clockin, clockout, getHolidays
- **`BaseApiController`** — 2 ação(ões): syncData, getData
- **`BrandController`** — 2 ação(ões): index, show
- **`BusinessController`** — 8 ação(ões): index, create, store, show, edit, update, destroy, saveBusiness
- **`BusinessLocationController`** — 2 ação(ões): index, show
- **`CashRegisterController`** — 3 ação(ões): index, store, show
- **`CategoryController`** — 2 ação(ões): index, show
- **`CheckUpdateController`** — 1 ação(ões): check
- **`CommonResourceController`** — 7 ação(ões): getPaymentAccounts, getPaymentMethods, getBusinessDetails, getProfitLoss, getProductStock, getNotifications, getLocation
- **`ContactController`** — 5 ação(ões): index, store, show, update, contactPay
- **`CallLogsController`** — 2 ação(ões): saveCallLogs, searchUser
- **`FollowUpController`** — 6 ação(ões): index, getFollowUpResources, store, show, update, getLeads
- **`ExpenseController`** — 6 ação(ões): index, show, store, update, listExpenseRefund, listExpenseCategories
- **`FieldForceController`** — 3 ação(ões): index, store, updateStatus
- **`LicencaComputadorController`** — 7 ação(ões): ProcessaDadosCliente, saveEquipamento, index, store, show, update, destroy
- **`OImpressoRegistroController`** — 1 ação(ões): registrar
- **`ProductController`** — 4 ação(ões): index, show, listVariations, getSellingPriceGroup
- **`ProductSellController`** — 3 ação(ões): newProduct, newSell, newContactApi
- **`SellController`** — 8 ação(ões): index, show, store, update, destroy, updateSellShippingStatus, addSellReturn, listSellReturn
- **`SuperadminController`** — 2 ação(ões): getActiveSubscription, getPackages
- **`TableController`** — 2 ação(ões): index, show
- **`TaxController`** — 2 ação(ões): index, show
- **`TypesOfServiceController`** — 2 ação(ões): index, show
- **`UnitController`** — 2 ação(ões): index, show
- **`UserController`** — 7 ação(ões): index, show, loggedin, updatePassword, registerUser, generateRandomString, forgetPassword
- **`ClientController`** — 8 ação(ões): index, create, store, show, edit, update, destroy, regenerate
- **`ConnectorController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 

## Migrations

- `2020_08_18_123107_add_connector_module_version_to_system_table.php`

## Views (Blade)

**Total:** 2 arquivos

**Pastas principais:**

- `clients/` — 1 arquivo(s)
- `layouts/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `connector.access`

**Usadas nas views** (`@can`/`@cannot`):

- `superadmin`

## Processamento / eventos

**Commands (artisan):** `ConnectorHealthCommand`

## Peças adicionais

- **Notifications:** `NewPassword`
- **Seeders:** `ConnectorDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Connector` |
| `module_version` | `2.0` |
| `pid` | `9` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Crm` | 3 |
| `Essentials` | 1 |
| `Superadmin` | 1 |

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
**Reaxecutar com:** `php artisan module:spec Connector`
