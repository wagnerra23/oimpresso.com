---
name: "SUPERFÍCIE — Crm"
description: "Índice GERADO dos artefatos do módulo Crm reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Crm
---

# 🗺️ Superfície de código — Crm

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Crm --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Crm/**` + `resources/js/Pages/Crm/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 196 arquivos em 14 papéis.

## Controllers — 28

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

## Models / Entities — 12

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

## Views (Blade) — 68

- 68 arquivos em [Modules/Crm/Resources/views/booking/](../../../Modules/Crm/Resources/views/booking) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Testes (Pest) — 13

- 13 arquivos em [Modules/Crm/Tests/Feature/](../../../Modules/Crm/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 11

- [ClienteProximaAcaoAgent.php](../../../Modules/Crm/Ai/Agents/ClienteProximaAcaoAgent.php)
- [ClienteResumoAgent.php](../../../Modules/Crm/Ai/Agents/ClienteResumoAgent.php)
- [ClienteSegmentoAgent.php](../../../Modules/Crm/Ai/Agents/ClienteSegmentoAgent.php)
- [CrmLeadRepositoryInterface.php](../../../Modules/Crm/Contracts/CrmLeadRepositoryInterface.php)
- [ScheduleNotification.php](../../../Modules/Crm/Notifications/ScheduleNotification.php)
- [SendCampaignNotification.php](../../../Modules/Crm/Notifications/SendCampaignNotification.php)
- [SendProposalNotification.php](../../../Modules/Crm/Notifications/SendProposalNotification.php)
- [CampaignPolicy.php](../../../Modules/Crm/Policies/CampaignPolicy.php)
- [ProposalPolicy.php](../../../Modules/Crm/Policies/ProposalPolicy.php)
- [CrmLeadRepository.php](../../../Modules/Crm/Repositories/CrmLeadRepository.php)
- [CrmUtil.php](../../../Modules/Crm/Utils/CrmUtil.php)
