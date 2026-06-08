# Módulo: Whatsapp

> **Whatsapp transacional via Z-API (driver default) + Meta Cloud (fallback obrigatório). Status OS, boleto/NFe, lembrete, dunning, bot Jana com HITL.**

- **Alias:** `whatsapp`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Whatsapp`
- **Status:** 🟢 ativo
- **Providers:** Modules\Whatsapp\Providers\WhatsappServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🔴 +50 rotas — módulo grande, migrar em fases
- ✅ Tem testes (110)
- ⚙️ Processamento assíncrono: 29 peça(s) (jobs/events/listeners)
- 🔗 Acoplamento: depende de 2 outro(s) módulo(s)

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 59 |
| Controllers | 19 |
| Entities (Models) | 23 |
| Services | 47 |
| FormRequests | 6 |
| Middleware | 6 |
| Views Blade | 1 |
| Migrations | 42 |
| Arquivos de lang | 2 |
| Testes | 110 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[InstallController::class, 'index']` |
| `GET` | `install/uninstall` | `[InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[InstallController::class, 'update']` |
| `GET` | `/templates` | `[TemplatesController::class, 'index']` |
| `POST` | `/templates/sync-meta` | `[TemplatesController::class, 'syncMeta']` |
| `POST` | `/templates` | `[TemplatesController::class, 'store']` |
| `GET` | `/settings` | `[SettingsController::class, 'settings']` |
| `GET` | `/settings/meta-oauth-init` | `[SettingsController::class, 'metaOauthInit']` |
| `POST` | `/settings/meta-embedded-callback` | `[SettingsController::class, 'metaEmbeddedCallback']` |
| `PUT` | `/settings` | `fn (` |
| `GET` | `/` | `[CaixaUnificadaController::class, 'index']` |
| `GET` | `/customer/{external_id}/profile` | `[CustomerProfileController::class, 'show']` |
| `GET` | `/employee/scorecards` | `[EmployeeScorecardController::class, 'index']` |
| `GET` | `/employee/{user_identifier}/scorecard` | `[EmployeeScorecardController::class, 'show']` |
| `GET` | `/caixa-unificada` | `[CaixaUnificadaController::class, 'index']` |
| `GET` | `/midia/{path}` | `[CaixaUnificadaController::class, 'serveMedia']` |
| `POST` | `/inbox/{id}/send` | `[InboxController::class, 'send']` |
| `POST` | `/inbox/{id}/send-media` | `[InboxController::class, 'sendMedia']` |
| `POST` | `/inbox/conversations/{id}/send-interactive` | `[InboxController::class, 'sendInteractive']` |
| `PATCH` | `/inbox/{id}` | `[InboxController::class, 'updateStatus']` |
| `POST` | `/feedback/capture` | `[ClientFeedbackController::class, 'capture']` |
| `GET` | `/feedback` | `[ClientFeedbackController::class, 'index']` |
| `PATCH` | `/feedback/{id}/status` | `[ClientFeedbackController::class, 'updateStatus']` |
| `PATCH` | `/inbox/{id}/tags` | `[InboxController::class, 'updateTags']` |
| `GET` | `/inbox/contacts/search` | `[InboxController::class, 'searchContacts']` |
| `PATCH` | `/inbox/{id}/contact` | `[InboxController::class, 'linkContact']` |
| `POST` | `/inbox/{id}/contact/create-from-phone` | `[InboxController::class, 'createContactFromPhone']` |
| `PATCH` | `/inbox/{id}/block` | `[InboxController::class, 'blockContact']` |
| `GET` | `/canais` | `[ChannelsController::class, 'index']` |
| `POST` | `/canais` | `[ChannelsController::class, 'store']` |
| `GET` | `/canais/{id}` | `[ChannelsController::class, 'show']` |
| `DELETE` | `/canais/{id}` | `[ChannelsController::class, 'destroy']` |
| `POST` | `/canais/{id}/connect` | `[ChannelsController::class, 'connect']` |
| `GET` | `/canais/{id}/status` | `[ChannelsController::class, 'status']` |
| `GET` | `/canais/{id}/whatsmeow-status` | `[ChannelsController::class, 'whatsmeowStatus']` |
| `POST` | `/canais/{id}/import-history` | `[ChannelsController::class, 'importHistory']` |
| `POST` | `/canais/{id}/users` | `[ChannelsController::class, 'grantUser']` |
| `DELETE` | `/canais/{id}/users/{userId}` | `[ChannelsController::class, 'revokeUser']` |
| `GET` | `/canais/jana-templates` | `[SettingsController::class, 'show']` |
| `PUT` | `/canais/jana-templates` | `[SettingsController::class, 'update']` |

