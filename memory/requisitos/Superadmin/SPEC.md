---
id: requisitos-superadmin-spec
module: Superadmin
version: "1.1"
last_updated: "2026-11-16"
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
  - 0170-onda5-simplificada
  - 0399-aposentar-rubrica-module-grade-gate-e-baseline
---

# SPEC — Modules/Superadmin

> **Histórico (até 2026-08-19):** este cabeçalho dizia "N/A justificado D5 + D4.c + D8.b — Blade legacy preservado, sem investimento MWART previsto". A D4.c foi revogada em 2026-08-19 (ver `na_justified` acima) e as telas migraram para Inertia nas ondas SA-O*. As chaves `na_justified*` e as ADRs 0153-0155 são da rubrica module-grade, aposentada pela [ADR 0399](../../decisions/0399-aposentar-rubrica-module-grade-gate-e-baseline.md) em 2026-09-15; ficam como registro, não como regra viva.

> Módulo de governança interna do oimpresso (uso backoffice Wagner). Herdado do UltimatePOS v6, gerencia businesses, packages, subscriptions, communicator, frontend pages e settings globais. **Cross-tenant intencional** — superadmin opera sobre todos os `business_id` (uma das poucas áreas legítimas com `withoutGlobalScopes()`).

## Contexto

- **Stack:** Laravel 13.6 + Inertia/React. Medido em 2026-11-16: 12 `Inertia::render` nos controllers do módulo (`git grep -n "Inertia::render" Modules/Superadmin/Http`). Seguem em Blade a tela de pagamento sem a flag `useV2SuperadminPagar`, o `/pricing/old`, a página pública `/page/{slug}` (`pages/show.blade.php`) e os partials de `subscription/`
- **Acesso:** as rotas `/superadmin/*` passam pelo middleware `superadmin` + `throttle:superadmin` (`Modules/Superadmin/Routes/web.php:13`). As rotas `/subscription/*` (Minha assinatura, Pagar) são telas do **cliente**, fora desse grupo, protegidas pela permissão `superadmin.access_package_subscriptions`
- **Tabelas core:** `packages`, `subscriptions`, `superadmin_communicator_logs`, `superadmin_frontend_pages`, `system`
- **Pré-requisito Tier 0:** TODA query cross-tenant em código `Modules/Superadmin/` DEVE ser comentada `// SUPERADMIN: <razão>` ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

## User Stories

