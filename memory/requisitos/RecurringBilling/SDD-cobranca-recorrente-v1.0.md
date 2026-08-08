---
id: requisitos-recurring-billing-sdd-cobranca-recorrente-v1-0
slug: recurring-billing-sdd
title: "SDD — Cobrança recorrente (domínio RecurringBilling)"
type: sdd
module: RecurringBilling
status: ativo
owner: W
version: 1.0.1
last_updated: "2026-08-05"
related_docs:
  - SPEC.md
  - BRIEFING.md
  - SUPERFICIE.md
  - RUNBOOK-inter-pj.md
  - CAPTERRA-FICHA.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
  - 0170-paymentgateway-extracao-camada-cobranca
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0351-sdd-from-source
related_us:
  - US-RB-001
  - US-RB-002
  - US-RB-003
  - US-RB-004
  - US-RB-005
  - US-RB-041
  - US-RB-042
  - US-RB-043
  - US-RB-044
  - US-RB-045
  - US-RB-046
  - US-RB-047
  - US-RB-051
  - US-RB-052
  - US-RB-056
---

# SDD — Software Design Document · Cobrança recorrente (domínio RecurringBilling)

> **O que é:** o mapa de cima do módulo. O [`SPEC.md`](SPEC.md) guarda as US, os `*.charter.md` guardam
> a lei por tela, os `*.casos.md` guardam o contrato de teste. Este SDD amarra os três e é **de onde o
> UC deriva** — nunca do `.tsx` ([proibicoes §5](../../proibicoes.md) 2026-06-05: teste derivado do
> código é tautológico).
>
> **Como nasceu (2026-07-28):** derivado pelo agent [`sdd-from-source`](../../../.claude/agents/sdd-from-source.md)
> ([ADR 0351](../../decisions/0351-sdd-from-source.md)), formato imitado do
> [SDD do Produto](../Produto/SDD-tela-cadastro-produto-v1.0.md) via
> [SDD-TEMPLATE](../_DesignSystem/SDD-TEMPLATE.md). É o **primeiro SDD criado do zero** neste repo — o
> do Produto foi escrito à mão 10 dias antes do agent existir.
>
> **Triangulação — declaração honesta das fontes:**
>
> | # | Fonte | Estado neste módulo |
> |---|---|---|
> | 1 | Documentação canon (`SPEC.md` 29 US · 6 charters · 20 ADRs de módulo · `RUNBOOK-inter-pj.md`) | ✅ rica — é a âncora |
> | 2 | React/Laravel atual (11 controllers · 6 services · 6 jobs · 6 `.tsx`) | ✅ lido |
> | 3 | Blade AdminLTE legada | ⚠️ **não existe mais** — o cutover foi feito (Onda 10): `Routes/web.php` redireciona `/recurringbilling` → `/recurring-billing` **301** e a view legada era literalmente *"Hello World"* (`Index.charter.md` §Mission). **Não há paridade Blade a preservar.** |
> | 4 | Delphi / Office Comercial (`ANTI-REGRESSAO-*.md`) | ❌ **NÃO EXISTE** — `find memory -iname "*ANTI-REGRESSAO*"` devolve 2 arquivos, **ambos do Produto**. Cobrança recorrente **não tem equivalente no WR Comercial** (o legado Delphi emitia boleto avulso, não assinatura). **Gap declarado, não inventado.** |
>
> ⚠️ **Consequência da fonte 4 ausente:** o contrato de paridade deste módulo é **mais fraco** que o do
> Produto. O que defende contra regressão aqui é o SPEC + os charters + os 39 testes Pest do módulo —
> não um manual de legado. Quando um comportamento sumir, **não haverá fonte externa que denuncie**.

---

## 0. Base empírica ⚙️ derivado (do inventário) + 🖐 curado (a leitura)

<!-- curado: foto que envelhece -->

**De onde vem o retrato:** [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) + [`CAPTERRA-INVENTARIO.md`](CAPTERRA-INVENTARIO.md)
(2026-05-06) + [`AUDITORIA-PAYMENTGATEWAY-2026-07.md`](../PaymentGateway/AUDITORIA-PAYMENTGATEWAY-2026-07.md).
**Não repito aqui os números que aqueles docs sabem melhor** ([proibicoes §5](../../proibicoes.md) 2026-07-17
— fact-anchor); consulte a fonte.

### 0.1 O que o benchmark expôs (leitura adversarial)

- **O commodity está pronto, o diferencial está meio-pronto.** Gateway de boleto/PIX é commodity
  (Iugu, Asaas, Vindi, Pagar.me têm). O que o `CAPTERRA-INVENTARIO` #6 chamou de **diferencial
  cross-vertical** é *"boleto pago → NFe modelo 55 emitida automaticamente sem clique humano"*
  (**US-RB-044**) — e ele existe em código, mas atrás de flag `nfebrasil.auto_emission_on_invoice_paid`
  **default `false`**.
- **A recorrência nasceu depois da cobrança avulsa.** O módulo começou como emissor de boleto
  (drivers Inter/C6/Asaas) e ganhou o domínio recorrente (`rb_plans`/`rb_subscriptions`/`rb_invoices`/
  `rb_charge_attempts`) na **US-RB-043**. Isso explica a costura irregular do §5.4.
- **O gateway saiu de casa.** A [ADR 0170](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md)
  extraiu `Modules/PaymentGateway`; hoje ele tem código real (drivers + webhooks + testes) mas
  **flags OFF em prod**. Ver §5.5 — o que já mora lá **não se duplica aqui**.

### 0.2 Recibo de medição (datado — re-rode, não edite o número)

| Fato | Porta que mediu · 2026-07-28 |
|---|---|
| 6 telas · 0 `casos.md` (antes deste PR) · 29 US · 0 CU | `node scripts/governance/requisitos-status.mjs RecurringBilling` |
| 39 arquivos de teste em `Modules/RecurringBilling/Tests/Feature` | `find Modules/RecurringBilling/Tests -name "*.php" \| wc -l` |
| `anchor_coverage` 97,4% · 28 US sem teste que a cobre | `node scripts/governance/anchor-lint.mjs memory/requisitos/RecurringBilling/SPEC.md` |
| trio de `RecurringBilling/Index`: `.tsx` ✓ · charter ✓ · casos ✗ | `npm run screen:files -- RecurringBilling/Index` |

---

## 1. Visão geral ⚙️ derivado

<!-- derivado: re-rodável do fonte -->

**O que é:** o subsistema que transforma *"este cliente paga R$ X todo mês"* em **fatura gerada,
cobrada no banco, baixada por webhook e (opcionalmente) documentada por NFe** — sem ninguém digitar
boleto a boleto.

**Fronteira do módulo** (fonte: [`SUPERFICIE.md`](SUPERFICIE.md) + `Routes/web.php`):

- **Dentro:** planos, assinaturas, faturas, tentativas de cobrança, drivers de boleto/PIX
  (Inter/C6/Asaas), webhooks Asaas e Inter, saldo/extrato Inter PJ.
