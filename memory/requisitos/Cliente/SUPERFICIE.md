---
name: "SUPERFÍCIE — Cliente"
description: "Índice GERADO dos artefatos do módulo Cliente reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Cliente
tabelas_dominio: ["contacts", "customer_groups"]
---

# 🗺️ Superfície de código — Cliente

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Cliente --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o módulo `Cliente` é CLASSE B — o código mora no núcleo UltimatePOS (`app/`), sem diretório modular homônimo. A membership vem de uma **semente curada** de paths do core declarada em `module-surface.mjs::CORE_APP_MODULES` (revisável no diff) + `resources/js/Pages/Cliente/**`. **O que NÃO é:** cobertura/nota/status (donos: `screen-coverage-map.mjs` + `casos-gate`). As **tabelas do domínio** (`contacts`, `customer_groups`) são metadado-ÂNCORA declarado, **não** o derivador (derivar por tabela over-inclui — medido 2026-07-21).

**Total mapeado:** 313 arquivos em 18 papéis.

## Controllers — 29

- [CallLogController.php](../../../Modules/Crm/Http/Controllers/CallLogController.php)
- [CampaignController.php](../../../Modules/Crm/Http/Controllers/CampaignController.php)
- [ClienteAuditoriaController.php](../../../Modules/Crm/Http/Controllers/ClienteAuditoriaController.php)
- [ClienteAutosaveController.php](../../../Modules/Crm/Http/Controllers/ClienteAutosaveController.php)
- [ClienteIaController.php](../../../Modules/Crm/Http/Controllers/ClienteIaController.php)
- [ClienteLookupController.php](../../../Modules/Crm/Http/Controllers/ClienteLookupController.php)
- [ClienteOssDataController.php](../../../Modules/Crm/Http/Controllers/ClienteOssDataController.php)
- [ClienteVeiculosController.php](../../../Modules/Crm/Http/Controllers/ClienteVeiculosController.php)
- [ContactAddressController.php](../../../Modules/Crm/Http/Controllers/ContactAddressController.php)
- [ContactBookingController.php](../../../Modules/Crm/Http/Controllers/ContactBookingController.php)
- [ContactLoginController.php](../../../Modules/Crm/Http/Controllers/ContactLoginController.php)
- [CrmDashboardController.php](../../../Modules/Crm/Http/Controllers/CrmDashboardController.php)
- [CrmMarketplaceController.php](../../../Modules/Crm/Http/Controllers/CrmMarketplaceController.php)
- [CrmSettingsController.php](../../../Modules/Crm/Http/Controllers/CrmSettingsController.php)
- [DashboardController.php](../../../Modules/Crm/Http/Controllers/DashboardController.php)
- [DataController.php](../../../Modules/Crm/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Crm/Http/Controllers/InstallController.php)
- [LeadController.php](../../../Modules/Crm/Http/Controllers/LeadController.php)
- [LedgerController.php](../../../Modules/Crm/Http/Controllers/LedgerController.php)
- [ManageProfileController.php](../../../Modules/Crm/Http/Controllers/ManageProfileController.php)
- [OrderRequestController.php](../../../Modules/Crm/Http/Controllers/OrderRequestController.php)
- [ProposalController.php](../../../Modules/Crm/Http/Controllers/ProposalController.php)
- [ProposalTemplateController.php](../../../Modules/Crm/Http/Controllers/ProposalTemplateController.php)
- [PurchaseController.php](../../../Modules/Crm/Http/Controllers/PurchaseController.php)
- [ReportController.php](../../../Modules/Crm/Http/Controllers/ReportController.php)
- [ScheduleController.php](../../../Modules/Crm/Http/Controllers/ScheduleController.php)
- [ScheduleLogController.php](../../../Modules/Crm/Http/Controllers/ScheduleLogController.php)
- [SellController.php](../../../Modules/Crm/Http/Controllers/SellController.php)
- [ContactController.php](../../../app/Http/Controllers/ContactController.php)

