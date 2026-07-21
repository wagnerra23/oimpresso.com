---
name: "SUPERFÍCIE — RecurringBilling"
description: "Índice GERADO dos artefatos do módulo RecurringBilling reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: RecurringBilling
---

# 🗺️ Superfície de código — RecurringBilling

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs RecurringBilling --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/RecurringBilling/**` + `resources/js/Pages/RecurringBilling/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 138 arquivos em 19 papéis.

## Controllers — 11

- [AsaasWebhookController.php](../../../Modules/RecurringBilling/Http/Controllers/AsaasWebhookController.php)
- [ConfiguracoesController.php](../../../Modules/RecurringBilling/Http/Controllers/ConfiguracoesController.php)
- [DataController.php](../../../Modules/RecurringBilling/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/RecurringBilling/Http/Controllers/InstallController.php)
- [InterWebhookController.php](../../../Modules/RecurringBilling/Http/Controllers/InterWebhookController.php)
- [InvoiceController.php](../../../Modules/RecurringBilling/Http/Controllers/InvoiceController.php)
- [PlanController.php](../../../Modules/RecurringBilling/Http/Controllers/PlanController.php)
- [RecurringBillingController.php](../../../Modules/RecurringBilling/Http/Controllers/RecurringBillingController.php)
- [SubscriptionEventController.php](../../../Modules/RecurringBilling/Http/Controllers/SubscriptionEventController.php)
- [SubscriptionFavoriteController.php](../../../Modules/RecurringBilling/Http/Controllers/SubscriptionFavoriteController.php)
- [SubscriptionNoteController.php](../../../Modules/RecurringBilling/Http/Controllers/SubscriptionNoteController.php)

## Requests (validação) — 7

- [CancelInvoiceRequest.php](../../../Modules/RecurringBilling/Http/Requests/CancelInvoiceRequest.php)
- [CancelSubscriptionRequest.php](../../../Modules/RecurringBilling/Http/Requests/CancelSubscriptionRequest.php)
- [PauseSubscriptionRequest.php](../../../Modules/RecurringBilling/Http/Requests/PauseSubscriptionRequest.php)
- [StoreAssinaturaRequest.php](../../../Modules/RecurringBilling/Http/Requests/StoreAssinaturaRequest.php)
- [StorePlanRequest.php](../../../Modules/RecurringBilling/Http/Requests/StorePlanRequest.php)
- [UpdateAssinaturaRequest.php](../../../Modules/RecurringBilling/Http/Requests/UpdateAssinaturaRequest.php)
- [UpdatePlanRequest.php](../../../Modules/RecurringBilling/Http/Requests/UpdatePlanRequest.php)

## Services — 12

- [AssinaturaCobrancaService.php](../../../Modules/RecurringBilling/Services/AssinaturaCobrancaService.php)
- [AssinaturaService.php](../../../Modules/RecurringBilling/Services/AssinaturaService.php)
- [InterPixCobDriver.php](../../../Modules/RecurringBilling/Services/Banking/Drivers/InterPixCobDriver.php)
- [InterStatementDriver.php](../../../Modules/RecurringBilling/Services/Banking/Drivers/InterStatementDriver.php)
- [InterBankingClient.php](../../../Modules/RecurringBilling/Services/Banking/InterBankingClient.php)
- [BoletoCredentialResolver.php](../../../Modules/RecurringBilling/Services/Boleto/BoletoCredentialResolver.php)
- [BoletoService.php](../../../Modules/RecurringBilling/Services/Boleto/BoletoService.php)
- [AsaasDriver.php](../../../Modules/RecurringBilling/Services/Boleto/Drivers/AsaasDriver.php)
- [C6Driver.php](../../../Modules/RecurringBilling/Services/Boleto/Drivers/C6Driver.php)
- [InterDriver.php](../../../Modules/RecurringBilling/Services/Boleto/Drivers/InterDriver.php)
- [GatewayBackfillService.php](../../../Modules/RecurringBilling/Services/GatewayBackfillService.php)
- [InvoiceGeneratorService.php](../../../Modules/RecurringBilling/Services/InvoiceGeneratorService.php)

## Models / Entities — 8

- [BoletoCredential.php](../../../Modules/RecurringBilling/Models/BoletoCredential.php)
- [ChargeAttempt.php](../../../Modules/RecurringBilling/Models/ChargeAttempt.php)
- [Invoice.php](../../../Modules/RecurringBilling/Models/Invoice.php)
- [Plan.php](../../../Modules/RecurringBilling/Models/Plan.php)
- [Subscription.php](../../../Modules/RecurringBilling/Models/Subscription.php)
- [SubscriptionEvent.php](../../../Modules/RecurringBilling/Models/SubscriptionEvent.php)
- [SubscriptionFavorite.php](../../../Modules/RecurringBilling/Models/SubscriptionFavorite.php)
- [SubscriptionNote.php](../../../Modules/RecurringBilling/Models/SubscriptionNote.php)

## Observers — 1

- [SubscriptionCachedFieldsObserver.php](../../../Modules/RecurringBilling/Observers/SubscriptionCachedFieldsObserver.php)

## Jobs — 6

- [CancelarCobrancaAsaasJob.php](../../../Modules/RecurringBilling/Jobs/CancelarCobrancaAsaasJob.php)
- [ProcessAsaasWebhookJob.php](../../../Modules/RecurringBilling/Jobs/ProcessAsaasWebhookJob.php)
- [ProcessInterWebhookJob.php](../../../Modules/RecurringBilling/Jobs/ProcessInterWebhookJob.php)
- [RefundCobrancaAsaasJob.php](../../../Modules/RecurringBilling/Jobs/RefundCobrancaAsaasJob.php)
- [SyncBankBalancesJob.php](../../../Modules/RecurringBilling/Jobs/SyncBankBalancesJob.php)
- [SyncBankStatementsJob.php](../../../Modules/RecurringBilling/Jobs/SyncBankStatementsJob.php)

## Events / Listeners — 2

- [AssinaturaAtualizada.php](../../../Modules/RecurringBilling/Events/AssinaturaAtualizada.php)
- [InvoicePaid.php](../../../Modules/RecurringBilling/Events/InvoicePaid.php)

## Console / Commands — 5

- [BackfillCachedFieldsCommand.php](../../../Modules/RecurringBilling/Console/Commands/BackfillCachedFieldsCommand.php)
- [BackfillGatewayCommand.php](../../../Modules/RecurringBilling/Console/Commands/BackfillGatewayCommand.php)
- [GenerateInvoicesCommand.php](../../../Modules/RecurringBilling/Console/Commands/GenerateInvoicesCommand.php)
- [RecurringHealthCommand.php](../../../Modules/RecurringBilling/Console/Commands/RecurringHealthCommand.php)
- [SyncBankBalancesCommand.php](../../../Modules/RecurringBilling/Console/Commands/SyncBankBalancesCommand.php)

## Providers — 2

- [RecurringBillingServiceProvider.php](../../../Modules/RecurringBilling/Providers/RecurringBillingServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/RecurringBilling/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/RecurringBilling/Routes/api.php)
- [web.php](../../../Modules/RecurringBilling/Routes/web.php)

## Migrations (schema) — 8

- [2026_05_06_000001_create_rb_boleto_credentials_table.php](../../../Modules/RecurringBilling/Database/Migrations/2026_05_06_000001_create_rb_boleto_credentials_table.php)
- [2026_05_06_000002_add_conta_bancaria_fk_to_rb_boleto_credentials.php](../../../Modules/RecurringBilling/Database/Migrations/2026_05_06_000002_add_conta_bancaria_fk_to_rb_boleto_credentials.php)
- [2026_05_06_000003_create_pg_webhook_events_table.php](../../../Modules/RecurringBilling/Database/Migrations/2026_05_06_000003_create_pg_webhook_events_table.php)
- [2026_05_06_001000_create_rb_plans_table.php](../../../Modules/RecurringBilling/Database/Migrations/2026_05_06_001000_create_rb_plans_table.php)
- [2026_05_06_001001_create_rb_subscriptions_table.php](../../../Modules/RecurringBilling/Database/Migrations/2026_05_06_001001_create_rb_subscriptions_table.php)
- [2026_05_06_001002_create_rb_invoices_table.php](../../../Modules/RecurringBilling/Database/Migrations/2026_05_06_001002_create_rb_invoices_table.php)
- [2026_05_06_001003_create_rb_charge_attempts_table.php](../../../Modules/RecurringBilling/Database/Migrations/2026_05_06_001003_create_rb_charge_attempts_table.php)
- [2026_05_16_120000_recurring_v975_schema.php](../../../Modules/RecurringBilling/Database/Migrations/2026_05_16_120000_recurring_v975_schema.php)

## Seeders — 2

- [RecurringBillingDatabaseSeeder.php](../../../Modules/RecurringBilling/Database/Seeders/RecurringBillingDatabaseSeeder.php)
- [RecurringBillingDemoSeeder.php](../../../Modules/RecurringBilling/Database/Seeders/RecurringBillingDemoSeeder.php)

## Config — 2

- [config.php](../../../Modules/RecurringBilling/Config/config.php)
- [retention.php](../../../Modules/RecurringBilling/Config/retention.php)

## Views (Blade) — 2

- 2 arquivos em [Modules/RecurringBilling/Resources/views/](../../../Modules/RecurringBilling/Resources/views) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Telas (Inertia/React) — 6

- [Index.tsx](../../../resources/js/Pages/RecurringBilling/Configuracoes/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/RecurringBilling/Faturas/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/RecurringBilling/Index.tsx)
- [Create.tsx](../../../resources/js/Pages/RecurringBilling/Planos/Create.tsx)
- [Edit.tsx](../../../resources/js/Pages/RecurringBilling/Planos/Edit.tsx)
- [Index.tsx](../../../resources/js/Pages/RecurringBilling/Planos/Index.tsx)

## Componentes / apoio de tela — 7

- [CheatSheet.tsx](../../../resources/js/Pages/RecurringBilling/_components/CheatSheet.tsx)
- [CmdPalette.tsx](../../../resources/js/Pages/RecurringBilling/_components/CmdPalette.tsx)
- [JanaPanel.tsx](../../../resources/js/Pages/RecurringBilling/_components/JanaPanel.tsx)
- [PresentationMode.tsx](../../../resources/js/Pages/RecurringBilling/_components/PresentationMode.tsx)
- [Sparkline.tsx](../../../resources/js/Pages/RecurringBilling/_components/Sparkline.tsx)
- [TourOnboarding.tsx](../../../resources/js/Pages/RecurringBilling/_components/TourOnboarding.tsx)
- [TroubleshooterOverlay.tsx](../../../resources/js/Pages/RecurringBilling/_components/TroubleshooterOverlay.tsx)

## Charters (lei da tela) — 6

- [Index.charter.md](../../../resources/js/Pages/RecurringBilling/Configuracoes/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/RecurringBilling/Faturas/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/RecurringBilling/Index.charter.md)
- [Create.charter.md](../../../resources/js/Pages/RecurringBilling/Planos/Create.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/RecurringBilling/Planos/Edit.charter.md)
- [Index.charter.md](../../../resources/js/Pages/RecurringBilling/Planos/Index.charter.md)

## Testes (Pest) — 39

- 39 arquivos em [Modules/RecurringBilling/Tests/Feature/](../../../Modules/RecurringBilling/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 10

- [BankStatementDriverContract.php](../../../Modules/RecurringBilling/Contracts/BankStatementDriverContract.php)
- [BoletoCredentialResolverInterface.php](../../../Modules/RecurringBilling/Contracts/BoletoCredentialResolverInterface.php)
- [BoletoDriverContract.php](../../../Modules/RecurringBilling/Contracts/BoletoDriverContract.php)
- [BoletoResult.php](../../../Modules/RecurringBilling/Dto/BoletoResult.php)
- [PixCobResult.php](../../../Modules/RecurringBilling/Dto/PixCobResult.php)
- [StatementLineDto.php](../../../Modules/RecurringBilling/Dto/StatementLineDto.php)
- [SubscriptionIndexPresenter.php](../../../Modules/RecurringBilling/Http/Presenters/SubscriptionIndexPresenter.php)
- [SubscriptionPolicy.php](../../../Modules/RecurringBilling/Policies/SubscriptionPolicy.php)
- [InvoiceRepository.php](../../../Modules/RecurringBilling/Repositories/InvoiceRepository.php)
- [SubscriptionRepository.php](../../../Modules/RecurringBilling/Repositories/SubscriptionRepository.php)
