---
slug: 0144-paymentgateway-extracao-camada-cobranca
number: 144
title: "Modules/PaymentGateway — extração da camada técnica de cobrança"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-05-19'
quarter: 2026-Q2
related:
  - '0079'
  - '0080'
  - '0093'
  - '0094'
  - '0101'
  - '0114'
  - '0143'
emends:
  - '0017'
pii: false
---

# ADR 0144 — `Modules/PaymentGateway`: extração da camada técnica de cobrança

**Status:** 🟡 Proposto (F0 brief — aguarda revisão Claude Code antes de virar aceito)
**Data:** 2026-05-19
**Decisão por:** Wagner Rocha (Cowork F0)
**Emenda:** ADR 0017 (Officeimpresso superadmin) — substitui modelo `Superadmin::Subscription` por dogfooding via RecurringBilling
**Não supersede:** ADRs 0093 (multi-tenant Tier 0), 0094 (constituição V2), 0143 (FSM pipeline)

---

## Contexto

Hoje a camada técnica de cobrança (drivers bancários, webhooks, credenciais, CNAB, retry) vive **dentro de `Modules/RecurringBilling`** — module cujo SCOPE.md se autodeclara "Cobrança recorrente BR — Pix Automático, smart retries, NFSe automática". Live biz=1 prod, 3 drivers (Inter / C6 / Asaas), Wave 23 D2.

Isso funcionou enquanto cobrança recorrente era o único cliente da camada. **Não funciona mais** porque:

1. **Cross-module reuse já está acontecendo informalmente.** O README do RecurringBilling diz literalmente:
   > "Resolução via `BoletoCredentialResolver` (Wave 23 D2 reuse cross-module Financeiro/NfeBrasil)."

   Ou seja: `Modules/Financeiro` e `Modules/NfeBrasil` já consomem internals de `Modules/RecurringBilling`. Isso fere encapsulamento — `RecurringBilling` deixou de ser um módulo de produto e virou bibliotecão de infra.

2. **Sells/PR drawer precisa emitir cobrança avulsa.** Sem refactor, Sell teria que importar `Modules\RecurringBilling\Services\Boleto\BoletoService` — coupling errado (Sell não vende assinatura, vende banner).

3. **Credenciais moram na tabela errada.** Campos `rb_gateway_credential_id`, `gateway_banco`, `gateway_ambiente`, `gateway_client_id` estão **coladas na tabela `accounts`** (vide `boleto-contas-app.jsx` e schema). Conta financeira e credencial de gateway são responsabilidades diferentes — conta é "onde dinheiro entra/sai", credencial é "como o ERP fala com banco".

4. **Conflito de nome com Superadmin.** `Modules/Superadmin/Entities/Subscription` ("venda de pacotes SaaS Oimpresso pra tenants") e `Modules/RecurringBilling/Entities/Subscription` ("assinatura recorrente que tenant vende pro cliente dele") são **duas tabelas com o mesmo nome**, mesmo significante, sujeitos diferentes. Comentário literal no `Superadmin/Subscription.php`:
   > "Subscriptions tocam pagamento de TODOS tenants (cross-tenant intencional Wagner-only)."

   Cada um tem fluxo de pagamento próprio: Superadmin usa `PesaPalController` (vestigial UltimatePOS pra cartão internacional) + `paid_via` manual; RecurringBilling usa Inter/C6/Asaas. **Dois caminhos para a mesma coisa** (cobrar mensalidade).

5. **PIX Automático BCB.** Wagner decidiu (F0 brief 2026-05-19) que PIX Automático será **driver direto BCB**, não passa por Inter/Asaas como abstração. Isso adiciona um 4º driver com regras próprias (Resolução BCB sobre PIX Automático, mandatos, CNPJ recebedor homologado) — peso suficiente pra justificar camada própria.

## Decisão

### A — Criar `Modules/PaymentGateway`

Módulo novo, Trust L3 (toca dinheiro + LGPD + regulação BCB), permission prefix `paymentgateway.*`.

**Conteúdo (extraído de `Modules/RecurringBilling`):**