### US-SUPER-001 — Listagem e CRUD de Businesses
**Como** superadmin, **quero** listar/criar/editar/suspender businesses, **pra** gerenciar clientes do oimpresso multi-tenant.
**Implementado em:** `Modules/Superadmin/Http/Controllers/BusinessController.php` · `Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/Index.tsx` · `Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/_components/NovoNegocio.tsx` · `Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/_components/AdicionarAssinatura.tsx` · `Modules/Superadmin/Notifications/NewBusinessNotification.php` · verificado@580be57 (2026-11-16)
**Testado em:** `Modules/Superadmin/Tests/Feature/SuperadminNegociosContratoTest.php`
- Tela: `superadmin/Negocios/Index` (Inertia desde a SA-O2; era `business/index.blade.php` + DataTables). Charter + casos ao lado do `.tsx` · RUNBOOK: `RUNBOOK-negocios.md`
**Aceite:** a rota responde Inertia e não DataTables (UC-SANEG-01); 1 negócio = 1 linha e o total da página bate com a contagem real (UC-SANEG-02); admin de negócio é barrado enquanto o superadmin passa (UC-SANEG-03); a lista enxerga TODOS os business, cross-tenant intencional por ADR 0093 §exceções (UC-SANEG-04); valor de filtro fora da lista é descartado (UC-SANEG-05); `/superadmin/business/create` leva ao drawer "Novo negócio" da lista (UC-SANEG-11, #8714, 2026-10-05); o drawer só monta o formulário com `?novo=1` (UC-SANEG-12); o store recusa usuário e e-mail repetidos sem criar nada (UC-SANEG-13); "Adicionar assinatura" grava no negócio com o preço do pacote (UC-SANEG-14, #8733, 2026-10-05).
- Controller: `BusinessController` métodos `index/create/store/show/edit/update`
- Aceite: criar business novo dispara `NewBusinessNotification` + `NewBusinessWelcomNotification` ao owner; permite reset de senha via `update_password_modal`

### US-SUPER-002 — Gestão de Packages (planos comerciais)
**Como** superadmin, **quero** criar e configurar packages (Free/Starter/Pro/etc) com custom_permissions + limites de location/user/product, **pra** definir SKUs comerciais.
**Implementado em:** `Modules/Superadmin/Http/Controllers/PackagesController.php` · `Modules/Superadmin/Entities/Package.php` · `Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx` · `Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/_components/PacoteForm.tsx` · verificado@580be57 (2026-11-16)
**Testado em:** `Modules/Superadmin/Tests/Feature/SuperadminPacotesContratoTest.php`
- Tela: `superadmin/Pacotes/Index.tsx` (Inertia desde a SA-O4c, 2026-08-21). Criar e editar viraram o drawer da grade em 2026-10-05 (#8725): `create()`/`edit()` redirecionam para `?pacote=novo` / `?pacote=<id>` (`PackagesController.php:230-310`). A assimetria de preço que o [RUNBOOK-pacotes §5.1](RUNBOOK-pacotes.md) mediu (`store()` em pt-BR × `update()` cru) foi fechada no mesmo PR: criar e editar gravam o mesmo preço (UC-SAPAC-11)
- Entity: `Package` com JSON `custom_permissions`
**Aceite:** a grade responde Inertia (UC-SAPAC-01) e barra admin de negócio (UC-SAPAC-02); mostra pacote inativo e privado, e o privado não entra no `/pricing` (UC-SAPAC-04, UC-SAPAC-05); só a permissão ligada vira módulo no card (UC-SAPAC-07); abrir a grade não grava nada (UC-SAPAC-08); chave de permissão fora do catálogo sobrevive ao salvar (UC-PAC-08); criar e editar gravam o mesmo preço (UC-SAPAC-11).

### US-SUPER-003 — Subscriptions (cobrança recorrente)
**Como** superadmin, **quero** consultar e ativar subscriptions, incluindo confirmar pagamentos offline, **pra** controlar acesso pago dos clientes.
**Implementado em:** `Modules/Superadmin/Http/Controllers/SubscriptionController.php` · `Modules/Superadmin/Http/Controllers/SuperadminSubscriptionsController.php` · `Modules/Superadmin/Resources/js/Pages/superadmin/Assinaturas/Index.tsx` · `Modules/Superadmin/Resources/js/Pages/superadmin/MinhaAssinatura/Index.tsx` · `Modules/Superadmin/Resources/js/Pages/superadmin/Pagar/Index.tsx` · `Modules/Superadmin/Support/RotuloAssinatura.php` · `Modules/Superadmin/Console/SubscriptionExpiryAlert.php` · verificado@580be57 (2026-11-16)
**Testado em:** `Modules/Superadmin/Tests/Feature/SuperadminAssinaturasContratoTest.php` · `Modules/Superadmin/Tests/Feature/SuperadminMinhaAssinaturaContratoTest.php` · `Modules/Superadmin/Tests/Feature/SuperadminPagarContratoTest.php`
- Tela: `superadmin/Assinaturas/Index.tsx` (Inertia desde a SA-O4a, 2026-08-20) — lista + ações por linha (SA-O4b, mesmo dia): mudar status e editar vigência, com toda escrita passando pelo `SubscriptionLifecycleService`. São **3 ações** (aprovar · vencer · cancelar), não os 5 status do F1 — o Service modela três transições, e os outros dois valores do enum não são escolha de operador. `subscription/index.blade.php` foi apagada no L5 (#10967, 2026-11-16). A tela do cliente é `superadmin/MinhaAssinatura/Index`, e o pagamento é `superadmin/Pagar/Index` atrás da flag `useV2SuperadminPagar` (com a flag desligada, ou `?classico=1`, segue `subscription/pay.blade.php` — UC-SAPG-02)
- Controller: `SubscriptionController`, `SuperadminSubscriptionsController`
**Aceite:** a lista enxerga assinatura de mais de um business (UC-SAASS-03) e não expõe valor cru do enum (UC-SAASS-04); aprovar calcula a vigência e deixa trilha (UC-SAASS-11); cancelar preserva registro, vigência e motivo (UC-SAASS-12); fim anterior ao início não é gravado (UC-SAASS-15); admin de negócio não muda status nem vigência (UC-SAASS-16); o cliente só vê assinaturas do próprio negócio (UC-SAMA-03); a tela Pagar nova só oferece Pix automático e offline (UC-SAPG-03).
- Gateways na Blade de pagamento: os partials de PayPal, Stripe, Razorpay, PesaPal, Paystack, Flutterwave, offline e PIX Automático (`pay_paymentgateway_pix_automatico.blade.php`, ADR 0170) seguem no repo. O cron `SubscriptionExpiryAlert` envia `SendSubscriptionExpiryAlert` D-N antes do término
- Fluxo ponta a ponta (empresa nova → trial → cobrança → pagamento → módulo → renovação/expiração): ver o SDD do fluxo de assinatura (lote L6, PR 3)

### US-SUPER-004 — Communicator (mensagens cross-tenant)
**Como** superadmin, **quero** enviar mensagens (email/notification) pra grupos de businesses filtrados, **pra** comunicar manutenção/upgrade/aviso legal.
**Implementado em:** `Modules/Superadmin/Http/Controllers/CommunicatorController.php` · `Modules/Superadmin/Entities/SuperadminCommunicatorLog.php` · `Modules/Superadmin/Resources/js/Pages/superadmin/Comunicador/Index.tsx` · verificado@580be57 (2026-11-16)
**Testado em:** `Modules/Superadmin/Tests/Feature/SuperadminComunicadorContratoTest.php`
- Tela: `superadmin/Comunicador/Index` (Inertia, #8342). `communicator/index.blade.php` foi apagada no L5 (#10967)
- Controller: `CommunicatorController`
- Entity: `SuperadminCommunicatorLog` (audit trail)
**Aceite:** a tela responde Inertia (UC-SACOM-01) e barra admin de negócio (UC-SACOM-02); os destinatários vêm de tenants diferentes (UC-SACOM-03); envio sem destinatário é recusado sem registrar nada (UC-SACOM-04); o corpo é gravado escapado (UC-SACOM-05); o envio aparece no histórico com o alcance (UC-SACOM-06).

### US-SUPER-005 — Frontend Pages (site público)
**Como** superadmin, **quero** editar páginas estáticas do site público (Sobre/Contato/Termos/Privacidade), **pra** manter conteúdo institucional fora do código.
**Implementado em:** `Modules/Superadmin/Http/Controllers/PageController.php` · `Modules/Superadmin/Entities/SuperadminFrontendPage.php` · `Modules/Superadmin/Resources/js/Pages/superadmin/Paginas/Index.tsx` · `Modules/Superadmin/Resources/views/pages/show.blade.php` · verificado@580be57 (2026-11-16)
**Testado em:** `Modules/Superadmin/Tests/Feature/SuperadminPaginasContratoTest.php`
- Tela: `superadmin/Paginas/Index` (Inertia, cutover em 2026-10-27). Resta em Blade só a página pública `/page/{slug}` (`pages/show.blade.php`)
- Controller: `PageController`
- Entity: `SuperadminFrontendPage` (slug + body HTML)
**Aceite:** a tela React é o padrão e as Blades de criar/editar não existem mais (UC-SAPAG-01); usuário comum é barrado (UC-SAPAG-02); slug repetido é recusado sem duplicar (UC-SAPAG-04); editar e excluir pela tela gravam e voltam para a lista (UC-SAPAG-05, UC-SAPAG-06).

### US-SUPER-006 — Manage Modules (instalar/desinstalar)
**Como** superadmin, **quero** ativar/desativar módulos nWidart por business, **pra** controlar o que cada cliente vê.
**Implementado em:** _parcial_ · `Modules/Superadmin/Http/Controllers/PackagesController.php` · `app/Http/Controllers/ModuleManagementController.php` · verificado@580be57 (2026-11-16) — `SuperadminController::manageModules` citado na US não existe; controle por business é via `custom_permissions` do Package e a ativação global nWidart via rota `/modulos`; check de dependências `requires` do module.json não implementado
- Controller citado originalmente: `SuperadminController::manageModules` (não existe)
**Aceite:** respeita `module.json` `requires` (não desinstala dependência viva)

### US-SUPER-007 — System Info (saúde do sistema)
**Como** superadmin, **quero** ver versão Laravel/PHP/MySQL, uso de disco, jobs failed, **pra** diagnosticar saúde do servidor sem SSH.
**Implementado em:** _parcial_ · `Modules/Superadmin/Http/Controllers/SuperadminController.php` · `Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` · verificado@580be57 (2026-11-16) — dashboard mostra métricas de subscriptions/businesses (index+stats); falta system info do aceite (versão Laravel/PHP/MySQL, uso de disco, jobs failed)
- Tela: `superadmin/Dashboard/Index` (Inertia desde a SA-O1; `superadmin/index.blade.php` foi apagada no L5, #10967). Medido em 2026-11-16: zero `PHP_VERSION`, `disk_free` ou `failed_jobs` no módulo
- Controller: `SuperadminController::index`
**Aceite:** a tela mostra versão de Laravel/PHP/MySQL, uso de disco e jobs com falha. Nada disso está entregue; a parte entregue (KPIs de negócios e assinaturas) é a US-SUPER-011.

### US-SUPER-008 — Settings globais (SMTP/Pusher/Cron/Backup/Gateways)
**Como** superadmin, **quero** editar settings que afetam todos os businesses (SMTP, gateways de pagamento, cron, backup), **pra** configurar infra sem mexer em `.env`.
**Implementado em:** `Modules/Superadmin/Http/Controllers/SuperadminSettingsController.php` · `Modules/Superadmin/Resources/js/Pages/superadmin/Configuracoes/Index.tsx` · verificado@580be57 (2026-11-16)
**Testado em:** `Modules/Superadmin/Tests/Feature/SuperadminConfiguracoesContratoTest.php`
- Tela: `superadmin/Configuracoes/Index` (Inertia, #8361). `superadmin_settings/edit.blade.php` foi apagada no L5 (#10967)
- Controller: `SuperadminSettingsController`
- Tabela: `system` (key/value)
**Aceite:** a tela responde Inertia (UC-SACFG-01) e barra admin de negócio (UC-SACFG-02); segredo nunca sai nas props, só "definido" (UC-SACFG-03); segredo em branco mantém o gravado e preenchido é regravado (UC-SACFG-04, UC-SACFG-05); quebra de linha num valor não abre linha nova no `.env` (UC-SACFG-06).

### US-SUPER-009 — Pricing público (`/pricing`)
**Como** visitante anônimo, **quero** ver planos disponíveis com preço/limite/feature comparison, **pra** decidir contratar.
**Implementado em:** `Modules/Superadmin/Http/Controllers/PricingController.php` · `Modules/Superadmin/Resources/js/Pages/Site/Pricing.tsx` · `Modules/Superadmin/Resources/views/pricing/index.blade.php` · verificado@580be57 (2026-11-16)
**Testado em:** `Modules/Superadmin/Tests/Feature/SitePricingContratoTest.php`
- Tela: `Site/Pricing` (Inertia) em `/pricing`; a Blade `pricing/index.blade.php` ficou só em `/pricing/old` (`Modules/Superadmin/Routes/web.php:9`)
- Controller: `PricingController`
**Aceite:** `/pricing` abre sem login com planos e permissões (UC-SPRIC-01); pacote privado ou desativado não aparece na vitrine (UC-SPRIC-02); ordena por `sort_order`.

### US-SUPER-010 — Usuario 360 (visão consolidada do cliente)
**Como** superadmin, **quero** ver consolidado de um business (subscription ativa + uso + tickets + última atividade), **pra** preparar call de suporte/upsell.
**Implementado em:** `Modules/Superadmin/Http/Controllers/Usuario360Controller.php` · `Modules/Superadmin/Resources/js/Pages/superadmin/Usuario360/Index.tsx` · `Modules/Superadmin/Resources/js/Pages/superadmin/Usuario360/Show.tsx` · verificado@580be57 (2026-11-16)
**Testado em:** `Modules/Superadmin/Tests/Feature/Usuario360ContratoTest.php`
- Controller: `Usuario360Controller`
**Aceite:** cross-tenant explícito (UC-SAUL-04, UC-SAUX-03); abrir o raio-X grava 1 acesso com quem viu, quem foi visto e o business do visto (UC-SAUX-06); listar não grava acesso. O log de acesso entrou em 2026-10-01 (#8364): `activity('superadmin_acesso')…->log('usuario360_visualizado')` em `Usuario360Controller.php:179-192` (LGPD Art. 7º). Até essa data esta US estava `_parcial_` por falta dele
- 2026-08-19: a Page saiu de `resources/js/Pages/` e foi pra dentro do módulo ([W]: "vai para dentro do módulo"). O namespace Inertia **não** mudou — o path acima é o novo endereço do mesmo arquivo, e nenhum call-site do controller mudou.

### US-SUPER-011 — Visão geral da plataforma (`/superadmin`)
**Como** superadmin, **quero** ver o pulso do negócio-SaaS num período (entradas, cadastros sem assinatura e tendência), **pra** saber se a plataforma está crescendo ou vazando antes de abrir tela nenhuma.
**Implementado em:** _parcial_ · `Modules/Superadmin/Http/Controllers/SuperadminController.php` · `Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.tsx` · `Modules/Superadmin/Services/SuperadminDashboardService.php` · verificado@580be57 (2026-11-16) — falta funil trial→pago e receita por pacote
**Testado em:** `Modules/Superadmin/Tests/Feature/SuperadminDashboardContratoTest.php`
- Tela: `superadmin/Dashboard/Index` (Inertia desde a SA-O1; era `superadmin::superadmin.index` em Blade/AdminLTE)
- Charter + casos: ao lado do `.tsx` · RUNBOOK: `RUNBOOK-dashboard.md`
**Aceite:** KPIs saem do `SuperadminDashboardService`, não de query inline no controller (UC-SADASH-02) · a rota responde Inertia com o componente `superadmin/Dashboard/Index`, não Blade (UC-SADASH-01) · admin de negócio é barrado enquanto o superadmin passa (UC-SADASH-03) · as contagens enxergam TODOS os negócios, cross-tenant intencional por ADR 0093 §exceções (UC-SADASH-04) · nenhum bloco sem query no backend chega às props (UC-SADASH-05). Provado por `Modules/Superadmin/Tests/Feature/SuperadminDashboardContratoTest.php`.
- **Parcial por escolha, não por esquecimento:** o MRR entrou na SA-O1b (#5955, 2026-08-19) e passou a ser calculado pelo RecurringBilling (#5981): prop deferida `mrr` ← `SuperadminDashboardService::calcularMrr`, que também devolve as canceladas dos últimos 30 dias. Funil trial→pago, receita por pacote e a fila "Vencendo ou vencido" ainda **não têm query** e não são renderizados — o protótipo os desenha com mock, e mock em produção é número fabricado. O comentário em `SuperadminController.php:41-43` é anterior ao #5955 e ainda diz que o MRR não tem query

### US-SUPER-012 · Onboarding guiado da empresa nova e ajuda dentro do sistema

> owner: — · priority: p2 · estimate: 10h · type: story
> blocked_by: —

**Implementado em:** _parcial_ · `app/Services/Dashboard/PrimeirosPassosService.php` · `app/Http/Controllers/HomeController.php` · `routes/web.php` — card "Primeiros passos" da Visão geral atrás da flag `onboardingPrimeirosPassos` (desligada); falta a fatia de tela e o link de ajuda contextual
**Testado em:** `tests/Feature/Home/PrimeirosPassosContratoTest.php`
**Aceite:** fatia backend, atrás da flag — com a flag desligada a Visão geral não muda (UC-PPAS-01) · quem configura a empresa recebe os 6 passos na ordem, como prop deferida (UC-PPAS-02) · sem `business_settings.access` o card não existe (UC-PPAS-03) · o passo é feito pelo dado real da própria empresa, e o de outra empresa não conta (UC-PPAS-04) · pular e dispensar ficam gravados na empresa sem marcar passo como feito (UC-PPAS-05, UC-PPAS-06) · ação ou passo desconhecido é recusado sem gravar nada (UC-PPAS-07).
- Casos UC-PPAS-01..07 em `resources/js/Pages/Home/Index.casos.md` · protótipo `prototipo-ui/cowork/Wagner/primeiros-passos-page.jsx` (shell integrado, ADR 0438).

Origem: levantamento de lacunas 2026-10-25 (P2 Plataforma — onboarding guiado e ajuda).
- Lista de primeiros passos no início para a empresa nova (dados da empresa, primeiro produto, primeiro cliente, primeira venda, certificado fiscal), cada passo marcado como feito a partir do dado real, não de clique.
- Some quando todos os passos estão feitos ou o usuário dispensa (por empresa).
- Link de ajuda contextual por tela apontando para a documentação existente.

## Anti-padrões (NÃO fazer)

- ❌ Expor rota do Superadmin sem middleware `is_superadmin`
- ❌ `withoutGlobalScopes()` sem comentário `// SUPERADMIN: <razão>`
- ❌ Cobrar gateway novo sem ADR de arquitetura financeira
- ❌ Apagar `superadmin_communicator_logs` (audit append-only)

## Testes

Medido em 2026-11-16 (`git ls-files Modules/Superadmin/Tests`): 28 arquivos em `Modules/Superadmin/Tests/Feature/`. Os que declaram `@covers-us` estão nas linhas `**Testado em:**` de cada US. A lista "Wave B" que ficava aqui (`MultiTenantIsolationTest`, `ScaffoldTest`) citava arquivos que não existem mais.

Quais desses arquivos rodam numa lane de PR é pergunta para `node scripts/governance/test-lane-coverage.mjs --json`, não para este SPEC (a auditoria de 2026-11-05 contou 15 de 27 fora de lane; o lote L1 trata disso).