- **Fora:** conciliação bancária e DRE (→ `Modules/Financeiro`) · emissão fiscal (→ `Modules/NfeBrasil`,
  consumindo o evento `InvoicePaid`) · cadastro de credencial de gateway (hoje ainda em
  `/financeiro/contas-bancarias`, ver §5.4).

### 1.1 Família de telas (prefixo `/recurring-billing`)

| Tela | Rota | Papel | Charter |
|---|---|---|---|
| `Index` | `/recurring-billing` | **porta de entrada** — lista de assinaturas 3-col + drawer + timeline | `Index.charter.md` |
| `Faturas/Index` | `/recurring-billing/faturas` | faturas individuais (`rb_invoices`) + cancelar | `Faturas/Index.charter.md` |
| `Planos/Index` | `/recurring-billing/planos` | catálogo de planos + KPIs + excluir protegido | `Planos/Index.charter.md` |
| `Planos/Create` | `/recurring-billing/planos/novo` | form de criação | `Planos/Create.charter.md` |
| `Planos/Edit` | `/recurring-billing/planos/{id}/editar` | form de edição | `Planos/Edit.charter.md` |
| `Configuracoes/Index` | `/recurring-billing/configuracoes` | **read-only** — gateways, régua de dunning, NFe auto, webhooks | `Configuracoes/Index.charter.md` |

> A **criação de assinatura** não tem tela própria: é um drawer (Sheet 760px) dentro do `Index`,
> aberto por `?new=1` / atalho `N` / CTA do header (Onda 21).

---

## 2. Público-alvo e personas 🖐 curado — [W] valida

<!-- curado: foto que envelhece -->

### P1 · Wagner — WR2 SC (biz=1) · **operador-dono e o único usuário real hoje**
Cobra assinaturas dos próprios clientes. É quem configura credencial de gateway, lê o extrato Inter PJ
e decide ativar flag (`ASAAS_REFUND_ENABLED`, `nfebrasil.auto_emission_on_invoice_paid`). **É também a
cobaia segura** dos testes ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).

### P2 · Eliana [E] — financeiro/jurídico
Consome a tela de Faturas pra saber quem está em atraso; opera cancelamento de título (exigência
Procon/LGPD — não pode depender de SQL). Ver [`memory/onboarding/team/eliana-financeiro.md`](../../onboarding/team/eliana-financeiro.md).

### P3 · Larissa — ROTA LIVRE (biz=4, vestuário) · **beneficiária indireta**
Não assina recorrência hoje. É a destinatária do **diferencial** US-RB-044 (NFe automática ao pagar).

> ⚠️ **Sinal de uso medido, não presumido:** o `anchor-lint --servido` (ledger `governance/route-hits.json`,
> janela 30d, lido 2026-07-28) reporta **5 US wired porém com 0 hits** — incluindo `US-RB-001`
> (`Planos/Index`), `US-RB-002` (`Index`) e `US-RB-042` (`Faturas/Index`). **As telas existem, estão
> roteadas e ninguém as abriu na janela.** Isso é dado, não julgamento: define a prioridade de risco
> (§9) e é o argumento mais forte pra **não** inflar UC aqui ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

---

## 3. Governança aplicável ⚙️ derivado

<!-- derivado: re-rodável do fonte -->

### 3.1 Tier 0 que morde AQUI

| Regra | Onde morde neste módulo |
|---|---|
| **REGRA MESTRE valor/estoque** ([proibicoes](../../proibicoes.md)) | `InvoiceGeneratorService` (gera fatura), `AssinaturaService::atualizarCobranca` (muda valor), `RefundCobrancaAsaasJob` (devolve dinheiro), `BoletoService` (emite título). Todo CU marcado `[V0]` exige **dupla confirmação por 2 caminhos + tabela antes→depois + OK humano** antes de mergear. |
| **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | `business_id` **da sessão, nunca do request** — o webhook é o ponto mais exposto: é rota **pública** (`/webhooks/asaas/{businessId}`), então quem isola o tenant é o `hash_equals` do secret da credencial daquele business, não o global scope. |
| **Tests biz=1** ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) | cross-tenant sempre biz=1 vs biz=99. **Nunca biz=4.** |
| **Refund atrás de flag** ([proibicoes](../../proibicoes.md) §FSM) | `POST /v3/payments/{id}/refund` **só** com `ASAAS_REFUND_ENABLED=true`; default `false` → job só loga TODO. |
| **LGPD opt-in antes de notificar** ([proibicoes](../../proibicoes.md) §FSM) | `Contact::canReceiveEmailNotification()` / `canReceiveWhatsappNotification()` — NULL permite (back-compat), FALSE bloqueia + log. ⚠️ **Ver o achado do §9.3.** |
| **PII nunca em log/PR** | `numero_documento`/`gateway_ref` podem ir; CPF/CNPJ do pagador **não**. |

### 3.2 Processo de mudança
Charter é lei da tela; onde discordarem, a precedência é **teste verde > casos > charter > SPEC**
([proibicoes](../../proibicoes.md) §Precedência) e o perdedor se corrige no MESMO PR.
**Non-Goals e Anti-hooks do charter só [W] preenche** — o agente é proibido de inferir.

---

## 4. Design system aplicável ⚙️ derivado

<!-- derivado: re-rodável do fonte -->

- **Duas linguagens visuais convivem por decisão registrada:** `Index.tsx` usa o **bundle Cowork
  escopado** (`.rec-cowork`, cópia literal do protótipo `cobranca-recorrente-page.jsx`); as outras 5
  telas usam **Tailwind 4 puro**. Os charters declaram isso explicitamente
  (`Configuracoes/Index.charter.md`: *"zero CSS Cowork escopado; esta tela é simpler"*). **Não é drift.**
- **Padrão de tela:** `Index` = list-detail 3 colunas ([ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md));
  `Faturas`/`Planos` = PT-01 Lista; `Create`/`Edit` = form dedicado (charter proíbe modal).
- **`Inertia::defer` em props caras** é contrato dos 4 charters de lista (skill `inertia-defer-default`).
- ⚠️ **Armadilha de portal já catalogada** ([proibicoes §5](../../proibicoes.md) 2026-07-10): o drawer é
  `<Sheet>` do Radix, que **portala pro `<body>`, FORA do `.rec-cowork`**. Token redeclarado no bloco
  do wrapper é **defesa consciente**, não duplicação a "limpar".

---

## 5. Arquitetura ⚙️ derivado

<!-- derivado: re-rodável do fonte -->

> **Âncoras por SÍMBOLO, não por linha** ([ADR 0351](../../decisions/0351-sdd-from-source.md) Fase 2.2):
> `Grep` do símbolo re-localiza depois de qualquer refactor; `arquivo:NNN` viraria mentira no primeiro.

### 5.1 Visão em camadas

