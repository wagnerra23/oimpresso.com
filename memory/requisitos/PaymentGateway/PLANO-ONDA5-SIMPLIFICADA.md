---
modulo: PaymentGateway
onda: 5
status: executado-aguardando-smoke
aprovado_por: Wagner
aprovado_em: 2026-05-19
executado_em: 2026-05-19
executado_pr: 1148
executado_commit: 3c2d00cc4
adr_origem: 0170
adr_filho: 0170-onda5-simplificada
tipo: blueprint
trust_required: tier-0
---

## Histórico de execução

- **2026-05-19** — Plano B aprovado por Wagner
- **2026-05-19** — PR #1148 mergeado (commit `3c2d00cc4`) — 6/6 itens código + bônus (BusinessAutoSubscriptionObserver, EmitTrialExpiredCobrancasCommand cron, MigrateCredentialsCommand)
- **2026-05-22** — Auditoria confirmou execução 100% código ([session log](../../sessions/2026-05-22-audit-onda5-paymentgateway.md))
- **2026-05-22** — ADR filho [`0170-onda5-simplificada`](../../decisions/0170-onda5-simplificada.md) criado formalizando aceite

**Pendências humano-limitadas (ADR 0106 — relógio do mundo real):**
1. 5 pré-condições prod manuais (ContaBancaria + Credencial BCB + Package Premium + `register-permissions --business=all` + homologação BCB 1-3d)
2. Smoke dogfooding biz=1 — Wagner paga ele mesmo
3. Canary 7d com Larissa (biz=4)
4. Smoke inadimplência — deixar 1 cobrança vencer + confirmar bloqueio Delphi 400 invalid_grant

# PaymentGateway Onda 5 SIMPLIFICADA — Dogfooding SaaS via gateway adicional

> **Decisão Wagner 2026-05-19:** caminho **B** aprovado (vs A original ADR 0170 §C, vs C híbrido).
>
> **Justificativa:** sistema canônico Connector + Officeimpresso + Superadmin **já tem 90% da infraestrutura** que ADR 0170 §C propôs construir do zero. Plano B comprime ~680→~250 LOC (3-4x), zero refator de SoT, zero backfill, zero risco LGPD D7.b append-only, Pesapal/Stripe/Razorpay/PayPal/Paystack coexistem.
>
> **Pesquisa de origem:** [`memory/sessions/2026-05-19-pesquisa-onda5-paymentgateway-dogfooding.md`](../../sessions/2026-05-19-pesquisa-onda5-paymentgateway-dogfooding.md) (versão fiel ADR 0170) + [`memory/sessions/2026-05-19-pesquisa-onda5-adendo-connector-superadmin.md`](../../sessions/2026-05-19-pesquisa-onda5-adendo-connector-superadmin.md) (re-desenho SIMPLIFICADO).

## 1. Problema real (catalogado Wagner 2026-05-19)

> "**não está funcionando os pagamentos**. eu teria que fazer lá as conecções e fazer integrar com o financeiro. senão vai pagar e não vai liberar o cliente. essa seria a intensão"

**Estado atual prod:**

- 5 gateways Subscription legacy implementados ([SubscriptionController](../../../Modules/Superadmin/Http/Controllers/SubscriptionController.php) — Razorpay/Stripe/PayPal/Paystack/Pesapal) mas **fluxo end-to-end quebrado em prod** (Wagner não conseguiu cobrar tenants de forma confiável)
- Sem ciclo automático "**paga → libera / não paga → bloqueia**" — Wagner controla manual `business.officeimpresso_bloqueado` via UI
- Sem reconciliação automática com **Modules/Financeiro** (`fin_contas_bancarias`, `fin_titulos`, `fin_titulo_baixas`) — entrada de dinheiro do tenant SaaS não fluí pra contabilidade do Wagner em biz=1

**Onda 5 SIMPLIFICADA resolve esses 3 problemas via 5 entregas + 1 integração Financeiro.**

## 2. Contrato Delphi inviolável (Tier 0)

Ler [`memory/reference/contrato-delphi-inviolavel.md`](../../reference/contrato-delphi-inviolavel.md) INTEGRAL antes de tocar qualquer coisa em Modules/{Connector,Officeimpresso,Superadmin}.

**Resumo aplicável:**

