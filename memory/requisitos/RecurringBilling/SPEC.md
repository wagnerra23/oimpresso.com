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

**Como** Gestor
**Quero** criar régua com passos (`step_1: D+1 email`, `step_2: D+3 SMS`, `step_3: D+7 WhatsApp + bloqueio`)
**Para** automatizar recuperação sem ligar 1-a-1

**DoD:**
- [ ] FormRequest valida steps com `delay_days`, `action` enum, `template`
- [ ] Test Feature: criar régua + ativar + isolamento

### US-RB-031 · Disparar régua quando cobrança falha

> **Área:** Campaigns
> **Listener:** `Modules\Dunning\Listeners\StartCampaignOnInvoiceFailed`

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
**Testado em:** `InvoiceGeneratorIdempotenciaTest`.

### R-RB-004 · Webhook idempotente por event_id
```gherkin
Dado um webhook Asaas chega com event_id = X
Quando chega de novo (at-least-once)
Então o segundo é descartado (200 OK sem efeito)
```
**Implementação:** UNIQUE `(provider, event_id)` em `pg_webhook_events`.
**Testado em:** `WebhookIdempotenciaTest`.

### R-RB-005 · Charge attempt idempotente
```gherkin
Dado uma fatura com tentativa em curso
Quando outro charge attempt chega com mesmo (invoice_id, attempt_number)
Então é descartado
```
**Implementação:** UNIQUE em `pg_charge_attempts`.
**Testado em:** `ChargeIdempotenciaTest`.

### R-RB-006 · Reajuste no aniversário
```gherkin
Dado um contrato criado em 2025-04 com plano `indice_reajuste = IPCA`
Quando gera-se a fatura de 2026-04 (12º ciclo)
Então o valor é reajustado conforme IPCA acumulado
E aparece em `rb_proration_events` como tipo `reajuste_aniversario`
```
**Implementação:** `ReajusteService` consulta IPCA via API BCB; cache 24h.
**Testado em:** `ReajusteAniversarioTest`.

### R-RB-007 · NFSe assíncrona não trava billing
```gherkin
Dado uma fatura paga
Quando o NFSe provider está fora
Então a fatura permanece como paid
E NFSe entra em fila de retentativa
E nenhuma cobrança seguinte é bloqueada
```
**Implementação:** Sub-módulo NFSe em queue separada; falhas viram `nfse_emissoes.status=failed` com retentativa 5x.
**Testado em:** `NfseAssincronaTest`.

### R-RB-008 · Cancelamento `fim_ciclo` cobra ciclo restante
```gherkin
Dado um contrato cancelado tipo fim_ciclo em 2026-04-15
E ciclo atual termina em 2026-04-30
Quando geramos fatura
Então a fatura de 2026-04 é gerada normalmente (cliente paga até 30/04)
E em 01/05, status muda pra canceled (sem nova fatura)
```
**Testado em:** `CancelamentoFimCicloTest`.

### R-RB-009 · Hard decline = não retentar
```gherkin
Dado um charge attempt retornou hard decline (cartão cancelado, ex: cStat 'card_canceled')
Quando SmartRetryScheduler é chamado
Então não agenda retry
E dispara InvoiceFailed para Dunning
```
**Implementação:** Switch em `decline_type` retornado pelo adapter; mapping per-provider.
**Testado em:** `HardDeclineNoRetryTest`.

### R-RB-010 · Pix Automático autorização expirada
```gherkin
Dado uma autorização Pix Automático com status `expired`
Quando tentamos cobrar
Então charge attempt falha imediatamente (sem chamar PSP)
E dispara InvoiceFailed
```
**Testado em:** `PixAutorizacaoExpiradaTest`.

### R-RB-011 · Pagamento durante campanha encerra dunning
```gherkin
Dado uma campanha de dunning ativa
Quando InvoicePaid é disparado
Então campanha vira status=resolved
E steps futuros são cancelados
```
**Testado em:** `DunningResolvidaPorPagamentoTest`.

### R-RB-012 · PCI compliance — não armazenar PAN/CVV
```gherkin
Dado o frontend captura cartão
Quando o servidor recebe request
Então NÃO armazena `pan` ou `cvv`
E só guarda `gateway_card_token`, `last_4`, `brand`
```
**Implementação:** FormRequest rejeita `pan`/`cvv` se chegarem (defesa em profundidade); checkout via iframe hosted ou redirecionamento.
**Testado em:** `PciNaoArmazenaPanTest`.

### R-RB-013 · Audit log Spatie em mutações críticas

```gherkin
Dado qualquer mutação em Plan, Contract, Invoice, ChargeAttempt, Authorization ou Campaign
Quando a mutação completa
Então existe row em activity_log com causer + subject + properties (valor, status, etc.)
```

**Implementação:** Trait `LogsActivity` em `Plan`, `Contract`, `Invoice`, `ChargeAttempt`, `Authorization`, `Campaign`.
**Testado em:** `Modules/RecurringBilling/Tests/Feature/AuditLogMutacoesTest` — 6 modelos × create/update/delete = 18 asserts.