```
Modules/PaymentGateway/
├── Entities/
│   ├── Cobranca.php                     ← nova (entity da cobrança, separada de Invoice)
│   ├── PaymentGatewayCredential.php     ← ex-BoletoCredential (renomeada + movida)
│   └── GatewayWebhookEvent.php          ← log idempotência
├── Services/
│   ├── Drivers/
│   │   ├── InterDriver.php              ← movido de RB
│   │   ├── C6Driver.php                 ← movido de RB
│   │   ├── AsaasDriver.php              ← movido de RB
│   │   ├── PixAutomaticoBcbDriver.php   ← NOVO (decisão Wagner #1)
│   │   └── PesaPalDriver.php            ← movido de Superadmin (vestigial,
│   │                                       deprecated, substituído por Inter+Pix)
│   ├── BoletoService.php                ← movido
│   ├── PixService.php                   ← NOVO (cob, cobv, recv)
│   ├── CardService.php                  ← NOVO
│   ├── RemessaCnabService.php           ← extraído se existir
│   ├── RetornoCnabService.php           ← extraído se existir
│   └── PaymentGatewayCredentialResolver.php  ← ex-BoletoCredentialResolver
├── Http/Controllers/
│   ├── PaymentGatewayController.php          ← UI Settings › Gateways
│   ├── CobrancaController.php                ← UI Financeiro › Cobrança
│   └── Webhooks/
│       ├── InterWebhookController.php        ← movido de RB
│       ├── C6WebhookController.php           ← movido de RB
│       ├── AsaasWebhookController.php        ← movido de RB
│       └── BcbPixWebhookController.php       ← NOVO
├── Events/
│   ├── CobrancaEmitida.php
│   ├── CobrancaPaga.php                 ← consumido por RecurringBilling + Sell + NFSe
│   ├── CobrancaVencida.php
│   ├── CobrancaCancelada.php
│   └── CobrancaErro.php
├── Contracts/
│   ├── PaymentGatewayContract.php       ← API pública do módulo
│   └── PaymentDriverContract.php        ← interface dos drivers
├── Dto/
│   ├── EmitirCobrancaInput.php
│   ├── CobrancaEmitidaResult.php
│   └── WebhookPayload.php
├── Jobs/
│   ├── ProcessInterWebhookJob.php       ← movido
│   ├── ProcessAsaasWebhookJob.php       ← movido
│   ├── ProcessBcbPixWebhookJob.php      ← NOVO
│   └── CancelarCobrancaJob.php
├── Database/Migrations/
│   ├── XXXX_create_payment_gateway_credentials_table.php
│   ├── XXXX_create_cobrancas_table.php
│   ├── XXXX_create_gateway_webhook_events_table.php
│   └── XXXX_migrate_boleto_credentials_to_payment_gateway_credentials.php
└── module.json + SCOPE.md + README.md + CONTRACTS.md
```

### B — `Modules/RecurringBilling` fica enxuto

**Permanece:**
- `Plan`, `Assinatura` (rename de `Subscription` pra evitar conflito), `Invoice`
- Lifecycle FSM (active/paused/canceled/overdue)
- Geração de Invoice via cron
- MRR/churn dashboards
- US-RB-044 NFe-de-boleto-pago (handler que escuta `CobrancaPaga` → emite NFSe)

**Sai:**
- Drivers Inter/C6/Asaas → PaymentGateway
- Webhooks → PaymentGateway
- BoletoService → PaymentGateway
- BoletoCredential → PaymentGateway (renomeada)
- BoletoCredentialResolver → PaymentGateway

**Vira consumidor** via `app(PaymentGatewayContract::class)`:
```php
// Antes (acoplado):
app(BoletoService::class)->emit($credencial, $titulo);

// Depois (contrato):
app(PaymentGatewayContract::class)
    ->for($account)
    ->emitirBoleto($cobrancaDto);
```

E escuta `CobrancaPaga` pra marcar Invoice como `paid` + atualizar `next_due_date`.

### C — Eliminar `Modules/Superadmin/Entities/Subscription` como source-of-truth

Wagner decisão #2 (F0 2026-05-19):
> "deveria entrar na empresa 1 que é a minha e ficar integrado com minha mensalidade, no meu caso empresa1 os clientes vão poder usar separado, isso é uma saas"

**Modelagem nova (dogfooding):**