- ✅ Pode adicionar Listener novo que reage a evento `CobrancaPaga`
- ✅ Pode adicionar campos novos no response `/connector/api/active-subscription`
- ✅ Pode adicionar comando CLI novo (`paymentgateway:register-permissions`)
- ✅ Pode adicionar `Cobranca.origem_type='subscription_license'` (enum ADR 0170 já prevê)
- ❌ NÃO mudar wire de `/oimpresso/registrar`, `/processa-dados-cliente`, `/oauth/token`
- ❌ NÃO mudar `Superadmin::Subscription` schema (Spatie LogsActivity append-only LGPD D7.b CC Art. 206)
- ❌ NÃO mover `PesaPalController` — coexiste como gateway existente

## 3. Pré-condições

| # | Pré-condição | Wagner manual | Time | Estado verificável |
|---|---|---|---|---|
| 1 | Ondas 1-4 PaymentGateway em prod | — | — | ✅ PRs #1125-#1136 (Onda 1 esqueleto + 2 DB + 2.5 backfill + 3 webhooks + 4a Inter + 4b C6/Asaas + 4c refund + 4d.1 BcbPix + 4d F3 UI) |
| 2 | Onda 2 migration `payment_gateway_credentials` rodada em prod | — | 5min SSH | `ssh hostinger 'php artisan migrate:status \| grep payment_gateway_credentials'` |
| 3 | `BcbPixDriver` smoke validado (mandato BCB recv funcional sandbox) | — | 30min | [Modules/PaymentGateway/Services/Drivers/BcbPixDriver.php](../../../Modules/PaymentGateway/Services/Drivers/BcbPixDriver.php) + Pest [BcbPixDriverTest](../../../Modules/PaymentGateway/Tests/Feature/BcbPixDriverTest.php) |
| 4 | Conta bancária Wagner em `fin_contas_bancarias` (biz=1) | ✅ Wagner cadastra em `/financeiro/contas` | 5min | `SELECT * FROM fin_contas_bancarias WHERE business_id=1 AND ativo=true` |
| 5 | Credencial BCB Pix Automático cadastrada em `payment_gateway_credentials` (biz=1, **`gateway_key='bcb_pix'`**, ambiente='production', **`conta_bancaria_id` preenchido no step 3 do wizard**) | ✅ Wagner via UI /settings/payment-gateways → "Novo gateway" (3 steps: Driver → Credenciais → Vínculo) | 15min Wagner + 1d homologação BCB | `SELECT * FROM payment_gateway_credentials WHERE business_id=1 AND gateway_key='bcb_pix' AND ativo=true AND conta_bancaria_id IS NOT NULL` |
| 6 | ~~`fin_contas_bancarias.payment_gateway_credential_id` apontando pra credencial~~ ❌ **REMOVIDO Wagner 2026-05-19** — direção FK invertida (canon: `payment_gateway_credentials.conta_bancaria_id`). Cadastro tudo em 1 tela só (Settings/PaymentGateways). FK em fin_contas_bancarias permanece como fallback legacy mas não obrigatória. | n/a (wizard step 3 já vincula) | n/a | n/a |
| 7 | Package "Premium" existe em `superadmin.packages` (`name='Premium Oimpresso ERP'`, `interval='months'`, `price=99.90`, `custom_permissions={modules wanted}`) | ✅ Wagner via UI `/superadmin/packages/create` | 10min | `SELECT * FROM superadmin.packages WHERE name LIKE 'Premium%' AND is_active=1` |
| 8 | Homologação BCB (CNPJ Wagner habilitado como recebedor PIX Automático Resolução BCB 380/2024) | ✅ Wagner pelo banco | 1-3d burocracia | Conta corrente PJ + cadastro RECEBEDOR no BCB |

**Sequência:** 1→2→3→4→7 (paralelo) → 5→6→8 (dependentes).

## 4. Escopo Onda 5 — 5 itens código

### 4.1 Listener `OnCobrancaPagaUpdateSubscription` (~40 LOC)

**Path:** `Modules/Superadmin/Listeners/OnCobrancaPagaUpdateSubscription.php`
**Registro:** `Modules/Superadmin/Providers/SuperadminServiceProvider::boot()` via `Event::listen(CobrancaPaga::class, [...])`
**Pattern imitado:** [`PesaPalController::pesaPalPaymentConfirmation`](../../../Modules/Superadmin/Http/Controllers/PesaPalController.php) 1:1

