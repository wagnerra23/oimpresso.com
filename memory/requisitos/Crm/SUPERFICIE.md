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
> **O que isto é:** o inventário completo das raízes `Modules/Crm/**` + `resources/js/Pages/Crm/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](../../../Modules/Crm/SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 232 arquivos em 14 papéis.

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

- [create.blade.php](../../../Modules/Crm/Resources/views/booking/create.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/booking/index.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/call_logs/index.blade.php)
- [create.blade.php](../../../Modules/Crm/Resources/views/campaign/create.blade.php)
- [edit.blade.php](../../../Modules/Crm/Resources/views/campaign/edit.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/campaign/index.blade.php)
- [show.blade.php](../../../Modules/Crm/Resources/views/campaign/show.blade.php)
- [all_contacts_login.blade.php](../../../Modules/Crm/Resources/views/contact_login/all_contacts_login.blade.php)
- [commissions.blade.php](../../../Modules/Crm/Resources/views/contact_login/commissions.blade.php)
- [contact_login_js.blade.php](../../../Modules/Crm/Resources/views/contact_login/contact_login_js.blade.php)
- [create.blade.php](../../../Modules/Crm/Resources/views/contact_login/create.blade.php)
- [edit.blade.php](../../../Modules/Crm/Resources/views/contact_login/edit.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/contact_login/index.blade.php)
- [contact_form_part.blade.php](../../../Modules/Crm/Resources/views/contact_login/partial/contact_form_part.blade.php)
- [contact_login_from.blade.php](../../../Modules/Crm/Resources/views/contact_login/partial/contact_login_from.blade.php)
- [tab_content.blade.php](../../../Modules/Crm/Resources/views/contact_login/partial/tab_content.blade.php)
- [tab_menu.blade.php](../../../Modules/Crm/Resources/views/contact_login/partial/tab_menu.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/crm_dashboard/index.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/dashboard/index.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/index.blade.php)
- [app.blade.php](../../../Modules/Crm/Resources/views/layouts/app.blade.php)
- [header.blade.php](../../../Modules/Crm/Resources/views/layouts/header.blade.php)
- [master.blade.php](../../../Modules/Crm/Resources/views/layouts/master.blade.php)
- [nav.blade.php](../../../Modules/Crm/Resources/views/layouts/nav.blade.php)
- [sidebar.blade.php](../../../Modules/Crm/Resources/views/layouts/sidebar.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/lead/index.blade.php)
- [lead_info.blade.php](../../../Modules/Crm/Resources/views/lead/partial/lead_info.blade.php)
- [lead_schedule.blade.php](../../../Modules/Crm/Resources/views/lead/partial/lead_schedule.blade.php)
- [show.blade.php](../../../Modules/Crm/Resources/views/lead/show.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/ledger/index.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/marketplace/index.blade.php)
- [all_list.blade.php](../../../Modules/Crm/Resources/views/order_request/all_list.blade.php)
- [create.blade.php](../../../Modules/Crm/Resources/views/order_request/create.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/order_request/index.blade.php)
- [product_row.blade.php](../../../Modules/Crm/Resources/views/order_request/product_row.blade.php)
- [edit.blade.php](../../../Modules/Crm/Resources/views/profile/edit.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/proposal/index.blade.php)
- [show.blade.php](../../../Modules/Crm/Resources/views/proposal/show.blade.php)
- [create.blade.php](../../../Modules/Crm/Resources/views/proposal_template/create.blade.php)
- [edit.blade.php](../../../Modules/Crm/Resources/views/proposal_template/edit.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/proposal_template/index.blade.php)
- [attachment.blade.php](../../../Modules/Crm/Resources/views/proposal_template/partials/attachment.blade.php)
- [template_form.blade.php](../../../Modules/Crm/Resources/views/proposal_template/partials/template_form.blade.php)
- [send.blade.php](../../../Modules/Crm/Resources/views/proposal_template/send.blade.php)
- [view.blade.php](../../../Modules/Crm/Resources/views/proposal_template/view.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/purchase/index.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/reports/index.blade.php)
- [leads_to_customer_details.blade.php](../../../Modules/Crm/Resources/views/reports/leads_to_customer_details.blade.php)
- [report_javascripts.blade.php](../../../Modules/Crm/Resources/views/reports/report_javascripts.blade.php)
- [create.blade.php](../../../Modules/Crm/Resources/views/schedule/create.blade.php)
- [create_advance_follow_up.blade.php](../../../Modules/Crm/Resources/views/schedule/create_advance_follow_up.blade.php)
- [create_recursive_follow_up.blade.php](../../../Modules/Crm/Resources/views/schedule/create_recursive_follow_up.blade.php)
- [edit.blade.php](../../../Modules/Crm/Resources/views/schedule/edit.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/schedule/index.blade.php)
- [advance_followup_modal.blade.php](../../../Modules/Crm/Resources/views/schedule/partial/advance_followup_modal.blade.php)
- [group_customers.blade.php](../../../Modules/Crm/Resources/views/schedule/partial/group_customers.blade.php)
- [group_invoices_by_customer.blade.php](../../../Modules/Crm/Resources/views/schedule/partial/group_invoices_by_customer.blade.php)
- [schedule_info.blade.php](../../../Modules/Crm/Resources/views/schedule/partial/schedule_info.blade.php)
- [schedule_info_invoices.blade.php](../../../Modules/Crm/Resources/views/schedule/partial/schedule_info_invoices.blade.php)
- [today_schedule.blade.php](../../../Modules/Crm/Resources/views/schedule/partial/today_schedule.blade.php)
- [show.blade.php](../../../Modules/Crm/Resources/views/schedule/show.blade.php)
- [create.blade.php](../../../Modules/Crm/Resources/views/schedule_log/create.blade.php)
- [edit.blade.php](../../../Modules/Crm/Resources/views/schedule_log/edit.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/schedule_log/index.blade.php)
- [log.blade.php](../../../Modules/Crm/Resources/views/schedule_log/partial/log.blade.php)
- [show.blade.php](../../../Modules/Crm/Resources/views/schedule_log/show.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/sell/index.blade.php)
- [index.blade.php](../../../Modules/Crm/Resources/views/settings/index.blade.php)

## Testes (Pest) — 13

- 13 arquivos em [Modules/Crm/Tests/Feature/](../../../Modules/Crm/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Demais arquivos (manifestos, docs, assets e misc) — 47

- [ClienteProximaAcaoAgent.php](../../../Modules/Crm/Ai/Agents/ClienteProximaAcaoAgent.php)
- [ClienteResumoAgent.php](../../../Modules/Crm/Ai/Agents/ClienteResumoAgent.php)
- [ClienteSegmentoAgent.php](../../../Modules/Crm/Ai/Agents/ClienteSegmentoAgent.php)
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
- [.gitkeep](../../../Modules/Crm/Tests/.gitkeep)
- [CrmUtil.php](../../../Modules/Crm/Utils/CrmUtil.php)
- [composer.json](../../../Modules/Crm/composer.json)
- [module.json](../../../Modules/Crm/module.json)
- [package.json](../../../Modules/Crm/package.json)
- [webpack.mix.js](../../../Modules/Crm/webpack.mix.js)
