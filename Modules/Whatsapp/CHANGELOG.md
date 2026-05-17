# Whatsapp — Changelog

## [Wave 26 — 2026-05-17] Drivers + Webhook polish ≥85 (74 → ≥85 +11pp)

### D2 — Expansão Drivers + WebhookSignatureChecker
- Novo Pest `Tests/Feature/Wave26WhatsappSaturationTest.php` (~25 cenários):
  - **BaileysDriver**: spans canon hot-path (`send_freeform/send_media/send_interactive`) + encapsulamento privado (`mapSendResponse/normalizePhone/client`) + cta_url rejeitado via `DriverDoesNotSupport::for` (fail-fast pro caller cair pro Meta Cloud) + `fetchMessageStatus` retorna MessageStatus (status real via webhook).
  - **MetaCloudDriver `parseInboundWebhook`** (PR4 PoC BSUID identifier mar/2026+): extrai 3 identifiers (wa_id + phone_e164 + bsuid); tolerante a payload pré BSUID (sem `contacts[].user_id`); tolerante a payload vazio; extrai body type=button + type=interactive.
  - **MetaCloudDriver `fetchTemplates`** (HSM aprovação Meta Business Manager) método público confirmado.
  - **WebhookSignatureChecker** (Wave 18 D4): rejeita prefixo errado (CRÍTICO Meta canon `sha256=`); rejeita hex inválido + header vazio/null; usa `hash_equals` constant-time (anti timing-attack); aliases canon dispatch (`meta`/`meta_cloud`, `zapi`/`z-api`/`z_api`); class final stateless puro (sem constructor); 3 headers canon constants (`HEADER_META` + `HEADER_BAILEYS` + `HEADER_ZAPI`).
  - **DriverDoesNotSupport** factory `::for(driver, capability)` cross-driver fail-fast.

### D4 — Services pattern (Wave 18 D4 baseline preservar)
- Services subdirs ≥10 (Audio + Centrifugo + Contacts + Csat + Drivers + Macros + Metrics + Notes + Sla + Webhook).
- `MessagePersister` (Webhook D4) bindable container.
- `WebhookSignatureChecker` class final canon.

### D3 — CHANGELOG (este entry) + BRIEFING.md Wave 26

### IRREVOGÁVEIS preservados
- BaileysDriver custom + MetaCloudDriver fallback (ADR 0096 emenda 4).
- EvolutionDriver ainda inexistente (proibido permanente).

## [Wave 25 — 2026-05-16] Drivers SATURATION + D4 services pattern (74→≥85)

### D2/D3 — Drivers contract coverage
- Novo Pest `Tests/Feature/Wave25WhatsappSaturationTest.php` — 15 cenários (15/15 passed, 41 assertions):
  - `BaileysDriver` + `MetaCloudDriver` + `ZapiDriver` + `NullDriver` todos implementam `DriverInterface` canon (6 métodos canon: sendTemplate/sendFreeform/sendMedia/fetchMessageStatus/ping/sendInteractive).
  - `EvolutionDriver` confirmado NÃO existe (ADR 0096 emenda 4 — proibido permanente).
  - `DriverInterface::sendFreeform` aceita union type `WhatsappBusinessConfig|WhatsappBusinessPhone` (ADR 0117 multi-números).
  - DI canon: `BaileysDriver` + `MetaCloudDriver` resolvem do container.

### D4 — Services pattern (preserva Wave 17/18 — OtelHelper canon)
- `BaileysDriver` importa `App\Util\OtelHelper` + ≥3 spans `whatsapp.baileys.*` hot-path (send_freeform/send_media/etc).
- `MetaCloudDriver` importa OtelHelper + ≥2 spans `whatsapp.meta_cloud.*` (send_template/send_freeform).
- `WebhookSignatureChecker` (Wave 18 D4) saturado: dispatcher rejeita driver desconhecido (fail-secure), Meta exige prefixo `sha256=` (formato canon Meta), Baileys aceita hex puro (formato daemon Node), resiste a key rotation (secret rotacionado = false).

### D7 — Auditoria preservada
- Drivers/Services não introduzem PII bypass — Trust L0 mantido (testes via Reflection + Http::fake nos drivers individuais).

## [Wave 18 RETRY — 2026-05-16] Saturação governance v3 — D2/D4/D5/D9 +Δ

### D4 Architecture — Service extraction (RETRY +1 service)
- `Services/Webhook/WebhookSignatureChecker.php` — verificação HMAC canon pra 3 drivers (Meta Cloud com prefixo `sha256=`, Baileys hex puro, Z-API hex puro). Dispatch `verify($driver, ...)`. Stateless. 3 spans canon `whatsapp.webhook.signature.*`. Uniformiza checks dispersos em ChannelBaileysWebhookController e MetaCloudWebhookController sem mudar comportamento.

### D2 Pest novo (RETRY +2 arquivos)
- `Tests/Feature/WebhookSignatureCheckerTest.php` — 12 cenários: Meta formato correto, sem prefixo, header null/vazio, payload tampered, secret errado (key rotation), hex inválido, Baileys hex puro, rejeição cross-format, Z-API, dispatch verify por driver, source-grep 3 spans canon, hash_equals constant-time.
- `Tests/Feature/E2EJourneyBiz1Test.php` — 5 cenários E2E: webhook Meta validado→ingestão→snapshot, tampered webhook NÃO persiste, drivers múltiplos coexistem por business sem leak, janela 24h exclui antigas, snapshot vazio estrutura completa.

### D5 Cliente real / Journey (RETRY)
- Journey E2E formalizado em `E2EJourneyBiz1Test.php` cobre webhook signature → ingestão → snapshot (3 passos do README cobertos por Pest verificável).

### D9 Observabilidade (RETRY +3 spans)
- `whatsapp.webhook.signature.verify_meta`, `verify_baileys`, `verify_zapi` — todos zero-cost se otel.enabled=false.

## [Wave 18 — 2026-05-16] Saturação governance v3 (inicial)

### D4 Architecture — Service extraction
- `Services/Metrics/MetricsSnapshotBuilder.php` extraído de `WhatsappObservabilityHealthCommand` — snapshot agregado outbound/driver com OtelHelper spans canon `whatsapp.metrics.*`. Stateless, multi-tenant Tier 0 explícito (caller passa `$businessId`).

### D2 Pest novo
- `Tests/Feature/MetricsSnapshotBuilderTest.php` — 7 cenários cobrindo fail-soft schema, cálculo taxa, isolamento cross-tenant biz=1 vs biz=99, janela temporal, direction filter, agregação por driver.

### D5 Cliente real / Journey
- `README.md` criado — journey 8 passos biz=1 (webhook entrante → snapshot dashboard) + drivers table + observability spans canon catalogados.

### D9 Observabilidade
- MetricsSnapshotBuilder usa `App\Util\OtelHelper::span` (canon) — 2 spans novos: `whatsapp.metrics.snapshot_outbound`, `whatsapp.metrics.snapshot_por_driver`.

## Histórico anterior

(ver `memory/requisitos/Whatsapp/AUDIT-LOG.md` pra histórico pré-CHANGELOG canônico)
