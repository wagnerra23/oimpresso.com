---
id: requisitos-superadmin-spec
module: Superadmin
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
status: ativo
na_justified:
  D5: "Cross-tenant intencional Wagner-only — superadmin opera FORA do multi-tenant por design (ADR 0093 §exceções + ADR 0094 Constituição Art. 6). Cliente externo biz=4 ROTA LIVRE não é alvo; gate `is_superadmin` bloqueia tudo que não seja Wagner. Penalizar D5 distorce ranking de módulo de backoffice."
  D4.c: "REVOGADO em 2026-08-19: o módulo ESTÁ migrando pra Inertia/React (ondas SA-O1 dashboard, SA-O2 negócios), por decisão [W]. A justificativa anterior dizia que as ~50 views Blade seriam preservadas sem migração e que nenhuma decisão futura previa reescrita — as duas afirmações deixaram de valer. Cada tela migrada nasce com o trio (charter+casos+teste) e RUNBOOK do MWART. As views ainda não migradas seguem em Blade até a onda que as cobre; a limpeza é a SA-O6."
na_justified_v3:
  D8.b: "Superadmin não expõe rotas em `VerifyCsrfToken::except` — todas rotas passam pelo CSRF middleware UltimatePOS padrão. D8.b não aplica por design — não há route do módulo no `except`."
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0153-module-grade-rubrica-v1
  - 0154-module-grade-v2-na-justificado
  - 0155-module-grade-v3-sub-dimensoes-gate-ci
---

# SPEC — Modules/Superadmin

> **N/A justificado** D5 + D4.c (v2) + D8.b (v3) — módulo cross-tenant Wagner-only com Blade legacy preservado. Não há cliente externo nem investimento MWART previsto. Detalhes na seção "Bounded context Wagner-only" abaixo.

> Módulo de governança interna do oimpresso (uso backoffice Wagner). Herdado do UltimatePOS v6, gerencia businesses, packages, subscriptions, communicator, frontend pages e settings globais. **Cross-tenant intencional** — superadmin opera sobre todos os `business_id` (uma das poucas áreas legítimas com `withoutGlobalScopes()`).

## Contexto

- **Stack:** Laravel 13.6 + Blade legacy (não migrado MWART)
- **Acesso:** restrito ao role `superadmin` (Wagner) — gate `is_superadmin` middleware
- **Tabelas core:** `packages`, `subscriptions`, `superadmin_communicator_logs`, `superadmin_frontend_pages`, `system`
- **Pré-requisito Tier 0:** TODA query cross-tenant em código `Modules/Superadmin/` DEVE ser comentada `// SUPERADMIN: <razão>` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

## User Stories

### US-SUPER-001 — Listagem e CRUD de Businesses
**Como** superadmin, **quero** listar/criar/editar/suspender businesses, **pra** gerenciar clientes do oimpresso multi-tenant.
**Implementado em:** `Modules/Superadmin/Http/Controllers/BusinessController.php` · `Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/Index.tsx` · `Modules/Superadmin/Notifications/NewBusinessNotification.php` · verificado@8af585a (2026-07-02)
**Testado em:** `Modules/Superadmin/Tests/Feature/SuperadminNegociosContratoTest.php`
- Tela: `superadmin/Negocios/Index` (Inertia desde a SA-O2; era `business/index.blade.php` + DataTables). Charter + casos ao lado do `.tsx` · RUNBOOK: `RUNBOOK-negocios.md`
**Aceite (SA-O2, só a LISTA):** a rota responde Inertia e não DataTables (UC-SANEG-01); 1 negócio = 1 linha e o total da página bate com a contagem real (UC-SANEG-02); admin de negócio é barrado enquanto o superadmin passa (UC-SANEG-03); a lista enxerga TODOS os business, cross-tenant intencional por ADR 0093 §exceções (UC-SANEG-04); valor de filtro fora da lista é descartado (UC-SANEG-05). Criar/editar/desativar é a SA-O3 e segue no Blade.
- Controller: `BusinessController` métodos `index/create/store/show/edit/update`
- Aceite: criar business novo dispara `NewBusinessNotification` + `NewBusinessWelcomNotification` ao owner; permite reset de senha via `update_password_modal`

