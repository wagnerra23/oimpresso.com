---
id: requisitos-recurring-billing-changelog
---

# Changelog — RecurringBilling

Formato: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) + SemVer.

## [Unreleased]

### Novembro 2026 — auditoria do módulo (lotes L0 a L5)

Auditoria de 2026-11-19. Decisões b1–b8 registradas pelo coordenador em 2026-11-23 a pedido do [W].

**Dinheiro e webhooks**
- `rb:generate-invoices` não prende a assinatura na competência já faturada (d7) — [#11193](https://github.com/wagnerra23/oimpresso.com/pull/11193).
- Pix Automático não cobra duas vezes na retentativa: chave e `txid` estáveis (d8) — [#11206](https://github.com/wagnerra23/oimpresso.com/pull/11206).
- Reais → centavos num helper único (`Centavos::de`), com sinal certo e sem float (d9) — [#11221](https://github.com/wagnerra23/oimpresso.com/pull/11221).
- Webhook Asaas credita uma vez e só na quitação; `InvoicePaid` só na transição — [#11223](https://github.com/wagnerra23/oimpresso.com/pull/11223).
- Worker da fila `rb_webhooks` agendado no `Kernel` (antes nenhum worker lia a fila) — [#11230](https://github.com/wagnerra23/oimpresso.com/pull/11230).
- Idempotência de webhook por empresa: `pg_webhook_events` com `unique(business_id, provider, event_id)`; FK de `business_id` nas tabelas do módulo — [#11242](https://github.com/wagnerra23/oimpresso.com/pull/11242).
- Segredo do webhook cifrado (`Crypt`) e valor/ciclo na trilha da assinatura — [#11227](https://github.com/wagnerra23/oimpresso.com/pull/11227).
- Webhook Inter acha a fatura pelo `txid` (gravado em `gateway_ref` pelo `CobradorPixAutomatico`) e credita só na quitação; Pix que não é de fatura do RB deixa de lançar crédito (a4) — [#11352](https://github.com/wagnerra23/oimpresso.com/pull/11352).

**Acesso e isolamento**
- Rotas web exigem o pacote `recurringbilling_module` — [#11205](https://github.com/wagnerra23/oimpresso.com/pull/11205).
- Cada rota exige a sua permissão (`ExigePermissaoRb` + `SubscriptionPolicy`) — [#11247](https://github.com/wagnerra23/oimpresso.com/pull/11247).
- Assinatura só aceita contato da empresa da sessão — [#11196](https://github.com/wagnerra23/oimpresso.com/pull/11196).
- Timeline só aceita nota humana; evento é imutável; nota só o autor apaga — [#11233](https://github.com/wagnerra23/oimpresso.com/pull/11233).
- Reenviar NF-e só pela nota vinculada e com a permissão do NfeBrasil — [#11209](https://github.com/wagnerra23/oimpresso.com/pull/11209).
- Configurações mostra a URL e o header reais dos webhooks — [#11203](https://github.com/wagnerra23/oimpresso.com/pull/11203).
- Testes de isolamento entre empresas pela rota, 98 × 99 — [#11345](https://github.com/wagnerra23/oimpresso.com/pull/11345).

**Gateway (b1)**
- Remove a pilha de boleto própria do RB (`BoletoService` e os drivers Asaas, C6 e Inter da pasta `Services/Boleto`), sem chamador de produção desde [#11359](https://github.com/wagnerra23/oimpresso.com/pull/11359); ficam o `BoletoCredentialResolver` e `rb_boleto_credentials` — [#11409](https://github.com/wagnerra23/oimpresso.com/pull/11409).

**Testes e documentação**
- Testes da lane voltam a medir o comando e o usuário reais — [#11287](https://github.com/wagnerra23/oimpresso.com/pull/11287).
- README e ARCHITECTURE marcados como desenho de abril; SCOPE e RUNBOOK com ponteiros vivos — [#11214](https://github.com/wagnerra23/oimpresso.com/pull/11214).
- Charters e casos alinhados ao 301 e ao desfecho do UC-RBSUB-05 — [#11238](https://github.com/wagnerra23/oimpresso.com/pull/11238).
- Gap e map do protótipo refeitos sobre a fonte de design — [#11219](https://github.com/wagnerra23/oimpresso.com/pull/11219).
- ADRs 0455 (Pix Automático na Jornada 2) e 0456 (PaymentGateway dono dos drivers) — [#11347](https://github.com/wagnerra23/oimpresso.com/pull/11347), mergeado em 2026-11-23 (status `proposto`). BRIEFING e SDD 1.1.0 — [#11354](https://github.com/wagnerra23/oimpresso.com/pull/11354). SPEC: US-RB-ESC0/1/2 marcadas `duplicate` das US-RECURRINGBILLING-001/002/003; US-RB-044 e US-RB-057 voltam a parcial (flag desligada, b8); nome da permissão `recurringbilling.invoices.cancel` no SPEC, SDD, charter e casos de Faturas — PR deste lote.

**Mergeados em 2026-11-23 e 2026-11-24:** cartão recorrente no drawer da assinatura ([#11355](https://github.com/wagnerra23/oimpresso.com/pull/11355), b5), Faturas na forma do protótipo ([#11366](https://github.com/wagnerra23/oimpresso.com/pull/11366)), apagar contato ou plano não leva assinaturas e faturas ([#11346](https://github.com/wagnerra23/oimpresso.com/pull/11346), b6), régua sem e-mail nem WhatsApp na tela ([#11350](https://github.com/wagnerra23/oimpresso.com/pull/11350), b7), navegador + axe nas 6 telas ([#11353](https://github.com/wagnerra23/oimpresso.com/pull/11353)). BRIEFING e SDD 1.1.0 ([#11354](https://github.com/wagnerra23/oimpresso.com/pull/11354)) mergeados em 2026-11-24.

**Gateway (b1, 2026-11-24):** cópia das credenciais do RB para `payment_gateway_credentials` decifrando o segredo e sem ligar conta ([#11356](https://github.com/wagnerra23/oimpresso.com/pull/11356); a migration roda no próximo deploy e só cria o que falta); cancelar e estornar cobrança Asaas pelo driver do PaymentGateway ([#11358](https://github.com/wagnerra23/oimpresso.com/pull/11358)); cancelar boleto Inter e fatura pelo driver do PaymentGateway ([#11359](https://github.com/wagnerra23/oimpresso.com/pull/11359)).

**Sem PR ainda:** cobrar com o cartão salvo; os outros leitores de `rb_boleto_credentials` (o `BoletoService` saiu em [#11409](https://github.com/wagnerra23/oimpresso.com/pull/11409)). **Do [W]:** `paymentgateway:migrate-credentials --apply` para consertar credencial já copiada com o segredo cifrado do RB.

**Onda 1 da saída de `rb_boleto_credentials` (2026-11-24, depois do `--apply`):** reajuste de assinatura no Asaas e sync de saldo passam a ler a credencial do PaymentGateway; o reajuste deixa de mandar o `api_key` cifrado e o saldo Asaas sandbox deixa de ir para produção — [#11412](https://github.com/wagnerra23/oimpresso.com/pull/11412).

**Onda 1 da saída de `rb_boleto_credentials` (2026-11-24, depois do `--apply`):** reajuste de assinatura no Asaas e sync de saldo passam a ler a credencial do PaymentGateway; o reajuste deixa de mandar o `api_key` cifrado e o saldo Asaas sandbox deixa de ir para produção — [#11412](https://github.com/wagnerra23/oimpresso.com/pull/11412).

**Onda 2 (2026-11-24):** os webhooks Asaas (`/api/webhooks/asaas/{businessId}`) e Inter Pix (`/webhooks/inter/pix/{businessId}`) conferem o header contra o `webhook_secret` da credencial ativa no PaymentGateway, não mais contra `rb_boleto_credentials`. Config que não decifra vira 401, nunca 500. Medido em produção antes da troca: `rb_boleto_credentials` vazia e `pg_webhook_events` sem nenhum evento, então as duas rotas já devolviam 404 para toda empresa; a única credencial ativa no PG (Inter, empresa 1) não tem `webhook_secret`, e o webhook Inter dela passa de 404 para 401 até alguém cadastrar o segredo no wizard. Nenhum código de produção grava segredo em `rb_boleto_credentials`; o segredo novo se cadastra no wizard do PaymentGateway.

**Onda 4 (2026-11-24):** sync de extrato e de saldo e o pagamento pelo Inter do Financeiro acham a credencial pela conta no PaymentGateway (`ContaBancaria::credencialGateway`), não mais por `fin_contas_bancarias.rb_gateway_credential_id`. Medido em produção antes da troca: nenhuma das 105 contas aponta para `rb_boleto_credentials` e a única credencial do PG não está ligada a conta, então os três já não rodavam para conta nenhuma. `BoletoCredentialResolver` fica sem leitor em produção; sai na onda 5 com a coluna.

### Outubro 2026

- Testes do módulo entram na matriz do `modules-pest` — [#9362](https://github.com/wagnerra23/oimpresso.com/pull/9362) (2026-10-14).
- Assinatura sem plano fatura pelo valor negociado (`metadata.valor`/`metadata.ciclo`) — [#9606](https://github.com/wagnerra23/oimpresso.com/pull/9606) (2026-10-19, remédio "a" de [W]).
- `plan_id` nulável em `rb_subscriptions` — [#9858](https://github.com/wagnerra23/oimpresso.com/pull/9858) (2026-10-26).
- Pix Automático BCB (US-RB-020/021): driver, motor, webhooks, mandato e tela, atrás de `RB_COBRAR_PIX_AUTOMATICO` — [#9870](https://github.com/wagnerra23/oimpresso.com/pull/9870) (2026-10-26).
- Cobrança paga no PaymentGateway marca a fatura paga e dispara `InvoicePaid` (US-RB-057), atrás de `RB_MARCAR_FATURA_PAGA_POR_COBRANCA` — [#9844](https://github.com/wagnerra23/oimpresso.com/pull/9844) (2026-10-27).
- Cartão pago guarda o token na assinatura (US-RB-011), atrás de `RB_SALVAR_CARTAO` — [#9951](https://github.com/wagnerra23/oimpresso.com/pull/9951) (2026-10-27).

### Wave 18 saturação 69→95 (2026-05-16)

- **D4 SoC brutal +14** — Criados `Modules\RecurringBilling\Repositories\SubscriptionRepository` (5 métodos: `listarPaginado`, `contarAtivas`, `mrrBaselineCached`, `vencendoNoIntervalo`, `acharPorId`) e `InvoiceRepository` (5 métodos: `listarPaginado`, `totaisPorStatus`, `atrasadasAntigas`, `acharPorGatewayRef`, `acharPorId`). Singleton via RecurringBillingServiceProvider (`registerRepositories()`). Substitui `Subscription::where()`/`Invoice::where()` inline. MRR cached calcula divisão por ciclo (mensal/trimestral/semestral/anual).
- **D8 saturação** — Criado `Modules\RecurringBilling\Http\Requests\CancelInvoiceRequest` (3º FormRequest tipado) com `Rule::in` pra motivo (ACERTOS/DUPLICIDADE/PEDIDO_CLIENTE/ERRO_OPERADOR/INADIMPLENCIA/OUTROS) + helper `motivo()` default ACERTOS.
- **D1+D2+D9.a Pest cross-tenant** — `RepositoryWave18Test` (8 cenários Pest): reflection valida type hints `int $businessId` no 1º param, biz=99 retorna zero em todos métodos (isolamento Tier 0 ADR 0093), spanBiz wrap nos métodos hot, defesa em profundidade `where('business_id', $businessId)` explícito por regex.
- **module.json governance** — Declarado `governance.fsm_n_a=true` (Subscription/Invoice são state machines lineares simples, não pipelines FSM canon ADR 0143; dunning futuro US-RB-013 pode virar FSM).

### Planejado (Onda 1 — PaymentGateway + Asaas)

- Schema PaymentGateway: `pg_credentials`, `pg_payment_methods`, `pg_charge_attempts`, `pg_webhook_events`
- Adapter Asaas (interface `PaymentGatewayInterface`)
- Webhook controller com idempotência (TECH-0001, TECH-0002)
- Tokenização cartão via iframe Asaas (R-RB-012)
- Cobrança avulsa (sem RecurringBilling ainda)
- Permissões Spatie + Multi-tenant tests

### Planejado (Onda 2 — RecurringBilling núcleo)

- Schema rb_*: `rb_plans`, `rb_contracts`, `rb_invoices`, `rb_proration_events`, `rb_contract_events`
- US-RB-001 (cadastrar plano) + US-RB-002 (criar contrato) + US-RB-003 (gerar fatura) + US-RB-004 (cobrar) + US-RB-005 (cancelar)
- Ciclo de vida: trialing → active → past_due → unpaid → canceled
- Proração mid-cycle (US-RB-006, TECH-0003)
- Timeline visual contrato (UI-0002)
- Integração Financeiro: cria `fin_titulos` em `InvoiceGenerated`

### Planejado (Onda 3 — NFSe via Focus/PlugNotas)

- Schema NFSe: `nfse_emissoes`, `nfse_providers_config`
- Adapters: `FocusNFeAdapter`, `PlugNotasAdapter`
- Emissão automática em `InvoicePaid` (assíncrona, R-RB-007)

### Planejado (Onda 4 — Dunning email-only)

- Schema Dunning: `dun_rules`, `dun_steps`, `dun_campaigns`, `dun_step_executions`
- US-RB-030 (configurar régua) + US-RB-031 (disparar campanha)
- Email só (SMS/WhatsApp em onda futura)

### Planejado (Onda 5 — Pix Automático)

- Schema PixAutomatico: `pa_authorizations`, `pa_payment_instructions`, `pa_authorization_events`
- US-RB-020 (autorização Jornada 3) + US-RB-021 (cobrança subsequente)
- ARQ-0003 (Jornada PAYMENTONAPPROVAL)
- PSP integração (Woovi ou direto banco)

### Planejado (Onda 6 — Boleto CNAB direto, opcional)

- Schema Boleto: `bol_*` ou compartilhar com Financeiro
- Lib `eduardokum/laravel-boleto` (ARQ-0001 dependência)

### Planejado (Onda 7 — 2º adapter)

- Adapter Iugu OU Pagar.me (decisão baseada em market share)

### Planejado (Onda futura)

- Smart retry ML (vs regra simples MVP)
- Portal B2C self-service white-label completo (UI-0001)
- US-RB-013 (smart retry sequence ML)
- Multi-currency (BRL only no início)
- Take rate metering completo (ARQ-0004)

## [0.0.0] - 2026-04-24

### Added

- Spec promovida de `_Ideias/CobrancaRecorrente/` para `requisitos/RecurringBilling/` (`spec-ready`)
- Estrutura completa: README + SPEC + ARCHITECTURE + GLOSSARY + 9 ADRs (arq/0001-0004 + tech/0001-0003 + ui/0001-0002)
- Frase de posicionamento e revenue model: Starter R$ [redacted Tier 0] / Pro R$ [redacted Tier 0] / Enterprise R$ [redacted Tier 0] + take rate 0,8% capped R$ [redacted Tier 0] (gateway próprio)
- 6 sub-módulos identificados (RecurringBilling, PaymentGateway, PixAutomatico, NFSe, Dunning, Boleto)
- Origem rastreada: conversa Claude mobile com 2 rodadas web search (`_Ideias/CobrancaRecorrente/evidencias/conversa-claude-2026-04-mobile.md`)

---

## Implementação (histórico movido de `Modules/RecurringBilling/CHANGELOG.md`)

> Movido em 2026-08-10. Os dois changelogs registravam eventos DIFERENTES — acima as
> decisões/requisitos, aqui o que foi de fato mergeado. Medido antes de fundir:
> sobreposição de datas era 0-2 (máx. 2, em ComVis e KB), logo nenhum lado era cópia do
> outro e escolher um deles perderia registro. Conteúdo preservado na íntegra.

# CHANGELOG — Modules/RecurringBilling

Convenção: [Keep a Changelog](https://keepachangelog.com/) + [SemVer](https://semver.org/).

## [Wave 27 POLISH FINAL] — 2026-05-17 (atual 76-88 → target ≥90)

### Added (D2 + D9 + US-RB-044 sentry)

- **D2 Pest comprehensive** — `Tests/Feature/Wave27PolishTest.php` (18 cenários, 81 assertions):
  - **D2 BoletoService API**: 5 métodos públicos canônicos (emitir/cancelar/pdf/refundAsaas/fetchPaymentAsaas) + tipos signature + guard InvalidArgumentException
  - **D2 AssinaturaService API**: 5 métodos públicos (criar/pausar/retomar/cancelar/calcularProximoVencimento) + cross-tenant guard `$businessId` 1º arg em todos métodos
  - **D2 AssinaturaCobrancaService API**: cancelInvoice + atualizarCobrancaAssinatura + http_status convencional (422 invoice paga, 501 C6 manual)
  - **D9 spans completos**: BoletoService ≥4 spans canon (rb.boleto.emitir/cancelar/pdf/refund_asaas) + AssinaturaService 4/4 (rb.assinatura.*) + AssinaturaCobrancaService 2 (rb.invoice.cancel + rb.subscription.update)
  - **D9 atributos canon**: 3 services declaram `module=RecurringBilling` + `business_id` em span attributes
- **US-RB-044 NFe-de-boleto-pago SENTRY** (canônico irrevogável — W25/W26 criou listener):
  - InvoicePaid Event class shape: 4 props readonly (businessId int, invoiceRef string, valor float, paidAt string)
  - Dispatchable + SerializesModels (queue-safe pra `EmitirNFeAoReceberPagamento`)
  - Cross-module wiring lock: `NfeBrasilServiceProvider` registra `Event::listen(InvoicePaid, EmitirNFeAoReceberPagamento)`
  - Log canon preservado: `AssinaturaCobrancaService` mantém `Log::info('rb.subscription.atualizada')`
- **Tier 0 lock-in**: BoletoService NÃO loga `config_json` raw (LGPD — credenciais criptografadas)

### Validated

- `php vendor/bin/pest Modules/RecurringBilling/Tests/Feature/Wave27PolishTest.php` → **18/18 passed (81 assertions, 5.40s)**

### Refs

- ADR 0093 (multi-tenant Tier 0 IRREVOGÁVEL) · ADR 0101 (tests biz=1) · ADR 0094 §5 (SoC) · ADR 0155 (module-grade-v3)
- US-RB-044 (NFe-de-boleto-pago — listener `EmitirNFeAoReceberPagamento` em NfeBrasil)
- Tier 0 IRREVOGÁVEIS: InvoicePaid event readonly (downstream contract), `business_id` 1º arg em Services

### Estimativa nota

- Wave 25 baseline: ~76-88 (variável conforme dimensão auditada)
- Wave 27 polish final: **≥90** com cobertura D2/D9 + sentry US-RB-044 + Tier 0 lock-in

## [Unreleased] — Wave 18 RETRY (2026-05-16)

### Added (saturação 69→97 — governance module-grade-v3)

- **D4 SoC saturação granular extração tripla** —
  - `Services/AssinaturaService.php` extraído (substitui Controller no-op): 4 métodos (`criar`, `pausar`, `retomar`, `cancelar`) com idempotência + cross-tenant guard explícito + `calcularProximoVencimento` helper. 4 spans OTel canônicos.
  - `Services/Boleto/BoletoCredentialResolver.php` extraído de `BoletoService::driver()` + `decryptConfig()`. Decifra 4 campos sensíveis via `Crypt::decryptString` + `resolveDriverName()` fail-safe pra logs. 1 span OTel.
  - Ambos registrados como singleton em `RecurringBillingServiceProvider`.
- **D2 Pest cobertura crítica triple** —
  - `Tests/Feature/AssinaturaServiceWave18Test.php` — 11 testes (34 assertions) cobre criar/pausar/retomar/cancelar idempotência + cross-tenant biz=99 + reflection spans.
  - `Tests/Feature/BoletoCredentialResolverTest.php` — 8 testes (17 assertions) cobre decryption de TODOS os 4 campos sensíveis + ModelNotFoundException pra biz sem credencial + fail-safe `resolveDriverName('unknown')`.
  - `Tests/Feature/CustomerJourneyTest.php` — 3 testes (32 assertions) — **D5 Customer Journey end-to-end 9 passos**: criar → invoice gerada → pausar → retomar → atualizar ciclo anual → overdue → pago atrasado → cancelar → MRR baseline zero.

### Changed

- `RecurringBillingServiceProvider::registerRepositories()` agora registra também `AssinaturaService` + `BoletoCredentialResolver` como singletons.

### Validated

- `php artisan vendor/bin/pest Modules/RecurringBilling/Tests/Feature/AssinaturaServiceWave18Test.php` → **11/11 passed (34 assertions)**
- `php artisan vendor/bin/pest Modules/RecurringBilling/Tests/Feature/BoletoCredentialResolverTest.php` → **8/8 passed (17 assertions)**
- `php artisan vendor/bin/pest Modules/RecurringBilling/Tests/Feature/CustomerJourneyTest.php` → **3/3 passed (32 assertions)**

### Refs

- ADR 0093 (multi-tenant Tier 0 IRREVOGÁVEL)
- ADR 0101 (tests biz=1, NUNCA biz=4 ROTA LIVRE)
- ADR 0094 §5 (SoC brutal)
- ADR 0155 (module-grade-v3)
- US-RB-044 (NFe-de-boleto-pago — preservado, listener `InvoicePaid` não tocado)

## [Wave 18] — 2026-05-16

### Added (saturação 56→95)

- `Repositories/SubscriptionRepository.php` + `Repositories/InvoiceRepository.php` (5+5 métodos canônicos)
- `Http/Requests/CancelInvoiceRequest.php`
- `Tests/Feature/RepositoryWave18Test.php` (8 cenários)

## [Wave 17] — 2026-05-15

### Added (saturação 39→56)

- `Console/Commands/RecurringHealthCommand.php` (5 checks SQL `rb:health`)
- `Services/AssinaturaCobrancaService::atualizarCobrancaAssinatura` (FIN-004 mutation)
- LogsActivity em Subscription/Plan/BoletoCredential (D7)
- module.json `lgpd_compliance` + `retention_days: 1825`
