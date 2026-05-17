# Whatsapp — Changelog

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
