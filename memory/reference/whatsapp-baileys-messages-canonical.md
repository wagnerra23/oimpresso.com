---
name: WhatsApp Baileys mensagens — protocolo, history sync, inbox queue, idempotência, anti-ban, webhook security, observability, gotchas
description: Conhecimento canônico operacional sobre mensagens Baileys WhatsApp no oimpresso — POR QUE cada decisão foi tomada (incidentes catalogados), as pegadinhas que custaram cara, e o caminho certo pra evoluir. Complementa whatsapp-daemon-ct100.md (foco daemon/deploy) e meta-whatsapp-tech-provider.md (foco Meta Cloud).
type: reference
---
# WhatsApp Baileys — mensagens, history, inbox, anti-ban, segurança

> Doc canônico operacional. Complementa:
> - `memory/reference/whatsapp-daemon-ct100.md` — daemon + deploy + endpoints
> - `memory/reference/meta-whatsapp-tech-provider.md` — Meta Cloud (fallback)
>
> **Decisão arquitetural mãe:** [ADR 0096](../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) (drivers gating) + emendas 4 (Baileys autorizado Sprint 3) e 5 (bypass cirúrgico).

---

## 1. POR QUE Baileys (e não Z-API/Evolution/whatsapp-web.js)

| Driver | Status | Por quê |
|---|---|---|
| **`zapi`** | Default Sprint 1 | API gerenciada, antibanned ok, custo ~R$ [redacted Tier 0]/mês/número |
| **`meta_cloud`** | Fallback obrigatório | Oficial Meta, anti-ban garantido, gating duro FormRequest |
| **`baileys`** | Autorizado Sprint 3 (ADR 0096 emenda 4) | Daemon Node próprio CT 100. Resolve as 3 dores do Evolution: estrutura customizada de atendimento, esquema oimpresso, observabilidade |
| **`evolution`** | PROIBIDO permanente | Bans em prod Wagner + schema não atende + falta observability |
| **`whatsapp_web_js`** | PROIBIDO | Sobreposição funcional com BaileysDriver |
| **`null`** | Dev/test | No-op |

**Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)):**
- `business_id` global scope em todo Eloquent
- `instance_id` prefix `biz{business_id}-` ou `ch-{channel_uuid_sem_hifens}` (canônico desde ADR 0135)
- API_KEY do daemon é **GLOBAL** (não per-tenant). Mora em Docker secret `/run/secrets/whatsapp_baileys_api_key` no CT 100. Tenant isolation é via `business_uuid` no path do webhook + `instance_id` no body

---

## 2. Protocolo WhatsApp — "é retardado, app-side dedup mandatory"

**Fato cru (pesquisa Baileys especialista 2026-05-14):** WhatsApp protocol **NÃO tem cursor de history**. Sempre manda full 90 dias.

**Implicações operacionais:**
1. **Idempotência = obrigação app-side.** UNIQUE constraint `(business_id, provider_message_id)` na tabela `messages` é a única defesa contra duplicação em reconnect/history sync.
2. **Não tente "incrementally sync"** — não existe API. Se você desconecta 30s, no reconnect vem tudo de novo. O dedup absorve.
3. **`syncFullHistory: true`** + `Browsers.appropriate('Desktop')` é o setup canônico. `'Mobile'` ou `'Chrome'` aumenta risco de ban por padrão suspeito.
4. **`shouldSyncHistoryMessage` callback** deve retornar `true` pra todas mensagens da janela 90d. Filtrar aqui = perder mensagens silenciosamente.

**Eventos messaging-history.set com `syncType`:**
- `1` INITIAL_BOOTSTRAP (primeira conexão)
- `2` FULL (sync completa pós-disconnect longo)
- `3` RECENT (últimas semanas)
- `4` PUSH_NAME
- `5` NON_BLOCKING_DATA
- `6` ON_DEMAND (fetchMessageHistory manual)

Daemon emite **todas** pro webhook `history.sync` event — Hostinger filtra/dedupa.

---

## 3. Inbox queue pattern (Wagner request 2026-05-14 02h)

> "Recebe tudo de maneira rapida no redis ou onde, depois sincroniza com o banco, mais sempre guarda para não perder."

