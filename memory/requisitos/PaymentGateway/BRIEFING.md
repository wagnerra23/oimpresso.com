# BRIEFING — PaymentGateway

Camada técnica única de cobrança bancária BR do oimpresso (extraída de RecurringBilling via [ADR 0170](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md)). Concentra num só lugar a conversa com bancos: emissão de boleto/PIX/cartão, recebimento via webhook, conciliação por polling, processamento de retorno CNAB e cofre de credenciais multi-tenant. Quem precisa cobrar (Sell, RecurringBilling, Financeiro, NFSe) fala com `PaymentGatewayContract::for(Account)->emitir...` e ignora o driver. O `gateway_key` da credencial ativa resolve o driver via `PaymentGatewayService::DRIVERS`. Cobrança paga dispara o evento `CobrancaPaga`, consumido por `Modules/Financeiro` (`OnCobrancaPagaCreateFinanceiroTitulo`) que cria/baixa o título financeiro.

**Estado:** ativo (parcial em produção). Boleto Inter já roda no drawer da Visão Unificada do Financeiro (PR #2452, jun/2026). README/SPEC ainda carregam header "Onda 0 não habilitado" — **stale**; o código está além disso (45 testes Pest, casts cifrados, 17 drivers).

**Capacidades REAIS (drivers de 350–760 linhas com HTTP real — não stubs):**
- **6 drivers API REST** no mapa `DRIVERS`: **Inter** (boleto v3 + PIX cob/cobv + refund + OAuth2/mTLS, 760 linhas), **Asaas** (boleto+pix+cartão+refund), **C6** (boleto+pix), **BCB Pix Automático** (mandato recorrente, Res. BCB 380/2024), **Pagar.me v5** (boleto+pix+cartão), **Sicoob API v3** (boleto, OAuth2+mTLS reusando cert NFe).
- **11 drivers CNAB file-based** (remessa/retorno, lib eduardokum): Bradesco, Itaú, BB, Santander, Caixa, Sicoob, Ailos, Sicredi, Cresol, Banrisul, BTG.
- **Webhooks com validação de assinatura real** (`WebhookProcessor::validateSignature`, `hash_equals` constant-time, fail-secure) por provedor. As 3 vulns P0 do SPEC (cast em texto claro, `signature_valid:false` hardcoded) **já corrigidas** — `config_json` é `encrypted:array`.
- Conciliação PIX Inter por **polling** (`paymentgateway:inter-reconcile-pix`, caminho primário hoje), importação de recebimentos, retry de webhook órfão **com linkage `cobranca_id`** (US-PG-008 — `CobrancaWebhookResolver` reusa `driver->processWebhook`; cron `retry-orphan-webhooks` **dormente atrás de flag** até cutover Onda 3 + dry-run aprovado), CNAB retorno (Job + upload UI), health-check, UI de credenciais (`/settings/payment-gateways`).

**PLANEJADO, não construído (US no SPEC):**
- Cadastro automático da URL de webhook PIX no Inter (US-PG-005) — hoje só polling.
- Correção do auth do webhook Inter (mTLS vs HMAC; US-PG-006) + URL pública HTTPS no CT100 (US-PG-007).
- Throttle/timestamp-window/nonce nos webhooks (US-PG-003 — rotas usam só `['web']`).
- PesaPal: vestigial/deprecated, **não** implementado.

**Dependências reais:** APIs Inter/Asaas/C6/BCB/Pagar.me/Sicoob (OAuth2 + mTLS via cert A1 canon NfeCertificado); evento `CobrancaPaga` → Financeiro; multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)); consumido por Sell/RecurringBilling/NFSe.

**SPEC:** [SPEC.md](SPEC.md) · ver também [PLANO-ONDA5-SIMPLIFICADA.md](PLANO-ONDA5-SIMPLIFICADA.md), [RUNBOOK-sicoob-api.md](RUNBOOK-sicoob-api.md).

---
**Tipo:** BRIEFING destilado (KL-E3). **Estado:** ativo (parcial em prod — boleto Inter live). **Fonte:** código real `Modules/PaymentGateway/` (17 drivers, 45 Pest, `WebhookProcessor`, `config_json` cifrado) + `OnCobrancaPagaCreateFinanceiroTitulo`, git log #2452. Verificado 2026-06-15.