### US-SUPER-002 — Gestão de Packages (planos comerciais)
**Como** superadmin, **quero** criar e configurar packages (Free/Starter/Pro/etc) com custom_permissions + limites de location/user/product, **pra** definir SKUs comerciais.
**Implementado em:** `Modules/Superadmin/Http/Controllers/PackagesController.php` · `Modules/Superadmin/Entities/Package.php` · `Modules/Superadmin/Resources/views/packages/index.blade.php` · verificado@8af585a (2026-07-02)
- Tela: `packages/index|create|edit.blade.php`
- Entity: `Package` com JSON `custom_permissions`
- Aceite: package marcado `enable_custom_link` aparece em `/pricing` público; campos `min_termination_alert_days` controlam alertas

### US-SUPER-003 — Subscriptions (cobrança recorrente)
**Como** superadmin, **quero** consultar e ativar subscriptions, incluindo confirmar pagamentos offline, **pra** controlar acesso pago dos clientes.
**Implementado em:** `Modules/Superadmin/Http/Controllers/SubscriptionController.php` · `Modules/Superadmin/Http/Controllers/SuperadminSubscriptionsController.php` · `Modules/Superadmin/Console/SubscriptionExpiryAlert.php` · verificado@8af585a (2026-07-02)
- Tela: `subscription/index.blade.php` + `pay.blade.php`
- Controller: `SubscriptionController`, `SuperadminSubscriptionsController`
- Aceite: gateways suportados PayPal/Stripe/Razorpay/PesaPal/Paystack/Flutterwave/offline; cron `SubscriptionExpiryAlert` envia `SendSubscriptionExpiryAlert` D-N antes do término

### US-SUPER-004 — Communicator (mensagens cross-tenant)
**Como** superadmin, **quero** enviar mensagens (email/notification) pra grupos de businesses filtrados, **pra** comunicar manutenção/upgrade/aviso legal.
**Implementado em:** `Modules/Superadmin/Http/Controllers/CommunicatorController.php` · `Modules/Superadmin/Entities/SuperadminCommunicatorLog.php` · `Modules/Superadmin/Resources/views/communicator/index.blade.php` · verificado@8af585a (2026-07-02)
- Tela: `communicator/index.blade.php`
- Controller: `CommunicatorController`
- Entity: `SuperadminCommunicatorLog` (audit trail)
- Aceite: filtra por package/status; envia `SuperadminCommunicator` notification; loga destinatários e status

### US-SUPER-005 — Frontend Pages (site público)
**Como** superadmin, **quero** editar páginas estáticas do site público (Sobre/Contato/Termos/Privacidade), **pra** manter conteúdo institucional fora do código.
**Implementado em:** `Modules/Superadmin/Http/Controllers/PageController.php` · `Modules/Superadmin/Entities/SuperadminFrontendPage.php` · `Modules/Superadmin/Resources/views/pages/index.blade.php` · verificado@8af585a (2026-07-02)
- Tela: `pages/index|create|edit|show.blade.php`
- Controller: `PageController`
- Entity: `SuperadminFrontendPage` (slug + body HTML)

### US-SUPER-006 — Manage Modules (instalar/desinstalar)
**Como** superadmin, **quero** ativar/desativar módulos nWidart por business, **pra** controlar o que cada cliente vê.
**Implementado em:** _parcial_ · `Modules/Superadmin/Http/Controllers/PackagesController.php` · `app/Http/Controllers/ModuleManagementController.php` · verificado@8af585a (2026-07-02) — `SuperadminController::manageModules` citado na US não existe; controle por business é via `custom_permissions` do Package e a ativação global nWidart via rota `/modulos`; check de dependências `requires` do module.json não implementado
- Controller: `SuperadminController::manageModules`
- Aceite: respeita `module.json` `requires` (não desinstala dependência viva)

### US-SUPER-007 — System Info (saúde do sistema)
**Como** superadmin, **quero** ver versão Laravel/PHP/MySQL, uso de disco, jobs failed, **pra** diagnosticar saúde do servidor sem SSH.
**Implementado em:** _parcial_ · `Modules/Superadmin/Http/Controllers/SuperadminController.php` · `Modules/Superadmin/Resources/views/superadmin/index.blade.php` · verificado@8af585a (2026-07-02) — dashboard mostra métricas de subscriptions/businesses (index+stats); falta system info do aceite (versão Laravel/PHP/MySQL, uso de disco, jobs failed)
- Tela: `superadmin/index.blade.php` (dashboard)
- Controller: `SuperadminController::index`

### US-SUPER-008 — Settings globais (SMTP/Pusher/Cron/Backup/Gateways)
**Como** superadmin, **quero** editar settings que afetam todos os businesses (SMTP, gateways de pagamento, cron, backup), **pra** configurar infra sem mexer em `.env`.
**Implementado em:** `Modules/Superadmin/Http/Controllers/SuperadminSettingsController.php` · `Modules/Superadmin/Resources/views/superadmin_settings/edit.blade.php` · verificado@8af585a (2026-07-02)
- Tela: `superadmin_settings/edit.blade.php` + partials
- Controller: `SuperadminSettingsController`
- Tabela: `system` (key/value)

