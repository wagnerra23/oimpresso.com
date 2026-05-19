---
module: PaymentGateway
purpose: "Camada técnica de cobrança BR — drivers Inter/C6/Asaas/Pix Automático BCB, webhooks, CNAB, credenciais. Consumida por Sell, RecurringBilling, NFSe, Superadmin license."
contains:
  - "PaymentGatewayController"
  - "CobrancaController"
  - "DataController — 3 hooks UltimatePOS (Onda 1)"
  - "InstallController — extends BaseModuleInstallController (Onda 1)"
  - "Webhooks/InterWebhookController — POST /paymentgateway/webhooks/inter/{businessId} (Onda 3 sem cutover)"
  - "Webhooks/C6WebhookController — POST /paymentgateway/webhooks/c6/{businessId} (Onda 3 sem cutover)"
  - "Webhooks/AsaasWebhookController — POST /paymentgateway/webhooks/asaas/{businessId} (Onda 3 sem cutover)"
  - "Webhooks/BcbPixWebhookController — POST /paymentgateway/webhooks/bcb-pix/{businessId} (Onda 3 novo)"
  - "Services/PaymentGatewayService — implementa PaymentGatewayContract; for(Account) resolve credential; idempotência (Onda 4a); DRIVERS mapa atualizado Onda 4b com 3 drivers"
  - "Services/Drivers/InterDriver — Inter API v3 OAuth2+mTLS (boleto Onda 4a + pix_cob Onda 4b + cancelar + consultar + healthCheck + processWebhook)"
  - "Services/Drivers/AsaasDriver — Asaas REST v3 (boleto + pix_cob + card + refund parcial + cancelar + consultar + healthCheck + processWebhook); Onda 4b"
  - "Services/Drivers/C6Driver — C6 Open Banking PJ OAuth2 (boleto + pix_cob + cancelar + consultar + healthCheck + processWebhook); Onda 4b. CNAB legacy fallback fica em RB"
  - "BoletoService (Onda 4d alto-nível)"
  - "RemessaCnabService (Onda 4d)"
  - "RetornoCnabService (Onda 4d)"
  - "PaymentGatewayCredentialResolver (Onda 4d se realmente precisar)"
  - "Drivers planejados: BcbPixDriver (4d — PIX Automático regulado), PesaPalDriver (deprecated Onda 5/6)"
not_contains:
  - "Plan / Assinatura / Invoice recorrente → Modules/RecurringBilling (consome este módulo)"
  - "Sale / Transaction de venda → app/ core (consome este módulo via PaymentGatewayContract)"
  - "NFSe / NFe emissão → Modules/NFSe / Modules/NfeBrasil (escuta evento CobrancaPaga)"
  - "Subscription SaaS Wagner→tenants → Plan em RecurringBilling biz=1 + handler Superadmin"
  - "Plano de contas contábil → Modules/Accounting"
  - "Account (saldo bancário) → app/Account.php (core); só recebe FK das credenciais"
trust_required: L3
owner: wagner
permission_prefix: paymentgateway.*
charter_adr: 0170
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
url_prefixes:
  - /payment-gateway/*
  - /cobranca/*
  - /webhooks/inter (migrado de RecurringBilling, redirect 301 das URLs antigas durante 30d)
  - /webhooks/c6 (migrado)
  - /webhooks/asaas (migrado)
  - /webhooks/bcb-pix (novo)
db_tables_owned:
  - payment_gateway_credentials
  - cobrancas
  - gateway_webhook_events
drift_alerts: []
---

# Modules/PaymentGateway

## Missão

Camada técnica de cobrança BR — drivers Inter/C6/Asaas/Pix Automático BCB, webhooks, CNAB, credenciais. Consumida por Sell, RecurringBilling, NFSe, Superadmin license.

Extraído de `Modules/RecurringBilling` (ADR 0170) pra desacoplar **infra de cobrança** (este módulo) de **lógica de recorrência** (RecurringBilling).

## Trust level

**L3** — toca dinheiro, dados de pagador (LGPD), credenciais de API bancária (segredos), regulação CMN/BCB. Ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte [ARCHITECTURE.md](../../memory/governance/ARCHITECTURE.md).

Regra de ouro: **se a operação envolve "quando cobrar / com que frequência", é RecurringBilling. Se envolve "como falar com banco pra emitir/cancelar", é PaymentGateway.**

## Contrato público

Quem consome este módulo (Sell, RecurringBilling, NFSe, Superadmin) **só conhece** [`PaymentGatewayContract`](Contracts/PaymentGatewayContract.php) e os 5 eventos broadcast (`CobrancaEmitida`, `CobrancaPaga`, `CobrancaVencida`, `CobrancaCancelada`, `CobrancaErro`).

Drivers concretos (`InterDriver`, `AsaasDriver`, etc) **nunca aparecem fora do módulo**.

Ver [CONTRACTS.md](CONTRACTS.md) pra interface + DTOs + payloads de evento.

---

- **v0.1.0** (2026-05-19) — SCOPE.md inicial, ADR 0170 proposto. Módulo registrado mas **não habilitado** (Onda 0 do roadmap).