**Lógica:**
```php
public function handle(CobrancaPaga $event): void
{
    $cobranca = $event->cobranca;

    // Filtro Tier 0 — só processa cobranças de license SaaS
    if ($cobranca->origem_type !== 'subscription_license') {
        return;
    }

    $subscription = Subscription::find($cobranca->origem_id);
    if (!$subscription) {
        Log::error('[onda5] CobrancaPaga sem Subscription correspondente', [
            'cobranca_id' => $cobranca->id,
            'origem_id' => $cobranca->origem_id,
        ]);
        return;
    }

    // Pattern PesaPalController — não duplicar approval se já approved
    if ($subscription->status === 'approved') {
        return;
    }

    $dates = $this->calculatePackageDates($subscription->business_id, $subscription->package);

    $subscription->status = 'approved';
    $subscription->start_date = $dates['start'];
    $subscription->end_date = $dates['end'];
    $subscription->trial_end_date = $dates['trial'];
    $subscription->payment_transaction_id = $cobranca->gateway_external_id;
    $subscription->paid_via = 'paymentgateway_pix_automatico';
    $subscription->save();
    // Spatie LogsActivity append-only registra mudança — LGPD D7.b CC Art. 206 honrado

    // Desbloqueia empresa SE estava bloqueada por inadimplência
    $business = Business::withoutGlobalScope(BusinessScope::class)->find($subscription->business_id);
    if ($business && $business->officeimpresso_bloqueado) {
        $business->officeimpresso_bloqueado = false;
        $business->officeimpresso_bloqueado_motivo = null;
        $business->save();
        Log::info('[onda5] business desbloqueado por pagamento', [
            'business_id' => $business->id,
            'cobranca_id' => $cobranca->id,
        ]);
    }
}
```

### 4.2 Listener `OnCobrancaVencidaBloqueaSubscription` (~40 LOC — NOVO vs original)

**Path:** `Modules/Superadmin/Listeners/OnCobrancaVencidaBloqueaSubscription.php`
**Registro:** mesmo SP, escuta `CobrancaVencida`
**Trigger:** Wagner regra "se não paga, bloqueia"

**Lógica:**
```php
public function handle(CobrancaVencida $event): void
{
    $cobranca = $event->cobranca;
    if ($cobranca->origem_type !== 'subscription_license') return;

    $subscription = Subscription::find($cobranca->origem_id);
    if (!$subscription) return;

    // Política Wagner: vence 1 cobrança → marca subscription expirada
    // (RB smart retry 3 retentativas roda antes — só dispara CobrancaVencida após 3 falhas)
    $subscription->status = 'declined';
    $subscription->save();

    // Bloqueia empresa — Tier 0 cross-tenant (skill multi-tenant-patterns)
    $business = Business::withoutGlobalScope(BusinessScope::class)->find($subscription->business_id);
    if ($business && !$business->officeimpresso_bloqueado) {
        $business->officeimpresso_bloqueado = true;
        $business->officeimpresso_bloqueado_motivo = 'Mensalidade SaaS vencida — cobranca #' . $cobranca->id;
        $business->save();
        Log::warning('[onda5] business bloqueado por inadimplencia SaaS', [
            'business_id' => $business->id,
            'cobranca_id' => $cobranca->id,
            'subscription_id' => $subscription->id,
        ]);

        // Próximo /oauth/token do Delphi do tenant → 400 invalid_grant (canônico)
        // Próximo /oimpresso/registrar → autorizado='N', message='Empresa bloqueada' (canônico)
    }
}
```

