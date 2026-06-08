# 2026-05-14 — Maratona WhatsApp: Ondas 1+2 (gap analysis) + OTel + cleanup

> Session log consolidado. ~3h de trabalho. **6 PRs merged**, nota Capterra **71 → 89/100 (+18pp)**, infra OTel deployada, ~330k linhas de protótipo morto removidas.

## TL;DR

| O que | Antes | Depois |
|---|---|---|
| Capterra whatsapp-arch-arte | 71/100 (gap -21pp) | **89/100** (gap -6pp) |
| `deploy.yml` Hostinger | bloqueado em route:cache há semanas | passa completo verde |
| OTel daemon→Laravel | NoOp tracer, headers vazios | Jaeger recebendo spans |
| Modules protótipo no repo | 2 (`Grow` + `IProduction`, ~330k linhas) | 0 |
| Webhook security (HMAC+nonce+backpressure) | inexistente | 3 middlewares em prod |
| Dashboard Grafana + Alertmanager rules | inexistente | 8 paineis + 10 alert rules |

## Ordem das ondas

### Onda 1 — Grafana + Replay Protection ([#834](https://github.com/wagnerra23/oimpresso.com/pull/834))
- US-WA-081 dashboard Grafana 8 paineis
- US-WA-082 HMAC + nonce + replay window middleware
- Tabela `webhook_nonces` migrada
- Cleanup cron hourly `whatsapp:cleanup-webhook-nonces`

### Onda 2 — OTel + Backpressure + Alertmanager ([#835](https://github.com/wagnerra23/oimpresso.com/pull/835))
- US-WA-083 OTel W3C traceparent propagation (lightweight bridge)
- US-WA-084 backpressure queue depth + drop policy 429
- US-WA-085 10 alert rules Prometheus + Alertmanager Slack

### Doc canônico ([#836](https://github.com/wagnerra23/oimpresso.com/pull/836))
- `memory/reference/whatsapp-baileys-messages-canonical.md` (370 linhas)

### Hotfix route collisions ([#837](https://github.com/wagnerra23/oimpresso.com/pull/837) + [#841](https://github.com/wagnerra23/oimpresso.com/pull/841))
- `bookings.index` Crm × Restaurant (sob `['as' => 'contact']`)
- `sells.*` duplicate dentro de `routes/web.php` linha 279 (comentado)
- `settings.*` AssetManagement × Manufacturing (AssetManagement → `asset.settings.*`)
- `client.*` Connector × Officeimpresso (Connector → `connector.client.*`)

### OTel end-to-end ([Jaeger CT 100](#jaeger-ct-100))
- Deploy Jaeger all-in-one `/opt/observability/jaeger/`
- Daemon Baileys env vars + network observability
- `whatsapp-baileys-daemon` aparece como service no Jaeger

### Remoção protótipos ([#842](https://github.com/wagnerra23/oimpresso.com/pull/842))
- `Modules/Grow/` (Worksuite-like, 50+ Route::resource colidindo)
- `Modules/IProduction/` (esqueleto Console/Http)
- **-2.514 arquivos / -330.570 linhas**

## Armadilhas catalogadas (CRÍTICO)

### A1. `deploy.yml` é frágil (irrecuperavelmente!)
**Sintoma:** falha em qualquer step pós `maintenance ON` → site fica DOWN (`php artisan up` não roda).

**Why:** sequência hardcoded:
```
1. maintenance ON (php artisan down)
2. git pull → composer → migrate → extra_artisan
3. Clear caches + re-cache  ← se falhar, tudo após não roda
4. maintenance OFF (php artisan up) ← NÃO RODA SE 3 FALHAR
```

**Workaround temporário (já usado 2026-05-14):** disparar deploy com `extra_artisan="up"` — roda **antes** do clear caches:
```bash
gh workflow run deploy.yml -f skip_backup=true -f skip_migrate=true -f extra_artisan="up"
```
→ site sobe mesmo se cache falhar.