## Requests (validação) — 16

- [DeleteProposalRequest.php](../../../Modules/Crm/Http/Requests/DeleteProposalRequest.php)
- [IndexLeadRequest.php](../../../Modules/Crm/Http/Requests/IndexLeadRequest.php)
- [IndexProposalRequest.php](../../../Modules/Crm/Http/Requests/IndexProposalRequest.php)
- [MassDestroyCallLogRequest.php](../../../Modules/Crm/Http/Requests/MassDestroyCallLogRequest.php)
- [MassDestroyLeadRequest.php](../../../Modules/Crm/Http/Requests/MassDestroyLeadRequest.php)
- [StoreCallLogRequest.php](../../../Modules/Crm/Http/Requests/StoreCallLogRequest.php)
- [StoreCampaignRequest.php](../../../Modules/Crm/Http/Requests/StoreCampaignRequest.php)
- [StoreCrmContactRequest.php](../../../Modules/Crm/Http/Requests/StoreCrmContactRequest.php)
- [StoreLeadRequest.php](../../../Modules/Crm/Http/Requests/StoreLeadRequest.php)
- [StoreProposalRequest.php](../../../Modules/Crm/Http/Requests/StoreProposalRequest.php)
- [StoreScheduleRequest.php](../../../Modules/Crm/Http/Requests/StoreScheduleRequest.php)
- [UpdateCallLogRequest.php](../../../Modules/Crm/Http/Requests/UpdateCallLogRequest.php)
- [UpdateCampaignRequest.php](../../../Modules/Crm/Http/Requests/UpdateCampaignRequest.php)
- [UpdateLeadRequest.php](../../../Modules/Crm/Http/Requests/UpdateLeadRequest.php)
- [UpdateProposalRequest.php](../../../Modules/Crm/Http/Requests/UpdateProposalRequest.php)
- [UpdateScheduleRequest.php](../../../Modules/Crm/Http/Requests/UpdateScheduleRequest.php)

## Middleware — 2

- [CheckContactLogin.php](../../../Modules/Crm/Http/Middleware/CheckContactLogin.php)
- [ContactSidebarMenu.php](../../../Modules/Crm/Http/Middleware/ContactSidebarMenu.php)

## Services — 9

- [BrLookupService.php](../../../Modules/Crm/Services/BrLookupService.php)
- [CallLogService.php](../../../Modules/Crm/Services/CallLogService.php)
- [CampaignService.php](../../../Modules/Crm/Services/CampaignService.php)
- [ContactBookingService.php](../../../Modules/Crm/Services/ContactBookingService.php)
- [CrmLeadService.php](../../../Modules/Crm/Services/CrmLeadService.php)
- [DealPipelineService.php](../../../Modules/Crm/Services/DealPipelineService.php)
- [LeadAssignmentService.php](../../../Modules/Crm/Services/LeadAssignmentService.php)
- [ProposalService.php](../../../Modules/Crm/Services/ProposalService.php)
- [ScheduleService.php](../../../Modules/Crm/Services/ScheduleService.php)

## Models / Entities — 14

- [Campaign.php](../../../Modules/Crm/Entities/Campaign.php)
- [CrmCallLog.php](../../../Modules/Crm/Entities/CrmCallLog.php)
- [CrmContact.php](../../../Modules/Crm/Entities/CrmContact.php)
- [CrmContactPersonCommission.php](../../../Modules/Crm/Entities/CrmContactPersonCommission.php)
- [CrmMarketplace.php](../../../Modules/Crm/Entities/CrmMarketplace.php)
- [Deal.php](../../../Modules/Crm/Entities/Deal.php)
- [Leaduser.php](../../../Modules/Crm/Entities/Leaduser.php)
- [Proposal.php](../../../Modules/Crm/Entities/Proposal.php)
- [ProposalTemplate.php](../../../Modules/Crm/Entities/ProposalTemplate.php)
- [Schedule.php](../../../Modules/Crm/Entities/Schedule.php)
- [ScheduleLog.php](../../../Modules/Crm/Entities/ScheduleLog.php)
- [ScheduleUser.php](../../../Modules/Crm/Entities/ScheduleUser.php)
- [Contact.php](../../../app/Contact.php)
- [CustomerGroup.php](../../../app/CustomerGroup.php)

