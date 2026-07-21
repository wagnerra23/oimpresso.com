---
name: "SUPERFÍCIE — Whatsapp"
description: "Índice GERADO dos arquivos que moram no módulo Whatsapp, agrupado por papel. Responde 'quais arquivos são deste contexto'. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Whatsapp
---

# 🗺️ Superfície de código — Whatsapp

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Whatsapp --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o código que MORA em `Modules/Whatsapp/**` + `resources/js/Pages/Whatsapp/**` — a porta pra "quais arquivos". **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 369 arquivos em 18 papéis.

## Controllers — 23

- [BroadcastController.php](../../../Modules/Whatsapp/Http/Controllers/Admin/BroadcastController.php)
- [CaixaUnificadaController.php](../../../Modules/Whatsapp/Http/Controllers/Admin/CaixaUnificadaController.php)
- [ChannelsController.php](../../../Modules/Whatsapp/Http/Controllers/Admin/ChannelsController.php)
- [ClientFeedbackController.php](../../../Modules/Whatsapp/Http/Controllers/Admin/ClientFeedbackController.php)
- [CsatController.php](../../../Modules/Whatsapp/Http/Controllers/Admin/CsatController.php)
- [InboxAiController.php](../../../Modules/Whatsapp/Http/Controllers/Admin/InboxAiController.php)
- [InboxController.php](../../../Modules/Whatsapp/Http/Controllers/Admin/InboxController.php)
- [MacroVariantsController.php](../../../Modules/Whatsapp/Http/Controllers/Admin/MacroVariantsController.php)
- [MacrosController.php](../../../Modules/Whatsapp/Http/Controllers/Admin/MacrosController.php)
- [MetricsController.php](../../../Modules/Whatsapp/Http/Controllers/Admin/MetricsController.php)
- [QueuesController.php](../../../Modules/Whatsapp/Http/Controllers/Admin/QueuesController.php)
- [SettingsController.php](../../../Modules/Whatsapp/Http/Controllers/Admin/SettingsController.php)
- [TemplatesController.php](../../../Modules/Whatsapp/Http/Controllers/Admin/TemplatesController.php)
- [BaileysWebhookController.php](../../../Modules/Whatsapp/Http/Controllers/Api/BaileysWebhookController.php)
- [ChannelBaileysWebhookController.php](../../../Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php)
- [CustomerProfileController.php](../../../Modules/Whatsapp/Http/Controllers/Api/CustomerProfileController.php)
- [EmployeeScorecardController.php](../../../Modules/Whatsapp/Http/Controllers/Api/EmployeeScorecardController.php)
- [MetaWebhookController.php](../../../Modules/Whatsapp/Http/Controllers/Api/MetaWebhookController.php)
- [WhatsmeowWebhookController.php](../../../Modules/Whatsapp/Http/Controllers/Api/WhatsmeowWebhookController.php)
- [ZapiWebhookController.php](../../../Modules/Whatsapp/Http/Controllers/Api/ZapiWebhookController.php)
- [DataController.php](../../../Modules/Whatsapp/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Whatsapp/Http/Controllers/InstallController.php)
- [FeedbackFormController.php](../../../Modules/Whatsapp/Http/Controllers/Publico/FeedbackFormController.php)

## Requests (validação) — 6

- [BusinessSettingsRequest.php](../../../Modules/Whatsapp/Http/Requests/BusinessSettingsRequest.php)
- [ChannelRequest.php](../../../Modules/Whatsapp/Http/Requests/ChannelRequest.php)
- [CreateChannelRequest.php](../../../Modules/Whatsapp/Http/Requests/CreateChannelRequest.php)
- [GrantChannelUserRequest.php](../../../Modules/Whatsapp/Http/Requests/GrantChannelUserRequest.php)
- [MetaEmbeddedCallbackRequest.php](../../../Modules/Whatsapp/Http/Requests/MetaEmbeddedCallbackRequest.php)
- [SendMessageRequest.php](../../../Modules/Whatsapp/Http/Requests/SendMessageRequest.php)

## Middleware — 6