**Fix permanente sugerido (US futura):** mover `maintenance OFF` para `if: always()` step, OU wrap todos artisan em `|| true` que loga warning sem abortar. Esforço ~30min.

### A2. `route:cache` failure = duplicação de route name (pré-existente)
**Sintoma:** `LogicException: Unable to prepare route [X/Y] for serialization. Another route has already been assigned name [Z].`

**Why:** Laravel `Route::resource()` 2x com mesma string gera mesmo route name. Laravel runtime aceita (último sobrescreve), mas serialização (`route:cache`) explode.

**Detection rápida:** `grep -rn "Route::resource(['\"]X" --include="*.php"` no projeto.

**Fix canônico:** `['as' => '<modulo>']` no segundo registro → prefixa names.

**Lista hoje catalogada (fechada 2026-05-14):**
- `bookings.*` — Crm + Restaurant ✓
- `sells.*` — routes/web.php duplicado ✓
- `settings.*` — AssetManagement + Manufacturing ✓
- `client.*` — Connector + Officeimpresso ✓

**Audit completo:** ao fazer feature nova que adiciona `Route::resource`, sempre grep + verifique se o nome já existe.

### A3. Daemon CT 100 = repo NÃO-git em `/opt/whatsapp-baileys/build/`
**Why:** source canon em `/opt/whatsapp-baileys/source/` (git), build em `/opt/whatsapp-baileys/build/` (cópia + Docker layer cache).

**Pegadinha:** esqueceu de copiar `source/Modules/Whatsapp/daemon-node/src/* → build/src/` antes de `docker compose build` → build com código antigo.

**Procedimento canônico em [`memory/reference/whatsapp-daemon-ct100.md`](../reference/whatsapp-daemon-ct100.md) § Deploy manual padrão.**

### A4. Daemon rebuild = risco de ban Multi-Device
**Sintoma:** instance connected pré-rebuild fica `banned` pós-restart (caso 2026-05-14: ch-9f675... Suporte). Logs mostram "Connection Failure decodeFrame noise-handler".

**Why:** WhatsApp detecta breve offline + re-handshake como suspeito. Mesmo com PR #685 auto-reconnect via meta.json, credentials podem ser rejeitadas.

**Mitigação:**
- Limite **~3 deploys daemon/dia** ([`feedback-daemon-max-deploys-day.md`](../reference/feedback-daemon-max-deploys-day.md))
- Backward-compat sempre que possível (Hostinger middleware HMAC aceita daemon sem headers — rollout gradual)
- Avisar Wagner antes de rebuild se Live channels conectadas

**Recovery:** `php artisan whatsapp:channel-reset {channel_id} --reconnect` OU `DELETE /instances/{id}` no daemon API + UI "Conectar" → escan QR.

### A5. `docker compose up -d --force-recreate` pode falhar com "container already in use"
**Sintoma:** `Error response: Conflict. The container name "/X" is already in use by container "Y"`.

**Why:** container antigo criado por compose anterior tem mesmo nome mas diferentes labels/networks.

**Fix seguro:** `docker stop X && docker rm X && docker compose up -d X` — **NÃO** use `docker run` ao invés (perde Traefik labels). Compose preserva labels do compose.yml.

### A6. `daemon-source-sha: unknown` em `/health`
**Why:** SHA é injetado via `--build-arg DAEMON_SOURCE_SHA=$(git rev-parse HEAD)` no build. Esquecer = `unknown`.

**Fix no script deploy daemon:** sempre `docker compose build --no-cache --build-arg DAEMON_SOURCE_SHA=$(cd ../source && git rev-parse HEAD) whatsapp-baileys`.

### A7. `OTEL_ENABLED=true` sem endpoint válido = ainda NoOp
**Why:** `daemon-node/src/observability/otel.ts`:
```ts
if (!env.OTEL_ENABLED || !env.OTEL_EXPORTER_OTLP_ENDPOINT) return;
```
Retorna early sem inicializar SDK → spans são NoOp → `traceparent` injetado é all-zeros.

