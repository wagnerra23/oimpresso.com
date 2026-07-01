---
slug: paymentgateway
title: "Especificação funcional — PaymentGateway"
type: spec
module: PaymentGateway
status: ativo
related_adrs:
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0105-cliente-como-sinal-guiar-sem-mandar"
  - "0106-recalibracao-velocidade-fator-10x-ia-pair"
  - "0170-paymentgateway-extracao-camada-cobranca"
pii: true
updated_at: "2026-06-24"
last_updated: "2026-06-24"
version: "0.1"
owner: wagner
---

# Especificação funcional — PaymentGateway

> Convenção do ID: `US-PG-NNN` user stories, `R-PG-NNN` regras Gherkin.
> SPEC.md criada em 2026-05-25 pela Onda Audit Sênior — antes só existia [PLANO-ONDA5-SIMPLIFICADA.md](PLANO-ONDA5-SIMPLIFICADA.md) + [RUNBOOK-settings-gateways.md](RUNBOOK-settings-gateways.md). D3 Doc 0/15 → primeiro passo pra subir nota.

## User stories — Onda Audit Sênior 2026-05-25 PR-0 Sec Hardening

> Origem: dossier inline `audit-senior-expert` 2026-05-25 (não persistido em .md devido a system reminder do agente). PaymentGateway 57/100 Médio. **3 vulnerabilidades P0 críticas detectadas** — Larissa biz=4 NÃO pode cobrar até PR-0 mergeado.
> Bypass MCP `tasks-create` (mcp_jira_projects ainda não tem entry "PaymentGateway") — webhook sincroniza no próximo push.

### US-PG-001 · [SEC P0] Encrypted cast config_json (drift ADR 0170 §G) + rewrap command + 3 Pest

**Implementado em:** `Modules/PaymentGateway/Models/PaymentGatewayCredential.php` · `Modules/PaymentGateway/Console/Commands/RewrapCredentialsCommand.php` · `Modules/PaymentGateway/Tests/Feature/EncryptedCredentialCastTest.php` · verificado@98cae0a (2026-06-18)

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

**Implementado em:** `Modules/PaymentGateway/Http/Controllers/Webhooks/WebhookProcessor.php` · `Modules/PaymentGateway/Http/Controllers/Webhooks/AsaasWebhookController.php` · `Modules/PaymentGateway/Http/Controllers/Webhooks/InterWebhookController.php` · `Modules/PaymentGateway/Http/Controllers/Webhooks/C6WebhookController.php` · `Modules/PaymentGateway/Http/Controllers/Webhooks/BcbPixWebhookController.php` · `Modules/PaymentGateway/Tests/Feature/WebhookSignatureValidationTest.php` · verificado@98cae0a (2026-06-18)

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

**Implementado em:** _pendente_ — nenhum dos 3 itens landou: rotas webhook `Modules/PaymentGateway/Routes/web.php:101-131` seguem só `['web']` (sem `throttle:120,1`), `WebhookProcessor.php` não tem timestamp window nem nonce-cache (grep sem hit de throttle/nonce; sem timestamp-window de replay-protection — ocorrências de timestamp no módulo são timestamps() de migration/vencimento/Retry-After, inócuas @176f9bc)

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

**Implementado em:** _parcial_ · `memory/requisitos/PaymentGateway/BRIEFING.md` · `resources/js/Pages/Settings/PaymentGateways/Index.charter.md` · `resources/js/Pages/Settings/PaymentGateways/CnabRetorno.charter.md` · verificado@176f9bc (2026-07-01) — falta `CAPTERRA-FICHA.md` e `RUNBOOK-integrar-provider.md` (só existem RUNBOOK-settings-gateways.md + RUNBOOK-sicoob-api.md)

> owner: — · priority: p1 · estimate: 12h · type: story
> blocked_by: —

**Sintoma:** D3 Doc 0/15 — sem documentação canon. SPEC.md atual (este arquivo) é primeiro passo.

**Acceptance:**
- [ ] `BRIEFING.md` (1-pager ≤90d, estado consolidado)
- [ ] `CAPTERRA-FICHA.md` (providers suportados: Asaas, Inter, C6, BCB-Pix, Pagarme; taxas; SLA; fallback)
- [ ] `RUNBOOK-integrar-provider.md` passo-a-passo adicionar novo gateway (espelhar PagarmeDriver como modelo)
- [ ] Charters `.charter.md` ao lado dos 5 .tsx (Index, DrawerGateway, SheetNovoGateway, etc)

**Refs:** Audit dossier inline 2026-05-25 §PR-0 Doc-1/2/3

### US-PG-005 · Registrar URL de webhook PIX no Inter (PUT /pix/v2/webhook)

**Implementado em:** `Modules/PaymentGateway/Services/Drivers/InterDriver.php` · `Modules/PaymentGateway/Console/Commands/RegisterInterWebhookCommand.php` · `Modules/PaymentGateway/Tests/Feature/InterDriverRegisterWebhookTest.php` · verificado@98cae0a (2026-06-18)