## Console / Commands — 3

- [CrmHealthCommand.php](../../../Modules/Crm/Console/Commands/CrmHealthCommand.php)
- [CreateRecursiveFollowup.php](../../../Modules/Crm/Console/CreateRecursiveFollowup.php)
- [SendScheduleNotification.php](../../../Modules/Crm/Console/SendScheduleNotification.php)

## Providers — 2

- [CrmServiceProvider.php](../../../Modules/Crm/Providers/CrmServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Crm/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Crm/Routes/api.php)
- [web.php](../../../Modules/Crm/Routes/web.php)

## Migrations (schema) — 27

- [2020_03_19_130231_add_contact_id_to_users_table.php](../../../Modules/Crm/Database/Migrations/2020_03_19_130231_add_contact_id_to_users_table.php)
- [2020_03_27_133605_create_schedules_table.php](../../../Modules/Crm/Database/Migrations/2020_03_27_133605_create_schedules_table.php)
- [2020_03_27_133628_create_schedule_users_table.php](../../../Modules/Crm/Database/Migrations/2020_03_27_133628_create_schedule_users_table.php)
- [2020_03_30_112834_create_schedule_logs_table.php](../../../Modules/Crm/Database/Migrations/2020_03_30_112834_create_schedule_logs_table.php)
- [2020_04_02_182331_add_crm_module_version_to_system_table.php](../../../Modules/Crm/Database/Migrations/2020_04_02_182331_add_crm_module_version_to_system_table.php)
- [2020_04_08_153231_modify_cloumn_in_contacts_table.php](../../../Modules/Crm/Database/Migrations/2020_04_08_153231_modify_cloumn_in_contacts_table.php)
- [2020_04_09_101052_create_lead_users_table.php](../../../Modules/Crm/Database/Migrations/2020_04_09_101052_create_lead_users_table.php)
- [2020_04_16_114747_create_crm_campaigns_table.php](../../../Modules/Crm/Database/Migrations/2020_04_16_114747_create_crm_campaigns_table.php)
- [2021_01_07_155757_add_followup_additional_info_column_to_crm_schedules_table.php](../../../Modules/Crm/Database/Migrations/2021_01_07_155757_add_followup_additional_info_column_to_crm_schedules_table.php)
- [2021_02_02_140021_add_additional_info_to_crm_campaigns_table.php](../../../Modules/Crm/Database/Migrations/2021_02_02_140021_add_additional_info_to_crm_campaigns_table.php)
- [2021_02_02_173651_add_new_columns_to_contacts_table.php](../../../Modules/Crm/Database/Migrations/2021_02_02_173651_add_new_columns_to_contacts_table.php)
- [2021_02_04_120439_create_call_logs_table.php](../../../Modules/Crm/Database/Migrations/2021_02_04_120439_create_call_logs_table.php)
- [2021_02_08_172047_add_mobile_name_column_to_crm_call_logs_table.php](../../../Modules/Crm/Database/Migrations/2021_02_08_172047_add_mobile_name_column_to_crm_call_logs_table.php)
- [2021_02_16_190038_add_crm_module_indexing.php](../../../Modules/Crm/Database/Migrations/2021_02_16_190038_add_crm_module_indexing.php)
- [2021_02_19_120846_create_crm_followup_invoices.php](../../../Modules/Crm/Database/Migrations/2021_02_19_120846_create_crm_followup_invoices.php)
- [2021_02_22_132125_add_follow_up_by_to_crm_schedules_table.php](../../../Modules/Crm/Database/Migrations/2021_02_22_132125_add_follow_up_by_to_crm_schedules_table.php)
- [2021_03_24_160736_add_department_and_designation_to_users_table.php](../../../Modules/Crm/Database/Migrations/2021_03_24_160736_add_department_and_designation_to_users_table.php)
- [2021_06_15_152924_create_proposal_templates_table.php](../../../Modules/Crm/Database/Migrations/2021_06_15_152924_create_proposal_templates_table.php)
- [2021_06_16_114448_add_recursive_fields_to_crm_schedules_table.php](../../../Modules/Crm/Database/Migrations/2021_06_16_114448_add_recursive_fields_to_crm_schedules_table.php)
- [2021_06_16_125740_create_proposals_table.php](../../../Modules/Crm/Database/Migrations/2021_06_16_125740_create_proposals_table.php)
- [2021_09_24_065738_add_crm_settings_column_to_business_table.php](../../../Modules/Crm/Database/Migrations/2021_09_24_065738_add_crm_settings_column_to_business_table.php)
- [2022_02_09_055012_create_crm_marketplaces_table.php](../../../Modules/Crm/Database/Migrations/2022_02_09_055012_create_crm_marketplaces_table.php)
- [2022_02_17_113045_add_source_id_to_marketplace.php](../../../Modules/Crm/Database/Migrations/2022_02_17_113045_add_source_id_to_marketplace.php)
- [2022_03_02_180929_add_followup_category_id.php](../../../Modules/Crm/Database/Migrations/2022_03_02_180929_add_followup_category_id.php)
- [2022_05_26_061553_create_crm_contact_person_commissions_table.php](../../../Modules/Crm/Database/Migrations/2022_05_26_061553_create_crm_contact_person_commissions_table.php)
- [2022_06_06_073006_add_cc_and_bcc_columns_to_crm_proposals_table.php](../../../Modules/Crm/Database/Migrations/2022_06_06_073006_add_cc_and_bcc_columns_to_crm_proposals_table.php)
- [2026_05_17_120000_create_crm_deals_table.php](../../../Modules/Crm/Database/Migrations/2026_05_17_120000_create_crm_deals_table.php)

