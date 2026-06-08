# Changelog — RecurringBilling

Formato: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) + SemVer.

## [Unreleased]

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