- [EnforceWebhookBackpressure.php](../../../Modules/Whatsapp/Http/Middleware/EnforceWebhookBackpressure.php)
- [PropagateTraceparent.php](../../../Modules/Whatsapp/Http/Middleware/PropagateTraceparent.php)
- [VerifyBaileysWebhookHmac.php](../../../Modules/Whatsapp/Http/Middleware/VerifyBaileysWebhookHmac.php)
- [VerifyMetaSignature.php](../../../Modules/Whatsapp/Http/Middleware/VerifyMetaSignature.php)
- [VerifyWhatsmeowSignature.php](../../../Modules/Whatsapp/Http/Middleware/VerifyWhatsmeowSignature.php)
- [VerifyZapiSignature.php](../../../Modules/Whatsapp/Http/Middleware/VerifyZapiSignature.php)

## Services — 47

- [AudioTranscriber.php](../../../Modules/Whatsapp/Services/Audio/Contracts/AudioTranscriber.php)
- [WhisperTranscriber.php](../../../Modules/Whatsapp/Services/Audio/WhisperTranscriber.php)
- [CentrifugoPublisher.php](../../../Modules/Whatsapp/Services/Centrifugo/CentrifugoPublisher.php)
- [CentrifugoTokenIssuer.php](../../../Modules/Whatsapp/Services/Centrifugo/CentrifugoTokenIssuer.php)
- [ConversationContactLinker.php](../../../Modules/Whatsapp/Services/Contacts/ConversationContactLinker.php)
- [LidPhoneResolver.php](../../../Modules/Whatsapp/Services/Contacts/LidPhoneResolver.php)
- [CsatDispatcher.php](../../../Modules/Whatsapp/Services/Csat/CsatDispatcher.php)
- [CsatResponseParser.php](../../../Modules/Whatsapp/Services/Csat/CsatResponseParser.php)
- [CustomerMemoryRebuilder.php](../../../Modules/Whatsapp/Services/CustomerMemory/CustomerMemoryRebuilder.php)
- [OfficeimpressoEnrichService.php](../../../Modules/Whatsapp/Services/CustomerMemory/OfficeimpressoEnrichService.php)
- [FirebirdLookupSourceContract.php](../../../Modules/Whatsapp/Services/CustomerMemory/Sources/FirebirdLookupSourceContract.php)
- [JsonFileFirebirdSource.php](../../../Modules/Whatsapp/Services/CustomerMemory/Sources/JsonFileFirebirdSource.php)
- [ChannelDriverFactory.php](../../../Modules/Whatsapp/Services/Drivers/ChannelDriverFactory.php)
- [DriverDoesNotSupport.php](../../../Modules/Whatsapp/Services/Drivers/DriverDoesNotSupport.php)
- [DriverFactory.php](../../../Modules/Whatsapp/Services/Drivers/DriverFactory.php)
- [DriverHealthStatus.php](../../../Modules/Whatsapp/Services/Drivers/DriverHealthStatus.php)
- [DriverInterface.php](../../../Modules/Whatsapp/Services/Drivers/DriverInterface.php)
- [MessageStatus.php](../../../Modules/Whatsapp/Services/Drivers/MessageStatus.php)
- [MetaCloudDriver.php](../../../Modules/Whatsapp/Services/Drivers/MetaCloudDriver.php)
- [NotImplementedDriverException.php](../../../Modules/Whatsapp/Services/Drivers/NotImplementedDriverException.php)
- [NullDriver.php](../../../Modules/Whatsapp/Services/Drivers/NullDriver.php)
- [WhatsappSendResult.php](../../../Modules/Whatsapp/Services/Drivers/WhatsappSendResult.php)
- [WhatsmeowDriver.php](../../../Modules/Whatsapp/Services/Drivers/WhatsmeowDriver.php)
- [WhatsmeowState.php](../../../Modules/Whatsapp/Services/Drivers/WhatsmeowState.php)
- [ZapiDriver.php](../../../Modules/Whatsapp/Services/Drivers/ZapiDriver.php)
- [EmployeePerformanceRebuilder.php](../../../Modules/Whatsapp/Services/EmployeePerformance/EmployeePerformanceRebuilder.php)
- [FeedbackIndexGenerator.php](../../../Modules/Whatsapp/Services/FeedbackIndexGenerator.php)
- [FeedbackRelevanceService.php](../../../Modules/Whatsapp/Services/FeedbackRelevanceService.php)
- [InboxQueryService.php](../../../Modules/Whatsapp/Services/InboxQueryService.php)
- [MacroExecutor.php](../../../Modules/Whatsapp/Services/Macros/MacroExecutor.php)
- [MacroVariantPicker.php](../../../Modules/Whatsapp/Services/Macros/MacroVariantPicker.php)
- [MacroVariantResponseTracker.php](../../../Modules/Whatsapp/Services/Macros/MacroVariantResponseTracker.php)
- [MetricsAggregator.php](../../../Modules/Whatsapp/Services/Metrics/MetricsAggregator.php)
- [MetricsSnapshotBuilder.php](../../../Modules/Whatsapp/Services/Metrics/MetricsSnapshotBuilder.php)
- [ConfigHandler.php](../../../Modules/Whatsapp/Services/Notes/ConfigHandler.php)
- [CorrigirHandler.php](../../../Modules/Whatsapp/Services/Notes/CorrigirHandler.php)
- [LembrarHandler.php](../../../Modules/Whatsapp/Services/Notes/LembrarHandler.php)
- [LembreteHandler.php](../../../Modules/Whatsapp/Services/Notes/LembreteHandler.php)
- [ParsedCommand.php](../../../Modules/Whatsapp/Services/Notes/ParsedCommand.php)
- [SlashCommandHandler.php](../../../Modules/Whatsapp/Services/Notes/SlashCommandHandler.php)
- [SlashCommandParser.php](../../../Modules/Whatsapp/Services/Notes/SlashCommandParser.php)
- [SlashCommandRegistry.php](../../../Modules/Whatsapp/Services/Notes/SlashCommandRegistry.php)
- [SlashCommandResult.php](../../../Modules/Whatsapp/Services/Notes/SlashCommandResult.php)
- [SlaEnforcer.php](../../../Modules/Whatsapp/Services/Sla/SlaEnforcer.php)
- [MessagePersister.php](../../../Modules/Whatsapp/Services/Webhook/MessagePersister.php)
- [WebhookSignatureChecker.php](../../../Modules/Whatsapp/Services/Webhook/WebhookSignatureChecker.php)
- [WhatsmeowReconciler.php](../../../Modules/Whatsapp/Services/WhatsmeowReconciler.php)