_... +13 rotas_

### `api.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/meta/{business_uuid}` | `[MetaWebhookController::class, 'verify']` |
| `POST` | `/meta/{business_uuid}` | `[MetaWebhookController::class, 'handle']` |
| `POST` | `/zapi/{business_uuid}` | `[ZapiWebhookController::class, 'handle']` |
| `POST` | `/baileys/{business_uuid}` | `[BaileysWebhookController::class, 'handle']` |
| `POST` | `/whatsmeow/{business_uuid}` | `[WhatsmeowWebhookController::class, 'handle']` |
| `POST` | `/baileys/{channel_uuid}` | `[ChannelBaileysWebhookController::class, 'handle']` |

## Controllers

- **`CaixaUnificadaController`** — 2 ação(ões): index, serveMedia
- **`ChannelsController`** — 10 ação(ões): index, store, destroy, show, grantUser, revokeUser, connect, whatsmeowStatus +2
- **`ClientFeedbackController`** — 3 ação(ões): capture, index, updateStatus
- **`CsatController`** — 1 ação(ões): index
- **`InboxController`** — 10 ação(ões): index, updateTags, send, blockContact, updateStatus, searchContacts, linkContact, createContactFromPhone +2
- **`MacroVariantsController`** — 5 ação(ões): index, store, update, destroy, markWinner
- **`MacrosController`** — 6 ação(ões): index, list, store, update, destroy, apply
- **`MetricsController`** — 1 ação(ões): index
- **`SettingsController`** — 5 ação(ões): show, update, settings, metaOauthInit, metaEmbeddedCallback
- **`TemplatesController`** — 3 ação(ões): index, syncMeta, store
- **`BaileysWebhookController`** — 1 ação(ões): handle
- **`ChannelBaileysWebhookController`** — 1 ação(ões): handle
- **`CustomerProfileController`** — 1 ação(ões): show
- **`EmployeeScorecardController`** — 2 ação(ões): show, index
- **`MetaWebhookController`** — 2 ação(ões): verify, handle
- **`WhatsmeowWebhookController`** — 1 ação(ões): handle
- **`ZapiWebhookController`** — 1 ação(ões): handle
- **`DataController`** — 3 ação(ões): superadmin_package, user_permissions, modifyAdminMenu
- **`InstallController`** — 0 ação(ões): 

## Entities (Models Eloquent)

- **`Channel`** (tabela: `channels`)
- **`ChannelUserAccess`** (tabela: `channel_user_access`)
- **`ClientFeedback`** (tabela: `clients_feedbacks`)
- **`Conversation`** (tabela: `conversations`)
- **`ConversationMetric`** (tabela: `whatsapp_conversation_metricas`)
- **`CsatResponse`** (tabela: `whatsapp_csat_responses`)
- **`CustomerMemory`** (tabela: `customer_memory`)
- **`EmployeePerformance`** (tabela: `employee_performance`)
- **`JanaCorrecao`** (tabela: `whatsapp_jana_correcoes`)
- **`LidPhoneMap`** (tabela: `whatsapp_lid_pn_map`)
- **`Macro`** (tabela: `macros`)
- **`MacroVariant`** (tabela: `macro_variants`)
- **`Message`** (tabela: `messages`)
- **`SlaPolicy`** (tabela: `sla_policies`)
- **`Tag`** (tabela: `whatsapp_tags`)
- **`WhatsappBusinessConfig`** (tabela: `whatsapp_business_configs`)
- **`WhatsappBusinessPhone`** (tabela: `whatsapp_business_phones`)
- **`WhatsappContactBotOverride`** (tabela: `whatsapp_contact_bot_overrides`)
- **`WhatsappConversation`** (tabela: `whatsapp_conversations`)
- **`WhatsappMessage`** (tabela: `whatsapp_messages`)
- **`WhatsappPhoneUserAccess`** (tabela: `whatsapp_phone_user_access`)
- **`WhatsappReminder`** (tabela: `whatsapp_reminders`)
- **`WhatsappTemplate`** (tabela: `whatsapp_templates`)

