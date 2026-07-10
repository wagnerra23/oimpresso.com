---
module: RecurringBilling
slug: recurring-billing-spec
title: Especificação funcional — RecurringBilling
type: spec
version: "1.0"
last_updated: "2026-07-03"
owner: wagner
status: ativo
---

<!-- schema-allowlist: US ativas sob "## 2. User stories" (US-RB-NNN canônicas, casa o gate); blocos stub legados §7-bis/§8 carregam ID auto-gerado malformado US-RECURRINGBILLING-001 (duplicatas das US-RB-040..044) — manter intacto evita restruturar corpo; IDs canônicos são os US-RB-NNN -->

# Especificação funcional — RecurringBilling

> Convenção do ID: `US-RB-NNN` para user stories, `R-RB-NNN` para regras Gherkin.

## 1. Glossário rápido

- **Plano** — template de cobrança (`rb_plans`): ciclo, valor, trial, setup_fee
- **Contrato** (subscription) — instância de plano contratada por cliente (`rb_contracts`)
- **Fatura** — cobrança gerada em cada ciclo (`rb_invoices`)
- **MRR** — Monthly Recurring Revenue
- **Churn** — % de contratos cancelados / total
- **Dunning** — fluxo de recuperação de pagamento falho
- **MoR** — Merchant of Record (quem aparece pro cliente final)
- **JRC** — Jornada de Recorrência de Consentimento (Pix Automático)
- **Soft decline** — falha temporária (saldo insuficiente, limite); retentável
- **Hard decline** — falha permanente (cartão cancelado); não retentar

(Vocabulário completo: [GLOSSARY.md](GLOSSARY.md))

## 2. User stories — sub-módulo RecurringBilling (núcleo)

### US-RB-001 · Cadastrar plano de assinatura

> **Área:** Plans
> **Rota:** `POST /recurring-billing/plans`
> **Controller/ação:** `PlanController@store`
> **Permissão Spatie:** `recurring-billing.plans.manage`

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Http/Controllers/PlanController.php` · `Modules/RecurringBilling/Http/Requests/StorePlanRequest.php` · `Modules/RecurringBilling/Models/Plan.php` · `resources/js/Pages/RecurringBilling/Planos/Index.tsx` · `Modules/RecurringBilling/Tests/Feature/Wave6PlanCrudTest.php` · verificado@176f9bc (2026-07-01) — falta: colunas `setup_fee`/`indice_reajuste` em `rb_plans` + validação no StorePlanRequest (só valor/ciclo/trial entregues); DoD exige FormRequest validar `setup_fee>=0` + `indice_reajuste` enum (IPCA/IGP-M/none), mas as colunas não existem (migration 2026_05_06_001000:16-31) e o FormRequest não valida (StorePlanRequest:46-49)

**Como** Gestor (tenant)
**Quero** definir plano com nome, valor, ciclo (mensal/anual/customizado), dias de trial, setup_fee, índice de reajuste
**Para** padronizar cobrança recorrente sem digitar tudo a cada contrato

**DoD:**
- [ ] FormRequest valida `valor>0`, `ciclo` enum, `trial_days>=0`, `setup_fee>=0`, `indice_reajuste` enum (IPCA/IGP-M/none)
- [ ] Soft delete (plano com contrato ativo não pode ser hard-deleted)
- [ ] Test Feature + isolamento

### US-RB-002 · Criar contrato (subscription)

> **Área:** Contracts
> **Rota:** `POST /recurring-billing/contracts`
> **Controller/ação:** `ContractController@store`
> **Permissão Spatie:** `recurring-billing.contracts.manage`

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Http/Controllers/RecurringBillingController.php` · `Modules/RecurringBilling/Http/Requests/StoreAssinaturaRequest.php` · `Modules/RecurringBilling/Models/Subscription.php` · `resources/js/Pages/RecurringBilling/Index.tsx` · `Modules/RecurringBilling/Tests/Feature/Wave21NewSubscriptionTest.php` · verificado@176f9bc (2026-07-01) — falta: `status=trialing` quando o plano tem trial (DoD) + caso de teste do trial; `store()` hardcoda `status:'active'` (RecurringBillingController:135) e não deriva trialing do plano

**Como** Larissa-financeiro
**Quero** criar contrato vinculando cliente + plano + dia de cobrança + meio de pagamento padrão
**Para** automatizar cobrança a partir do primeiro ciclo

**DoD:**
- [ ] Valida cliente pertence ao business (não cross-tenant)
- [ ] Calcula `next_billing_date` baseado em `anchor_date` + ciclo do plano
- [ ] Cria `rb_contracts` com `status=trialing` se plano tem trial, senão `status=active`
- [ ] Test Feature: criar com trial, sem trial, com customizações de valor (override do plano), isolamento

### US-RB-003 · Gerar faturas em ciclo (job)

> **Área:** Invoices
> **Rota:** scheduler `php artisan recurring-billing:generate-invoices`
> **Service:** `InvoiceGeneratorService::run()`

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Services/InvoiceGeneratorService.php` · `Modules/RecurringBilling/Console/Commands/GenerateInvoicesCommand.php` · `Modules/RecurringBilling/Tests/Feature/InvoiceGeneratorServiceTest.php` · verificado@176f9bc (2026-07-01) — falta: proração em upgrade/downgrade + reajuste no aniversário (R-RB-006) + disparo do evento `InvoiceGenerated` (grep 0 em Events/Listeners); entregue idempotência por competência (YYYY-MM) + lead-time configurável + avanço de next_due_date + evento na timeline

**Como** Sistema (job diário 03:00)
**Quero** gerar faturas para contratos com `next_billing_date <= hoje + 3 dias` (lead time 3d)
**Para** cliente receber fatura antes do vencimento

**DoD:**
- [ ] Idempotência: `(contract_id, ciclo_competencia)` UNIQUE em `rb_invoices`
- [ ] Calcula valor com proração se há upgrade/downgrade desde último ciclo
- [ ] Aplica reajuste se passou aniversário (R-RB-006)
- [ ] Dispara evento `InvoiceGenerated` (Financeiro escuta pra criar título)
- [ ] Test: 100 contratos × 3 ciclos = 300 invoices distintas, sem dupla

### US-RB-004 · Cobrar fatura (charge attempt)

> **Área:** Invoices → PaymentGateway
> **Rota:** `POST /recurring-billing/invoices/{id}/charge`
> **Controller/ação:** `InvoiceController@charge`
> **Permissão Spatie:** `recurring-billing.invoices.charge`

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Database/Migrations/2026_05_06_001003_create_rb_charge_attempts_table.php` · `Modules/RecurringBilling/Models/ChargeAttempt.php` · `Modules/RecurringBilling/Events/InvoicePaid.php` · `Modules/RecurringBilling/Services/Boleto/BoletoService.php` · verificado@176f9bc (2026-07-01) — falta: endpoint POST /invoices/{id}/charge, adapter ChargeResult com decline_type/retry_at e agendamento de retry por soft/hard decline (cobrança hoje via geração de boleto/Pix + baixa por webhook, não por endpoint de charge dedicado)

**Como** Sistema (auto via job 1h após gerar) ou Larissa (manual)
**Quero** disparar cobrança via PaymentGateway adapter configurado
**Para** debitar cliente automaticamente no meio escolhido

**DoD:**
- [ ] Idempotência por `(invoice_id, attempt_number)` em `pg_charge_attempts`
- [ ] Adapter retorna `ChargeResult { success, transaction_id, decline_type, retry_at }`
- [ ] Em sucesso: dispara `InvoicePaid`, atualiza `rb_invoices.status=paid`
- [ ] Em soft decline: agenda retry (smart retry — ML futuro, regra simples MVP)
- [ ] Em hard decline: dispara `InvoiceFailed` (Dunning escuta)
- [ ] Test Feature: success + soft + hard + idempotência + isolamento

### US-RB-005 · Cancelar contrato

> **Área:** Contracts
> **Rota:** `POST /recurring-billing/contracts/{id}/cancel`
> **Controller/ação:** `ContractController@cancel`
> **Permissão Spatie:** `recurring-billing.contracts.cancel`

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Http/Controllers/RecurringBillingController.php` · `Modules/RecurringBilling/Http/Requests/CancelSubscriptionRequest.php` · `Modules/RecurringBilling/Policies/SubscriptionPolicy.php` · `Modules/RecurringBilling/Tests/Feature/CustomerJourneyTest.php` · verificado@176f9bc (2026-07-01) — falta: enum `tipo_cancelamento` (`fim_ciclo`/`imediato`) + status `canceled_at_period_end` + `credit_note` do saldo restante + disparo do evento `ContractCanceled` (Events/ só tem AssinaturaAtualizada + InvoicePaid); entregue `cancelar()` com churn_reason + Gate + AuditLog via LogsActivity + idempotente (já cancelada retorna 422)

**Como** Larissa ou cliente final (via portal)
**Quero** cancelar contrato com motivo + escolha "fim do ciclo" ou "imediato"
**Para** parar cobranças sem cobrança extra

**DoD:**
- [ ] FormRequest: `motivo` obrigatório, `tipo_cancelamento` enum (`fim_ciclo`/`imediato`)
- [ ] `fim_ciclo`: status=`canceled_at_period_end`; cobrança continua até fim, depois `canceled`
- [ ] `imediato`: status=`canceled` agora; gera credit_note pro saldo restante (se aplicável)
- [ ] Dispara `ContractCanceled` (NFSe, Dunning, Pix Automático escutam)
- [ ] Audit log com motivo
- [ ] Test Feature: ambos tipos + clientes vs tenant + isolamento

### US-RB-006 · Proração em upgrade/downgrade mid-cycle

> **Área:** Proration
> **Service:** `ProrationService::calculate()`

**Implementado em:** _pendente_ — só existe o desenho em `memory/requisitos/RecurringBilling/adr/tech/0003-proration-mid-cycle.md`; `ProrationService` e a tabela `rb_proration_events` ainda não foram construídos

**Como** Sistema (chamado no upgrade/downgrade)
**Quero** calcular crédito ou débito proporcional ao tempo restante do ciclo
**Para** cobrar justo (cliente upgrades dia 15 não paga 2 ciclos cheios)

**DoD:**
- [ ] Service puro (sem side effects) com 6 cenários cobertos: upgrade-meio-mês, downgrade-meio-mês, mudança em D-1 do vencimento, com/sem trial restante, etc.
- [ ] Cria `rb_proration_events` (audit) + ajusta próximo `rb_invoices`
- [ ] Test unit ProrationServiceTest cobrindo 6 cenários

## 3. User stories — sub-módulo PaymentGateway

### US-RB-010 · Cadastrar credencial de gateway

> **Área:** Adapters → Credentials
> **Rota:** `POST /payment-gateway/credentials`
> **Permissão Spatie:** `payment-gateway.credentials.manage`

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Models/BoletoCredential.php` · `Modules/RecurringBilling/Database/Migrations/2026_05_06_000001_create_rb_boleto_credentials_table.php` · `Modules/RecurringBilling/Http/Controllers/ConfiguracoesController.php` · `Modules/RecurringBilling/Tests/Feature/BoletoCredentialResolverTest.php` · verificado@176f9bc (2026-07-01) — credencial por tenant (banco inter/c6/asaas) com `config_json` cifrado (token + webhook_secret) e UI de configuração; falta: nomenclatura `pg_credentials` genérica e N credenciais por provider (UNIQUE atual é 1 por banco/business)

**Como** Gestor (tenant)
**Quero** colocar API key + webhook secret de gateway escolhido (Asaas/Iugu/etc.)
**Para** integrar oimpresso ↔ gateway sem expor credencial