## Models / Entities — 25

- [Channel.php](../../../Modules/Whatsapp/Entities/Channel.php)
- [ChannelUserAccess.php](../../../Modules/Whatsapp/Entities/ChannelUserAccess.php)
- [ClientFeedback.php](../../../Modules/Whatsapp/Entities/ClientFeedback.php)
- [Conversation.php](../../../Modules/Whatsapp/Entities/Conversation.php)
- [ConversationMetric.php](../../../Modules/Whatsapp/Entities/ConversationMetric.php)
- [CsatResponse.php](../../../Modules/Whatsapp/Entities/CsatResponse.php)
- [CustomerMemory.php](../../../Modules/Whatsapp/Entities/CustomerMemory.php)
- [EmployeePerformance.php](../../../Modules/Whatsapp/Entities/EmployeePerformance.php)
- [JanaCorrecao.php](../../../Modules/Whatsapp/Entities/JanaCorrecao.php)
- [LidPhoneMap.php](../../../Modules/Whatsapp/Entities/LidPhoneMap.php)
- [Macro.php](../../../Modules/Whatsapp/Entities/Macro.php)
- [MacroVariant.php](../../../Modules/Whatsapp/Entities/MacroVariant.php)
- [Message.php](../../../Modules/Whatsapp/Entities/Message.php)
- [SlaPolicy.php](../../../Modules/Whatsapp/Entities/SlaPolicy.php)
- [Tag.php](../../../Modules/Whatsapp/Entities/Tag.php)
- [WhatsappBroadcast.php](../../../Modules/Whatsapp/Entities/WhatsappBroadcast.php)
- [WhatsappBusinessConfig.php](../../../Modules/Whatsapp/Entities/WhatsappBusinessConfig.php)
- [WhatsappBusinessPhone.php](../../../Modules/Whatsapp/Entities/WhatsappBusinessPhone.php)
- [WhatsappContactBotOverride.php](../../../Modules/Whatsapp/Entities/WhatsappContactBotOverride.php)
- [WhatsappConversation.php](../../../Modules/Whatsapp/Entities/WhatsappConversation.php)
- [WhatsappMessage.php](../../../Modules/Whatsapp/Entities/WhatsappMessage.php)
- [WhatsappPhoneUserAccess.php](../../../Modules/Whatsapp/Entities/WhatsappPhoneUserAccess.php)
- [WhatsappQueue.php](../../../Modules/Whatsapp/Entities/WhatsappQueue.php)
- [WhatsappReminder.php](../../../Modules/Whatsapp/Entities/WhatsappReminder.php)
- [WhatsappTemplate.php](../../../Modules/Whatsapp/Entities/WhatsappTemplate.php)

