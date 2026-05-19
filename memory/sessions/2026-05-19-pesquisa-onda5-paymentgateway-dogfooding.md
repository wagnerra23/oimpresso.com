---
data: 2026-05-19
tipo: pesquisa
modulo: PaymentGateway
adr_origem: 0170
status: pesquisa-apenas-nao-codificar
---

# Onda 5 PaymentGateway — pesquisa introspectiva (não codificar)

> Wagner pediu pesquisa, não execução. Esta sessão é dossiê pra Wagner decidir SE/QUANDO a Onda 5 entra no roadmap. Nenhum código foi tocado.

## 1. Definição canônica (do ADR 0170)

| Atributo | Valor |
|---|---|
| Nome | **Dogfooding Superadmin** |
| Risco | Médio — afeta cobrança SaaS Wagner ↔ tenants |
| Reversível | Sim, manter `Superadmin::Subscription` source-of-truth via flag |
| Posição no roadmap | 5ª de 6 ondas (0 docs / 1 esqueleto / 2 credentials / 3 webhooks / 4 domínio / **5 dogfooding** / 6 cleanup) |

**Citação literal do ADR 0170 (linha 296):**
> "`Plan` 'SaaS Oimpresso Premium' em biz=1. Tenants viram Contact em biz=1. `Superadmin::Subscription` vira projection. PesaPal deprecated"

## 2. O que muda conceitualmente

### Modelo atual (legado UltimatePOS)

```
Modules/Superadmin/Entities/Subscription (tabela: subscriptions)
  ↑ source-of-truth de "qual tenant está com mensalidade em dia"
  ↑ alimentada por PesaPalController.pesaPalPaymentConfirmation()
  ↑ status: approved | waiting | declined
  ↑ dates: start_date, end_date, trial_end_date
  ↑ Spatie LogsActivity (LGPD D7.b — CC Art. 206 prescrição 10 anos)
```

### Modelo proposto (Onda 5)

```
Wagner (biz=1) usa o próprio ERP pra cobrar tenants

  RecurringBilling::Plan "SaaS Oimpresso Premium R$ 99,90/mês"
       ↓ (Wagner cadastra 1x manual em /recurring-billing/plans dentro de biz=1)
  RecurringBilling::Subscription(business_id=1, contact_id=<tenant_as_contact>, plan_id=<saas>)
       ↓ (1 por tenant ativo)
  RecurringBilling Cron → Invoice mensal
       ↓
  PaymentGateway emite Cobrança (PIX Automático BCB)
       ↓ (tenant paga)
  Webhook BCB → evento CobrancaPaga(origem_type='subscription_license', target_business_id=<tenant>)
       ↓
  SuperadminLicenseObserver (handler novo em Modules/Superadmin)
       ↓ atualiza business.subscription_end_date += 1 mês
  Superadmin::Subscription = view materializada (projection)
       ↓ read-side: dashboard cross-tenant Wagner + auditoria LGPD D7.b
```

**Tenant = entidade dupla:**
- É `Business` (próprio ERP isolado Tier 0, ADR 0093 IRREVOGÁVEL)
- E é `Contact` dentro de biz=1 (representa "este tenant é cliente SaaS do Wagner")

**Bônus dogfooding:** Wagner descobre bugs de Plan/Invoice/Cobrança ANTES do cliente externo porque é cliente do próprio produto.

## 3. Estado atual (inventário pesquisado 2026-05-19)

### Já existe (✅ pronto pra reusar)

