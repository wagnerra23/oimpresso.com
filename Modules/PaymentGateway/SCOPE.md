---
module: PaymentGateway
purpose: "Camada técnica de cobrança BR — drivers Inter/C6/Asaas/Pix Automático BCB, webhooks, CNAB, credenciais. Consumida por Sell, RecurringBilling, NFSe, Superadmin license."
contains:
  - "PaymentGatewayController"
  - "CobrancaController"
  - "Settings/PaymentGatewaysController — F3 PaymentGateway UI Tela 2 (CRUD credenciais + health check + toggle, Onda 4d.3)"
  - "Settings/PaymentGatewaysCnabRetornoController — POST /settings/payment-gateways/{credential}/cnab-retorno (upload arquivo retorno + histórico processamento, Onda 4f.0)"
  - "DataController — 3 hooks UltimatePOS (Onda 1)"
  - "InstallController — extends BaseModuleInstallController (Onda 1)"
  - "Webhooks/InterWebhookController — POST /paymentgateway/webhooks/inter/{businessId} (Onda 3 sem cutover)"
  - "Webhooks/C6WebhookController — POST /paymentgateway/webhooks/c6/{businessId} (Onda 3 sem cutover)"
  - "Webhooks/AsaasWebhookController — POST /paymentgateway/webhooks/asaas/{businessId} (Onda 3 sem cutover)"
  - "Webhooks/BcbPixWebhookController — POST /paymentgateway/webhooks/bcb-pix/{businessId} (Onda 3 novo)"
  - "Webhooks/InterPixWebhookController — POST /webhooks/inter/{credentialId} (Onda 26 US-FIN-032 PIX recebido → titulo auto-pago)"
  - "Webhooks/PagarmeWebhookController — POST /paymentgateway/webhooks/pagarme/{businessId} (Onda 4e) — HMAC-SHA256 via header X-Hub-Signature-256"
  - "Webhooks/SicoobApiWebhookController — POST /paymentgateway/webhooks/sicoob-api/{businessId} (Onda 4f.sicoob_api · US-FIN-044) — HMAC-SHA256 via header x-sicoob-signature"
  - "Services/PaymentGatewayService — implementa PaymentGatewayContract; for(Account) resolve credential; idempotência (Onda 4a); DRIVERS mapa atualizado Onda 4b com 3 drivers"
  - "Services/Drivers/InterDriver — Inter API v3 OAuth2+mTLS (boleto Onda 4a + pix_cob/pix_cobv Onda 4b/4c + refund PIX Onda 4c + cancelar + consultar + healthCheck + processWebhook). Refund de boleto Inter NÃO via API — exige TED reverso manual"
  - "Services/Drivers/AsaasDriver — Asaas REST v3 (boleto + pix_cob + card + refund parcial + cancelar + consultar + healthCheck + processWebhook); Onda 4b"
  - "Services/Drivers/C6Driver — C6 Open Banking PJ OAuth2 (boleto + pix_cob + cancelar + consultar + healthCheck + processWebhook); Onda 4b. CNAB legacy fallback fica em RB"
  - "Services/Drivers/BcbPixDriver — PSP-agnóstico PIX Automático (Resolução BCB 380/2024) — pix_recv (mandato recorrente) + revogar + consultar + healthCheck + processWebhook; Onda 4d.1. base_url configurável via credential"
  - "Services/Drivers/PagarmeDriver — Pagar.me v5 REST (boleto + pix_cob + card + refund parcial + cancelar + consultar + healthCheck + processWebhook); Onda 4e. HTTP Basic Auth via secret_key, sandbox via prefixo sk_test_*"
  - "Services/Drivers/SicoobApiDriver — Sicoob API Cobrança Bancária v3 REST OAuth2+mTLS (boleto + cancelar + consultar + healthCheck + processWebhook); Onda 4f.sicoob_api · US-FIN-044 + US-FIN-046. Convênio + carteira (1 Simples / 3 Caucionada) + modalidade. Cache token Redis-safe por business_id. mTLS REUSA NfeCertificado canon via CertificadoService::carregarParaSefaz (single source of truth — mesmo cert A1 usado pra NFe SEFAZ)"
  - "BoletoService (Onda 4d alto-nível)"
  - "RemessaCnabService (Onda 4d)"
  - "RetornoCnabService (Onda 4d)"
  - "PaymentGatewayCredentialResolver (Onda 4d se realmente precisar)"
  - "Drivers planejados: PesaPalDriver (deprecated Onda 5/6)"
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
  - /webhooks/pagarme (Onda 4e)
db_tables_owned:
  - payment_gateway_credentials
  - cobrancas
  - gateway_webhook_events
drift_alerts:
  - "Linkage gateway_webhook_events.cobranca_id IMPLEMENTADO (US-PG-008, PR pendente): WebhookProcessor resolve a Cobrança no recebimento + RetryOrphanWebhookJob re-resolve a race, ambos via CobrancaWebhookResolver (reusa driver->processWebhook — correto p/ BCB idRec). A branch de quitação do Job deixou de ser inalcançável. PORÉM o cron paymentgateway:retry-orphan-webhooks SEGUE DORMENTE (flag PAYMENTGATEWAY_RETRY_ORPHAN_WEBHOOKS_ENABLED default OFF) — habilitar só após cutover dos webhooks Onda 3 (registrar URLs nos gateways; tabela VAZIA em prod hoje) + dry-run + tabela antes→depois + aprovação Wagner (REGRA MESTRE valor/estoque). A quitação PIX biz=1 LIVE continua por inter_webhook_log + ProcessarWebhookPixInterJob, não por aqui."
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
- **v0.1.1** (2026-06-24) — Fix ghost-scheduled `paymentgateway:retry-orphan-webhooks` (censo artisan): docblock prometia cron `everyFiveMinutes` que NÃO existia no Kernel. Schedule **registrado** (sai do limbo + aparece em `schedule:list`) porém **DORMENTE** via flag default-OFF `PAYMENTGATEWAY_RETRY_ORPHAN_WEBHOOKS_ENABLED` (REGRA MESTRE valor/estoque — o Job quita título). Backlog prod = **0** (tabela `gateway_webhook_events` vazia, dry-run confirmou). Docblock corrigido + drift_alert + testes (schedule-registration + command). Habilitar só após cutover Onda 3 + linkage cobranca_id + dry-run aprovado.
- **v0.1.2** (2026-06-24) — Linkage `cobranca_id` destravado (US-PG-008, PR pendente): `CobrancaWebhookResolver` (reusa `driver->processWebhook`, correto p/ BCB `idRec`) + `WebhookProcessor` linka no recebimento + persiste `payment_gateway_credential_id` + `RetryOrphanWebhookJob` re-resolve a race (antes desistia em `still_orphan`). `--dry-run` mostra taxa de linkage. Drive-by: `PagarmeWebhookController` passa `signatureValid` (corrige ArgumentCountError latente). Cron **SEGUE DORMENTE** (flag OFF) até cutover Onda 3 + dry-run/antes→depois aprovado. Pest linkage + multi-tenant biz=1/99.