**DoD:**
- [ ] FormRequest valida shape do provider
- [ ] Storage encrypted via Laravel `encrypt()` em `pg_credentials.api_key_encrypted`
- [ ] Webhook secret separada
- [ ] Tenant pode ter N credentials por provider (multi-conta — útil pra subsidiárias)
- [ ] Test Feature: criar + invalid provider + isolamento

### US-RB-011 · Salvar cartão tokenizado de cliente

> **Área:** Cards
> **Rota:** `POST /payment-gateway/cards`
> **Permissão:** cliente final (portal) ou Larissa (admin)

**Implementado em:** _pendente_ — não há modelo/rota/tabela de cartão tokenizado (`gateway_card_token`, `last_4`, `brand`) no módulo; cobrança recorrente hoje é via boleto/Pix, sem via cartão

**Como** Cliente final via portal
**Quero** salvar cartão pra cobrança recorrente (token, não número cru)
**Para** próximas cobranças usarem sem digitar tudo de novo

**DoD:**
- [ ] **PROIBIDO armazenar PAN/CVV** — provider tokeniza → oimpresso recebe `gateway_card_token`
- [ ] Persiste apenas: `last_4`, `brand`, `expires_at`, `gateway_card_token`, `provider`
- [ ] Test Feature: criar (mock provider) + listar + isolamento
- [ ] PCI compliance: tokenização nunca chega no servidor oimpresso (provider hosted iframe ou checkout redirecionamento)

### US-RB-012 · Receber webhook de gateway

> **Área:** Webhooks
> **Rota:** `POST /payment-gateway/webhooks/{provider}`
> **Controller/ação:** `WebhookController@handle{Provider}`

**Implementado em:** `Modules/RecurringBilling/Http/Controllers/AsaasWebhookController.php` · `Modules/RecurringBilling/Http/Controllers/InterWebhookController.php` · `Modules/RecurringBilling/Jobs/ProcessAsaasWebhookJob.php` · `Modules/RecurringBilling/Tests/Feature/AsaasWebhookAuthTest.php` · verificado@176f9bc (2026-07-01) — validação de secret via `hash_equals` (rejeita 401), idempotência por `(provider, event_id)` em `pg_webhook_events`, resposta 200 imediata + dispatch async

**Como** Gateway (Asaas/Iugu) chamando back
**Quero** entregar evento (`payment.confirmed`, `payment.refunded`, `subscription.canceled`)
**Para** oimpresso atualizar estado interno

**DoD:**
- [ ] Validação assinatura HMAC do payload (rejeita 401 se falha)
- [ ] Idempotência por `(provider, event_id)` UNIQUE em `pg_webhook_events`
- [ ] Resposta 200 imediata + processamento async via job (gateway tem timeout curto)
- [ ] Job mapeia evento provider → enum interno → dispara evento Laravel correspondente
- [ ] Test Feature: assinatura inválida 401 + idempotência + dispatch correto

### US-RB-013 · Smart retry em soft decline

> **Área:** SmartRetries
> **Service:** `SmartRetryScheduler::schedule()`

**Implementado em:** _pendente_ — `SmartRetryScheduler` não existe; a tabela `rb_charge_attempts` já tem enum soft_decline/hard_decline, mas não há agendador de retry `[1d,3d,7d]` nem limite de 3 tentativas

**Como** Sistema (após charge attempt com soft decline)
**Quero** agendar retentativa em horário ótimo (ML futuro; regra fixa MVP)
**Para** maximizar taxa de aprovação

**DoD:**
- [ ] MVP: retry sequence `[1d, 3d, 7d]` em horário 10:00 fuso do business
- [ ] Limite: 3 tentativas; 4ª = hard decline → dispara `InvoiceFailed` pro Dunning
- [ ] Cancelar retry manualmente (Larissa) é possível
- [ ] Test Feature: soft → 3 tentativas → hard / cancelar manual / isolamento

## 4. User stories — sub-módulo PixAutomatico

### US-RB-020 · Solicitar autorização Pix Automático

> **Área:** Authorizations
> **Rota:** `POST /pix-automatico/authorizations`
> **Permissão:** `pix-automatico.authorizations.manage`

**Implementado em:** _pendente_ — o sub-módulo PixAutomatico (autorização JRC/BCB: `pa_authorizations`, QR de autorização, status created→activated via webhook) não foi construído no RecurringBilling; o Pix Automático Asaas em `Modules/Financeiro` é capacidade distinta (não cobre esta US)

**Como** Larissa-financeiro
**Quero** gerar QR code de autorização pix automático pro cliente apontar app
**Para** cliente autorizar débito recorrente sem cartão

**DoD:**
- [ ] PSP emite `txid` + QR code
- [ ] `pa_authorizations.status=created` → cliente lê QR → autoriza no app banco → status=`activated` (via webhook)
- [ ] Limite valor máximo (`limite_max`) configurável por autorização
- [ ] Test Feature: criar + receber webhook ativação + isolamento

### US-RB-021 · Cobrar via Pix Automático autorizado

> **Área:** PaymentInstructions
> **Service:** `PixAutomaticoCharger::charge()`

**Implementado em:** _pendente_ — `PixAutomaticoCharger` e `pa_payment_instructions` não existem; depende do sub-módulo PixAutomatico (US-RB-020), ainda não construído no RecurringBilling

**Como** Sistema (em `InvoiceController@charge` quando contract.payment_method = 'pix_automatico')
**Quero** debitar via PSP usando autorização ativa
**Para** sucesso ~99% (autorização válida = débito automático)

**DoD:**
- [ ] Cria `pa_payment_instructions` com `e2e_id` único + `scheduled_date`
- [ ] PSP debita conta autorizada
- [ ] Webhook traz status final → atualiza `pg_charge_attempts` e dispara `InvoicePaid`
- [ ] Test Feature: cobrança com autorização ativa + sem autorização (rejeita) + autorização expirada

## 5. User stories — sub-módulo Dunning

### US-RB-030 · Configurar régua de inadimplência

> **Área:** Rules
> **Rota:** `POST /dunning/rules`
> **Permissão:** `dunning.rules.manage`

**Implementado em:** _pendente_ — a régua de dunning existe só como config hardcoded read-only em `ConfiguracoesController` (1ª +3d / 2ª +7d / 3ª +15d); não há sub-módulo Dunning, tabela `dun_rules` persistida por business, nem FormRequest de edição de steps

**Como** Gestor
**Quero** criar régua com passos (`step_1: D+1 email`, `step_2: D+3 SMS`, `step_3: D+7 WhatsApp + bloqueio`)
**Para** automatizar recuperação sem ligar 1-a-1

**DoD:**
- [ ] FormRequest valida steps com `delay_days`, `action` enum, `template`
- [ ] Test Feature: criar régua + ativar + isolamento

### US-RB-031 · Disparar régua quando cobrança falha

> **Área:** Campaigns
> **Listener:** `Modules\Dunning\Listeners\StartCampaignOnInvoiceFailed`

**Implementado em:** _pendente_ — não há modulo de dunning (planejado — não existe), tabelas `dun_campaigns`/`dun_campaign_steps`/`dun_step_executions`, nem listener de `InvoiceFailed`; motor de campanha de recuperação não construído

**Como** Sistema (escutando `InvoiceFailed`)
**Quero** iniciar campanha automática seguindo régua configurada
**Para** recuperar 30%+ de inadimplência sem trabalho manual

**DoD:**
- [ ] Cria `dun_campaigns` + N `dun_campaign_steps` agendados
- [ ] Cada step roda em queue `dunning` na data calculada
- [ ] Cada execução loga em `dun_step_executions` (tentativas + status)
- [ ] Pagamento durante campanha encerra automaticamente (escuta `InvoicePaid`)
- [ ] Test Feature: ciclo completo + cancelar manual + isolamento

## 6. Regras de negócio (Gherkin)

### R-RB-001 · Isolamento multi-tenant
```gherkin
Dado um usuário do business A
Quando ele acessa qualquer recurso de RecurringBilling/PaymentGateway/PixAutomatico/Dunning/NFSe
Então só vê dados com `business_id = A`
```
**Implementação:** Trait `BusinessScope` em todos os Models.
**Testado em:** `MultiTenantIsolationTest` em cada sub-módulo.

### R-RB-002 · Permissões Spatie por sub-módulo
**Implementação:** ~25 permissões (`recurring-billing.*`, `payment-gateway.*`, `pix-automatico.*`, `dunning.*`).
**Testado em:** `SpatiePermissionsTest`.

### R-RB-003 · Idempotência geração fatura
```gherkin
Dado um contrato com next_billing_date = 2026-05-01
Quando o job InvoiceGenerator roda 2x no mesmo dia
Então apenas 1 fatura é criada para a competência 2026-05
```
**Implementação:** UNIQUE `(contract_id, competencia_yyyy_mm)` em `rb_invoices`.
**Testado em:** _lacuna — InvoiceGeneratorIdempotenciaTest não existe (reconciliação 2026-07-01; cobertura a criar)_.

### R-RB-004 · Webhook idempotente por event_id
```gherkin
Dado um webhook Asaas chega com event_id = X
Quando chega de novo (at-least-once)
Então o segundo é descartado (200 OK sem efeito)
```
**Implementação:** UNIQUE `(provider, event_id)` em `pg_webhook_events`.
**Testado em:** _lacuna — WebhookIdempotenciaTest não existe (reconciliação 2026-07-01; cobertura a criar)_.

### R-RB-005 · Charge attempt idempotente
```gherkin
Dado uma fatura com tentativa em curso
Quando outro charge attempt chega com mesmo (invoice_id, attempt_number)
Então é descartado
```
**Implementação:** UNIQUE em `pg_charge_attempts`.
**Testado em:** _lacuna — ChargeIdempotenciaTest não existe (reconciliação 2026-07-01; cobertura a criar)_.

### R-RB-006 · Reajuste no aniversário
```gherkin
Dado um contrato criado em 2025-04 com plano `indice_reajuste = IPCA`
Quando gera-se a fatura de 2026-04 (12º ciclo)
Então o valor é reajustado conforme IPCA acumulado
E aparece em `rb_proration_events` como tipo `reajuste_aniversario`
```
**Implementação:** `ReajusteService` consulta IPCA via API BCB; cache 24h.
**Testado em:** _lacuna — ReajusteAniversarioTest não existe (reconciliação 2026-07-01; cobertura a criar)_.

### R-RB-007 · NFSe assíncrona não trava billing
```gherkin
Dado uma fatura paga
Quando o NFSe provider está fora
Então a fatura permanece como paid
E NFSe entra em fila de retentativa
E nenhuma cobrança seguinte é bloqueada
```
**Implementação:** Sub-módulo NFSe em queue separada; falhas viram `nfse_emissoes.status=failed` com retentativa 5x.
**Testado em:** _lacuna — NfseAssincronaTest não existe (reconciliação 2026-07-01; cobertura a criar)_.

### R-RB-008 · Cancelamento `fim_ciclo` cobra ciclo restante
```gherkin
Dado um contrato cancelado tipo fim_ciclo em 2026-04-15
E ciclo atual termina em 2026-04-30
Quando geramos fatura
Então a fatura de 2026-04 é gerada normalmente (cliente paga até 30/04)
E em 01/05, status muda pra canceled (sem nova fatura)
```
**Testado em:** _lacuna — CancelamentoFimCicloTest não existe (reconciliação 2026-07-01; cobertura a criar)_.

### R-RB-009 · Hard decline = não retentar
```gherkin
Dado um charge attempt retornou hard decline (cartão cancelado, ex: cStat 'card_canceled')
Quando SmartRetryScheduler é chamado
Então não agenda retry
E dispara InvoiceFailed para Dunning
```
**Implementação:** Switch em `decline_type` retornado pelo adapter; mapping per-provider.
**Testado em:** _lacuna — HardDeclineNoRetryTest não existe (reconciliação 2026-07-01; cobertura a criar)_.