| Item | Path | Estado |
|---|---|---|
| `RecurringBilling::Plan` | [Modules/RecurringBilling/Models/Plan.php](../../Modules/RecurringBilling/Models/Plan.php) | Tabela `rb_plans`, `business_id` scope, Spatie LogsActivity, fillables completos (valor/ciclo/trial/fiscal_type) |
| `RecurringBilling::Subscription` | [Modules/RecurringBilling/Models/Subscription.php](../../Modules/RecurringBilling/Models/Subscription.php) | ⚠️ Mesmo nome de `Superadmin::Subscription` — ADR 0170 prevê rename pra `Assinatura` (não confirmado se já foi feito) |
| `RecurringBilling::Invoice` | [Modules/RecurringBilling/Models/Invoice.php](../../Modules/RecurringBilling/Models/Invoice.php) | Existe |
| `Superadmin::Subscription` | [Modules/Superadmin/Entities/Subscription.php:79](../../Modules/Superadmin/Entities/Subscription.php#L79) | Source-of-truth atual. Métodos `active_subscription` / `upcoming_subscriptions`. Spatie LogsActivity `useLogName('superadmin.subscription')` |
| `PesaPalController` (Superadmin) | [Modules/Superadmin/Http/Controllers/PesaPalController.php](../../Modules/Superadmin/Http/Controllers/PesaPalController.php) | UltimatePOS legacy. 39 linhas. Lê `Subscription::where('payment_transaction_id')`, marca approved/waiting |
| `PesaPalController` (app/) | [app/Http/Controllers/PesaPalController.php](../../app/Http/Controllers/PesaPalController.php) | Proxy thin (15 linhas) que delega pro Modules/Superadmin |
| `SubscriptionController` (Superadmin) | [Modules/Superadmin/Http/Controllers/SubscriptionController.php](../../Modules/Superadmin/Http/Controllers/SubscriptionController.php) | CRUD UI legacy + 5 payment gateways (Pesapal/Razorpay/Stripe/PayPal/Paystack) — herança UltimatePOS |
| `CobrancaPaga` event | [Modules/PaymentGateway/Events/CobrancaPaga.php](../../Modules/PaymentGateway/Events/CobrancaPaga.php) | ✅ Existe (Onda 4) |
| `BcbPixDriver` | [Modules/PaymentGateway/Services/Drivers/BcbPixDriver.php](../../Modules/PaymentGateway/Services/Drivers/BcbPixDriver.php) | ✅ Existe (Onda 4d.1, PR #1134) — emite PIX Automático recv (mandato) |

### Não existe ainda (❌ código novo da Onda 5)

| Item | Onde | Estimativa LOC |
|---|---|---|
| `SuperadminLicenseObserver` | `Modules/Superadmin/Listeners/SuperadminLicenseObserver.php` | ~80 |
| Backfill command: tenant→Contact em biz=1 | `Modules/Superadmin/Console/Commands/BackfillTenantContactsCommand.php` | ~150 |
| Migration: opcional novo campo `business.saas_contact_id` (FK) ou usar `Contact.business_id=1 + Contact.metadata.tenant_business_id` | `Modules/Superadmin/Database/Migrations/...` | ~30 |
| Refactor `Superadmin::Subscription` pra projection mode (sem source-of-truth direto) | mesmo arquivo | ~50 (rename methods + adicionar `rb_subscription_id` FK) |
| Migration histórica: backfill `Superadmin::Subscription` → `RecurringBilling::Subscription` pra tenants ativos | one-shot CLI | ~100 |
| Wave/feature flag `feature.paymentgateway_onda5_dogfooding` | `config/features.php` + getter | ~20 |
| Pest: `CobrancaPagaListenerSuperadminTest`, `TenantContactBackfillCommandTest`, `SubscriptionProjectionConsistencyTest` | `Modules/Superadmin/Tests/` | ~250 |
| **Total código novo** | — | **~680 LOC** |

## 4. Pré-condições (devem estar verdes antes de Onda 5)

| # | Pré-condição | Estado pesquisado |
|---|---|---|
| 1 | Onda 1 esqueleto + Module enabled | ✅ Esta sessão (PRs #1136 + #1138) |
| 2 | Onda 2 migration `payment_gateway_credentials` + backfill `accounts.*gateway*` | ⚠️ Migration `2026_05_19_120000_create_payment_gateway_credentials_table.php` existe no git — **verificar se rodou em prod** (`php artisan migrate:status \| grep payment_gateway`) |
| 3 | Onda 3 webhooks live em prod (Inter/C6/Asaas/BcbPix) | ⚠️ Rotas `/paymentgateway/webhooks/{driver}/{businessId}` existem (Routes/web.php) — **verificar se 30d shadow mode foi observado e cutover real foi feito** |
| 4 | Onda 4 domínio (BoletoService + PixService + drivers funcionais + UI Inertia) | ✅ PR #1130 (4a), #1132 (4b), #1133 (4c), #1134 (4d.1), #1135 (4d F3 UI) — todos mergeados |
| 5 | `RecurringBilling::Subscription` renomeada pra `Assinatura` | ❌ **Não confirmado** — Models/Subscription.php ainda usa nome antigo. Rename é pré-requisito pra evitar conflito de nomes simultâneos com Superadmin::Subscription |
| 6 | Credencial BCB cadastrada em biz=1 (`payment_gateway_credentials.driver='bcb_pix'`) | ❌ **Wagner ação manual** — homologação BCB Pix Automático + CNPJ recebedor habilitado |
| 7 | Plan "SaaS Oimpresso Premium R$ 99,90" cadastrado em biz=1 | ❌ **Wagner ação manual** — `/recurring-billing/plans` |
| 8 | Backfill: cada tenant ativo precisa ter Contact correspondente em biz=1 | ❌ **Comando CLI novo** (item 5.2 acima) |

## 5. Tier 0 risks (multi-tenant, LGPD, append-only)

### R1 — Cross-tenant event handling (**crítico**)

`SuperadminLicenseObserver` precisa atualizar `business.subscription_end_date` do tenant pagador. Mas o handler roda no contexto do business_id da cobrança (biz=1, dono da Assinatura), NÃO no business_id do tenant (que é o `Contact.metadata.tenant_business_id` ou similar).

**Mitigação:** payload do evento `CobrancaPaga` precisa carregar `target_business_id` explícito. Handler usa `Business::withoutGlobalScope(BusinessScope::class)->find($targetBusinessId)` pra atualizar — pattern Tier 0 cross-tenant intencional documentado (skill `multi-tenant-patterns` + comentário literal `Superadmin/Entities/Subscription.php:30` — "cross-tenant intencional Wagner-only").

### R2 — Distinguir cobrança SaaS de cobrança normal

`CobrancaPaga` é disparado pra TODA cobrança paga (RB Invoice cliente, Sell avulsa, mensalidade SaaS). Listener Superadmin não pode reagir a Sell ou Invoice de cliente final.

**Mitigação:** `Cobranca.origem_type` enum aceita `'subscription_license'` — usar isso pra filtrar:
```php
public function handle(CobrancaPaga $event): void {
    if ($event->cobranca->origem_type !== 'subscription_license') return;
    // ... atualiza business.subscription_end_date
}
```
Marcador `origem_type` é seteado quando `RecurringBilling::Invoice` é gerada a partir de `Assinatura(plan.slug='saas-oimpresso-premium')`. Requer ajuste em `RecurringBilling::InvoiceGenerator` (não documentado se existe — verificar).

### R3 — Append-only auditoria LGPD D7.b

`Superadmin::Subscription` é append-only via Spatie LogsActivity (`useLogName('superadmin.subscription')`). Invariante CC Art. 206 — prescrição 10 anos sobre dívida fiscal.

**Mitigação:** projection mode mantém Spatie LogsActivity. Cada update via listener gera 1 entry em `activity_log` com `event='updated'` + `properties.dirty` mostrando old→new. Não há `DELETE` direto. ✅

### R4 — Tenants existentes com Subscription Pesapal/manual

Wagner tem N tenants vivos pagando via PesaPal OR manualmente (`Superadmin::SubscriptionController` aceita 5 gateways). Antes de cutover, precisamos:

1. Snapshot `superadmin.subscriptions` ativos hoje
2. Pra cada, criar `Contact(business_id=1, metadata->tenant_business_id=<X>)` se não existir
3. Pra cada, criar `RecurringBilling::Subscription(business_id=1, contact_id=<contact>, plan_id=<saas_premium>, status='active', next_due_date=<superadmin.subscription.end_date>)`
4. Manter `Superadmin::Subscription` como source-of-truth durante 30d shadow — projection só read-side; PesaPalController continua aceitando confirmações antigas

**Mitigação:** flag `feature.paymentgateway_onda5_dogfooding` — quando OFF, `Superadmin::Subscription` é SoT (modo atual). Quando ON, projection mode + listener atualiza. Rollback = OFF.

### R5 — PesaPal vestigial não pode quebrar

`app/Http/Controllers/PesaPalController.php` (proxy) e `Modules/Superadmin/Http/Controllers/PesaPalController.php` estão registrados em rotas, recebem callbacks PesaPal pra Subscriptions antigas. Não dá pra remover na Onda 5 — só em Onda 6 cleanup, depois de 90d sem nenhum callback recebido.

**Mitigação:** ADR 0170 já documenta — PesaPalDriver é movido pra PaymentGateway (não removido) com `deprecated: true` no `Cobranca.tipo`. Onda 6 cleanup remove se metric `pesapal_callbacks_30d == 0`.

## 6. Esforço e duração estimados

| Fase | Esforço | Wall time |
|---|---|---|
| **5.0 Planejamento** — ADR filho 0170-onda5 + smoke plan + flag config | 1 PR ~50 LOC | 1 dia |
| **5.1 Listener + Observer + Tests** — código novo `SuperadminLicenseObserver` + Pest | 1 PR ~350 LOC | 2 dias |
| **5.2 Backfill command** — `BackfillTenantContactsCommand` + dry-run mode + tests | 1 PR ~300 LOC | 1 dia |
| **5.3 Migration histórica Subscription→RB** — one-shot CLI + dry-run | 1 PR ~120 LOC | 1 dia |
| **5.4 Plan + Credencial BCB cadastrados** — Wagner ação manual em /recurring-billing/plans + /paymentgateway/credenciais | 0 código | 30 min |
| **5.5 Shadow mode 14d** — flag OFF mas listener loga `[SHADOW]` no que faria — sem efeito real. Wagner observa logs vs Superadmin::Subscription updates manuais | 0 código | 14 dias |
| **5.6 Cutover** — flag ON pra biz=1 only, observação 7d | 0 código | 7 dias |
| **5.7 Promoção pra todos tenants** — flag ON universal | 0 código | — |
| **Total** | ~820 LOC + Wagner manual + 21d observação | **~5 dias dev + 21d observação real** |

**Recalibração 2026-05-08+ (ADR 0106):** código com IA-pair pode comprimir 5 dias → ~12h ativas. Observação não comprime — relógio do mundo real preserva.

## 7. Sinais que justificariam adiar Onda 5

Per ADR 0105 (cliente como sinal qualificado): backlog item só ativo se cliente paga + reporta OR métrica detecta drift.

**Sinais atuais:**

| Sinal | Estado | Interpretação |
|---|---|---|
| Wagner já paga PesaPal manual hoje? | ❓ não sei | Se sim — Onda 5 = dogfooding real ativo |
| Larissa (cliente piloto biz=4) já paga mensalidade SaaS? | ❓ não sei | Se sim — Onda 5 = produto pago, prioridade alta |
| Algum bug catalogado em Plan/Invoice/Cobrança que dogfooding pegaria? | ❓ não sei (verificar `decisions-search since:2026-04-01 'plan\|invoice'`) | Se sim — dogfooding teria valor preventivo claro |
| `superadmin.subscriptions` está consistente hoje? (active = tenant pagando) | ❓ não sei (`SELECT COUNT(*) FROM superadmin.subscriptions WHERE status='approved' AND end_date >= CURDATE()` vs `Business::active()`) | Drift aqui = sinal de SoT corrompido — Onda 5 resolve via eventos |

**Recomendação preliminar pesquisa:** Onda 5 vale APENAS se pelo menos 1 dos sinais acima for ✅. Senão, fica em phase=later — não tem urgência se PesaPal manual está funcionando.

## 8. Dependências fora do código

- **Homologação BCB PIX Automático** — Wagner CNPJ precisa estar habilitado como recebedor. Resolução BCB 380/2024.
- **Conta bancária BCB associada** — credencial Pix Automático precisa de conta corrente PJ ligada.
- **Decisão fiscal** — Plan "SaaS Oimpresso Premium" precisa ter `fiscal_type` + `fiscal_cfop` + `fiscal_servico` corretos (campos já existem em `rb_plans`) — emissão NFSe automática via US-RB-044 vai gerar nota a cada CobrancaPaga.
- **Aviso aos tenants existentes** — comunicação 30d antes do cutover ("a partir de DD/MM/AAAA sua mensalidade SaaS Oimpresso será cobrada via PIX Automático BCB em vez de PesaPal — autorize o mandato").

## 9. Refs pra leitura aprofundada

- [ADR 0170 §Roadmap linha 296](../decisions/0170-paymentgateway-extracao-camada-cobranca.md) — Onda 5 oficial
- [ADR 0170 §C - Eliminar Subscription source-of-truth](../decisions/0170-paymentgateway-extracao-camada-cobranca.md) — modelagem dogfooding
- [ADR 0017 emendado](../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md) — Superadmin original
- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 IRREVOGÁVEL
- [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado
- [ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM pipeline (Cobranca status é state machine simples, não FSM canon)
- [Modules/Superadmin/Entities/Subscription.php](../../Modules/Superadmin/Entities/Subscription.php) — SoT atual (vira projection na Onda 5)
- [Modules/RecurringBilling/Models/Subscription.php](../../Modules/RecurringBilling/Models/Subscription.php) — destino do rename pra `Assinatura`
- [Modules/PaymentGateway/Events/CobrancaPaga.php](../../Modules/PaymentGateway/Events/CobrancaPaga.php) — evento que aciona listener
- [Modules/PaymentGateway/SCOPE.md](../../Modules/PaymentGateway/SCOPE.md) — frontmatter completo

## 10. Próximos passos sugeridos (Wagner aprova)

1. **Responder os 4 ❓ da §7** — se nenhum sinal ✅, Onda 5 vai pra `later` e fica documentada
2. Se ativar: criar **ADR filho 0170-onda5-dogfooding** com checklist objetivo + flag name + smoke plan
3. Esse ADR vira blueprint pro implementador (com-integrar skill ou audit-implement-expert) executar em ondas 5.1→5.7

---

**Fonte:** pesquisa introspectiva 2026-05-19 — ADR 0170 lido integralmente + cruzamento com 7 arquivos do Superadmin/RecurringBilling/PaymentGateway + ADRs relacionados (0017, 0093, 0105, 0143).

**Não codificado nesta sessão.** Wagner pediu pesquisa apenas.
