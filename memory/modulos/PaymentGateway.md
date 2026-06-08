# Módulo: PaymentGateway

> **Camada técnica de cobrança BR — drivers Inter/C6/Asaas/Pix Automático BCB, webhooks, CNAB, credenciais. Consumida por Sell, RecurringBilling, NFSe, Superadmin.**

- **Alias:** `paymentgateway`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/PaymentGateway`
- **Status:** 🟢 ativo
- **Providers:** Modules\PaymentGateway\Providers\PaymentGatewayServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- ℹ️ Módulo sem views (provável API-only ou service)
- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🟡 22 rotas — escopo médio
- ✅ Tem testes (40)
- ⚙️ Processamento assíncrono: 8 peça(s) (jobs/events/listeners)
- 🔗 Acoplamento: depende de 2 outro(s) módulo(s)

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 22 |
| Controllers | 12 |
| Entities (Models) | 0 |
| Services | 21 |
| FormRequests | 0 |
| Middleware | 0 |
| Views Blade | 0 |
| Migrations | 9 |
| Arquivos de lang | 1 |
| Testes | 40 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `GET` | `payment-gateways` | `[PaymentGatewaysController::class, 'index']` |
| `POST` | `payment-gateways` | `[PaymentGatewaysController::class, 'store']` |
| `PUT` | `payment-gateways/{credentialId}` | `[PaymentGatewaysController::class, 'update']` |
| `DELETE` | `payment-gateways/{credentialId}` | `[PaymentGatewaysController::class, 'destroy']` |
| `POST` | `payment-gateways/health-check` | `[PaymentGatewaysController::class, 'healthCheck']` |
| `POST` | `payment-gateways/{credentialId}/health-check` | `[PaymentGatewaysController::class, 'healthCheck']` |
| `POST` | `payment-gateways/{credentialId}/toggle` | `[PaymentGatewaysController::class, 'toggle']` |
| `GET` | `payment-gateways/{credentialId}/history` | `[PaymentGatewaysController::class, 'history']` |
| `GET` | `payment-gateways/{credentialId}/webhook-events` | `[PaymentGatewaysController::class, 'webhookEvents']` |
| `GET` | `payment-gateways/{credentialId}/quota` | `[PaymentGatewaysController::class, 'quota']` |
| `GET` | `payment-gateways/{credentialId}/cnab-retorno` | `[PaymentGatewaysCnabRetornoController::class, 'index']` |
| `POST` | `payment-gateways/{credentialId}/cnab-retorno` | `[PaymentGatewaysCnabRetornoController::class, 'store']` |
| `POST` | `inter/{businessId}` | `[InterWebhookController::class, 'handle']` |
| `POST` | `c6/{businessId}` | `[C6WebhookController::class, 'handle']` |
| `POST` | `asaas/{businessId}` | `[AsaasWebhookController::class, 'handle']` |
| `POST` | `bcb-pix/{businessId}` | `[BcbPixWebhookController::class, 'handle']` |
| `POST` | `pagarme/{businessId}` | `[PagarmeWebhookController::class, 'handle']` |
| `POST` | `sicoob-api/{businessId}` | `[SicoobApiWebhookController::class, 'handle']` |
| `POST` | `webhooks/inter/{credentialId}` | `[InterPixWebhookController::class, 'handle']` |

## Controllers

- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 
- **`PaymentGatewaysCnabRetornoController`** — 2 ação(ões): index, store
- **`PaymentGatewaysController`** — 9 ação(ões): index, healthCheck, store, update, destroy, history, webhookEvents, quota +1
- **`AsaasWebhookController`** — 1 ação(ões): handle
- **`BcbPixWebhookController`** — 1 ação(ões): handle
- **`C6WebhookController`** — 1 ação(ões): handle
- **`InterPixWebhookController`** — 1 ação(ões): handle
- **`InterWebhookController`** — 1 ação(ões): handle
- **`PagarmeWebhookController`** — 1 ação(ões): handle
- **`SicoobApiWebhookController`** — 1 ação(ões): handle
- **`WebhookProcessor`** — 2 ação(ões): handle, validateSignature

## Migrations

- `2026_05_19_120000_create_payment_gateway_credentials_table.php`
- `2026_05_19_120001_create_cobrancas_table.php`
- `2026_05_19_120002_create_gateway_webhook_events_table.php`
- `2026_05_19_130000_add_payment_gateway_credential_id_to_fin_contas_bancarias.php`
- `2026_05_20_120000_create_inter_webhook_log_table.php`
- `2026_05_26_120000_expand_payment_gateway_credentials_gateway_key_for_cnab.php`
- `2026_05_26_120100_create_cnab_retorno_uploads_table.php`
- `2026_05_27_120000_add_sicoob_api_to_payment_gateway_credentials.php`
- `2026_05_27_140000_drop_mtls_columns_sicoob_reusa_nfecertificado.php`

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Jobs (queue):** `CnabRetornoProcessor`, `ProcessarWebhookPixInterJob`, `RetryOrphanWebhookJob`

**Commands (artisan):** `EmitTrialExpiredCobrancasCommand`, `MigrateCredentialsCommand`, `RegisterPermissionsCommand`, `RetryOrphanWebhookCommand`, `RewrapCredentialsCommand`

**Events:** `CobrancaCancelada`, `CobrancaEmitida`, `CobrancaErro`, `CobrancaPaga`, `CobrancaVencida`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `PaymentGateway` |
| `module_version` | `0.1.0` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Financeiro` | 1 |
| `NfeBrasil` | 1 |

## Integridade do banco

**Unique indexes:** 4

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
**Reaxecutar com:** `php artisan module:spec PaymentGateway`