## Migrations

- `2026_05_07_000001_create_whatsapp_business_configs_table.php`
- `2026_05_07_000002_create_whatsapp_conversations_table.php`
- `2026_05_07_000003_create_whatsapp_messages_table.php`
- `2026_05_07_000004_create_whatsapp_templates_table.php`
- `2026_05_09_000001_simplify_baileys_columns_in_whatsapp_business_configs.php`
- `2026_05_09_120000_create_whatsapp_business_phones_table.php`
- `2026_05_09_120100_create_whatsapp_phone_user_access_table.php`
- `2026_05_09_120200_add_phone_id_to_whatsapp_conversations_and_messages.php`
- `2026_05_09_120300_seed_whatsapp_business_phones_from_configs.php`
- `2026_05_11_000001_create_omnichannel_tables.php`
- `2026_05_11_120000_create_conversation_tags_tables.php`
- `2026_05_11_130000_add_is_blocked_to_conversations.php`
- `2026_05_11_200000_add_updated_at_to_whatsapp_conversation_tags.php`
- `2026_05_12_000001_add_last_message_denormalized_to_conversations.php`
- `2026_05_12_140000_add_is_internal_note_to_messages.php`
- `2026_05_12_150000_add_media_to_messages.php`
- `2026_05_12_160000_create_channel_user_access_table.php`
- `2026_05_12_170000_create_whatsapp_jana_correcoes_table.php`
- `2026_05_12_180000_create_whatsapp_reminders_table.php`
- `2026_05_12_190000_create_whatsapp_contact_bot_overrides_table.php`
- `2026_05_12_200000_add_media_download_tracking_to_messages.php`
- `2026_05_12_210000_create_whatsapp_lid_pn_map_table.php`
- `2026_05_12_220000_create_sla_policies_table.php`
- `2026_05_12_220000_create_whatsapp_conversation_metricas_table.php`
- `2026_05_12_220000_create_whatsapp_csat_responses_table.php`
- `2026_05_13_000001_create_macros_table.php`
- `2026_05_13_100001_create_macro_variants_table.php`
- `2026_05_13_100002_add_macro_variant_id_to_messages.php`
- `2026_05_13_205208_add_index_display_identifier_to_channels.php`
- `2026_05_14_010001_create_jobs_table_for_whatsapp_queue.php`
- `2026_05_14_020001_create_webhook_nonces_table.php`
- `2026_05_15_010000_add_identity_columns_to_conversations.php`
- `2026_05_15_020000_fix_identity_columns_whatsapp_conversations.php`
- `2026_05_15_230000_create_customer_memory_table.php`
- `2026_05_15_240000_add_employee_complaints_external_to_customer_memory.php`
- `2026_05_15_250000_create_employee_performance_table.php`
- `2026_05_27_180000_create_clients_feedbacks_table.php`
- `2026_05_27_220000_add_dev_task_requested_to_clients_feedbacks.php`
- `2026_05_27_240000_add_signature_relevance_to_clients_feedbacks.php`
- `2026_05_28_000001_drop_baileys_columns_from_whatsapp_business_configs.php`
- `2026_05_28_000002_drop_whatsapp_baileys_auth_state_table.php`
- `2026_05_28_000003_add_meta_waba_id_to_whatsapp_business_configs.php`

## Views (Blade)

**Total:** 1 arquivos

**Pastas principais:**

