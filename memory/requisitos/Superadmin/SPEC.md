# SPEC — Modules/Superadmin

> Módulo de governança interna do oimpresso (uso backoffice Wagner). Herdado do UltimatePOS v6, gerencia businesses, packages, subscriptions, communicator, frontend pages e settings globais. **Cross-tenant intencional** — superadmin opera sobre todos os `business_id` (uma das poucas áreas legítimas com `withoutGlobalScopes()`).

## Contexto

- **Stack:** Laravel 13.6 + Blade legacy (não migrado MWART)
- **Acesso:** restrito ao role `superadmin` (Wagner) — gate `is_superadmin` middleware
- **Tabelas core:** `packages`, `subscriptions`, `superadmin_communicator_logs`, `superadmin_frontend_pages`, `system`
- **Pré-requisito Tier 0:** TODA query cross-tenant em código `Modules/Superadmin/` DEVE ser comentada `// SUPERADMIN: <razão>` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

## User Stories

### US-SUPER-001 — Listagem e CRUD de Businesses
**Como** superadmin, **quero** listar/criar/editar/suspender businesses, **pra** gerenciar clientes do oimpresso multi-tenant.
- Tela: `business/index.blade.php` (DataTable cross-tenant)
- Controller: `BusinessController` métodos `index/create/store/show/edit/update`
- Aceite: criar business novo dispara `NewBusinessNotification` + `NewBusinessWelcomNotification` ao owner; permite reset de senha via `update_password_modal`

### US-SUPER-002 — Gestão de Packages (planos comerciais)
**Como** superadmin, **quero** criar e configurar packages (Free/Starter/Pro/etc) com custom_permissions + limites de location/user/product, **pra** definir SKUs comerciais.
- Tela: `packages/index|create|edit.blade.php`
- Entity: `Package` com JSON `custom_permissions`
- Aceite: package marcado `enable_custom_link` aparece em `/pricing` público; campos `min_termination_alert_days` controlam alertas

### US-SUPER-003 — Subscriptions (cobrança recorrente)
**Como** superadmin, **quero** consultar e ativar subscriptions, incluindo confirmar pagamentos offline, **pra** controlar acesso pago dos clientes.
- Tela: `subscription/index.blade.php` + `pay.blade.php`
- Controller: `SubscriptionController`, `SuperadminSubscriptionsController`
- Aceite: gateways suportados PayPal/Stripe/Razorpay/PesaPal/Paystack/Flutterwave/offline; cron `SubscriptionExpiryAlert` envia `SendSubscriptionExpiryAlert` D-N antes do término

### US-SUPER-004 — Communicator (mensagens cross-tenant)
**Como** superadmin, **quero** enviar mensagens (email/notification) pra grupos de businesses filtrados, **pra** comunicar manutenção/upgrade/aviso legal.
- Tela: `communicator/index.blade.php`
- Controller: `CommunicatorController`
- Entity: `SuperadminCommunicatorLog` (audit trail)
- Aceite: filtra por package/status; envia `SuperadminCommunicator` notification; loga destinatários e status

### US-SUPER-005 — Frontend Pages (site público)
**Como** superadmin, **quero** editar páginas estáticas do site público (Sobre/Contato/Termos/Privacidade), **pra** manter conteúdo institucional fora do código.
- Tela: `pages/index|create|edit|show.blade.php`
- Controller: `PageController`
- Entity: `SuperadminFrontendPage` (slug + body HTML)

### US-SUPER-006 — Manage Modules (instalar/desinstalar)
**Como** superadmin, **quero** ativar/desativar módulos nWidart por business, **pra** controlar o que cada cliente vê.
- Controller: `SuperadminController::manageModules`
- Aceite: respeita `module.json` `requires` (não desinstala dependência viva)

### US-SUPER-007 — System Info (saúde do sistema)
**Como** superadmin, **quero** ver versão Laravel/PHP/MySQL, uso de disco, jobs failed, **pra** diagnosticar saúde do servidor sem SSH.
- Tela: `superadmin/index.blade.php` (dashboard)
- Controller: `SuperadminController::index`

### US-SUPER-008 — Settings globais (SMTP/Pusher/Cron/Backup/Gateways)
**Como** superadmin, **quero** editar settings que afetam todos os businesses (SMTP, gateways de pagamento, cron, backup), **pra** configurar infra sem mexer em `.env`.
- Tela: `superadmin_settings/edit.blade.php` + partials
- Controller: `SuperadminSettingsController`
- Tabela: `system` (key/value)

### US-SUPER-009 — Pricing público (`/pricing`)
**Como** visitante anônimo, **quero** ver planos disponíveis com preço/limite/feature comparison, **pra** decidir contratar.
- Tela: `pricing/index.blade.php`
- Controller: `PricingController`
- Aceite: respeita `enable_custom_link` no Package; ordena por `sort_order`

### US-SUPER-010 — Usuario 360 (visão consolidada do cliente)
**Como** superadmin, **quero** ver consolidado de um business (subscription ativa + uso + tickets + última atividade), **pra** preparar call de suporte/upsell.
- Controller: `Usuario360Controller`
- Aceite: cross-tenant explícito + logs de acesso em `audit_log` (LGPD Art. 7º)

## Anti-padrões (NÃO fazer)

- ❌ Expor rota do Superadmin sem middleware `is_superadmin`
- ❌ `withoutGlobalScopes()` sem comentário `// SUPERADMIN: <razão>`
- ❌ Cobrar gateway novo sem ADR de arquitetura financeira
- ❌ Apagar `superadmin_communicator_logs` (audit append-only)

## Testes existentes (Wave B)

- `Tests/Feature/MultiTenantIsolationTest.php` — garante que rota Superadmin exige role + que cross-tenant é intencional
- `Tests/Feature/ScaffoldTest.php`
- `Tests/Feature/SmokeRoutesTest.php`