## Observers — 6

- [ChannelObserver.php](../../../Modules/Whatsapp/Observers/ChannelObserver.php)
- [ClientFeedbackObserver.php](../../../Modules/Whatsapp/Observers/ClientFeedbackObserver.php)
- [ContactObserver.php](../../../Modules/Whatsapp/Observers/ContactObserver.php)
- [LidPhoneMapObserver.php](../../../Modules/Whatsapp/Observers/LidPhoneMapObserver.php)
- [MessageObserver.php](../../../Modules/Whatsapp/Observers/MessageObserver.php)
- [WhatsappMessageObserver.php](../../../Modules/Whatsapp/Observers/WhatsappMessageObserver.php)

## Jobs — 17

- [BackfillLidConversationsJob.php](../../../Modules/Whatsapp/Jobs/BackfillLidConversationsJob.php)
- [DeleteBaileysInstanceJob.php](../../../Modules/Whatsapp/Jobs/DeleteBaileysInstanceJob.php)
- [DispatchCsatJob.php](../../../Modules/Whatsapp/Jobs/DispatchCsatJob.php)
- [DownloadMediaJob.php](../../../Modules/Whatsapp/Jobs/DownloadMediaJob.php)
- [NotificarClienteCancelamentoJob.php](../../../Modules/Whatsapp/Jobs/NotificarClienteCancelamentoJob.php)
- [PersistContactsFromHistorySyncJob.php](../../../Modules/Whatsapp/Jobs/PersistContactsFromHistorySyncJob.php)
- [PersistHistorySyncBatchJob.php](../../../Modules/Whatsapp/Jobs/PersistHistorySyncBatchJob.php)
- [ProcessIncomingWebhookJob.php](../../../Modules/Whatsapp/Jobs/ProcessIncomingWebhookJob.php)
- [ProcessRemindersJob.php](../../../Modules/Whatsapp/Jobs/ProcessRemindersJob.php)
- [RebuildCustomerMemoryJob.php](../../../Modules/Whatsapp/Jobs/RebuildCustomerMemoryJob.php)
- [RebuildEmployeePerformanceJob.php](../../../Modules/Whatsapp/Jobs/RebuildEmployeePerformanceJob.php)
- [RetryFailedMediaDownloadsJob.php](../../../Modules/Whatsapp/Jobs/RetryFailedMediaDownloadsJob.php)
- [SendInteractiveJob.php](../../../Modules/Whatsapp/Jobs/SendInteractiveJob.php)
- [SendMediaJob.php](../../../Modules/Whatsapp/Jobs/SendMediaJob.php)
- [SendWhatsappMessageJob.php](../../../Modules/Whatsapp/Jobs/SendWhatsappMessageJob.php)
- [TranscribeAudioJob.php](../../../Modules/Whatsapp/Jobs/TranscribeAudioJob.php)
- [WhatsappDriverHealthCheckJob.php](../../../Modules/Whatsapp/Jobs/WhatsappDriverHealthCheckJob.php)

## Events / Listeners — 12

