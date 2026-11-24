---
module: RecurringBilling
purpose: "Assinaturas, planos e faturas recorrentes (schema rb_*) com boleto e PIX (Inter, C6, Asaas) pelos drivers do Modules/PaymentGateway e webhooks. Gera faturas por schedule diário em live; a régua de dunning é declarativa e a cobrança automática (ChargeAttempt) ainda não é criada por código de produção."
migracao_ui: "concluido — 0 Blade servido (medido 2026-11-19: module-surface --migracao = servido 0, estado migrado); as 6 telas sao Inertia e /recurringbilling redireciona com 301"
contains:
  - "DataController"
  - "InstallController"
  - "RecurringBillingController"
  - "AsaasWebhookController"
  - "InterWebhookController"
  - "InvoiceController"
  - "PlanController"
  - "ConfiguracoesController"
  - "SubscriptionNoteController"
  - "SubscriptionFavoriteController"
  - "SubscriptionEventController"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
  - "Emissão da cobrança, driver do banco (BcbPixDriver, Pix Automático) e credenciais do gateway → Modules/PaymentGateway"
trust_required: L3
owner: wagner
permission_prefix: recurringbilling.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /recurring-billing/*
  - /recurringbilling/* (legacy 301 redirect — Onda 10 cutover v9,75; exceto /recurringbilling/install*, que o InstallController segue servindo)
  - /financeiro/rb-invoices/* (monta DENTRO do prefixo do Modules/Financeiro)
  - /webhooks/inter/* (RB serve /webhooks/inter/pix/{businessId}; o PG co-prove o PREFIXO com /webhooks/inter/{credentialId}, nao o /pix/)
  - /api/v1/* (scaffold nWidart · auth:sanctum)
  - /api/webhooks/*
drift_alerts: []
---

# Modules/RecurringBilling

## Missão

Cobrança recorrente BR: planos, assinaturas e faturas (`rb_*`), com geração diária de fatura (`rb:generate-invoices`), boleto/Pix pelos drivers do `Modules/PaymentGateway` (Inter, C6, Asaas; a pilha própria do RB, `BoletoService` + drivers, foi removida em 2026-11-24 — ADR 0456, decisão b1) e webhooks. Pix Automático (US-RB-020/021) e cartão salvo (US-RB-011) existem em código atrás de flag, apoiados no `Modules/PaymentGateway`. NFe da fatura paga vai pelo NfeBrasil (ARQ-0006), não NFSe. Não há smart retry.

> Texto da v1.0.0 (2026-05-05), preservado: *"Cobrança recorrente BR — Pix Automático, smart retries, NFSe automática. Spec-ready."* Foi a intenção da época; a frase acima é o construído medido em 2026-11-19.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
- **2026-11-19** — `migracao_ui` passou a `concluido` (o `module-surface --migracao` já media 0 Blade servido); Missão reescrita pelo construído; exceção do `/recurringbilling/install*` declarada no `url_prefixes`.
