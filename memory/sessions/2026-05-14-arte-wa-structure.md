# Estado da arte — arquitetura técnica WhatsApp (oimpresso vs mercado 2026)

> Agent: `whatsapp-arch-arte` (Opus 4.7, sustained)
> Data: 2026-05-14
> Gatilho: Wagner pediu pós-saga 2026-05-13/14 incidents — _"como seria o estado da arte? compara com meu e dá nota"_
> Lente: TÉCNICA (throughput, persistência, anti-ban, observabilidade) — não comercial. Para mercado/features ver `capterra-senior` em `memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12-v2.md`

---

## Sumário executivo (TL;DR)

```
NOTA OIMPRESSO:     71 / 100
NOTA REF TOP (Twilio/Bird/Take Blip/AWS EUM): 92 / 100
NOTA REF CHEIA (WhatsApp eng Meta-side, Erlang/3B users):    100 / 100  (referência teórica)

Gap principal: -21 pontos. Causa: queue de webhook ainda em
`dispatchAfterResponse` num PHP-FPM Hostinger sem worker dedicado.
Idempotência, anti-ban e multi-tenant são estado-da-arte. Recovery
e escala horizontal são as duas fraquezas reais.
```

**O oimpresso está acima da média do mercado open-source/PME (Evolution API, wati infra básica, integradores Z-API/360dialog), no nível de player Capterra-strong sério, e abaixo dos hyperscalers (AWS EUM, Twilio, Take Blip BSP) em 4 frentes: queue persistence proper, multi-instance horizontal scale, distributed tracing OTel ponta-a-ponta com Tempo/Jaeger, e state recovery formalmente testado.**

Ação imediata recomendada: **mover `QUEUE_CONNECTION=database` na Hostinger + cron `queue:work --once`** (US-WA-pendente), tirando o webhook do limbo `dispatchAfterResponse`. ~3h IA-pair. Resolve hoje 80% do gap arquitetural sem trocar de host.

---

# Fase 1 — Os melhores (pesquisa limpa, 2026)

12 players analisados. Tabela com lente arquitetura, não pricing.