⚠️ **Tier 0 cross-tenant** — handler atualiza Business de OUTRO tenant a partir do contexto biz=1. Pattern `Superadmin::Subscription` já documenta isso como "cross-tenant intencional Wagner-only" — ver [Subscription.php:30](../../../Modules/Superadmin/Entities/Subscription.php#L30) comentário canônico.

### 4.3 Integração `SubscriptionController::payForPackage()` PaymentGateway (~30 LOC)

**Path:** [Modules/Superadmin/Http/Controllers/SubscriptionController.php](../../../Modules/Superadmin/Http/Controllers/SubscriptionController.php)
**Pattern imitado:** branch Pesapal/Stripe existente (case `'paid_via' === 'pesapal'` / `'stripe'`)

```php
elseif ($paid_via === 'paymentgateway_pix_automatico') {
    $subscription = $this->createWaitingSubscription($package, $business_id);

    $cobranca = app(PaymentGatewayContract::class)
        ->for($wagner_account)  // ContaBancaria Wagner em fin_contas_bancarias
        ->emitirPixAutomatico(new EmitirCobrancaInput([
            'origem_type' => 'subscription_license',
            'origem_id' => $subscription->id,
            'business_id' => 1,  // Wagner é dono da Cobranca
            'metadata' => ['target_business_id' => $business_id],  // tenant alvo
            'payer_cpf_cnpj' => $tenant_owner->cpf_cnpj,
            'payer_name' => $tenant_owner->name,
            'payer_email' => $tenant_owner->email,
            'valor' => $package->price,
            'vencimento' => now()->addDays(7),  // 7d pra autorizar mandato
            'description' => "Mensalidade Oimpresso ERP — {$package->name} — {$tenant_business->name}",
        ]));

    $subscription->payment_transaction_id = $cobranca->gateway_external_id;
    $subscription->save();

    return redirect()->route('subscription.index')
        ->with('status', "Cobrança PIX Automático enviada. Tenant deve autorizar mandato em até 7 dias.");
}
```

### 4.4 Form view pagamento (~50 LOC)

**Path:** `Modules/Superadmin/Resources/views/subscription/pay_paymentgateway.blade.php`
**Pattern imitado:** `pay_pesapal.blade.php` ou similar (form Blade legacy AdminLTE)

Adiciona button "Pagar via PIX Automático BCB (recomendado)" antes dos gateways legacy.

### 4.5 Comando `paymentgateway:register-permissions` (~50 LOC)

**Path:** `Modules/PaymentGateway/Console/Commands/RegisterPermissionsCommand.php`
**Pattern imitado:** [`whatsapp:register-permissions`](../../reference/whatsapp-permissions-spatie.md) (PR #665) 1:1

**Necessidade:** as 10 permissions definidas em [PaymentGateway/Http/Controllers/DataController](../../../Modules/PaymentGateway/Http/Controllers/DataController.php) (`paymentgateway.access`, `credenciais.viewAny`, ..., `webhook.replay`) **nunca foram registradas em prod** — Spatie cria on-demand, ninguém atribuiu pela UI ainda. Bug recorrente.

```php
public function handle()
{
    $permissions = [
        'paymentgateway.access',
        'paymentgateway.credenciais.viewAny',
        // ... 10 permissions do DataController
    ];

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $businesses = $this->option('business') === 'all'
        ? Business::all()->pluck('id')
        : [(int) $this->option('business')];

    foreach ($businesses as $biz) {
        $role = Role::where('name', "Admin#{$biz}")->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}
```

## 5. Integração Modules/Financeiro (item NOVO vs ADR 0170 original)

> Wagner 2026-05-19: "fazer integrar com o financeiro. senão vai pagar e não vai liberar o cliente."

**Achado pesquisa:** `fin_contas_bancarias` JÁ tem FK `rb_gateway_credential_id` apontando pra `rb_boleto_credentials` (migration [`2026_05_06_000001_add_rb_gateway_credential_to_fin_contas_bancarias.php`](../../../Modules/Financeiro/Database/Migrations/2026_05_06_000001_add_rb_gateway_credential_to_fin_contas_bancarias.php)).

**Plano de integração:** quando PaymentGateway extrair `rb_boleto_credentials` → `payment_gateway_credentials` (Onda 2 do roadmap geral PaymentGateway), o FK em `fin_contas_bancarias` se renomeia ou ganha alias. **Pra Onda 5 SIMPLIFICADA não bloqueia** — basta:

### 5.1 Listener `OnCobrancaPagaCreateFinanceiroTitulo` (~50 LOC — novo)

**Path:** `Modules/Financeiro/Listeners/OnCobrancaPagaCreateFinanceiroTitulo.php`
**Trigger:** `CobrancaPaga`
**Lógica:**

```php
public function handle(CobrancaPaga $event): void
{
    $cobranca = $event->cobranca;
    if ($cobranca->business_id !== 1) return;  // Só Wagner (dogfooding)

    // Cria FinTitulo recebido + FinTituloBaixa (pago)
    $titulo = FinTitulo::create([
        'business_id' => 1,
        'tipo' => 'receber',
        'origem_type' => 'paymentgateway_cobranca',
        'origem_id' => $cobranca->id,
        'cliente_id' => $cobranca->contact_id,
        'descricao' => $cobranca->description ?: 'Cobrança PaymentGateway',
        'valor_original' => $cobranca->valor,
        'valor_pago' => $cobranca->valor,
        'data_emissao' => $cobranca->emitida_em,
        'data_vencimento' => $cobranca->vencimento,
        'data_pagamento' => $cobranca->paga_em,
        'status' => 'pago',
    ]);

    FinTituloBaixa::create([
        'titulo_id' => $titulo->id,
        'business_id' => 1,
        'conta_bancaria_id' => $this->resolveContaBancariaFromCredencial($cobranca->payment_gateway_credential_id),
        'valor_baixa' => $cobranca->valor,
        'data_baixa' => $cobranca->paga_em,
        'forma_pagamento' => $cobranca->tipo,  // boleto | pix_cob | pix_recv | card
        'observacao' => "Auto-baixa via PaymentGateway cobranca #{$cobranca->id}",
    ]);

    // Atualiza saldo cached da conta (se feature ativa)
    $this->updateContaSaldo($cobranca->payment_gateway_credential_id, $cobranca->valor);
}
```

### 5.2 (Opcional) Listener `OnCobrancaPagaCreateFinanceiroTituloPagar` pra cobrança outgoing

Wagner ainda não confirmou se quer reverso (Cobranca emitida pelo Wagner em biz=N → cria conta a pagar em biz=N apontando pra Wagner). Out of scope Onda 5 — fica em Onda futura.

## 6. Ciclo end-to-end (visualização)

```
Wagner em biz=1:
  1. Cadastra Package "Premium" em /superadmin/packages
  2. Cadastra ContaBancaria Wagner em /financeiro/contas
  3. Cadastra Credencial BCB Pix Automático em /paymentgateway/credenciais
  4. Liga ContaBancaria ↔ Credencial (FK rb_gateway_credential_id)
  5. Vai em /superadmin/business/{tenant}/subscriptions → "Nova subscription"
     → Escolhe Package "Premium" + paid_via='paymentgateway_pix_automatico'
     → SubscriptionController.payForPackage emite Cobranca PIX Automático
     → Subscription criada com status='waiting'

Tenant Larissa em biz=4:
  6. Recebe link/QR pra autorizar mandato BCB Pix Automático
  7. Autoriza no app banco dela
     → Webhook BCB → /paymentgateway/webhooks/bcb-pix/1
     → BcbPixWebhookController processa
     → Dispatch CobrancaPaga
     → Listener OnCobrancaPagaUpdateSubscription:
         Subscription.status='approved' + dates set
         Business.officeimpresso_bloqueado=false (se estava bloqueado)
     → Listener OnCobrancaPagaCreateFinanceiroTitulo:
         FinTitulo + FinTituloBaixa criados em biz=1 (Wagner contabiliza receita)

Larissa abre Delphi WR Comercial:
  8. POST /oauth/token → user passa (officeimpresso_bloqueado=false)
  9. POST /connector/api/oimpresso/registrar → autorizado='S', dias_restantes=30

Cenário inadimplência (passados 30d sem renovar):
 10. Próxima cobrança gerada por cron RB (Wagner cria Assinatura recorrente — Onda separada)
 11. Tenant não paga
 12. RB smart retry 3x falha → dispatch CobrancaVencida
 13. Listener OnCobrancaVencidaBloqueaSubscription:
         Subscription.status='declined'
         Business.officeimpresso_bloqueado=true
 14. Próximo /oauth/token Delphi Larissa → 400 invalid_grant
 15. Delphi mostra "não autenticou" → Larissa liga pro Wagner → paga → cycle 5
```

## 7. Riscos Tier 0 + mitigações

| # | Risco | Mitigação |
|---|---|---|
| R1 | **Cross-tenant** — listeners atualizam Business de OUTRO tenant | `withoutGlobalScope(BusinessScope::class)` + audit log + documentado em ADR Tier 0 ([0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — pattern Wagner-only intencional já estabelecido por `Superadmin::Subscription` |
| R2 | **Distinguir cobrança SaaS de cobrança normal** | `Cobranca.origem_type='subscription_license'` filtro literal no listener |
| R3 | **Append-only LGPD D7.b CC Art. 206** | `Subscription` continua SoT, Spatie LogsActivity intacto. ✅ Zero risco |
| R4 | **Tenants existentes pagando Pesapal** | Pesapal continua funcionando. PaymentGateway é OPÇÃO adicional (não substitui). Migração lenta caso-a-caso |
| R5 | **Bloquear empresa errada** (bug listener) | Pest test `OnCobrancaPagaListener::block_only_when_origem_type_matches` + Pest `OnCobrancaVencidaListener::dont_block_when_subscription_not_found` |
| R6 | **Webhook BCB chega antes da Subscription estar criada** (race) | Listener faz `Subscription::find($cobranca->origem_id)` — se NULL loga erro e retorna; cobrança fica em status 'paga' órfã (Wagner reconcilia manual) |
| R7 | **Delphi Larissa autentica enquanto Wagner ainda processando webhook** (gap) | `business.officeimpresso_bloqueado` cache hit → eventual consistency aceita (gap de segundos) |
| R8 | **PII em log** | `payer_cpf_cnpj`/`payer_email` redacted em todos `Log::*` calls — usar [`Modules/Superadmin/Support/RedactsPiiInLogs`](../../../Modules/Superadmin/Support/RedactsPiiInLogs.php) trait |
| R9 | **Quebra Delphi wire** | NÃO mexer em [Modules/Connector/Routes/api.php](../../../Modules/Connector/Routes/api.php) nem em [OImpressoRegistroController](../../../Modules/Connector/Http/Controllers/Api/OImpressoRegistroController.php) nem em [User::validateForPassportPasswordGrant](../../../app/User.php). Ver [contrato-delphi-inviolavel.md](../../reference/contrato-delphi-inviolavel.md) |

## 8. Esforço dimensionado

| Fase | Esforço |
|---|---|
| ADR filho 0170-onda5-simplificada (este blueprint refinado pra Wagner aprovar) | 1h |
| 4.1 + 4.2 Listeners Superadmin + Pest (~80 + ~80 LOC + ~150 LOC tests) | 4h |
| 4.3 SubscriptionController integração (~30 LOC) | 1h |
| 4.4 View Blade pay_paymentgateway (~50 LOC) | 2h |
| 4.5 Command register-permissions (~50 LOC) | 1h |
| 5.1 Listener Financeiro FinTitulo+FinTituloBaixa (~50 LOC + ~80 LOC tests) | 3h |
| Smoke shadow biz=1 (flag OFF mas listener loga `[SHADOW]`) — 7 dias observação | observação |
| Cutover biz=1 (flag ON, Wagner paga ele mesmo) — 7 dias observação | observação |
| Promoção pra todos tenants — flag ON universal | quando Wagner aprovar |
| **Total código** | **~570 LOC + ~310 LOC tests** |
| **Wall time código (com IA-pair recalibração ADR 0106)** | **~12-15h ativas** |
| **Wall time observação** | **14 dias real** |

## 9. Critérios de aceite

- [ ] Wagner cobra ELE MESMO em biz=1 com sucesso 1 vez (Wagner é cliente do próprio produto — dogfooding sério)
- [ ] Wagner cobra Larissa (biz=4) com sucesso 1 vez ciclo end-to-end (subscription created → cobrança emitida → autorizada mandato → CobrancaPaga → Subscription approved → desbloqueia se estava bloqueada)
- [ ] Wagner deixa expirar 1 cobrança propositalmente (sandbox) → confirma `business.officeimpresso_bloqueado=true` + Delphi rejeita oauth/token
- [ ] `FinTitulo` + `FinTituloBaixa` aparecem em /financeiro biz=1 corretamente
- [ ] `php artisan paymentgateway:register-permissions --business=all` rodado em prod sem erro
- [ ] Pest cobertura: listeners + register-permissions + race conditions (subscription not found / origem_type mismatch / already approved)
- [ ] Pest regression `DelphiOImpressoContractTest` 9 guards ainda verdes (zero quebra wire Delphi)
- [ ] OTel spans `superadmin.onda5.cobranca_paga_processed` + `superadmin.onda5.cobranca_vencida_blocked` + `financeiro.onda5.titulo_auto_baixa_created`
- [ ] Health check `php artisan jana:health-check` adiciona métricas:
  - `paymentgateway_onda5.subscription_pagamentos_24h`
  - `paymentgateway_onda5.bloqueios_inadimplencia_24h`
  - `paymentgateway_onda5.fin_titulos_auto_baixados_24h`
  - `paymentgateway_onda5.race_orphan_cobranca_paga` (deve ser 0)

## 10. ADR filho pendente

Quando Wagner aprovar execução desta blueprint, criar:

**ADR 0170-onda5-simplificada — Dogfooding SaaS via gateway adicional**

- `status: aceito`
- `decided_by: [W]`
- `supersedes: nada` (não substitui — emenda ADR 0170 §C "vira projection" pra "permanece SoT + PaymentGateway é 6º gateway")
- `related: [0017, 0093, 0105, 0170]`
- Resumo: PaymentGateway entra como 6º payment gateway em `Superadmin::Subscription`, 2 listeners (paga/vencida) + 1 integração Financeiro + 1 comando permissions + 1 view Blade. ~570 LOC. Zero refator SoT. Zero backfill. Wire Delphi inviolável preservado.

## 11. Sinais futuros que justificariam Onda 5 ORIGINAL (não SIMPLIFICADA)

Per [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md), versão original só se algum sinal ✅ aparecer:

| Sinal | Pergunta |
|---|---|
| Múltiplos produtos SaaS | Wagner planeja vender Plans diferentes via RB pra cliente final (não só Oimpresso ERP)? — **se SIM, Plan RB faz sentido em biz=1** |
| Cobrança avulsa SaaS | Wagner vai cobrar consultoria/setup/treinamento separados? — **se SIM, Cobranca avulsa via PaymentGateway entra em Sells, não Subscription** |
| Audit cross-tenant pesado | Power BI / dashboard externo precisa view materializada Subscription? — **se SIM, projection mode tem valor** |
| Conformidade LGPD pede separação | Auditor exigiu separar "licença SaaS" de "cobrança bancária" no modelo? — **se SIM, refator justifica** |

Se nenhum ✅ em 6 meses, ADR 0170 §C original pode ser marcada `historical`/`superseded` pela Onda 5 SIMPLIFICADA executada.

## 12. Próximos passos sugeridos

1. Wagner aprova este blueprint final (confirma B vs C híbrido)
2. Criar ADR filho 0170-onda5-simplificada
3. Confirmar 8 pré-condições (§3) — Wagner faz 4 cadastros manuais + tempo BCB homologar
4. Spawn `audit-implement-expert` por item §4 + §5 em paralelo OR sequencial (decidir baseado em risk surface)
5. Pest verde + canary biz=1 (Wagner ele mesmo) → biz=4 (Larissa beta) → universal

## 13. Refs

- [ADR 0170](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md) — PaymentGateway extração (Onda 5 §C versão original)
- [ADR 0017](../../decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md) — Officeimpresso restaurado
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [contrato-delphi-inviolavel.md](../../reference/contrato-delphi-inviolavel.md) — wire imutável (criado nesta sessão 2026-05-19)
- [project-officeimpresso-modulo.md](../../reference/project-officeimpresso-modulo.md) — sistema canônico Connector+Officeimpresso+Superadmin
- [whatsapp-permissions-spatie.md](../../reference/whatsapp-permissions-spatie.md) — pattern register-permissions
- [memory/sessions/2026-05-19-pesquisa-onda5-paymentgateway-dogfooding.md](../../sessions/2026-05-19-pesquisa-onda5-paymentgateway-dogfooding.md) — pesquisa A original
- [memory/sessions/2026-05-19-pesquisa-onda5-adendo-connector-superadmin.md](../../sessions/2026-05-19-pesquisa-onda5-adendo-connector-superadmin.md) — pesquisa B SIMPLIFICADA

---

**Status:** Plano APROVADO Wagner 2026-05-19 — execução pendente. Aguarda ADR filho + 8 pré-condições.

**Princípio:** PaymentGateway entra como **mais um gateway**, não como **refator do core**. Reuso máximo. Wire Delphi sagrado.
