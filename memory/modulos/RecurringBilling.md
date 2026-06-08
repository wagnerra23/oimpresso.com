# Módulo: RecurringBilling

> **Cobrança recorrente brasileira: assinaturas, Pix Automático, smart retries, NFSe automática, régua de inadimplência.**

- **Alias:** `recurringbilling`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/RecurringBilling`
- **Status:** 🟢 ativo
- **Providers:** Modules\RecurringBilling\Providers\RecurringBillingServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🟡 28 rotas — escopo médio
- ✅ Tem testes (34)
- ⚙️ Processamento assíncrono: 8 peça(s) (jobs/events/listeners)
- 🔗 Acoplamento: depende de 1 outro(s) módulo(s)

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 28 |
| Controllers | 11 |
| Entities (Models) | 0 |
| Services | 10 |
| FormRequests | 7 |
| Middleware | 0 |
| Views Blade | 2 |
| Migrations | 8 |
| Arquivos de lang | 1 |
| Testes | 34 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `GET` | `/` | `[RecurringBillingController::class, 'index']` |
| `POST` | `/` | `[RecurringBillingController::class, 'store']` |
| `POST` | `/{id}/cancelar` | `[RecurringBillingController::class, 'cancelar']` |
| `POST` | `/{id}/pausar` | `[RecurringBillingController::class, 'pausar']` |
| `POST` | `/{id}/reativar` | `[RecurringBillingController::class, 'reativar']` |
| `POST` | `/{subscriptionId}/notes` | `[SubscriptionNoteController::class, 'store']` |
| `DELETE` | `/{subscriptionId}/notes/{noteId}` | `[SubscriptionNoteController::class, 'destroy']` |
| `POST` | `/{subscriptionId}/notes/{noteId}/pin` | `[SubscriptionNoteController::class, 'togglePin']` |
| `POST` | `/{subscriptionId}/favorite` | `[SubscriptionFavoriteController::class, 'toggle']` |
| `GET` | `/{subscriptionId}/events` | `[SubscriptionEventController::class, 'index']` |
| `POST` | `/{subscriptionId}/events` | `[SubscriptionEventController::class, 'store']` |
| `POST` | `/{subscriptionId}/reenviar-nfe` | `[RecurringBillingController::class, 'reenviarNfe']` |
| `GET` | `/faturas` | `[InvoiceController::class, 'index']` |
| `GET` | `/configuracoes` | `[ConfiguracoesController::class, 'index']` |
| `GET` | `/` | `[PlanController::class, 'index']` |
| `GET` | `/novo` | `[PlanController::class, 'create']` |
| `POST` | `/` | `[PlanController::class, 'store']` |
| `GET` | `/{id}/editar` | `[PlanController::class, 'edit']` |
| `PUT` | `/{id}` | `[PlanController::class, 'update']` |
| `DELETE` | `/{id}` | `[PlanController::class, 'destroy']` |
| `GET` | `/recurringbilling/{any}` | `function ($any` |
| `POST` | `rb-invoices/{invoice}/cancelar` | `[InvoiceController::class, 'cancel']` |
| `POST` | `/webhooks/inter/pix/{businessId}` | `[InterWebhookController::class, 'handle']` |

### `api.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `recurringbilling` | `fn (Request $request` |
| `POST` | `webhooks/asaas/{businessId}` | `[\Modules\RecurringBilling\Http\Controllers\AsaasWebhookController::class, 'handle']` |

## Controllers

- **`AsaasWebhookController`** — 1 ação(ões): handle
- **`ConfiguracoesController`** — 1 ação(ões): index
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 
- **`InterWebhookController`** — 1 ação(ões): handle
- **`InvoiceController`** — 2 ação(ões): index, cancel
- **`PlanController`** — 6 ação(ões): index, create, store, edit, update, destroy
- **`RecurringBillingController`** — 11 ação(ões): index, store, cancelar, pausar, reativar, reenviarNfe, create, show +3
- **`SubscriptionEventController`** — 2 ação(ões): index, store
- **`SubscriptionFavoriteController`** — 1 ação(ões): toggle
- **`SubscriptionNoteController`** — 3 ação(ões): store, destroy, togglePin

## Migrations

- `2026_05_06_000001_create_rb_boleto_credentials_table.php`
- `2026_05_06_000002_add_conta_bancaria_fk_to_rb_boleto_credentials.php`
- `2026_05_06_000003_create_pg_webhook_events_table.php`
- `2026_05_06_001000_create_rb_plans_table.php`
- `2026_05_06_001001_create_rb_subscriptions_table.php`
- `2026_05_06_001002_create_rb_invoices_table.php`
- `2026_05_06_001003_create_rb_charge_attempts_table.php`
- `2026_05_16_120000_recurring_v975_schema.php`

## Views (Blade)

**Total:** 2 arquivos

**Pastas principais:**

- `D:/` — 1 arquivo(s)
- `layouts/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Jobs (queue):** `CancelarCobrancaAsaasJob`, `ProcessAsaasWebhookJob`, `ProcessInterWebhookJob`, `RefundCobrancaAsaasJob`, `SyncBankBalancesJob`, `SyncBankStatementsJob`

**Commands (artisan):** `BackfillCachedFieldsCommand`, `RecurringHealthCommand`, `SyncBankBalancesCommand`

**Events:** `AssinaturaAtualizada`, `InvoicePaid`

**Observers:** `SubscriptionCachedFieldsObserver`

## Peças adicionais

- **Policies:** `SubscriptionPolicy`
- **Seeders:** `RecurringBillingDatabaseSeeder`, `RecurringBillingDemoSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `RecurringBilling` |
| `module_version` | `0.1.0` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `NfeBrasil` | 1 |

## Integridade do banco

**Unique indexes:** 6

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
- **Deps JS:** `axios`, `laravel-vite-plugin`, `sass`, `postcss`, `vite`

**Arquivos JS** (primeiros 1):

- `js\app.js` (0 B)

**Arquivos CSS/SCSS** (primeiros 1):

- `sass\app.scss` (0 B)

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
**Reaxecutar com:** `php artisan module:spec RecurringBilling`