- [OmnichannelMessageReceived.php](../../../Modules/Whatsapp/Events/OmnichannelMessageReceived.php)
- [OmnichannelMessageSent.php](../../../Modules/Whatsapp/Events/OmnichannelMessageSent.php)
- [WhatsappMessageFailed.php](../../../Modules/Whatsapp/Events/WhatsappMessageFailed.php)
- [WhatsappMessageQueued.php](../../../Modules/Whatsapp/Events/WhatsappMessageQueued.php)
- [WhatsappMessageReceived.php](../../../Modules/Whatsapp/Events/WhatsappMessageReceived.php)
- [WhatsappMessageSent.php](../../../Modules/Whatsapp/Events/WhatsappMessageSent.php)
- [DispatchToJanaBot.php](../../../Modules/Whatsapp/Listeners/DispatchToJanaBot.php)
- [NotifyRepairCustomer.php](../../../Modules/Whatsapp/Listeners/NotifyRepairCustomer.php)
- [PublishMessageReceivedToCentrifugo.php](../../../Modules/Whatsapp/Listeners/PublishMessageReceivedToCentrifugo.php)
- [PublishMessageSentToCentrifugo.php](../../../Modules/Whatsapp/Listeners/PublishMessageSentToCentrifugo.php)
- [PublishOmnichannelToCentrifugo.php](../../../Modules/Whatsapp/Listeners/PublishOmnichannelToCentrifugo.php)
- [TouchCustomerMemoryOnMessage.php](../../../Modules/Whatsapp/Listeners/TouchCustomerMemoryOnMessage.php)

## Console / Commands — 33

- [AuditChannelAccessCommand.php](../../../Modules/Whatsapp/Console/Commands/AuditChannelAccessCommand.php)
- [AutoLinkConversationContactsCommand.php](../../../Modules/Whatsapp/Console/Commands/AutoLinkConversationContactsCommand.php)
- [BackfillChannelAccessCommand.php](../../../Modules/Whatsapp/Console/Commands/BackfillChannelAccessCommand.php)
- [BackfillMediaDownloadCommand.php](../../../Modules/Whatsapp/Console/Commands/BackfillMediaDownloadCommand.php)
- [ChannelHealthSnapshotCommand.php](../../../Modules/Whatsapp/Console/Commands/ChannelHealthSnapshotCommand.php)
- [ChannelResetCommand.php](../../../Modules/Whatsapp/Console/Commands/ChannelResetCommand.php)
- [ChannelsReconcilerCommand.php](../../../Modules/Whatsapp/Console/Commands/ChannelsReconcilerCommand.php)
- [CleanupStaleJobsCommand.php](../../../Modules/Whatsapp/Console/Commands/CleanupStaleJobsCommand.php)
- [CleanupWebhookNoncesCommand.php](../../../Modules/Whatsapp/Console/Commands/CleanupWebhookNoncesCommand.php)
- [CustomerMemoryBackfillCommand.php](../../../Modules/Whatsapp/Console/Commands/CustomerMemoryBackfillCommand.php)
- [CustomerMemoryEnrichFirebirdCommand.php](../../../Modules/Whatsapp/Console/Commands/CustomerMemoryEnrichFirebirdCommand.php)
- [CustomerMemoryRefreshDailyCommand.php](../../../Modules/Whatsapp/Console/Commands/CustomerMemoryRefreshDailyCommand.php)
- [DaemonSourceDriftCheckCommand.php](../../../Modules/Whatsapp/Console/Commands/DaemonSourceDriftCheckCommand.php)
- [DriverHealthCheckAllCommand.php](../../../Modules/Whatsapp/Console/Commands/DriverHealthCheckAllCommand.php)
- [EmployeePerformanceBackfillCommand.php](../../../Modules/Whatsapp/Console/Commands/EmployeePerformanceBackfillCommand.php)
- [EmployeePerformanceRefreshDailyCommand.php](../../../Modules/Whatsapp/Console/Commands/EmployeePerformanceRefreshDailyCommand.php)
- [FeedbackLinkCommand.php](../../../Modules/Whatsapp/Console/Commands/FeedbackLinkCommand.php)
- [FeedbackReindexCommand.php](../../../Modules/Whatsapp/Console/Commands/FeedbackReindexCommand.php)
- [HealthProbeChannelsCommand.php](../../../Modules/Whatsapp/Console/Commands/HealthProbeChannelsCommand.php)
- [ImportHistoryCommand.php](../../../Modules/Whatsapp/Console/Commands/ImportHistoryCommand.php)
- [LidBackfillCommand.php](../../../Modules/Whatsapp/Console/Commands/LidBackfillCommand.php)
- [MetricsAggregateCommand.php](../../../Modules/Whatsapp/Console/Commands/MetricsAggregateCommand.php)
- [ReconnectAndImportCommand.php](../../../Modules/Whatsapp/Console/Commands/ReconnectAndImportCommand.php)
- [RegisterWhatsappPermissionsCommand.php](../../../Modules/Whatsapp/Console/Commands/RegisterWhatsappPermissionsCommand.php)
- [ReparseMediaFromPayloadCommand.php](../../../Modules/Whatsapp/Console/Commands/ReparseMediaFromPayloadCommand.php)
- [RetryRecentMediaDownloadsCommand.php](../../../Modules/Whatsapp/Console/Commands/RetryRecentMediaDownloadsCommand.php)
- [ScanMediaDriftCommand.php](../../../Modules/Whatsapp/Console/Commands/ScanMediaDriftCommand.php)
- [SlaScanCommand.php](../../../Modules/Whatsapp/Console/Commands/SlaScanCommand.php)
- [WebhookCanaryCommand.php](../../../Modules/Whatsapp/Console/Commands/WebhookCanaryCommand.php)
- [WhatsappAuthStateDriftCheckCommand.php](../../../Modules/Whatsapp/Console/Commands/WhatsappAuthStateDriftCheckCommand.php)
- [WhatsappObservabilityHealthCommand.php](../../../Modules/Whatsapp/Console/Commands/WhatsappObservabilityHealthCommand.php)
- [WhatsmeowHealthProbeCommand.php](../../../Modules/Whatsapp/Console/Commands/WhatsmeowHealthProbeCommand.php)
- [WhatsmeowResubscribeEventsCommand.php](../../../Modules/Whatsapp/Console/Commands/WhatsmeowResubscribeEventsCommand.php)