> owner: eliana · priority: p2 · status: todo · type: story
> blocked_by: US-PG-006, US-PG-007

**Contexto:** o webhook PIX Inter (`InterPixWebhookController`, US-FIN-032) está pronto pra RECEBER, mas ninguém cadastra a URL de callback no Inter — por isso o Inter nunca chama. Hoje a confirmação roda por polling (`paymentgateway:inter-reconcile-pix`, commit fa22d5313), que é o fallback canônico. Esta task adiciona o webhook como otimização de latência.

**Aceite:**
- Comando/serviço que chama `PUT /pix/v2/webhook/{chave}` na API Pix do Inter com a URL `/webhooks/inter/{credentialId}` (escopo OAuth `webhook.write`).
- Botão/step no wizard `/settings/payment-gateways` pra (re)cadastrar + consultar (`GET /pix/v2/webhook/{chave}`) + remover.
- Idempotente; loga sucesso/falha; multi-tenant Tier 0 (credencial por business).
- Smoke no sandbox Inter validando que o callback chega.

### US-PG-006 · Corrigir autenticação do webhook Inter (mTLS em vez de HMAC x-inter-signature)

**Implementado em:** _pendente_ — correção não aplicada: `InterPixWebhookController::validateSignature()` e `WebhookProcessor::validateInterHmac()` seguem exigindo header HMAC `x-inter-signature` (fail-secure); nenhuma validação mTLS pro Inter (só o BCB usa `validateBcbPixMtls`) @176f9bc

> owner: eliana · priority: p2 · status: todo · type: story
> blocked_by: —

**Bug latente (P0 quando o webhook entrar):** `InterPixWebhookController::validateSignature()` e `WebhookProcessor::validateInterHmac()` exigem header `x-inter-signature` (HMAC-SHA256) e são *fail-secure* (sem header → 401). Mas a doc oficial do Inter diz que o webhook PIX é autenticado por **mTLS** (certificado mútuo), NÃO por header HMAC. Do jeito atual, **todo webhook real do Inter seria rejeitado com 401**.

**Aceite:**
- Confirmar o mecanismo real contra a doc/sandbox do Inter (developers.inter.co/references/pix).
- Validar o webhook por mTLS (fingerprint do cert do Inter no proxy/Caddy → `SSL_CLIENT_CERT`, espelhando o pattern `validateBcbPixMtls` já existente) OU pelo mecanismo que o Inter realmente usar.
- Não quebrar os testes existentes (`InterWebhookTest`) — ajustar expectativas.
- Documentar no SCOPE/CONTRACTS qual é o mecanismo canônico do Inter.

**Nota:** enquanto não resolvido, manter o polling como caminho primário de confirmação.

### US-PG-007 · Expor URL pública HTTPS do webhook Inter (deploy/proxy CT100)

**Implementado em:** _pendente_ — infra não landou (deploy/proxy CT100, humano-limitado ADR 0062): `docker/oimpresso-workers/Caddyfile` não roteia `/webhooks/inter/{credentialId}`, nenhum RUNBOOK documenta a URL pública nem reachability externa

> owner: eliana · priority: p2 · status: todo · type: story
> blocked_by: —

**Contexto:** o Inter só entrega webhook em endpoint HTTPS público com TLS válido, e retenta 4x (20/30/60/120min) se falhar. Hoje `/webhooks/inter/{credentialId}` existe nas rotas mas precisa estar acessível da internet no ambiente de runtime correto.

**Aceite:**
- Definir e expor a URL pública (respeitando separação Hostinger ≠ CT100, ADR 0062 — Centrifugo/FrankenPHP no CT100).
- TLS válido + roteamento do proxy/Caddy pra `/webhooks/inter/{credentialId}`.
- Confirmar reachability externa (curl de fora) e considerar mTLS no handshake (ver US-PG-006).
- Documentar a URL final no RUNBOOK do PaymentGateway pra cadastrar no Inter (US-PG-005).

### US-PG-008 · Linkage cobranca_id no webhook genérico + re-resolve do órfão