### R-RB-010 · Pix Automático autorização expirada
```gherkin
Dado uma autorização Pix Automático com status `expired`
Quando tentamos cobrar
Então charge attempt falha imediatamente (sem chamar PSP)
E dispara InvoiceFailed
```
**Testado em:** _lacuna — PixAutorizacaoExpiradaTest não existe (reconciliação 2026-07-01; cobertura a criar)_.

### R-RB-011 · Pagamento durante campanha encerra dunning
```gherkin
Dado uma campanha de dunning ativa
Quando InvoicePaid é disparado
Então campanha vira status=resolved
E steps futuros são cancelados
```
**Testado em:** _lacuna — DunningResolvidaPorPagamentoTest não existe (reconciliação 2026-07-01; cobertura a criar)_.

### R-RB-012 · PCI compliance — não armazenar PAN/CVV
```gherkin
Dado o frontend captura cartão
Quando o servidor recebe request
Então NÃO armazena `pan` ou `cvv`
E só guarda `gateway_card_token`, `last_4`, `brand`
```
**Implementação:** FormRequest rejeita `pan`/`cvv` se chegarem (defesa em profundidade); checkout via iframe hosted ou redirecionamento.
**Testado em:** _lacuna — PciNaoArmazenaPanTest não existe (reconciliação 2026-07-01; cobertura a criar)_.

### R-RB-013 · Audit log Spatie em mutações críticas

```gherkin
Dado qualquer mutação em Plan, Contract, Invoice, ChargeAttempt, Authorization ou Campaign
Quando a mutação completa
Então existe row em activity_log com causer + subject + properties (valor, status, etc.)
```

**Implementação:** Trait `LogsActivity` em `Plan`, `Contract`, `Invoice`, `ChargeAttempt`, `Authorization`, `Campaign`.
**Testado em:** _lacuna — Modules/RecurringBilling/Tests/Feature/AuditLogMutacoesTest não existe (reconciliação 2026-07-01; cobertura a criar) — 6 modelos × create/update/delete = 18 asserts_.

### R-RB-014 · Take rate calculado só se gateway próprio
```gherkin
Dado uma cobrança via gateway próprio (oimpresso intermedia)
Quando InvoicePaid dispara
Então cria revenue_event com fee_calculado = min(valor * 0.008, 19.90)

Dado uma cobrança via merchant-of-record do cliente (gateway com credencial do cliente)
Então NÃO cria revenue_event (sem take rate)
```
**Implementação:** Verifica `pg_credentials.owner` em listener do `InvoicePaid`.
**Testado em:** _lacuna — TakeRateMorVsGatewayTest não existe (reconciliação 2026-07-01; cobertura a criar)_.

## 7. Decisões pendentes

- [ ] Merchant-of-record vs gateway direto — afeta NFSe e take rate
- [ ] Stack open source de referência: Lago (event-driven) ou Kill Bill (maduro)?
- [ ] Portal B2C self-service pra Onda 2 ou Onda 5?
- [ ] Smart retry ML: vale construir com 100 contratos ou esperar 1k?
- [ ] Boleto direto CNAB: vale o esforço ou só via gateway?
- [ ] Reajuste IPCA via API BCB ou cache local atualizado mensal?

## 9. Escopos de implementação — 2026-05-06

### US-RB-ESC0 · Escopo 0 — PaymentGateway + adapter Asaas (pré-requisito cobrança)

> owner: wagner · priority: p0 · estimate: 16h · status: todo · type: story

- Módulo PaymentGateway scaffold (tabelas `pg_credentials`, `pg_charge_attempts`, `pg_webhook_events`)
- Adapter Asaas: credencial por tenant (api_key encrypted), cobrança avulsa, webhook `payment.confirmed` / `payment.overdue`
- Idempotência por `(provider, event_id)` em `pg_webhook_events`
- Resposta 200 imediata + processamento async via job (fila `rb_webhooks`)
- Tela admin: cadastrar credencial Asaas por tenant
- Test Feature: criar credencial + cobrança avulsa mock + webhook idempotência + isolamento multi-tenant
- **Pré-requisito de todos os outros escopos**

### US-RB-ESC1 · Escopo 1 — Motor de cobrança recorrente (plans + contracts + invoices + job)

> owner: wagner · priority: p0 · estimate: 32h · status: todo · type: story
> blocked_by: US-RB-ESC0

- Migrations: `rb_plans`, `rb_contracts`, `rb_invoices`
- PlanController CRUD + ContractController (criar/cancelar) + InvoiceController (listar/charge manual)
- `GenerateInvoicesJob` — diário 03:00, gera fatura pra contratos com `next_billing_date <= hoje+3d`, idempotência por `(contract_id, ciclo_competencia)`
- `ChargeInvoicesJob` — 1h após geração, dispara evento `ChargeRequested` pro PaymentGateway
- Listener `InvoiceGenerated` → cria título no Financeiro (`rb_invoices` linked)
- Layout React: lista contratos (status badge), detalhe com timeline de ciclos (Recharts), ação manual "cobrar agora"
- Test Feature: 100 contratos × 3 ciclos = 300 invoices sem dupla + isolamento

### US-RB-ESC2 · Escopo 2 — Boleto impresso via eduardokum/laravel-boleto

> owner: wagner · priority: p0 · estimate: 10h · status: todo · type: story
> blocked_by: US-RB-ESC1

- `composer require eduardokum/laravel-boleto` — geração direta (sem gateway externo)
- Suporta múltiplos bancos (Bradesco, Itaú, BB, Santander, Sicoob, etc.) — configurável por tenant
- No `ChargeRequested` com forma=boleto: `BoletoService::gerar()` retorna PDF + linha digitável + código de barras
- Persiste em `pg_charge_attempts.boleto_pdf_url` (storage local ou S3)
- Tela fatura (React): botão "Imprimir boleto" (abre PDF em nova aba) + linha digitável copiável + QR Code Pix (se banco suportar)
- Envio por email automático com PDF anexo ao gerar (job `SendBoletoEmailJob`)
- Registrar banco preferido por tenant em `pg_credentials` (tipo `boleto_banco`)
- Test Feature: gerar boleto real no banco configurado + verificar PDF salvo + email disparado + isolamento

### US-RB-ESC3 · Escopo 3 — NFe via módulo NfeBrasil (nfephp-org/sped-nfe)

> owner: wagner · priority: p1 · estimate: 24h · status: todo · type: story
> blocked_by: US-RB-ESC1

- Usa o módulo `Modules/NfeBrasil` já existente no projeto (não criar sub-módulo novo)
- Integração via evento: listener em `InvoicePaid` → dispara `NFeEmissionRequested` (fila `rb_nfe`, não trava billing)
- `NfeBrasilService::emitir()` gera XML NFe + assina com certificado A1 por tenant + transmite SEFAZ
- Retorno: chave de acesso + PDF DANFE + XML autorizado salvos em `nfse_documents` (reuso tabela)
- Tela fatura (React): status da NF (pendente/autorizada/rejeitada/cancelada), link DANFE PDF, botão reemitir
- Certificado A1 (.pfx) por tenant — armazenado encrypted, configurável na tela admin
- NFe pode ser rejeitada pela SEFAZ: UI mostra código de erro + descrição amigável + ação "corrigir e reemitir"
- Test Feature: mock SEFAZ + listener disparado ao pagar + chave retornada + isolamento multi-tenant

## 7-bis. Backlog vindo do Capterra-Inventário (range 040+)

> Tasks geradas pela skill `comparativo-do-modulo` em **2026-05-06**. Range 040-049 reservado pra essa origem.
> Detalhes em [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md). Doutrina: [ADR 0089](../../decisions/0089-capterra-driven-module-evolution.md).

### US-RB-040 · Cobertura Pest dos 3 drivers de boleto (Inter/C6/Asaas)

> owner: — · priority: p0 · estimate: 8h · type: story · origin: capterra-inventario-2026-05-06 · capacidade: #1
> blocked_by: —

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Tests/Feature/InterDriverTest.php` · `Modules/RecurringBilling/Tests/Feature/C6DriverTest.php` · `Modules/RecurringBilling/Tests/Feature/AsaasDriverTest.php` · `Modules/RecurringBilling/Tests/Feature/BoletoServiceTest.php` · verificado@176f9bc (2026-07-01) — falta: acceptance #1 exige `InterDriverTest` round-trip emitir + cancelar com sandbox mockada; os 5 its cobrem só construtor + writeTempCert e o próprio arquivo confessa o diferimento (InterDriverTest:19,43-113)

**Contexto.** CAPTERRA-INVENTARIO #1 classificou como 🟡 PARCIAL: drivers + UI existem, mas `Modules/RecurringBilling/Tests/` está vazia. Sem teste, qualquer mexida nova é bug em prod garantido.

**Acceptance criteria:**
- [ ] `Tests/Feature/InterDriverTest.php` — round-trip emitir + cancelar com sandbox response mockada (não chamar API real)
- [ ] `Tests/Feature/C6DriverTest.php` — geração local CNAB + nossoNumero + linha digitável válidos
- [ ] `Tests/Feature/AsaasDriverTest.php` — POST /payments mockado + parsing do response
- [ ] `Tests/Feature/BoletoServiceTest.php` — resolve driver correto por banco da credencial; `decryptConfig` roundtrip (ADR tech/0007)
- [ ] CI verde com os novos testes

### US-RB-041 · Test de retry idempotente do ProcessAsaasWebhookJob

> owner: — · priority: p0 · estimate: 3h · status: done · type: story · origin: capterra-inventario-2026-05-06 · capacidade: #2
> blocked_by: —

**Implementado em:** `Modules/RecurringBilling/Tests/Feature/AsaasWebhookIdempotencyTest.php` · `Modules/RecurringBilling/Jobs/ProcessAsaasWebhookJob.php` · verificado@176f9bc (2026-07-01) — teste de idempotência (event_id repetido → 200 sem reprocessar; `insertOrIgnore` em account_transactions)

**Contexto.** CAPTERRA-INVENTARIO #2 classificou 🟡 PARCIAL — tabela `pg_webhook_events` UNIQUE existe, mas sem teste cobrindo retry/replay. Webhook duplicado pelo Asaas (acontece em produção) sem cobertura de teste = cobrança duplicada esperando pra acontecer.

**Acceptance criteria:**
- [ ] `Tests/Feature/AsaasWebhookIdempotencyTest.php`
- [ ] 2 chamadas POST `/api/webhooks/asaas/{biz}` com mesmo `event_id` → segunda retorna 200 sem reprocessar
- [ ] `PAYMENT_RECEIVED` processado 2× não cria 2 `account_transactions` (insertOrIgnore funciona)
- [ ] `BALANCE_UPDATED` processado 2× não duplica `saldo_cached`
- [ ] Job falha no meio → retry roda completo sem dups (atomicidade)

### US-RB-042 · Completar cancelar() C6/Asaas + UI Cancelar título + audit log

> owner: — · priority: p0 · estimate: 6h · type: story · origin: capterra-inventario-2026-05-06 · capacidade: #4
> blocked_by: US-RB-040

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Services/Boleto/Drivers/C6Driver.php` · `Modules/RecurringBilling/Services/Boleto/Drivers/AsaasDriver.php` · `Modules/RecurringBilling/Http/Controllers/InvoiceController.php` · `Modules/RecurringBilling/Services/AssinaturaCobrancaService.php` · `resources/js/Pages/RecurringBilling/Faturas/Index.tsx` · verificado@176f9bc (2026-07-01) — falta: `C6Driver::cancelar()` é STUB que lança `BadMethodCallException` (C6Driver:80-91) — CNAB ocorrência 02 não implementado; UI real de cancelamento fica em Faturas/Index.tsx:423 (o botão Cancelar foi removido de propósito do drawer da Cobrança — "B6 botões honestos", DrawerCobranca.tsx:118-123); entregue `cancelar()` no Inter/Asaas + endpoint financeiro/rb-invoices/{invoice}/cancelar com permissão recurringbilling.invoice.cancel + AuditLog