**Fix correto:** ambos `OTEL_ENABLED=true` E `OTEL_EXPORTER_OTLP_ENDPOINT=http://jaeger:4318`. Daemon precisa estar na network `observability` que tem o Jaeger.

### A8. Daemon recriação perde network `observability`
**Sintoma:** após `docker compose up -d`, daemon volta a só `docker-host_default` (perde `observability`).

**Why:** compose-yml não tinha declaração das 2 networks até este PR. Compose silenciosamente recria sem extras.

**Fix:** garantir `networks: [default, observability]` em service block + declaração top-level `networks: observability: external: true`.

### A9. Pacote Docker image tag não-existente
**Sintoma:** `docker pull jaegertracing/all-in-one:1.62 → not found`.

**Why:** versão pinada não existe. Sempre conferir [docker hub tags](https://hub.docker.com/r/jaegertracing/all-in-one/tags) antes de pinar.

**Fix:** ajustar pra última estável (`1.60` funcionou 2026-05-14).

### A10. `DELETE /instances/{id}` NÃO limpa MySQL auth state (post PR #701 `useMySQLAuthState`)
**Sintoma:** purgar instance banned via daemon API + UI "Conectar" → daemon faz `logging in` (em vez de gerar QR) → ban silencioso → "QR não aparece".

**Why:** PR #701 introduziu `useMySQLAuthState` — credentials persistem em `whatsapp_baileys_auth_state` no MySQL Hostinger (~44 keys/instance). DELETE no daemon API limpa só socket em memória + FS sessions, **não toca MySQL**.

**Fix correto:** purgar AMBOS daemon API + MySQL. Procedimento completo em [`whatsapp-daemon-ct100.md`](../reference/whatsapp-daemon-ct100.md) § "Purgar session SEM restart daemon".

### A11. Reportar timestamps DB ao Wagner SEM converter pra tz business
**Sintoma:** Claude relata "last_message_at: 08:33 UTC ≈ 3h atrás" quando na verdade é 08:33 BRT (agora mesmo).

**Why:** oimpresso armazena timestamps em `America/Sao_Paulo` (não UTC) — `config('app.timezone') = America/Sao_Paulo`, e `business.time_zone = America/Sao_Paulo` por tenant. DB column JÁ é BRT. Claude ao ler raw via SQL/tinker assume UTC sem checar.

**Fix (Claude side):** sempre que reportar timestamp DB:
- Usar `Carbon::format('Y-m-d H:i:s T')` que inclui tz no string
- OU `$dt->diffForHumans()` que é tz-aware
- OU ler `business.time_zone` antes e converter explicitamente

**Princípio multi-tenant Wagner 2026-05-14:** TODA hora exibida deve vir do tz do business, sem exceção (UI, reports, API, e o chat do próprio Claude).

**Bandeira amarela arquitetural:** armazenar em `America/Sao_Paulo` em vez de UTC quebra quando precisar internacionalizar (cliente Argentina/Portugal). Fix futuro: DB sempre UTC + cast Eloquent com tz business no render.

## Comandos canônicos consolidados

### Trigger deploy.yml seguro
```bash
gh workflow run deploy.yml -f skip_backup=true -f extra_artisan="up"
```

### Recovery rápido site DOWN pós-deploy
```bash
gh workflow run deploy.yml -f skip_backup=true -f skip_migrate=true -f extra_artisan="up"
```

### Rebuild daemon CT 100 canônico
```bash
tailscale ssh root@ct100-mcp '
cd /opt/whatsapp-baileys/source && git pull origin main && cd .. &&
cp -r source/Modules/Whatsapp/daemon-node/src/* build/src/ &&
cp source/Modules/Whatsapp/daemon-node/Dockerfile build/Dockerfile &&
cp source/Modules/Whatsapp/daemon-node/package.json build/package.json &&
cp source/Modules/Whatsapp/daemon-node/package-lock.json build/package-lock.json &&
cp source/Modules/Whatsapp/daemon-node/tsconfig.json build/tsconfig.json &&
cd build && docker compose build --no-cache whatsapp-baileys &&
docker stop whatsapp-baileys; docker rm whatsapp-baileys;
docker compose up -d whatsapp-baileys &&
sleep 8 && curl -s http://172.18.0.16:3000/health
'
```

### Purge instance banned (sem rebuild)
```bash
tailscale ssh root@ct100-mcp 'KEY=$(docker exec whatsapp-baileys cat /run/secrets/whatsapp_baileys_api_key); curl -s -X DELETE -H "Authorization: Bearer $KEY" http://172.18.0.16:3000/instances/ch-XXX'
```

### Verificar OTel e2e
```bash
tailscale ssh root@ct100-mcp 'curl -s http://127.0.0.1:16686/api/services'
# Deve retornar {"data":["jaeger-all-in-one","whatsapp-baileys-daemon"],...}
```

### Acessar Jaeger UI (sem DNS configurado)
```bash
tailscale ssh -L 16686:127.0.0.1:16686 root@ct100-mcp
# Abre http://localhost:16686 no browser local
```

## Jaeger CT 100

**Localização:** `/opt/observability/jaeger/docker-compose.yml`

**Stack:**
- Imagem: `jaegertracing/all-in-one:1.60`
- Storage: in-memory, 50k traces (volátil — restart perde traces)
- OTLP HTTP: `:4318` (daemon usa este)
- OTLP gRPC: `:4317`
- UI: `:16686` (Traefik → `jaeger.oimpresso.com` quando DNS configurar)
- Network: `observability` (compartilhada com daemon Baileys)

**Healthcheck:** `wget -qO- http://localhost:14269/` (admin endpoint).

**Evolução futura (storage persistente):**
- Migrar pra Tempo + S3 backend (escala melhor) OU
- Jaeger com Elasticsearch backend (já no stack? langfuse-clickhouse... ClickHouse pode servir)
- Esforço ~4-6h

## Pendências bloqueadas pelo Wagner (não-código)

1. **Re-pair Suporte WhatsApp** — `https://oimpresso.com/atendimento/canais` → "Conectar" → escanear QR
2. **DNS `jaeger.oimpresso.com`** — CNAME pra mesmo IP do mcp.oimpresso.com (Cloudflare API)
3. **Tempo+Grafana stack** (evolução futura) — substitui Jaeger in-memory por persistente (US separada)

## Referências cruzadas

- **Nota gap analysis ANTERIOR (71/100):** [`memory/sessions/2026-05-14-arte-wa-structure.md`](2026-05-14-arte-wa-structure.md)
- **Nota gap analysis REAVALIADA (86 → 89 com OTel):** [`memory/sessions/2026-05-14-arte-wa-structure-reavaliacao-pos-ondas.md`](2026-05-14-arte-wa-structure-reavaliacao-pos-ondas.md)
- **Doc canônico WhatsApp Baileys mensagens:** [`memory/reference/whatsapp-baileys-messages-canonical.md`](../reference/whatsapp-baileys-messages-canonical.md)
- **Doc canônico daemon CT 100:** [`memory/reference/whatsapp-daemon-ct100.md`](../reference/whatsapp-daemon-ct100.md)

## ADRs relevantes

- [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 multi-tenant (HMAC + nonce + backpressure todos respeitam)
- [ADR 0096](../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) — Drivers gating (zapi/meta_cloud/baileys autorizados)
- [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal (justifica não fazer horizontal scale agora)
- [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md) — Session handoff append-only
- [ADR 0131](../decisions/0131-tiering-memoria-canonico-local-segredo.md) — Knowledge canônico em git (este doc)
