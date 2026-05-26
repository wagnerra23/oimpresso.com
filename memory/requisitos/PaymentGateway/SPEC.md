---
slug: paymentgateway
title: "Especificação funcional — PaymentGateway"
type: spec
module: PaymentGateway
status: proposed
related_adrs: [0093, 0094, 0105, 0106, 0170]
pii: true
updated_at: 2026-05-25
last_updated: 2026-05-25
version: 0.1
owner: wagner
---

# Especificação funcional — PaymentGateway

> Convenção do ID: `US-PG-NNN` user stories, `R-PG-NNN` regras Gherkin.
> SPEC.md criada em 2026-05-25 pela Onda Audit Sênior — antes só existia [PLANO-ONDA5-SIMPLIFICADA.md](PLANO-ONDA5-SIMPLIFICADA.md) + [RUNBOOK-settings-gateways.md](RUNBOOK-settings-gateways.md). D3 Doc 0/15 → primeiro passo pra subir nota.

## Onda Audit Sênior 2026-05-25 — PR-0 Sec Hardening

> Origem: dossier inline `audit-senior-expert` 2026-05-25 (não persistido em .md devido a system reminder do agente). PaymentGateway 57/100 Médio. **3 vulnerabilidades P0 críticas detectadas** — Larissa biz=4 NÃO pode cobrar até PR-0 mergeado.
> Bypass MCP `tasks-create` (mcp_jira_projects ainda não tem entry "PaymentGateway") — webhook sincroniza no próximo push.

### US-PG-001 · [SEC P0] Encrypted cast config_json (drift ADR 0170 §G) + rewrap command + 3 Pest

> owner: — · priority: p0 · estimate: 8h · status: todo · type: story
> blocked_by: —

**VULN P0-#1:** `api_key` Asaas (`$aact_*`), `client_secret` Inter, `secret_key` Pagar.me, `webhook_secret`, `cert_password` mTLS — TODOS armazenados **em texto claro** em `payment_gateway_credentials.config_json`. Dump SQL (backup Hostinger, leak Hostinger CT 100, replicação mal-configurada) expõe tudo.

**Drift confirmado:** ADR 0170 §G linha 241 dizia "client_secret(encrypted), mTLS cert(encrypted), webhook_secret(encrypted)" mas IMPLEMENTAÇÃO atual (controller linha 152 + model cast `'array'`) NÃO implementou.

**Fix:**
```php
// PaymentGatewayCredential.php — trocar cast
protected $casts = [
    'config_json'       => 'encrypted:array',   // AES-256-CBC + MAC nativo Laravel 12
    'ativo'             => 'boolean',
    'health_checked_at' => 'datetime',
];
```

**Acceptance:**
- [ ] Encrypted cast aplicado
- [ ] Artisan command `paymentgateway:rewrap-credentials` (migration de rewrap dos `config_json` existentes)
- [ ] 3 Pest cenários (encrypt/decrypt/rewrap)
- [ ] PCI-DSS 4.0 compliance file/app-layer