**Contexto.** CAPTERRA-INVENTARIO #4 🟡 — `BoletoDriverContract::cancelar()` definido e implementado em InterDriver, mas C6/Asaas precisam ser auditados/completados. UI inexistente. Cancelamento é exigência de lei (Procon/LGPD) — não pode depender de SQL.

**Acceptance criteria:**
- [ ] Auditar `C6Driver::cancelar()` — implementar via CNAB remessa de cancelamento se ausente
- [ ] Auditar `AsaasDriver::cancelar()` — usar `DELETE /payments/{id}`
- [ ] Botão "Cancelar título" em `resources/js/Pages/Financeiro/Boletos/` (ou similar) com confirmação
- [ ] Endpoint `POST /financeiro/boletos/{id}/cancelar` chama `BoletoService` → driver
- [ ] Spatie Activity Log registrando cancelamento (quem/quando/motivo)
- [ ] Permissão `financeiro.boleto.cancelar` (default só admin do business)
- [ ] Teste Pest cobrindo os 3 drivers (depende de US-RB-040)

### US-RB-043 · [Epic] Models Subscription/Plan/Invoice/ChargeAttempt + migrations

> owner: — · priority: p1 · estimate: 16h · status: done · type: epic · origin: capterra-inventario-2026-05-06 · capacidade: #4-domínio
> blocked_by: —
> bloqueia: US-RB-001, US-RB-002, US-RB-003, US-RB-004, US-RB-005, US-RB-006, US-RB-011, US-RB-013

**Implementado em:** `Modules/RecurringBilling/Models/Plan.php` · `Modules/RecurringBilling/Models/Subscription.php` · `Modules/RecurringBilling/Models/Invoice.php` · `Modules/RecurringBilling/Models/ChargeAttempt.php` · `Modules/RecurringBilling/Database/Migrations/2026_05_06_001000_create_rb_plans_table.php` · `Modules/RecurringBilling/Tests/Feature/DomainModelsTest.php` · verificado@176f9bc (2026-07-01) — 4 models Eloquent com HasBusinessScope + migrations `rb_plans`/`rb_subscriptions`/`rb_invoices`/`rb_charge_attempts`

**Contexto.** CAPTERRA-INVENTARIO classificou ❌ AUSENTE — fundação do domínio recorrente. Epic bloqueador das US-RB-001..013 que dependem de modelos. Hoje o módulo tem boleto avulso funcionando, mas **não tem cobrança recorrente** porque não há modelo de Subscription/Plan/Invoice.

**Acceptance criteria:**
- [ ] Migration `rb_plans` — id, business_id, nome, valor, ciclo (monthly/yearly), trial_days, ativo
- [ ] Migration `rb_subscriptions` — id, business_id, plan_id, contact_id (UPos contacts), status (active/paused/canceled/trial), próximo_vencimento, billing_anchor_date
- [ ] Migration `rb_invoices` — id, subscription_id, valor, status (open/paid/overdue/canceled), vencimento, pago_em, gateway_ref, conta_bancaria_id
- [ ] Migration `rb_charge_attempts` — id, invoice_id, gateway, attempt_n, response_json, status, created_at (idempotent retry log)
- [ ] Models Eloquent com relacionamentos + `BusinessScope` global (multi-tenant)
- [ ] Tipos: `int unsigned` para FKs em tabelas legadas UltimatePOS (ADR tech/0008)
- [ ] Migrations idempotentes (`Schema::hasColumn` guard, ADR tech/0008)
- [ ] Seeder de exemplo com 2 planos para ROTA LIVRE (biz=4)
- [ ] Tests Pest: criar subscription, gerar próxima fatura, transição de status

### US-RB-044 · Listener InvoicePaid em NfeBrasil — emissão automática NFe55 + DANFE + e-mail

> owner: wagner · priority: p1 · estimate: 12h · status: done · type: story · origin: capterra-inventario-2026-05-06 · capacidade: #6 (diferencial vertical)

**Implementado em:** [`Modules/NfeBrasil/Listeners/EmitirNFeAoReceberPagamento.php`](../../../Modules/NfeBrasil/Listeners/EmitirNFeAoReceberPagamento.php) · [`Modules/NfeBrasil/Services/NfeService::emitirParaInvoice`](../../../Modules/NfeBrasil/Services/NfeService.php) · [`Modules/NfeBrasil/Events/NFeAutorizada`](../../../Modules/NfeBrasil/Events/NFeAutorizada.php)

**Contexto.** CAPTERRA-INVENTARIO #6 ❌ AUSENTE — **diferencial cross-vertical** (vai pro núcleo, não Modules/<Vertical>). Gateway de boleto é commodity (Iugu/Asaas/Vindi/Pagar.me têm). "Boleto pago → NFe modelo 55 emitida automaticamente sem clique humano" é diferencial do oimpresso. Larissa (ROTA LIVRE — Modules/Vestuario) pediu há tempos; também útil pra Modules/ComunicacaoVisual quando ativar. Event `InvoicePaid` JÁ existe em `Modules/RecurringBilling/Events/InvoicePaid.php`.

**Acceptance criteria:**
- [x] `Modules/NfeBrasil/Listeners/EmitirNFeAoReceberPagamento.php` registrado em `NfeBrasilServiceProvider` (consome `InvoicePaid`)
- [x] Listener resolve Invoice por (business_id + numero_documento) e chama `NfeService::emitirParaInvoice`
- [x] `NfeService::emitirParaInvoice` usa `MotorTributarioService` (US-NFE-043 cascade ARQ-0006) pra resolver CFOP/CSOSN/CST/alíquotas
- [x] Carrega certificado A1 do business via `CertificadoService::carregarParaSefaz` (fallback legado ADR 0090)
- [x] Chama `nfephp-org/sped-nfe` (Make + Tools) → autoriza SEFAZ via `NfeService::emitir`
- [x] Idempotência por `transaction_id = invoice.id` — segunda chamada retorna emissão existente
- [x] Flag `nfebrasil.auto_emission_on_invoice_paid` (default `false`) controla ativação por env
- [x] Log estruturado em cada passo (start, disabled, invoice ausente, falha, sucesso)
- [x] Falha SEFAZ não derruba pagamento — Throwable é re-throwado pra queue retry (3 tries, backoff 60s)
- [x] Status autorizada → dispara `Modules\NfeBrasil\Events\NFeAutorizada`
- [x] Tests Pest (10 cenários): listener registrado, flag off no-op, invoice ausente, autorizada→event, rejeitada→sem event, throwable→retry, queue config, failed log
- [x] DANFE PDF render (`Modules/NfeBrasil/Services/DanfeService` — gerado lazy via `NfeService::processarRetorno` autorizada)
- [x] Envia e-mail pro pagador com DANFE + XML anexados (`Modules/NfeBrasil/Listeners/EnviarDanfePorEmail` consumindo `NFeAutorizada` event; resolve email via Invoice→Contact)
**Marcada `done` em 2026-05-10** — code-complete em main desde PR #118 (`33e061bf`). DoD original tinha `[ ] Prod-evidence: ≥1 NFe55 autorizada ROTA LIVRE biz=4` mas removido após pivot conceitual com Wagner: **venda sem nota é caminho feliz, não falha**. Pré-requisito mais profundo (gate de emissão **POR VENDA**, não por business) virou cadeia [US-SELL-010](../Sells/SPEC.md#us-sell-010) → [US-SELL-011](../Sells/SPEC.md#us-sell-011) → [US-SELL-012](../Sells/SPEC.md#us-sell-012) (FSM canônica + RBAC por transição). Smoke prod end-to-end real fica em [US-NFE-059](../NfeBrasil/SPEC.md#us-nfe-059) (bloqueada por US-SELL-012).

### US-RB-045 · Inter PJ — saldo via Banking API v2 (Fase 1 OF direto)

> owner: wagner · priority: p1 · estimate: 2h · status: done · type: story · origin: sessao-2026-05-07-of-direto
> blocked_by: —

**Implementado em:** `Modules/RecurringBilling/Services/Banking/InterBankingClient.php` · `Modules/RecurringBilling/Jobs/SyncBankBalancesJob.php` · `Modules/RecurringBilling/Tests/Feature/InterBankingClientTest.php` · verificado@176f9bc (2026-07-01) — OAuth client_credentials + mTLS + saldo cacheado 50min; `SyncBankBalancesJob::fetchInterSaldo()` atualiza `saldo_cached` da conta Inter