```
resources/js/Pages/RecurringBilling/**.tsx        (Inertia + React 19)
        ↕  Inertia::render / router.post
Modules/RecurringBilling/Http/Controllers/**       (11 controllers)
        ↕
Modules/RecurringBilling/Services/**               (AssinaturaService · AssinaturaCobrancaService ·
                                                    InvoiceGeneratorService · GatewayBackfillService ·
                                                    Boleto/BoletoService + Drivers · Banking/**)
        ↕
Modules/RecurringBilling/Models/**                 (Plan · Subscription · Invoice · ChargeAttempt ·
                                                    SubscriptionEvent — todos HasBusinessScope)
        ↕
rb_plans · rb_subscriptions · rb_invoices · rb_charge_attempts · rb_subscription_events ·
pg_webhook_events · account_transactions (legado UltimatePOS)
```

Jobs assíncronos (fila): `ProcessAsaasWebhookJob` · `ProcessInterWebhookJob` ·
`CancelarCobrancaAsaasJob` · `RefundCobrancaAsaasJob` · `SyncBankBalancesJob` · `SyncBankStatementsJob`.
**Todo job recebe `$businessId` no construtor** — `session()` não existe em fila ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

### 5.2 Modelo de dados (núcleo)

| Tabela | Colunas que importam | Onde mora o `business_id` |
|---|---|---|
| `rb_plans` | `name`, `slug` (unique por business), `valor`, `ciclo`, `trial_days`, `ativo`, `fiscal_type`, SoftDeletes | coluna + `HasBusinessScope` |
| `rb_subscriptions` | `plan_id` **(nullable)**, `contact_id`, `status`, `next_due_date`, `billing_anchor_date`, `payment_method`, `conta_bancaria_id` (override de gateway), `metadata` (JSON: `valor`/`ciclo`/`gateway`), `canceled_at`, `churn_reason` | coluna + scope |
| `rb_invoices` | `subscription_id`, `numero_documento` (`RB-{sub}-{YYYY-MM}`), `valor`, `status` (`open/paid/overdue/canceled/refunded`), `vencimento`, `gateway`, `gateway_ref`, `conta_bancaria_id` | coluna + scope |
| `rb_charge_attempts` | `invoice_id`, `attempt_n`, `status`, `response_json` | coluna |
| `rb_subscription_events` | `kind`, `by_actor`, `body`, `occurred_at` — **append-only** (timeline) | coluna |
| `pg_webhook_events` | `provider`, `event_id` — **UNIQUE(provider, event_id)** = a idempotência | coluna |

> 🔑 **`rb_subscriptions.plan_id` é NULLABLE.** Guarde este fato: é a raiz do F2/§9.1.

### 5.3 Fluxos críticos

---

**F1 — Criar assinatura pelo drawer** `[V0]` `[T0]`

`Index.tsx` (drawer `?new=1`) → `POST /recurring-billing` → `RecurringBillingController@store`.

1. `Gate::authorize('create', Subscription::class)` (`SubscriptionPolicy`).
2. `$businessId = (int) session('user.business_id')` — **da sessão, nunca do request**.
3. `StoreAssinaturaRequest` valida: `valor` `numeric|min:0.01`, `ciclo ∈ {mensal,trimestral,semestral,anual}`,
   `data_proxima_cobranca` `after_or_equal:today`, `gateway ∈ {asaas,inter}`,
   `forma_pagamento ∈ {boleto,pix,cartao}`.
4. **`resolvePlanIdFromCiclo()`** procura um `Plan` ativo do business cujo **`ciclo` E `valor` batam
   exatamente** com o digitado. Não achou → `plan_id = null`.
5. `Subscription::create([... 'status' => 'active', 'metadata' => ['valor' => …, 'ciclo' => …, 'gateway' => …]])`.

> ⚠️ **Dois desvios do contrato declarado, ambos no passo 4/5:**
> **(a)** o `status` é **hardcoded `'active'`** — a DoD da `US-RB-002` exige `trialing` quando o plano
> tem trial (o próprio SPEC já confessa isso em `Implementado em: _parcial_`);
> **(b)** o **valor digitado vira `metadata.valor`** (JSON), e o valor que **fatura** é
> `plan.valor` (F2). Ver §9.1 — é o achado `[V0]` deste SDD.

---

**F2 — Gerar faturas do ciclo (job)** `[V0]`

`php artisan recurring-billing:generate-invoices` → `GenerateInvoicesCommand@handle` →
`InvoiceGeneratorService::run($businessId, $date, $dryRun, $leadDays)`.

1. Candidatas: `Subscription` do business com `status='active'` e `next_due_date <= hoje + leadDays`.
2. **`processarSubscription`: se `$sub->plan === null` → `errors++` + `Log::error('subscription sem plan')`
   e RETORNA.** Nenhuma fatura, nenhum evento na timeline, **nenhum alarme** — só uma linha de log.
3. Idempotência por **competência (`YYYY-MM` do vencimento)**: já existe `Invoice` não-cancelada
   naquele mês → `skipped++`.
4. Cria `Invoice` com **`valor = plan.valor`** (⚠️ **não** `subscription.metadata.valor`),
   `numero_documento = RB-{sub}-{YYYY-MM}`, herda `conta_bancaria_id` da assinatura.
5. Avança `next_due_date` via `avancarCiclo()` — **`addMonthsNoOverflow`** (dia 31 → 28/29 em fev,
   não transborda pra março) e vocabulário **EN** (`monthly/quarterly/…`).
6. Grava `SubscriptionEvent kind=charge` (timeline append-only). Passos 4-6 numa `DB::transaction`.

> 🔒 **Este fluxo já tem dupla-confirmação `[V0]` escrita:** `tests/Feature/Calculo/CalculoRecurringBillingTest.php`
> (fora do módulo, na lane `tests/Feature`) trava tanto a cópia `plan.valor → invoice.valor` quanto o
> avanço NoOverflow, e **documenta as 3 implementações divergentes de "próximo vencimento"**
> (`InvoiceGeneratorService` = fonte de verdade, NoOverflow/EN; as outras duas = Overflow/PT). A
> unificação é a **US-RB-056**, ainda aberta.

---

**F3 — Editar cobrança da assinatura** `[V0]` `[T0]`

`Index.tsx` (drawer) → `PUT /recurring-billing/{id}` → `RecurringBillingController@update` →
`AssinaturaService::atualizarCobranca`.
`loadOwnedOrFail()` escopa por `session('user.business_id')` → **404 cross-tenant**;
`Gate::authorize('update', $sub)`; assinatura cancelada → **422**.

---

**F4 — Cancelar / pausar / reativar assinatura**

`POST /recurring-billing/{id}/{cancelar|pausar|reativar}` → `RecurringBillingController`.
Cancelar: `status='canceled'` + `canceled_at=now()` + `churn_reason`; **idempotente** (já cancelada → 422).
Auditoria por `LogsActivity` (Spatie).
⚠️ **Falta vs DoD da US-RB-005** (o SPEC já declara): enum `tipo_cancelamento` (`fim_ciclo`/`imediato`),
status `canceled_at_period_end`, `credit_note` do saldo restante, e o evento `ContractCanceled`
(`Events/` só tem `AssinaturaAtualizada` + `InvoicePaid`).