**Implementado em:** _parcial_ · `Modules/PaymentGateway/Services/Webhook/CobrancaWebhookResolver.php` · `Modules/PaymentGateway/Services/PaymentGatewayService.php` · `Modules/PaymentGateway/Http/Controllers/Webhooks/WebhookProcessor.php` · `Modules/PaymentGateway/Jobs/RetryOrphanWebhookJob.php` · `Modules/PaymentGateway/Console/Commands/RetryOrphanWebhookCommand.php` · `Modules/PaymentGateway/Tests/Feature/WebhookProcessorLinkageTest.php` · `Modules/PaymentGateway/Tests/Feature/RetryOrphanWebhookJobTest.php` · verificado@176f9bc (2026-07-01) — código do linkage mergeado (PR #3371, 007a418f40); falta o Gate REGRA MESTRE (cutover webhooks Onda 3 + `--dry-run` antes→depois + aprovação Wagner) antes de `PAYMENTGATEWAY_RETRY_ORPHAN_WEBHOOKS_ENABLED=true` — humano-limitado, flag OFF (valor/estoque)

> owner: wagner · priority: p1 · type: story
> blocked_by: —

**Arquivos (linkage · mergeado PR #3371 · ver âncora `Implementado em:` acima, ADR 0302/0273):** `Modules/PaymentGateway/Services/Webhook/CobrancaWebhookResolver.php` · `Modules/PaymentGateway/Services/PaymentGatewayService.php` (driverForKey) · `Modules/PaymentGateway/Http/Controllers/Webhooks/WebhookProcessor.php` (+ 6 controllers) · `Modules/PaymentGateway/Jobs/RetryOrphanWebhookJob.php` (re-resolve) · `Modules/PaymentGateway/Console/Commands/RetryOrphanWebhookCommand.php` (--dry-run) · Pest `WebhookProcessorLinkageTest` + `RetryOrphanWebhookJobTest`

**Contexto (drift SCOPE.md + censo artisan 2026-06-24):** `gateway_webhook_events.cobranca_id` nascia SEMPRE NULL — o `WebhookProcessor` não resolvia a Cobrança e nenhum outro caminho setava o campo. Logo a branch de quitação do `RetryOrphanWebhookJob` (dispatch `CobrancaPaga` = quita título = VALOR) era INALCANÇÁVEL: todo órfão caía em `still_orphan`. O cron `paymentgateway:retry-orphan-webhooks` está registrado mas DORMENTE (flag `PAYMENTGATEWAY_RETRY_ORPHAN_WEBHOOKS_ENABLED` default OFF — REGRA MESTRE valor/estoque). A quitação PIX biz=1 LIVE roda por outro caminho (`inter_webhook_log` + `ProcessarWebhookPixInterJob`).

**Aceite:**
- [x] Resolver único `payload → Cobrança` (`CobrancaWebhookResolver`) reusando `driver->processWebhook` (parser puro por driver — correto p/ o caso BCB `idRec` ≠ `txid` e Asaas/Pagar.me `payment.id`/`data.id` ≠ id do evento).
- [x] `WebhookProcessor` linka `cobranca_id` + persiste `payment_gateway_credential_id` no recebimento (best-effort: falha de linkage NUNCA impede a persistência/idempotência do webhook).
- [x] `RetryOrphanWebhookJob` re-resolve órfão `cobranca_id` NULL (race: webhook antes da emissão) em vez de desistir.
- [x] `--dry-run` mostra a taxa de linkage (antes→depois no nível do webhook — ferramenta do gate REGRA MESTRE).
- [x] Pest: extração por driver + multi-tenant biz=1 nunca casa biz=99 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)/[0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).
- [ ] **Gate REGRA MESTRE (pré-flag, humano-limitado, Wagner):** cutover dos webhooks Onda 3 (registrar URLs nos gateways; tabela VAZIA em prod hoje) → `php artisan paymentgateway:retry-orphan-webhooks --dry-run` → tabela antes→depois dos títulos afetados → aprovação explícita ANTES de `PAYMENTGATEWAY_RETRY_ORPHAN_WEBHOOKS_ENABLED=true`.

**Refs:** [ADR 0170](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md), SCOPE.md `drift_alerts`, censo [memory/sessions/2026-06-24-censo-artisan.md](../../sessions/2026-06-24-censo-artisan.md).

## Referências

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — Recalibração 10x
- [ADR 0170](../../decisions/0170-paymentgateway-extracao-camada-cobranca.md) — Extração camada cobrança (drift §G detectado)
- [PLANO-ONDA5-SIMPLIFICADA.md](PLANO-ONDA5-SIMPLIFICADA.md)
- [RUNBOOK-settings-gateways.md](RUNBOOK-settings-gateways.md)

### US-PG-009 · Executar smokes humano-limitados PaymentGateway Onda 5 (biz=1 + canary Larissa)

**Implementado em:** _pendente_ — tarefa humano-limitada (relógio real, ADR 0106): smoke biz=1 + canary Larissa não executados; sem roteiro nem evidência (screenshots/logs) anexada @176f9bc

> owner: — · priority: p1 · estimate: 3h · status: todo · type: story
> blocked_by: —
> parent_plan: paymentgateway-onda-5-dogfooding

**Iniciativa-plano perdida** recuperada pro backlog (triagem 2026-06-20 · run wf_1bfbefba).
labels: `plano-perdido`, `backlog-2026-06-20`

**Sinal (ADR 0105):** código da Onda 5 já feito (PR #1148); restam pendências **humano-limitadas** (relógio do mundo real, ADR 0106): smoke biz=1 + canary Larissa biz=4.
**Dedup:** distinto de US-PG-002/003 (SEC webhooks) e US-PG-005/006/007 (webhook Inter).

**DoD:**
- Roteiro de smoke real biz=1.
- Canary controlado Larissa.
- Evidência (screenshots/logs) anexada.

**Fonte:** memory/requisitos/_processo/BATCH-BACKLOG-34-2026-06-20.md (§Aprovação [W] 2026-06-20)
