---
date: "2026-06-13"
topic: "Auditoria: testes que corrompem SQLite :memory: compartilhado (snapshot ranqueado)"
authors: [W, C]
---

# Corruptores SQLite — snapshot 2026-06-13

> Gerado por `node scripts/audit/sqlite-test-corruptors.mjs --json`. **Regenere a qualquer momento** — este arquivo é um retrato datado, não a fonte da verdade.

Motivação: a suíte roda em SQLite `:memory:`. Testes com schema sintético manual (`Schema::create/drop` de tabelas compartilhadas) corrompem o schema pro próximo teste da mesma conexão → cascata de fails. É o "lever real" do floor SDD F2b (~19 corruptores citados nos handoffs → na verdade 237 potenciais).

## Resumo

- Test files escaneados: **1228**
- Corruptores potenciais: **237**
- Buckets (raio de cascata): **S=58** crítico · **A=89** alto · **B=64** médio · **C=26** baixo

Ataque por ordem: **S → A**. Cada um: converter pra `RefreshDatabase` (migrations reais) OU quarentenar com `markTestSkipped` não-sqlite (como a família era-sqlite já faz).

Colunas: **tier** | **score** | **arquivo** | **quarentenado** (já tem marcador era-sqlite) | **tabelas de alto raio** | **razões**.

## Bucket S (58)

