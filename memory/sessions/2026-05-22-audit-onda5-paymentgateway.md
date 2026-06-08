---
data: 2026-05-22
tipo: audit
escopo: PaymentGateway Onda 5 SIMPLIFICADA
blueprint: memory/requisitos/PaymentGateway/PLANO-ONDA5-SIMPLIFICADA.md
pr_principal: '#1148'
commit: 3c2d00cc4
status_geral: 'CÓDIGO 100% MERGEADO — falta ADR filho 0170-onda5-simplificada (status do ADR pai 0170 ainda "proposto"), pré-condições prod manuais e smoke real biz=1.'
---

# Auditoria Onda 5 PaymentGateway — 2026-05-22

## Sumário executivo

- ✅ Itens código feitos: **6/6** (todos arquivos esperados existem com implementação completa)
- ✅ Service Providers: **2/2** registrados (Superadmin + Financeiro)
- ✅ Pest: **4 arquivos** novos (3 listeners + 1 command) + integrações
- 🟡 Parciais: **0** (a view foi entregue como `partials/pay_paymentgateway_pix_automatico.blade.php` em vez de `pay_paymentgateway.blade.php` — divergência **intencional** vs blueprint pra seguir o padrão `@includeIf('superadmin::subscription.partials.pay_'.$k)` já existente em `pay.blade.php:83`. Decisão correta.)
- ❌ ADR filho **0170-onda5-simplificada**: **NÃO criado** (blueprint §10 prevê — ADR pai 0170 ainda `proposto`)
- ❌ Smoke real biz=1: pendente Wagner (dogfooding)
- 🟡 Pré-condições prod (cadastros manuais + BCB): pendentes Wagner (verificáveis só via SSH/DB)
- **Esforço restante código:** ~0 LOC. Esforço restante humano-limitado: ~1h ADR filho + 8 pré-condições + 14d observação canary.
- **PR principal:** [#1148](https://github.com/oimpresso/oimpresso.com/pull/1148) commit `3c2d00cc4` mergeado 2026-05-19 15:27 BRT.

## Detalhamento por item

### 4.1 OnCobrancaPagaUpdateSubscription — ✅
- Arquivo: `Modules/Superadmin/Listeners/OnCobrancaPagaUpdateSubscription.php` — **existe**
- Implementação vs blueprint: **completa**. Pattern PesaPalController 1:1, filtro `origem_type='subscription_license'`, idempotência (`status === 'approved'` → return), `withoutGlobalScopes()` cross-tenant, log estruturado, `calcPackageDates()` inlined.
- Pest: `Modules/Superadmin/Tests/Feature/OnCobrancaPagaUpdateSubscriptionTest.php` — 4 it (happy / filtro / desbloqueio / idempotência)
- Observação: comentário canônico aponta cross-tenant Wagner-only (§30 Subscription.php). Logs sem PII.

### 4.2 OnCobrancaVencidaBloqueaSubscription — ✅
- Arquivo: `Modules/Superadmin/Listeners/OnCobrancaVencidaBloqueaSubscription.php` — **existe**
- Implementação vs blueprint: **completa**. Bloqueio condicional (`!$business->officeimpresso_bloqueado`), idempotência (`status === 'declined'` → return), log inclui `dias_vencido` + `vencimento_original`.
- Pest: `Modules/Superadmin/Tests/Feature/OnCobrancaVencidaBloqueaSubscriptionTest.php` — 2 it (happy / filtro)
- Observação: confia no smart retry RB (3 retentativas) antes do `CobrancaVencida` chegar.

### 4.3 Branch `paymentgateway_pix_automatico` em SubscriptionController — ✅
- Path: `Modules/Superadmin/Http/Controllers/SubscriptionController.php`
- Linhas-chave: 217 (detect), 272 (`confirm_paymentgateway`), 292 (`_add_subscription` chamado com paid_via='paymentgateway_pix_automatico'), 360 (`_log_emergency_redacted`).
- Implementação vs blueprint: **completa + reforçada**. Adicional ao blueprint:
  - `BaseController::_payment_gateways()` (linha 60) injeta gateway condicional a credencial BCB ativa + ContaBancaria vinculada (helper `isPaymentGatewayPixAutomaticoConfigured()`)
  - Resolve credencial via canon `payment_gateway_credentials.conta_bancaria_id` (Wagner 2026-05-19 inverteu direção FK; FK reverso `fin_contas_bancarias.rb_gateway_credential_id` fica como fallback)
  - `idempotencyKey: 'onda5-sub-' . $subscription->id`
- Pest: testado indiretamente via `OnCobrancaPaga*Test` (Subscription transitions); flow controller-level testado manualmente.

### 4.4 Form view pay_paymentgateway — ✅ (divergência intencional)
- Path: `Modules/Superadmin/Resources/views/subscription/partials/pay_paymentgateway_pix_automatico.blade.php` — **existe**
- Blueprint esperava `pay_paymentgateway.blade.php` no nível raiz; entregue como **partial nomeado pela chave do gateway** seguindo o padrão dinâmico existente em `pay.blade.php:83`:
  ```php
  $view = 'superadmin::subscription.partials.pay_'.$k;
  @includeIf($view)
  ```
- Decisão correta (consistente com `pay_pesapal/pay_stripe/...` legacy). Conteúdo: form com csrf + button "PIX Automático BCB" + helper text explicando mandato 7d.

### 4.5 Comando paymentgateway:register-permissions — ✅
- Arquivo: `Modules/PaymentGateway/Console/Commands/RegisterPermissionsCommand.php` — **existe**
- Implementação vs blueprint: **completa + superior**. Espelho fiel de `whatsapp:register-permissions` (PR #665) com:
  - `--dry-run` (preview sem persistir)
  - `--business=all` (rollout multi-tenant) ou `--business={id}`
  - Lookup `Admin#{biz}` filtra por `business_id` (UltimatePOS pattern, não só `name`)
  - `firstOrCreate` + `forgetCachedPermissions()` Spatie
  - Log estruturado `[paymentgateway.register_permissions.completed]`
- Pest: `Modules/PaymentGateway/Tests/Feature/RegisterPermissionsCommandTest.php` — 4 it (dry-run / apply / idempotência / input inválido)
- Registrado em `PaymentGatewayServiceProvider::registerCommands()` (confirmado no commit message).

### 5.1 OnCobrancaPagaCreateFinanceiroTitulo (Financeiro) — ✅
- Arquivo: `Modules/Financeiro/Listeners/OnCobrancaPagaCreateFinanceiroTitulo.php` — **existe**
- Implementação vs blueprint: **completa + superior**. Adicional ao blueprint:
  - Escopo conservador biz=1 (`if ($event->businessId !== 1) return`)
  - Idempotência via lookup (`business_id=1`, `origem='manual'`, `origem_id=cobrancaId`)
  - Resolver de ContaBancaria em **3 camadas** (canon `payment_gateway_credentials.conta_bancaria_id` → legacy FK reverso → primeira ativa biz=1)
  - Fallback graceful: se nenhuma conta resolve, cria Titulo SEM Baixa (status=aberto) + log warning (Wagner reconcilia)
  - Idempotency key UUID determinístico (`md5('paymentgateway.onda5.cobranca-' . id)`)
  - `metadata` JSON com source/cobranca_id/origem/tipo/forma_pagamento (audit trail)
  - `meio_pagamento` map (pix / boleto / cartao_credito / outro)
- Pest: `Modules/Financeiro/Tests/Feature/OnCobrancaPagaCreateFinanceiroTituloTest.php` — 3 it (happy biz=1 / skip biz!=1 / idempotência)
- Observação: usa `origem='manual'` pra não migrar enum `fin_titulos.origem` em prod — `metadata.source='paymentgateway_cobranca'` preserva trail.

## Registros nos Service Providers

| Provider | Listener | Status |
|---|---|---|
| `SuperadminServiceProvider::boot()` | `Event::listen(CobrancaPaga::class, OnCobrancaPagaUpdateSubscription)` | ✅ linha 121 |
| `SuperadminServiceProvider::boot()` | `Event::listen(CobrancaVencida::class, OnCobrancaVencidaBloqueaSubscription)` | ✅ linha 122 |
| `FinanceiroServiceProvider::boot()` | `Event::listen(CobrancaPaga::class, OnCobrancaPagaCreateFinanceiroTitulo)` | ✅ linha 49 |

Ambos providers usam guard estático `$paymentgatewayListenersRegistered` pra prevenir double-register no boot duplo do nWidart.

## Itens bônus não previstos no blueprint mas mergeados no PR #1148

1. **`BusinessAutoSubscriptionObserver`** (`Modules/Superadmin/Observers/BusinessAutoSubscriptionObserver.php`) — Onda 5.B: `Business::created` → cria Subscription waiting com Package default + trial. Cobre UI Superadmin + API Delphi. Configurável via System property `default_saas_package_id` (no-op graceful se ausente).
2. **`EmitTrialExpiredCobrancasCommand`** (`Modules/PaymentGateway/Console/Commands/`) — cron diário 08:00 BRT: encontra Subscriptions waiting com trial expirado e emite cobrança PIX Automático.
3. **`MigrateCredentialsCommand`** — migração de credenciais legacy.
4. **Pest extra:** `BusinessAutoSubscriptionObserverTest` + `EmitTrialExpiredCobrancasCommandTest` + `Onda5CuradoriaR1Test` (Financeiro).
5. **Schedule registrado** em `SuperadminServiceProvider::registerScheduleCommands()` (linha 88) — `paymentgateway:emit-trial-expired` daily 08:00 (env=live only).

## Pré-condições §3 (8 itens)

| # | Pré-condição | Verificável? | Estado |
|---|---|---|---|
| 1 | Ondas 1-4 PaymentGateway em prod | git (✅ PRs #1125-#1136) | ✅ feito |
| 2 | Migration `payment_gateway_credentials` rodada em prod | só via SSH Hostinger: `php artisan migrate:status` | ⏳ Wagner confirma |
| 3 | `BcbPixDriver` smoke validado sandbox | Pest `BcbPixDriverTest` existe; smoke real homologação BCB ainda não validado | ⏳ Wagner valida |
| 4 | Conta bancária Wagner em `fin_contas_bancarias` (biz=1, ativo) | DB prod: `SELECT * FROM fin_contas_bancarias WHERE business_id=1 AND ativo_para_boleto=true` | ⏳ Wagner cadastra |
| 5 | Credencial BCB Pix Automático em `payment_gateway_credentials` (biz=1, `gateway_key='bcb_pix'`, `conta_bancaria_id` preenchido) | DB prod query | ⏳ Wagner cadastra via UI |
| 6 | FK fin_contas_bancarias.payment_gateway_credential_id | **REMOVIDO** pelo Wagner 2026-05-19 (direção invertida — canon `payment_gateway_credentials.conta_bancaria_id` já no wizard step 3) | n/a (fallback legacy mantido em código) |
| 7 | Package "Premium" em `superadmin.packages` | DB prod: `SELECT * FROM packages WHERE name LIKE 'Premium%' AND is_active=1` | ⏳ Wagner cadastra |
| 8 | Homologação BCB (CNPJ Wagner habilitado RECEBEDOR PIX Automático Resolução 380/2024) | Banco / contrato PJ | ⏳ Wagner banco 1-3d |

**Veredito:** 1 verificada via git, 5 pendem cadastro manual Wagner + 1 pende banco. Nenhuma é blocker de código.

## ADR filho

- Path esperado: `memory/decisions/0170-onda5-simplificada-*.md`
- Estado: **NÃO existe**
- ADR pai: `memory/decisions/0170-paymentgateway-extracao-camada-cobranca.md` ainda com `status: proposto` (linha 6 do frontmatter)
- **Gap:** blueprint §10 prevê criação de ADR filho `0170-onda5-simplificada — Dogfooding SaaS via gateway adicional` com `status: aceito`, `related: [0017, 0093, 0105, 0170]`. **Pendente.**

## Tasks MCP relacionadas

- **SPEC.md de PaymentGateway: NÃO existe** em `memory/requisitos/PaymentGateway/` — só `PLANO-ONDA5-SIMPLIFICADA.md` e `RUNBOOK-settings-gateways.md`.
- US no MCP: não verifiquei via tool MCP `tasks-list` (audit read-only). Recomendo `tasks-list module:PaymentGateway` se quiser cross-check com backlog do MCP.

## Critérios de aceite §9 (8 itens)

| # | Critério | Estado |
|---|---|---|
| 1 | Wagner cobra ele mesmo biz=1 (dogfooding 1ª vez) | ❌ pendente |
| 2 | Wagner cobra Larissa biz=4 ciclo end-to-end | ❌ pendente |
| 3 | Expira 1 cobrança propositalmente → block + Delphi rejeita oauth | ❌ pendente |
| 4 | FinTitulo + Baixa aparecem em /financeiro biz=1 | ❌ pendente (depende de critério 1) |
| 5 | `php artisan paymentgateway:register-permissions --business=all` rodado prod | ❌ pendente Wagner SSH |
| 6 | Pest cobertura (listeners + command + race) | ✅ feito (4 Pest novos no PR + integration test) |
| 7 | `DelphiOImpressoContractTest` 9 guards ainda verdes | ⏳ não verificado nesta auditoria — recomendado rodar `php artisan test --filter=DelphiOImpressoContract` |
| 8 | OTel spans + health-check métricas `paymentgateway_onda5.*` | ⏳ não verificado — grep `jana:health-check` adicionou métricas? |

## Veredito objetivo

**Código mergeado: 100% do blueprint §4 + §5 + listeners bônus (BusinessAutoSubscriptionObserver + EmitTrialExpired).**

PR #1148 entrega tudo. O que falta NÃO é código — é:
1. ADR filho 0170-onda5-simplificada formalizando a decisão (governance — 1h).
2. Wagner cadastrar 5 pré-condições prod (ContaBancaria + Credencial + Package + register-permissions + homologação BCB).
3. Smoke real biz=1 (Wagner paga ele mesmo) — primeiro teste real do ciclo end-to-end.
4. Verificar critérios §9.7 (DelphiOImpressoContractTest) e §9.8 (OTel + health-check) — podem já estar verdes, só não checados aqui.

## Próximos passos sugeridos

1. **Criar ADR filho `0170-onda5-simplificada-dogfooding-saas`** com `status: aceito`, `supersedes: nada`, `amends: 0170`, `related: [0017, 0093, 0105, 0170]`. Texto base já no blueprint §10. (~1h, governance débit.)
2. **Wagner cadastrar pré-condições prod** (8 → 5 ativas + 1 banco + 2 já feitas). Sequência: Package → ContaBancaria → Credencial BCB → register-permissions → homologação BCB (paralelo).
3. **Wagner smoke biz=1 (ele mesmo paga ele mesmo)** — primeiro dogfooding. Validar criterios §9.1 + §9.4 num único ciclo.
4. **Rodar `php artisan test --filter=DelphiOImpressoContract`** local + prod pra confirmar guard wire Delphi intacto.
5. **Verificar/adicionar OTel spans + health-check `paymentgateway_onda5.*`** (§9.8) — se não tiver, abrir task delta pequena.
6. **Atualizar `PLANO-ONDA5-SIMPLIFICADA.md` status frontmatter** de `plano-aprovado-aguardando-execucao` → `executado-aguardando-smoke` ou similar.
7. **Após smoke biz=1 sucesso:** marcar critério §9.1 ✅ → cycle aprovação Larissa biz=4 (critério §9.2).
8. **(Opcional) Criar SPEC.md** em `memory/requisitos/PaymentGateway/` consolidando US por onda (já existe blueprint, mas SPEC.md canônico padrão dos outros módulos não existe pra PaymentGateway).

---

**Auditor:** Claude Code  
**Data:** 2026-05-22  
**Modo:** read-only (apenas escrita deste arquivo de auditoria)  
**Refs:** `memory/requisitos/PaymentGateway/PLANO-ONDA5-SIMPLIFICADA.md`, commit `3c2d00cc4`, PR #1148
