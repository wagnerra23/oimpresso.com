---
name: "SUPERFÍCIE — PaymentGateway"
description: "Índice GERADO dos artefatos do módulo PaymentGateway reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: PaymentGateway
---

# 🗺️ Superfície de código — PaymentGateway

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs PaymentGateway --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/PaymentGateway/**` + `resources/js/Pages/PaymentGateway/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 134 arquivos em 12 papéis.

## Controllers — 12

- [DataController.php](../../../Modules/PaymentGateway/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/PaymentGateway/Http/Controllers/InstallController.php)
- [PaymentGatewaysCnabRetornoController.php](../../../Modules/PaymentGateway/Http/Controllers/Settings/PaymentGatewaysCnabRetornoController.php)
- [PaymentGatewaysController.php](../../../Modules/PaymentGateway/Http/Controllers/Settings/PaymentGatewaysController.php)
- [AsaasWebhookController.php](../../../Modules/PaymentGateway/Http/Controllers/Webhooks/AsaasWebhookController.php)
- [BcbPixWebhookController.php](../../../Modules/PaymentGateway/Http/Controllers/Webhooks/BcbPixWebhookController.php)
- [C6WebhookController.php](../../../Modules/PaymentGateway/Http/Controllers/Webhooks/C6WebhookController.php)
- [InterPixWebhookController.php](../../../Modules/PaymentGateway/Http/Controllers/Webhooks/InterPixWebhookController.php)
- [InterWebhookController.php](../../../Modules/PaymentGateway/Http/Controllers/Webhooks/InterWebhookController.php)
- [PagarmeWebhookController.php](../../../Modules/PaymentGateway/Http/Controllers/Webhooks/PagarmeWebhookController.php)
- [SicoobApiWebhookController.php](../../../Modules/PaymentGateway/Http/Controllers/Webhooks/SicoobApiWebhookController.php)
- [WebhookProcessor.php](../../../Modules/PaymentGateway/Http/Controllers/Webhooks/WebhookProcessor.php)

## Services — 23

- [CnabBoletoAdapter.php](../../../Modules/PaymentGateway/Services/Cnab/CnabBoletoAdapter.php)
- [AilosCnabDriver.php](../../../Modules/PaymentGateway/Services/Cnab/Drivers/AilosCnabDriver.php)
- [BBCnabDriver.php](../../../Modules/PaymentGateway/Services/Cnab/Drivers/BBCnabDriver.php)
- [BanrisulCnabDriver.php](../../../Modules/PaymentGateway/Services/Cnab/Drivers/BanrisulCnabDriver.php)
- [BradescoCnabDriver.php](../../../Modules/PaymentGateway/Services/Cnab/Drivers/BradescoCnabDriver.php)
- [BtgCnabDriver.php](../../../Modules/PaymentGateway/Services/Cnab/Drivers/BtgCnabDriver.php)
- [CaixaCnabDriver.php](../../../Modules/PaymentGateway/Services/Cnab/Drivers/CaixaCnabDriver.php)
- [CresolCnabDriver.php](../../../Modules/PaymentGateway/Services/Cnab/Drivers/CresolCnabDriver.php)
- [ItauCnabDriver.php](../../../Modules/PaymentGateway/Services/Cnab/Drivers/ItauCnabDriver.php)
- [SantanderCnabDriver.php](../../../Modules/PaymentGateway/Services/Cnab/Drivers/SantanderCnabDriver.php)
- [SicoobCnabDriver.php](../../../Modules/PaymentGateway/Services/Cnab/Drivers/SicoobCnabDriver.php)
- [SicrediCnabDriver.php](../../../Modules/PaymentGateway/Services/Cnab/Drivers/SicrediCnabDriver.php)
- [AsaasDriver.php](../../../Modules/PaymentGateway/Services/Drivers/AsaasDriver.php)
- [BcbPixDriver.php](../../../Modules/PaymentGateway/Services/Drivers/BcbPixDriver.php)
- [C6Driver.php](../../../Modules/PaymentGateway/Services/Drivers/C6Driver.php)
- [InterDriver.php](../../../Modules/PaymentGateway/Services/Drivers/InterDriver.php)
- [PagarmeDriver.php](../../../Modules/PaymentGateway/Services/Drivers/PagarmeDriver.php)
- [SicoobApiDriver.php](../../../Modules/PaymentGateway/Services/Drivers/SicoobApiDriver.php)
- [HealthCheckService.php](../../../Modules/PaymentGateway/Services/HealthCheckService.php)
- [HttpClientFactory.php](../../../Modules/PaymentGateway/Services/HttpClientFactory.php)
- [PaymentGatewayService.php](../../../Modules/PaymentGateway/Services/PaymentGatewayService.php)
- [ReconciliarCobrancaService.php](../../../Modules/PaymentGateway/Services/ReconciliarCobrancaService.php)
- [CobrancaWebhookResolver.php](../../../Modules/PaymentGateway/Services/Webhook/CobrancaWebhookResolver.php)

## Models / Entities — 5

- [CnabRetornoUpload.php](../../../Modules/PaymentGateway/Models/CnabRetornoUpload.php)
- [Cobranca.php](../../../Modules/PaymentGateway/Models/Cobranca.php)
- [GatewayWebhookEvent.php](../../../Modules/PaymentGateway/Models/GatewayWebhookEvent.php)
- [InterWebhookLog.php](../../../Modules/PaymentGateway/Models/InterWebhookLog.php)
- [PaymentGatewayCredential.php](../../../Modules/PaymentGateway/Models/PaymentGatewayCredential.php)

## Jobs — 3

- [CnabRetornoProcessor.php](../../../Modules/PaymentGateway/Jobs/CnabRetornoProcessor.php)
- [ProcessarWebhookPixInterJob.php](../../../Modules/PaymentGateway/Jobs/ProcessarWebhookPixInterJob.php)
- [RetryOrphanWebhookJob.php](../../../Modules/PaymentGateway/Jobs/RetryOrphanWebhookJob.php)

## Events / Listeners — 5

- [CobrancaCancelada.php](../../../Modules/PaymentGateway/Events/CobrancaCancelada.php)
- [CobrancaEmitida.php](../../../Modules/PaymentGateway/Events/CobrancaEmitida.php)
- [CobrancaErro.php](../../../Modules/PaymentGateway/Events/CobrancaErro.php)
- [CobrancaPaga.php](../../../Modules/PaymentGateway/Events/CobrancaPaga.php)
- [CobrancaVencida.php](../../../Modules/PaymentGateway/Events/CobrancaVencida.php)

## Console / Commands — 8

- [EmitTrialExpiredCobrancasCommand.php](../../../Modules/PaymentGateway/Console/Commands/EmitTrialExpiredCobrancasCommand.php)
- [InterImportarRecebimentosCommand.php](../../../Modules/PaymentGateway/Console/Commands/InterImportarRecebimentosCommand.php)
- [InterReconcilePixCommand.php](../../../Modules/PaymentGateway/Console/Commands/InterReconcilePixCommand.php)
- [MigrateCredentialsCommand.php](../../../Modules/PaymentGateway/Console/Commands/MigrateCredentialsCommand.php)
- [RegisterInterWebhookCommand.php](../../../Modules/PaymentGateway/Console/Commands/RegisterInterWebhookCommand.php)
- [RegisterPermissionsCommand.php](../../../Modules/PaymentGateway/Console/Commands/RegisterPermissionsCommand.php)
- [RetryOrphanWebhookCommand.php](../../../Modules/PaymentGateway/Console/Commands/RetryOrphanWebhookCommand.php)
- [RewrapCredentialsCommand.php](../../../Modules/PaymentGateway/Console/Commands/RewrapCredentialsCommand.php)

## Providers — 2

- [PaymentGatewayServiceProvider.php](../../../Modules/PaymentGateway/Providers/PaymentGatewayServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/PaymentGateway/Providers/RouteServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/PaymentGateway/Routes/web.php)

## Migrations (schema) — 11

- [2026_05_19_120000_create_payment_gateway_credentials_table.php](../../../Modules/PaymentGateway/Database/Migrations/2026_05_19_120000_create_payment_gateway_credentials_table.php)
- [2026_05_19_120001_create_cobrancas_table.php](../../../Modules/PaymentGateway/Database/Migrations/2026_05_19_120001_create_cobrancas_table.php)
- [2026_05_19_120002_create_gateway_webhook_events_table.php](../../../Modules/PaymentGateway/Database/Migrations/2026_05_19_120002_create_gateway_webhook_events_table.php)
- [2026_05_19_130000_add_payment_gateway_credential_id_to_fin_contas_bancarias.php](../../../Modules/PaymentGateway/Database/Migrations/2026_05_19_130000_add_payment_gateway_credential_id_to_fin_contas_bancarias.php)
- [2026_05_20_120000_create_inter_webhook_log_table.php](../../../Modules/PaymentGateway/Database/Migrations/2026_05_20_120000_create_inter_webhook_log_table.php)
- [2026_05_26_120000_expand_payment_gateway_credentials_gateway_key_for_cnab.php](../../../Modules/PaymentGateway/Database/Migrations/2026_05_26_120000_expand_payment_gateway_credentials_gateway_key_for_cnab.php)
- [2026_05_26_120100_create_cnab_retorno_uploads_table.php](../../../Modules/PaymentGateway/Database/Migrations/2026_05_26_120100_create_cnab_retorno_uploads_table.php)
- [2026_05_27_120000_add_sicoob_api_to_payment_gateway_credentials.php](../../../Modules/PaymentGateway/Database/Migrations/2026_05_27_120000_add_sicoob_api_to_payment_gateway_credentials.php)
- [2026_05_27_140000_drop_mtls_columns_sicoob_reusa_nfecertificado.php](../../../Modules/PaymentGateway/Database/Migrations/2026_05_27_140000_drop_mtls_columns_sicoob_reusa_nfecertificado.php)
- [2026_06_08_120000_add_fin_titulo_to_cobrancas_origem_type.php](../../../Modules/PaymentGateway/Database/Migrations/2026_06_08_120000_add_fin_titulo_to_cobrancas_origem_type.php)
- [2026_06_13_080000_alter_payment_gateway_credentials_config_json_to_longtext.php](../../../Modules/PaymentGateway/Database/Migrations/2026_06_13_080000_alter_payment_gateway_credentials_config_json_to_longtext.php)

## Config — 1

- [config.php](../../../Modules/PaymentGateway/Config/config.php)

## Testes (Pest) — 47

- 47 arquivos em [Modules/PaymentGateway/Tests/Feature/](../../../Modules/PaymentGateway/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 16

- [PaymentDriverContract.php](../../../Modules/PaymentGateway/Contracts/PaymentDriverContract.php)
- [PaymentGatewayContract.php](../../../Modules/PaymentGateway/Contracts/PaymentGatewayContract.php)
- [CardToken.php](../../../Modules/PaymentGateway/Dto/CardToken.php)
- [CobrancaEmitidaResult.php](../../../Modules/PaymentGateway/Dto/CobrancaEmitidaResult.php)
- [CobrancaStatus.php](../../../Modules/PaymentGateway/Dto/CobrancaStatus.php)
- [DriverHealth.php](../../../Modules/PaymentGateway/Dto/DriverHealth.php)
- [EmitirCobrancaInput.php](../../../Modules/PaymentGateway/Dto/EmitirCobrancaInput.php)
- [CardDeclinedException.php](../../../Modules/PaymentGateway/Exceptions/CardDeclinedException.php)
- [CredentialMisconfiguredException.php](../../../Modules/PaymentGateway/Exceptions/CredentialMisconfiguredException.php)
- [DriverNotSupportedException.php](../../../Modules/PaymentGateway/Exceptions/DriverNotSupportedException.php)
- [GatewayUnavailableException.php](../../../Modules/PaymentGateway/Exceptions/GatewayUnavailableException.php)
- [IdempotencyConflictException.php](../../../Modules/PaymentGateway/Exceptions/IdempotencyConflictException.php)
- [InvalidPayerException.php](../../../Modules/PaymentGateway/Exceptions/InvalidPayerException.php)
- [PaymentGatewayException.php](../../../Modules/PaymentGateway/Exceptions/PaymentGatewayException.php)
- [WebhookSignatureInvalidException.php](../../../Modules/PaymentGateway/Exceptions/WebhookSignatureInvalidException.php)
- [CobrancaQuery.php](../../../Modules/PaymentGateway/Repositories/CobrancaQuery.php)