**Refs:** ADR 0170 §G, [Laravel 12 Encrypted Casts](https://laravel-news.com/eloquent-encrypted-casting), [PCI-DSS 4.0](https://www.upguard.com/blog/pci-compliance)

### US-PG-002 · [SEC P0] HMAC validation 4 webhooks legacy (espelhar Pagarme/InterPix) + WebhookProcessor refactor + 8 Pest

> owner: — · priority: p0 · estimate: 12h · status: todo · type: story
> blocked_by: —

**VULN P0-#2:** `WebhookProcessor.php:55` grava `signature_valid: false` HARDCODED. 4 controllers SEM validação HMAC: AsaasWebhookController, InterWebhookController (legacy), C6WebhookController, BcbPixWebhookController. Atacante POST a `/paymentgateway/webhooks/asaas/1` com `{"event":"PAYMENT_RECEIVED"}` → sistema marca cobrança como paga FALSAMENTE.

**Blast radius:** fraude sobre `business.officeimpresso_bloqueado` (libera tenants sem pagamento) + fraude financeira `FinTitulo` listener Onda 5.

**Modelo de referência canon:** `PagarmeWebhookController` (Onda 4e) + `InterPixWebhookController` (Onda 26 US-FIN-032) JÁ implementam HMAC certo — só espelhar.

**Acceptance:**
- [ ] `WebhookProcessor::validateSignature(string $gatewayKey, Request $req, PaymentGatewayCredential $cred): bool`
- [ ] asaas → `asaas-access-token` header via `hash_equals` ([changelog fev/2026 mandatório](https://docs.asaas.com/changelog/obrigatoriedade-e-auto-gera%C3%A7%C3%A3o-de-tokens-para-webhooks))
- [ ] inter (legacy) → HMAC-SHA256 espelhando InterPixWebhookController
- [ ] c6 → `X-Hub-Signature-256` (GitHub-style)
- [ ] bcb_pix → mTLS cert fingerprint (Open Finance v2.1.0+)
- [ ] Fail-secure (sem secret cadastrado → 401)
- [ ] 8 Pest (4 happy path + 4 fail-secure)
- [ ] Marcar `signature_valid: true` SOMENTE se OK

**Refs:** ADR 0170, [Webhook signature 2026](https://hookray.com/blog/webhook-signature-verification-2026), [Replay prevention](https://webhooks.fyi/security/replay-prevention)

### US-PG-003 · [SEC P0] Throttle 120/min webhooks + timestamp window 5min + nonce-cache

> owner: — · priority: p0 · estimate: 4h · status: todo · type: story
> blocked_by: —

**VULN P0-#3:** Webhook routes `Modules/PaymentGateway/Routes/web.php:88-112` usam apenas `['web']`. Sem throttle, sem timestamp window, sem nonce-replay-cache. Vulnerável a (a) DoS 10k req/s aproveitando UNIQUE contention; (b) replay de webhook legítimo capturado em sniff.

**Fix:**
```php
// Routes/web.php
Route::middleware(['web', 'throttle:120,1'])
    ->prefix('paymentgateway/webhooks')
    ->group(...)

// WebhookProcessor::handle adicionar:
$timestamp = $payload['timestamp'] ?? $payload['horario'] ?? null;
if ($timestamp && abs(now()->timestamp - Carbon::parse($timestamp)->timestamp) > 300) {
    return response()->json(['ok'=>false,'error'=>'timestamp_outside_tolerance'], 401);
}
```

**Acceptance:**
- [ ] Throttle 120/min nos webhook routes
- [ ] Timestamp window 5min (Stripe-standard)
- [ ] Nonce-cache pra prevenção replay
- [ ] Pest cobre DoS rate-limit + replay rejection

**Refs:** [Stripe 5min default tolerance](https://www.hooklistener.com/learn/webhook-security-fundamentals), [Laravel ThrottleRequests](https://laravel.com/docs/12.x/rate-limiting)

### US-PG-004 · Doc mínimo: BRIEFING + CAPTERRA-FICHA + RUNBOOK-integrar-provider

> owner: — · priority: p1 · estimate: 12h · status: todo · type: story
> blocked_by: —

**Sintoma:** D3 Doc 0/15 — sem documentação canon. SPEC.md atual (este arquivo) é primeiro passo.

**Acceptance:**
- [ ] `BRIEFING.md` (1-pager ≤90d, estado consolidado)
- [ ] `CAPTERRA-FICHA.md` (providers suportados: Asaas, Inter, C6, BCB-Pix, Pagarme; taxas; SLA; fallback)
- [ ] `RUNBOOK-integrar-provider.md` passo-a-passo adicionar novo gateway (espelhar PagarmeDriver como modelo)
- [ ] Charters `.charter.md` ao lado dos 5 .tsx (Index, DrawerGateway, SheetNovoGateway, etc)

**Refs:** Audit dossier inline 2026-05-25 §PR-0 Doc-1/2/3

## Referências

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — Recalibração 10x
- [ADR 0170](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md) — Extração camada cobrança (drift §G detectado)
- [PLANO-ONDA5-SIMPLIFICADA.md](PLANO-ONDA5-SIMPLIFICADA.md)
- [RUNBOOK-settings-gateways.md](RUNBOOK-settings-gateways.md)