- `business_id=1` é a Empresa "Oimpresso ERP" (Wagner) — tenant raiz.
- Tenants (Larissa, demais clientes SaaS) são **simultaneamente** `Business` (têm seu próprio ERP isolado, Tier 0) **E** `Contact` dentro de `business_id=1` (representam "este tenant é meu cliente SaaS").
- Wagner cadastra `Plan` "SaaS Premium R$ [redacted Tier 0]/mês" em RecurringBilling **dentro do `business_id=1`**.
- Pra cada tenant ativo, cria `Assinatura(business_id=1, contact_id={tenant_como_contact}, plan_id={saas_premium})`.
- Cron gera `Invoice` mensal → emite cobrança via `PaymentGateway` (PIX Automático BCB, decisão #1) → tenant paga.
- Webhook BCB → `CobrancaPaga` evento → handler **`SuperadminLicenseObserver`** (fica no Superadmin) escuta e atualiza `business.subscription_end_date += 1 month`.
- `Modules/Superadmin/Entities/Subscription` **vira view materializada** (projection alimentada por eventos), não source-of-truth. Mantida pra read-side (dashboard cross-tenant Wagner) + auditoria LGPD D7.b já existente.
- `PesaPalController` deprecated — substituído por PaymentGateway (decisão #3 Wagner: "pode substituir, desde que tenha as funções"). PesaPalDriver fica no módulo durante transição pra honrar Subscriptions antigas em sandbox.

**Bônus:** Wagner usa o próprio ERP pra cobrar o ERP. Bug em Plan/Invoice/Cobrança aparece pra ele primeiro (porque é cliente do seu próprio produto). Dogfooding sério.

### D — Contrato público `PaymentGatewayContract`

```php
namespace Modules\PaymentGateway\Contracts;

interface PaymentGatewayContract
{
    /** Seleciona account+credencial. Lança se não houver gateway ativo. */
    public function for(Account $account): self;

    /** Emite boleto avulso. Idempotente por chave (Sell ID, Invoice ID, etc). */
    public function emitirBoleto(EmitirCobrancaInput $input): CobrancaEmitidaResult;

    /** Emite PIX cobrança imediata (cob) ou com vencimento (cobv). */
    public function emitirPix(EmitirCobrancaInput $input, string $tipo = 'cob'): CobrancaEmitidaResult;

    /** Emite PIX Automático (recv) — mandato BCB. Só driver BcbPix. */
    public function emitirPixAutomatico(EmitirCobrancaInput $input): CobrancaEmitidaResult;

    /** Tokeniza + cobra cartão. Driver-dependent. */
    public function cobrarCartao(EmitirCobrancaInput $input, CardToken $token): CobrancaEmitidaResult;

    /** Cancela cobrança em aberto. */
    public function cancelar(Cobranca $cobranca, string $motivo): void;

    /** Consulta status no provedor (force refresh). */
    public function consultar(Cobranca $cobranca): CobrancaStatus;
}
```

Quem consome (Sell, RecurringBilling, Superadmin licensing) **só conhece este contrato** — drivers concretos nunca aparecem fora do módulo.

### E — Eventos broadcast

```
CobrancaEmitida   → RecurringBilling marca Invoice.boleto_id
                   → Sell marca sale.payment_status='aguardando_pagamento'

CobrancaPaga      → RecurringBilling marca Invoice.paid_at + reagenda
                   → NFSe escuta (US-RB-044) e emite NFSe automática (canônico irrevogável)
                   → Sell marca sale.payment_status='paid' + AccountTransaction
                   → Superadmin (handler license) renova subscription_end_date do tenant

CobrancaVencida   → RecurringBilling smart retry (3 retentativas)
                   → Subscription → overdue

CobrancaCancelada → RecurringBilling Invoice → canceled
                   → Sell sale.payment_status='cancelled'

CobrancaErro      → Otel alerta + healthcheck
                   → handler de retry decide se reemite ou para
```

### F — Multi-tenant Tier 0 herdado

PaymentGateway é Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) por construção:
- `payment_gateway_credentials.business_id` global scope
- `cobrancas.business_id` global scope
- Jobs recebem `businessId` no constructor (mesmo padrão dos jobs do RB)
- Webhooks resolvem `business_id` via `external_reference` ou domínio do callback URL antes de qualquer query

### G — Tabela `payment_gateway_credentials`

```php
Schema::create('payment_gateway_credentials', function (Blueprint $t) {
    $t->id();
    $t->unsignedInteger('business_id');
    $t->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
    $t->unsignedInteger('account_id')->nullable();
    $t->foreign('account_id')->references('id')->on('accounts')->onDelete('set null');
    $t->string('driver');  // inter | c6 | asaas | bcb_pix | pesapal
    $t->string('ambiente'); // sandbox | production
    $t->boolean('ativo')->default(true);
    $t->string('nome');  // ex: "Inter PJ Operacional"
    $t->json('config'); // client_id, client_secret(encrypted), mTLS cert(encrypted), webhook_secret(encrypted)
    $t->timestamp('last_health_check_at')->nullable();
    $t->string('last_health_status')->nullable();
    $t->softDeletes();
    $t->timestamps();
    $t->index(['business_id', 'driver', 'ativo']);
});
```

Migration de backfill: lê campos `rb_gateway_credential_id`, `gateway_*` da tabela `accounts`, popula `payment_gateway_credentials`, deixa `accounts.payment_gateway_credential_id` apontando pra novo PK. Colunas antigas em `accounts` viram nullable, depreciated em PR separado, removidas em onda futura.

### H — Tabela `cobrancas`

Entity separada de `transactions` e `invoices`. Razão: cobrança é "intent de receber dinheiro através de gateway" — tem ciclo de vida próprio (emitida → registrada → paga | vencida | cancelada | erro) e pode existir sem Sell (cobrança avulsa) e sem Invoice recorrente. Sell e Invoice **referenciam** `cobranca_id`, não o contrário.

```php
Schema::create('cobrancas', function (Blueprint $t) {
    $t->id();
    $t->unsignedInteger('business_id');
    $t->unsignedBigInteger('payment_gateway_credential_id');
    $t->string('origem_type')->nullable();  // 'sale' | 'invoice' | 'subscription_license' | null (avulsa)
    $t->unsignedBigInteger('origem_id')->nullable();
    $t->unsignedInteger('contact_id');  // pagador
    $t->decimal('valor', 22, 4);
    $t->date('vencimento');
    $t->string('tipo'); // boleto | pix_cob | pix_cobv | pix_recv | card
    $t->string('status'); // pending | emitida | paga | vencida | cancelada | erro
    $t->string('nosso_numero')->nullable();
    $t->string('linha_digitavel')->nullable();
    $t->string('codigo_barras')->nullable();
    $t->string('pix_emv')->nullable();
    $t->string('pix_qr_code_path')->nullable();
    $t->string('gateway_external_id')->nullable(); // ID no Inter/Asaas/BCB
    $t->timestamp('emitida_em')->nullable();
    $t->timestamp('paga_em')->nullable();
    $t->timestamp('cancelada_em')->nullable();
    $t->text('cancelamento_motivo')->nullable();
    $t->json('payload_gateway')->nullable(); // request/response do gateway
    $t->softDeletes();
    $t->timestamps();
    $t->index(['business_id', 'status', 'vencimento']);
    $t->index(['business_id', 'origem_type', 'origem_id']);
    $t->unique(['business_id', 'gateway_external_id']);
});
```

## Roadmap — 6 ondas

| Onda | O quê | Pode quebrar prod? | Reversível? |
|---|---|---|---|
| **0 · ADR + SCOPE** (este PR) | ADR 0144 + SCOPE.md + README.md + CONTRACTS.md em `Modules/PaymentGateway/` (vazio, só docs) | Não | Trivial |
| **1 · Esqueleto** | `Modules/PaymentGateway/` com module.json + ServiceProvider + Contracts + DTOs + Events. Eventos disparados em modo "shadow" (a partir do RB existente) | Não | Sim, flag |
| **2 · Migrar credentials** | Cria `payment_gateway_credentials`. Backfill de `accounts.*gateway*`. RB lê da nova tabela via Resolver. Colunas antigas em `accounts` viram nullable + deprecated | Médio (DDL) | Reverte migration + restaurar `rb_gateway_credential_id` |
| **3 · Migrar webhooks** | `InterWebhookController` / `C6Webhook` / `AsaasWebhook` movem. URLs antigas → 301 redirect (padrão Onda 10 RB). Eventos disparados | Alto risco — testar sandbox **antes** | Manter rotas duplicadas durante 30d |
| **4 · Mover domínio** | `BoletoService` / `BoletoCredentialResolver` movem. `PixService` + `PixAutomaticoBcbDriver` novos. Tela `Financeiro › Cobrança` (Cockpit) lê de `cobrancas`. Sell drawer ganha botão "Emitir cobrança" | Alto — Larissa vê tela nova | Flag `feature.cobranca_v2` rollback rota antiga |
| **5 · Dogfooding Superadmin** | `Plan` "SaaS Oimpresso Premium" em biz=1. Tenants viram Contact em biz=1. `Superadmin::Subscription` vira projection. PesaPal deprecated | Médio — afeta cobrança SaaS de Wagner ↔ tenants | Manter Subscription source-of-truth via flag |
| **6 · Cleanup** | Remover colunas `rb_gateway_credential_id`, `gateway_*` de `accounts`. Remover `PesaPalController` legacy. Remover redirects 301 | Baixo se ondas 2-5 estáveis 90d | Apenas DDL forward |

Cada onda = PR isolada + ADR per-onda (filha desta) + smoke test biz=1 antes de promover.

## Consequências

### Boas

- **Encapsulamento real** — Sell/NFSe/Financeiro/RB/Superadmin consomem contrato, não internals
- **PIX Automático BCB** — driver dedicado, regulação isolada (Resolução BCB 380/2024 sobre PIX Automático)
- **Dogfooding** — Wagner usa próprio ERP pra cobrar o ERP. Bug aparece pra ele primeiro
- **Eliminação de duplicação semântica** — só existe um `Subscription` (em RB, renomeado pra `Assinatura`). Superadmin::Subscription vira projection
- **Auditoria mais simples** — uma só tabela `cobrancas` registra TUDO que foi cobrado, independente de origem (Sell, Invoice recorrente, mensalidade SaaS)
- **Compliance isolada** — PCI-DSS (cartão) + LGPD (CPF pagador) + segredos de API moram num módulo, audit fica em escopo conhecido
- **Driver per banco** — Inter v3 quebra → mexe só em `InterDriver`. Hoje muda em RB inteiro

### Ruins / mitigações

- **6 ondas = trabalho denso de 8-12 semanas.** Mitigação: cada onda tem valor isolado (Onda 2 já desacopla credencial de `accounts`). Pode pausar entre ondas
- **US-RB-044 NFSe-de-boleto-pago é canônico irrevogável** ([README RB](../../Modules/RecurringBilling/README.md)). Mitigação: virar listener de evento `CobrancaPaga` no módulo NFSe (passive coupling), contrato preservado
- **Sell hoje não tem cobrança avulsa.** Mitigação: feature nova na Onda 4, opt-in via flag; comportamento antigo permanece
- **Risco de Onda 3 (webhooks) em produção.** Mitigação: deploy em modo shadow primeiro (webhook duplicado, RB ignora se já processado), 7d observação, depois cutover
- **Superadmin::Subscription como projection** quebra invariantes existentes da auditoria LGPD. Mitigação: append-only mantido via `superadmin.subscription` activity_log no handler de evento

## Plano de aplicação (Onda 0 — este PR)

1. **PR atual:**
   - [ ] `Modules/PaymentGateway/SCOPE.md`
   - [ ] `Modules/PaymentGateway/README.md`
   - [ ] `Modules/PaymentGateway/CONTRACTS.md` (interfaces + events + DTOs)
   - [ ] `Modules/PaymentGateway/module.json` (não habilitado ainda — apenas registrado)
   - [ ] `memory/decisions/0144` (este arquivo)
   - [ ] CLAUDE.md adiciona Modules/PaymentGateway ao mapa
   - [ ] AUDITORIA_MODULOS.md adiciona PaymentGateway / F1 / phase=2 status=later

2. **Cowork next:** primeira tela F1 `Cobrança` (UI no Cockpit) — substitui `boletos` na sidebar do protótipo, com badges Inter/C6/Asaas/PIX. Pedido entra em `COWORK_NOTES.md` depois deste ADR mergeado.

3. **Próximo ADR (0116):** detalhamento Onda 1 (esqueleto Module + ServiceProvider + Contracts) com critérios de sucesso e checklist de migração.

4. **Health checks:** adicionar `payment_gateway` em `jana:health-check` quando Onda 1 mergear:
   - `payment_gateway.credentials_ativas`
   - `payment_gateway.last_emitida_freshness`
   - `payment_gateway.webhook_idempotency`
   - `payment_gateway.driver_resolvidos` (4 drivers responsivos no health check)

## Refs

- [ADR 0017](0017-officeimpresso-restaurado-superadmin-exclusivo.md) — Superadmin exclusivo (emendado por este)
- [ADR 0079](0079-constituicao-oimpresso-7-camadas-governanca.md) — 7 camadas governança
- [ADR 0080](0080-trust-tiers-operacional-audit-findings.md) — Trust tiers L1-L4
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição V2
- [ADR 0101](0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1
- [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) — Loop Cowork formal
- [ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM pipeline pattern
- [Modules/RecurringBilling/README.md](../../Modules/RecurringBilling/README.md) — origem da extração
- [Modules/RecurringBilling/SCOPE.md](../../Modules/RecurringBilling/SCOPE.md)
- [Modules/Superadmin/Entities/Subscription.php](../../Modules/Superadmin/Entities/Subscription.php) — eliminada como source-of-truth
- Resolução BCB 380/2024 — PIX Automático (regulamentação driver `bcb_pix`)

---

**Última atualização:** 2026-05-19 · F0 brief Wagner+Cowork