## Seeders — 1

- [CrmDatabaseSeeder.php](../../../Modules/Crm/Database/Seeders/CrmDatabaseSeeder.php)

## Config — 2

- [config.php](../../../Modules/Crm/Config/config.php)
- [retention.php](../../../Modules/Crm/Config/retention.php)

## Views (Blade) — 91

- 91 arquivos em [Modules/Crm/Resources/views/booking/](../../../Modules/Crm/Resources/views/booking) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Telas (Inertia/React) — 7

- [Create.tsx](../../../resources/js/Pages/Cliente/Create.tsx)
- [Edit.tsx](../../../resources/js/Pages/Cliente/Edit.tsx)
- [Import.tsx](../../../resources/js/Pages/Cliente/Import.tsx)
- [Index.tsx](../../../resources/js/Pages/Cliente/Index.tsx)
- [Ledger.tsx](../../../resources/js/Pages/Cliente/Ledger.tsx)
- [Map.tsx](../../../resources/js/Pages/Cliente/Map.tsx)
- [Show.tsx](../../../resources/js/Pages/Cliente/Show.tsx)

## Componentes / apoio de tela — 31

- [ActiveChip.tsx](../../../resources/js/Pages/Cliente/_components/ActiveChip.tsx)
- [Avatar.tsx](../../../resources/js/Pages/Cliente/_components/Avatar.tsx)
- [KpiStripClickable.tsx](../../../resources/js/Pages/Cliente/_components/KpiStripClickable.tsx)
- [Pills.tsx](../../../resources/js/Pages/Cliente/_components/Pills.tsx)
- [AuditoriaTab.tsx](../../../resources/js/Pages/Cliente/_drawer/AuditoriaTab.tsx)
- [ClassificacaoTab.tsx](../../../resources/js/Pages/Cliente/_drawer/ClassificacaoTab.tsx)
- [ComercialTab.tsx](../../../resources/js/Pages/Cliente/_drawer/ComercialTab.tsx)
- [ContatoTab.tsx](../../../resources/js/Pages/Cliente/_drawer/ContatoTab.tsx)
- [EnderecoTab.tsx](../../../resources/js/Pages/Cliente/_drawer/EnderecoTab.tsx)
- [EnderecosEntregaList.tsx](../../../resources/js/Pages/Cliente/_drawer/EnderecosEntregaList.tsx)
- [IATab.tsx](../../../resources/js/Pages/Cliente/_drawer/IATab.tsx)
- [IdentificacaoTab.tsx](../../../resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx)
- [OssTab.tsx](../../../resources/js/Pages/Cliente/_drawer/OssTab.tsx)
- [PlacasMainTab.tsx](../../../resources/js/Pages/Cliente/_drawer/PlacasMainTab.tsx)
- [ClienteForm.tsx](../../../resources/js/Pages/Cliente/_form/ClienteForm.tsx)
- [ClienteRail.tsx](../../../resources/js/Pages/Cliente/_form/ClienteRail.tsx)
- [DadosFiscaisBRSection.tsx](../../../resources/js/Pages/Cliente/_form/DadosFiscaisBRSection.tsx)
- [Field.tsx](../../../resources/js/Pages/Cliente/_form/Field.tsx)
- [ActionsMenu.tsx](../../../resources/js/Pages/Cliente/_show/ActionsMenu.tsx)
- [ActivitiesTab.tsx](../../../resources/js/Pages/Cliente/_show/ActivitiesTab.tsx)
- [AddDiscountModal.tsx](../../../resources/js/Pages/Cliente/_show/AddDiscountModal.tsx)
- [ContactPicker.tsx](../../../resources/js/Pages/Cliente/_show/ContactPicker.tsx)
- [DocumentsTab.tsx](../../../resources/js/Pages/Cliente/_show/DocumentsTab.tsx)
- [LedgerTab.tsx](../../../resources/js/Pages/Cliente/_show/LedgerTab.tsx)
- [PaymentsTab.tsx](../../../resources/js/Pages/Cliente/_show/PaymentsTab.tsx)
- [PessoasContatoTab.tsx](../../../resources/js/Pages/Cliente/_show/PessoasContatoTab.tsx)
- [RewardPointsTab.tsx](../../../resources/js/Pages/Cliente/_show/RewardPointsTab.tsx)
- [RiscoClienteCard.tsx](../../../resources/js/Pages/Cliente/_show/RiscoClienteCard.tsx)
- [SalesTab.tsx](../../../resources/js/Pages/Cliente/_show/SalesTab.tsx)
- [SubscriptionsTab.tsx](../../../resources/js/Pages/Cliente/_show/SubscriptionsTab.tsx)
- [VehiclesTab.tsx](../../../resources/js/Pages/Cliente/_show/VehiclesTab.tsx)