### R-RB-014 · Take rate calculado só se gateway próprio
```gherkin
Dado uma cobrança via gateway próprio (oimpresso intermedia)
Quando InvoicePaid dispara
Então cria revenue_event com fee_calculado = min(valor * 0.008, 19.90)

Dado uma cobrança via merchant-of-record do cliente (gateway com credencial do cliente)
Então NÃO cria revenue_event (sem take rate)
```
**Implementação:** Verifica `pg_credentials.owner` em listener do `InvoicePaid`.
**Testado em:** `TakeRateMorVsGatewayTest`.

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

> owner: — · priority: p0 · estimate: 8h · status: todo · type: story · origin: capterra-inventario-2026-05-06 · capacidade: #1
> blocked_by: —

**Contexto.** CAPTERRA-INVENTARIO #1 classificou como 🟡 PARCIAL: drivers + UI existem, mas `Modules/RecurringBilling/Tests/` está vazia. Sem teste, qualquer mexida nova é bug em prod garantido.

**Acceptance criteria:**
- [ ] `Tests/Feature/InterDriverTest.php` — round-trip emitir + cancelar com sandbox response mockada (não chamar API real)
- [ ] `Tests/Feature/C6DriverTest.php` — geração local CNAB + nossoNumero + linha digitável válidos
- [ ] `Tests/Feature/AsaasDriverTest.php` — POST /payments mockado + parsing do response
- [ ] `Tests/Feature/BoletoServiceTest.php` — resolve driver correto por banco da credencial; `decryptConfig` roundtrip (ADR tech/0007)
- [ ] CI verde com os novos testes

### US-RB-041 · Test de retry idempotente do ProcessAsaasWebhookJob

> owner: — · priority: p0 · estimate: 3h · status: todo · type: story · origin: capterra-inventario-2026-05-06 · capacidade: #2
> blocked_by: —

**Contexto.** CAPTERRA-INVENTARIO #2 classificou 🟡 PARCIAL — tabela `pg_webhook_events` UNIQUE existe, mas sem teste cobrindo retry/replay. Webhook duplicado pelo Asaas (acontece em produção) sem cobertura de teste = cobrança duplicada esperando pra acontecer.

**Acceptance criteria:**
- [ ] `Tests/Feature/AsaasWebhookIdempotencyTest.php`
- [ ] 2 chamadas POST `/api/webhooks/asaas/{biz}` com mesmo `event_id` → segunda retorna 200 sem reprocessar
- [ ] `PAYMENT_RECEIVED` processado 2× não cria 2 `account_transactions` (insertOrIgnore funciona)
- [ ] `BALANCE_UPDATED` processado 2× não duplica `saldo_cached`
- [ ] Job falha no meio → retry roda completo sem dups (atomicidade)

### US-RB-042 · Completar cancelar() C6/Asaas + UI Cancelar título + audit log

> owner: — · priority: p0 · estimate: 6h · status: todo · type: story · origin: capterra-inventario-2026-05-06 · capacidade: #4
> blocked_by: US-RB-040

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

> owner: — · priority: p1 · estimate: 16h · status: todo · type: epic · origin: capterra-inventario-2026-05-06 · capacidade: #4-domínio
> blocked_by: —
> bloqueia: US-RB-001, US-RB-002, US-RB-003, US-RB-004, US-RB-005, US-RB-006, US-RB-011, US-RB-013

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

> owner: — · priority: p1 · estimate: 12h · status: todo · type: story · origin: capterra-inventario-2026-05-06 · capacidade: #6 (diferencial vertical)
> blocked_by: US-RB-ESC3

**Contexto.** CAPTERRA-INVENTARIO #6 ❌ AUSENTE — **diferencial vertical gráfica**. Gateway de boleto é commodity (Iugu/Asaas/Vindi/Pagar.me têm). "Boleto pago → NFe modelo 55 emitida automaticamente sem clique humano" é diferencial do oimpresso. Larissa (ROTA LIVRE) pediu há tempos. Event `InvoicePaid` JÁ existe em `Modules/RecurringBilling/Events/InvoicePaid.php` — falta o listener em NfeBrasil consumindo.

**Acceptance criteria:**
- [ ] `Modules/NfeBrasil/Listeners/EmitirNFeAoReceberPagamento.php` registrado em `EventServiceProvider`
- [ ] Listener resolve produto/serviço da fatura → mapeia pra item de NFe (CFOP, NCM, alíquotas)
- [ ] Carrega certificado A1 do business via `NfeCertificadoService`
- [ ] Chama `nfephp-org/sped-nfe` → autoriza SEFAZ
- [ ] Renderiza DANFE (PDF)
- [ ] Envia e-mail pro pagador com DANFE anexado
- [ ] Log estruturado de cada passo (gen_ai.* OpenTelemetry pattern, ADR 0049)
- [ ] Falha de SEFAZ não derruba pagamento — retry job separado em fila `nfe_retry`
- [ ] Teste Pest: dispara `InvoicePaid` → assert NFe criada com status=autorizada
- [ ] **Prod-evidence:** ≥1 NFe modelo 55 autorizada via esse fluxo (ROTA LIVRE biz=4)

## 8. Referências

- `_Ideias/CobrancaRecorrente/evidencias/conversa-claude-2026-04-mobile.md`
- Lago (open source): https://github.com/getlago/lago
- Kill Bill: https://killbill.io/
- BCB Pix Automático docs (jornadas JRC)
- Stripe smart retries (referência ML)
- Auto-memória: `reference_ultimatepos_integracao.md`, `reference_db_schema.md`
