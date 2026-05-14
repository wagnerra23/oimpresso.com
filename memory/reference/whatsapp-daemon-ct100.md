---
name: Whatsapp Daemon Baileys CT 100 — source/deploy/endpoints/anti-QR-fest/purge API/canais prod
description: Daemon Baileys CT 100 (Baileys 6.7.18 + Fastify + TypeScript) — onde mora, endpoints canônicos, deploy padrão, purge sem restart, anti-QR-fest PRs #685+#686, Multi-Device unified inbox PR #688, states semântica, erros comuns
type: reference
---
# Whatsapp Daemon CT 100 (Baileys 6.7.18 + Fastify + TypeScript)

## Canais em prod biz=1 (estado 2026-05-12)

| ID | Label | Phone | UUID | Tipo |
|---|---|---|---|---|
| **#2** | "Suorte" (typo "Suporte") | 554888782087 | `da8c23c5-5a6c-4538-b82f-1a05c47ac5da` | whatsapp_baileys |
| **#3** | "Suporte" | 554896486699 | `3bcafcfc-7506-48cd-843d-72116460d95b` | whatsapp_baileys |

**Instance ID = `'ch-' + str_replace('-', '', channel_uuid)`** (sem hifens).

## Onde fica (CT 100)

- **Source**: `/opt/whatsapp-baileys/source/Modules/Whatsapp/daemon-node/`
- **Build/deploy**: `/opt/whatsapp-baileys/build/docker-compose.yml`
- **Container**: `whatsapp-baileys` (single, healthcheck Up)
- **Sessions FS (bind mount)**: `/srv/docker/whatsapp-baileys/sessions/ch-<uuid_sem_hifens>/` no host (NÃO docker volume — caí 2026-05-12 tentando deletar pelo volume errado)
- **Hostname**: `whatsapp-baileys.oimpresso.com` (Traefik, IP whitelist `148.135.133.115/32` = Hostinger)
- **API key**: secret `/run/secrets/whatsapp_baileys_api_key` (Bearer global)
- **IP interno container**: rede `docker-host_default`, IP típico `172.18.0.16` (verificar com `docker inspect`)

## Endpoints canônicos