## Charters (lei da tela) — 7

- [Create.charter.md](../../../resources/js/Pages/Cliente/Create.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/Cliente/Edit.charter.md)
- [Import.charter.md](../../../resources/js/Pages/Cliente/Import.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Cliente/Index.charter.md)
- [Ledger.charter.md](../../../resources/js/Pages/Cliente/Ledger.charter.md)
- [Map.charter.md](../../../resources/js/Pages/Cliente/Map.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Cliente/Show.charter.md)

## Casos (contrato UC) — 7

- [Create.casos.md](../../../resources/js/Pages/Cliente/Create.casos.md)
- [Edit.casos.md](../../../resources/js/Pages/Cliente/Edit.casos.md)
- [Import.casos.md](../../../resources/js/Pages/Cliente/Import.casos.md)
- [Index.casos.md](../../../resources/js/Pages/Cliente/Index.casos.md)
- [Ledger.casos.md](../../../resources/js/Pages/Cliente/Ledger.casos.md)
- [Map.casos.md](../../../resources/js/Pages/Cliente/Map.casos.md)
- [Show.casos.md](../../../resources/js/Pages/Cliente/Show.casos.md)

## Testes (Pest) — 13

- 13 arquivos em [Modules/Crm/Tests/Feature/](../../../Modules/Crm/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Demais arquivos (manifestos, docs, assets e misc) — 50

- [ClienteProximaAcaoAgent.php](../../../Modules/Crm/Ai/Agents/ClienteProximaAcaoAgent.php)
- [ClienteResumoAgent.php](../../../Modules/Crm/Ai/Agents/ClienteResumoAgent.php)
- [ClienteSegmentoAgent.php](../../../Modules/Crm/Ai/Agents/ClienteSegmentoAgent.php)
- [CHANGELOG.md](../../../Modules/Crm/CHANGELOG.md)
- [.gitkeep](../../../Modules/Crm/Config/.gitkeep)
- [.gitkeep](../../../Modules/Crm/Console/.gitkeep)
- [CrmLeadRepositoryInterface.php](../../../Modules/Crm/Contracts/CrmLeadRepositoryInterface.php)
- [.gitkeep](../../../Modules/Crm/Database/Migrations/.gitkeep)
- [.gitkeep](../../../Modules/Crm/Database/Seeders/.gitkeep)
- [.gitkeep](../../../Modules/Crm/Database/factories/.gitkeep)
- [.gitkeep](../../../Modules/Crm/Entities/.gitkeep)
- [.gitkeep](../../../Modules/Crm/Http/Controllers/.gitkeep)
- [.gitkeep](../../../Modules/Crm/Http/Middleware/.gitkeep)
- [.gitkeep](../../../Modules/Crm/Http/Requests/.gitkeep)
- [ScheduleNotification.php](../../../Modules/Crm/Notifications/ScheduleNotification.php)
- [SendCampaignNotification.php](../../../Modules/Crm/Notifications/SendCampaignNotification.php)
- [SendProposalNotification.php](../../../Modules/Crm/Notifications/SendProposalNotification.php)
- [CampaignPolicy.php](../../../Modules/Crm/Policies/CampaignPolicy.php)
- [ProposalPolicy.php](../../../Modules/Crm/Policies/ProposalPolicy.php)
- [.gitkeep](../../../Modules/Crm/Providers/.gitkeep)
- [CrmLeadRepository.php](../../../Modules/Crm/Repositories/CrmLeadRepository.php)
- [.gitkeep](../../../Modules/Crm/Resources/assets/.gitkeep)
- [crm.js](../../../Modules/Crm/Resources/assets/js/crm.js)
- [crm.css](../../../Modules/Crm/Resources/assets/sass/crm.css)
- [.gitkeep](../../../Modules/Crm/Resources/lang/.gitkeep)
- [lang.php](../../../Modules/Crm/Resources/lang/ar/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/ce/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/de/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/en/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/es/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/fr/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/hi/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/id/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/lo/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/nl/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/ps/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/pt/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/ro/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/sq/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/tr/lang.php)
- [lang.php](../../../Modules/Crm/Resources/lang/vi/lang.php)
- [.gitkeep](../../../Modules/Crm/Resources/views/.gitkeep)
- [SCOPE.md](../../../Modules/Crm/SCOPE.md)
- [.gitkeep](../../../Modules/Crm/Tests/.gitkeep)
- [CrmUtil.php](../../../Modules/Crm/Utils/CrmUtil.php)
- [composer.json](../../../Modules/Crm/composer.json)
- [module.json](../../../Modules/Crm/module.json)
- [package.json](../../../Modules/Crm/package.json)
- [webpack.mix.js](../../../Modules/Crm/webpack.mix.js)
- [cliente-form-types.ts](../../../resources/js/Pages/Cliente/_form/cliente-form-types.ts)