- `D:/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Processamento / eventos

**Jobs (queue):** `BackfillLidConversationsJob`, `DeleteBaileysInstanceJob`, `DispatchCsatJob`, `DownloadMediaJob`, `NotificarClienteCancelamentoJob`, `PersistContactsFromHistorySyncJob`, `PersistHistorySyncBatchJob`, `ProcessIncomingWebhookJob`, `ProcessRemindersJob`, `RebuildCustomerMemoryJob`, `RebuildEmployeePerformanceJob`, `RetryFailedMediaDownloadsJob`, `SendInteractiveJob`, `SendMediaJob`, `SendWhatsappMessageJob`, `TranscribeAudioJob`, `WhatsappDriverHealthCheckJob`

**Commands (artisan):** `AutoLinkConversationContactsCommand`, `BackfillChannelAccessCommand`, `BackfillMediaDownloadCommand`, `ChannelResetCommand`, `ChannelsReconcilerCommand`, `CleanupStaleJobsCommand`, `CleanupWebhookNoncesCommand`, `CustomerMemoryBackfillCommand`, `CustomerMemoryEnrichFirebirdCommand`, `CustomerMemoryRefreshDailyCommand`, `DaemonSourceDriftCheckCommand`, `DriverHealthCheckAllCommand`, `EmployeePerformanceBackfillCommand`, `EmployeePerformanceRefreshDailyCommand`, `FeedbackReindexCommand`, `HealthProbeChannelsCommand`, `ImportHistoryCommand`, `LidBackfillCommand`, `MetricsAggregateCommand`, `ReconnectAndImportCommand`, `RegisterWhatsappPermissionsCommand`, `ReparseMediaFromPayloadCommand`, `RetryRecentMediaDownloadsCommand`, `ScanMediaDriftCommand`, `SlaScanCommand`, `WhatsappAuthStateDriftCheckCommand`, `WhatsappObservabilityHealthCommand`

**Events:** `OmnichannelMessageReceived`, `OmnichannelMessageSent`, `WhatsappMessageFailed`, `WhatsappMessageQueued`, `WhatsappMessageReceived`, `WhatsappMessageSent`

**Listeners:** `DispatchToJanaBot`, `NotifyRepairCustomer`, `PublishMessageReceivedToCentrifugo`, `PublishMessageSentToCentrifugo`, `PublishOmnichannelToCentrifugo`, `TouchCustomerMemoryOnMessage`

**Observers:** `ChannelObserver`, `ClientFeedbackObserver`, `ContactObserver`, `LidPhoneMapObserver`, `MessageObserver`, `WhatsappMessageObserver`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Whatsapp` |
| `default_driver` | `meta_cloud` |
| `zapi` | `[array(2 itens)]` |
| `meta` | `[array(6 itens)]` |
| `whatsmeow` | `[array(4 itens)]` |
| `health_check` | `[array(4 itens)]` |
| `fallback` | `[array(4 itens)]` |
| `forbidden_drivers` | `[array(3 itens)]` |
| `queue` | `whatsapp` |
| `queues` | `[array(2 itens)]` |
| `default_queue` | `comercial` |
| `webhook` | `[array(1 itens)]` |
| `backpressure` | `[array(5 itens)]` |
| `centrifugo` | `[array(7 itens)]` |
| `bot` | `[array(1 itens)]` |
| `customer_memory` | `[array(4 itens)]` |
| `audio` | `[array(1 itens)]` |
| `media` | `[array(3 itens)]` |
| `csat` | `[array(1 itens)]` |
| `history_import` | `[array(1 itens)]` |
| `retention` | `[array(4 itens)]` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Jana` | 2 |
| `Repair` | 1 |

## Integridade do banco

**Foreign Keys** (5):

- `message_id_errada` → `messages.id`
- `business_id` → `business.id`
- `channel_id` → `channels.id`
- `macro_id` → `macros.id`
- `macro_variant_id` → `macro_variants.id`

**Unique indexes:** 26

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
**Reaxecutar com:** `php artisan module:spec Whatsapp`