## Providers — 2

- [RouteServiceProvider.php](../../../Modules/Whatsapp/Providers/RouteServiceProvider.php)
- [WhatsappServiceProvider.php](../../../Modules/Whatsapp/Providers/WhatsappServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Whatsapp/Routes/api.php)
- [web.php](../../../Modules/Whatsapp/Routes/web.php)

## Migrations (schema) — 48

- [2026_05_07_000001_create_whatsapp_business_configs_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_07_000001_create_whatsapp_business_configs_table.php)
- [2026_05_07_000002_create_whatsapp_conversations_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_07_000002_create_whatsapp_conversations_table.php)
- [2026_05_07_000003_create_whatsapp_messages_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_07_000003_create_whatsapp_messages_table.php)
- [2026_05_07_000004_create_whatsapp_templates_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_07_000004_create_whatsapp_templates_table.php)
- [2026_05_09_000001_simplify_baileys_columns_in_whatsapp_business_configs.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_09_000001_simplify_baileys_columns_in_whatsapp_business_configs.php)
- [2026_05_09_120000_create_whatsapp_business_phones_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_09_120000_create_whatsapp_business_phones_table.php)
- [2026_05_09_120100_create_whatsapp_phone_user_access_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_09_120100_create_whatsapp_phone_user_access_table.php)
- [2026_05_09_120200_add_phone_id_to_whatsapp_conversations_and_messages.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_09_120200_add_phone_id_to_whatsapp_conversations_and_messages.php)
- [2026_05_09_120300_seed_whatsapp_business_phones_from_configs.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_09_120300_seed_whatsapp_business_phones_from_configs.php)
- [2026_05_11_000001_create_omnichannel_tables.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_11_000001_create_omnichannel_tables.php)
- [2026_05_11_120000_create_conversation_tags_tables.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_11_120000_create_conversation_tags_tables.php)
- [2026_05_11_130000_add_is_blocked_to_conversations.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_11_130000_add_is_blocked_to_conversations.php)
- [2026_05_11_200000_add_updated_at_to_whatsapp_conversation_tags.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_11_200000_add_updated_at_to_whatsapp_conversation_tags.php)
- [2026_05_12_000001_add_last_message_denormalized_to_conversations.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_12_000001_add_last_message_denormalized_to_conversations.php)
- [2026_05_12_140000_add_is_internal_note_to_messages.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_12_140000_add_is_internal_note_to_messages.php)
- [2026_05_12_150000_add_media_to_messages.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_12_150000_add_media_to_messages.php)
- [2026_05_12_160000_create_channel_user_access_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_12_160000_create_channel_user_access_table.php)
- [2026_05_12_170000_create_whatsapp_jana_correcoes_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_12_170000_create_whatsapp_jana_correcoes_table.php)
- [2026_05_12_180000_create_whatsapp_reminders_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_12_180000_create_whatsapp_reminders_table.php)
- [2026_05_12_190000_create_whatsapp_contact_bot_overrides_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_12_190000_create_whatsapp_contact_bot_overrides_table.php)
- [2026_05_12_200000_add_media_download_tracking_to_messages.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_12_200000_add_media_download_tracking_to_messages.php)
- [2026_05_12_210000_create_whatsapp_lid_pn_map_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_12_210000_create_whatsapp_lid_pn_map_table.php)
- [2026_05_12_220000_create_sla_policies_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_12_220000_create_sla_policies_table.php)
- [2026_05_12_220000_create_whatsapp_conversation_metricas_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_12_220000_create_whatsapp_conversation_metricas_table.php)
- [2026_05_12_220000_create_whatsapp_csat_responses_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_12_220000_create_whatsapp_csat_responses_table.php)
- [2026_05_13_000001_create_macros_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_13_000001_create_macros_table.php)
- [2026_05_13_100001_create_macro_variants_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_13_100001_create_macro_variants_table.php)
- [2026_05_13_100002_add_macro_variant_id_to_messages.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_13_100002_add_macro_variant_id_to_messages.php)
- [2026_05_13_205208_add_index_display_identifier_to_channels.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_13_205208_add_index_display_identifier_to_channels.php)
- [2026_05_14_010001_create_jobs_table_for_whatsapp_queue.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_14_010001_create_jobs_table_for_whatsapp_queue.php)
- [2026_05_14_020001_create_webhook_nonces_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_14_020001_create_webhook_nonces_table.php)
- [2026_05_15_010000_add_identity_columns_to_conversations.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_15_010000_add_identity_columns_to_conversations.php)
- [2026_05_15_020000_fix_identity_columns_whatsapp_conversations.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_15_020000_fix_identity_columns_whatsapp_conversations.php)
- [2026_05_15_230000_create_customer_memory_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_15_230000_create_customer_memory_table.php)
- [2026_05_15_240000_add_employee_complaints_external_to_customer_memory.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_15_240000_add_employee_complaints_external_to_customer_memory.php)
- [2026_05_15_250000_create_employee_performance_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_15_250000_create_employee_performance_table.php)
- [2026_05_27_180000_create_clients_feedbacks_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_27_180000_create_clients_feedbacks_table.php)
- [2026_05_27_220000_add_dev_task_requested_to_clients_feedbacks.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_27_220000_add_dev_task_requested_to_clients_feedbacks.php)
- [2026_05_27_240000_add_signature_relevance_to_clients_feedbacks.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_27_240000_add_signature_relevance_to_clients_feedbacks.php)
- [2026_05_28_000001_drop_baileys_columns_from_whatsapp_business_configs.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_28_000001_drop_baileys_columns_from_whatsapp_business_configs.php)
- [2026_05_28_000002_drop_whatsapp_baileys_auth_state_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_28_000002_drop_whatsapp_baileys_auth_state_table.php)
- [2026_05_28_000003_add_meta_waba_id_to_whatsapp_business_configs.php](../../../Modules/Whatsapp/Database/Migrations/2026_05_28_000003_add_meta_waba_id_to_whatsapp_business_configs.php)
- [2026_06_10_000001_create_whatsapp_queues_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_06_10_000001_create_whatsapp_queues_table.php)
- [2026_06_10_000002_add_queue_override_to_conversations.php](../../../Modules/Whatsapp/Database/Migrations/2026_06_10_000002_add_queue_override_to_conversations.php)
- [2026_06_10_000003_create_whatsapp_broadcasts_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_06_10_000003_create_whatsapp_broadcasts_table.php)
- [2026_06_13_120000_enforce_single_active_channel_user_access.php](../../../Modules/Whatsapp/Database/Migrations/2026_06_13_120000_enforce_single_active_channel_user_access.php)
- [2026_06_19_000001_create_channel_health_snapshots_table.php](../../../Modules/Whatsapp/Database/Migrations/2026_06_19_000001_create_channel_health_snapshots_table.php)
- [2026_07_17_100000_add_web_form_channel_to_clients_feedbacks.php](../../../Modules/Whatsapp/Database/Migrations/2026_07_17_100000_add_web_form_channel_to_clients_feedbacks.php)