| tier | score | arquivo | quar. | alto raio | razões |
|---|---|---|---|---|---|
| S | 165 | `tests/Feature/Domain/Fsm/ExecuteStageActionServiceTest.php` | não | users, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | não-quarentenado; alto-raio[users,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 165 | `tests/Feature/Jobs/CancelarCobrancaInterJobTest.php` | não | users, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | não-quarentenado; alto-raio[users,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 165 | `tests/Feature/Jobs/RefundCobrancaInterJobTest.php` | não | users, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | não-quarentenado; alto-raio[users,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 155 | `Modules/RecurringBilling/Tests/Feature/Wave21NewSubscriptionTest.php` | não | contacts, activity_log | não-quarentenado; alto-raio[contacts,activity_log]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 155 | `Modules/RecurringBilling/Tests/Feature/Wave2Observer3ActionsTest.php` | não | contacts, activity_log | não-quarentenado; alto-raio[contacts,activity_log]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 155 | `Modules/RecurringBilling/Tests/Feature/Wave6PlanCrudTest.php` | não | contacts, activity_log | não-quarentenado; alto-raio[contacts,activity_log]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 155 | `Modules/RecurringBilling/Tests/Feature/Wave7FaturasIndexTest.php` | não | activity_log, contacts | não-quarentenado; alto-raio[activity_log,contacts]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 155 | `Modules/RecurringBilling/Tests/Feature/Wave9NotesFavoritesTest.php` | não | contacts, activity_log | não-quarentenado; alto-raio[contacts,activity_log]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 135 | `tests/Feature/Domain/Inventory/BomResolverTest.php` | não | products, variations | não-quarentenado; alto-raio[products,variations]; writes-sem-isolamento; DDL-manual |
| S | 135 | `tests/Feature/Domain/Inventory/ReservarEstoqueBomTest.php` | não | products, variations | não-quarentenado; alto-raio[products,variations]; writes-sem-isolamento; DDL-manual |
| S | 125 | `Modules/Financeiro/Tests/Feature/MultiTenantComprehensiveTest.php` | não | activity_log | não-quarentenado; alto-raio[activity_log]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 125 | `Modules/RecurringBilling/Tests/Feature/AssinaturaServiceWave18Test.php` | não | activity_log | não-quarentenado; alto-raio[activity_log]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 125 | `Modules/RecurringBilling/Tests/Feature/BoletoCredentialResolverTest.php` | não | activity_log | não-quarentenado; alto-raio[activity_log]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 125 | `Modules/RecurringBilling/Tests/Feature/CustomerJourneyTest.php` | não | activity_log | não-quarentenado; alto-raio[activity_log]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 125 | `Modules/RecurringBilling/Tests/Feature/InvoiceGeneratorServiceTest.php` | não | activity_log | não-quarentenado; alto-raio[activity_log]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 125 | `Modules/RecurringBilling/Tests/Feature/Wave23EditarAssinaturaTest.php` | não | activity_log | não-quarentenado; alto-raio[activity_log]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 125 | `Modules/RecurringBilling/Tests/Feature/Wave8ConfiguracoesIndexTest.php` | não | activity_log | não-quarentenado; alto-raio[activity_log]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 110 | `tests/Feature/Console/FsmBulkStartPipelineCommandTest.php` | sim | users, transactions, activity_log | quarentenado(era-sqlite); alto-raio[users,transactions,activity_log]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 110 | `tests/Feature/Modules/Copiloto/TenancyLeakTest.php` | sim | users, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 105 | `Modules/Crm/Tests/Feature/Wave27DealPipelineTest.php` | não | activity_log | não-quarentenado; alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| S | 105 | `Modules/Jana/Tests/Feature/Ai/Clarify/ClarifyCascadeServiceTest.php` | não | activity_log | não-quarentenado; alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| S | 105 | `Modules/NfeBrasil/Tests/Feature/CertificadoFallbackLegadoTest.php` | não | business | não-quarentenado; alto-raio[business]; writes-sem-isolamento; DDL-manual |
| S | 105 | `Modules/NfeBrasil/Tests/Feature/DanfeServiceTest.php` | não | business | não-quarentenado; alto-raio[business]; writes-sem-isolamento; DDL-manual |
| S | 105 | `Modules/NfeBrasil/Tests/Feature/NfeDomainModelsTest.php` | não | activity_log | não-quarentenado; alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| S | 105 | `Modules/Repair/Tests/Feature/MultiTenantRepairTest.php` | não | activity_log | não-quarentenado; alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| S | 95 | `Modules/Governance/Tests/Feature/CrossTenantPolicyTest.php` | não | — | não-quarentenado; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 95 | `Modules/Governance/Tests/Feature/GovernanceGateTest.php` | não | — | não-quarentenado; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 95 | `Modules/Governance/Tests/Feature/MultiTenantGovernanceTest.php` | não | — | não-quarentenado; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 95 | `Modules/Governance/Tests/Feature/SmokeRoutesTest.php` | não | — | não-quarentenado; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 95 | `Modules/RecurringBilling/Tests/Feature/AtualizarCobrancaAssinaturaTest.php` | não | — | não-quarentenado; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 95 | `Modules/RecurringBilling/Tests/Feature/RepositoryWave18Test.php` | não | — | não-quarentenado; mutação-conexão; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/Jana/Tests/Feature/Ai/BriefDiarioAgentTest.php` | sim | transactions, transaction_payments, contacts, channels, products, transaction_sell_lines | quarentenado(era-sqlite); alto-raio[transactions,transaction_payments,contacts,channels,products,transaction_sell_lines]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/Jana/Tests/Feature/BriefDiarioServiceTest.php` | sim | transactions, transaction_payments, contacts, channels, products, transaction_sell_lines | quarentenado(era-sqlite); alto-raio[transactions,transaction_payments,contacts,channels,products,transaction_sell_lines]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/NfeBrasil/Tests/Feature/EmitirNFSeJobTest.php` | sim | users, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/RecurringBilling/Tests/Feature/CancelarCobrancaAsaasJobTest.php` | sim | users, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/RecurringBilling/Tests/Feature/RecurringV975SchemaTest.php` | sim | contacts, permissions, roles, role_has_permissions, model_has_permissions, model_has_roles, users | quarentenado(era-sqlite); alto-raio[contacts,permissions,roles,role_has_permissions,model_has_permissions,model_has_roles,users]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/RecurringBilling/Tests/Feature/RefundCobrancaAsaasJobTest.php` | sim | users, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/Repair/Tests/Feature/RepairFsmActionControllerTest.php` | sim | activity_log, business, users, model_has_roles, model_has_permissions, role_has_permissions | quarentenado(era-sqlite); alto-raio[activity_log,business,users,model_has_roles,model_has_permissions,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/Repair/Tests/Feature/Wave25RepairFsmCanonExpandedTest.php` | sim | users, model_has_roles, model_has_permissions, role_has_permissions, activity_log | quarentenado(era-sqlite); alto-raio[users,model_has_roles,model_has_permissions,role_has_permissions,activity_log]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/Whatsapp/Tests/Feature/AuditChannelAccessCommandTest.php` | sim | users, channels, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,channels,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/Whatsapp/Tests/Feature/AutoLinkContactTest.php` | sim | permissions, roles, role_has_permissions, model_has_permissions, model_has_roles, users, activity_log, contacts, channels | quarentenado(era-sqlite); alto-raio[permissions,roles,role_has_permissions,model_has_permissions,model_has_roles,users,activity_log,contacts,channels]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/Whatsapp/Tests/Feature/BackfillChannelAccessCommandTest.php` | sim | users, channels, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,channels,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/Whatsapp/Tests/Feature/CaixaUnificadaControllerTest.php` | sim | contacts, users, channels | quarentenado(era-sqlite); alto-raio[contacts,users,channels]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/Whatsapp/Tests/Feature/LinkContactTest.php` | sim | activity_log, contacts, channels | quarentenado(era-sqlite); alto-raio[activity_log,contacts,channels]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/Whatsapp/Tests/Feature/MacroVariantsCrudTest.php` | sim | permissions, roles, role_has_permissions, model_has_permissions, model_has_roles, users | quarentenado(era-sqlite); alto-raio[permissions,roles,role_has_permissions,model_has_permissions,model_has_roles,users]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/Whatsapp/Tests/Feature/NotificarClienteCancelamentoJobTest.php` | sim | business, contacts, transactions | quarentenado(era-sqlite); alto-raio[business,contacts,transactions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `Modules/Whatsapp/Tests/Feature/RegisterWhatsappPermissionsCommandTest.php` | sim | business, users, channels, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | quarentenado(era-sqlite); alto-raio[business,users,channels,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `tests/Feature/Domain/Fsm/CancelarVendaCascadeSideEffectTest.php` | sim | users, model_has_roles, model_has_permissions, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,model_has_roles,model_has_permissions,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `tests/Feature/Domain/Fsm/CurrentStageIdBypassObserverTest.php` | sim | users, model_has_roles, model_has_permissions, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,model_has_roles,model_has_permissions,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `tests/Feature/Domain/Fsm/EstornarBoletoJobTest.php` | sim | users, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `tests/Feature/Domain/Fsm/GateEmissaoPorVendaTest.php` | sim | users, business, transactions, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,business,transactions,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `tests/Feature/Domain/Fsm/MultiTenantIsolationTest.php` | sim | users, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `tests/Feature/Domain/Fsm/ProcessoVendaComProducaoTest.php` | sim | users, model_has_roles, model_has_permissions, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,model_has_roles,model_has_permissions,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `tests/Feature/Domain/Fsm/SaleHistoryControllerTest.php` | sim | users, model_has_roles, model_has_permissions, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,model_has_roles,model_has_permissions,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `tests/Feature/Domain/Fsm/StockReservationsTest.php` | sim | users, products, model_has_permissions, model_has_roles, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,products,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `tests/Feature/Domain/Fsm/TransactionDocumentTest.php` | sim | users, permissions, roles, model_has_permissions, model_has_roles, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,permissions,roles,model_has_permissions,model_has_roles,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 90 | `tests/Feature/Domain/Fsm/TransicaoCriticaExigeAutorizacaoTest.php` | sim | users, model_has_roles, model_has_permissions, role_has_permissions | quarentenado(era-sqlite); alto-raio[users,model_has_roles,model_has_permissions,role_has_permissions]; writes-sem-isolamento; DDL-manual |
| S | 80 | `Modules/RecurringBilling/Tests/Feature/AssinaturaCobrancaServiceTest.php` | não | — | não-quarentenado; mutação-conexão; DDL-manual |

## Bucket A (89)

| tier | score | arquivo | quar. | alto raio | razões |
|---|---|---|---|---|---|
| A | 75 | `Modules/ADS/Tests/Unit/ContextForTaskActiveTasksTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Financeiro/Tests/Feature/OndaCommentsAuditBridgeTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Financeiro/Tests/Feature/UnificadoCommentsAuditTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Governance/Tests/Feature/DetectOtelQueryTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Ai/UiJudgeRunMeasurementTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/BriefDiarioChatTriggerTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/HealthNarratorServiceTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/HealthSnapshotServiceTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/HitTrackerServiceTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/AutomationRegistrySyncTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/CyclesCloseToolTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/HandoffDiffToolTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/HandoffDraftToolTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/HandoffFetchSummarizedToolTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/InboxAutoCleanupJobTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/IndexarMemoryGitSoftDeleteRestoreTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/JanaCyclesAutoCloseExpiredCommandTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/JanaWeeklyDigestCommandTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/JanaWeeklyDigestEmailTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/KbAnswerToolTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/McpTasksHealthCheckCommandTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/MyInboxToolTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Mcp/WeeklyDigestFetchToolTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Memoria/Freshness/StalenessDetectorTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/NarrarSaudeEcosistemaJobTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Jana/Tests/Feature/Summarizer/AutoSummarizerServiceTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/NfeBrasil/Tests/Feature/CertificadoServiceTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/NfeBrasil/Tests/Feature/DanfeServicePrefersArquivosTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/NfeBrasil/Tests/Feature/ImportRegrasCsvServiceTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/NfeBrasil/Tests/Feature/SyncFiscalRuleToTaxRateTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/NfeBrasil/Tests/Feature/TributacaoControllerTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Repair/Tests/Feature/DeviceModelsInertiaSmokeTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/DispatchToJanaBotTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/DriverLayerUnionTypeTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/E2EJourneyBiz1Test.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/LidPhoneResolverTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/MetricsSnapshotBuilderTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/MultiTenantIsolationTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/ObservabilityTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/OfficeimpressoEnrichServiceTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/PhoneResolutionWebhookTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/PhonesMigrationDataTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/ProcessIncomingWebhookJobTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/SendInteractiveJobTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/SendMessageRequestTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/SendWhatsappMessageJobTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/WebhookBackpressureTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/WebhookReplayProtectionTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/WebhookSignatureTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/WhatsappDriverHealthCheckJobTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/WhatsappMessageObserverTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `Modules/Whatsapp/Tests/Feature/WhatsappTemplateTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `tests/Feature/Cliente/ClienteIndexDrawer760CharterTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `tests/Feature/Modules/Copiloto/ApuracaoIdempotenciaTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `tests/Feature/Modules/Copiloto/Mcp/IndexarMemoryGitParaDbTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `tests/Feature/Modules/Governance/DashboardExtensionTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 75 | `tests/Feature/Modules/Governance/DetectDriftCommandTest.php` | não | — | não-quarentenado; writes-sem-isolamento; DDL-manual |
| A | 70 | `Modules/Essentials/Tests/Feature/EssentialsBladeT1InertiaSmokeTest.php` | não | — | não-quarentenado; mutação-conexão |
| A | 70 | `Modules/Financeiro/Tests/Feature/FluxoCaixaServiceTest.php` | não | — | não-quarentenado; mutação-conexão |
| A | 70 | `tests/Browser/CoreScreens/A11yAxeBrowserTest.php` | não | — | não-quarentenado; mutação-conexão |
| A | 70 | `tests/Browser/CoreScreens/AuthBridgeSmokeTest.php` | não | — | não-quarentenado; mutação-conexão |
| A | 70 | `tests/Browser/CoreScreens/ConformanceProbesTest.php` | não | — | não-quarentenado; mutação-conexão |
| A | 70 | `tests/Browser/CoreScreens/PixelBaselineTest.php` | não | — | não-quarentenado; mutação-conexão |
| A | 60 | `Modules/Governance/Tests/Feature/ObservabilitySnapshotServiceTest.php` | não | — | não-quarentenado; DDL-manual |
| A | 60 | `Modules/Governance/Tests/Feature/ScorecardSnapshotCommandTest.php` | não | — | não-quarentenado; DDL-manual |
| A | 60 | `Modules/Governance/Tests/Feature/Wave28InitiativeServiceTest.php` | não | — | não-quarentenado; DDL-manual |
| A | 60 | `Modules/Jana/Tests/Feature/Mcp/AutomationRegistryMigrationTest.php` | não | — | não-quarentenado; DDL-manual |
| A | 60 | `Modules/Jana/Tests/Feature/RetentionPurgeCommandTest.php` | sim | business, activity_log | quarentenado(era-sqlite); alto-raio[business,activity_log]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/NfeBrasil/Tests/Feature/NfeInutilizacaoServiceTest.php` | sim | activity_log, business | quarentenado(era-sqlite); alto-raio[activity_log,business]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/Vestuario/Tests/Feature/Wave28DevolucaoServiceTest.php` | não | — | não-quarentenado; DDL-manual |
| A | 60 | `Modules/Whatsapp/Tests/Feature/ClientFeedbackDevTaskTest.php` | sim | activity_log, contacts | quarentenado(era-sqlite); alto-raio[activity_log,contacts]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/Whatsapp/Tests/Feature/CustomerMemoryRebuilderTest.php` | sim | business, contacts | quarentenado(era-sqlite); alto-raio[business,contacts]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/Whatsapp/Tests/Feature/EmployeePerformanceRebuilderTest.php` | sim | business, users | quarentenado(era-sqlite); alto-raio[business,users]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/Whatsapp/Tests/Feature/FeedbackReindexCommandTest.php` | sim | activity_log, contacts | quarentenado(era-sqlite); alto-raio[activity_log,contacts]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/Whatsapp/Tests/Feature/FeedbackRelevanceTest.php` | sim | activity_log, contacts | quarentenado(era-sqlite); alto-raio[activity_log,contacts]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/Whatsapp/Tests/Feature/InboxCleanupTest.php` | sim | users, channels | quarentenado(era-sqlite); alto-raio[users,channels]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/Whatsapp/Tests/Feature/IncidentCrossContact20260514E2ERegressionTest.php` | sim | contacts, channels | quarentenado(era-sqlite); alto-raio[contacts,channels]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/Whatsapp/Tests/Feature/LidBackfillObserverTest.php` | sim | contacts, channels | quarentenado(era-sqlite); alto-raio[contacts,channels]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/Whatsapp/Tests/Feature/LidCrossContactIncidentP0Test.php` | sim | contacts, channels | quarentenado(era-sqlite); alto-raio[contacts,channels]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/Whatsapp/Tests/Feature/MediaInboundProcessedTest.php` | sim | contacts, channels | quarentenado(era-sqlite); alto-raio[contacts,channels]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/Whatsapp/Tests/Feature/WhatsmeowBroadcastFilterTest.php` | sim | channels, activity_log | quarentenado(era-sqlite); alto-raio[channels,activity_log]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/Whatsapp/Tests/Feature/WhatsmeowMediaInboundTest.php` | sim | channels, activity_log | quarentenado(era-sqlite); alto-raio[channels,activity_log]; writes-sem-isolamento; DDL-manual |
| A | 60 | `Modules/Whatsapp/Tests/Feature/WhatsmeowOutboundFromMeRegressionTest.php` | sim | channels, activity_log | quarentenado(era-sqlite); alto-raio[channels,activity_log]; writes-sem-isolamento; DDL-manual |
| A | 60 | `tests/Feature/Domain/Fsm/SideEffects/EmitirNovaAposCancelamentoTest.php` | sim | transactions, transaction_sell_lines | quarentenado(era-sqlite); alto-raio[transactions,transaction_sell_lines]; writes-sem-isolamento; DDL-manual |
| A | 60 | `tests/Feature/Modules/Copiloto/MemoriaMetricaTest.php` | não | — | não-quarentenado; DDL-manual |
| A | 60 | `tests/Feature/Modules/Copiloto/MetricasApuradorTest.php` | não | — | não-quarentenado; DDL-manual |
| A | 60 | `tests/Feature/Modules/Copiloto/SemanticCacheServiceTest.php` | não | — | não-quarentenado; DDL-manual |
| A | 50 | `Modules/Whatsapp/Tests/Feature/AutoPrefixSenderNameTest.php` | sim | users | quarentenado(era-sqlite); alto-raio[users]; mutação-conexão; writes-sem-isolamento; DDL-manual |

## Bucket B (64)

<details><summary>64 arquivos (clique)</summary>

| tier | score | arquivo | quar. | alto raio | razões |
|---|---|---|---|---|---|
| B | 30 | `Modules/Jana/Tests/Feature/DsrServiceTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Jana/Tests/Feature/LgpdEsquecerTitularToolTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/KB/Tests/Feature/LgpdComplianceTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/KB/Tests/Feature/MultiTenantTraitTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/NfeBrasil/Tests/Feature/NfeEmissaoControllerSerializeUrlsTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/PaymentGateway/Tests/Feature/Cnab/Drivers/SicoobCnabDriverTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/PaymentGateway/Tests/Feature/CresolCnabDriverTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/PaymentGateway/Tests/Feature/EncryptedCredentialCastTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/PaymentGateway/Tests/Feature/InterImportarRecebimentosCommandTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/PaymentGateway/Tests/Feature/InterReconcilePixCommandTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/PaymentGateway/Tests/Feature/ReconciliarCobrancaServiceTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/PaymentGateway/Tests/Feature/SicrediCnabDriverTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/RecurringBilling/Tests/Feature/SyncBankBalancesJobTest.php` | sim | business | quarentenado(era-sqlite); alto-raio[business]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/RecurringBilling/Tests/Feature/SyncBankStatementsJobTest.php` | sim | business | quarentenado(era-sqlite); alto-raio[business]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/AuthStateDriftCheckTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/Baileys7xPayloadShapeTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/BlockContactTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/CanalFilaIsolationTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ChannelBaileysInstanceIdHelperTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ChannelBaileysWebhookIdempotencyTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ChannelImportHistoryGatingTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ChannelObserverSyncDaemonTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ChannelRequestUniqueIdentifierTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ChannelResetCommandTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ChannelsControllerAutoPurgeBeforeConnectTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ChannelsReconcilerCommandTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ChannelUserAccessTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ContactObserverCacheInvalidationTest.php` | sim | contacts | quarentenado(era-sqlite); alto-raio[contacts]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ConversationSchemaIdentitiesTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ConversationTagsTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/CsatFlowTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/CustomerMemoryBackfillCommandTest.php` | sim | contacts | quarentenado(era-sqlite); alto-raio[contacts]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/DispatchToJanaBotPiiRedactionTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/GuardiaoMidiaTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/HealthProbeChannelsCommandTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/HistorySyncMetricsTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/HistorySyncQueueArchitectureTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ImportHistoryCommandTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/InboxFiltersTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/InboxMultiPhoneFilterTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/InboxQueueDerivationTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/InboxSendInteractiveTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/InternalNoteTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/LastMessageDenormalizeTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/MacrosCrudTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/MacroVariantPickerTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/MediaMessageTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/MetricsAggregateCommandTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/NotifyRepairCustomerTest.php` | sim | contacts | quarentenado(era-sqlite); alto-raio[contacts]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/OmnichannelIsolationTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/PersistContactsFromHistorySyncJobTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ReconnectAndImportCommandTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/ReparseMediaFromPayloadCommandTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/RetryRecentMediaDownloadsTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/SlaScanCommandTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/SlashConfigTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/SlashCorrigirTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/SlashLembrarTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/SlashLembreteTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/WebhookMediaExtractTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `Modules/Whatsapp/Tests/Feature/WebhookOutboundFromMeRegressionTest.php` | sim | channels | quarentenado(era-sqlite); alto-raio[channels]; writes-sem-isolamento; DDL-manual |
| B | 30 | `tests/Feature/Domain/Fsm/SequencialNfeAposCancelamentoTest.php` | sim | business | quarentenado(era-sqlite); alto-raio[business]; writes-sem-isolamento; DDL-manual |
| B | 30 | `tests/Feature/Domain/Fsm/SideEffects/InutilizarFaixaNfeTest.php` | sim | business | quarentenado(era-sqlite); alto-raio[business]; writes-sem-isolamento; DDL-manual |
| B | 30 | `tests/Feature/Modules/Copiloto/Mcp/WhatsActiveToolTest.php` | sim | users | quarentenado(era-sqlite); alto-raio[users]; writes-sem-isolamento; DDL-manual |

</details>

## Bucket C (26)

<details><summary>26 arquivos (clique)</summary>

| tier | score | arquivo | quar. | alto raio | razões |
|---|---|---|---|---|---|
| C | 15 | `Modules/PaymentGateway/Tests/Feature/AilosCnabDriverTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; DDL-manual |
| C | 15 | `Modules/PaymentGateway/Tests/Feature/BanrisulCnabDriverTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; DDL-manual |
| C | 15 | `Modules/PaymentGateway/Tests/Feature/BBCnabDriverTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; DDL-manual |
| C | 15 | `Modules/PaymentGateway/Tests/Feature/BradescoCnabDriverTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; DDL-manual |
| C | 15 | `Modules/PaymentGateway/Tests/Feature/BtgCnabDriverTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; DDL-manual |
| C | 15 | `Modules/PaymentGateway/Tests/Feature/CaixaCnabDriverTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; DDL-manual |
| C | 15 | `Modules/PaymentGateway/Tests/Feature/CnabBoletoAdapterContractTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; DDL-manual |
| C | 15 | `Modules/PaymentGateway/Tests/Feature/CnabRetornoProcessorTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; DDL-manual |
| C | 15 | `Modules/PaymentGateway/Tests/Feature/ItauCnabDriverTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; DDL-manual |
| C | 15 | `Modules/PaymentGateway/Tests/Feature/RetryOrphanWebhookJobTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; DDL-manual |
| C | 15 | `Modules/PaymentGateway/Tests/Feature/SantanderCnabDriverTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; DDL-manual |
| C | 15 | `Modules/PaymentGateway/Tests/Feature/WebhookEndpointsTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; DDL-manual |
| C | 15 | `Modules/PaymentGateway/Tests/Feature/WebhookSignatureValidationTest.php` | sim | activity_log | quarentenado(era-sqlite); alto-raio[activity_log]; DDL-manual |
| C | 15 | `Modules/RecurringBilling/Tests/Feature/DomainModelsTest.php` | sim | contacts | quarentenado(era-sqlite); alto-raio[contacts]; DDL-manual |
| C | 0 | `Modules/RecurringBilling/Tests/Feature/InterWebhookControllerTest.php` | sim | — | quarentenado(era-sqlite); writes-sem-isolamento; DDL-manual |
| C | 0 | `Modules/RecurringBilling/Tests/Feature/SyncBankBalancesCommandTest.php` | sim | — | quarentenado(era-sqlite); writes-sem-isolamento; DDL-manual |
| C | 0 | `Modules/Whatsapp/Tests/Feature/LidBackfillCommandTest.php` | sim | — | quarentenado(era-sqlite); writes-sem-isolamento; DDL-manual |
| C | 0 | `Modules/Whatsapp/Tests/Feature/SidebarCountsTest.php` | sim | — | quarentenado(era-sqlite); writes-sem-isolamento; DDL-manual |
| C | 0 | `tests/Feature/Domain/Fsm/FsmDriftDetectorTest.php` | sim | — | quarentenado(era-sqlite); writes-sem-isolamento; DDL-manual |
| C | 0 | `tests/Feature/Domain/Fsm/FsmScanDriftCommandTest.php` | sim | — | quarentenado(era-sqlite); writes-sem-isolamento; DDL-manual |
| C | 0 | `tests/Feature/Domain/Fsm/SideEffects/OficinaAutoSideEffectsTest.php` | sim | — | quarentenado(era-sqlite); writes-sem-isolamento; DDL-manual |
| C | 0 | `tests/Feature/Modules/Copiloto/Mcp/McpAuthHealthTest.php` | sim | — | quarentenado(era-sqlite); writes-sem-isolamento; DDL-manual |
| C | 0 | `tests/Feature/Modules/Copiloto/Mcp/McpServerTest.php` | sim | — | quarentenado(era-sqlite); writes-sem-isolamento; DDL-manual |
| C | -15 | `Modules/RecurringBilling/Tests/Feature/AsaasWebhookIdempotencyTest.php` | sim | — | quarentenado(era-sqlite); DDL-manual |
| C | -15 | `Modules/RecurringBilling/Tests/Feature/BoletoServiceTest.php` | sim | — | quarentenado(era-sqlite); DDL-manual |
| C | -15 | `tests/Feature/Modules/Copiloto/Mcp/McpSchemaTest.php` | sim | — | quarentenado(era-sqlite); DDL-manual |

</details>