### US-SUPER-009 — Pricing público (`/pricing`)
**Como** visitante anônimo, **quero** ver planos disponíveis com preço/limite/feature comparison, **pra** decidir contratar.
**Implementado em:** `Modules/Superadmin/Http/Controllers/PricingController.php` · `Modules/Superadmin/Resources/js/Pages/Site/Pricing.tsx` · `Modules/Superadmin/Resources/views/pricing/index.blade.php` · verificado@8af585a (2026-07-02)
- Tela: `pricing/index.blade.php`
- Controller: `PricingController`
- Aceite: respeita `enable_custom_link` no Package; ordena por `sort_order`

### US-SUPER-010 — Usuario 360 (visão consolidada do cliente)
**Como** superadmin, **quero** ver consolidado de um business (subscription ativa + uso + tickets + última atividade), **pra** preparar call de suporte/upsell.
**Implementado em:** _parcial_ · `Modules/Superadmin/Http/Controllers/Usuario360Controller.php` · `Modules/Superadmin/Resources/js/Pages/superadmin/Usuario360/Show.tsx` · verificado@8af585a (2026-07-02) — visão 360 Inertia lê `mcp_audit_log` pra exibir auditoria; falta gravar log de acesso do superadmin previsto no aceite (LGPD Art. 7º)
- Controller: `Usuario360Controller`
- Aceite: cross-tenant explícito + logs de acesso em `audit_log` (LGPD Art. 7º)
- 2026-08-19: a Page saiu de `resources/js/Pages/` e foi pra dentro do módulo ([W]: "vai para dentro do módulo"). O namespace Inertia **não** mudou — o path acima é o novo endereço do mesmo arquivo, e nenhum call-site do controller mudou.

### US-SUPER-011 — Visão geral da plataforma (`/superadmin`)
**Como** superadmin, **quero** ver o pulso do negócio-SaaS num período (entradas, cadastros sem assinatura e tendência), **pra** saber se a plataforma está crescendo ou vazando antes de abrir tela nenhuma.
**Implementado em:** _parcial_ · `Modules/Superadmin/Http/Controllers/SuperadminController.php` · `Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` · `Modules/Superadmin/Services/SuperadminDashboardService.php`
**Testado em:** `Modules/Superadmin/Tests/Feature/SuperadminDashboardContratoTest.php`
- Tela: `superadmin/Dashboard/Index` (Inertia desde a SA-O1; era `superadmin::superadmin.index` em Blade/AdminLTE)
- Charter + casos: ao lado do `.tsx` · RUNBOOK: `RUNBOOK-dashboard.md`
**Aceite:** KPIs saem do `SuperadminDashboardService`, não de query inline no controller (UC-SADASH-02) · a rota responde Inertia com o componente `superadmin/Dashboard/Index`, não Blade (UC-SADASH-01) · admin de negócio é barrado enquanto o superadmin passa (UC-SADASH-03) · as contagens enxergam TODOS os negócios, cross-tenant intencional por ADR 0093 §exceções (UC-SADASH-04) · nenhum bloco sem query no backend chega às props (UC-SADASH-05). Provado por `Modules/Superadmin/Tests/Feature/SuperadminDashboardContratoTest.php`.
- **Parcial por escolha, não por esquecimento:** MRR, funil trial→pago, churn, receita por pacote e a fila "Vencendo ou vencido" ainda **não têm query** e por isso não são renderizados — o protótipo os desenha com mock, e mock em produção é número fabricado. Entram na SA-O1b (churn depende da migration `cancel_reason`, decisão [W] 2026-08-19)

## Anti-padrões (NÃO fazer)

- ❌ Expor rota do Superadmin sem middleware `is_superadmin`
- ❌ `withoutGlobalScopes()` sem comentário `// SUPERADMIN: <razão>`
- ❌ Cobrar gateway novo sem ADR de arquitetura financeira
- ❌ Apagar `superadmin_communicator_logs` (audit append-only)

## Testes existentes (Wave B)

- `Tests/Feature/MultiTenantIsolationTest.php` — garante que rota Superadmin exige role + que cross-tenant é intencional
- `Tests/Feature/ScaffoldTest.php`
- `Tests/Feature/SmokeRoutesTest.php`