## Config — 1

- [config.php](../../../Modules/Whatsapp/Config/config.php)

## Views (Blade) — 1

- 1 arquivos em [Modules/Whatsapp/Resources/views/](../../../Modules/Whatsapp/Resources/views) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Telas (Inertia/React) — 15

- [FeedbackPublico.tsx](../../../resources/js/Pages/Whatsapp/FeedbackPublico.tsx)
- [Settings.tsx](../../../resources/js/Pages/Whatsapp/Settings.tsx)
- [Index.tsx](../../../resources/js/Pages/Whatsapp/Templates/Index.tsx)
- [Avatar.tsx](../../../resources/js/Pages/Whatsapp/_components/Avatar.tsx)
- [CaptureFeedbackSheet.tsx](../../../resources/js/Pages/Whatsapp/_components/CaptureFeedbackSheet.tsx)
- [ContactPickerModal.tsx](../../../resources/js/Pages/Whatsapp/_components/ContactPickerModal.tsx)
- [ConversationList.tsx](../../../resources/js/Pages/Whatsapp/_components/ConversationList.tsx)
- [ConversationSidebar.tsx](../../../resources/js/Pages/Whatsapp/_components/ConversationSidebar.tsx)
- [ConversationThread.tsx](../../../resources/js/Pages/Whatsapp/_components/ConversationThread.tsx)
- [CustomerMemoryBlock.tsx](../../../resources/js/Pages/Whatsapp/_components/CustomerMemoryBlock.tsx)
- [InteractiveMessageDialog.tsx](../../../resources/js/Pages/Whatsapp/_components/InteractiveMessageDialog.tsx)
- [MediaFullscreenModal.tsx](../../../resources/js/Pages/Whatsapp/_components/MediaFullscreenModal.tsx)
- [MediaPreviewCard.tsx](../../../resources/js/Pages/Whatsapp/_components/MediaPreviewCard.tsx)
- [MicRecorder.tsx](../../../resources/js/Pages/Whatsapp/_components/MicRecorder.tsx)
- [TemplatePicker.tsx](../../../resources/js/Pages/Whatsapp/_components/TemplatePicker.tsx)

## Charters (lei da tela) — 3

- [FeedbackPublico.charter.md](../../../resources/js/Pages/Whatsapp/FeedbackPublico.charter.md)
- [Settings.charter.md](../../../resources/js/Pages/Whatsapp/Settings.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Whatsapp/Templates/Index.charter.md)

## Casos (contrato UC) — 1

- [FeedbackPublico.casos.md](../../../resources/js/Pages/Whatsapp/FeedbackPublico.casos.md)

## Testes (Pest) — 121

- 121 arquivos em [Modules/Whatsapp/Tests/Feature/](../../../Modules/Whatsapp/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 2

- [InboxAssistAgent.php](../../../Modules/Whatsapp/Ai/Agents/InboxAssistAgent.php)
- [CancelamentoVendaTemplate.php](../../../Modules/Whatsapp/Templates/CancelamentoVendaTemplate.php)

