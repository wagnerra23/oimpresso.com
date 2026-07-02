---
number: 170
title: "PaymentGateway Onda 5 SIMPLIFICADA — Dogfooding SaaS via 6º gateway adicional"
status: aceito
decided_by: [W]
decided_at: "2026-05-19"
related: [0017-officeimpresso-restaurado-superadmin-exclusivo, 0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0105-cliente-como-sinal-guiar-sem-mandar, 0170-paymentgateway-extracao-camada-cobranca]
supersedes: []
amends: [0170-paymentgateway-extracao-camada-cobranca]
tipo: amendment
trust_required: tier-0
slug: 0170-onda5-simplificada
type: adr
authority: canonical
lifecycle: ativo
---

# Contexto

[ADR 0170 §C](0170-paymentgateway-extracao-camada-cobranca.md) original propôs **projection mode** — `Superadmin::Subscription` viraria projeção read-only de `cobrancas` (SoT migrada pro `Modules/PaymentGateway`). Estimativa ~680 LOC de refator + backfill + risco LGPD D7.b (append-only Spatie LogsActivity) + coexistência forçada com 5 gateways legacy (Razorpay/Stripe/PayPal/Paystack/Pesapal).

**Pesquisa Wagner 2026-05-19** ([sessão pesquisa-adendo](../sessions/2026-05-19-pesquisa-onda5-adendo-connector-superadmin.md)) descobriu que o sistema canônico **Connector + Officeimpresso + Superadmin já tem 90% da infraestrutura** que §C propôs construir do zero:

- 5 gateways legacy já implementados (`SubscriptionController::payForPackage`)
- Spatie LogsActivity append-only LGPD D7.b honrado em `Subscription`
- Wire Delphi `/connector/api/oimpresso/registrar` + `/oauth/token` consultando `business.officeimpresso_bloqueado` SoT
- `BaseController::_payment_gateways()` enumera gateways disponíveis dinamicamente

**Decisão Wagner 2026-05-19:** caminho B (SIMPLIFICADA) aprovado vs A original §C vs C híbrido. Compressão ~680→~570 LOC (~16% menor mas com **zero refator SoT**, zero backfill, zero risco LGPD, 5 gateways legacy coexistem).

# Decisão

PaymentGateway entra como **6º payment gateway adicional** em `Superadmin::Subscription`, não como refator do SoT.

## Escopo executado (PR #1148, commit `3c2d00cc4`, 2026-05-19)

### Listeners (2)
- `Modules/Superadmin/Listeners/OnCobrancaPagaUpdateSubscription.php` — escuta `CobrancaPaga`, filtro `origem_type='subscription_license'`, `Subscription.status='approved'` + datas, desbloqueia `business.officeimpresso_bloqueado=false` (cross-tenant Wagner-only)
- `Modules/Superadmin/Listeners/OnCobrancaVencidaBloqueaSubscription.php` — escuta `CobrancaVencida`, `Subscription.status='declined'`, bloqueia `business.officeimpresso_bloqueado=true` + motivo (cross-tenant Wagner-only)

Ambos registrados em [`SuperadminServiceProvider`](../../Modules/Superadmin/Providers/SuperadminServiceProvider.php) com guard anti-double-register.

### SubscriptionController (1)
Branch `paid_via='paymentgateway_pix_automatico'` adicionado em [`payForPackage()`](../../Modules/Superadmin/Http/Controllers/SubscriptionController.php) — emite Cobrança via `PaymentGatewayContract::emitirPixAutomatico` (driver BCB Pix), gera Subscription `status='waiting'`, retorna QR pra tenant autorizar mandato. `BaseController::_payment_gateways()` enumera condicional ao Module ativo.

### View Blade (1)
[`partials/pay_paymentgateway_pix_automatico.blade.php`](../../Modules/Superadmin/Resources/views/subscription/partials/) — partial seguindo padrão legacy `@includeIf('superadmin::subscription.partials.pay_'.$k)` em vez de view completa nova (divergência intencional vs blueprint §4.4 pra alinhamento de pattern).

### Comando CLI (1)
[`paymentgateway:register-permissions`](../../Modules/PaymentGateway/Console/Commands/RegisterPermissionsCommand.php) — registra 10 permissions Spatie do `DataController` + atribui a role `Admin#{biz}`. Resolve bug recorrente de permissions on-demand não atribuídas. Pattern imitado de `whatsapp:register-permissions` PR #665.

### Integração Financeiro (1)
[`Modules/Financeiro/Listeners/OnCobrancaPagaCreateFinanceiroTitulo.php`](../../Modules/Financeiro/Listeners/) — escuta `CobrancaPaga` (filtro `business_id=1`), cria `FinTitulo` + `FinTituloBaixa` auto-baixadas pra Wagner contabilizar receita SaaS em biz=1.

Registrado em [`FinanceiroServiceProvider`](../../Modules/Financeiro/Providers/FinanceiroServiceProvider.php) linha 49.

### Bônus não previstos (mergeados junto, valor extra)
- `BusinessAutoSubscriptionObserver` — `Business::created` → `Subscription waiting` auto (Onda 5.B)
- `EmitTrialExpiredCobrancasCommand` — cron diário 08:00 BRT
- `MigrateCredentialsCommand` — migração legacy `rb_boleto_credentials` → `payment_gateway_credentials`
- 3 Pest extras (BusinessAutoSubscription / EmitTrialExpired / Onda5CuradoriaR1)