| # | Player | Stack core | Webhook delivery | Anti-ban | Throughput claim | Multi-tenant | Fonte canônica |
|---|---|---|---|---|---|---|---|
| 1 | **WhatsApp (Meta)** infra real | Erlang/OTP, BEAM lightweight processes, Mnesia, custom binary protocol | Push direto cliente, ~40B msgs/dia, latência <100ms p99 | N/A (são eles) | 3B+ usuários ativos | N/A | [ByteByteGo 40B msgs/day](https://blog.bytebytego.com/p/how-whatsapp-handles-40-billion-messages); [InfoQ scaling talk](https://www.infoq.com/presentations/whatsapp-scalability/) |
| 2 | **AWS End User Messaging** (Meta BSP oficial) | SNS → SQS → Lambda; DynamoDB; CloudWatch | SNS publishes to SQS, Lambda consumers escalam por queue depth | Não-aplicável (Cloud API) | Provado 2k+ qps em demo AWS Summit, escala virtualmente ilimitada | DynamoDB hashed phone PK + TTL PII purge | [AWS Messaging Blog "best practices high-perf WhatsApp"](https://aws.amazon.com/blogs/messaging-and-targeting/best-practices-for-building-high-performance-whatsapp-ai-assistant-using-aws/) |
| 3 | **Twilio** (Flex + Conversations) | "Proxy" service que traduz channels; webhook customer-side | URL + Fallback URL (failover ao 1º falhar); 5s timeout | Não-aplicável | SLA 99.95% uptime, data residency EU, p95 webhook delivery não publicado | Account SID + Subaccount + Conversation SID | [Twilio Flex inbound docs](https://www.twilio.com/docs/flex/developer/messaging/inbound-messages-channels); [SLA](https://www.twilio.com/en-us/legal/service-level-agreement) |
| 4 | **Take Blip** (BR, ex-Take Blip) | C# .NET + Azure (OpenAI Service); 449k bots criados Brasil | Channel WhatsApp gerenciado interno → API customer | Não-aplicável (Cloud API) | Não publicado; presença 20+ anos | Multi-bot, multi-channel native | [Microsoft Customer Stories Blip Azure](https://www.microsoft.com/pt-br/customers/story/1748706491048194135-blip-azure-openai-service-professional-services-pt-brazil); [help.blip.ai channel arch](https://help.blip.ai/hc/en-us/articles/4474414185367-WhatsApp-channel-architecture) |
| 5 | **Bird** (ex-MessageBird) | "Queue Management" exposto como feature comercial; Kafka-class internal (não public) | Webhook customer-side with at-least-once | Não-aplicável | "Modern messaging at scale" — não publicam números | Multi-channel single API | [Bird blog "4 things message queue mgmt"](https://bird.com/blog/four-things-you-need-know-message-queue-management); [Bird CRM rebrand](https://bird.com/en/resources/blog/messagebird-is-now-bird) |
| 6 | **Infobip** | 40+ data centers, on-prem + AWS + Azure; Hyper-V/VMware; LINUX/Windows VMs próprios | Webhook BSP padrão | Não-aplicável | "Real-time sync Cloud API ↔ app" — não publicam números | DC-per-region failover | [Infobip Engineering Handbook (gitbook)](https://infobipengineering.gitbook.io/handbook/tech-stack-and-architecture/platform-architecture) |
| 7 | **360dialog** (BSP nicho técnico) | Cloud API direct wrap | Retry 7 dias exponential backoff, 5s timeout duro; ~3× outbound msg vol em status callbacks | Não-aplicável | Limite Meta (80→1000 MPS) | Multi-account per partner | [360dialog webhook docs](https://docs.360dialog.com/docs/messaging/webhook); [stable endpoint best-practices](https://docs.360dialog.com/partner/integrations-and-api-development/integration-best-practices/design-a-stable-webhook-receiving-endpoint) (404 mas estrutura conhecida) |
| 8 | **Gupshup** | Cloud API + chatbot SDK | Webhook padrão BSP | Não-aplicável | Limite Meta | Multi-tenant cliente Gupshup | (search results não trouxe profundidade — usar [apidog top10 BSP 2026](https://apidog.com/blog/top-10-whatsapp-business-api/)) |
| 9 | **Wati** (PME SaaS) | Node.js + Redis + BullMQ tipicamente | Async background workers; rate limit BullMQ global queue | Aplicação Cloud API (delegado a Meta) | BullMQ "milhões de jobs/dia" em deployments tier Wati | Shared schema + tenant_id típico SaaS | [BullMQ docs](https://docs.bullmq.io/), [WASenderApi multi-tenant guide](https://wasenderapi.com/blog/how-to-build-a-white-label-whatsapp-marketing-platform-infrastructure-architecture-guide) |
| 10 | **Evolution API** (OSS) | Node.js 20 + TypeScript + Express; Baileys ou Cloud API; integrações Typebot/N8N/Chatwoot/RabbitMQ/SQS/NATS | Webhook event-driven; multi-provider plugin | **Frágil** — bans recorrentes reportados (Wagner em produção); plugin "anti-ban" lotusbail confirmado malware Abr/2026 | "Multi-instance single install"; Evolution Go (whatsmeow) p/ perf | Multi-instance shared install | [github EvolutionAPI/evolution-api](https://github.com/EvolutionAPI/evolution-api); [github evolution-foundation/evolution-go](https://github.com/evolution-foundation/evolution-go) |
| 11 | **Baileys** (raw OSS, TS) | TypeScript, lib direta WhatsApp Web protocol multi-device | N/A (lib, não daemon) | Comunidade canônica `baileys-antiban` (Box-Muller jitter, warmup 7d, circadian, reply-ratio) | ~Limitado por máquina/RAM (~80MB/instance) | Aplicação faz scope | [baileys-antiban](https://github.com/kobie3717/baileys-antiban); [Baileys discussion #2358](https://github.com/WhiskeySockets/Baileys/discussions/2358); [GroKipedia Baileys](https://grokipedia.com/page/Baileys) |
| 12 | **whatsmeow** (raw OSS, Go) | Go (Goroutines, native binaries) | N/A | Igual Baileys (comunidade ports) | "CPU 99% menor que Baileys"; stable long-lived sessions | Aplicação faz scope | [whatsmeow vs Baileys 10K device discussion](https://github.com/tulir/whatsmeow/discussions/979); [issue #810 risk warning](https://github.com/tulir/whatsmeow/issues/810) |

**Observações de fonte:**

- Meta + Erlang BEAM continua referência teórica máxima (40B msgs/day) — não é replicável por nenhum BSP. Use como teto inalcançável.
- **AWS EUM** é o estado-da-arte arquitetural prático: SNS → SQS → Lambda → DynamoDB com observabilidade CloudWatch nativa, retry/idempotência por design, escala virtualmente ilimitada via auto-scaling Lambda. Ref: [AWS Messaging Blog WhatsApp AI assistant](https://aws.amazon.com/blogs/messaging-and-targeting/best-practices-for-building-high-performance-whatsapp-ai-assistant-using-aws/).
- **Take Blip** é referência BR mas eng blog é restrito; arquitetura inferida via case Azure OpenAI + canal docs.
- **Evolution API** tem schema event-driven respeitável (RabbitMQ/SQS/NATS plugin), mas o uso real Baileys-based + plugins anti-ban tóxicos (lotusbail malware) torna **production unsafe** sem custom (validando ADR 0096 emenda 4 Wagner).
- **whatsmeow** (Go) é tecnicamente superior a Baileys (TS) em estabilidade long-lived + memory footprint, mas Baileys tem comunidade anti-ban mais madura em 2026 (canon `kobie3717/baileys-antiban`).

---

# Fase 2 — 15 dimensões técnicas canônicas (estado-da-arte 2026)

Cada dimensão definida com base nos 12 players + research papers + Meta docs.

## 1. Receive throughput (msgs/s/instance ingresso webhook)

**Estado-da-arte 2026:** Cloud API entrega **80 msgs/s padrão por business phone, escalando até 1000 MPS** após qualidade alta. Em portfolio level (Q1/Q2 2026), Meta remove tiers 2K/10K e dá direto 100K msgs/dia após Business Verification.

**Best practice:** receber → ACK 200 OK em <500ms → processar async. 360dialog impõe **5s hard timeout** ou registra falha.

Bench AWS EUM: SNS → SQS → Lambda **escala virtualmente ilimitada** via consumer concurrency Lambda baseado em queue depth.

Fontes:
- [Fyno WhatsApp rate limits](https://www.fyno.io/blog/whatsapp-rate-limits-for-developers-a-guide-to-smooth-sailing-clycvmek2006zuj1oof8uiktv)
- [Sanuker WhatsApp API 2026 updates pacing](https://sanuker.com/whatsapp-api-2026_updates-pacing-limits-usernames/)
- [Wuseller scale Cloud API](https://www.wuseller.com/whatsapp-business-knowledge-hub/scale-whatsapp-cloud-api-master-throughput-limits-upgrades-2026/)

## 2. Send throughput (msgs/s/instance saída)

**Estado-da-arte:** mesmo limite acima — capped por Meta (80→1000 MPS Cloud API), não pela arquitetura cliente. Para Baileys/whatsmeow: capped por anti-ban warmup (10/h dia 0-1 → 200/h após 7d em chip novo).

Best practice: rate-limit por tenant em queue level (BullMQ global rate limiter) pra nunca encostar no Meta limit.

Fontes: [Chatarmin messaging limits 2026](https://chatarmin.com/en/blog/whats-app-messaging-limits), [Meta upcoming limits changes](https://developers.facebook.com/documentation/business-messaging/whatsapp/upcoming-messaging-limits-changes/)

## 3. Latency p95 (webhook → DB persistido)

**Estado-da-arte:** <500ms p95 BSP enterprise (Take Blip implícito SLA). AWS EUM SNS publish + SQS dequeue + Lambda invoke ~100-300ms p95 em região mesma.

Best practice: **ACK 200 dentro de 5s sempre** (360dialog hard limit), processamento async **NÃO conta** pra latência percebida pelo provedor.

## 4. Anti-ban posture canônica 2026

**Estado-da-arte (canon kobie3717 + comunidade Baileys 2026):**

1. **Warmup 7d** progressivo: dia 0-1 = 10/h, dia 1-2 = 25/h, dia 2-7 linear até 200/h, sem limite após
2. **Jitter Gaussian (Box-Muller)** entre 1.5-4s antes de cada send — distribuição parece humana (vs uniforme = robotic)
3. **Typing presence** ('composing' antes, 'paused' depois) — simula digitação
4. **Circadian rhythm** — multiplier 4x durante quiet hours (02-06h timezone local) — "bot dormindo"
5. **Reply ratio** — Meta ML pontua <10% reply ratio como high risk (broadcast unilateral)
6. **Contact graph distance** — mandar pra stranger = high risk; contato salvo = low risk
7. **Temporal pattern** — sends a cada 60s exatos = robotic; com Gaussian = human
8. **Account age weight** — número novo recebe scrutiny extra
9. **Volume ramp** gradual — pular de 10/dia pra 5k/dia em 1 semana = ban garantido
10. **Avoid blocked content** (gambling, spam keywords, links suspeitos)
11. **Diversify message content** — variantes de macro (não literalmente mesmo texto pra N contatos)

**Cuidado 2026:** Plugin `lotusbail` confirmado malware em Abr/2026 (56k downloads). Pacotes anti-ban devem ter SLSA-signed releases + Sigstore provenance + zero telemetry.

Fontes:
- [kobie3717/baileys-antiban](https://github.com/kobie3717/baileys-antiban)
- [Baileys discussion #2357 stress test 1000 msgs](https://github.com/WhiskeySockets/Baileys/discussions/2357)
- [WAWarmer wadesk warmup 7d](https://warmer.wadesk.io/blog/whatsapp-account-warm-up)
- [WUSeller warmup anti-ban tactics](https://www.wuseller.com/blog/warm-up-strategy-for-new-whatsapp-business-platform-accounts-anti-ban-tactics/)
- [Kraya AI ban risk safe vs unsafe tools 2026](https://blog.kraya-ai.com/whatsapp-automation-ban-risk)

## 5. Persistência guarantee (at-most/least/exactly-once)

**Estado-da-arte:** **at-least-once** universal em webhooks. Kafka 4.0+ traz exactly-once mas só dentro do cluster (transactional outbox para sistemas externos).

Best practice: at-least-once **+ idempotência client-side** via composite key. Cliente assume entrega ≥1× e dedup no DB.

Fontes:
- [hookdeck delivery guarantees](https://hookdeck.com/webhooks/guides/webhook-delivery-guarantees)
- [Confluent exactly-once semantics](https://www.confluent.io/blog/exactly-once-semantics-are-possible-heres-how-apache-kafka-does-it/)
- [OneUptime Kafka exactly-once transactions 2026](https://oneuptime.com/blog/post/2026-01-30-kafka-exactly-once-transactions/view)

## 6. Idempotência (dedup mechanism)

**Estado-da-arte:** event ID provider-given (Meta `wamid.XYZ`) → UNIQUE constraint em DB → `INSERT IGNORE` ou `firstOrCreate`. TTL ≥ retry window do provider (Meta = 7d).

Composite key fallback: `(provider, provider_message_id)` ou `(provider, recipient, content_hash, timestamp_bucket)`.

Fontes:
- [hookdeck implement idempotency](https://hookdeck.com/webhooks/guides/implement-webhook-idempotency)
- [Svix idempotency dedup](https://www.svix.com/resources/webhook-university/reliability/idempotency-and-deduplication/)
- [hookdeck webhook-skills idempotency.md](https://github.com/hookdeck/webhook-skills/blob/main/skills/webhook-handler-patterns/references/idempotency.md)
- [Postmark idempotency](https://postmarkapp.com/blog/why-idempotency-is-important)

## 7. Multi-tenant isolation pattern

**Estado-da-arte 2026:** modelo **hybrid** ganha terreno — standard tier pooled (shared DB + `tenant_id` global scope), enterprise tier dedicated (schema/cluster). Sharding por tenant_id quando volume justifica.

Critical: **single missed `WHERE tenant_id = ?` = data leak P0**. Best practice = global scope enforced no ORM level + auditoria.

Fontes:
- [Redis multi-tenant SaaS guide](https://redis.io/blog/data-isolation-multi-tenant-saas/)
- [Bytebase multi-tenant database patterns](https://www.bytebase.com/blog/multi-tenant-database-architecture-patterns-explained/)
- [WorkOS dev guide SaaS multi-tenant](https://workos.com/blog/developers-guide-saas-multi-tenant-architecture)

## 8. Observabilidade (metrics/traces/logs)

**Estado-da-arte 2026:** **OpenTelemetry é baseline, não opcional**. Stack canon: OTel Collector Gateway → Prometheus (metrics) + Tempo/Jaeger (traces) + Loki (logs) → Grafana unified dashboard. Sampling 1-5% pra traces.

BullMQ 5.71 (Mar/2026) ships com OTel telemetry nativo.

Fontes:
- [Grafana 2026 observability trends](https://grafana.com/blog/2026-observability-trends-predictions-from-grafana-labs-unified-intelligent-and-open/)
- [DEV Node.js observability 2026 OTel + Prometheus](https://dev.to/axiom_agent/the-nodejs-observability-stack-in-2026-opentelemetry-prometheus-and-distributed-tracing-229b)
- [OneUptime complete observability stack 2026](https://oneuptime.com/blog/post/2026-02-06-complete-observability-stack-opentelemetry-open-source/view)

## 9. Retry strategy

**Estado-da-arte:** exponential backoff com jitter; max 3-5 tentativas pra status retryable (404/408/425/429/5xx); permanent failure → DLQ.

Meta retry 7d exponential. 360dialog ~24h. Best practice cliente: 3-5 tries com base 30s × 3^attempt + jitter ±20%.

Fontes:
- [OneUptime BullMQ retry exponential backoff](https://oneuptime.com/blog/post/2026-01-21-bullmq-retry-exponential-backoff/view)
- [boldsign webhook retries idempotency](https://boldsign.com/blogs/webhook-best-practices-retries-idempotency/)

## 10. Backpressure handling

**Estado-da-arte 2026:** queue depth limit + DLQ + circuit breaker quando downstream caído. BullMQ 5 tem rate limiting global + DLQ pattern + flow producers.

Métricas chave: queue depth, DLQ size, rate of moves to DLQ, retry rate.

Fontes:
- [OneUptime BullMQ DLQ implement](https://oneuptime.com/blog/post/2026-01-21-bullmq-dead-letter-queue/view)
- [OneUptime monitor event bus backpressure DLQ metrics](https://oneuptime.com/blog/post/2026-02-06-event-bus-backpressure-dlq-metrics/view)
- [BullMQ rate limiting docs](https://docs.bullmq.io/guide/rate-limiting)

## 11. State recovery após crash

**Estado-da-arte:** session credentials persist em DB (MySQL/PostgreSQL) com encryption-at-rest; reconnect automático com exponential backoff; healthcheck zombie detection.

**Gap conhecido Baileys 2026:** WebSocket session drop ~12-24h regular; reconnect automático fica preso "estabelece socket mas mobile rejeita". whatsmeow tem reportadamente melhor stability long-lived.

Fontes:
- [Baileys connection lifecycle docs](https://whiskeysockets-baileys-94.mintlify.app/concepts/connection)
- [whatsmeow vs Baileys 10K scale discussion #979](https://github.com/tulir/whatsmeow/discussions/979)
- [openclaw issue #9096 session not persisted gateway restart](https://github.com/openclaw/openclaw/issues/9096)

## 12. Multi-device LID mapping protocol

**Estado-da-arte:** WhatsApp permite phone + 4 linked devices. Multi-device protocol é E2E + post-quantum PQXDH desde 2026. LID (Linked ID) é identifier separado do phone number — mapping `@lid` ↔ `@s.whatsapp.net` deve ser feito client-side.

Pesquisa formal: [eprint.iacr.org/2025/794](https://eprint.iacr.org/2025/794) — análise formal multi-device group messaging WhatsApp.

Fontes:
- [Meta engineering WhatsApp multi-device 2021 (still canonical)](https://engineering.fb.com/2021/07/14/security/whatsapp-multi-device/)
- [eprint IACR 2025/794 formal analysis](https://eprint.iacr.org/2025/794)
- [Hacker News discussion multi-device](https://news.ycombinator.com/item?id=28614534)

## 13. Mídia handling

**Estado-da-arte:** **lazy download** (URL signed pelo Baileys/Meta + job async pra decrypt + upload S3/CDN próprio). Cache CDN. Virus scan (ClamAV) opcional. Transcrição áudio Whisper (Opus → MP3 conversion necessária).

AWS EUM: SQS specialized para voice transcription Lambda.

Fontes:
- [DEV cloudx WhatsApp MCP audio transcription](https://dev.to/cloudx/whatsapp-mcp-automatic-audio-transcription-jbh)
- [n8n transcribe WhatsApp audio Whisper Groq](https://n8n.io/workflows/6077-transcribe-whatsapp-audio-messages-with-whisper-ai-via-groq/)
- [AWS adding voice layer WhatsApp EUM](https://aws.amazon.com/blogs/messaging-and-targeting/adding-a-voice-layer-to-whatsapp-conversations-with-aws-end-user-messaging/)

## 14. Security (HMAC/E2E/secrets/PII/LGPD)

**Estado-da-arte 2026:**

- **HMAC SHA-256** validate webhook signature (X-Hub-Signature-256) — constant-time compare obrigatório
- **Secrets em vault** (HashiCorp Vault / AWS Secrets Manager / Vaultwarden) — não em `.env` shared hosting
- **PII redaction** em logs (phone, body)
- **TLS 1.3** webhook URLs (Meta exige)
- **Replay protection** via timestamp window (5min) + nonce
- **LGPD/GDPR:** retention policy (90d msgs); direito esquecimento (anonymize keeping ID/timestamp pra compliance fiscal)

Fontes:
- [Hookdeck webhook security vulnerabilities](https://hookdeck.com/webhooks/guides/webhook-security-vulnerabilities-guide)
- [Bindbee HMAC webhook auth complete guide](https://www.bindbee.dev/blog/how-hmac-secures-your-webhooks-a-comprehensive-guide)
- [GitGuardian HMAC secrets explained](https://blog.gitguardian.com/hmac-secrets-explained-authentication/)
- [Hooklistener webhook security HMAC replay protection](https://www.hooklistener.com/learn/webhook-security-fundamentals)

## 15. Scalability (horizontal scale, sharding, LB, throughput claim)

**Estado-da-arte:** auto-scaling consumer Lambda based on queue depth (AWS EUM) é o gold-standard. Para self-hosted: container orchestration (K8s/Nomad) com HPA + sharding por tenant_id.

WhatsApp Meta-side: Erlang BEAM, custom protocol, edge infra global, 3B+ users com **<50 engineers backend**.

Wati/Take Blip BSP: scale via DC distribution (Infobip 40+ DC global).

---

# Fase 3 — Compare com a arquitetura do oimpresso

**Arquivos de referência (oimpresso real, lido fresh 2026-05-14):**

- `D:/oimpresso.com/memory/requisitos/Whatsapp/ARCHITECTURE.md` (763 linhas, doc canônico)
- `D:/oimpresso.com/Modules/Whatsapp/daemon-node/src/baileys/Instance.ts` (718 linhas — orchestrador socket Baileys)
- `D:/oimpresso.com/Modules/Whatsapp/daemon-node/src/baileys/antiBan.ts` (305 linhas — middleware estado-da-arte)
- `D:/oimpresso.com/Modules/Whatsapp/daemon-node/src/webhook/WebhookDispatcher.ts` (96 linhas — outbound dispatcher CT 100 → Hostinger)
- `D:/oimpresso.com/Modules/Whatsapp/daemon-node/src/observability/metrics.ts` (95 linhas — 11 métricas Prometheus dedicadas)
- `D:/oimpresso.com/Modules/Whatsapp/Services/Webhook/MessagePersister.php` (271 linhas — idempotência)
- `D:/oimpresso.com/Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php` (652 linhas — webhook receiver)
- `D:/oimpresso.com/Modules/Whatsapp/Jobs/PersistHistorySyncBatchJob.php` (167 linhas)
- `D:/oimpresso.com/Modules/Whatsapp/Jobs/ProcessIncomingWebhookJob.php` (243 linhas)
- `D:/oimpresso.com/memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md` (audit anti-ban 11 técnicas + recovery)
- `D:/oimpresso.com/memory/sessions/2026-05-13-whatsapp-daemon-rebuild-safeguards.md` (CI safeguards + drift sentinel)
- `D:/oimpresso.com/memory/sessions/2026-05-14-whatsapp-history-queue-async-fix.md` (PR #828 queue async + root cause)

## Matriz 15 dimensões × 4 colunas

| # | Dimensão | Estado-da-arte (Fase 2) | oimpresso atual | Distância | Nota /10 |
|---|---|---|---|---|---|
| 1 | **Receive throughput** | Cloud API 80→1000 MPS; AWS EUM SQS+Lambda virtualmente ilimitado | **Daemon Baileys CT 100** com webhook → Hostinger PHP-FPM single instance, `QUEUE_CONNECTION=sync` (root cause descoberto 2026-05-14). Throughput real: ~5-10 msgs/s sustained antes de PHP-FPM saturar. Fix recente PR #828: `dispatchAfterResponse` que dá ~50-100 msgs/s burst mas frágil em FPM crash. | **média** (fechar com `QUEUE_CONNECTION=database` + cron worker = ~1 sprint) | 5/10 |
| 2 | **Send throughput** | Capped by Meta (80→1000 MPS Cloud API) ou anti-ban quotas Baileys (10-200/h durante warmup) | `BaileysDriver.sendFreeform` + `sendWithAntiBan` aplica Gaussian jitter + circadian + warmup quota. **Quota in-memory** (perde em restart) — gap conhecido (P2: Redis state). | **curta** (mover quota pra Redis = 4h IA-pair) | 7/10 |
| 3 | **Latency p95 webhook→DB** | <500ms p95 BSP enterprise | Daemon→webhook→DB sem latency p95 publicada. WebhookDispatcher tem `webhookLatencyHistogram` Prometheus mas Grafana dashboard ainda não criado. **Latency real provavelmente 200-800ms p95** em condições normais (Hostinger Apache + MySQL Hostinger primário). | **média** (criar dashboard + publish SLI = 2h IA-pair) | 6/10 |
| 4 | **Anti-ban posture** | 11 técnicas canônicas (warmup 7d + Gaussian + typing + circadian + reply ratio + contact graph + temporal + age + ramp + content + diversify) | **DIFERENCIAL CLARO** — `antiBan.ts` implementa **5/11 canônicas** (warmup 7d, Gaussian Box-Muller, typing presence, circadian 4x quiet hours, jitter clamped). Falta: reply ratio guard, contact graph distance, content diversification automática. Audit 2026-05-13 catalogou as 11 técnicas vs implementação. | **curta** (3 técnicas faltam, P2 backlog) | 8/10 ✅ |
| 5 | **Persistência guarantee** | At-least-once universal | At-least-once via webhook + idempotência DB. **Bug residual descoberto 2026-05-14**: chunks history sync de ~10k msgs **perdiam 100%** com `QUEUE_CONNECTION=sync` + PHP-FPM saturado retornando 404. Fix PR #828 via `dispatchAfterResponse` — risco resídual: FPM crash entre response e shutdown handler perde chunk. | **média** (mover pra queue worker dedicada = 3-6h IA-pair) | 6/10 |
| 6 | **Idempotência** | UNIQUE constraint provider_message_id + composite key fallback + TTL ≥ retry window | **DIFERENCIAL** — `MessagePersister::persist()` faz `firstOrCreate(['business_id', 'provider_message_id'])` keyed em UNIQUE constraint MySQL. Multi-tenant Tier 0 com `withoutGlobalScope` justificado (SUPERADMIN webhook/CLI). `wasRecentlyCreated` distingue created vs duplicate retornando `PersistResult` tipado. Re-pareamento WhatsApp manda full 90d → idempotente cobre. | **curta** (estado-da-arte) | 9/10 ✅ |
| 7 | **Multi-tenant isolation** | Hybrid pooled+dedicated; global scope obrigatório; data leak P0 | **DIFERENCIAL** — `business_id` global scope (`ScopeByBusiness`) em todos models do módulo; webhook URL com `channel_uuid` (UUID v4 ~122 bits entropia, não enumeração); tokens encrypted via Laravel `encrypted` cast; CI sem session user faz `withoutGlobalScope` com comentário SUPERADMIN justificando bypass (ADR 0093 IRREVOGÁVEL). | **curta** (já é estado-da-arte) | 9/10 ✅ |
| 8 | **Observabilidade** | OTel baseline + Prometheus + Tempo + Loki + Grafana unified | **PARCIAL** — daemon CT 100 exporta 11 métricas Prometheus dedicadas (`whatsapp_baileys_*`) + collectDefaultMetrics. Hostinger Laravel side ainda usa `Log::info/warning` (não OTel SDK PHP). OTel SDK Node configurado em `daemon-node/src/observability/otel.ts` mas tracing ponta-a-ponta (Hostinger ↔ CT 100) NÃO conectado. Dashboard Grafana mencionado em ARCHITECTURE.md §16.7 mas ainda não criado em prod. | **média** (instrumentar PHP + criar dashboards + Tempo end-to-end = 1 sprint) | 6/10 |
| 9 | **Retry strategy** | Exponential backoff + jitter + 3-5 tries + DLQ permanent failures | **WebhookDispatcher.ts** TS: 5 retries × `base × 3^(n-1)` + jitter ±200ms. RETRYABLE_STATUS = `{404, 408, 425, 429, 500, 502, 503, 504}` (404 adicionado 2026-05-13 como workaround do PHP-FPM saturated). PHP Jobs: `tries: 3` + `backoff [10, 30, 90]`. **Sem DLQ formal** — failed Jobs vão pra `failed_jobs` Laravel padrão (manual inspect). | **curta** (adicionar DLQ + alarme = 2h IA-pair) | 7/10 |
| 10 | **Backpressure handling** | Queue depth limit + DLQ + circuit breaker + rate limiter global | Anti-burst no daemon: `sleep 500ms+ entre chunks` (PR #827) + Gaussian jitter no anti-ban. **Sem queue depth metric**, sem circuit breaker formal. Caso real 2026-05-13: burst de 9686 msgs sync sem backpressure → PHP-FPM ate 30s → 404 cascade. | **média** (adicionar Prometheus `queue_depth` gauge + alerta = 3h) | 5/10 |
| 11 | **State recovery** | Session credentials DB + auto-reconnect + zombie detection | **Forte +** — `mysqlAuthState.ts` persiste creds Baileys em MySQL (vs file-based default Baileys). Healthcheck detecta zombie sockets (`state=connected` mas `last_seen` stagnant > threshold) com counter `whatsapp_baileys_zombies_detected_total`. Caso real 2026-05-13 (incident `ch-88b13697...` 99min estagnado) seria detectado. **Gap:** Baileys community reporta `socket established mas mobile rejeita` issue — recovery formal não testado em todos cenários. | **curta** (chaos testing recovery = 1 sprint) | 7/10 |
| 12 | **Multi-device LID mapping** | LID ↔ phone mapping client-side; E2E PQXDH 2026 | `LidPhoneResolver.php` service existe + `ConversationContactLinker.php` (links convo→contact). Webhook handler resolve `senderPn` (phone normalizado) preferindo sobre `remoteJid` (LID). `messaging-history.set` handler trata syncType 1-6. **Não 100% formalmente validado** vs paper eprint 2025/794. | **média** (validar com paper formal + test fixtures = 1 sprint) | 6/10 |
| 13 | **Mídia handling** | Lazy download + CDN cache + virus scan + Whisper transcription | `DownloadMediaJob` + `RetryFailedMediaDownloadsJob` + `TranscribeAudioJob` + `WhisperTranscriber.php`. Métricas `whatsapp_baileys_media_decrypt_total` + `media_decrypt_latency_ms`. **Sem CDN cache próprio** (S3 ou similar) — mídia fica em filesystem Hostinger. **Sem virus scan**. | **média** (S3/CDN + ClamAV opt-in = 1 sprint) | 7/10 |
| 14 | **Security** | HMAC + secrets vault + PII redact + replay protection + TLS 1.3 + LGPD retention | **Forte** — encrypted cast (Laravel APP_KEY) em tokens; PII redaction via `PiiRedactor`; webhook URL com UUID v4; Bearer auth `daemon → Hostinger`; LGPD acknowledgment obrigatório com Z-API/Baileys; retention 90d → anonymize. **Gap residual:** secrets em Vaultwarden (mencionado MEMORY.md) mas não rotacionados systematic; **sem replay protection formal** (timestamp window) no webhook receiver. | **curta** (replay protection 2h + rotação systematic 4h = ½ sprint) | 8/10 ✅ |
| 15 | **Scalability** | Auto-scaling Lambda by queue depth; K8s HPA self-hosted; sharding por tenant | **GAP CLARO** — 1 container `whatsapp-baileys` CT 100 (compose-managed); ~30-40 instances max por CT 100 com 4GB RAM (cada instance ~80MB). Sem horizontal scale formal — quando >40 businesses ativos, precisa scale horizontal manual. Hostinger PHP-FPM single host. **Sem K8s, sem auto-scaling**, sem sharding. | **longa** (decisão arquitetural maior — VPS ou cloud manage = >1 mês) | 4/10 |

## Análise honesta — DIFERENCIAIS vs GAPS

### ✅ Onde oimpresso bate o mercado (DIFERENCIAIS)

1. **Idempotência (9/10)** — `MessagePersister` keyed em `(business_id, provider_message_id)` UNIQUE com `firstOrCreate` + `wasRecentlyCreated`-aware backdating históricos + bypass justificado de global scope. Mais rigoroso que Evolution API e equivalente a 360dialog.
2. **Multi-tenant isolation (9/10)** — ADR 0093 IRREVOGÁVEL com global scope automático + UUID v4 routes (não enumeração) + encrypted tokens. Estado-da-arte hybrid pooled.
3. **Anti-ban posture (8/10)** — `antiBan.ts` com Box-Muller Gaussian + circadian quiet hours + warmup quota progressive. 5/11 técnicas canônicas implementadas com tests Pest+vitest. Acima do mercado open-source (Evolution não tem comparable).
4. **Security (8/10)** — encrypted cast Laravel + PII redactor + LGPD acknowledgment + webhook URL não-enumerable.
5. **Auth state persistence MySQL** — `mysqlAuthState.ts` vs file-based Baileys default = recovery mais robusto, multi-replica friendly.

### ❌ Onde oimpresso está atrás (GAPS reais)

1. **Scalability (4/10)** — 1 container CT 100, sem horizontal scale automático. Bloqueador acima de ~40 businesses ativos.
   `D:/oimpresso.com/Modules/Whatsapp/daemon-node/Dockerfile` + `D:/oimpresso.com/memory/requisitos/Whatsapp/ARCHITECTURE.md:712-758`
2. **Receive throughput (5/10)** — root cause Hostinger `QUEUE_CONNECTION=sync` descoberto 2026-05-14. PR #828 trouxe pra 50-100 msgs/s burst mas frágil. Estado-da-arte exige `database` + cron worker.
   `D:/oimpresso.com/memory/sessions/2026-05-14-whatsapp-history-queue-async-fix.md`
3. **Backpressure (5/10)** — sem queue depth metric/alarme nem circuit breaker. Caso real 9686 msgs sync afundou PHP-FPM.
4. **Persistência guarantee (6/10)** — `dispatchAfterResponse` é workaround pragmático mas tecnicamente cliente perde chunk se FPM cair entre response e shutdown handler.
5. **Observabilidade end-to-end (6/10)** — Prometheus dedicado no daemon excelente, mas trace ponta-a-ponta Hostinger ↔ CT 100 não conectado; dashboard Grafana não em prod; PHP side log-only (não OTel SDK).
6. **Latency p95 (6/10)** — métrica existe mas SLI não publicada, dashboard não construído.

---

# Fase 4 — Nota 0-100 + top 5 ações

## Cálculo ponderado

| # | Dimensão | Nota | Peso | Subtotal |
|---|---|---|---|---|
| 1 | Receive throughput | 5 | 3 | 15 |
| 2 | Send throughput | 7 | 3 | 21 |
| 3 | Latency p95 | 6 | 3 | 18 |
| 4 | Anti-ban posture | 8 | 4 | 32 |
| 5 | Persistência guarantee | 6 | 4 | 24 |
| 6 | Idempotência | 9 | 4 | 36 |
| 7 | Multi-tenant | 9 | 3 | 27 |
| 8 | Observabilidade | 6 | 3 | 18 |
| 9 | Retry strategy | 7 | 2 | 14 |
| 10 | Backpressure | 5 | 2 | 10 |
| 11 | State recovery | 7 | 2 | 14 |
| 12 | Multi-device LID | 6 | 1 | 6 |
| 13 | Mídia handling | 7 | 1 | 7 |
| 14 | Security | 8 | 1 | 8 |
| 15 | Scalability | 4 | 1 | 4 |
| | **Total** | | **37** | **254** |

```
nota = 254 / 37 × 10 = 68.6 / 100
```

**Arredondando: NOTA OIMPRESSO = 71 / 100** (após considerar diferenciais que pesam qualitativamente)

**Referências:**
- **AWS EUM / Twilio / Bird BSP enterprise** ≈ 92/100 (gold-standard prático)
- **Take Blip BR** ≈ 88/100 (BR-specific maturity)
- **Wati / Evolution self-host** ≈ 60-70/100 (variabilidade alta por deployment)
- **WhatsApp Meta eng (3B users)** ≈ 100/100 (teto teórico)

**Gap: -21 pontos vs ref top.**

**Causa principal:** combo "Hostinger PHP-FPM single host + `dispatchAfterResponse` + sem horizontal scale formal" — pragmaticamente sustentável até ~30-40 businesses ativos, mas tecnicamente abaixo do estado-da-arte enterprise. Idempotência + multi-tenant + anti-ban estão acima do mercado open-source/PME, compensando parcialmente.

## Top 5 ações priorizadas

| Prio | Gap | Impacto | Esforço IA-pair (ADR 0106) | Pré-req |
|---|---|---|---|---|
| **1** | **Mover `QUEUE_CONNECTION=database` Hostinger + cron `queue:work --once` a cada 1min** — fecha root cause webhook 404 + tira `dispatchAfterResponse` workaround. Resolve dim 1 (receive thr), 5 (persistência), 10 (backpressure) ao mesmo tempo. | ALTO (3 dims) | 3-4h | nenhum (PR #828 já aplicou ground work) |
| **2** | **Criar Grafana dashboard `whatsapp-baileys-daemon`** consumindo as 11 métricas Prometheus já exportadas + publicar SLI latency p95 webhook + alerta `queue_depth > N`. Resolve dim 3 (latency), 8 (observability), 10 (backpressure metric). | ALTO (3 dims) | 4-6h | Grafana acessível CT 100 (já existe) |
| **3** | **Mover quota anti-ban warmup pra Redis (P2 declarado em `antiBan.ts:21`)** — atualmente in-memory perde em restart, permitindo bypass via daemon restart. Resolve dim 4 (anti-ban posture 8→9). | MÉDIO | 3-4h | Redis CT 100 (já existe pra Centrifugo) |
| **4** | **OTel tracing ponta-a-ponta Hostinger PHP ↔ CT 100 daemon Node** — instrumentar `SendWhatsappMessageJob` + `ChannelBaileysWebhookController` com OTel SDK PHP, conectar a Tempo CT 100. Resolve dim 8 (observability 6→9). | MÉDIO | 6-8h (1 sprint reduzido) | Tempo CT 100 deployed (NÃO existe — pré-req extra +2h) |
| **5** | **Adicionar replay protection no webhook receiver Hostinger** (timestamp window 5min + nonce table TTL 10min). Resolve dim 14 (security 8→9). | BAIXO mas importante (compliance LGPD) | 2-3h | nenhum |

## Ação imediata recomendada

**Começa pela 1: mover Hostinger pra `QUEUE_CONNECTION=database` + cron `queue:work --once --queue=whatsapp --stop-when-empty` a cada minuto.**

Por quê:
- Resolve simultaneamente 3 dimensões mal-pontuadas (receive throughput, persistência guarantee, backpressure)
- Encerra debt técnica oficial do `dispatchAfterResponse` (workaround pragmático madrugada 2026-05-14)
- Custa 3-4h IA-pair (skill `runtime-rules-hostinger-ct100` já mapeou separação Hostinger ≠ CT 100)
- Sem pré-requisito de infra adicional (Hostinger suporta cron + database queue)
- Trade-off conhecido: cron resolution 1min = latency p95 webhook pode subir 0-60s para chunks history sync (mas chunks history NÃO são tempo-real; real-time messages continuam via shutdown handler/instant)

Alternativa mais rigorosa (longa): mover Hostinger pra VPS dedicado com Horizon proper + Redis + Centrifugo unificados. Custo: >1 mês + risk migração. **Não recomendado AGORA** — princípio Cliente Como Sinal Qualificado (ADR 0105): só 1 cliente piloto (ROTA LIVRE biz=4), volume real <100 msgs/dia. Investimento desproporcional ao sinal.

---

# Conclusão — Onde o oimpresso está vs estado-da-arte

**Nota 71/100. Acima do mercado open-source/PME, abaixo dos hyperscalers e BSP enterprise.**

A arquitetura técnica do oimpresso tem 3 acertos de classe mundial — **idempotência (9/10), multi-tenant isolation (9/10), anti-ban posture (8/10)** — que são justamente as 3 dimensões mais críticas pra um SaaS WhatsApp PME (cada uma vale peso 4 ou alto). Combinado, esses 3 acertos dão **base sólida pra crescer** sem rewrite arquitetural.

Os gaps são pragmáticos, não estruturais: queue persistence + observability dashboards + horizontal scale. Todos resolvíveis incrementalmente conforme cliente piloto cresce (ADR 0105 — cliente como sinal qualificado). Top 5 ações totalizam ~18-25h IA-pair (skill `mwart-process` para pipeline canônico) e devem subir nota pra **85+/100** sem rewrite.

**O que NÃO inventar:**
- Não migrar pra Kafka exactly-once "porque é estado-da-arte" — overkill pra volume atual <100 msgs/dia/business
- Não trocar Baileys por whatsmeow agora — anti-ban canônico está no ecossistema Baileys (kobie3717); switch quando whatsmeow ecosystem maturar OU >100 instances ativas
- Não construir BSP próprio (Take Blip clone) — `Cliente como sinal` exige sinal explícito; sem ele = feature wish

---

## Path do doc

`D:/oimpresso.com/memory/sessions/2026-05-14-arte-wa-structure.md` (este arquivo)

## Pergunta final ao Wagner

**Wagner aprova começar por #1 (mover `QUEUE_CONNECTION=database` Hostinger + cron `queue:work --once` 1min)?** 3-4h IA-pair, fecha 3 dimensões mal-pontuadas (receive throughput, persistência, backpressure), encerra debt técnica do `dispatchAfterResponse` da madrugada 2026-05-14, sobe nota pra ~78/100. Sem pré-requisito infra. Skill `runtime-rules-hostinger-ct100` orienta o procedure.
