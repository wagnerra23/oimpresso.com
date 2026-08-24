repo: wagnerra23/oimpresso.com
branch: main
path: Modules/Crm

## Last sync
date: 2026-08-24T14:46:33Z
tree: 5f96f574d5eb (hash de árvore do github_get_tree — NÃO é sha de commit; commit não verificado neste turno)

### Updated in this project
- Funil de negócios alinhado ao `Modules/Crm/Entities/Deal.php`: 6 stages canônicos PT-BR + `PROBABILIDADES_DEFAULT` + previsão ponderada (`valorPonderado`).
- Confirmado no `main` que `resources/js/Pages/Crm/` não existe — o trio .md do módulo é fonte nova (pacote em `handoff-crm/`).
- Módulo Crm em `module_version 2.1`, `pid 7` (`Modules/Crm/Config/config.php`).
- Formato do trio copiado de `resources/js/Pages/Cliente/Ledger.{charter,casos}.md` (frontmatter + UC com Dado/Quando/Então).

## Screen map
| Tela no protótipo (rota do shell) | Arquivos do repo que a originaram |
|---|---|
| `crm-painel` | `Modules/Crm/Resources/views/crm_dashboard/index.blade.php` · `Http/Controllers/CrmDashboardController.php` |
| `crm-leads` (+ ficha) | `views/lead/{index,show}.blade.php` · `lead/partial/{lead_info,lead_schedule}` · `LeadController.php` |
| `crm-followups` (+ antecipado, recorrente, logs) | `views/schedule/*.blade.php` · `views/schedule_log/*.blade.php` · `ScheduleController.php` · `ScheduleLogController.php` |
| `crm-campanhas` | `views/campaign/{index,create,edit,show}.blade.php` · `CampaignController.php` |
| `crm-logins` / `crm-comissoes` | `views/contact_login/{all_contacts_login,commissions,create,edit}.blade.php` · `ContactLoginController.php` |
| `crm-chamadas` | `views/call_logs/index.blade.php` · `CallLogController.php` |
| `crm-relatorios` | `views/reports/{index,leads_to_customer_details}.blade.php` · `ReportController.php` |
| `crm-modelo` / `crm-propostas` | `views/proposal_template/*` · `views/proposal/*` · `ProposalTemplateController.php` · `ProposalController.php` |
| `crm-marketplace` | `views/marketplace/index.blade.php` · `CrmMarketplaceController.php` |
| `crm-pedidos` | `views/order_request/{index,all_list}.blade.php` · `OrderRequestController.php` |
| `crm-taxonomias` | `views/layouts/nav.blade.php` (links `TaxonomyController?type=source|life_stage|followup_category`) |
| `crm-config` | `views/settings/index.blade.php` · `CrmSettingsController.php` |
| `crm-portal` | `views/{dashboard,profile,purchase,sell,ledger,booking,order_request}/*` · grupo `prefix('contact')` de `Routes/web.php` |
| `crm` (funil) | `Modules/Crm/Entities/Deal.php` · `Services/DealPipelineService.php` (stages e probabilidades) |