## Cross-tenant Tier 0 (R1 mitigado)

Listeners 4.1 e 4.2 atualizam `Business` de OUTRO tenant a partir do contexto `biz=1`. Pattern Wagner-only intencional documentado em [`Superadmin::Subscription`](../../Modules/Superadmin/Entities/Subscription.php#L30) e [ADR 0093](0093-multi-tenant-isolation-tier-0.md). Implementação usa `withoutGlobalScope(BusinessScope::class)` + audit log + Pest test `block_only_when_origem_type_matches`.

# Consequências

## Positivas
- Wire Delphi inviolável preservado ([contrato-delphi-inviolavel](../reference/contrato-delphi-inviolavel.md)) — zero mudança em `/oauth/token`, `/connector/api/oimpresso/registrar`, `/processa-dados-cliente`
- `Superadmin::Subscription` permanece SoT — zero refator + zero backfill + Spatie LogsActivity append-only intacto (LGPD D7.b CC Art. 206)
- 5 gateways legacy (Razorpay/Stripe/PayPal/Paystack/Pesapal) coexistem com PaymentGateway sem precisar migrar
- Ciclo end-to-end "paga → libera / não paga → bloqueia" automatizado via eventos `CobrancaPaga`/`CobrancaVencida`
- Receita SaaS Wagner-em-biz=1 entra automaticamente no `Modules/Financeiro` via FinTitulo+FinTituloBaixa

## Negativas / Custos
- **Cross-tenant cognitive load** — 2 listeners precisam `withoutGlobalScope` documentado (pattern Wagner-only)
- **6 gateways pra manter** — adicionar Pagar.me (Onda 4e) sobe pra 7
- **Race condition R6** — webhook BCB pode chegar antes da Subscription estar gravada; listener faz `find()` e retorna null se órfão (Wagner reconcilia manual via UI Cobranças)

## Pendências pós-aceite (não-código, humano-limitado per [ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md))

| # | Ação | Wagner | Tempo |
|---|---|---|---|
| 1 | Cadastrar ContaBancaria Wagner em `/financeiro/contas` (biz=1) | ✅ manual | 5min |
| 2 | Cadastrar Credencial BCB Pix Automático em `/settings/payment-gateways` | ✅ manual | 15min |
| 3 | Cadastrar Package "Premium" em `/superadmin/packages` (R$ [redacted Tier 0]/mês) | ✅ manual | 10min |
| 4 | Rodar `php artisan paymentgateway:register-permissions --business=all` em prod | ✅ SSH | 5min |
| 5 | Homologação BCB Pix Automático (Resolução BCB 380/2024) | ✅ banco | 1-3d |
| 6 | Smoke real biz=1 (Wagner paga ele mesmo — dogfooding §9.1) | ✅ manual | 1d |
| 7 | Cobrar Larissa biz=4 com sucesso 1 vez (§9.2) | ✅ manual | 7d canary |
| 8 | Smoke inadimplência (deixar 1 cobrança vencer → confirmar `officeimpresso_bloqueado=true` + Delphi 400 invalid_grant) | ✅ manual | 1d |

## Frontmatter PLANO-ONDA5-SIMPLIFICADA pendente atualizar

Atualizar [`memory/requisitos/PaymentGateway/PLANO-ONDA5-SIMPLIFICADA.md`](../requisitos/PaymentGateway/PLANO-ONDA5-SIMPLIFICADA.md) frontmatter de `plano-aprovado-aguardando-execucao` → `executado-aguardando-smoke` quando esta ADR for mergeada. Adicionar seção "Histórico de execução" apontando PR #1148.

# Refs

- [ADR 0170](0170-paymentgateway-extracao-camada-cobranca.md) — PaymentGateway extração (parent, §C original)
- [ADR 0017](0017-officeimpresso-restaurado-superadmin-exclusivo.md) — Officeimpresso restaurado
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal
- [Blueprint PLANO-ONDA5-SIMPLIFICADA](../requisitos/PaymentGateway/PLANO-ONDA5-SIMPLIFICADA.md)
- [Pesquisa A — ADR 0170 §C original](../sessions/2026-05-19-pesquisa-onda5-paymentgateway-dogfooding.md)
- [Pesquisa B — adendo Connector+Superadmin (SIMPLIFICADA)](../sessions/2026-05-19-pesquisa-onda5-adendo-connector-superadmin.md)
- [contrato-delphi-inviolavel](../reference/contrato-delphi-inviolavel.md) — wire imutável
- [Auditoria execução 2026-05-22](../sessions/2026-05-22-audit-onda5-paymentgateway.md)
- PR #1148 commit `3c2d00cc4` — execução completa

# Status histórico

- 2026-05-19 — Plano B aprovado por Wagner ([PLANO-ONDA5-SIMPLIFICADA](../requisitos/PaymentGateway/PLANO-ONDA5-SIMPLIFICADA.md))
- 2026-05-19 — PR #1148 mergeado (6/6 itens código + bônus)
- 2026-05-22 — Auditoria confirmou execução 100% código; gap restante = pré-condições prod manuais + smoke real
- 2026-05-22 — Esta ADR criada formalizando aceite + executado