---

**F5 — Cancelar fatura no gateway** `[V0]` `[T0]`

`Faturas/Index.tsx` → `POST /financeiro/rb-invoices/{invoice}/cancelar` → `InvoiceController@cancel`
→ `AssinaturaCobrancaService::cancelInvoice` → `BoletoService::cancelar` → driver.

1. **Permissão `recurringbilling.invoice.cancel`** — sem ela, **403** antes de qualquer coisa.
2. `Invoice::where('business_id', …)->whereKey(…)->firstOrFail()` → **404 cross-tenant**.
3. Máquina de estados do cancelamento — **este é o contrato**:
   - já `canceled` → `ok:true, skipped:'already_canceled'`, **sem chamar o gateway** (idempotente);
   - `paid` → **422** *"Invoice já paga. Use estorno em vez de cancelamento."* (**nunca** cancela paga);
   - sem `gateway`/`gateway_ref` (nunca foi cobrada) → marca `canceled` **local**, `gateway_call:false`;
   - caso normal → `DB::transaction` chama o driver e marca `canceled`.
4. `activity('recurringbilling.invoice')` **sempre** — sucesso E falha.

> ⚠️ **`C6Driver::cancelar()` é STUB que lança `BadMethodCallException`** (CNAB ocorrência 02 não
> implementado) — o próprio SPEC declara em `US-RB-042`. Inter e Asaas funcionam.

---

**F6 — Receber webhook do gateway (baixa de pagamento)** `[T0]` `[V0]`

`POST /webhooks/asaas/{businessId}` (rota **pública**, `throttle:60,1`) → `AsaasWebhookController@handle`.

1. **Autenticidade ANTES de qualquer processamento:** carrega a `BoletoCredential` Asaas **ativa**
   daquele business; sem credencial → **404**; compara o header `asaas-access-token` com
   `config_json.webhook_secret` por **`hash_equals`** → não bate → **401**.
   *É aqui que mora o isolamento multi-tenant desta rota:* o token do business A nunca casa com o
   secret do B — o global scope não ajuda numa rota sem sessão.
2. **Idempotência:** `pg_webhook_events` com `UNIQUE(provider, event_id)`; `event_id` repetido → 200
   **sem dispatch**. Quando o Asaas não manda id, deriva `md5(event + payment.id)` (determinístico).
3. Responde **200 imediato** e joga `ProcessAsaasWebhookJob` na fila `rb_webhooks` (at-least-once —
   [ADR tech/0002](adr/tech/0002-webhook-asaas-at-least-once-resposta-rapida.md)).
4. O job credita com `insertOrIgnore` em `account_transactions` → reprocessar não duplica dinheiro.

Análogo para o Inter: `POST /webhooks/inter/pix/{businessId}` → `InterWebhookController` →
`ProcessInterWebhookJob`.

---

**F7 — Estornar cobrança (refund)** `[V0]`

`RefundCobrancaAsaasJob` → `POST /v3/payments/{id}/refund`.
**Guarda Tier 0:** `config('services.asaas.refund_enabled')` (env `ASAAS_REFUND_ENABLED`), **default
`false`** — desligado, o job **não chama a API**, só loga `warning` com o TODO. Também: charge já
`REFUNDED` → no-op; `businessId` divergente do documento → `RuntimeException`;
`doc_type != boleto_asaas` → `RuntimeException`.

---

**F8 — NFe automática ao pagar (o diferencial)** — **cruza a fronteira do módulo**

`InvoicePaid` (evento **daqui**) → `Modules/NfeBrasil/Listeners/EmitirNFeAoReceberPagamento` →
`NfeService::emitirParaInvoice` → SEFAZ → `NFeAutorizada` → `EnviarDanfePorEmail`.
Idempotência por `transaction_id = invoice.id`. Flag `nfebrasil.auto_emission_on_invoice_paid`
**default `false`**. Falha SEFAZ **não derruba o pagamento** (re-throw → retry da fila).

---

**F9 — Saldo e extrato Inter PJ**

`SyncBankBalancesJob` / `SyncBankStatementsJob` (cron) → `Services/Banking/**` → Banking API v2 (OF
direto, mTLS com certificado **separado** do certificado de NFe — [ADR tech/0005](adr/tech/0005-certificado-inter-separado-nfe-certificados.md))
→ `saldo_cached` / extrato → tela `/financeiro/extrato`. Operação documentada no
[`RUNBOOK-inter-pj.md`](RUNBOOK-inter-pj.md).

### 5.4 Dívida — onde as camadas ainda não se conversam

| # | Dívida | Evidência |
|---|---|---|
| D1 | **`metadata.valor` ≠ `plan.valor`** — o valor que o operador digita não é o que fatura | F1 passo 5 × F2 passo 4 |
| D2 | **3 implementações de "próximo vencimento"** (NoOverflow/EN × Overflow/PT ×2) | `SPEC.md` US-RB-056 + `CalculoRecurringBillingTest` |
| D3 | **Cadastro de credencial de gateway mora fora** (`/financeiro/contas-bancarias`); `Configuracoes` só **lê** | `Configuracoes/Index.charter.md` §Non-Goals |
| D4 | **Régua de dunning é hardcoded** (3/7/15d) — não há `rb_dunning_rules` per-business | idem |
| D5 | **`C6Driver::cancelar()` é stub** que lança exceção | `SPEC.md` US-RB-042 |
| D6 | **Sem endpoint de charge dedicado** (`POST /invoices/{id}/charge` da US-RB-004 não existe); cobrança acontece por geração de boleto/PIX + baixa por webhook | `SPEC.md` US-RB-004 |
| D7 | **Sem proração** em upgrade/downgrade mid-cycle (US-RB-006) e sem reajuste no aniversário | `SPEC.md` US-RB-003/006 |
| D8 | `InvoiceController@cancel` lê `session('business.id')`; **todo o resto do módulo** lê `session('user.business_id')`. Ambas as chaves existem (`SetSessionData` grava as duas) — **não é bug medido**, é inconsistência de vocabulário que confunde quem lê | `InvoiceController@cancel` × `RecurringBillingController`/`PlanController` |

### 5.5 Fronteira com `Modules/PaymentGateway` ([ADR 0170](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md))

O PaymentGateway foi **extraído deste módulo**. O que **já mora lá** — e portanto **não se duplica
aqui**: o processamento genérico de webhook com linkage `cobranca_id`
(`PaymentGateway/Tests/Feature/WebhookProcessorLinkageTest.php`), o retry de webhook órfão
(`RetryOrphanWebhookJobTest.php`) e a tabela `pg_webhook_events`. Ambos **já rodam na lane per-PR**
(`.github/ci-sqlite-pest.list`). **Flags OFF em prod** — ver `AUDITORIA-PAYMENTGATEWAY-2026-07.md`.

---

## 6. Casos de uso ⚙️+🖐 — agente propõe, **[W] confere**