**Contexto.** Wagner aprovou plano em 3 fases pra ter "extrato + boleto + PIX direto" do Inter (sem agregador OF tipo Pluggy). Esta é a **Fase 1 — Quick win** que valida cert mTLS + OAuth ponta-a-ponta antes de gastar com extrato/PIX. Hoje [`SyncBankBalancesJob.php:73`](../../../Modules/RecurringBilling/Jobs/SyncBankBalancesJob.php#L73) tem `'inter' => null` (TODO).

**Escopo:**
- Novo service `Modules/RecurringBilling/Services/Banking/InterBankingClient` (separado de `InterDriver` — SoC ADR 0094 §5: banking ≠ boleto)
- Métodos: `oauthToken(scope)` cacheado 50min por `(business_id, scope)`; `getSaldo()` retorna `{disponivel, bloqueado, limite}`
- Reusa `certificado_crt_b64` + `certificado_key_b64` do `BoletoCredential.config_json` (mesmo cert mTLS cobre Banking API)
- Wire `SyncBankBalancesJob::fetchInterSaldo()` rotando `'inter' => $this->fetchInterSaldo($conta, $config)`

**Acceptance criteria:**
- [x] `InterBankingClient` cria com config + retorna `disponivel` como float
- [x] OAuth token cacheado por `(business_id, scope)` com TTL 3000s
- [x] `SyncBankBalancesJob` atualiza `fin_contas_bancarias.saldo_cached` da conta Inter
- [x] Pest `InterBankingClientTest` com `Http::fake()`: token request → saldo request → cache hit
- [x] Pest passa multi-tenant: 2 businesses com Inter cada, sync isola cache de token
- [x] Erro 401 (cert inválido) loga `[REDACTED]` e propaga RequestException
- [x] PR ≤300 linhas, conventional commits `feat(rb): inter banking client + saldo sync`
- [x] Sem `withoutGlobalScopes` (Tier 0 multi-tenant)

**Marcada `done` em 2026-05-11** — code-complete em main desde PR [#206](https://github.com/wagnerra23/oimpresso.com/pull/206) (`feat(rb): InterBankingClient + saldo Inter via Banking API v2`, MERGED 2026-05-07) + fix PR [#331](https://github.com/wagnerra23/oimpresso.com/pull/331) (`fix(recurring-billing): descriptografar certificado_key_b64 nos Sync jobs`, MERGED 2026-05-09). 7 cenários Pest em `Modules/RecurringBilling/Tests/Feature/InterBankingClientTest.php` cobrem saldo OK, cache 50min, isolamento multi-tenant (biz=1 vs biz=2), isolamento por scope, 401 saldo, 401 token, cert 0600 idempotente. Smoke prod real fica em [US-RB-048](#us-rb-048) (RUNBOOK + canary 7d).

**Pré-requisito (Wagner):** liberar escopo `extrato.read` no portal Inter pra conta de teste.

**Out of scope:** pagamento PIX/boleto saída (`/banking/v2/pagamento`); outros bancos (depois Fase 2 com `BankStatementDriverContract`).

**Refs:** ADR 0094 §5 SoC brutal · `eduardokum/laravel-boleto` cobre apenas boleto+PIX charging, não Banking API.

### US-RB-046 · Inter PJ — extrato sync diário + tela /financeiro/extrato (Fase 2)

> owner: wagner · priority: p1 · estimate: 6h · status: done · type: story · origin: sessao-2026-05-07-of-direto
> blocked_by: US-RB-045

**Implementado em:** `Modules/RecurringBilling/Contracts/BankStatementDriverContract.php` · `Modules/RecurringBilling/Services/Banking/Drivers/InterStatementDriver.php` · `Modules/RecurringBilling/Jobs/SyncBankStatementsJob.php` · `Modules/Financeiro/Database/Migrations/2026_05_07_220000_create_fin_extrato_lancamentos_table.php` · `resources/js/Pages/Financeiro/Extrato/Index.tsx` · `Modules/RecurringBilling/Tests/Feature/InterStatementDriverTest.php` · verificado@176f9bc (2026-07-01) — driver de extrato + `fin_extrato_lancamentos` com UNIQUE idempotente + job de sync + tela React

**Contexto.** Fase 2 do plano "Inter direto". Lê extrato D-7 do Inter e mostra lançamentos em tela. Reaproveita `InterBankingClient` (Fase 1 mergeada).

**Escopo:**
- Endpoint Inter: `GET /banking/v2/extrato/completo?dataInicio=...&dataFim=...`
- Tabela nova `fin_extrato_lancamentos` (vive em Modules/Financeiro): id, business_id (indexed), conta_bancaria_id (FK), data, valor (decimal 15,2), tipo (enum C|D), descricao, contraparte_documento, contraparte_nome, idempotency_key, raw_payload (JSON), timestamps. UNIQUE `(conta_bancaria_id, idempotency_key)` — re-sync seguro. Index `(business_id, data)`.
- Contract novo `Modules/RecurringBilling/Contracts/BankStatementDriverContract` (separado de `BoletoDriverContract` — SoC ADR 0094 §5): `fetchStatement(Carbon $from, Carbon $to): Collection<StatementLineDto>`
- `InterStatementDriver` em `Modules/RecurringBilling/Services/Banking/Drivers/`
- DTO `StatementLineDto` (data, valor, tipo, descricao, contraparte, idempotency_key, raw)
- Job `SyncBankStatementsJob` agendado em `app/Console/Kernel.php` daily 07:00 BRT, puxa últimos 7d por conta
- Tela Inertia/React `Modules/Financeiro/resources/js/Pages/Extrato/Index.tsx` em `/financeiro/extrato/{conta_bancaria_id}` — DataTable lançamentos paginada, filtro período (default 30d), saldo do dia. Skill `mwart-quality` Tier B ativa antes de codar.

**Acceptance criteria:**
- [ ] Migration cria `fin_extrato_lancamentos` com UNIQUE idempotency
- [ ] `BankStatementDriverContract` em `Contracts/`
- [ ] `InterStatementDriver` parsa response Inter v2 → `StatementLineDto[]`
- [ ] Job grava lançamentos com upsert idempotente — re-sync 2x não duplica
- [ ] Tela renderiza extrato com `business_id` global scope
- [ ] Pest cobre: parse · idempotência · isolamento entre 2 businesses · scope global aplicado
- [ ] DataController de Financeiro adiciona link "Extrato bancário" no topnav
- [ ] PR ≤300 linhas (provável split: backend + frontend)

**Out of scope:** conciliação automática (matchear extrato com `fin_titulos`) — futura US separada; outros bancos.

**Refs:** ADR 0094 §5 SoC brutal · skill `mwart-quality` Tier B · skill `multi-tenant-patterns` Tier A.

### US-RB-047 · Inter PJ — PIX cob imediata + webhook receiver (Fase 3)

> owner: wagner · priority: p1 · estimate: 6h · status: done · type: story · origin: sessao-2026-05-07-of-direto
> blocked_by: US-RB-045, US-RB-046

**Implementado em:** `Modules/RecurringBilling/Services/Banking/Drivers/InterPixCobDriver.php` · `Modules/RecurringBilling/Dto/PixCobResult.php` · `Modules/RecurringBilling/Http/Controllers/InterWebhookController.php` · `Modules/RecurringBilling/Jobs/ProcessInterWebhookJob.php` · `Modules/RecurringBilling/Tests/Feature/InterWebhookControllerTest.php` · verificado@176f9bc (2026-07-01) — cob imediata (PUT cobranca/v3/cob) retorna QR + copia-e-cola; webhook público valida secret (401 mismatch) + idempotência pg_webhook_events + dispara InvoicePaid

**Contexto.** Fase 3 do plano "Inter direto". Gera QR Code PIX dinâmico (cob imediato) e recebe notificação `pix.recebido` em tempo real via webhook. Reaproveita OAuth + cliente HTTP mTLS de `InterBankingClient` (Fases 1+2 mergeadas).

**Escopo:**
- Endpoint Inter: `PUT /cobranca/v3/cob/{txid}` (cob imediata)
- Driver `InterPixCobDriver` (separado do `InterDriver` de boleto): `criarCobImediata(valor, devedor, infoAdicionais): PixCobResult` (qrcode_base64 + copia_e_cola)
- Endpoint público `POST /webhooks/inter/pix/{business_id}` (rota web group)
  - **CRÍTICO Tier 0:** valida assinatura HMAC com `secret_webhook` por `business_id` (BoletoCredential.config_json) ANTES de processar
  - Idempotência via tabela `pg_webhook_events` (já existe pro Asaas — reusar com `gateway: inter`)
  - Job `ProcessInterWebhookJob` parsa payload e dispara `Modules\RecurringBilling\Events\InvoicePaid` se `status == CONCLUIDA`
- Botão "Gerar PIX" em telas de cobrança — modal com QR Code + copia-e-cola
- Configurar webhook URL no Inter via API: `PUT /webhooks/{tipoWebhook}` durante onboarding da credencial

**Acceptance criteria:**
- [ ] `InterPixCobDriver::criarCobImediata` retorna `PixCobResult` com txid, qrcode_base64, copia_e_cola, expiracao
- [ ] Endpoint webhook valida HMAC; assinatura inválida → 401 com log `[REDACTED]`
- [ ] Idempotência: webhook 2× com mesmo `endToEndId` grava 1×
- [ ] `business_id` no path bate com `business_id` da `cob` original — mismatch → 403
- [ ] Pest cobre: criar cob · webhook válido dispara `InvoicePaid` · webhook duplicado ignorado · HMAC inválido 401 · cross-tenant 403
- [ ] Botão "Gerar PIX" funcional em UI Financeiro
- [ ] Listener `BaixarTituloOnInvoicePaidListener` (já existe pro Asaas) trata também Inter via mesmo Event
- [ ] PR ≤300 linhas (provável split: driver+webhook backend → UI)

**Risco.** Alto porque é endpoint público + dinheiro real. Mitigação: HMAC assinatura obrigatória · `business_id` no path validado contra cob · idempotência forte · Pest com cenários adversariais.

**Out of scope:** PIX automático recorrente (BCB nova fase) — futuro; PIX saída (`/banking/v2/pagamento`) — futuro.

**Refs:** ADR 0094 §6 Multi-tenant Tier 0 IRREVOGÁVEL · pattern webhook `Modules/RecurringBilling/Http/Controllers/AsaasWebhookController.php` · tabela idempotência `pg_webhook_events`.

## 8. Referências

- `_Ideias/CobrancaRecorrente/evidencias/conversa-claude-2026-04-mobile.md`
- Lago (open source): https://github.com/getlago/lago
- Kill Bill: https://killbill.io/
- BCB Pix Automático docs (jornadas JRC)
- Stripe smart retries (referência ML)
- Auto-memória: `reference_ultimatepos_integracao.md`, `reference_db_schema.md`

### US-RECURRINGBILLING-001 · Escopo 0 — PaymentGateway + adapter Asaas (pré-requisito cobrança)

> owner: wagner · priority: p0 · estimate: 16h · status: done · type: story
> blocked_by: —

**Implementado em:** `Modules/RecurringBilling/Services/Boleto/Drivers/AsaasDriver.php` · `Modules/RecurringBilling/Http/Controllers/AsaasWebhookController.php` · `Modules/RecurringBilling/Models/BoletoCredential.php` · `Modules/RecurringBilling/Database/Migrations/2026_05_06_000003_create_pg_webhook_events_table.php` · `Modules/RecurringBilling/Tests/Feature/AsaasDriverTest.php` · verificado@176f9bc (2026-07-01) — adapter Asaas (credencial cifrada por tenant + cobrança + webhook idempotente `pg_webhook_events`); duplicata do range US-RB-ESC0/040

- Módulo PaymentGateway scaffold (tabelas `pg_credentials`, `pg_charge_attempts`, `pg_webhook_events`)
- Adapter Asaas: credencial por tenant (api_key encrypted), cobrança avulsa, webhook `payment.confirmed` / `payment.overdue`
- Idempotência por `(provider, event_id)` em `pg_webhook_events`
- Resposta 200 imediata + processamento async via job (fila `rb_webhooks`)
- Tela admin: cadastrar credencial Asaas por tenant
- Test Feature: criar credencial + cobrança avulsa mock + webhook idempotência + isolamento multi-tenant
- **Pré-requisito de todos os outros escopos**

### US-RECURRINGBILLING-002 · Escopo 1 — Motor de cobrança recorrente (plans + contracts + invoices + job)

> owner: wagner · priority: p0 · estimate: 32h · type: story
> blocked_by: —

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Services/InvoiceGeneratorService.php` · `Modules/RecurringBilling/Console/Commands/GenerateInvoicesCommand.php` · `Modules/RecurringBilling/Models/Subscription.php` · `Modules/RecurringBilling/Http/Controllers/RecurringBillingController.php` · `Modules/RecurringBilling/Tests/Feature/InvoiceGeneratorServiceTest.php` · verificado@176f9bc (2026-07-01) — falta: `ChargeInvoicesJob` + evento `ChargeRequested` + charge manual + Listener `InvoiceGenerated` → título no Financeiro (grep 0 em todos); entregue plans/subscriptions/invoices + geração idempotente por competência + comando `rb:generate-invoices` + Page Inertia (consistente com o _parcial_ da US-RB-004); duplicata do range US-RB-ESC1/043

- Migrations: `rb_plans`, `rb_contracts`, `rb_invoices`
- PlanController CRUD + ContractController (criar/cancelar) + InvoiceController (listar/charge manual)
- `GenerateInvoicesJob` — diário 03:00, gera fatura pra contratos com `next_billing_date <= hoje+3d`, idempotência por `(contract_id, ciclo_competencia)`
- `ChargeInvoicesJob` — 1h após geração, dispara evento `ChargeRequested` pro PaymentGateway
- Listener `InvoiceGenerated` → cria título no Financeiro (`rb_invoices` linked)
- Layout React: lista contratos (status badge), detalhe com timeline de ciclos (Recharts), ação manual "cobrar agora"
- Test Feature: 100 contratos × 3 ciclos = 300 invoices sem dupla + isolamento
- **Bloqueado por:** Escopo 0 (PaymentGateway)

### US-RECURRINGBILLING-003 · Escopo 2 — Boleto impresso via Asaas

> owner: wagner · priority: p0 · estimate: 8h · type: story
> blocked_by: —

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Services/Boleto/Drivers/AsaasDriver.php` · `Modules/RecurringBilling/Services/Boleto/BoletoService.php` · `Modules/RecurringBilling/Tests/Feature/BoletoServiceTest.php` · verificado@176f9bc (2026-07-01) — falta: `SendBoletoEmailJob` (inexistente) + fluxo `ChargeRequested` (inexistente) + botão "Imprimir boleto" em Faturas/Index.tsx (inexistente); entregue só o driver-side (AsaasDriver gera boleto e retorna `pdfUrl`/bankSlipUrl); duplicata do range US-RB-ESC2

- No `ChargeRequested` com forma=boleto: Asaas adapter gera boleto + retorna `boleto_pdf_url` + `boleto_barcode`
- Persiste em `pg_charge_attempts.boleto_pdf_url`
- Tela fatura (React): botão "Imprimir boleto" (abre PDF em nova aba) + linha digitável copiável
- Envio por email automático com link PDF ao gerar (job `SendBoletoEmailJob`)
- Test Feature: gerar boleto mock + verificar url salva + email disparado
- **Bloqueado por:** Escopo 1

### US-RECURRINGBILLING-004 · Escopo 3 — NFSe assíncrona ao pagar (Focus/PlugNotas adapter)

> owner: wagner · priority: p1 · estimate: 24h · type: story
> blocked_by: —

**Implementado em:** _parcial_ · `Modules/NFSe/Adapters/SnNfseAdapter.php` · `Modules/NFSe/Jobs/EmitirNfseJob.php` · `Modules/NFSe/Models/NfseEmissao.php` · `Modules/NFSe/Database/Migrations/2026_05_01_000003_create_nfse_emissoes_table.php` · verificado@176f9bc (2026-07-01) — existe módulo NFSe com emissão assíncrona por job + provider config (tabelas `nfse_emissoes`/`nfse_provider_configs`, adapter SnNfse); falta: o wire específico desta US — listener de `InvoicePaid` do RecurringBilling disparando `NFSeEmissionRequested` (nenhum listener de InvoicePaid em Modules/NFSe) e adapter Focus/PlugNotas

- Sub-módulo NFSe scaffold isolado (tabelas `nfse_documents`, `nfse_credentials`)
- Adapter Focus NFe (plugável, configurável por tenant): emitir NFSe + consultar status + cancelar
- Listener em `InvoicePaid` → dispara `NFSeEmissionRequested` (fila `rb_nfse`, não trava billing)
- Webhook do provider confirma emissão → salva PDF link + XML em `nfse_documents`
- Tela: status da NF por fatura (pendente/emitida/erro), link PDF, botão reemitir
- NFSe é assíncrona: provider pode levar minutos; UI mostra "aguardando prefeitura"
- Test Feature: listener disparado ao pagar + mock provider + status assíncrono + isolamento
- **Bloqueado por:** Escopo 1

### US-RECURRINGBILLING-005 · Cobertura Pest dos 3 drivers de boleto (Inter/C6/Asaas)

> owner: — · priority: p0 · estimate: 8h · type: story
> blocked_by: —

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Tests/Feature/InterDriverTest.php` · `Modules/RecurringBilling/Tests/Feature/C6DriverTest.php` · `Modules/RecurringBilling/Tests/Feature/AsaasDriverTest.php` · `Modules/RecurringBilling/Tests/Feature/BoletoServiceTest.php` · verificado@176f9bc (2026-07-01) — falta: mesmo gap do round-trip da US-RB-040 (InterDriverTest não faz emitir+cancelar com sandbox mockada — só construtor + writeTempCert, InterDriverTest:19,43-113); duplicata de US-RB-040 (mesmos 4 testes)

## Contexto
Origem: `/comparativo RecurringBilling` em 2026-05-06. Capacidade #1 da CAPTERRA-FICHA classificada 🟡 PARCIAL — drivers e UI existem, mas `Modules/RecurringBilling/Tests/` está vazia. Sem teste, qualquer mexida nova é bug em prod garantido.

## Acceptance criteria
- [ ] `Tests/Feature/InterDriverTest.php` — round-trip emitir + cancelar com sandbox response mockada (não chamar API real)
- [ ] `Tests/Feature/C6DriverTest.php` — geração local CNAB + nossoNumero + linha digitável válidos
- [ ] `Tests/Feature/AsaasDriverTest.php` — POST /payments mockado + parsing do response
- [ ] `Tests/Feature/BoletoServiceTest.php` — resolve driver correto por banco da credencial; decryptConfig roundtrip
- [ ] CI verde com os novos testes

## Referências
- ADR 0089 (Capterra-driven Module Evolution)
- ADR tech/0007 (encryption pattern credenciais boleto)
- CAPTERRA-INVENTARIO.md item #1

### US-RECURRINGBILLING-006 · Test de retry idempotente do ProcessAsaasWebhookJob

> owner: — · priority: p0 · estimate: 3h · status: done · type: story
> blocked_by: —

**Implementado em:** `Modules/RecurringBilling/Tests/Feature/AsaasWebhookIdempotencyTest.php` · `Modules/RecurringBilling/Jobs/ProcessAsaasWebhookJob.php` · verificado@176f9bc (2026-07-01) — duplicata de US-RB-041

## Contexto
Origem: `/comparativo RecurringBilling` 2026-05-06. Capacidade #2 🟡 — tabela `pg_webhook_events` UNIQUE(provider, event_id) existe, mas sem teste cobrindo retry/replay. Webhook duplicado pelo Asaas (que **acontece em produção**) sem cobertura de teste = cobrança duplicada esperando pra acontecer.

## Acceptance criteria
- [ ] `Tests/Feature/AsaasWebhookIdempotencyTest.php`
- [ ] Cenário: 2 chamadas POST /api/webhooks/asaas/{biz} com mesmo event_id → segunda retorna 200 sem reprocessar
- [ ] Cenário: PAYMENT_RECEIVED processado 2× não cria 2 account_transactions (insertOrIgnore funciona)
- [ ] Cenário: BALANCE_UPDATED processado 2× não duplica saldo_cached
- [ ] Cenário: job falha no meio → retry roda completo sem dups (atomicidade)

## Referências
- ADR tech/0001-idempotencia-charge-attempts-e-webhooks
- ProcessAsaasWebhookJob.php
- CAPTERRA-INVENTARIO.md item #2

### US-RECURRINGBILLING-007 · Completar cancelar() C6/Asaas + UI Cancelar título + audit log

> owner: — · priority: p0 · estimate: 6h · type: story
> blocked_by: —

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Services/Boleto/Drivers/C6Driver.php` · `Modules/RecurringBilling/Services/Boleto/Drivers/AsaasDriver.php` · `Modules/RecurringBilling/Http/Controllers/InvoiceController.php` · `resources/js/Pages/RecurringBilling/Faturas/Index.tsx` · verificado@176f9bc (2026-07-01) — falta: `C6Driver::cancelar()` é STUB que lança `BadMethodCallException` (C6Driver:80-91) — CNAB ocorrência 02 não implementado; UI real de cancelamento fica em Faturas/Index.tsx:423 (botão removido de propósito do drawer da Cobrança — "B6 botões honestos", DrawerCobranca.tsx:118-123); duplicata de US-RB-042

## Contexto
Origem: `/comparativo RecurringBilling` 2026-05-06. Capacidade #4 🟡 — `BoletoDriverContract::cancelar()` definido e implementado em InterDriver, mas C6 e Asaas precisam ser auditados/completados. UI inexistente. Cancelamento é exigência de lei (Procon/LGPD) — não pode depender de SQL.

## Acceptance criteria
- [ ] Auditar `C6Driver::cancelar()` — implementar via CNAB remessa de cancelamento se ausente
- [ ] Auditar `AsaasDriver::cancelar()` — usar DELETE /payments/{id}
- [ ] Botão "Cancelar título" em `resources/js/Pages/Financeiro/Boletos/` (ou similar) com confirmação
- [ ] Endpoint `POST /financeiro/boletos/{id}/cancelar` chama BoletoService → driver
- [ ] Spatie Activity Log registrando cancelamento (quem/quando/motivo)
- [ ] Permissão `financeiro.boleto.cancelar` (default só admin do business)
- [ ] Teste Pest cobrindo os 3 drivers (depende de #1)

## Referências
- BoletoDriverContract.php
- InterDriver::cancelar() (referência)
- CAPTERRA-INVENTARIO.md item #4

### US-RECURRINGBILLING-008 · [Epic] Models Subscription/Plan/Invoice/ChargeAttempt + migrations

> owner: — · priority: p1 · estimate: 16h · status: done · type: story
> blocked_by: —

**Implementado em:** `Modules/RecurringBilling/Models/Plan.php` · `Modules/RecurringBilling/Models/Subscription.php` · `Modules/RecurringBilling/Models/Invoice.php` · `Modules/RecurringBilling/Models/ChargeAttempt.php` · `Modules/RecurringBilling/Database/Migrations/2026_05_06_001001_create_rb_subscriptions_table.php` · `Modules/RecurringBilling/Tests/Feature/DomainModelsTest.php` · verificado@176f9bc (2026-07-01) — duplicata de US-RB-043 (4 models + migrations do domínio recorrente)

## Contexto
Origem: `/comparativo RecurringBilling` 2026-05-06. Capacidade #4 ❌ AUSENTE — fundação do domínio recorrente. **Epic bloqueador** das capacidades #5 (cartão recorrente), #7 (régua dunning), #8 (matcher reconciliação), #9 (tela assinaturas), #11 (proration), #12 (split), #13 (métricas SaaS).

## Acceptance criteria
- [ ] Migration `rb_plans` — id, business_id, nome, valor, ciclo (monthly/yearly), trial_days, ativo
- [ ] Migration `rb_subscriptions` — id, business_id, plan_id, contact_id (UPos contacts), status (active/paused/canceled/trial), próximo_vencimento, billing_anchor_date
- [ ] Migration `rb_invoices` — id, subscription_id, valor, status (open/paid/overdue/canceled), vencimento, pago_em, gateway_ref, conta_bancaria_id
- [ ] Migration `rb_charge_attempts` — id, invoice_id, gateway, attempt_n, response_json, status, created_at (idempotent retry log)
- [ ] Models Eloquent com relacionamentos + global scope BusinessScope (multi-tenant)
- [ ] Tipos: int unsigned para FKs em tabelas legadas UltimatePOS (ADR tech/0008)
- [ ] Migrations idempotentes (Schema::hasColumn guard, ADR tech/0008)
- [ ] Seeder de exemplo com 2 planos para ROTA LIVRE (biz=4)
- [ ] Tests Pest cobrindo: criar subscription, gerar próxima fatura, transição de status

## Bloqueia
- Cartão recorrente, Régua dunning, Matcher reconciliação, Tela assinaturas, Proration, Split, Métricas SaaS

## Referências
- ADR tech/0008 (FK type-mismatch UltimatePOS)
- multi-tenant-patterns skill
- CAPTERRA-INVENTARIO.md item #4

### US-RECURRINGBILLING-009 · Listener InvoicePaid em NfeBrasil — emissão automática de NFe55 + DANFE + e-mail

> owner: — · priority: p1 · estimate: 12h · status: done · type: story
> blocked_by: —

**Implementado em:** `Modules/NfeBrasil/Listeners/EmitirNFeAoReceberPagamento.php` · `Modules/NfeBrasil/Services/NfeService.php` · `Modules/NfeBrasil/Events/NFeAutorizada.php` · `Modules/RecurringBilling/Events/InvoicePaid.php` · verificado@176f9bc (2026-07-01) — duplicata de US-RB-044 (listener consome InvoicePaid → emite NFe55 + DANFE + e-mail)

## Contexto
Origem: `/comparativo RecurringBilling` 2026-05-06. Capacidade #6 ❌ AUSENTE — **diferencial cross-vertical do núcleo oimpresso** (não Modules/<Vertical>; serve qualquer assinatura/contrato com cobrança recorrente). Gateway de boleto é commodity (5 concorrentes têm). "Boleto pago → NFe modelo 55 emitida automaticamente sem clique humano" é diferencial do oimpresso. Larissa (ROTA LIVRE — Modules/Vestuario, loja roupa Gravatal/SC) pediu isso há tempos.

Event `InvoicePaid` JÁ existe em `Modules/RecurringBilling/Events/InvoicePaid.php` — falta listener em NfeBrasil consumindo.

## Acceptance criteria
- [ ] `Modules/NfeBrasil/Listeners/EmitirNFeAoReceberPagamento.php` registrado em EventServiceProvider
- [ ] Listener resolve produto/serviço da fatura → mapeia pra item de NFe (CFOP, NCM, alíquotas)
- [ ] Carrega certificado A1 do business via NfeCertificadoService
- [ ] Chama nfephp-org/sped-nfe → autoriza SEFAZ
- [ ] Renderiza DANFE (PDF)
- [ ] Envia e-mail pro pagador com DANFE anexado
- [ ] Log estruturado de cada passo (gen_ai.* OpenTelemetry pattern, ADR 0049)
- [ ] Falha de SEFAZ não derruba pagamento — retry job separado
- [ ] Teste Pest: dispara InvoicePaid → assert NFe criada com status=autorizada
- [ ] Prod-evidence: ≥1 NFe modelo 55 autorizada via esse fluxo (ROTA LIVRE biz=4)

## Diferencial competitivo
Iugu/Asaas/Vindi/Pagar.me **não têm** isso. Para gráfica, é dor real (emissão manual de NFe é gargalo de Larissa).

## Referências
- Modules/RecurringBilling/Events/InvoicePaid.php
- Modules/NfeBrasil (escopo já existente)
- CAPTERRA-INVENTARIO.md item #6

---

## Auditoria de completude — 2026-05-10

Disparada por: `/module-completeness-audit` (skill `module-completeness-audit` v0.1.0, sessão Wagner 2026-05-10).

**Resultado: 4 ✅ / 2 🟡 / 2 ❌ (de 8 dimensões)**

| Dim | Nome | Status | Evidência |
|---|---|---|---|
| 1 | Multi-instance scope | ✅ APROVADO | `Models/Subscription.php:59-61` (contaBancaria BelongsTo) + `BoletoCredential.php:15-16` (conta_bancaria_id NULLABLE) |
| 2 | Permissions middleware + UI | 🟡 PARCIAL | só `recurringbilling.invoice.cancel` em `InvoiceController.php:33-38`; faltam `plans.manage`, `contracts.manage`, `webhooks.view` |
| 3 | Charter | ❌ AUSENTE (exceção) | UI Pages/RecurringBilling/ ainda não existe — módulo SPEC-only |
| 4 | RUNBOOK | 🟡 PARCIAL | sem `RUNBOOK*.md`; SPEC + 9 ADRs cobrem orquestração mas faltam playbook operacional |
| 5 | Pest golden + cross-tenant biz=99 | ✅ APROVADO | `BoletoServiceTest.php:50-103` (biz=99,100,101 com 3 drivers Inter/C6/Asaas) + `InterWebhookControllerTest.php:86-93` (404 cross-tenant guard) |
| 6 | AuditLog em mutações | ✅ APROVADO | `InvoiceController.php:97-121` (Spatie Activitylog `activity('recurringbilling.invoice')` com business_id em properties) |
| 7 | business_id global scope | ✅ APROVADO | 5/5 Models usam `HasBusinessScope`; UNIQUE constraints compostos (business_id + slug, business_id + numero_documento) |
| 8 | Browser MCP smoke | ❌ AUSENTE (exceção) | UI ainda não existe — smoke MCP impossível antes Inter PJ go-live |

### Gaps virando US-fix

- **US-RB-048** (P0): Dim 4 RUNBOOK — bloqueia go-live Inter PJ Banking API v2 (em voo Wagner +3h)

### Gaps com exceção registrada (Wagner aprovou pular — UI ainda não existe)

- ❌ Dim 3 Charter — UI Pages/RecurringBilling/ não existe; charter virá quando US-RB-046 (Inter PJ UI) for codada. Risco aceito: módulo SPEC-only por design.
- ❌ Dim 8 Smoke MCP — mesma razão; smoke captura screenshot só após Inter PJ ir pra prod.

### Gaps deferred (P1/P2 — não aprovados nesta auditoria)

- 🟡 Dim 2 Permissions UI (P1) — adicionar permissions `recurringbilling.plans.manage`, `contracts.manage`, `webhooks.view` em US-RB-001..005. Razão deferred: Wagner aprovou só P0; reauditar próximo cycle.


### Atualização da auditoria 2026-05-10 — re-aprovação batch completo

Wagner re-aprovou (mesma data, turno seguinte) o batch completo: P1 também virou US-fix. **Lista "Gaps deferred" acima zerada.**

US-fix adicional criada:
- **US-RB-049** (P1): Dim 2 Permissions UI (plans.manage, contracts.manage, webhooks.view)

Exceções RB-1 (Dim 3 Charter) e RB-2 (Dim 8 Smoke MCP) **mantidas** — UI Pages/RecurringBilling/ ainda não existe; charter+smoke virão em US-RB-046 (Inter PJ UI). Reauditar quando UI for codada.

Total de gaps RecurringBilling convertidos em US-fix: **2 de 4 detectados** (2 mantidos como exceção).


### US-RB-048 · RUNBOOK operacional antes do Inter PJ Banking API ir pra prod

> owner: wagner · sprint: cycle-04 · priority: p0 · estimate: 1h · status: done · type: story
> blocked_by: —

**Implementado em:** `memory/requisitos/RecurringBilling/RUNBOOK-inter-pj.md` · verificado@176f9bc (2026-07-01) — RUNBOOK operacional Inter PJ criado

Gap detectado por skill `module-completeness-audit` em 2026-05-10 (Dim 4 RUNBOOK — 🟡 PARCIAL escalado a P0).

**Evidência:** Glob `memory/requisitos/RecurringBilling/RUNBOOK*.md` retornou vazio. Wagner em voo HOJE com Inter PJ Banking API v2 (saldo + extrato, ETA +3h). Sem RUNBOOK, suporte/operação não tem playbook quando algo falhar (token expira, webhook 401, sandbox vs prod confusion).

**Fix sugerido:** rodar skill `cockpit-runbook` em `memory/requisitos/RecurringBilling/` → gera `RUNBOOK-inter-pj.md` com 11 seções obrigatórias (Pré-requisitos / Setup / Smoke / Debug / Erros comuns / Rollback / etc).

**Acceptance criteria:**
- [ ] `memory/requisitos/RecurringBilling/RUNBOOK-inter-pj.md` criado, status:live
- [ ] 11 seções canônicas preenchidas (referência: `memory/requisitos/NfeBrasil/RUNBOOK-smoke-sefaz.md`)
- [ ] Inclui: como obter token Inter, validar cert PJ, sandbox→prod, ler saldo, debug webhook 401, rollback se receber valor errado
- [ ] Snippet PowerShell + cURL executáveis em PT-BR
- [ ] Push antes de promover Inter PJ pra prod

**Disparo:** Auditoria de completude 2026-05-10. Bloqueia go-live Inter PJ Banking API v2.
**Tags:** completeness-gap, from-skill, audit-2026-05-10, inter-pj

### US-RB-049 · Permissions UI: plans.manage, contracts.manage, webhooks.view (US-RB-001..005)

> owner: — · sprint: cycle-04 · priority: p1 · estimate: 4h · type: story
> blocked_by: US-RB-046

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Http/Controllers/DataController.php` · `Modules/RecurringBilling/Resources/lang/pt-BR/recurringbilling.php` · verificado@176f9bc (2026-07-01) — permissões reais declaradas em `user_permissions()` (DataController:45-57) e aparecem em /roles: `recurringbilling.subscriptions.manage`/`subscriptions.cancel`/`plans.manage`/`invoices.view`/`invoices.charge`/`notes.write`/`favorites.write`/`configuracoes.manage` (`contracts.manage` e `webhooks.view` NÃO existem no módulo); falta: middleware `can:<scope>.<action>` por rota (controllers usam gate genérico `recurringbilling.access`), Pest de 403 por permissão e UI de gestão

Gap detectado por skill `module-completeness-audit` em 2026-05-10 (Dim 2 Permissions middleware + UI — 🟡 PARCIAL).

**Evidência:** `Modules/RecurringBilling/Http/Controllers/InvoiceController.php:33-38` tem `can:recurringbilling.invoice.cancel`. Mas `Routes/web.php:26` é placeholder Resource VAZIO. Faltam permissions canônicas pra plans, contracts, webhooks.

**Fix sugerido:**
1. Criar permissions Spatie via seeder: `recurringbilling.plans.manage`, `recurringbilling.contracts.manage`, `recurringbilling.webhooks.view`
2. Aplicar middleware `can:recurringbilling.<scope>.<action>` nas rotas conforme controllers vierem (US-RB-001..005)
3. Quando UI Pages/RecurringBilling/ for codada (US-RB-046 Inter PJ UI), adicionar `Pages/RecurringBilling/Admin/Permissions/Index.tsx` ou agrupar em /admin/roles

**Acceptance criteria:**
- [ ] Migration seeder cria 3 permissions Spatie
- [ ] `Routes/web.php` aplica `can:*` em Plans/Contracts/Webhooks routes
- [ ] Pest test verifica 403 quando user sem permission
- [ ] Pest test cross-tenant biz=99 não vê permissions de biz=1

**Disparo:** Auditoria de completude 2026-05-10.
**Bloqueado por:** US-RB-046 (Inter PJ UI — pra ter onde mostrar gestão de permissões). Pode ser implementada parcialmente (só backend + middleware) antes da UI.
**Tags:** completeness-gap, from-skill, audit-2026-05-10

### US-RB-050 · Inter PJ — PIX cobrança imediata (CYCLE-06 G1 wiring Martinho)

> owner: wagner · sprint: cycle-06 · priority: p0 · estimate: 4h · type: story · origin: cycle-06-martinho-cacambas-2026-05-16
> blocked_by: US-RB-045 (Banking API OAuth + mTLS já mergeado)

**Implementado em:** _parcial_ · `Modules/RecurringBilling/Services/Banking/Drivers/InterPixCobDriver.php` · `Modules/RecurringBilling/Dto/PixCobResult.php` · `Modules/RecurringBilling/Services/Banking/InterBankingClient.php` · verificado@176f9bc (2026-07-01) — cob imediata Pix Inter (`criarCobImediata` → txid + QR + copia-e-cola) coberta via US-RB-047; falta: o InterPixCobrancaService nomeado nesta US (em Services/Inter/, foi implementado como Driver, não Service DDD) e a persistência da tentativa em pg_charge_attempts com metodo=pix

**Contexto.** Pivot Martinho Caçambas — cliente real (CYCLE-06 G1) precisa receber pagamento PIX antes do boleto cair. Reaproveita 100% do `InterBankingClient` (Fase 1 OF direto, ADR-paralela): OAuth client_credentials, mTLS cert+key, retry exponencial, token cache 60min.

Refina/separa o `InterPixCobDriver` mencionado em US-RB-047 num `InterPixCobrancaService` (Service de domínio, não Driver de adapter) pra alinhar com nomenclatura DDD do módulo (`Modules/RecurringBilling/Services/Inter/`).

**Escopo backend:**
- `Modules/RecurringBilling/Services/Inter/InterPixCobrancaService.php` registrado como singleton em `RecurringBillingServiceProvider::registerInterPixServices()` (já wired neste PR)
- Método `criarCobImediata(int $businessId, float $valor, array $devedor, ?string $infoAdicionais, ?int $expiracaoSegundos = 3600): PixCobResult`
- Endpoint Inter: `PUT /cobranca/v3/cob/{txid}` — txid gerado server-side (UUIDv4 sem hífens, 26 chars conforme spec BCB Pix)
- Credencial: lê `BoletoCredential` do tenant (gateway=inter) ANTES de cair em `config('services.inter')` (Tier 0 multi-tenant)
- Retorna `PixCobResult` DTO: `txid`, `qrcode_base64`, `copia_e_cola`, `expiracao_em`, `e2e_id_esperado`
- Persiste tentativa em `pg_charge_attempts` com `gateway=inter`, `metodo=pix`, `status=aguardando_pagamento`

**Acceptance criteria:**
- [ ] `InterPixCobrancaService::criarCobImediata` retorna `PixCobResult` válido em sandbox Inter
- [ ] Pest cobre: criação cob biz=1 sucesso · cred ausente lança `BoletoCredentialMissingException` · cross-tenant biz=99 não usa cred de biz=1 · txid colisão é retried 1x
- [ ] Pest test biz=1 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) — NUNCA biz=4 (Larissa/ROTA LIVRE)
- [ ] Singleton registrado em ServiceProvider sem quebrar boot se classe ainda não existe (defensive `class_exists`)
- [ ] `config/services.php` chave `inter` documentada como fallback dev-only
- [ ] `.env - Copia.example` lista 7 envs `INTER_*` com placeholders comentados

**Risco.** Médio. Mitigação: token cache + circuit breaker já testados em US-RB-045; txid colisão extremely rara (UUIDv4); fallback de credencial estrito (cred tenant > config > exception).

**Out of scope:** PIX automático recorrente BCB (US futura — JRC), PIX saída (`/banking/v2/pagamento`), QR Code estático.

**Refs:** ADR 0093 Multi-tenant Tier 0 · skill `multi-tenant-patterns` Tier A · `Modules/RecurringBilling/Services/Inter/InterBankingClient.php` (US-RB-045).

### US-RB-051 · Inter PJ — webhook PIX receiver (CYCLE-06 G1 wiring Martinho)

> owner: wagner · sprint: cycle-06 · priority: p0 · estimate: 5h · status: done · type: story · origin: cycle-06-martinho-cacambas-2026-05-16
> blocked_by: US-RB-050

**Implementado em:** `Modules/RecurringBilling/Http/Controllers/InterWebhookController.php` · `Modules/RecurringBilling/Jobs/ProcessInterWebhookJob.php` · `Modules/RecurringBilling/Routes/web.php` · `Modules/RecurringBilling/Tests/Feature/InterWebhookControllerTest.php` · verificado@176f9bc (2026-07-01) — endpoint público POST /webhooks/inter/pix/{businessId} (throttle 60/1) valida secret via hash_equals (401 mismatch) + idempotência pg_webhook_events (endToEndId) + 200 imediato + dispatch ProcessInterWebhookJob → InvoicePaid

**Contexto.** Complemento de US-RB-050 — sem receiver, cobrança imediata fica órfã (Service emite QR mas nunca sabe que cliente pagou). Caminho canônico Inter: webhook `pix.recebido` em endpoint HTTPS público com mTLS + HMAC.

**Escopo backend:**
- Endpoint público `POST /webhooks/inter/pix/{business_id}` (rota web group, fora do middleware `auth`)
- Controller `Modules/RecurringBilling/Http/Controllers/InterWebhookController.php::pix()`:
  1. Validar `business_id` existe (404 se não)
  2. Validar HMAC SHA-256 do raw body contra `BoletoCredential.config_json.webhook_hmac` (gateway=inter, business_id=path) ANTES de qualquer parse — assinatura inválida → 401 com log `[REDACTED]`
  3. Idempotência via `pg_webhook_events` (UNIQUE `gateway+event_id`) — `event_id = endToEndId` do payload PIX
  4. Resposta 200 imediata (Inter exige ≤10s)
  5. Dispatch `ProcessInterWebhookJob` na fila `rb_webhooks`
- `ProcessInterWebhookJob`:
  - Parsa array `pix[]` do payload (Inter envia batch até 1000 PIX)
  - Pra cada item: localiza `pg_charge_attempts.txid` → atualiza `status=pago` + `paid_at` → dispara `Modules\RecurringBilling\Events\InvoicePaid`
  - Listener `BaixarTituloOnInvoicePaidListener` (já existe pro Asaas, US-RB-044) baixa título Financeiro + dispara NFe automática via `NfeBrasil`

**Acceptance criteria:**
- [ ] Endpoint registrado em `Routes/web.php` web group SEM middleware `auth` — apenas `web` + `throttle:60,1` (anti-DDOS)
- [ ] HMAC mismatch retorna 401 + log estruturado sem payload (apenas hash 8 chars + business_id)
- [ ] Pest cobre: webhook válido dispara `InvoicePaid` · HMAC inválido 401 · `business_id` 404 · duplicado mesmo endToEndId ignorado · cross-tenant biz=99 não vê event de biz=1
- [ ] Pest test biz=1 (ADR 0101) — NUNCA biz=4
- [ ] `InvoicePaid` event reusa pattern Asaas — listener único trata ambos gateways
- [ ] Idempotência: 2× POST mesmo payload grava 1× em `pg_charge_attempts` e dispara 1× evento
- [ ] Configurar webhook URL no Inter via API durante onboarding credencial (`PUT /webhooks/pix`)

**Risco.** ALTO porque é endpoint público + dinheiro real. Mitigação Tier 0: HMAC obrigatório · idempotência forte · resposta 200 mesmo se payload órfão (evita Inter ficar reenviando) · log `[REDACTED]` (PII LGPD) · Pest cenários adversariais explícitos.

**Out of scope:** PIX automático JRC, PIX saída, dashboards de webhooks (futuro UI sob `recurringbilling.webhooks.view` perm — US-RB-049).

**Refs:** ADR 0093 Multi-tenant Tier 0 · ADR 0094 §6 · pattern `Modules/RecurringBilling/Http/Controllers/AsaasWebhookController.php` · tabela `pg_webhook_events` (idempotência shared) · US-RB-044 listener NFe automática boleto pago (reusado).

### US-RB-052 · Ativar gateway nas 109 assinaturas com gateway=NULL (cobranças dormentes)

> owner: — · priority: p1 · estimate: 8h · status: todo · type: story
> blocked_by: —
> parent_plan: recurring-billing-gateway-ativacao

**Implementado em:** `Modules/RecurringBilling/Services/GatewayBackfillService.php` + `Modules/RecurringBilling/Console/Commands/BackfillGatewayCommand.php` (`rb:backfill-gateway {biz}` — default dry-run) + `Modules/RecurringBilling/Tests/Feature/GatewayBackfillServiceTest.php` — **parcial**: atribuição de gateway construída (fail-closed: exige credencial ativa; Cora 403 sem driver = bloqueada); execução real + reativação da régua de emissão pendem decisão [W] (Tier 0 dinheiro) — hoje nenhum código de produção cria ChargeAttempt (gap catalogado no PR do backfill)

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105):** receita parada — 109 assinaturas ativas com `gateway=NULL` não geram cobrança (36 C6 + 51 Inter + 22 Cora). Maior ROI do batch.

**DoD:**
- Mapear as 109 subscriptions `gateway IS NULL` por business + provider preferencial.
- Definir/atribuir gateway por assinatura (idempotente, com audit log).
- Re-ativar a régua de cobrança das assinaturas destravadas; smoke de 1 ciclo.
- Multi-tenant Tier 0: filtro `business_id` em toda query (ADR 0093).

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-RB-055 · Aplicar recalibração de pricing (setup · trial · anual) — 3 ajustes

> owner: — · priority: p1 · estimate: 3h · status: todo · type: story
> blocked_by: —
> parent_plan: pricing-3-ajustes-urgentes

**Implementado em:** _pendente_ — recalibração de pricing (setup · trial · anual) ainda não aplicada nos planos/config; nenhuma mudança de código/dados correspondente

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105):** Martinho com compra ativa (cliente pagante) — recalibração de pricing em 3 eixos: setup, trial, anual.

**⚠️ Módulo:** colocado em RecurringBilling (domínio de Plans/assinatura — US-RB-001/043). Se a recalibração for da **página pública de pricing** (cycle "pricing público no ar"), re-homear pra Grow/Infra.

**DoD:**
- Aplicar os 3 ajustes (setup, trial, anual) nos planos/config.
- Validar reflexo na cobrança e na página de pricing.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)

### US-RB-056 · Unificar as 3 implementações de "próximo vencimento" (NoOverflow/EN vs Overflow/PT) — REGRA MESTRE

> owner: — · priority: p1 · estimate: 6h · status: todo · type: story
> blocked_by: —

**Origem:** dente de cálculo do RecurringBilling (Onda 1.4, PR #3737, TEST-ONLY). O teste `tests/Feature/Calculo/CalculoRecurringBillingTest.php` **caracteriza** (não corrige) uma divergência entre TRÊS cópias da conta de "próximo vencimento" — análogo do `getTotalPaid ≠ getTotalAmountPaid` da Onda 1.4.

**As 3 implementações divergentes:**
- **A** `InvoiceGeneratorService::avancarCiclo` — enum **EN** (`monthly/quarterly/semiannual/yearly/custom`), **NoOverflow**, default = +1 mês.
- **B** `AssinaturaService::calcularProximoVencimento` — enum **PT** (`mensal/trimestral/semestral/anual`), **Overflow**, default = **NO-OP** (retorna a base).
- **C** `AssinaturaCobrancaService::recalcularProximaCobranca` — igual ao B (PT, Overflow, default NO-OP). O docblock do B diz "helper compartilhado com C", mas são **duplicados**.

**Vocabulário SPLIT no storage:** `rb_plans.ciclo` é enum **EN**; `rb_subscriptions.metadata['ciclo']` (path de edição FIN-004) é **PT**.

**Divergências travadas pelo teste (comportamento ATUAL):**
1. Anchor dia-31: A(`2026-01-31`,monthly) = `2026-02-28` (fica em fev) vs B(`2026-01-31`,mensal) transborda pra **março** → data de cobrança no mês errado pelo path de edição.
2. Default cruzado: A tolera termo PT (avança); B/C recebem termo EN → default **NO-OP** → `next_due_date` **congela** = re-cobrança presa (bug latente; pode disparar via FIN-004 se o vocabulário cruzar).

**Fonte de verdade decidida no teste:** **A** (`InvoiceGeneratorService`, NoOverflow) — é o job que fatura de verdade. A unificação deve alinhar B/C a A (uma função só, NoOverflow, vocabulário único).

**⛔ REGRA MESTRE (memory/proibicoes.md §"CÁLCULO DE VALOR ou ESTOQUE"):** é mudança de **data de cobrança em prod**. Antes de mergear:
1. Dupla confirmação por 2 caminhos independentes com números concretos.
2. Tabela **antes→depois** das assinaturas/datas afetadas (dry-run sobre biz reais com anchor dia-31 / ciclo editado) apresentada ao [W].
3. Aprovação explícita [W].

**Nota de execução:** o teste #3737 já trava a divergência atual; ao unificar, o golden `divergencia_anchor_31_*` / `divergencia_vocabulario_cruzado_*` vai virar RED **conscientemente** — atualizar as asserções junto, documentando a decisão (é o sinal esperado, não regressão).

**Refs:** PR #3737 · Onda 1.4 (`memory/requisitos/_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md`) · ADR 0093 · ADR 0101.