**Arquitetura final (PR #831):**

```
[Baileys daemon CT 100]
    │
    │  history.sync webhook (90d batch)
    ▼
[Hostinger /api/atendimento/channels/baileys/{uuid}]
    │
    │  PersistHistorySyncBatchJob::dispatch()
    │  → onConnection('database')
    │  → onQueue('whatsapp-history')
    ▼
[jobs table — SQLite-like fila persistente]
    │
    │  cron `* * * * * queue:work database --queue=whatsapp-history --max-time=55 --stop-when-empty --tries=3`
    ▼
[Worker process job → insertOrIgnore em messages]
```

**POR QUÊ database queue (não Redis):**
- Hostinger shared hosting **não tem Redis** disponível
- `QUEUE_CONNECTION=sync` saturava PHP-FPM em burst de webhooks (incidente 2026-05-13 → 404/429 cascata)
- `dispatchAfterResponse()` ajudava mas ainda no mesmo worker PHP — não escala
- Database queue persiste em `jobs` table → cron worker isolado processa

**POR QUÊ cron everyMinute (não supervisor):**
- Hostinger compartilhado **sem supervisor/systemd**
- Cron + `--max-time=55 --stop-when-empty` é workaround padrão Laravel
- Pior caso: 1min latência pra primeira msg histórica aparecer no Inbox após pareamento (aceitável)

**Tunables:**
- `WHATSAPP_QUEUE=whatsapp` (queue default outbound; history usa `whatsapp-history` hardcoded)
- Backpressure US-WA-084: `WHATSAPP_QUEUE_MAX_DEPTH=2000`, retorna 429 se exceder

**Gotcha:** `optimize:clear` é obrigatório após mexer em rotas, middlewares ou config queue. Cache de route não pega mudanças.

---

## 4. Webhook security stack (US-WA-082 + US-WA-084)

**Ordem dos middlewares em `/api/atendimento/channels/baileys/{channel_uuid}`:**

```
1. whatsapp.otel.propagate     — US-WA-083, lightweight bridge traceparent
2. whatsapp.baileys.hmac       — US-WA-082, replay protection
3. whatsapp.baileys.backpressure — US-WA-084, queue depth gate
```

### 4.1 HMAC + nonce (US-WA-082)

**Headers daemon → Hostinger:**
- `x-baileys-signature` — HMAC-SHA256(`API_KEY`, `${ts}.${nonce}.${body}`), hex digest
- `x-baileys-nonce` — UUID v4 random (idempotente em retries — **MESMO** nonce do 1º attempt)
- `x-baileys-ts` — Unix epoch seconds
- `traceparent` — W3C Trace Context (US-WA-083)

**Validações:**
1. **Replay window 5min** (`abs(time() - ts) > 300` → 401 `replay_window_expired`)
2. **HMAC constant-time** via `hash_equals()` (não `===` — timing attack)
3. **Nonce não-visto** via `INSERT IGNORE` atômico em `webhook_nonces` (→ 401 `nonce_replayed`)

**Backward compat:** daemon antigo sem headers → middleware passa direto (rollout gradual). Quando todos daemons atualizados, remover compat.

**Defensive:** `API_KEY` vazio no `.env` → middleware é no-op (não derruba prod).

**Cleanup cron hourly:** `whatsapp:cleanup-webhook-nonces` purga rows >24h (replay window real é 5min, margem 24h pra audit).

### 4.2 Backpressure (US-WA-084)

**Quando dispara:** `SELECT COUNT(*) FROM jobs WHERE queue='whatsapp-history' >= 2000`

**Resposta:** `429 + Retry-After: 30` + body JSON `{ok:false, error:queue_backpressure, queue_depth, retry_after_seconds}`

**Daemon respeita:** `429 ∈ RETRYABLE_STATUS` → exponential backoff + jitter. Mensagem **NÃO se perde** — fica viva em SQLite local até receiver normalizar.

**Cache 10s** em `Cache::remember()` evita martelar `SELECT COUNT` em burst de N webhooks/segundo.

**Fail-open:** se SELECT falhar (DB lento), passa. Backpressure não pode virar SPOF.

**Cleanup stale hourly:** `whatsapp:jobs-cleanup-stale --max-age=6` purga reserved-presos + órfãos.

---

## 5. Anti-ban patterns (lições caras 2026-05-12 a 2026-05-13)

**Banimentos catalogados ([memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md](../sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md)):**
- 3 instances banned em 4h (24 e 25 jan 2026) — padrão `device_removed` (HTTP 401 conflict)
- Causa raiz: mass re-handshake em loop (zombie socket auto-restart por Docker policy)

**Patterns canônicos PR #699 + pesquisa especialista:**

1. **Typing delay + jitter Gaussian 1.5–4s** antes de `sendMessage` (`baileys-antiban` package)
2. **7d warmup** chip novo: limitar a 50 msgs/dia → 200/dia → ilimitado
3. **`Browsers.appropriate('Desktop')`** — `'Mobile'`/`'Chrome'` = bandeira vermelha Meta
4. **Rate limit 3 connect/business/dia** (`WHATSAPP_BAILEYS_CONNECT_RATE_LIMIT=3`) — anti-abuse pra evitar mass re-handshake
5. **Cross-tenant alarm:** 3 bans em 24h congela envios programados Wagner-wide (`cross_tenant_ban_alarm_threshold=3` em config)
6. **NUNCA reusar session pareada em 2 IPs simultâneos** — Meta detecta e bana
7. **Zombie socket protection:** healthcheck detecta `state=connected` + `last_seen` estagnado >threshold → alerta `WhatsappZombiesDetected` ANTES de Docker restart silencioso
8. **`useMultiFileAuthState` em prod = warning Baileys upstream** ("Don't ever use in production"). Mitigação: PR #701 deployado, evoluir pra DB-backed `useMySQLAuthState` ~6h

**Mensagens outbound de teste em prod:** USAR seu próprio número, NUNCA cliente real. Testes em massa de envio dispara ban heuristics.

---

## 6. Observability stack (Onda 1 + Onda 2 — 2026-05-14)

### 6.1 Daemon Prometheus metrics (11)

`Modules/Whatsapp/daemon-node/src/observability/metrics.ts`:
- `whatsapp_baileys_session_state{instance_id,business_id}` — 1=connected, 0.5=qr, 0=disconnected
- `whatsapp_baileys_session_age_seconds`
- `whatsapp_baileys_message_lag_ms` (histogram, daemon→WA Web→ack)
- `whatsapp_baileys_send_total{status,kind}`
- `whatsapp_baileys_recv_total`
- `whatsapp_baileys_ban_detected_total` — cross-tenant alarm
- `whatsapp_baileys_zombies_detected_total` — pre-restart signal
- `whatsapp_baileys_webhook_dispatch_total{event,outcome}` — ok | retried | failed_permanent
- `whatsapp_baileys_webhook_latency_ms`
- `whatsapp_baileys_media_decrypt_total{status,type}`
- `whatsapp_baileys_media_decrypt_latency_ms`

### 6.2 Grafana dashboard (US-WA-081)

`infra/grafana/dashboards/whatsapp-baileys.json` — 8 paineis. UID `whatsapp-baileys-ct100`. Refresh 30s.

### 6.3 Alert rules Prometheus (US-WA-085)

`infra/prometheus/alerts/whatsapp.yml` — 10 alertas:
- **Tier 0 critical:** ZombiesDetected, BansCrossTenant, DaemonDown
- **Tier 1 warning:** SessionDisconnected, QrStuckPending
- **Tier 2 latency:** MessageLagHigh (p95>5s), WebhookHighFailureRate (>5%), WebhookLatencyHigh (p95>10s)
- **Tier 3 backpressure:** QueueDepthHigh (>1000)
- **Drift:** WebhookDropDetected (recv > dispatched 15min — loss silenciosa)

**Inhibit rules:** DaemonDown silencia derivados; Ban silencia QrPending mesma instance.

### 6.4 OTel tracing distribuído (US-WA-083 — fechado e2e 2026-05-14)

**Daemon side (`WebhookDispatcher.ts`):**
- Span `webhook.dispatch` cobre dispatch + todos retries
- Atributos: `whatsapp.event`, `whatsapp.instance_id`, `whatsapp.business_uuid`, `http.status_code`, `http.attempt`
- `propagation.inject(context.active(), traceHeaders)` injeta W3C `traceparent` (+`tracestate`)
- **SDK init** depende de `OTEL_ENABLED=true` + `OTEL_EXPORTER_OTLP_ENDPOINT=http://jaeger:4318` no compose. Sem ambos, SDK retorna early → spans NoOp.

**Jaeger CT 100 (backend):**
- Single-container `jaegertracing/all-in-one:1.60` em `/opt/observability/jaeger/`
- Storage in-memory 50k traces (volátil — restart perde)
- Daemon Baileys precisa estar na network `observability` pra alcançar `jaeger:4318`
- UI: Traefik → `jaeger.oimpresso.com` (DNS pendente) OU `tailscale ssh -L 16686:127.0.0.1:16686`
- Doc dedicado: [observability-jaeger-ct100.md](observability-jaeger-ct100.md)

**Hostinger side (`PropagateTraceparent` middleware):**
- Extrai `traceparent` regex W3C (`00-{trace_id 32hex}-{parent_id 16hex}-{flags 2hex}`)
- `Log::withContext(['trace_id', 'parent_span_id', 'sampled'])` — todos logs subsequentes carregam trace_id
- **Lightweight bridge** — sem SDK PECL no Hostinger. Correlação via log estruturado cross-system. Evolução futura: container CT 101 com `ext-opentelemetry` → SDK full Laravel.

**Validação ponta-a-ponta:**
```bash
tailscale ssh root@ct100-mcp 'curl -s http://127.0.0.1:16686/api/services'
# Deve retornar: {"data":["jaeger-all-in-one","whatsapp-baileys-daemon"],...}
```

---

## 7. Channels / Phone / Instance model (ADR 0117 + ADR 0135)

**Hierarquia:**
- `Business` (tenant) → `Channel` (canal omnichannel — Baileys/Z-API/Meta) → `Phone` (multi-números por business)
- `Channel.uuid` é a identidade canônica em webhooks (NÃO mais `business_uuid` legacy, ADR 0135)
- `instance_id` = `'ch-' + str_replace('-', '', channel_uuid)` — sem hifens

**Sessions FS bind mount:** `/srv/docker/whatsapp-baileys/sessions/ch-{uuid_sem_hifens}/` no host CT 100 (NÃO docker volume — cuidado).

**Auto-link Contact CRM (US-WA-078):**
- Conversation tenta resolver `contact_id` via phone normalization E.164
- Backfill command `whatsapp:auto-link-conversation-contacts`

**Channels reconciler (cron 5min):** auto-fix drift `channels` DB ↔ daemon `/health` instances. Comando `whatsapp:channel-reset {id}` faz purge + reset DB em 1 step.

---

## 8. CT 100 deploy gotchas (catalogadas a sangue)

### 8.1 `/srv/build/whatsapp-baileys-daemon/` NÃO é git repo
- Source canônico vive em `/opt/whatsapp-baileys/source/` (esse SIM é git)
- Build dir `/opt/whatsapp-baileys/build/` é cópia separada — **deploy = `cp -r source/Modules/Whatsapp/daemon-node/src/* build/src/` ANTES de `docker compose build`**
- Esqueceu de copiar? Bug silencioso — código antigo continua rodando

### 8.2 Docker rm + run PERDE Traefik labels
- Comando `docker rm whatsapp-baileys && docker run …` → container sem labels → Traefik não roteia → webhook 502
- **Sempre `docker compose up -d`** (preserva labels do compose YAML)

### 8.3 Dockerfile groupadd 'daemon' clash
- Imagem base já tem grupo `daemon` → `groupadd daemon` falha
- **Fix:** renomear `daemon` → `nodeapp` em Dockerfile + Dockerfile-compose alinhados

### 8.4 Source drift Hostinger ↔ CT 100
- Caso real 2026-05-13: ~15 commits drift descobertos na unha durante incidente
- **Mitigação:** `whatsapp:daemon-source-drift-check` cron weekly (segunda 09:00 BRT) alerta drift main↔CT 100 antes de virar bug compatibility

### 8.5 `--no-cache` quando mexer em Dockerfile/package.json
- Cache Docker mascara bugs (pacote não instalado, env var ausente)
- **Sempre `docker compose build --no-cache`** ao mudar dependências/Dockerfile

### 8.6 `build/.env` não existe
- API_KEY vem via Docker secret mount `/run/secrets/whatsapp_baileys_api_key`
- Config inteiramente via env vars do compose

### 8.7 `curl` ausente no container
- `docker exec whatsapp-baileys curl …` falha — sem curl instalado
- Testar `/health` de host CT 100 via container IP (`docker inspect`)

### 8.8 Endpoint `/instances` (listar) NÃO existe
- Só `/health` retorna lista de instances ativas

---

## 9. Webhook 404 retry policy (decisão controversa)

**`RETRYABLE_STATUS = {404, 408, 425, 429, 500, 502, 503, 504}`**

**POR QUE 404 está incluído** (PR mergeado 2026-05-13):
- Em burst de webhooks pra Hostinger com PHP-FPM saturado, retorno errôneo 404 disfarçando rate-limit transitório
- **Trade-off aceito:** se channel realmente não existe (UUID errado), retry 5x polui log — custo é só log. Sem retry, msgs históricas se perdem **definitivamente**.
- Decisão de risco: log noise < message loss

**Backoff:** `WEBHOOK_BACKOFF_BASE_MS * 3^(attempt-1) + jitter(0-200ms)` — exponential com jitter pra não trovejar.

---

## 10. CSAT pós-resolução (PR-6 CYCLE-07)

- `WHATSAPP_CSAT_ENABLED=true` default → dispara mensagem CSAT auto quando atendente marca conv como `resolved`
- Parser extrai score 1-5 do próximo inbound
- Opt-out simples via env per-business (Wagner desliga se cliente reclamar)
- Template per-business + opt-out granular em PR futuro

---

## 11. Bot Jana / slash commands (US-WA-074 família, ADR 0142)

- `WHATSAPP_BOT_ENABLED=false` default. Sprint 3 prep, ativa com ADS Universal
- 4 slash commands em notas internas:
  - `/corrigir` (US-WA-075) — training signal Jana
  - `/config bot=on|off` (US-WA-077) — toggle per-contato
  - `/lembrar` (US-WA-074) — grava em `memoria_facts`
  - `/lembrete` (US-WA-076) — agendado + cron hourly

---

## 12. History import gated (US-WA-080)

**Botão "Importar Histórico" na UI Settings/Channels:**
- Default **disabled** (custo Whisper transcription + risco ban se cliente esquecer)
- Gated por `business_id` em allowlist (clientes pagantes altos — Wagner request 2026-05-14)
- Cliente pequeno não vê o botão. Wagner libera caso a caso.

**LID backfill (US-WA-093 P1):** `whatsapp:lid-backfill` resolve LID→phone em `messages.payload` histórico (mensagens pré-fix).

---

## 13. Centrifugo realtime (ADR 0058)

- Channel legacy: `whatsapp:business:{id}` — publica via `PublishMessageReceivedToCentrifugo` / `PublishMessageSentToCentrifugo`
- Channel novo (US-WA-059 + ADR 0135): `omnichannel:business:{id}` — publica via `PublishOmnichannelToCentrifugo`
- JWT HS256 subscribe via `CentrifugoTokenIssuer`
- API HTTP: `POST {url}/api` com header `X-API-Key` + body `{"method":"publish",...}`
- URL canônica: `realtime.oimpresso.com` (NÃO `centrifugo.*` que é só nome do binary)

---

## 14. Anti-mídia-perdida — 6 camadas

1. **Camada 1:** webhook controller persiste media meta no `messages.payload` JSON
2. **Camada 2:** `whatsapp:health-probe-channels` daily 03:30 — detecta channels disconnected/banned
3. **Camada 3:** `whatsapp:reconnect-and-import` — auto-reconnect + import fallback 90d
4. **Camada 4:** Idempotência UNIQUE `provider_message_id` — dedup em retries
5. **Camada 5:** `whatsapp:scan-media-drift` daily — scan mídias falt no FS storage
6. **Camada 6 (bonus):** `whatsapp:backfill-media-download` + `whatsapp:reparse-media-from-payload` — recupera meta de payloads pré-PR #664

---

## 15. Drivers fallback gating (ADR 0096 emenda 5)

- **Mandatório fallback Meta Cloud configurado** pra drivers `zapi` e `baileys`
- FormRequest rejeita 422 se driver=baileys/zapi mas Meta Cloud não configurado
- **Bypass cirúrgico per-business:** `WHATSAPP_BYPASS_META_FALLBACK_BUSINESS_IDS=1,7` (CSV ints). ADR 0111 — preserva Tier 0 em todos outros businesses
- LGPD continua exigido em todos
- Auto-switch driver `degraded` → fallback (`auto_switch_after_status=degraded`)

---

## 16. Quando chamar quem (subagents)

- **`whatsapp-doctor`** — incidente operacional Baileys daemon (zombie, ban, QR fest, reconnect loop). SRE specialist
- **`whatsapp-arch-arte`** — auditoria de arquitetura técnica 15 dimensões + nota 0-100 + gap analysis vs estado-da-arte 2026
- **`whatsapp-baileys-expert`** — **nunca versionado em git** (descrito em session log, arquivo nunca commitado); para estabilidade operacional Baileys use `whatsapp-doctor` acima
- **`capterra-senior`** — comparativo features (não infra)

---

## 17. Quick reference — comandos artisan WhatsApp

```bash
# Healthcheck
php artisan whatsapp:driver-health-check-all
php artisan whatsapp:health-probe-channels             # daily 03:30 cron

# Reset / reconnect
php artisan whatsapp:channel-reset {id}                # 1-step purge + reset
php artisan whatsapp:channel-reset {id} --reconnect
php artisan whatsapp:reconnect-and-import {channel}

# Import histórico
php artisan whatsapp:import-history {channel} --days=90

# Backfill / repair
php artisan whatsapp:backfill-channel-access
php artisan whatsapp:backfill-media-download
php artisan whatsapp:reparse-media-from-payload
php artisan whatsapp:lid-backfill                      # US-WA-093
php artisan whatsapp:auto-link-conversation-contacts   # US-WA-078

# Drift / sync
php artisan whatsapp:channels-reconcile                # 5min cron
php artisan whatsapp:daemon-source-drift-check         # weekly cron

# Cleanup (cron hourly)
php artisan whatsapp:cleanup-webhook-nonces            # US-WA-082
php artisan whatsapp:jobs-cleanup-stale                # US-WA-084

# SLA / métricas
php artisan whatsapp:sla-scan                          # CYCLE-07 PR-2
php artisan whatsapp:metrics-aggregate                 # daily snapshot

# Permissions
php artisan whatsapp:register-permissions
```

---

## 18. Issues conhecidos sem fix (P0/P1)

- **P0** `useMultiFileAuthState` em prod — mitigado PR #701 mas migrar pra DB-backed `useMySQLAuthState` (~6h)
- **P0** Baileys 6.7.18 LIVE — não pular pra v7 ainda (breaking changes)
- **P1** ffmpeg server-side ausente — converter webm→opus/mp4 antes de `sendMessage` (WhatsApp prefere mp4)
- **P2** OTel SDK full (PECL ext) Hostinger — escopo evolução futura (container CT 101)

---

## 19. Referências essenciais

- **ADRs:** [0093](../decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0) · [0096](../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) (drivers) · [0117](../decisions/0117-multiplos-numeros-whatsapp-por-business.md) (multi-números) · [0135](../decisions/0135-omnichannel-inbox-arquitetura.md) (channel UUID path) · [0142](../decisions/0142-notas-internas-sinal-treino-jana.md) (slash commands) · [0058](../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md) (Centrifugo)
- **SPECs:** `memory/requisitos/Whatsapp/SPEC.md` · `memory/requisitos/Whatsapp/ARCHITECTURE.md`
- **Sessions:** `memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md` (ban loop incident) · `memory/sessions/2026-05-14-arte-wa-structure.md` (gap analysis NOTA 71/100) · `memory/sessions/2026-05-14-whatsapp-saga-madrugada.md` (queue inbox decision)
- **Runbooks:** `memory/runbooks/daemon-ct100-rebuild.md` · `.claude/agents/whatsapp-doctor.md`

---

**Última atualização:** 2026-05-14 — Onda 1 + Onda 2 gap analysis whatsapp-arch-arte mergedo (PR #834 + #835).