<!-- derivado: re-rodável do fonte -->

**Estado** vem do **veredito do teste**, não da leitura: `✅` provado por teste verde que o cita ·
`🟡` parcial (diz o quê) · `🔴` falso/quebrado · `⬜` não-verificado.

> ⚠️ Os `✅` abaixo significam **"existe teste que declara este CU e o exercita"**. O veredito de
> execução vem da lane — e a lane per-PR deste módulo **não existe** (§8). Ver a declaração honesta lá.

### 6.1 Núcleo recorrente (`CU-RB`)

#### CU-RB-01 — Cadastrar plano de assinatura `[must]` `[V0]` 🟡
*Dado* um gestor com `recurringbilling.access`; *quando* preenche nome/valor/ciclo/trial; *então*
o plano nasce no business dele com `slug` único e passa a compor o MRR potencial.
1. `[must]` `slug` é auto-gerado do nome e **unique por business** (dois businesses podem ter o mesmo slug).
2. `[V0]` `valor` > 0 e `ciclo` dentro do enum — o valor do plano é o que **fatura** (F2 passo 4).
3. `[must]` plano com assinatura **ativa** não pode ser deletado → **422** com contagem exata.
4. `[must]` exclusão é **soft delete** (`SoftDeletes`), nunca hard.
5. `[T0]` plano de outro business em `edit/update/destroy` → **404**.
6. 🟡 **Parcial vs a DoD da US-RB-001:** `setup_fee` e `indice_reajuste` **não existem** como coluna
   nem como validação. O SPEC já declara.

#### CU-RB-02 — Criar assinatura (contrato) `[must]` `[V0]` `[T0]` 🟡
*Dado* um cliente do business e um valor/ciclo; *quando* o operador submete o drawer; *então* nasce
uma assinatura ativa que vai gerar fatura no próximo ciclo.
1. `[must]` `business_id` vem **da sessão**, nunca do request.
2. `[must]` validação rejeita `contact_id` ausente, enum fora do domínio e **data no passado**.
3. `[T0]` a busca de cliente (`searchContacts`) só devolve contatos do business da sessão, exclui
   `lead`/`supplier`, e ignora query < 2 chars.
4. `[must]` `forma_pagamento` (PT: boleto/pix/cartao) mapeia pro `payment_method` do modelo.
5. `[V0]` **a assinatura criada tem que ser faturável** — se o valor digitado não casa com nenhum
   plano, `plan_id` fica `null` e o gerador **descarta a assinatura em silêncio** (F2 passo 2).
   🔴 **Este item está QUEBRADO** — ver §9.1.
6. 🟡 **Parcial vs a DoD da US-RB-002:** `status` é hardcoded `'active'`; `trialing` não é derivado do
   plano.

#### CU-RB-03 — Gerar as faturas do ciclo `[must]` `[V0]` ✅
*Dado* assinaturas ativas vencendo; *quando* o job diário roda; *então* cada uma ganha **exatamente
uma** fatura da competência, com o valor certo e o próximo vencimento avançado.
1. `[V0]` `invoice.valor` = `plan.valor` — **fonte única de valor do faturamento**.
2. `[must]` **idempotência por competência `YYYY-MM`**: 2× `run()` no mesmo mês não duplica.
3. `[V0]` `next_due_date` avança por `addMonthsNoOverflow` — **dia 31 → 28/29 de fevereiro**, nunca
   transborda pra março.
4. `[must]` `paused`/`canceled` são puladas; `dry-run` conta sem escrever; `leadDays` antecipa.
5. `[T0]` `run(99)` não enxerga nem toca assinatura do business 1.
6. `[must]` cada fatura gerada deixa rastro na timeline (`SubscriptionEvent kind=charge`).

#### CU-RB-04 — Editar a cobrança de uma assinatura `[must]` `[V0]` `[T0]` ✅
1. `[V0]` alterar `valor`/`ciclo`/`forma` é **local-only** — não re-emite título já gerado.
2. `[T0]` assinatura de outro business → **404**.
3. `[must]` assinatura **cancelada** não aceita edição → **422** com mensagem do serviço.

#### CU-RB-05 — Cancelar, pausar e reativar assinatura `[must]` 🟡
1. `[must]` cancelar exige `churn_reason` e grava `canceled_at`.
2. `[must]` **idempotente**: cancelar de novo → **422**, sem efeito colateral.
3. `[must]` a operação é auditável (Spatie `LogsActivity`).
4. 🟡 **Parcial vs a DoD da US-RB-005:** sem `tipo_cancelamento` (fim de ciclo × imediato), sem
   `credit_note`, sem evento `ContractCanceled`.

#### CU-RB-06 — Cancelar uma fatura no gateway `[must]` `[V0]` `[T0]` ✅
1. `[must]` sem a permissão `recurringbilling.invoice.cancel` → **403** (antes de tocar no gateway).
2. `[V0]` fatura **paga NUNCA é cancelada** → **422** *"use estorno"*. Dinheiro recebido não some por
   clique de tela.
3. `[must]` fatura já cancelada → `ok` **sem chamar o gateway** (idempotente).
4. `[must]` fatura que nunca foi ao gateway → cancela **local**, `gateway_call:false`.
5. `[must]` auditoria grava **sucesso E falha** (quem/quando/motivo/erro).
6. `[T0]` fatura de outro business → **404**.

#### CU-RB-07 — Receber e conciliar o webhook do gateway `[must]` `[T0]` `[V0]` ✅
1. `[T0]` webhook **sem** header de token → **401**, **sem creditar**.
2. `[T0]` webhook com token **errado** (atacante) → **401**, **sem creditar**.
3. `[T0]` token do business 1 **não credita** no business 2 — o isolamento desta rota pública é o
   `hash_equals` contra o secret **daquele** business, não o global scope.
4. `[must]` business sem credencial ativa → **404** (credencial desligada não autentica).
5. `[V0]` **idempotência**: mesmo `event_id` 2× → 200 **sem dispatch**; `UNIQUE(provider, event_id)`
   enforced no banco; providers diferentes podem repetir id.
6. `[must]` `event_id` determinístico (`md5(event+payment.id)`) quando o gateway não manda id.
7. `[V0]` reprocessar `PAYMENT_RECEIVED` **não cria 2 `account_transactions`** (`insertOrIgnore`).

#### CU-RB-08 — Estornar uma cobrança (refund) `[should]` `[V0]` ✅
1. `[V0]` **com a flag desligada (`ASAAS_REFUND_ENABLED=false`, o default) o job NÃO chama a API** —
   só loga o TODO. Devolver dinheiro exige ato consciente de [W] no `.env`.
2. `[V0]` charge já `REFUNDED` → **no-op** (não estorna 2×).
3. `[T0]` `businessId` divergente do documento → `RuntimeException`.
4. `[must]` `doc_type` diferente de `boleto_asaas` → `RuntimeException`.