- `GET /health` — `{status, uptime_seconds, instances:[{id,state,session_age_seconds}]}` (sem auth)
- `POST /instances/:id/connect` — body `{business_uuid, business_id}` → state: `connecting`/`qr_required`/`connected`/`banned`
- `GET /instances/:id/status` — snapshot atual (com `qr` field quando `qr_required`)
- `DELETE /instances/:id` — **purga session sem restart daemon** (preserva outras instances ativas). Equivalente a `manager.purge(id)`. Force fresh QR no próximo connect
- `POST /instances/:id/disconnect` — logout graceful (auth state preservado pra reconnect)
- `POST /instances/:id/text` — outbound texto
- `POST /instances/:id/media` — outbound mídia. Schema: `{to, media_url URL, type, mimetype?, caption?, filename?}` — desde PR #692 (2026-05-12) aceita AMBAS `mime` E `mimetype` (back-compat 30d, expira 2026-06-12)
- `POST /media/decrypt-url` — stateless decrypt Baileys cripto inbound (PR #669)
- `POST /instances/:id/history` — fetchMessageHistory 90d (PR #683)
- `POST /instances/:id/pairing-code` — fallback se QR não renderiza

## Deploy manual padrão (Wagner — pegadinha cache)

```bash
tailscale ssh root@ct100-mcp 'cd /opt/whatsapp-baileys && \
  cd source && git pull origin main && cd .. && \
  cp -r source/Modules/Whatsapp/daemon-node/src/* build/src/ && \
  cd build && docker compose build --no-cache whatsapp-baileys && \
  docker compose up -d whatsapp-baileys && \
  sleep 6 && \
  KEY=<chave do Vaultwarden> && \
  IP=$(docker inspect whatsapp-baileys --format "{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}") && \
  curl -s "http://$IP:3000/health"'
```

**Cuidados críticos**:
- **SEMPRE `--no-cache`** quando mexer em Dockerfile/package.json (cache mascara bugs — caí 2026-05-12)
- **SEMPRE `cp -r source/.../src build/src/`** ANTES de build (build/ é cópia separada — caí 2026-05-12 com /media/decrypt-url 404)
- **`build/.env` NÃO existe** (`cd build && cat .env` → "no such file"); api_key vem via Docker secret mount; daemon `/app/.env` também não tem (config inteiramente via env vars do compose)
- **Daemon CLI sem `curl` no container** (`docker exec whatsapp-baileys curl` falha) — testar de host via container IP
- **Endpoint `/instances` (listar) NÃO existe** — só `/health` retorna lista de instances ativas

## Purgar session SEM restart daemon (preserva outras instances)

```bash
KEY=<chave do Vaultwarden>
IP=$(docker inspect whatsapp-baileys --format "{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}")
# Purga só uma instance (UI: clica "Conectar" depois gera QR fresh)
curl -s -X DELETE -H "Authorization: Bearer $KEY" \
  "http://$IP:3000/instances/ch-<uuid_sem_hifens>"
# Equivalente a deletar pasta /srv/docker/whatsapp-baileys/sessions/ch-<uuid>/
# MAS sem força daemon a restart (outras instances continuam conectadas)
```

## Anti-QR-fest (PRs #685 + #686 mergeados 2026-05-12)

**PR #685** (`feat(daemon): auto-reconnect instances ao boot`):
- `InstanceManager.bootstrap()` ao server start scan `SESSIONS_DIR` por `ch-*`
- Lê `meta.json` ao lado da session → re-disparam `connect()` em background
- `Instance.persistMeta()` grava `meta.json` (business_uuid+business_id+last_connected_at, mode 0o600) toda vez que `connection.update === 'open'`
- **Primeira boot pós-deploy**: sessions legacy SEM meta.json são SKIPADAS (`bootstrap: sessão legacy sem meta.json — skip (usuário precisa clicar Conectar 1x)`)
- Após o primeiro pair manual, meta.json é criado → próximos restarts auto-resumem invisivelmente

**PR #686** (`feat(whatsapp): Camadas 2+4 self-healing`):
- `php artisan whatsapp:health-probe-channels` daily 03:30 BRT — itera Channels active, 3 retries com backoff 1s/5s/30s, marca `disconnected`/`recovered`/`banned`/`skipped`
- `php artisan whatsapp:reconnect-and-import {channel} --since=auto` — reconnect + wait `state=connected` + invoke import-history (auto = última msg DB +1h, fallback 90d)
- Banned NUNCA tenta `/connect` (escalation manual)

## Multi-Device unified inbox (PR #688 mergeado 2026-05-12)

Daemon emite tanto inbound (`fromMe=false`) quanto outbound (`fromMe=true`) no webhook event `message`. Webhook controller já era designed pra receber ambos:
- `direction = $fromMe ? 'outbound' : 'inbound'`
- `status = $fromMe ? 'sent' : 'received'`
- `sender_kind = $fromMe ? 'human' : null`
- Dedup contra eco do próprio `sendMessage()` via `firstOrCreate(['business_id', 'provider_message_id'])` UNIQUE `msgs_provider_msg_uniq`

Resultado: áudio/foto/texto mandado pelo WA Web/celular do mesmo número cai na inbox unificada `/atendimento/inbox` como outbound. Pest regression test: `WebhookOutboundFromMeRegressionTest.php` (PR #692).

## States daemon (semântica)

- `connecting` — handshake Baileys em andamento (8-15s típico)
- `qr_required` — aguardando scan QR (válido ~20s, auto-renova)
- `connected` — conectado e enviando/recebendo
- `disconnected` — desconectado intencional (logout via API) OU rede caiu (auto-reconnect tentando)
- `banned` — `ban_reason: "logged_out"` quando WhatsApp envia `stream:error code=401 conflict device_removed` (device removido server-side, sessions stale, ou mass re-handshake disparou anti-abuse)

## Erros comuns log daemon

- `MessageCounterError: Key used already or never filled` — signal protocol desync (re-pair QR; mais recorrente pré-upgrade 6.7.18)
- `Stream Errored (restart required) code:515` — normal pós-pairing (Baileys reinicia conexão sozinho, ~5s)
- `Stream Errored (conflict) code:401 device_removed` — **banned forever pra essa session**; purge + re-pair QR
- `Timed Out` em `executeInitQueries` (chats.js:631 fetchProps) — handshake parcial; reconnect automático tenta de novo
- `failed to sync state from version, removing and trying from scratch` em `regular_low` — NORMAL durante initial sync (history download)
- `Connection Failure` no `decodeFrame` do noise-handler — credentials rejected pelo WA (logged_out OR keys stale >24h offline)

## Decrypt mediaKey (endpoint /media/decrypt-url)

- SDK: `import { downloadContentFromMessage } from '@whiskeysockets/baileys'`
- Retorna `AsyncIterable<Buffer>` — stream
- Stateless (não exige instance conectada — bom pra reprocessar backlog)
- Rate limit in-memory 100/min global

## Issues conhecidos sem fix ainda (P0/P1 — relatório 2026-05-12)

- **P0**: `useMultiFileAuthState` em prod — upstream Baileys diz "Don't ever use in production". Migrar pra DB-backed (`useMySQLAuthState` custom) — ~6h (status: PR #701 deployado 2026-05-12, ver project-sessao-2026-05-12-23-prs.md)
- **P0**: Baileys 6.7.18 LIVE (não pular pra v7 ainda — breaking changes)
- **P1**: Zero anti-ban patterns. Pacote `baileys-antiban` disponível (jitter Gaussian 1.5-4s + typing presence + 7d warmup chip novo) — PR #699 deployado
- **P1**: LID resolution implementado custom (PRs #696 + #698)
- **P1**: ffmpeg server-side ausente pra converter webm→opus/mp4 antes do `sendMessage()` (WhatsApp prefere mp4)
- **P2**: ~~Observabilidade — daemon `/metrics` Prometheus existe mas sem dashboard Grafana ou alertas configurados~~ **RESOLVIDO 2026-05-14** (Onda 1+2 gap analysis whatsapp-arch-arte): dashboard `infra/grafana/dashboards/whatsapp-baileys.json` 8 paineis + 10 alert rules `infra/prometheus/alerts/whatsapp.yml` + OTel traceparent propagation + webhook backpressure 429. Detalhes em [whatsapp-baileys-messages-canonical.md](whatsapp-baileys-messages-canonical.md). **OTel ponta-a-ponta:** Jaeger all-in-one rodando em `/opt/observability/jaeger/`, daemon emite spans via `OTEL_EXPORTER_OTLP_ENDPOINT=http://jaeger:4318` — veja [observability-jaeger-ct100.md](observability-jaeger-ct100.md)

## OTel + Jaeger (2026-05-14 — US-WA-083 fechado)

Daemon agora emite spans OTel pra Jaeger CT 100:

- **Env vars no compose** (`/opt/whatsapp-baileys/build/docker-compose.yml`):
  - `OTEL_ENABLED=true`
  - `OTEL_EXPORTER_OTLP_ENDPOINT=http://jaeger:4318`
  - `OTEL_SERVICE_NAME=whatsapp-baileys-daemon`
- **Network:** daemon precisa estar em `observability` network (junto com `default`/`docker-host_default`)
- **Validação rápida:** `curl http://127.0.0.1:16686/api/services` no CT 100 deve listar `whatsapp-baileys-daemon`
- **Verificação `/health`:** retorna `"otel": true` quando SDK iniciou OK (vs `false` quando env ausente)

**Pegadinhas:**
- `OTEL_ENABLED=true` SEM `OTEL_EXPORTER_OTLP_ENDPOINT` → SDK retorna early, spans NoOp (all-zeros traceparent)
- Após `docker compose up -d` daemon pode perder a network `observability` se ela não estiver declarada explicitamente no top-level `networks:` da compose-yml
- Para Jaeger receber e2e: daemon precisa ter código com `propagation.inject` (post-Onda 2, source `5e0c90e1` ou later)

Doc dedicado: [observability-jaeger-ct100.md](observability-jaeger-ct100.md).

## Subagent dedicado

`.claude/agents/whatsapp-baileys-expert.md` (300+ linhas) — invoque com `Agent({subagent_type: 'whatsapp-baileys-expert', ...})` em sessões futuras pra trabalho em estabilidade operacional Baileys.