#### CU-RB-09 — Emitir NFe automaticamente ao receber o pagamento `[should]` 🟡
*O diferencial declarado do módulo* (`CAPTERRA-INVENTARIO` #6 · US-RB-044).
1. `[must]` idempotência por `transaction_id = invoice.id` — 2ª chamada devolve a emissão existente.
2. `[must]` flag `nfebrasil.auto_emission_on_invoice_paid` **default `false`** controla a ativação.
3. `[must]` falha na SEFAZ **não derruba o pagamento** (re-throw → retry da fila).
4. 🟡 **Fora da fronteira deste módulo** — o listener vive em `Modules/NfeBrasil/`. O contrato **daqui**
   é só: *o evento `InvoicePaid` é disparado quando a fatura é paga*.
5. ⬜ **Ver o achado do §9.3** (LGPD no e-mail do DANFE).

#### CU-RB-10 — Isolamento multi-tenant do módulo `[must]` `[T0]` ✅
*Um business jamais enxerga plano, assinatura, fatura, gateway ou saldo do outro.*
Cobertura por superfície: planos (CU-RB-01.5) · assinaturas (CU-RB-04.2) · faturas
(CU-RB-11.3) · gerador (CU-RB-03.5) · webhook (CU-RB-07.3) · refund (CU-RB-08.3) ·
configurações (CU-RB-12.3). **Teste sempre biz=1 vs biz=99, nunca biz=4**
([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).

#### CU-RB-11 — Consultar as faturas (lista) `[must]` ✅
1. `[must]` filtros de status, gateway, período e busca (cliente **ou** número do documento) combinam.
2. `[must]` KPIs agregam pago-no-mês, pendente, atrasado, contagem de atrasadas e total.
3. `[T0]` fatura de outro business não aparece.
4. `[must]` a paginação reporta `current_page`/`last_page`/`per_page`/`total` corretos.
5. `[must]` `kpis` e `invoices` são **deferidos** (props caras).

#### CU-RB-12 — Entender como o business cobra (configurações) `[should]` ✅
*Uma tela em vez de quatro (painel Asaas + painel Inter + memória + RUNBOOK).*
1. `[must]` lista os gateways cadastrados **sem vazar `config_json`** (só banco/ambiente/nome).
2. `[must]` mostra a régua de dunning canônica com as 3 retentativas estruturadas.
3. `[T0]` gateway de biz=1 não aparece pra biz=99.
4. `[must]` a URL de webhook exibida reflete o `business_id` **da sessão ativa** — colar a URL errada
   no painel do gateway é dinheiro que não entra.
5. `[should]` é **read-only por design** (o CRUD mora em `/financeiro/contas-bancarias`) — ver D3.

#### CU-RB-13 — Ler saldo e extrato do Inter PJ `[should]` 🟡
1. `[must]` sync de saldo e de extrato rodam por job com `businessId` explícito.
2. `[T0]` saldo/extrato de um business não vazam pro outro.
3. 🟡 Operação depende do RUNBOOK (US-RB-048) — certificado, renovação, rotação de secret.

#### CU-RB-14 — Consultar a carteira de assinaturas (porta de entrada) `[must]` `[V0]` ✅
*Dado* a carteira do business; *quando* o operador abre `/recurring-billing`; *então* vê o estado
de cobrança de cada cliente e os números que resumem a saúde da carteira.
1. `[must]` o status **visual** deriva do estado do banco, não é coluna: `em_dia` ← `active|trialing`
   sem fatura vencida · `retentando` ← `past_due` com < 3 tentativas · `falhou` ← `past_due` com ≥ 3 ·
   `pausada` ← `paused` · `cancelada` ← `canceled` (mapa declarado no `Index.charter.md` §Goals).
2. `[V0]` **o MRR normaliza o ciclo**: uma assinatura trimestral entra no MRR pelo **equivalente
   mensal**, nunca pelo valor cheio — senão o número que [W] usa pra decidir infla ~3×.
3. `[V0]` o **churn** é calculado sobre o total **não-trialing** (trial não é cliente perdido).
4. `[must]` a linha da lista e o payload do drawer carregam os campos canônicos (contato, nota
   pinada, blocos fiscais) — o drawer é a 3ª coluna sempre visível, não modal.
5. `[must]` `subscriptions` e `kpis` são **deferidos** (props caras).

#### CU-RB-15 — Ativar gateway em assinaturas de cobrança dormente `[must]` `[V0]` `[T0]` 🧪
*Dado* um conjunto de assinaturas `active|trialing` sem `conta_bancaria_id`; *quando* o operador
executa o backfill; *então* cada assinatura resolvível recebe a conta do próprio business e volta
ao fluxo normal de emissão, sem atribuição por palpite.
1. `[V0]` dry-run é o padrão e apresenta impacto antes→depois sem escrever uma linha.
2. `[T0]` a conta candidata pertence ao mesmo `business_id`; zero ou múltiplas candidatas fazem a
   assinatura ser pulada com motivo explícito.
3. `[must]` `--apply` só toca assinatura ainda sem conta e grava evento auditável `gateway_atribuido`;
   repetir o comando produz zero alterações.
4. `[V0]` a aplicação em produção exige aprovação humana do dry-run e smoke canário de uma
   assinatura antes do lote completo.
5. `[must]` a próxima fatura do canário herda `conta_bancaria_id`/gateway e confirma emissão real.

### 6.2 Non-goals explícitos — 🖐 **SÓ [W] PREENCHE**

> ⚠️ **Deixado VAZIO de propósito.** O agente é **proibido de inferir** Non-Goal
> ([ADR 0351](../../decisions/0351-sdd-from-source.md) · skill `charter-write`). Os charters das 6
> telas já declaram Non-Goals por tela; o que falta é o Non-Goal **do domínio** — e é decisão de
> produto. Candidatos que a análise encontrou e que **[W] precisa julgar** estão no §10.

---

## 7. Requisitos não-funcionais ⚙️ derivado

<!-- derivado: re-rodável do fonte -->

| NFR | Alvo | Fonte |
|---|---|---|
| First-paint p95 | < 1500ms nas listas · < 800ms em Configurações/Create/Edit | `ux_targets` dos 6 charters |
| Largura | cabe em **1280px** sem scroll horizontal | canon Larissa/ROTA LIVRE |
| Props caras | `Inertia::defer` obrigatório (`kpis`, `invoices`, `plans`, `subscriptions`, `gateways`) | skill `inertia-defer-default` |
| Webhook | responde **200 imediato**, processa async; `throttle:60,1` | [ADR tech/0002](adr/tech/0002-webhook-asaas-at-least-once-resposta-rapida.md) |
| Observabilidade | spans `rb.invoice.gerador.run` / `rb.invoice.cancel` via `OtelHelper::spanBiz` | `RecurringOtelD9Test` |
| Segredo | `config_json` da credencial **cifrado**; nunca renderizado | [ADR tech/0007](adr/tech/0007-encryption-pattern-credenciais-boleto.md) |
| LGPD | notificação ao pagador respeita opt-in do `Contact` | [proibicoes](../../proibicoes.md) §FSM — ⚠️ §9.3 |

---

## 8. Estratégia de qualidade e rollout ⚙️ derivado

<!-- derivado: re-rodável do fonte -->

### 8.1 As 3 portas de "este teste roda?" — **medidas 2026-07-28**

> 🔴 Responder isto com `grep` em `.github/workflows/` é a classe **LC-08**. As três portas são
> distintas e foram consultadas uma a uma:

| Pergunta | Porta consultada | Resultado |
|---|---|---|
| **roda em algum lugar?** | `phpunit.xml` (testsuites) + `scripts/tests/shards-plan.mjs` | ✅ **SIM.** `phpunit.xml` lista `./Modules/RecurringBilling/Tests/Feature` na testsuite `Feature`; o `shards-plan.mjs --roots tests,Modules` descobre o diretório recursivamente e o `SHARD_EXCLUDE` do `ct100-fullsuite.sh` só poda `tests/Browser,tests/governance-fixtures`. **Os 39 testes rodam na nightly CT100.** |
| **roda no PR?** | allowlist `.github/ci-sqlite-pest.list` + `paths` dos workflows | ❌ **NÃO.** Zero linhas de `RecurringBilling` na allowlist; **nenhum** dos workflows menciona o módulo (`grep -rln RecurringBilling .github/workflows/` = 0 arquivos); `modules-pest.yml` roda só Arquivos/ComunicacaoVisual/Fiscal/NfeBrasil/Repair/Vestuario. |
| **bloqueia merge?** | [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) | ❌ **NÃO** — nada deste módulo. O contexto `PHP / Pest (Unit)` **é required**, mas só executa o que está na allowlist. |

> 📌 **"Sem lane" era meia-verdade.** Os testes deste módulo **nunca foram invisíveis** — rodam
> na nightly desde sempre. O que falta é a **mordida per-PR**. O caminho não é criar lane: é
> **1 linha** na allowlist que já existe (e é `merge=union` no `.gitattributes`, então não conflita
> com PRs concorrentes). A linha proposta está no §8.2. **Este PR não a escreve** (`.github/**` está
> fora da área do chip).

### 8.2 A linha que o parent deve adicionar

```
# RecurringBilling — contrato dos CU do SDD (UC-RBSUB/RBFAT/RBPLN/RBCFG). Sqlite-safe:
# schema sintético em beforeEach (idioma do bloco RecurringBilling já presente em tests/Pest.php),
# sem RefreshDatabase/ENUM MySQL-only. Sem esta linha os UC deste módulo só têm veredito na nightly.
Modules/RecurringBilling/Tests/Feature/InvoiceGeneratorServiceTest.php
Modules/RecurringBilling/Tests/Feature/AsaasWebhookAuthTest.php
Modules/RecurringBilling/Tests/Feature/AsaasWebhookIdempotencyTest.php
Modules/RecurringBilling/Tests/Feature/RefundCobrancaAsaasJobTest.php
Modules/RecurringBilling/Tests/Feature/AssinaturaCobrancaServiceTest.php
Modules/RecurringBilling/Tests/Feature/PlanoSemFaturaContratoTest.php
```

> ⚠️ **Predição, não veredito** — não rodei nenhum destes (CT 100, [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).
> Os 5 primeiros já passam na nightly hoje; o 6º **nasce vermelho de propósito** (§9.1). Se algum
> reprovar em sqlite, o honesto é **tirar aquele arquivo da linha**, não desligar o gate.

### 8.3 O trio por tela

`casos-gate` ([ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)) G-1 exige
`.tsx` + `.charter.md` + `.casos.md`. As 6 telas ganham `casos.md` neste PR. Os UC derivam **deste §6**
— nunca do `.tsx`.

**Onde cada CU foi ancorado** (recibo de `node scripts/governance/requisitos-status.mjs RecurringBilling`,
2026-07-28: **36 UC declarados · 36 com teste que os cita · 0 órfãos**):

| casos.md | UC | CU ancorados |
|---|---|---|
| `Index.casos.md` | `UC-RBSUB-01..08` | `CU-RB-02` · `CU-RB-04` · `CU-RB-05` · `CU-RB-10` · `CU-RB-14` |
| `Faturas/Index.casos.md` | `UC-RBFAT-01..13` | `CU-RB-03` · `CU-RB-06` · `CU-RB-08` · `CU-RB-11` |
| `Planos/Index.casos.md` | `UC-RBPLN-01..03` | `CU-RB-01` |
| `Planos/Create.casos.md` | `UC-RBPNC-01..02` | `CU-RB-01` |
| `Planos/Edit.casos.md` | `UC-RBPNE-01..02` | `CU-RB-01` |
| `Configuracoes/Index.casos.md` | `UC-RBCFG-01..08` | `CU-RB-07` · `CU-RB-12` |

**Fluxos sem tela** (gerador, refund, webhook) ficaram no `casos.md` da tela **do artefato que eles
produzem ou desfazem** — nunca em arquivo paralelo (seria tipo novo,
[ADR 0351](../../decisions/0351-sdd-from-source.md) D-B).

#### Os 2 CU que ficam SEM `casos.md` — e por quê (declarado, não escondido)

| CU | Por que não tem UC aqui | Onde ele nasce |
|---|---|---|
| `CU-RB-09` (NFe automática ao pagar) | o listener vive em **`Modules/NfeBrasil/`** — fora da área deste chip. Escrever UC daqui sobre código de lá cria dono duplo | chip do **NfeBrasil**. O contrato **deste** módulo é só *disparar `InvoicePaid`* |
| `CU-RB-13` (saldo/extrato Inter PJ) | os jobs são daqui e **já declaram `@covers-us`** (`US-RB-045`/`046`), mas a **tela é `Financeiro/Extrato/Index`** — fora da área | chip do **Financeiro** |

Isso é gap **declarado**, não lacuna escondida: a porta viva vai continuar acusando os dois até que o
chip dono os feche — e isso é o comportamento correto dela.

---

## 9. Riscos e dívidas 🖐 curado

<!-- curado: foto que envelhece -->

### 9.1 🔴 `[V0]` A assinatura sem plano correspondente **nunca gera fatura** — e ninguém é avisado

**O que é.** `RecurringBillingController@store` resolve o `plan_id` procurando um plano ativo cujo
**`ciclo` E `valor` batam exatamente** com o digitado. Não achou → `plan_id = null` (a coluna é
nullable). Depois, `InvoiceGeneratorService::processarSubscription` faz
`if ($plan === null) { errors++; Log::error(...); return; }` — **sem fatura, sem timeline, sem alarme**.

**Por que é achado e não opinião** (os 3 requisitos de [proibicoes §5](../../proibicoes.md) 2026-07-15):
1. **Varredura contada:** `InvoiceGeneratorService` tem **3 invocadores de produção**
   (`GenerateInvoicesCommand@handle` + os 2 wrappers `run`/`runInternal` do próprio serviço) e
   **2 arquivos de teste** (`InvoiceGeneratorServiceTest`, `tests/Feature/Calculo/CalculoRecurringBillingTest`).
   Todos os 3 caminhos de produção passam pelo mesmo `processarSubscription`. `Grep` sem `head_limit`,
   contado — **não há um segundo gerador** que salve o caso.
2. **Âncora de contrato:** a DoD da **US-RB-002** exige literalmente *"Test Feature: criar com trial,
   sem trial, **com customizações de valor (override do plano)**, isolamento"*. **Override de valor é
   contrato declarado** — e o override é exatamente o caso que produz `plan_id = null`.
3. **Teste vermelho:** `Modules/RecurringBilling/Tests/Feature/PlanoSemFaturaContratoTest.php` nasce
   **failing-first** neste PR (UC-RBSUB-05). ⚠️ **Não rodei** (CT 100) — a predição é vermelho.

**Por que ninguém percebeu.** O sintoma é *silêncio*: `stats['errors']` sobe, uma linha vai pro log
`single`, e a assinatura fica parada com `next_due_date` no passado. É **a mesma família da US-RB-052**
(*"Ativar gateway nas 109 assinaturas com `gateway=NULL` — cobranças dormentes"*) — assinatura que
existe e não cobra.

**O que este PR NÃO faz.** Não conserta. Existem ≥2 correções válidas e **elas se anulam**:
(a) o gerador cair pra `metadata.valor` quando não há plano; (b) o `store()` recusar valor sem plano;
(c) criar plano implícito. Escolher entre elas é decisão `[V0]` de [W] — exige dupla-confirmação por 2
caminhos + tabela antes→depois das assinaturas afetadas ([proibicoes](../../proibicoes.md) §REGRA
MESTRE). **Escolher remédio antes do diagnóstico é o erro catalogado em 2026-07-15.**

### 9.2 🟡 Três implementações de "próximo vencimento" (D2)
Já catalogado como **US-RB-056** e já travado por characterization test. A fonte de verdade decidida é
`InvoiceGeneratorService` (NoOverflow, vocabulário EN). Unificar é `[V0]`.

### 9.3 ⬜ Hipótese: o e-mail do DANFE ao pagador não checa o opt-in LGPD — **fora da área deste chip**

**Varredura contada:** `canReceiveEmailNotification|canReceiveWhatsappNotification` tem **3 sites de
chamada** em todo o repo — `Modules/Whatsapp/Jobs/NotificarClienteCancelamentoJob` (2) e
`Modules/OficinaAuto/Jobs/EnviarLinkAprovacaoWhatsappJob` (1). **Zero em `Modules/NfeBrasil/`.**
E `Modules/NfeBrasil/Listeners/EnviarDanfePorEmail` faz `Mail::to($email)->send(...)` resolvendo o
destinatário **via `Invoice → Contact`** — ou seja, e-mail comercial disparado ao pagador da fatura
recorrente por gatilho automático.

**Por que fica `⬜` hipótese e não achado:** a proibição Tier 0 nomeia especificamente o
`NotificarClienteCancelamentoJob`; **não** há regra escrita dizendo que DANFE (documento fiscal, que o
destinatário tem direito de receber) entra na mesma classe de "notificação comercial". **É decisão
jurídica de [E]/[W], não técnica minha.** Reportado, não corrigido — `Modules/NfeBrasil/` está fora
da área deste chip.

### 9.4 🟡 Telas com 0 hits na janela de 30 dias
5 US wired com zero uso (§2). Risco: **contrato escrito sobre tela que ninguém exercita**. Mitigação
adotada: **UC ancorados em teste que já existe**, e o resto vira `[BACKLOG]` sem id (UC órfão trava o
merge de quem for atendê-lo — G-2).

---

## 10. Roadmap 🖐 [W] prioriza

| Trilha | Item | Estado |
|---|---|---|
| **Correção `[V0]`** | decidir o remédio do §9.1 (assinatura sem plano) | ⛔ **precisa de [W]** — 3 remédios que se anulam |
| **Correção `[V0]`** | US-RB-056 — unificar as 3 regras de próximo vencimento | aberta |
| Paridade DoD | US-RB-001 `setup_fee`/`indice_reajuste` · US-RB-002 `trialing` · US-RB-005 `tipo_cancelamento`+`credit_note` | parciais declaradas |
| Cobrança | US-RB-004 endpoint de charge + smart retry soft/hard decline | D6 |
| Cobrança | US-RB-006 proração mid-cycle + reajuste no aniversário | D7 |
| Dunning | régua per-business (`rb_dunning_rules`) em vez de hardcode | D4 |
| Gateway | `C6Driver::cancelar()` (CNAB ocorrência 02) | D5 |
| Diferencial | ligar `nfebrasil.auto_emission_on_invoice_paid` com evidência de prod | US-RB-044 |
| Governança | a linha do §8.2 na allowlist per-PR | **parent** |
| **Non-Goals do domínio** | §6.2 vazio — candidatos: *"não somos MoR"* ([ADR arq/0004](adr/arq/0004-take-rate-vs-merchant-of-record.md)), *"não fazemos cartão tokenizado próprio"*, *"não emitimos NFS-e aqui"* ([ADR arq/0002](adr/arq/0002-nfse-submodulo-vs-nfebrasil.md)) | ⛔ **só [W]** |

---

## 11. Referências

- [`SPEC.md`](SPEC.md) — as 29 US · [`BRIEFING.md`](BRIEFING.md) · [`SUPERFICIE.md`](SUPERFICIE.md) · [`RUNBOOK-inter-pj.md`](RUNBOOK-inter-pj.md)
- Charters: `resources/js/Pages/RecurringBilling/**/*.charter.md` (6)
- ADRs de módulo: [`adr/arq/`](adr/arq) (9) · [`adr/tech/`](adr/tech) (8) · [`adr/ui/`](adr/ui) (3)
- [ADR 0170](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md) — fronteira com PaymentGateway
- [ADR 0351](../../decisions/0351-sdd-from-source.md) — o método que produziu este doc · [SDD-TEMPLATE](../_DesignSystem/SDD-TEMPLATE.md)
- Formato imitado (não reaberto): [`Produto/SDD-tela-cadastro-produto-v1.0.md`](../Produto/SDD-tela-cadastro-produto-v1.0.md)

---

## Changelog (append-only — correção não apaga, vira linha)

| Versão | Data | O quê |
|---|---|---|
| 1.0.1 | 2026-08-05 | `CU-RB-15` tornou explícito o contrato da US-RB-052 já diagnosticado no §9.1 e ligou o trio `gateway-ativacao` ao SDD; sem tela por desenho (comando operacional). |
| 1.0.0 | 2026-07-28 | Nascimento. §5.3 com F1–F9 · §6 com CU-RB-01..13 · §9.1 achado `[V0]` da assinatura sem plano · fontes 3 (Blade) e 4 (Delphi) declaradas **ausentes**, não inventadas · §6.2 Non-Goals deixado vazio pra [W]. |
